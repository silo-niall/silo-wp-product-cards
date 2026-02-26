<?php
/**
 * SILO Product Cards — Admin Settings.
 *
 * Settings page under Settings → SILO Product Cards.
 * Includes dynamic scrape selector configuration.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Silo_PC_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_post_silo_pc_clear_cache', array( $this, 'clear_cache' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
    }

    public function add_menu() {
        add_options_page(
            'SILO Product Cards',
            'SILO Product Cards',
            'manage_options',
            'silo-product-cards',
            array( $this, 'render_page' )
        );
    }

    /**
     * Enqueue admin JS for the settings page.
     */
    public function admin_scripts( $hook ) {
        if ( 'settings_page_silo-product-cards' !== $hook ) {
            return;
        }

        wp_enqueue_script(
            'silo-pc-admin',
            SILO_PC_URL . 'assets/js/admin-selectors.js',
            array( 'jquery' ),
            SILO_PC_VERSION,
            true
        );

        wp_enqueue_style(
            'silo-pc-admin',
            SILO_PC_URL . 'assets/css/admin-selectors.css',
            array(),
            SILO_PC_VERSION
        );
    }

    public function register_settings() {
        // --- General ---
        add_settings_section( 'silo_pc_general', 'General Settings', null, 'silo-product-cards' );

        $this->add_field( 'silo_pc_default_data_source', 'Default Data Source', 'silo_pc_general', 'select', array(
            'options' => array(
                'manual' => 'Manual Entry',
                'scrape' => 'Scrape from Product Page',
                'api'    => 'Magento API',
            ),
        ) );

        $this->add_field( 'silo_pc_default_tracking', 'Default Tracking Source', 'silo_pc_general', 'text', array(
            'placeholder' => 'blog_product_card',
        ) );

        $this->add_field( 'silo_pc_cache_ttl', 'Cache TTL (hours)', 'silo_pc_general', 'number', array(
            'min' => 1, 'max' => 168, 'step' => 1,
            'description' => 'How long to cache scraped/API product data.',
        ) );

        $this->add_field( 'silo_pc_auto_insert', 'Auto-Insert Cards', 'silo_pc_general', 'select', array(
            'options' => array(
                'after_content' => 'After post content',
                'disabled'      => 'Disabled (shortcode only)',
            ),
        ) );

        // --- Scrape Selectors (custom rendering) ---
        add_settings_section(
            'silo_pc_selectors',
            'Scrape Selectors',
            function () {
                echo '<p>Configure which HTML elements to extract when scraping product pages. ';
                echo 'Selectors are processed top-to-bottom — for each field, the first matching selector wins. ';
                echo 'Add multiple selectors for the same field to create fallbacks.</p>';
            },
            'silo-product-cards'
        );

        register_setting( 'silo_pc_settings', 'silo_pc_scrape_selectors', array(
            'sanitize_callback' => array( $this, 'sanitize_selectors' ),
        ) );

        add_settings_field(
            'silo_pc_scrape_selectors',
            'Selectors',
            array( $this, 'render_selectors_ui' ),
            'silo-product-cards',
            'silo_pc_selectors'
        );

        // --- API ---
        add_settings_section( 'silo_pc_api', 'Magento 2 API', function () {
            echo '<p>Configure these when API access is granted. Until then, use Manual or Scrape mode.</p>';
        }, 'silo-product-cards' );

        $this->add_field( 'silo_pc_magento_api_url', 'API Base URL', 'silo_pc_api', 'url', array(
            'placeholder' => 'https://your-store.com/rest/default',
        ) );

        $this->add_field( 'silo_pc_magento_api_token', 'API Bearer Token', 'silo_pc_api', 'password', array(
            'description' => 'Integration access token from Magento admin.',
        ) );

        // --- Appearance ---
        add_settings_section( 'silo_pc_appearance', 'Appearance', null, 'silo-product-cards' );

        $this->add_field( 'silo_pc_custom_css', 'Custom CSS', 'silo_pc_appearance', 'textarea', array(
            'rows' => 8,
            'placeholder' => '/* Override card styles */',
            'description' => 'Use CSS custom properties like --sd-accent to change colours.',
        ) );
    }

    /**
     * Render the dynamic scrape selectors UI.
     */
    public function render_selectors_ui() {
        $selectors = get_option( 'silo_pc_scrape_selectors' );
        if ( empty( $selectors ) || ! is_array( $selectors ) ) {
            $selectors = Silo_PC_Data_Source::default_selectors();
        }

        $field_options = array(
            'title'            => 'Title',
            'description'      => 'Description',
            'price'            => 'Price',
            'compare_at_price' => 'Compare-at Price',
            'images'           => 'Images',
            'rating'           => 'Rating',
            'review_count'     => 'Review Count',
        );

        $type_options = array(
            'xpath' => 'XPath',
            'css'   => 'CSS Selector',
        );

        $extract_options = array(
            'text'      => 'Text Content',
            'attribute' => 'Attribute',
            'html'      => 'Inner HTML',
        );

        $transform_options = array(
            'none'              => 'None',
            'float'             => 'Float (number)',
            'int'               => 'Integer',
            'truncate'          => 'Truncate (200 chars)',
            'strip_site_suffix' => 'Strip site suffix',
            'percent_to_rating' => 'Percentage → 0-5 rating',
            'regex_number'      => 'Extract first number',
        );

        ?>
        <div id="silo-pc-selectors-wrap">
            <table class="silo-pc-selectors-table widefat">
                <thead>
                    <tr>
                        <th class="silo-pc-col-handle"></th>
                        <th>Field</th>
                        <th>Type</th>
                        <th>Selector</th>
                        <th>Extract</th>
                        <th>Attribute</th>
                        <th>Transform</th>
                        <th>Multi</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="silo-pc-selectors-body">
                    <?php foreach ( $selectors as $i => $sel ) : ?>
                        <tr class="silo-pc-selector-row">
                            <td class="silo-pc-col-handle"><span class="dashicons dashicons-menu"></span></td>
                            <td>
                                <select name="silo_pc_scrape_selectors[<?php echo $i; ?>][field]">
                                    <?php foreach ( $field_options as $val => $label ) : ?>
                                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $sel['field'] ?? '', $val ); ?>><?php echo esc_html( $label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="silo_pc_scrape_selectors[<?php echo $i; ?>][type]">
                                    <?php foreach ( $type_options as $val => $label ) : ?>
                                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $sel['type'] ?? 'xpath', $val ); ?>><?php echo esc_html( $label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="text" name="silo_pc_scrape_selectors[<?php echo $i; ?>][selector]" value="<?php echo esc_attr( $sel['selector'] ?? '' ); ?>" class="regular-text silo-pc-selector-input">
                            </td>
                            <td>
                                <select name="silo_pc_scrape_selectors[<?php echo $i; ?>][extract]" class="silo-pc-extract-select">
                                    <?php foreach ( $extract_options as $val => $label ) : ?>
                                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $sel['extract'] ?? 'text', $val ); ?>><?php echo esc_html( $label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="text" name="silo_pc_scrape_selectors[<?php echo $i; ?>][attribute]" value="<?php echo esc_attr( $sel['attribute'] ?? '' ); ?>" placeholder="e.g. data-price-amount" class="silo-pc-attr-input">
                            </td>
                            <td>
                                <select name="silo_pc_scrape_selectors[<?php echo $i; ?>][transform]">
                                    <?php foreach ( $transform_options as $val => $label ) : ?>
                                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $sel['transform'] ?? 'none', $val ); ?>><?php echo esc_html( $label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td style="text-align:center">
                                <input type="checkbox" name="silo_pc_scrape_selectors[<?php echo $i; ?>][multiple]" value="1" <?php checked( ! empty( $sel['multiple'] ) ); ?>>
                            </td>
                            <td>
                                <button type="button" class="button silo-pc-remove-row" title="Remove">&times;</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p>
                <button type="button" class="button" id="silo-pc-add-selector">+ Add Selector</button>
                <button type="button" class="button" id="silo-pc-reset-defaults">Reset to Defaults</button>
            </p>
        </div>

        <!-- Template for new rows (used by JS) -->
        <script type="text/html" id="silo-pc-row-template">
            <tr class="silo-pc-selector-row">
                <td class="silo-pc-col-handle"><span class="dashicons dashicons-menu"></span></td>
                <td>
                    <select name="silo_pc_scrape_selectors[__INDEX__][field]">
                        <?php foreach ( $field_options as $val => $label ) : ?>
                            <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select name="silo_pc_scrape_selectors[__INDEX__][type]">
                        <?php foreach ( $type_options as $val => $label ) : ?>
                            <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <input type="text" name="silo_pc_scrape_selectors[__INDEX__][selector]" value="" class="regular-text silo-pc-selector-input">
                </td>
                <td>
                    <select name="silo_pc_scrape_selectors[__INDEX__][extract]" class="silo-pc-extract-select">
                        <?php foreach ( $extract_options as $val => $label ) : ?>
                            <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <input type="text" name="silo_pc_scrape_selectors[__INDEX__][attribute]" value="" placeholder="e.g. data-price-amount" class="silo-pc-attr-input">
                </td>
                <td>
                    <select name="silo_pc_scrape_selectors[__INDEX__][transform]">
                        <?php foreach ( $transform_options as $val => $label ) : ?>
                            <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td style="text-align:center">
                    <input type="checkbox" name="silo_pc_scrape_selectors[__INDEX__][multiple]" value="1">
                </td>
                <td>
                    <button type="button" class="button silo-pc-remove-row" title="Remove">&times;</button>
                </td>
            </tr>
        </script>
        <?php
    }

    /**
     * Sanitize the selectors array.
     */
    public function sanitize_selectors( $input ) {
        if ( ! is_array( $input ) ) {
            return Silo_PC_Data_Source::default_selectors();
        }

        $clean = array();
        foreach ( $input as $sel ) {
            if ( empty( $sel['selector'] ) ) {
                continue;
            }
            $clean[] = array(
                'field'     => sanitize_key( $sel['field'] ?? 'title' ),
                'type'      => in_array( $sel['type'] ?? '', array( 'xpath', 'css' ), true ) ? $sel['type'] : 'xpath',
                'selector'  => wp_kses_no_html( $sel['selector'] ?? '' ),
                'extract'   => in_array( $sel['extract'] ?? '', array( 'text', 'attribute', 'html' ), true ) ? $sel['extract'] : 'text',
                'attribute' => sanitize_text_field( $sel['attribute'] ?? '' ),
                'multiple'  => ! empty( $sel['multiple'] ),
                'transform' => sanitize_key( $sel['transform'] ?? 'none' ),
            );
        }

        return ! empty( $clean ) ? $clean : Silo_PC_Data_Source::default_selectors();
    }

    private function add_field( $name, $label, $section, $type, $args = array() ) {
        register_setting( 'silo_pc_settings', $name, array(
            'sanitize_callback' => $this->get_sanitizer( $type ),
        ) );

        add_settings_field( $name, $label, function () use ( $name, $type, $args ) {
            $value = get_option( $name, '' );
            $this->render_field( $name, $type, $value, $args );
        }, 'silo-product-cards', $section );
    }

    private function get_sanitizer( $type ) {
        switch ( $type ) {
            case 'url':      return 'esc_url_raw';
            case 'number':   return 'absint';
            case 'textarea': return 'wp_strip_all_tags';
            default:         return 'sanitize_text_field';
        }
    }

    private function render_field( $name, $type, $value, $args ) {
        switch ( $type ) {
            case 'select':
                echo '<select name="' . esc_attr( $name ) . '">';
                foreach ( $args['options'] as $key => $label ) {
                    echo '<option value="' . esc_attr( $key ) . '"' . selected( $value, $key, false ) . '>' . esc_html( $label ) . '</option>';
                }
                echo '</select>';
                break;

            case 'textarea':
                $rows = $args['rows'] ?? 5;
                echo '<textarea name="' . esc_attr( $name ) . '" rows="' . esc_attr( $rows ) . '" class="large-text code"';
                if ( ! empty( $args['placeholder'] ) ) echo ' placeholder="' . esc_attr( $args['placeholder'] ) . '"';
                echo '>' . esc_textarea( $value ) . '</textarea>';
                break;

            case 'number':
                echo '<input type="number" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="small-text"';
                if ( isset( $args['min'] ) )  echo ' min="' . esc_attr( $args['min'] ) . '"';
                if ( isset( $args['max'] ) )  echo ' max="' . esc_attr( $args['max'] ) . '"';
                if ( isset( $args['step'] ) ) echo ' step="' . esc_attr( $args['step'] ) . '"';
                echo '>';
                break;

            case 'password':
                echo '<input type="password" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="regular-text"';
                if ( ! empty( $args['placeholder'] ) ) echo ' placeholder="' . esc_attr( $args['placeholder'] ) . '"';
                echo '>';
                break;

            default:
                $input_type = ( 'url' === $type ) ? 'url' : 'text';
                echo '<input type="' . esc_attr( $input_type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="regular-text"';
                if ( ! empty( $args['placeholder'] ) ) echo ' placeholder="' . esc_attr( $args['placeholder'] ) . '"';
                echo '>';
                break;
        }

        if ( ! empty( $args['description'] ) ) {
            echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
        }
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_GET['cache_cleared'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>Product data cache cleared.</p></div>';
        }
        ?>
        <div class="wrap">
            <h1>SILO Product Cards</h1>

            <form action="options.php" method="post">
                <?php
                settings_fields( 'silo_pc_settings' );
                do_settings_sections( 'silo-product-cards' );
                submit_button();
                ?>
            </form>

            <hr>

            <h2>Cache Management</h2>
            <p>Clear all cached product data (scraped pages and API responses).</p>
            <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
                <?php wp_nonce_field( 'silo_pc_clear_cache', 'silo_pc_cache_nonce' ); ?>
                <input type="hidden" name="action" value="silo_pc_clear_cache">
                <?php submit_button( 'Clear Product Cache', 'secondary' ); ?>
            </form>
        </div>
        <?php
    }

    public function clear_cache() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_admin_referer( 'silo_pc_clear_cache', 'silo_pc_cache_nonce' );

        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_silo_pc_scrape_%'
                OR option_name LIKE '_transient_timeout_silo_pc_scrape_%'
                OR option_name LIKE '_transient_silo_pc_api_%'
                OR option_name LIKE '_transient_timeout_silo_pc_api_%'"
        );

        wp_safe_redirect( add_query_arg( 'cache_cleared', '1', admin_url( 'options-general.php?page=silo-product-cards' ) ) );
        exit;
    }
}
