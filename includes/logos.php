<?php
/**
 * Logos (partners / sponsors) for the logo carousel block.
 *
 * - Managed in one place: menu "Logos" (post type enroute_logo)
 * - Each logo: name, logo image (featured image), link, languages
 *   (no language ticked = shown in all languages)
 * - Order: drag & drop in the logo list (menu_order)
 * - Block "Logo Carousel" (render.php) shows the logos of the current language
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Post type ────────────────────────────────────────────────────────────────
add_action( 'init', function() {
    register_post_type( 'enroute_logo', [
        'labels' => [
            'name'                  => __( 'Logos', 'enroute_blocks' ),
            'singular_name'         => __( 'Logo', 'enroute_blocks' ),
            'add_new'               => __( 'Add logo', 'enroute_blocks' ),
            'add_new_item'          => __( 'Add logo', 'enroute_blocks' ),
            'edit_item'             => __( 'Edit logo', 'enroute_blocks' ),
            'all_items'             => __( 'All logos', 'enroute_blocks' ),
            'not_found'             => __( 'No logos yet', 'enroute_blocks' ),
            'featured_image'        => __( 'Logo', 'enroute_blocks' ),
            'set_featured_image'    => __( 'Set logo', 'enroute_blocks' ),
            'remove_featured_image' => __( 'Remove logo', 'enroute_blocks' ),
            'use_featured_image'    => __( 'Use as logo', 'enroute_blocks' ),
        ],
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => true,
        'menu_icon'     => 'dashicons-images-alt',
        'menu_position' => 26,
        'supports'      => [ 'title', 'thumbnail' ],
        'show_in_rest'  => false,
    ] );
} );

// Polylang must not manage logos (languages are a field of the logo)
add_filter( 'pll_get_post_types', function( array $types ): array {
    unset( $types['enroute_logo'] );
    return $types;
}, 20 );

/** Site languages: Polylang languages, else DE/FR/IT. */
function ebl_logo_languages(): array {
    if ( function_exists( 'pll_languages_list' ) ) {
        $slugs = (array) pll_languages_list( [ 'fields' => 'slug' ] );
        $names = (array) pll_languages_list( [ 'fields' => 'name' ] );
        if ( $slugs ) return array_combine( $slugs, $names ?: $slugs );
    }
    return [ 'de' => 'Deutsch', 'fr' => 'Français', 'it' => 'Italiano' ];
}

// ── Fields: link + languages ────────────────────────────────────────────────
add_action( 'add_meta_boxes', function() {
    add_meta_box( 'ebl_logo_details', __( 'Link & languages', 'enroute_blocks' ), function( WP_Post $post ) {
        wp_nonce_field( 'ebl_logo_save', 'ebl_logo_nonce' );
        $link  = get_post_meta( $post->ID, '_logo_link', true );
        $langs = (array) ( get_post_meta( $post->ID, '_logo_langs', true ) ?: [] );
        ?>
        <p>
            <label for="ebl_logo_link"><strong><?php esc_html_e( 'Link', 'enroute_blocks' ); ?></strong></label><br>
            <input type="text" id="ebl_logo_link" name="ebl_logo_link" value="<?php echo esc_attr( $link ); ?>" class="widefat" placeholder="www.example.ch">
        </p>
        <p><strong><?php esc_html_e( 'Show in these languages', 'enroute_blocks' ); ?></strong></p>
        <p>
            <?php foreach ( ebl_logo_languages() as $slug => $name ) : ?>
            <label style="margin-right:1.25rem;">
                <input type="checkbox" name="ebl_logo_langs[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $langs, true ) ); ?>>
                <?php echo esc_html( $name ); ?>
            </label>
            <?php endforeach; ?>
        </p>
        <p class="description"><?php esc_html_e( 'No language ticked = the logo is shown in all languages. The logo image is set in the "Logo" box on the right. Order: drag & drop in the list of logos.', 'enroute_blocks' ); ?></p>
        <?php
    }, 'enroute_logo', 'normal', 'high' );
} );

add_action( 'save_post_enroute_logo', function( int $post_id ) {
    if ( ! isset( $_POST['ebl_logo_nonce'] ) || ! wp_verify_nonce( $_POST['ebl_logo_nonce'], 'ebl_logo_save' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $link = trim( (string) wp_unslash( $_POST['ebl_logo_link'] ?? '' ) );
    if ( $link !== '' && ! preg_match( '#^[a-z][a-z0-9+.\-]*:#i', $link ) ) $link = 'https://' . ltrim( $link, '/' );
    update_post_meta( $post_id, '_logo_link', esc_url_raw( $link ) );

    $allowed = array_keys( ebl_logo_languages() );
    $langs   = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $_POST['ebl_logo_langs'] ?? [] ) ), $allowed ) );
    update_post_meta( $post_id, '_logo_langs', $langs );
} );

// New logos go to the end of the list
add_filter( 'wp_insert_post_data', function( array $data, array $postarr ) {
    if ( $data['post_type'] === 'enroute_logo' && empty( $postarr['ID'] ) && empty( $data['menu_order'] ) ) {
        global $wpdb;
        $data['menu_order'] = (int) $wpdb->get_var( "SELECT MAX(menu_order) FROM {$wpdb->posts} WHERE post_type = 'enroute_logo'" ) + 1;
    }
    return $data;
}, 10, 2 );

// ── List: columns, order, drag & drop ───────────────────────────────────────
add_filter( 'manage_enroute_logo_posts_columns', function( $cols ) {
    return [
        'cb'         => $cols['cb'],
        'ebl_sort'   => '',
        'ebl_logo'   => __( 'Logo', 'enroute_blocks' ),
        'title'      => __( 'Name', 'enroute_blocks' ),
        'ebl_link'   => __( 'Link', 'enroute_blocks' ),
        'ebl_langs'  => __( 'Languages', 'enroute_blocks' ),
    ];
} );
add_action( 'manage_enroute_logo_posts_custom_column', function( $col, $post_id ) {
    if ( $col === 'ebl_sort' ) echo '<span class="dashicons dashicons-menu ebl-drag" title="' . esc_attr__( 'Drag to sort', 'enroute_blocks' ) . '" style="cursor:move; color:#8c8f94;"></span>';
    if ( $col === 'ebl_logo' ) echo get_the_post_thumbnail( $post_id, [ 120, 60 ], [ 'style' => 'max-width:120px; max-height:60px; width:auto; height:auto;' ] );
    if ( $col === 'ebl_link' ) {
        $l = get_post_meta( $post_id, '_logo_link', true );
        if ( $l ) echo '<a href="' . esc_url( $l ) . '" target="_blank" rel="noopener">' . esc_html( preg_replace( '#^https?://(www\.)?#', '', untrailingslashit( $l ) ) ) . '</a>';
    }
    if ( $col === 'ebl_langs' ) {
        $langs = (array) ( get_post_meta( $post_id, '_logo_langs', true ) ?: [] );
        echo esc_html( $langs ? strtoupper( implode( ', ', $langs ) ) : __( 'All', 'enroute_blocks' ) );
    }
}, 10, 2 );

// Admin list: in the saved order, all on one page (so drag & drop covers everything)
add_action( 'pre_get_posts', function( WP_Query $q ) {
    if ( ! is_admin() || ! $q->is_main_query() || $q->get( 'post_type' ) !== 'enroute_logo' ) return;
    if ( empty( $_GET['orderby'] ) ) { $q->set( 'orderby', 'menu_order title' ); $q->set( 'order', 'ASC' ); }
    $q->set( 'posts_per_page', 500 );
} );

add_action( 'admin_enqueue_scripts', function( $hook ) {
    $screen = get_current_screen();
    if ( $hook !== 'edit.php' || ! $screen || $screen->post_type !== 'enroute_logo' ) return;
    wp_enqueue_script( 'jquery-ui-sortable' );
    wp_add_inline_script( 'jquery-ui-sortable', '
        jQuery(function ($) {
            var $list = $("#the-list");
            if (!$list.length || $list.find("tr.no-items").length) return;
            $list.sortable({
                items: "tr", axis: "y", handle: ".ebl-drag, .column-ebl_logo", cursor: "move",
                helper: function (e, tr) { tr.children().each(function () { $(this).width($(this).width()); }); return tr; },
                update: function () {
                    var ids = $list.find("tr").map(function () { return (this.id || "").replace("post-", ""); }).get();
                    $.post(ajaxurl, { action: "ebl_logo_sort", nonce: ' . wp_json_encode( wp_create_nonce( 'ebl_logo_sort' ) ) . ', ids: ids });
                }
            });
        });
    ' );
    wp_add_inline_style( 'common', '.column-ebl_sort{width:28px;} .column-ebl_logo{width:140px;} #the-list tr.ui-sortable-helper{background:#fff; box-shadow:0 2px 8px rgba(0,0,0,.15);}' );
} );

add_action( 'wp_ajax_ebl_logo_sort', function() {
    check_ajax_referer( 'ebl_logo_sort', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( null, 403 );
    foreach ( array_values( (array) ( $_POST['ids'] ?? [] ) ) as $i => $id ) {
        $id = absint( $id );
        if ( $id && get_post_type( $id ) === 'enroute_logo' && current_user_can( 'edit_post', $id ) ) {
            wp_update_post( [ 'ID' => $id, 'menu_order' => $i + 1 ] );
        }
    }
    wp_send_json_success();
} );

// ── Data for the block ──────────────────────────────────────────────────────

/** Logos for a language (current language if empty; 'all' = every logo), in the saved order. */
function ebl_get_logos( string $lang = '' ): array {
    if ( ! $lang && function_exists( 'pll_current_language' ) ) $lang = (string) pll_current_language();
    $posts = get_posts( [
        'post_type'   => 'enroute_logo',
        'post_status' => 'publish',
        'numberposts' => -1,
        'orderby'     => 'menu_order title',
        'order'       => 'ASC',
    ] );
    $logos = [];
    foreach ( $posts as $p ) {
        $langs = (array) ( get_post_meta( $p->ID, '_logo_langs', true ) ?: [] );
        if ( $lang && $lang !== 'all' && $langs && ! in_array( $lang, $langs, true ) ) continue;
        $img = get_the_post_thumbnail_url( $p->ID, 'medium_large' ) ?: get_the_post_thumbnail_url( $p->ID, 'full' );
        if ( ! $img ) continue;
        $logos[] = [ 'name' => get_the_title( $p->ID ), 'img' => $img, 'link' => (string) get_post_meta( $p->ID, '_logo_link', true ) ];
    }
    return $logos;
}
