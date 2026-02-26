<?php
/**
 * Product Card Template.
 *
 * All visible content is rendered server-side for SEO and no-JS support.
 * The JS (sdpc-card.js) only enhances interactivity: gallery navigation,
 * discount copy-to-clipboard, and GA4 tracking.
 *
 * Override: copy to your theme at silo-product-cards/product-card.php
 *
 * @var array $product_data Normalised product data array.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$images          = ! empty( $product_data['images'] ) ? $product_data['images'] : array();
$title           = ! empty( $product_data['title'] ) ? $product_data['title'] : '';
$description     = ! empty( $product_data['description'] ) ? $product_data['description'] : '';
$url             = ! empty( $product_data['url'] ) ? $product_data['url'] : '#';
$price           = ! empty( $product_data['price'] ) ? (float) $product_data['price'] : 0;
$compare_at      = ! empty( $product_data['compareAtPrice'] ) ? (float) $product_data['compareAtPrice'] : 0;
$currency        = ! empty( $product_data['currency'] ) ? $product_data['currency'] : 'USD';
$badge           = ! empty( $product_data['badge'] ) ? $product_data['badge'] : '';
$rating          = ! empty( $product_data['rating'] ) ? (float) $product_data['rating'] : 0;
$review_count    = ! empty( $product_data['reviewCount'] ) ? (int) $product_data['reviewCount'] : 0;
$show_discount   = ! empty( $product_data['showReaderDiscount'] );
$discount_code   = ! empty( $product_data['discountCode'] ) ? trim( $product_data['discountCode'] ) : '';
$discount_pct    = ! empty( $product_data['discountPercent'] ) ? (int) $product_data['discountPercent'] : 0;
$discount_label  = ! empty( $product_data['discountLabel'] ) ? $product_data['discountLabel'] : 'Exclusive Reader Offer';
$tracking_source = ! empty( $product_data['trackingSource'] ) ? $product_data['trackingSource'] : 'blog';

// Currency symbol.
$symbol = 'GBP' === $currency ? '&pound;' : '$';

// Build tracked URL.
$tracked_url = $url;
if ( '#' !== $url ) {
    $tracked_url = add_query_arg( 'source', rawurlencode( $tracking_source ), $url );
}

// Savings percentage.
$savings = 0;
if ( $compare_at > 0 && $compare_at > $price && $price > 0 ) {
    $savings = round( ( 1 - $price / $compare_at ) * 100 );
}
?>
<div class="sdpc-card"<?php
    // Data attributes for JS interactivity (gallery, discount copy, live price).
    if ( ! empty( $discount_code ) ) echo ' data-discount-code="' . esc_attr( $discount_code ) . '"';
    if ( ! empty( $product_data['fetchLivePrice'] ) ) {
        echo ' data-fetch-live-price="1"';
        echo ' data-product-url="' . esc_attr( $url ) . '"';
        echo ' data-currency="' . esc_attr( $currency ) . '"';
    }
?>>

    <div class="sdpc-media">
        <div class="sdpc-media__gallery" role="region" aria-label="Product images">
            <div class="sdpc-media__track">
                <?php if ( ! empty( $images ) ) : ?>
                    <?php foreach ( $images as $i => $img_url ) : ?>
                        <div class="sdpc-media__slide">
                            <img class="sdpc-media__image"
                                 src="<?php echo esc_url( $img_url ); ?>"
                                 alt="<?php echo esc_attr( $title . ' - Image ' . ( $i + 1 ) ); ?>"
                                 loading="<?php echo 0 === $i ? 'eager' : 'lazy'; ?>"
                                 decoding="async">
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="sdpc-media__slide">
                        <img class="sdpc-media__image" src="" alt="<?php echo esc_attr( $title . ' - No image available' ); ?>" loading="eager" decoding="async">
                    </div>
                <?php endif; ?>
            </div>
            <?php if ( count( $images ) > 1 ) : ?>
                <div class="sdpc-media__nav">
                    <button class="sdpc-media__button sdpc-media__button--prev" type="button" aria-label="Previous image" disabled>
                        <svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6" /></svg>
                    </button>
                    <button class="sdpc-media__button sdpc-media__button--next" type="button" aria-label="Next image">
                        <svg viewBox="0 0 24 24"><path d="M9 6l6 6-6 6" /></svg>
                    </button>
                </div>
            <?php else : ?>
                <div class="sdpc-media__nav" aria-hidden="true"></div>
            <?php endif; ?>
        </div>
        <?php if ( count( $images ) > 1 && count( $images ) <= 5 ) : ?>
            <div class="sdpc-media__dots" role="tablist">
                <?php foreach ( $images as $i => $img_url ) : ?>
                    <button class="sdpc-media__dot<?php echo 0 === $i ? ' sdpc-media__dot--active' : ''; ?>"
                            type="button"
                            role="tab"
                            aria-label="Go to image <?php echo $i + 1; ?>"
                            aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>"></button>
                <?php endforeach; ?>
            </div>
        <?php elseif ( count( $images ) > 5 ) : ?>
            <div class="sdpc-media__thumbs">
                <?php foreach ( $images as $i => $img_url ) : ?>
                    <button class="sdpc-media__thumb<?php echo 0 === $i ? ' sdpc-media__thumb--active' : ''; ?>"
                            type="button"
                            aria-label="Go to image <?php echo $i + 1; ?>">
                        <img src="<?php echo esc_url( $img_url ); ?>" alt="" loading="lazy" decoding="async">
                    </button>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="sdpc-media__dots" role="tablist"></div>
        <?php endif; ?>
    </div>

    <div class="sdpc-content">
        <div class="sdpc-content__header">
            <div class="sdpc-content__badges">
                <?php if ( $badge ) : ?>
                    <span class="sdpc-content__badge"><?php echo esc_html( $badge ); ?></span>
                <?php endif; ?>
                <?php if ( $savings > 0 ) : ?>
                    <span class="sdpc-content__badge sdpc-content__badge--savings">Save <?php echo esc_html( $savings ); ?>%</span>
                <?php endif; ?>
            </div>
            <h2 class="sdpc-content__title"><?php echo esc_html( $title ); ?></h2>
            <p class="sdpc-content__description"><?php echo esc_html( $description ); ?></p>
            <?php if ( $rating > 0 && $review_count > 0 ) : ?>
                <div class="sdpc-content__rating">
                    <div class="sdpc-content__stars">
                        <?php
                        $full_stars = (int) floor( $rating );
                        $has_half   = ( $rating - $full_stars ) >= 0.5;
                        for ( $s = 0; $s < 5; $s++ ) :
                            $filled = ( $s < $full_stars || ( $s === $full_stars && $has_half ) );
                        ?>
                            <svg class="sdpc-content__star<?php echo ! $filled ? ' sdpc-content__star--empty' : ''; ?>" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path d="M10 1l2.5 6.5H19l-5.25 4.5L16.25 19 10 14.5 3.75 19l2.5-6.5L1 8h6.5L10 1z"/>
                            </svg>
                        <?php endfor; ?>
                    </div>
                    <span><?php echo esc_html( $rating . ' (' . $review_count . ' reviews)' ); ?></span>
                    <span class="sdpc-sr-only">Rated <?php echo esc_html( $rating ); ?> out of 5 from <?php echo esc_html( $review_count ); ?> reviews</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="sdpc-content__pricing">
            <?php if ( $price > 0 ) : ?>
                <span class="sdpc-content__price"><?php echo $symbol . esc_html( number_format( $price, 2 ) ); ?></span>
                <?php if ( $compare_at > $price ) : ?>
                    <span class="sdpc-content__compare"><?php echo $symbol . esc_html( number_format( $compare_at, 2 ) ); ?></span>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="sdpc-content__discount-container">
            <?php if ( $show_discount && ! empty( $discount_code ) ) :
                $discount_message = $discount_pct > 0
                    ? 'Save an extra ' . $discount_pct . '% with our exclusive reader discount'
                    : 'Enjoy our exclusive reader discount';
            ?>
                <div class="sdpc-content__discount" role="button" tabindex="0" aria-label="Copy discount code <?php echo esc_attr( $discount_code ); ?> to clipboard">
                    <div class="sdpc-content__discount-feedback" aria-live="polite" aria-atomic="true">Copied!</div>
                    <span class="sdpc-content__discount-label"><?php echo esc_html( $discount_label ); ?></span>
                    <span class="sdpc-content__discount-message"><?php echo esc_html( $discount_message ); ?></span>
                    <div class="sdpc-content__discount-code">
                        <span class="sdpc-content__discount-text"><?php echo esc_html( $discount_code ); ?></span>
                        <svg class="sdpc-content__discount-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                        </svg>
                    </div>
                    <span class="sdpc-content__discount-hint">Click to copy code</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="sdpc-content__cta">
            <a href="<?php echo esc_url( $tracked_url ); ?>"
               class="sdpc-button"
               rel="nofollow noopener"
               target="_blank"
               data-product="<?php echo esc_attr( sanitize_title( $title ) ); ?>"
               data-source="<?php echo esc_attr( $tracking_source ); ?>"
               <?php if ( ! empty( $discount_code ) ) : ?>data-discount="<?php echo esc_attr( $discount_code ); ?>"<?php endif; ?>
            >View Product</a>
        </div>
    </div>
</div>
