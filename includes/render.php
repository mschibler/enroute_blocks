<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'enroute-blocks-frontend', ENROUTE_BLOCKS_URL . 'build/frontend.css', [], filemtime( ENROUTE_BLOCKS_PATH . 'build/frontend.css' ) );
} );

// ── Helpers ───────────────────────────────────────────────────────────────────
function ebl_placeholder( string $msg ): string {
    return '<div class="ebl-placeholder">' . esc_html( $msg ) . '</div>';
}

// ── OFFER BLOCK ───────────────────────────────────────────────────────────────
add_action( 'init', function() {
    register_block_type( 'enroute/featured-offer', [
        'attributes' => [
            'postId'      => [ 'type' => 'number', 'default' => 0 ],
            'featuredNum' => [ 'type' => 'number', 'default' => 0 ],
            'mode'        => [ 'type' => 'string', 'default' => 'specific' ],
        ],
        'render_callback' => 'enroute_render_featured_offer',
    ] );
} );

function enroute_render_featured_offer( array $attrs ): string {
    $post_id      = (int) ( $attrs['postId']      ?? 0 );
    $featured_num = (int) ( $attrs['featuredNum']  ?? 0 );
    $mode         = $attrs['mode'] ?? 'specific';

    if ( $mode === 'featured' && $featured_num ) {
        $found = get_posts([
            'post_type'   => 'offer',
            'post_status' => 'publish',
            'numberposts' => 1,
            'meta_query'  => [
                'relation' => 'AND',
                [ 'key' => '_offer_featured', 'value' => $featured_num, 'compare' => '=' ],
                // only active offers (never set = active)
                [
                    'relation' => 'OR',
                    [ 'key' => '_offer_active', 'compare' => 'NOT EXISTS' ],
                    [ 'key' => '_offer_active', 'value' => '0', 'compare' => '!=' ],
                ],
            ],
        ]);
        if ( $found ) $post_id = $found[0]->ID;
    }

    if ( ! $post_id ) return ebl_placeholder( __( 'Select an offer', 'enroute_blocks' ) );
    $post = get_post( $post_id );
    if ( ! $post ) return '';

    // Inactive offer: not shown on the front end (placeholder in the editor)
    if ( get_post_meta( $post_id, '_offer_active', true ) === '0' ) {
        return ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ? ebl_placeholder( __( 'This offer is inactive', 'enroute_blocks' ) ) : '';
    }

    $title    = get_the_title( $post_id );
    $subtitle = get_post_meta( $post_id, '_offer_subtitle', true );
    $url      = get_permalink( $post_id );

    $photo_id   = get_post_meta( $post_id, '_offer_photo_id', true );
    $station_id = get_post_meta( $post_id, '_offer_station',  true );
    $img_url    = '';
    if ( $photo_id ) $img_url = wp_get_attachment_image_url( (int) $photo_id, 'large' ) ?: '';
    if ( ! $img_url && $station_id ) {
        $act  = get_post_meta( (int) $station_id, '_station_activities_photo_id', true );
        $stat = get_post_meta( (int) $station_id, '_station_photo_id', true );
        if ( $act )  $img_url = wp_get_attachment_image_url( (int) $act,  'large' ) ?: '';
        if ( ! $img_url && $stat ) $img_url = wp_get_attachment_image_url( (int) $stat, 'large' ) ?: '';
    }

    $color = '#EBD84A';
    if ( function_exists( 'enroute_get_offer_color' ) ) $color = enroute_get_offer_color( $post_id, 1 );

    $type_terms = get_the_terms( $post_id, 'offer_type' );
    $type_label = ( $type_terms && ! is_wp_error( $type_terms ) ) ? $type_terms[0]->name : '';

    ob_start(); ?>
    <a href="<?php echo esc_url( $url ); ?>" class="ebl-card">
        <div class="ebl-img">
            <?php if ( $img_url ) : ?>
            <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $title ); ?>">
            <?php endif; ?>
        </div>
        <div class="ebl-band" style="background:<?php echo esc_attr( $color ); ?>;">
            <?php if ( $type_label ) : ?><span class="ebl-label"><?php echo esc_html( strtoupper( $type_label ) ); ?></span><?php endif; ?>
            <span class="ebl-title"><?php echo esc_html( $title ); ?></span>
            <?php if ( $subtitle ) : ?><span class="ebl-subtitle"><?php echo esc_html( $subtitle ); ?></span><?php endif; ?>
        </div>
    </a>
    <?php return ob_get_clean();
}

// ── GUIDE BLOCK ───────────────────────────────────────────────────────────────
add_action( 'init', function() {
    register_block_type( 'enroute/featured-guide', [
        'attributes' => [
            'mode'       => [ 'type' => 'string', 'default' => 'random' ],
            'postId'     => [ 'type' => 'number', 'default' => 0 ],
            'colorIndex' => [ 'type' => 'number', 'default' => 1 ],
            'linkText'   => [ 'type' => 'string', 'default' => 'Zu den Guides' ],
            'linkUrl'    => [ 'type' => 'string', 'default' => '' ],
        ],
        'render_callback' => 'enroute_render_featured_guide',
    ] );
} );

function enroute_render_featured_guide( array $attrs ): string {
    $mode        = $attrs['mode']       ?? 'random';
    $post_id     = (int) ( $attrs['postId']     ?? 0 );
    $color_index = (int) ( $attrs['colorIndex'] ?? 1 );
    $link_text   = $attrs['linkText']   ?? __( 'Zu den Guides', 'enroute_blocks' );
    $link_url    = $attrs['linkUrl']    ?? '';
    $color       = enroute_blocks_get_color( $color_index );

    if ( ! $link_url ) $link_url = get_option( 'enroute_guides_page_url', '' );

    if ( $mode === 'random' ) {
        $guides = get_posts([
            'post_type'   => 'guide',
            'post_status' => 'publish',
            'numberposts' => 20,
            'meta_query'  => [[ 'key' => '_guide_active', 'value' => '1', 'compare' => '=' ]],
        ]);
        if ( empty( $guides ) ) return ebl_placeholder( __( 'No active guides found', 'enroute_blocks' ) );
        $guide   = $guides[ array_rand( $guides ) ];
        $post_id = $guide->ID;
    }

    if ( ! $post_id ) return ebl_placeholder( __( 'Select a guide', 'enroute_blocks' ) );
    $guide = get_post( $post_id );
    if ( ! $guide ) return '';

    $name     = get_the_title( $post_id );
    $photo_id = get_post_meta( $post_id, '_guide_photo_id', true );
    $img_url  = '';
    if ( $photo_id ) {
        $img_url = wp_get_attachment_image_url( (int) $photo_id, 'enroute-guide-square' ) ?: '';
        if ( ! $img_url ) $img_url = wp_get_attachment_image_url( (int) $photo_id, 'large' ) ?: '';
    }

    // Link text: the standard text is translated (Polylang / offers plugin); a custom text via Polylang
    if ( ( $link_text === '' || $link_text === 'Zu den Guides' ) && function_exists( 'enroute_guides_link_text' ) ) {
        $link_text_translated = enroute_guides_link_text();
    } else {
        $link_text_translated = function_exists( 'pll__' ) ? pll__( $link_text ) : $link_text;
    }
    // Link target: the guides page in the current language (Polylang translation of the page)
    if ( ! $link_url ) $link_url = get_option( 'enroute_guides_page_url', '' );
    if ( $link_url && function_exists( 'enroute_translated_page_url' ) ) $link_url = enroute_translated_page_url( $link_url, 'enroute_guides_listing' );
    if ( ! $link_url ) $link_url = home_url( '/' ); // absolute fallback so card is always an <a>

    ob_start(); ?>
    <a href="<?php echo esc_url( $link_url ); ?>" class="ebl-card ebl-card--guide">
        <div class="ebl-img">
            <?php if ( $img_url ) : ?>
            <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $name ); ?>">
            <?php endif; ?>
        </div>
        <div class="ebl-band" style="background:<?php echo esc_attr( $color ); ?>;">
            <span class="ebl-title"><?php echo esc_html( $name ); ?></span>
            <span class="ebl-guide-link"><?php echo esc_html( $link_text_translated ); ?></span>
        </div>
    </a>
    <?php return ob_get_clean();
}

// ── BLOG BLOCK ────────────────────────────────────────────────────────────────
add_action( 'init', function() {
    register_block_type( 'enroute/featured-blog', [
        'attributes' => [
            'postId'     => [ 'type' => 'number', 'default' => 0 ],
            'colorIndex' => [ 'type' => 'number', 'default' => 1 ],
            'mode'       => [ 'type' => 'string', 'default' => 'newest' ],
        ],
        'render_callback' => 'enroute_render_featured_blog',
    ] );
} );

function enroute_render_featured_blog( array $attrs ): string {
    $post_id     = (int) ( $attrs['postId']     ?? 0 );
    $color_index = (int) ( $attrs['colorIndex'] ?? 1 );
    $mode        = $attrs['mode'] ?? 'newest';
    $color       = enroute_blocks_get_color( $color_index );

    if ( $mode === 'newest' ) {
        $args = [ 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1, 'orderby' => 'date', 'order' => 'DESC' ];
        if ( function_exists( 'pll_current_language' ) ) $args['lang'] = pll_current_language();
        $newest = get_posts( $args );
        if ( $newest ) $post_id = $newest[0]->ID;
    }

    if ( ! $post_id ) return ebl_placeholder( __( 'Select a blog post', 'enroute_blocks' ) );
    $post = get_post( $post_id );
    if ( ! $post ) return '';

    $title   = html_entity_decode( get_the_title( $post_id ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    $excerpt = wp_trim_words( $post->post_excerpt ?: wp_strip_all_tags( $post->post_content ), 18, '…' );
    $url     = get_permalink( $post_id );
    $img_url = get_the_post_thumbnail_url( $post_id, 'large' ) ?: '';
    $cats    = wp_get_post_categories( $post_id, [ 'fields' => 'all' ] );
    $cat_name = $cats ? $cats[0]->name : '';

    ob_start(); ?>
    <a href="<?php echo esc_url( $url ); ?>" class="ebl-card">
        <?php if ( $img_url ) : ?>
        <div class="ebl-img">
            <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $title ); ?>">
        </div>
        <?php endif; ?>
        <div class="ebl-band" style="background:<?php echo esc_attr( $color ); ?>;">
            <?php if ( $cat_name ) : ?><span class="ebl-label"><?php echo esc_html( strtoupper( $cat_name ) ); ?></span><?php endif; ?>
            <span class="ebl-title"><?php echo esc_html( $title ); ?></span>
            <?php if ( $excerpt ) : ?><span class="ebl-subtitle"><?php echo esc_html( $excerpt ); ?></span><?php endif; ?>
        </div>
    </a>
    <?php return ob_get_clean();
}

// ── CUSTOM BLOCK ──────────────────────────────────────────────────────────────
add_action( 'init', function() {
    register_block_type( 'enroute/featured-custom', [
        'attributes' => [
            'title'       => [ 'type' => 'string', 'default' => '' ],
            'description' => [ 'type' => 'string', 'default' => '' ],
            'imageId'     => [ 'type' => 'number', 'default' => 0 ],
            'imageUrl'    => [ 'type' => 'string', 'default' => '' ],
            'colorIndex'  => [ 'type' => 'number', 'default' => 1 ],
            'linkUrl'     => [ 'type' => 'string', 'default' => '' ],
            'linkText'    => [ 'type' => 'string', 'default' => '' ],
        ],
        'render_callback' => 'enroute_render_featured_custom',
    ] );
} );

function enroute_render_featured_custom( array $attrs ): string {
    $title       = $attrs['title']       ?? '';
    $description = $attrs['description'] ?? '';
    $image_id    = (int) ( $attrs['imageId']    ?? 0 );
    $color_index = (int) ( $attrs['colorIndex'] ?? 1 );
    $color       = enroute_blocks_get_color( $color_index );
    $img_url     = '';
    if ( $image_id ) $img_url = wp_get_attachment_image_url( $image_id, 'large' ) ?: '';
    if ( ! $img_url && ! empty( $attrs['imageUrl'] ) ) $img_url = $attrs['imageUrl'];

    $link_url  = $attrs['linkUrl']  ?? '';
    $link_text = $attrs['linkText'] ?? '';

    ob_start(); ?>
    <div class="ebl-card--custom ebl-span2">
        <?php if ( $img_url ) : ?>
        <div class="ebl-custom-img">
            <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $title ); ?>">
        </div>
        <?php endif; ?>
        <div class="ebl-custom-text" style="background:<?php echo esc_attr( $color ); ?>;">
            <?php if ( $title ) : ?><h2 class="ebl-custom-title"><?php echo esc_html( $title ); ?></h2><?php endif; ?>
            <?php if ( $description ) : ?><div class="ebl-custom-desc"><?php echo wp_kses_post( wpautop( $description ) ); ?></div><?php endif; ?>
            <?php if ( $link_url ) : ?>
            <a href="<?php echo esc_url( $link_url ); ?>" class="ebl-custom-link"><?php echo esc_html( $link_text ?: $link_url ); ?></a>
            <?php endif; ?>
        </div>
    </div>
    <?php return ob_get_clean();
}

// ── EVENT BLOCK ───────────────────────────────────────────────────────────────
// Next upcoming event of the current language (default) or a specific event.
// Needs the Enroute Offers plugin (events, helpers).
add_action( 'init', function() {
    register_block_type( 'enroute/featured-event', [
        'attributes' => [
            'mode'       => [ 'type' => 'string', 'default' => 'next' ],
            'postId'     => [ 'type' => 'number', 'default' => 0 ],
            'colorIndex' => [ 'type' => 'number', 'default' => 1 ],
        ],
        'render_callback' => 'enroute_render_featured_event',
    ] );
} );

function enroute_render_featured_event( array $attrs ): string {
    $in_editor = defined( 'REST_REQUEST' ) && REST_REQUEST;
    if ( ! function_exists( 'enroute_get_upcoming_events' ) ) {
        return $in_editor ? ebl_placeholder( __( 'Enroute Offers plugin required', 'enroute_blocks' ) ) : '';
    }

    $mode  = $attrs['mode'] ?? 'next';
    $color = enroute_blocks_get_color( (int) ( $attrs['colorIndex'] ?? 1 ) );

    if ( $mode === 'specific' ) {
        $post_id = (int) ( $attrs['postId'] ?? 0 );
        if ( ! $post_id ) return $in_editor ? ebl_placeholder( __( 'Select an event', 'enroute_blocks' ) ) : '';
        $event = get_post( $post_id );
        if ( ! $event || $event->post_type !== 'event' || $event->post_status !== 'publish' ) return '';
        // Inactive or over: not shown on the website
        if ( ! enroute_event_is_active( $post_id ) || enroute_event_is_past( $post_id ) ) {
            return $in_editor ? ebl_placeholder( __( 'This event is inactive or already over', 'enroute_blocks' ) ) : '';
        }
    } else {
        $next = enroute_get_upcoming_events( '', 1 );
        if ( ! $next ) return $in_editor ? ebl_placeholder( __( 'No upcoming events', 'enroute_blocks' ) ) : '';
        $post_id = $next[0]->ID;
    }

    $url      = get_permalink( $post_id );
    $img_url  = enroute_event_image_url( $post_id, 'large' );
    $title    = get_the_title( $post_id );
    $meta     = enroute_event_card_meta( $post_id );
    $more_url = enroute_events_page_url();

    ob_start(); ?>
    <div class="ebl-card ebl-card--event">
        <a href="<?php echo esc_url( $url ); ?>" class="ebl-img">
            <?php if ( $img_url ) : ?>
            <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $title ); ?>">
            <?php endif; ?>
        </a>
        <div class="ebl-band" style="background:<?php echo esc_attr( $color ); ?>;">
            <a href="<?php echo esc_url( $url ); ?>" class="ebl-title"><?php echo esc_html( $title ); ?></a>
            <?php if ( $meta ) : ?><span class="ebl-subtitle"><?php echo esc_html( $meta ); ?></span><?php endif; ?>
            <?php if ( $more_url ) : ?>
            <a href="<?php echo esc_url( $more_url ); ?>" class="ebl-guide-link"><?php echo esc_html( enroute_events_more_link_text() ); ?></a>
            <?php endif; ?>
        </div>
    </div>
    <?php return ob_get_clean();
}

// ── SLIDESHOW BLOCK ───────────────────────────────────────────────────────────
// Slides: title, text (HTML), link, colour (default colours 1–5), image.
// Shown above the grid (moved out of it in the grid wrapper below), so the
// grid's equal row heights are not stretched to the slideshow's height.
add_action( 'init', function() {
    register_block_type( 'enroute/slideshow', [
        'attributes' => [
            'slides'   => [ 'type' => 'array', 'default' => [] ],
            'interval' => [ 'type' => 'number', 'default' => 6 ],
        ],
        'render_callback' => 'enroute_render_slideshow',
    ] );
} );

function enroute_render_slideshow( array $attrs ): string {
    $in_editor = defined( 'REST_REQUEST' ) && REST_REQUEST;
    $slides    = array_values( array_filter( (array) ( $attrs['slides'] ?? [] ), fn( $s ) => is_array( $s ) && ( ! empty( $s['title'] ) || ! empty( $s['text'] ) || ! empty( $s['imageId'] ) ) ) );
    if ( ! $slides ) return $in_editor ? ebl_placeholder( __( 'Add slides in the block', 'enroute_blocks' ) ) : '';

    $interval = max( 2, min( 30, (int) ( $attrs['interval'] ?? 6 ) ) );
    $uid      = 'ebl-ss-' . wp_unique_id();

    ob_start(); ?>
    <!--ebl-slideshow-->
    <div class="ebl-slideshow" id="<?php echo esc_attr( $uid ); ?>" data-interval="<?php echo (int) $interval * 1000; ?>" aria-roledescription="carousel">
        <?php foreach ( $slides as $i => $s ) :
            $color   = enroute_blocks_get_color( (int) ( $s['colorIndex'] ?? 1 ) );
            $img_url = ! empty( $s['imageId'] ) ? ( wp_get_attachment_image_url( (int) $s['imageId'], 'full' ) ?: '' ) : '';
            if ( ! $img_url && ! empty( $s['imageUrl'] ) ) $img_url = $s['imageUrl'];
            $link    = trim( (string) ( $s['linkUrl'] ?? '' ) );
            if ( $link && function_exists( 'enroute_normalize_url' ) ) $link = enroute_normalize_url( $link );
            $title   = (string) ( $s['title'] ?? '' );
        ?>
        <div class="ebl-slide<?php echo $i === 0 ? ' is-active' : ''; ?>" aria-roledescription="slide" aria-label="<?php echo esc_attr( ( $i + 1 ) . ' / ' . count( $slides ) ); ?>"<?php echo $i === 0 ? '' : ' aria-hidden="true"'; ?>>
            <div class="ebl-slide-text" style="background:<?php echo esc_attr( $color ); ?>;">
                <div class="ebl-slide-inner">
                    <?php if ( $title ) : ?>
                    <h2 class="ebl-slide-title">
                        <?php if ( $link ) : ?><a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a><?php else : echo esc_html( $title ); endif; ?>
                    </h2>
                    <?php endif; ?>
                    <?php if ( ! empty( $s['text'] ) ) : ?>
                    <div class="ebl-slide-body"><?php echo wp_kses_post( $s['text'] ); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="ebl-slide-img">
                <?php if ( $img_url ) : ?>
                    <?php if ( $link ) : ?><a href="<?php echo esc_url( $link ); ?>" tabindex="-1"><?php endif; ?>
                    <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $title ); ?>"<?php echo $i === 0 ? '' : ' loading="lazy"'; ?>>
                    <?php if ( $link ) : ?></a><?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if ( count( $slides ) > 1 ) : ?>
        <div class="ebl-slide-dots">
            <?php foreach ( $slides as $i => $s ) : ?>
            <button type="button" class="ebl-slide-dot<?php echo $i === 0 ? ' is-active' : ''; ?>" data-slide="<?php echo (int) $i; ?>" aria-label="<?php echo esc_attr( ( $i + 1 ) . ' / ' . count( $slides ) ); ?>"></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <!--/ebl-slideshow-->
    <?php return ob_get_clean();
}

// Slideshow behaviour (autoplay, dots, pause on hover/focus); printed once in the footer
add_action( 'wp_footer', function() {
    if ( ! is_front_page() ) return; ?>
    <script>
    (function () {
        document.querySelectorAll('.ebl-slideshow').forEach(function (show) {
            var slides = show.querySelectorAll('.ebl-slide');
            var dots   = show.querySelectorAll('.ebl-slide-dot');
            if (slides.length < 2) return;
            var current = 0, timer = null, delay = parseInt(show.dataset.interval, 10) || 6000;
            var reduce  = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            function go(n) {
                slides[current].classList.remove('is-active'); slides[current].setAttribute('aria-hidden', 'true');
                if (dots[current]) dots[current].classList.remove('is-active');
                current = (n + slides.length) % slides.length;
                slides[current].classList.add('is-active'); slides[current].removeAttribute('aria-hidden');
                if (dots[current]) dots[current].classList.add('is-active');
            }
            function start() { if (!reduce) { stop(); timer = setInterval(function () { go(current + 1); }, delay); } }
            function stop()  { if (timer) clearInterval(timer); timer = null; }
            dots.forEach(function (d) { d.addEventListener('click', function () { go(parseInt(d.dataset.slide, 10)); start(); }); });
            show.addEventListener('mouseenter', stop);  show.addEventListener('mouseleave', start);
            show.addEventListener('focusin', stop);     show.addEventListener('focusout', start);
            start();
        });
    })();
    </script>
<?php }, 20 );

// ── LOGO CAROUSEL BLOCK ───────────────────────────────────────────────────────
// Shows the logos (menu "Logos") of the current language. If they fit in one
// row they are shown centred; otherwise they move on one logo at a time
// (endless loop, pause on hover). On the front page it is placed below the grid.
add_action( 'init', function() {
    register_block_type( 'enroute/logo-carousel', [
        'attributes' => [
            'lines'    => [ 'type' => 'boolean', 'default' => true ],
            'interval' => [ 'type' => 'number',  'default' => 3 ], // seconds per step
        ],
        'render_callback' => 'enroute_render_logo_carousel',
    ] );
} );

function enroute_render_logo_carousel( array $attrs ): string {
    $in_editor = defined( 'REST_REQUEST' ) && REST_REQUEST;
    $logos     = function_exists( 'ebl_get_logos' ) ? ebl_get_logos() : [];
    if ( ! $logos ) return $in_editor ? ebl_placeholder( __( 'No logos for this language yet (menu "Logos")', 'enroute_blocks' ) ) : '';

    $interval = max( 1, min( 20, (float) ( $attrs['interval'] ?? 3 ) ) );
    $GLOBALS['ebl_logos_used'] = true;
    $classes  = 'ebl-logos' . ( ! isset( $attrs['lines'] ) || $attrs['lines'] ? ' has-lines' : '' );

    ob_start(); ?>
    <!--ebl-logos-->
    <div class="<?php echo esc_attr( $classes ); ?>" data-interval="<?php echo (int) round( $interval * 1000 ); ?>">
        <div class="ebl-logos-viewport">
            <ul class="ebl-logos-track">
                <?php foreach ( $logos as $logo ) : ?>
                <li class="ebl-logo">
                    <?php if ( $logo['link'] ) : ?><a href="<?php echo esc_url( $logo['link'] ); ?>" target="_blank" rel="noopener" title="<?php echo esc_attr( $logo['name'] ); ?>"><?php endif; ?>
                    <img src="<?php echo esc_url( $logo['img'] ); ?>" alt="<?php echo esc_attr( $logo['name'] ); ?>">
                    <?php if ( $logo['link'] ) : ?></a><?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <!--/ebl-logos-->
    <?php return ob_get_clean();
}

// Logo carousel behaviour: step by step, only when the logos don't fit; printed once in the footer
add_action( 'wp_footer', function() {
    if ( empty( $GLOBALS['ebl_logos_used'] ) ) return; ?>
    <script>
    (function () {
        var boxes = document.querySelectorAll('.ebl-logos');
        if (!boxes.length) return;
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var DURATION = 600; // ms for one step

        function setup(box) {
            var viewport = box.querySelector('.ebl-logos-viewport');
            var track    = box.querySelector('.ebl-logos-track');
            if (!track._orig) track._orig = Array.prototype.slice.call(track.children);
            // reset: original order, no movement
            if (box._timer) clearInterval(box._timer);
            box._timer = null; box._busy = false;
            track._orig.forEach(function (li) { track.appendChild(li); });
            track.style.transition = 'none'; track.style.transform = '';
            box.classList.remove('is-scrolling');
            if (track.scrollWidth <= viewport.clientWidth + 1) return; // fits: static, centred
            box.classList.add('is-scrolling');
            if (reduce) return; // scrollable by swipe/scrollbar only, no automatic movement

            function step() {
                if (box._busy || box._paused) return;
                var first = track.firstElementChild; if (!first) return;
                var gap   = parseFloat(getComputedStyle(track).columnGap) || 0;
                box._busy = true;
                track.style.transition = 'transform ' + DURATION + 'ms ease-in-out';
                track.style.transform  = 'translateX(' + -(first.offsetWidth + gap) + 'px)';
                setTimeout(function () {
                    track.style.transition = 'none';
                    track.appendChild(first);           // first logo goes to the end
                    track.style.transform = '';
                    void track.offsetWidth;            // apply before the next step
                    box._busy = false;
                }, DURATION + 20);
            }
            var delay = Math.max(1000, parseInt(box.dataset.interval, 10) || 3000);
            box._timer = setInterval(step, delay);
        }
        boxes.forEach(function (box) {
            box.addEventListener('mouseenter', function () { box._paused = true; });
            box.addEventListener('mouseleave', function () { box._paused = false; });
            box.addEventListener('focusin',    function () { box._paused = true; });
            box.addEventListener('focusout',   function () { box._paused = false; });
        });

        var t;
        function all() { boxes.forEach(setup); }
        // re-measure when a logo finishes loading late
        boxes.forEach(function (box) {
            box.querySelectorAll('img').forEach(function (img) {
                if (!img.complete) img.addEventListener('load', function () { clearTimeout(t); t = setTimeout(all, 100); });
            });
        });
        var lastW = window.innerWidth;
        window.addEventListener('resize', function () {
            if (window.innerWidth === lastW) return; lastW = window.innerWidth; // ignore mobile toolbar height changes
            clearTimeout(t); t = setTimeout(all, 200);
        });
        // wait for the images so the widths are known
        if (document.readyState === 'complete') all(); else window.addEventListener('load', all);
    })();
    </script>
<?php }, 20 );

// ── Grid wrapper on front page ────────────────────────────────────────────────
add_filter( 'the_content', function( string $content ): string {
    if ( ! is_front_page() ) return $content;
    if ( ! has_block( 'enroute/featured-offer' ) && ! has_block( 'enroute/featured-guide' ) &&
         ! has_block( 'enroute/featured-blog' )  && ! has_block( 'enroute/featured-custom' ) &&
         ! has_block( 'enroute/featured-event' ) && ! has_block( 'enroute/slideshow' ) &&
         ! has_block( 'enroute/logo-carousel' ) ) return $content;

    // Slideshows go above the grid (not inside it)
    $top = '';
    if ( preg_match_all( '#<!--ebl-slideshow-->.*?<!--/ebl-slideshow-->#s', $content, $m ) ) {
        $top     = implode( '', $m[0] );
        $content = str_replace( $m[0], '', $content );
    }
    // Logo carousels go below the grid, full width
    $bottom = '';
    if ( preg_match_all( '#<!--ebl-logos-->.*?<!--/ebl-logos-->#s', $content, $m ) ) {
        $bottom  = implode( '', $m[0] );
        $content = str_replace( $m[0], '', $content );
    }
    $grid = trim( $content ) !== '' ? '<div class="ebl-grid">' . $content . '</div>' : '';
    return $top . $grid . $bottom;
} );

// ── Block category ────────────────────────────────────────────────────────────
add_filter( 'block_categories_all', function( array $cats ): array {
    array_unshift( $cats, [ 'slug' => 'enroute', 'title' => 'Enroute', 'icon' => null ] );
    return $cats;
} );
