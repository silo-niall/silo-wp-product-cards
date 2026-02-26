<?php
/**
 * SILO Product Cards — Renderer.
 *
 * Shortcode, auto-insertion, and asset enqueueing.
 * Reads product selections from Silo_PC_Post_Picker (native meta).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Silo_PC_Renderer {

    /** @var bool */
    private $assets_enqueued = false;

    public function __construct() {
        add_shortcode( 'silo_products', array( $this, 'shortcode' ) );
        add_filter( 'the_content', array( $this, 'auto_insert' ), 20 );
        add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
        add_action( 'wp_head', array( $this, 'custom_css' ) );
    }

    /**
     * [silo_products] shortcode.
     */
    public function shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'ids'     => '',
            'post_id' => 0,
        ), $atts, 'silo_products' );

        // Direct product IDs — e.g. [silo_products ids="12,34"]
        if ( ! empty( $atts['ids'] ) ) {
            $ids = array_map( 'intval', explode( ',', $atts['ids'] ) );
            $ids = array_filter( $ids );
            if ( ! empty( $ids ) ) {
                return $this->render_cards_by_ids( $ids );
            }
        }

        $post_id = (int) $atts['post_id'] ?: get_the_ID();
        if ( ! $post_id ) {
            return '';
        }

        return $this->render_cards( $post_id );
    }

    /**
     * Auto-insert product cards after post content.
     */
    public function auto_insert( $content ) {
        if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }

        if ( 'disabled' === get_option( 'silo_pc_auto_insert', 'after_content' ) ) {
            return $content;
        }

        $post_id   = get_the_ID();
        $placement = Silo_PC_Post_Picker::get_placement( $post_id );

        if ( 'shortcode' === $placement ) {
            return $content;
        }

        $cards_html = $this->render_cards( $post_id );

        return empty( $cards_html ) ? $content : $content . $cards_html;
    }

    /**
     * Render product cards for a given post.
     *
     * @param int $post_id The blog post/page ID.
     * @return string
     */
    private function render_cards( $post_id ) {
        $product_ids = Silo_PC_Post_Picker::get_product_ids( $post_id );
        return $this->render_cards_by_ids( $product_ids );
    }

    /**
     * Render product cards for an explicit list of product IDs.
     */
    private function render_cards_by_ids( $product_ids ) {
        if ( empty( $product_ids ) ) {
            return '';
        }

        $output = '';

        foreach ( $product_ids as $product_id ) {
            if ( 'publish' !== get_post_status( $product_id ) ) {
                continue;
            }

            $product_data = Silo_PC_Data_Source::get_product_data( $product_id );

            if ( empty( $product_data['title'] ) && empty( $product_data['url'] ) ) {
                continue;
            }

            $output .= $this->render_single_card( $product_data );
        }

        if ( ! empty( $output ) ) {
            $this->enqueue_assets();
            $output = '<div class="silo-product-cards-wrapper">' . $output . '</div>';
        }

        return $output;
    }

    /**
     * Render a single product card.
     */
    private function render_single_card( $product_data ) {
        $template = locate_template( 'silo-product-cards/product-card.php' );
        if ( ! $template ) {
            $template = SILO_PC_DIR . 'templates/product-card.php';
        }

        ob_start();
        include $template;
        return ob_get_clean();
    }

    /**
     * Pre-enqueue assets on singular views that have product cards.
     */
    public function maybe_enqueue_assets() {
        if ( ! is_singular() ) {
            return;
        }

        $post_id = get_queried_object_id();
        if ( ! $post_id ) {
            return;
        }

        $product_ids = Silo_PC_Post_Picker::get_product_ids( $post_id );
        if ( ! empty( $product_ids ) ) {
            $this->enqueue_assets();
        }
    }

    private function enqueue_assets() {
        if ( $this->assets_enqueued ) {
            return;
        }

        wp_enqueue_style( 'silo-pc-card', SILO_PC_URL . 'assets/css/sdpc-card.css', array(), SILO_PC_VERSION );
        wp_enqueue_script( 'silo-pc-card', SILO_PC_URL . 'assets/js/sdpc-card.js', array(), SILO_PC_VERSION, true );

        $this->assets_enqueued = true;
    }

    public function custom_css() {
        $css = get_option( 'silo_pc_custom_css', '' );
        if ( ! empty( $css ) ) {
            echo '<style id="silo-pc-custom-css">' . wp_strip_all_tags( $css ) . '</style>' . "\n";
        }
    }
}
