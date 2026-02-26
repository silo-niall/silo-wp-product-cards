<?php
/**
 * SILO Product Cards — Custom Post Type + Meta Boxes.
 *
 * Fully self-contained — no ACF dependency.
 * Registers the 'silo_product' CPT and native meta boxes for all product data.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Silo_PC_CPT {

    /** Meta key prefix. */
    const META_PREFIX = '_silo_pc_';

    /** All product meta fields with their defaults. */
    private static $fields = array(
        'data_source'      => 'manual',
        'product_url'      => '',
        'product_sku'      => '',
        'description'      => '',
        'images'           => '',
        'price'            => '',
        'compare_at_price' => '',
        'currency'         => 'USD',
        'badge'            => '',
        'rating'           => '',
        'review_count'     => '',
        'tracking_source'  => '',
        'show_discount'    => '0',
        'discount_code'    => '',
        'discount_percent' => '',
        'discount_label'   => '',
        'fetch_live_price' => '0',
        'custom_selectors' => '',
    );

    public function __construct() {
        add_action( 'init', array( __CLASS__, 'register_post_type' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post_silo_product', array( $this, 'save_meta' ), 10, 2 );

        // Admin columns.
        add_filter( 'manage_silo_product_posts_columns', array( $this, 'admin_columns' ) );
        add_action( 'manage_silo_product_posts_custom_column', array( $this, 'admin_column_content' ), 10, 2 );
    }

    /**
     * Register the silo_product CPT.
     */
    public static function register_post_type() {
        register_post_type( 'silo_product', array(
            'labels' => array(
                'name'               => 'Products',
                'singular_name'      => 'Product',
                'add_new'            => 'Add Product',
                'add_new_item'       => 'Add New Product',
                'edit_item'          => 'Edit Product',
                'new_item'           => 'New Product',
                'view_item'          => 'View Product',
                'search_items'       => 'Search Products',
                'not_found'          => 'No products found',
                'not_found_in_trash' => 'No products found in Trash',
                'all_items'          => 'All Products',
                'menu_name'          => 'Product Cards',
            ),
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'menu_position'      => 25,
            'menu_icon'          => 'dashicons-cart',
            'supports'           => array( 'title', 'thumbnail' ),
            'has_archive'        => false,
            'rewrite'            => false,
            'show_in_rest'       => true,
            'capability_type'    => 'post',
        ) );
    }

    /**
     * Get a product meta value.
     *
     * @param int    $post_id
     * @param string $key Field name (without prefix).
     * @return mixed
     */
    public static function get_meta( $post_id, $key ) {
        $default = isset( self::$fields[ $key ] ) ? self::$fields[ $key ] : '';
        $value   = get_post_meta( $post_id, self::META_PREFIX . $key, true );
        return ( '' !== $value && false !== $value ) ? $value : $default;
    }

    /**
     * Get all product meta as an associative array.
     *
     * @param int $post_id
     * @return array
     */
    public static function get_all_meta( $post_id ) {
        $data = array();
        foreach ( self::$fields as $key => $default ) {
            $data[ $key ] = self::get_meta( $post_id, $key );
        }
        $data['_post_title'] = get_the_title( $post_id );
        return $data;
    }

    /**
     * Register meta boxes.
     */
    public function add_meta_boxes() {
        add_meta_box(
            'silo_pc_source',
            'Data Source',
            array( $this, 'render_source_box' ),
            'silo_product',
            'normal',
            'high'
        );

        add_meta_box(
            'silo_pc_product_info',
            'Product Info',
            array( $this, 'render_info_box' ),
            'silo_product',
            'normal',
            'default'
        );

        add_meta_box(
            'silo_pc_pricing',
            'Pricing',
            array( $this, 'render_pricing_box' ),
            'silo_product',
            'normal',
            'default'
        );

        add_meta_box(
            'silo_pc_usage',
            'Usage',
            array( $this, 'render_usage_box' ),
            'silo_product',
            'side',
            'high'
        );

        add_meta_box(
            'silo_pc_reviews',
            'Reviews',
            array( $this, 'render_reviews_box' ),
            'silo_product',
            'side',
            'default'
        );

        add_meta_box(
            'silo_pc_discount',
            'Discount Code',
            array( $this, 'render_discount_box' ),
            'silo_product',
            'normal',
            'default'
        );

        add_meta_box(
            'silo_pc_selectors',
            'Custom Scrape Selectors',
            array( $this, 'render_selectors_box' ),
            'silo_product',
            'normal',
            'low'
        );
    }

    // --- Meta box renderers ---

    public function render_usage_box( $post ) {
        $post_id = $post->ID;
        $is_new  = 'auto-draft' === $post->post_status;
        ?>
        <p><strong>Embed this product</strong></p>
        <?php if ( $is_new ) : ?>
            <p class="description">Publish this product first, then a shortcode will appear here.</p>
        <?php else : ?>
            <p>Paste into any post or page:</p>
            <code style="display:block;padding:8px;background:#f6f7f7;margin:4px 0 8px;font-size:13px;user-select:all">[silo_products ids="<?php echo esc_attr( $post_id ); ?>"]</code>
            <p class="description">Or assign this product to a post via its <strong>Product Cards</strong> sidebar panel — no shortcode needed if auto-insert is on.</p>
        <?php endif; ?>
        <?php
    }

    public function render_source_box( $post ) {
        wp_nonce_field( 'silo_pc_save_product', 'silo_pc_nonce' );
        $source   = self::get_meta( $post->ID, 'data_source' );
        $url      = self::get_meta( $post->ID, 'product_url' );
        $sku      = self::get_meta( $post->ID, 'product_sku' );
        $tracking = self::get_meta( $post->ID, 'tracking_source' );
        if ( empty( $tracking ) ) {
            $tracking = get_option( 'silo_pc_default_tracking', 'blog_product_card' );
        }
        ?>
        <table class="form-table">
            <tr>
                <th><label for="silo_pc_data_source">Data Source</label></th>
                <td>
                    <select name="silo_pc_data_source" id="silo_pc_data_source" style="min-width:200px">
                        <option value="manual" <?php selected( $source, 'manual' ); ?>>Manual Entry</option>
                        <option value="scrape" <?php selected( $source, 'scrape' ); ?>>Scrape from Product Page</option>
                        <option value="api" <?php selected( $source, 'api' ); ?>>Magento API (requires setup)</option>
                    </select>
                    <p class="description">Manual: enter all data below. Scrape: fetches from URL, fields below act as overrides.</p>
                </td>
            </tr>
            <tr>
                <th><label for="silo_pc_product_url">Product URL</label></th>
                <td>
                    <input type="url" name="silo_pc_product_url" id="silo_pc_product_url" value="<?php echo esc_attr( $url ); ?>" class="large-text">
                    <p class="description">Used as the CTA link and as the scrape source URL.</p>
                </td>
            </tr>
            <tr>
                <th><label for="silo_pc_product_sku">Product SKU</label></th>
                <td>
                    <input type="text" name="silo_pc_product_sku" id="silo_pc_product_sku" value="<?php echo esc_attr( $sku ); ?>" class="regular-text">
                    <p class="description">Magento SKU — only needed for API mode.</p>
                </td>
            </tr>
            <tr>
                <th><label for="silo_pc_tracking_source">Tracking Source</label></th>
                <td>
                    <input type="text" name="silo_pc_tracking_source" id="silo_pc_tracking_source" value="<?php echo esc_attr( $tracking ); ?>" class="regular-text">
                    <p class="description">Added as <code>?source=</code> param to the product link for GA4 attribution.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function render_info_box( $post ) {
        $desc     = self::get_meta( $post->ID, 'description' );
        $images   = self::get_meta( $post->ID, 'images' );
        $badge    = self::get_meta( $post->ID, 'badge' );
        $currency = self::get_meta( $post->ID, 'currency' );
        ?>
        <table class="form-table">
            <tr>
                <th><label for="silo_pc_description">Short Description</label></th>
                <td>
                    <textarea name="silo_pc_description" id="silo_pc_description" rows="3" class="large-text"><?php echo esc_textarea( $desc ); ?></textarea>
                    <p class="description">In scrape mode, leave blank to use fetched data.</p>
                </td>
            </tr>
            <tr>
                <th><label for="silo_pc_images">Image URLs</label></th>
                <td>
                    <textarea name="silo_pc_images" id="silo_pc_images" rows="4" class="large-text" placeholder="https://example.com/image-1.jpg&#10;https://example.com/image-2.jpg"><?php echo esc_textarea( $images ); ?></textarea>
                    <p class="description">One URL per line. In scrape mode, leave empty to use fetched images.</p>
                </td>
            </tr>
            <tr>
                <th><label for="silo_pc_badge">Badge</label></th>
                <td>
                    <input type="text" name="silo_pc_badge" id="silo_pc_badge" value="<?php echo esc_attr( $badge ); ?>" class="regular-text" placeholder="e.g. Bestseller, New, Sale">
                </td>
            </tr>
            <tr>
                <th><label for="silo_pc_currency">Currency</label></th>
                <td>
                    <select name="silo_pc_currency" id="silo_pc_currency">
                        <option value="USD" <?php selected( $currency, 'USD' ); ?>>USD ($)</option>
                        <option value="GBP" <?php selected( $currency, 'GBP' ); ?>>GBP (&pound;)</option>
                        <option value="EUR" <?php selected( $currency, 'EUR' ); ?>>EUR (&euro;)</option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    public function render_pricing_box( $post ) {
        $price      = self::get_meta( $post->ID, 'price' );
        $compare    = self::get_meta( $post->ID, 'compare_at_price' );
        $fetch_live = self::get_meta( $post->ID, 'fetch_live_price' );
        ?>
        <table class="form-table">
            <tr>
                <th><label for="silo_pc_price">Price</label></th>
                <td>
                    <input type="number" name="silo_pc_price" id="silo_pc_price" value="<?php echo esc_attr( $price ); ?>" step="0.01" min="0" class="regular-text">
                    <p class="description">In scrape mode, leave blank to use fetched price.</p>
                </td>
            </tr>
            <tr>
                <th><label for="silo_pc_compare_at_price">Compare-at Price</label></th>
                <td>
                    <input type="number" name="silo_pc_compare_at_price" id="silo_pc_compare_at_price" value="<?php echo esc_attr( $compare ); ?>" step="0.01" min="0" class="regular-text">
                    <p class="description">Original/RRP — shown crossed out if higher than price.</p>
                </td>
            </tr>
            <tr>
                <th>Live Price Enrichment</th>
                <td>
                    <label>
                        <input type="checkbox" name="silo_pc_fetch_live_price" value="1" <?php checked( $fetch_live, '1' ); ?>>
                        Fetch live price from product page on each visit
                    </label>
                    <p class="description">Card renders with cached/manual price first, then JS fetches the live page to update the price. Useful when the server-side scrape can't capture JS-rendered prices.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function render_reviews_box( $post ) {
        $rating = self::get_meta( $post->ID, 'rating' );
        $count  = self::get_meta( $post->ID, 'review_count' );
        ?>
        <p>
            <label for="silo_pc_rating"><strong>Rating (0-5)</strong></label><br>
            <input type="number" name="silo_pc_rating" id="silo_pc_rating" value="<?php echo esc_attr( $rating ); ?>" step="0.01" min="0" max="5" style="width:100%">
        </p>
        <p>
            <label for="silo_pc_review_count"><strong>Review Count</strong></label><br>
            <input type="number" name="silo_pc_review_count" id="silo_pc_review_count" value="<?php echo esc_attr( $count ); ?>" min="0" style="width:100%">
        </p>
        <?php
    }

    public function render_discount_box( $post ) {
        $show    = self::get_meta( $post->ID, 'show_discount' );
        $code    = self::get_meta( $post->ID, 'discount_code' );
        $percent = self::get_meta( $post->ID, 'discount_percent' );
        $label   = self::get_meta( $post->ID, 'discount_label' );
        ?>
        <table class="form-table">
            <tr>
                <th>Show Discount</th>
                <td>
                    <label>
                        <input type="checkbox" name="silo_pc_show_discount" value="1" <?php checked( $show, '1' ); ?>>
                        Display a discount code block on the card
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="silo_pc_discount_code">Discount Code</label></th>
                <td>
                    <input type="text" name="silo_pc_discount_code" id="silo_pc_discount_code" value="<?php echo esc_attr( $code ); ?>" class="regular-text" style="font-family:monospace">
                </td>
            </tr>
            <tr>
                <th><label for="silo_pc_discount_percent">Discount %</label></th>
                <td>
                    <input type="number" name="silo_pc_discount_percent" id="silo_pc_discount_percent" value="<?php echo esc_attr( $percent ); ?>" min="0" max="100" class="small-text">
                </td>
            </tr>
            <tr>
                <th><label for="silo_pc_discount_label">Label</label></th>
                <td>
                    <input type="text" name="silo_pc_discount_label" id="silo_pc_discount_label" value="<?php echo esc_attr( $label ); ?>" class="regular-text" placeholder="Exclusive Reader Offer">
                </td>
            </tr>
        </table>
        <?php
    }

    public function render_selectors_box( $post ) {
        $raw = self::get_meta( $post->ID, 'custom_selectors' );
        $json = '';
        if ( ! empty( $raw ) ) {
            $json = is_string( $raw ) ? $raw : wp_json_encode( $raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        }
        ?>
        <p class="description">Leave empty to use the <a href="<?php echo esc_url( admin_url( 'options-general.php?page=silo-product-cards' ) ); ?>">global selectors</a>. Only needed if this product's page has a different template.</p>
        <textarea name="silo_pc_custom_selectors" id="silo_pc_custom_selectors" rows="10" class="large-text code" placeholder='[{"field":"price","type":"xpath","selector":"...","extract":"attribute","attribute":"data-price-amount","multiple":false,"transform":"float"}]'><?php echo esc_textarea( $json ); ?></textarea>
        <p class="description">JSON array of selector objects. Same format as the global settings. <a href="#" onclick="var el=document.getElementById('silo_pc_custom_selectors');el.value=JSON.stringify(<?php echo esc_attr( wp_json_encode( Silo_PC_Data_Source::default_selectors() ) ); ?>,null,2);return false;">Load defaults as starting point</a></p>
        <?php
    }

    /**
     * Save product meta on post save.
     */
    public function save_meta( $post_id, $post ) {
        if ( ! isset( $_POST['silo_pc_nonce'] ) || ! wp_verify_nonce( $_POST['silo_pc_nonce'], 'silo_pc_save_product' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Text / select / url fields.
        $text_fields = array(
            'data_source', 'product_url', 'product_sku', 'description',
            'images', 'badge', 'currency', 'tracking_source',
            'discount_code', 'discount_label',
        );

        foreach ( $text_fields as $key ) {
            $value = isset( $_POST[ 'silo_pc_' . $key ] ) ? sanitize_textarea_field( $_POST[ 'silo_pc_' . $key ] ) : '';
            update_post_meta( $post_id, self::META_PREFIX . $key, $value );
        }

        // Numeric fields.
        $number_fields = array( 'price', 'compare_at_price', 'rating', 'review_count', 'discount_percent' );
        foreach ( $number_fields as $key ) {
            $value = isset( $_POST[ 'silo_pc_' . $key ] ) && '' !== $_POST[ 'silo_pc_' . $key ]
                ? sanitize_text_field( $_POST[ 'silo_pc_' . $key ] )
                : '';
            update_post_meta( $post_id, self::META_PREFIX . $key, $value );
        }

        // Checkboxes.
        $show_discount = isset( $_POST['silo_pc_show_discount'] ) ? '1' : '0';
        update_post_meta( $post_id, self::META_PREFIX . 'show_discount', $show_discount );

        $fetch_live = isset( $_POST['silo_pc_fetch_live_price'] ) ? '1' : '0';
        update_post_meta( $post_id, self::META_PREFIX . 'fetch_live_price', $fetch_live );

        // Custom selectors (JSON).
        $selectors_raw = isset( $_POST['silo_pc_custom_selectors'] ) ? trim( wp_unslash( $_POST['silo_pc_custom_selectors'] ) ) : '';
        if ( ! empty( $selectors_raw ) ) {
            $decoded = json_decode( $selectors_raw, true );
            // Only save if it's valid JSON array.
            if ( is_array( $decoded ) ) {
                update_post_meta( $post_id, self::META_PREFIX . 'custom_selectors', $selectors_raw );
            }
        } else {
            delete_post_meta( $post_id, self::META_PREFIX . 'custom_selectors' );
        }
    }

    // --- Admin columns ---

    public function admin_columns( $columns ) {
        $new = array();
        foreach ( $columns as $key => $val ) {
            $new[ $key ] = $val;
            if ( 'title' === $key ) {
                $new['silo_source'] = 'Source';
                $new['silo_price']  = 'Price';
                $new['silo_badge']  = 'Badge';
            }
        }
        return $new;
    }

    public function admin_column_content( $column, $post_id ) {
        switch ( $column ) {
            case 'silo_source':
                $source = self::get_meta( $post_id, 'data_source' );
                $labels = array( 'manual' => 'Manual', 'scrape' => 'Scrape', 'api' => 'API' );
                echo esc_html( isset( $labels[ $source ] ) ? $labels[ $source ] : $source );
                break;
            case 'silo_price':
                $price = self::get_meta( $post_id, 'price' );
                echo $price ? '$' . esc_html( number_format( (float) $price, 2 ) ) : '—';
                break;
            case 'silo_badge':
                $badge = self::get_meta( $post_id, 'badge' );
                echo $badge ? esc_html( $badge ) : '—';
                break;
        }
    }
}
