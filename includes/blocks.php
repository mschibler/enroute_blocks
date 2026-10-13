<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Enqueue block editor assets ───────────────────────────────────────────────
// Register translatable strings with Polylang
add_action( 'init', function() {
    if ( function_exists( 'pll_register_string' ) ) {
        pll_register_string( 'enroute_blocks_guide_link', 'Zu den Guides', 'Enroute Blocks' );
    }
} );

add_action( 'enqueue_block_editor_assets', function() {
    wp_enqueue_script(
        'enroute-blocks-editor',
        ENROUTE_BLOCKS_URL . 'build/editor.js',
        [ 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-block-editor', 'wp-data' ],
        ENROUTE_BLOCKS_VER,
        true
    );
    wp_enqueue_style(
        'enroute-blocks-editor-css',
        ENROUTE_BLOCKS_URL . 'build/editor.css',
        [],
        ENROUTE_BLOCKS_VER
    );

    // Pass settings colors + data to editor
    $colors = [];
    for ( $i = 1; $i <= 5; $i++ ) {
        $colors[] = [
            'index' => $i,
            'color' => get_option( "enroute_featured_color_$i", enroute_get_default_color( $i ) ),
        ];
    }
    // Guides listing URL
    $guides_url = '';
    $guides_page = get_posts([ 'post_type' => 'page', 'meta_key' => '_wp_page_template', 'numberposts' => 1, 'fields' => 'ids' ]);
    // Try to find by shortcode
    $pages = get_posts([ 'post_type' => 'page', 'numberposts' => -1, 's' => 'enroute_guides_listing' ]);
    if ( $pages ) $guides_url = get_permalink( $pages[0]->ID );

    wp_localize_script( 'enroute-blocks-editor', 'enrouteBlocksData', [
        'colors'       => $colors,
        'guidesUrl'    => $guides_url,
        'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
        'nonce'        => wp_create_nonce( 'enroute_blocks_nonce' ),
    ] );
} );

// ── AJAX: search posts/guides/offers ─────────────────────────────────────────
add_action( 'wp_ajax_enroute_blocks_search', function() {
    check_ajax_referer( 'enroute_blocks_nonce', 'nonce' );
    $type    = sanitize_key( $_GET['post_type'] ?? 'post' );
    $search  = sanitize_text_field( $_GET['search'] ?? '' );
    $allowed = [ 'post', 'offer', 'guide' ];
    if ( ! in_array( $type, $allowed, true ) ) wp_send_json_error();

    $posts = get_posts([
        'post_type'   => $type,
        'post_status' => 'publish',
        'numberposts' => 20,
        's'           => $search,
    ]);

    $results = array_map( function( $p ) {
        $thumb = get_the_post_thumbnail_url( $p->ID, 'thumbnail' ) ?: '';
        // For guides, use guide photo
        if ( $p->post_type === 'guide' ) {
            $photo_id = get_post_meta( $p->ID, '_guide_photo_id', true );
            if ( $photo_id ) $thumb = wp_get_attachment_image_url( (int) $photo_id, 'thumbnail' ) ?: $thumb;
        }
        return [ 'id' => $p->ID, 'title' => get_the_title( $p->ID ), 'thumb' => $thumb ];
    }, $posts );

    wp_send_json_success( $results );
} );

// ── Helper ────────────────────────────────────────────────────────────────────
function enroute_get_default_color( int $n ): string {
    // Same defaults as the offers plugin (Settings → General → Default colors)
    $defaults = function_exists( 'enroute_get_default_colors' )
        ? enroute_get_default_colors()
        : [ 1 => '#C9D56B', 2 => '#F8ED84', 3 => '#E8AB58', 4 => '#D4735D', 5 => '#D0687B' ];
    return $defaults[ $n ] ?? $defaults[1];
}

function enroute_blocks_get_color( int $index ): string {
    return get_option( "enroute_featured_color_$index", enroute_get_default_color( $index ) );
}
