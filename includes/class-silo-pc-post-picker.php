<?php
/**
 * SILO Product Cards — Post Product Picker.
 *
 * Native meta box on posts/pages for selecting which products to show.
 * Searchable multi-select with drag-to-reorder — no ACF needed.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Silo_PC_Post_Picker {

    const META_KEY       = '_silo_pc_product_ids';
    const PLACEMENT_KEY  = '_silo_pc_placement';

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
        add_action( 'save_post', array( $this, 'save' ), 10, 2 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Get the selected product IDs for a post.
     *
     * @param int $post_id
     * @return int[]
     */
    public static function get_product_ids( $post_id ) {
        $ids = get_post_meta( $post_id, self::META_KEY, true );
        if ( empty( $ids ) || ! is_array( $ids ) ) {
            return array();
        }
        return array_map( 'intval', $ids );
    }

    /**
     * Get the placement setting for a post.
     *
     * @param int $post_id
     * @return string 'auto' or 'shortcode'
     */
    public static function get_placement( $post_id ) {
        $placement = get_post_meta( $post_id, self::PLACEMENT_KEY, true );
        return in_array( $placement, array( 'auto', 'shortcode' ), true ) ? $placement : 'auto';
    }

    /**
     * Add meta box to posts and pages.
     */
    public function add_meta_box() {
        $post_types = apply_filters( 'silo_pc_post_types', array( 'post', 'page' ) );
        foreach ( $post_types as $pt ) {
            add_meta_box(
                'silo_pc_products',
                'Product Cards',
                array( $this, 'render' ),
                $pt,
                'side',
                'default'
            );
        }
    }

    /**
     * Render the product picker meta box.
     */
    public function render( $post ) {
        wp_nonce_field( 'silo_pc_save_picker', 'silo_pc_picker_nonce' );

        $selected_ids = self::get_product_ids( $post->ID );
        $placement    = self::get_placement( $post->ID );

        // Get all published products for the dropdown.
        $products = get_posts( array(
            'post_type'      => 'silo_product',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );
        ?>

        <p>
            <label for="silo_pc_add_product"><strong>Add products:</strong></label>
            <select id="silo_pc_add_product" style="width:100%;margin-top:4px">
                <option value="">— Select a product —</option>
                <?php foreach ( $products as $p ) : ?>
                    <option value="<?php echo esc_attr( $p->ID ); ?>"><?php echo esc_html( $p->post_title ); ?></option>
                <?php endforeach; ?>
            </select>
        </p>

        <ul id="silo_pc_selected_products" style="margin:0;padding:0;list-style:none">
            <?php foreach ( $selected_ids as $pid ) :
                $title = get_the_title( $pid );
                if ( ! $title ) continue;
            ?>
                <li data-id="<?php echo esc_attr( $pid ); ?>" style="padding:6px 8px;margin:4px 0;background:#f6f7f7;border:1px solid #ddd;border-radius:3px;cursor:move;display:flex;justify-content:space-between;align-items:center">
                    <span><?php echo esc_html( $title ); ?></span>
                    <input type="hidden" name="silo_pc_product_ids[]" value="<?php echo esc_attr( $pid ); ?>">
                    <button type="button" class="silo-pc-remove-product" style="background:none;border:none;color:#b32d2e;cursor:pointer;font-size:16px;line-height:1" title="Remove">&times;</button>
                </li>
            <?php endforeach; ?>
        </ul>

        <p style="margin-top:12px">
            <label for="silo_pc_placement"><strong>Placement:</strong></label>
            <select name="silo_pc_placement" id="silo_pc_placement" style="width:100%;margin-top:4px">
                <option value="auto" <?php selected( $placement, 'auto' ); ?>>Auto (appended after content)</option>
                <option value="shortcode" <?php selected( $placement, 'shortcode' ); ?>>Shortcode only [silo_products]</option>
            </select>
        </p>

        <?php if ( empty( $products ) ) : ?>
            <p class="description">No products yet. <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=silo_product' ) ); ?>">Create your first product.</a></p>
        <?php endif; ?>

        <?php
    }

    /**
     * Enqueue inline JS for the picker (add/remove/reorder).
     */
    public function enqueue_scripts( $hook ) {
        if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
            return;
        }

        $screen = get_current_screen();
        $post_types = apply_filters( 'silo_pc_post_types', array( 'post', 'page' ) );
        if ( ! $screen || ! in_array( $screen->post_type, $post_types, true ) ) {
            return;
        }

        wp_add_inline_script( 'jquery-core', '
            jQuery(function($){
                var $list = $("#silo_pc_selected_products");
                var $select = $("#silo_pc_add_product");

                // Add product.
                $select.on("change", function(){
                    var id = $(this).val();
                    var text = $(this).find("option:selected").text();
                    if (!id) return;

                    // Prevent duplicates.
                    if ($list.find("[data-id=\"" + id + "\"]").length) {
                        $(this).val("");
                        return;
                    }

                    var $li = $("<li>", {
                        "data-id": id,
                        style: "padding:6px 8px;margin:4px 0;background:#f6f7f7;border:1px solid #ddd;border-radius:3px;cursor:move;display:flex;justify-content:space-between;align-items:center"
                    });
                    $li.append($("<span>").text(text));
                    $li.append($("<input>", {type:"hidden", name:"silo_pc_product_ids[]", value:id}));
                    $li.append($("<button>", {
                        type:"button",
                        class:"silo-pc-remove-product",
                        style:"background:none;border:none;color:#b32d2e;cursor:pointer;font-size:16px;line-height:1",
                        title:"Remove",
                        text:"\u00d7"
                    }));
                    $list.append($li);
                    $(this).val("");
                });

                // Remove product.
                $list.on("click", ".silo-pc-remove-product", function(){
                    $(this).closest("li").remove();
                });

                // Drag to reorder (simple swap).
                if (typeof $.fn.sortable !== "undefined") {
                    $list.sortable({handle:"li", placeholder:"ui-state-highlight"});
                }
            });
        ' );
    }

    /**
     * Save the selected products and placement on post save.
     */
    public function save( $post_id, $post ) {
        if ( ! isset( $_POST['silo_pc_picker_nonce'] ) || ! wp_verify_nonce( $_POST['silo_pc_picker_nonce'], 'silo_pc_save_picker' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Product IDs.
        $ids = array();
        if ( ! empty( $_POST['silo_pc_product_ids'] ) && is_array( $_POST['silo_pc_product_ids'] ) ) {
            $ids = array_map( 'intval', $_POST['silo_pc_product_ids'] );
            $ids = array_filter( $ids );
        }
        update_post_meta( $post_id, self::META_KEY, $ids );

        // Placement.
        $placement = isset( $_POST['silo_pc_placement'] ) ? sanitize_key( $_POST['silo_pc_placement'] ) : 'auto';
        update_post_meta( $post_id, self::PLACEMENT_KEY, $placement );
    }
}
