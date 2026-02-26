<?php
/**
 * SILO Product Cards — Data Source.
 *
 * Fetches and normalises product data from one of three sources:
 *   1. Manual   — ACF fields on the product CPT
 *   2. Scrape   — wp_remote_get() the product page, parse with configurable selectors, cache
 *   3. API      — Magento 2 REST API (stubbed for future)
 *
 * Scrape selectors are fully configurable from the admin settings page.
 * In scrape/api modes, ACF field values act as overrides.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Silo_PC_Data_Source {

    /**
     * Bump this when default_selectors() changes so existing installs
     * pick up the new defaults on the next plugin activation/update.
     */
    const SELECTOR_VERSION = 2;

    /**
     * Default scrape selectors, pre-configured for Magento 2.
     *
     * @return array
     */
    public static function default_selectors() {
        return array(
            array(
                'field'     => 'price',
                'type'      => 'xpath',
                'selector'  => '//*[contains(@class, "normal-price")]//*[contains(@class, "price-wrapper")][@data-price-amount]',
                'extract'   => 'attribute',
                'attribute' => 'data-price-amount',
                'multiple'  => false,
                'transform' => 'float',
            ),
            array(
                'field'     => 'compare_at_price',
                'type'      => 'xpath',
                'selector'  => '//*[contains(@class, "old-price")]//*[contains(@class, "price-wrapper")][@data-price-amount]',
                'extract'   => 'attribute',
                'attribute' => 'data-price-amount',
                'multiple'  => false,
                'transform' => 'float',
            ),
            array(
                'field'     => 'title',
                'type'      => 'xpath',
                'selector'  => '//h1[contains(@class, "page-title")]//span',
                'extract'   => 'text',
                'attribute' => '',
                'multiple'  => false,
                'transform' => 'none',
            ),
            array(
                'field'     => 'title',
                'type'      => 'css',
                'selector'  => 'title',
                'extract'   => 'text',
                'attribute' => '',
                'multiple'  => false,
                'transform' => 'strip_site_suffix',
            ),
            array(
                'field'     => 'description',
                'type'      => 'xpath',
                'selector'  => '//*[contains(@class, "product") and contains(@class, "description")]//*[contains(@class, "value")]',
                'extract'   => 'text',
                'attribute' => '',
                'multiple'  => false,
                'transform' => 'truncate',
            ),
            array(
                'field'     => 'description',
                'type'      => 'xpath',
                'selector'  => '//meta[@name="description"]',
                'extract'   => 'attribute',
                'attribute' => 'content',
                'multiple'  => false,
                'transform' => 'none',
            ),
            array(
                'field'     => 'images',
                'type'      => 'xpath',
                'selector'  => '//*[contains(@class, "gallery-placeholder")]//img[@src]',
                'extract'   => 'attribute',
                'attribute' => 'src',
                'multiple'  => true,
                'transform' => 'none',
            ),
            array(
                'field'     => 'images',
                'type'      => 'xpath',
                'selector'  => '//*[contains(@class, "fotorama")]//img[@src]',
                'extract'   => 'attribute',
                'attribute' => 'src',
                'multiple'  => true,
                'transform' => 'none',
            ),
            // Rating: Magento default.
            array(
                'field'     => 'rating',
                'type'      => 'xpath',
                'selector'  => '//*[contains(@class, "rating-result")]//span',
                'extract'   => 'attribute',
                'attribute' => 'style',
                'multiple'  => false,
                'transform' => 'percent_to_rating',
            ),
            // Rating: Schema.org structured data (Yotpo, etc.).
            array(
                'field'     => 'rating',
                'type'      => 'xpath',
                'selector'  => '//*[@itemprop="ratingValue"]',
                'extract'   => 'text',
                'attribute' => '',
                'multiple'  => false,
                'transform' => 'float',
            ),
            // Review count: Magento default.
            array(
                'field'     => 'review_count',
                'type'      => 'xpath',
                'selector'  => '//*[contains(@class, "reviews-actions")]//*[contains(@class, "action")]',
                'extract'   => 'text',
                'attribute' => '',
                'multiple'  => false,
                'transform' => 'regex_number',
            ),
            // Review count: Schema.org structured data (Yotpo, etc.).
            array(
                'field'     => 'review_count',
                'type'      => 'xpath',
                'selector'  => '//*[@itemprop="reviewCount"]',
                'extract'   => 'text',
                'attribute' => '',
                'multiple'  => false,
                'transform' => 'int',
            ),
        );
    }

    /**
     * Get product data for a product CPT post.
     *
     * @param int $product_id The silo_product post ID.
     * @return array Normalised product data matching the card JSON schema.
     */
    public static function get_product_data( $product_id ) {
        $fields = Silo_PC_CPT::get_all_meta( $product_id );

        $source = ! empty( $fields['data_source'] ) ? $fields['data_source'] : 'manual';

        switch ( $source ) {
            case 'scrape':
                return self::get_scrape_data( $fields );

            case 'api':
                return self::get_api_data( $fields );

            case 'manual':
            default:
                return self::normalise( $fields, array() );
        }
    }

    /**
     * Scrape mode.
     */
    private static function get_scrape_data( $fields ) {
        $url = ! empty( $fields['product_url'] ) ? $fields['product_url'] : '';

        if ( empty( $url ) ) {
            return self::normalise( $fields, array() );
        }

        $cache_key = 'silo_pc_scrape_' . md5( $url );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached && is_array( $cached ) ) {
            return self::normalise( $fields, $cached );
        }

        $response = wp_remote_get( $url, array(
            'timeout'    => 15,
            'user-agent' => 'Mozilla/5.0 (compatible; SiloBot/1.0)',
        ) );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return self::normalise( $fields, array() );
        }

        $html             = wp_remote_retrieve_body( $response );
        $custom_selectors = self::resolve_selectors( $fields );
        $scraped          = self::parse_product_page( $html, $custom_selectors );

        $ttl = (int) get_option( 'silo_pc_cache_ttl', 6 );
        set_transient( $cache_key, $scraped, $ttl * HOUR_IN_SECONDS );

        return self::normalise( $fields, $scraped );
    }

    /**
     * API mode — stubbed.
     */
    private static function get_api_data( $fields ) {
        $sku       = ! empty( $fields['product_sku'] ) ? $fields['product_sku'] : '';
        $api_url   = get_option( 'silo_pc_magento_api_url', '' );
        $api_token = get_option( 'silo_pc_magento_api_token', '' );

        if ( empty( $sku ) || empty( $api_url ) || empty( $api_token ) ) {
            if ( ! empty( $fields['product_url'] ) ) {
                return self::get_scrape_data( $fields );
            }
            return self::normalise( $fields, array() );
        }

        $cache_key = 'silo_pc_api_' . sanitize_key( $sku );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached && is_array( $cached ) ) {
            return self::normalise( $fields, $cached );
        }

        // TODO: Implement when API access is granted.
        if ( ! empty( $fields['product_url'] ) ) {
            return self::get_scrape_data( $fields );
        }
        return self::normalise( $fields, array() );
    }

    /**
     * Resolve which selectors to use: per-card custom or global.
     */
    private static function resolve_selectors( $fields ) {
        if ( ! empty( $fields['custom_selectors'] ) ) {
            $custom = $fields['custom_selectors'];
            if ( is_string( $custom ) ) {
                $custom = json_decode( $custom, true );
            }
            if ( is_array( $custom ) && ! empty( $custom ) ) {
                return $custom;
            }
        }
        return null;
    }

    /**
     * Parse a product page using configurable scrape selectors.
     *
     * @param string     $html      Raw HTML of the product page.
     * @param array|null $overrides Per-card selectors, or null to use global.
     */
    private static function parse_product_page( $html, $overrides = null ) {
        $data = array();

        libxml_use_internal_errors( true );
        $doc = new DOMDocument();
        $doc->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
        libxml_clear_errors();

        $xpath = new DOMXPath( $doc );

        if ( ! empty( $overrides ) && is_array( $overrides ) ) {
            $selectors = $overrides;
        } else {
            $selectors = get_option( 'silo_pc_scrape_selectors' );
            if ( empty( $selectors ) || ! is_array( $selectors ) ) {
                $selectors = self::default_selectors();
            }
        }

        foreach ( $selectors as $sel ) {
            $field = $sel['field'];

            // First match wins — fallback selectors only fire when earlier ones miss.
            if ( ! empty( $data[ $field ] ) ) {
                continue;
            }

            $nodes = self::query_nodes( $doc, $xpath, $sel );
            if ( ! $nodes || $nodes->length === 0 ) {
                continue;
            }

            $multiple = ! empty( $sel['multiple'] );

            if ( $multiple ) {
                $values = array();
                foreach ( $nodes as $node ) {
                    $raw = self::extract_value( $node, $sel );
                    if ( ! empty( $raw ) ) {
                        $values[] = self::apply_transform( $raw, $sel['transform'] );
                    }
                }
                if ( ! empty( $values ) ) {
                    $data[ $field ] = array_values( array_unique( $values ) );
                }
            } else {
                $raw = self::extract_value( $nodes->item( 0 ), $sel );
                if ( '' !== $raw && null !== $raw ) {
                    $data[ $field ] = self::apply_transform( $raw, $sel['transform'] );
                }
            }
        }

        return $data;
    }

    /**
     * Run a selector query against the document.
     */
    private static function query_nodes( $doc, $xpath, $sel ) {
        $type     = ! empty( $sel['type'] ) ? $sel['type'] : 'xpath';
        $selector = ! empty( $sel['selector'] ) ? $sel['selector'] : '';

        if ( empty( $selector ) ) {
            return false;
        }

        if ( 'css' === $type ) {
            $selector = self::css_to_xpath( $selector );
            if ( empty( $selector ) ) {
                return false;
            }
        }

        $result = @$xpath->query( $selector );
        return ( false !== $result ) ? $result : false;
    }

    /**
     * Extract a value from a DOM node.
     */
    private static function extract_value( $node, $sel ) {
        $extract = ! empty( $sel['extract'] ) ? $sel['extract'] : 'text';

        switch ( $extract ) {
            case 'attribute':
                $attr = ! empty( $sel['attribute'] ) ? $sel['attribute'] : '';
                return $attr ? trim( $node->getAttribute( $attr ) ) : '';

            case 'html':
                $inner = '';
                foreach ( $node->childNodes as $child ) {
                    $inner .= $node->ownerDocument->saveHTML( $child );
                }
                return trim( $inner );

            case 'text':
            default:
                return trim( $node->textContent );
        }
    }

    /**
     * Apply a transform to a raw scraped value.
     */
    private static function apply_transform( $value, $transform ) {
        if ( empty( $transform ) || 'none' === $transform ) {
            return $value;
        }

        switch ( $transform ) {
            case 'float':
                return (float) $value;
            case 'int':
                return (int) $value;
            case 'truncate':
                return ( strlen( $value ) > 200 ) ? substr( $value, 0, 197 ) . '...' : $value;
            case 'strip_site_suffix':
                return preg_replace( '/\s*[\|\-\x{2013}\x{2014}]\s*.+$/u', '', $value );
            case 'percent_to_rating':
                if ( preg_match( '/(\d+(?:\.\d+)?)%/', $value, $m ) ) {
                    return round( ( (float) $m[1] / 100 ) * 5, 2 );
                }
                return 0;
            case 'regex_number':
                if ( preg_match( '/(\d+)/', $value, $m ) ) {
                    return (int) $m[1];
                }
                return 0;
            default:
                return $value;
        }
    }

    /**
     * Convert a simple CSS selector to XPath.
     */
    private static function css_to_xpath( $css ) {
        $css = trim( $css );
        if ( empty( $css ) ) {
            return '';
        }

        $parts  = preg_split( '/\s+/', $css );
        $xparts = array();

        foreach ( $parts as $part ) {
            $xp = '';
            $attr_selector = '';

            if ( preg_match( '/^(.*?)(\[.+\])$/', $part, $am ) ) {
                $part = $am[1];
                $attr_selector = preg_replace( '/\[([a-zA-Z])/', '[@$1', $am[2] );
            }

            if ( preg_match( '/^([a-zA-Z0-9-]*)#([a-zA-Z0-9_-]+)(.*)$/', $part, $m ) ) {
                $tag  = $m[1] ?: '*';
                $xp   = $tag . '[@id="' . $m[2] . '"]';
                $part = $m[3];
            }

            if ( preg_match_all( '/\.([a-zA-Z0-9_-]+)/', $part, $cm ) ) {
                $tag = preg_replace( '/\..*/', '', $part );
                if ( empty( $tag ) && empty( $xp ) ) {
                    $tag = '*';
                }
                if ( ! empty( $tag ) && empty( $xp ) ) {
                    $xp = $tag;
                }
                foreach ( $cm[1] as $cls ) {
                    $xp .= '[contains(@class, "' . $cls . '")]';
                }
            } elseif ( empty( $xp ) ) {
                $xp = ! empty( $part ) ? $part : '*';
            }

            if ( $attr_selector ) {
                $xp .= $attr_selector;
            }

            $xparts[] = $xp;
        }

        return '//' . implode( '//', $xparts );
    }

    /**
     * Normalise data into the card JSON schema.
     * Post meta fields override scraped/API data.
     */
    private static function normalise( $fields, $fetched ) {
        $pick = function ( $key, $fetched_key = null, $default = '' ) use ( $fields, $fetched ) {
            if ( null === $fetched_key ) {
                $fetched_key = $key;
            }
            if ( ! empty( $fields[ $key ] ) ) {
                return $fields[ $key ];
            }
            if ( ! empty( $fetched[ $fetched_key ] ) ) {
                return $fetched[ $fetched_key ];
            }
            return $default;
        };

        // Title: CPT post title, falling back to scraped title.
        $title = ! empty( $fields['_post_title'] ) ? $fields['_post_title'] : '';
        if ( empty( $title ) && ! empty( $fetched['title'] ) ) {
            $title = $fetched['title'];
        }

        // Images: stored as newline-separated URLs in a textarea field.
        $images = array();
        if ( ! empty( $fields['images'] ) ) {
            if ( is_string( $fields['images'] ) ) {
                $lines = preg_split( '/[\r\n]+/', $fields['images'] );
                foreach ( $lines as $line ) {
                    $line = trim( $line );
                    if ( ! empty( $line ) && filter_var( $line, FILTER_VALIDATE_URL ) ) {
                        $images[] = $line;
                    }
                }
            } elseif ( is_array( $fields['images'] ) ) {
                $images = $fields['images'];
            }
        }
        if ( empty( $images ) && ! empty( $fetched['images'] ) ) {
            $images = $fetched['images'];
        }

        $price            = (float) $pick( 'price', 'price', 0 );
        $compare_at_price = (float) $pick( 'compare_at_price', 'compare_at_price', 0 );

        return array(
            'title'              => $title,
            'description'        => $pick( 'description' ),
            'url'                => ! empty( $fields['product_url'] ) ? $fields['product_url'] : '',
            'images'             => $images,
            'price'              => $price,
            'compareAtPrice'     => $compare_at_price > 0 ? $compare_at_price : '',
            'currency'           => ! empty( $fields['currency'] ) ? $fields['currency'] : 'USD',
            'badge'              => $pick( 'badge' ),
            'rating'             => (float) $pick( 'rating', 'rating', 0 ),
            'reviewCount'        => (int) $pick( 'review_count', 'review_count', 0 ),
            'showReaderDiscount' => ! empty( $fields['show_discount'] ) && '1' === $fields['show_discount'],
            'discountCode'       => $pick( 'discount_code' ),
            'discountPercent'    => (int) $pick( 'discount_percent' ),
            'discountLabel'      => $pick( 'discount_label' ),
            'trackingSource'     => $pick( 'tracking_source', 'tracking_source', get_option( 'silo_pc_default_tracking', 'blog_product_card' ) ),
            'fetchLivePrice'     => ! empty( $fields['fetch_live_price'] ) && '1' === $fields['fetch_live_price'],
        );
    }
}
