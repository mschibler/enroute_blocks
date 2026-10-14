<?php
/**
 * Logos (partners / sponsors) for the logo carousel block.
 *
 * - Managed in one place: menu "Logos" (post type enroute_logo)
 * - Each logo: name, logo image (featured image), link, languages
 *   (no language ticked = not assigned yet, not shown)
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

/** Active unless the checkbox was unticked (logos saved before the field existed count as active). */
function ebl_logo_is_active( int $post_id ): bool {
    return get_post_meta( $post_id, '_logo_active', true ) !== '0';
}

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
            <label><input type="checkbox" name="ebl_logo_active" value="1" <?php checked( ebl_logo_is_active( $post->ID ) ); ?>>
            <strong><?php esc_html_e( 'Active', 'enroute_blocks' ); ?></strong> – <?php esc_html_e( 'show this logo in the logo carousel', 'enroute_blocks' ); ?></label>
        </p>
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
        <p class="description"><?php esc_html_e( 'No language ticked = not assigned yet, the logo is not shown anywhere. The logo image is set in the "Logo" box on the right. Order: drag & drop in the list of logos.', 'enroute_blocks' ); ?></p>
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
    update_post_meta( $post_id, '_logo_active', empty( $_POST['ebl_logo_active'] ) ? '0' : '1' );

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
        'ebl_active' => __( 'Active', 'enroute_blocks' ),
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
        echo esc_html( $langs ? strtoupper( implode( ', ', $langs ) ) : __( '– not assigned –', 'enroute_blocks' ) );
    }
    if ( $col === 'ebl_active' ) {
        $on = ebl_logo_is_active( (int) $post_id );
        printf( '<a href="#" class="ebl-toggle-active" data-id="%d" title="%s"><span class="dashicons %s" style="color:%s;"></span></a>',
            (int) $post_id, esc_attr__( 'Click to switch on / off', 'enroute_blocks' ),
            $on ? 'dashicons-yes-alt' : 'dashicons-dismiss', $on ? '#2e7d32' : '#b32d2e' );
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
        jQuery(document).on("click", ".ebl-toggle-active", function (e) {
            e.preventDefault();
            var $a = jQuery(this), $i = $a.find(".dashicons");
            jQuery.post(ajaxurl, { action: "ebl_logo_toggle", nonce: ' . wp_json_encode( wp_create_nonce( 'ebl_logo_sort' ) ) . ', id: $a.data("id") }, function (r) {
                if (!r || !r.success) return;
                $i.toggleClass("dashicons-yes-alt", r.data.active).toggleClass("dashicons-dismiss", !r.data.active).css("color", r.data.active ? "#2e7d32" : "#b32d2e");
                $a.closest("tr").css("opacity", r.data.active ? 1 : .55);
            });
        });
    ' );
    wp_add_inline_style( 'common', '.column-ebl_sort{width:28px;} .column-ebl_logo{width:140px;} .column-ebl_active{width:60px; text-align:center !important;} #the-list tr.ebl-inactive{opacity:.55;} #the-list tr.ui-sortable-helper{background:#fff; box-shadow:0 2px 8px rgba(0,0,0,.15);}' );
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

add_action( 'wp_ajax_ebl_logo_toggle', function() {
    check_ajax_referer( 'ebl_logo_sort', 'nonce' );
    $id = absint( $_POST['id'] ?? 0 );
    if ( ! $id || get_post_type( $id ) !== 'enroute_logo' || ! current_user_can( 'edit_post', $id ) ) wp_send_json_error( null, 403 );
    $active = ! ebl_logo_is_active( $id );
    update_post_meta( $id, '_logo_active', $active ? '1' : '0' );
    wp_send_json_success( [ 'active' => $active ] );
} );

// Inactive logos are shown faded in the list
add_filter( 'post_class', function( $classes, $class, $post_id ) {
    if ( is_admin() && get_post_type( $post_id ) === 'enroute_logo' && ! ebl_logo_is_active( (int) $post_id ) ) $classes[] = 'ebl-inactive';
    return $classes;
}, 10, 3 );

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
        if ( ! ebl_logo_is_active( $p->ID ) ) continue;
        $langs = (array) ( get_post_meta( $p->ID, '_logo_langs', true ) ?: [] );
        if ( $lang && $lang !== 'all' && ! in_array( $lang, $langs, true ) ) continue; // no language = not assigned yet
        $img = get_the_post_thumbnail_url( $p->ID, 'medium_large' ) ?: get_the_post_thumbnail_url( $p->ID, 'full' );
        if ( ! $img ) continue;
        $logos[] = [ 'name' => get_the_title( $p->ID ), 'img' => $img, 'link' => (string) get_post_meta( $p->ID, '_logo_link', true ) ];
    }
    return $logos;
}

// ── Import from GS Logo Slider ──────────────────────────────────────────────
// Logos → Import from GS Slider. Copies name, logo image (same media file),
// link and order; already imported logos are skipped, so it can be run again.

/** GS Logo Slider post type present in the database (also when the plugin is deactivated). */
function ebl_gs_post_type(): string {
    global $wpdb;
    foreach ( [ 'gs-logo-slider', 'gs_logo_slider', 'gs-logo' ] as $pt ) {
        if ( $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s LIMIT 1", $pt ) ) ) return $pt;
    }
    return '';
}

/** GS logos (raw posts, incl. drafts), in GS order. */
function ebl_gs_logos(): array {
    global $wpdb;
    $pt = ebl_gs_post_type();
    if ( ! $pt ) return [];
    return $wpdb->get_results( $wpdb->prepare(
        "SELECT ID, post_title, post_status, menu_order FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish','draft','pending','private','future') ORDER BY menu_order ASC, post_date DESC",
        $pt
    ) );
}

/** Language(s) of a GS logo: Polylang language, else a GS category named like a language; [] = all. */
function ebl_gs_logo_langs( int $gs_id ): array {
    $site = ebl_logo_languages();
    if ( function_exists( 'pll_get_post_language' ) ) {
        $l = pll_get_post_language( $gs_id );
        if ( $l && isset( $site[ $l ] ) ) return [ $l ];
    }
    $aliases = [
        // GS Slider categories as used on enroute.ch
        'de' => [ 'category-one', 'category one', 'de', 'deutsch', 'german', 'allemand', 'tedesco' ],
        'fr' => [ 'category-two', 'category two', 'fr', 'francais', 'français', 'french', 'franzoesisch', 'französisch', 'francese' ],
        'it' => [ 'category-three', 'category three', 'it', 'italiano', 'italian', 'italienisch', 'italien' ],
    ];
    $langs = [];
    foreach ( get_object_taxonomies( (string) get_post_type( $gs_id ) ) as $tax ) {
        $terms = wp_get_object_terms( $gs_id, $tax );
        if ( is_wp_error( $terms ) ) continue;
        foreach ( $terms as $term ) {
            foreach ( [ strtolower( $term->slug ), strtolower( remove_accents( $term->name ) ), strtolower( $term->name ) ] as $v ) {
                foreach ( $aliases as $code => $names ) {
                    if ( isset( $site[ $code ] ) && in_array( $v, $names, true ) ) $langs[] = $code;
                }
                foreach ( $site as $code => $name ) {
                    if ( $v === strtolower( $name ) ) $langs[] = $code;
                }
            }
        }
    }
    return array_values( array_unique( $langs ) );
}

/** Link of a GS logo (meta key differs between GS versions). */
function ebl_gs_logo_link( int $gs_id ): string {
    foreach ( [ 'client_url', 'gs_logo_url', '_gs_logo_url', 'gsl_url', 'logo_url', 'url' ] as $key ) {
        $v = trim( (string) get_post_meta( $gs_id, $key, true ) );
        if ( $v !== '' ) return $v;
    }
    return '';
}

add_action( 'admin_menu', function() {
    add_submenu_page( 'edit.php?post_type=enroute_logo', __( 'Import from GS Slider', 'enroute_blocks' ), __( 'Import from GS Slider', 'enroute_blocks' ), 'edit_posts', 'ebl-logo-import', 'ebl_logo_import_page' );
} );

function ebl_logo_import_page() {
    global $wpdb;
    $gs       = ebl_gs_logos();
    $imported = array_map( 'intval', $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_logo_gs_id' AND p.post_type = 'enroute_logo' AND p.post_status <> 'trash'" ) );
    $new      = array_filter( $gs, fn( $g ) => ! in_array( (int) $g->ID, $imported, true ) );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Import logos from GS Slider', 'enroute_blocks' ); ?></h1>
        <?php if ( isset( $_GET['imported'] ) ) : ?>
            <div class="notice notice-success"><p><?php printf( esc_html__( '%d logos imported.', 'enroute_blocks' ), (int) $_GET['imported'] ); ?>
            <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=enroute_logo' ) ); ?>"><?php esc_html_e( 'Go to the logos', 'enroute_blocks' ); ?></a></p></div>
        <?php endif; ?>

        <?php if ( ! $gs ) : ?>
            <p><?php esc_html_e( 'No GS Slider logos found in the database.', 'enroute_blocks' ); ?></p>
        <?php else : ?>
            <p><?php printf( esc_html__( '%1$d GS Slider logos found, %2$d of them not imported yet.', 'enroute_blocks' ), count( $gs ), count( $new ) ); ?></p>
            <p class="description"><?php esc_html_e( 'Copied: name, logo image (same file in the media library), link and order. Unpublished GS logos are imported as inactive. Language: taken from the GS category ("category-one" = DE, "category-two" = FR, "category-three" = IT; a logo in several categories gets several languages); logos without one of these categories get no language (not assigned yet, not shown) – assign them afterwards. GS Slider itself is not changed. Logos already imported are skipped.', 'enroute_blocks' ); ?></p>
            <?php if ( $new ) : ?>
            <table class="widefat striped" style="max-width:900px; margin:1rem 0;">
                <thead><tr><th><?php esc_html_e( 'Logo', 'enroute_blocks' ); ?></th><th><?php esc_html_e( 'Name', 'enroute_blocks' ); ?></th><th><?php esc_html_e( 'Link', 'enroute_blocks' ); ?></th><th><?php esc_html_e( 'Languages', 'enroute_blocks' ); ?></th><th><?php esc_html_e( 'Active', 'enroute_blocks' ); ?></th></tr></thead>
                <tbody>
                <?php foreach ( $new as $g ) : $langs = ebl_gs_logo_langs( (int) $g->ID ); $thumb = get_post_thumbnail_id( (int) $g->ID ); ?>
                    <tr>
                        <td><?php echo $thumb ? wp_get_attachment_image( $thumb, [ 120, 60 ], false, [ 'style' => 'max-width:120px; max-height:60px; width:auto; height:auto;' ] ) : '<em>' . esc_html__( 'no image', 'enroute_blocks' ) . '</em>'; ?></td>
                        <td><?php echo esc_html( $g->post_title ); ?></td>
                        <td><?php echo esc_html( ebl_gs_logo_link( (int) $g->ID ) ); ?></td>
                        <td><?php echo esc_html( $langs ? strtoupper( implode( ', ', $langs ) ) : __( '– not assigned –', 'enroute_blocks' ) ); ?></td>
                        <td><?php echo $g->post_status === 'publish' ? '✓' : '–'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="ebl_logo_import">
                <?php wp_nonce_field( 'ebl_logo_import' ); ?>
                <?php submit_button( sprintf( __( 'Import %d logos', 'enroute_blocks' ), count( $new ) ), 'primary', 'submit', false ); ?>
            </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}

add_action( 'admin_post_ebl_logo_import', function() {
    check_admin_referer( 'ebl_logo_import' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_die( esc_html__( 'Not allowed.', 'enroute_blocks' ) );
    global $wpdb;
    $imported = array_map( 'intval', $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_logo_gs_id' AND p.post_type = 'enroute_logo' AND p.post_status <> 'trash'" ) );
    $order    = (int) $wpdb->get_var( "SELECT MAX(menu_order) FROM {$wpdb->posts} WHERE post_type = 'enroute_logo'" );
    $count    = 0;
    foreach ( ebl_gs_logos() as $g ) {
        if ( in_array( (int) $g->ID, $imported, true ) ) continue;
        $id = wp_insert_post( [
            'post_type'   => 'enroute_logo',
            'post_status' => 'publish',
            'post_title'  => $g->post_title,
            'menu_order'  => ++$order,
        ] );
        if ( ! $id || is_wp_error( $id ) ) continue;
        $thumb = get_post_thumbnail_id( (int) $g->ID );
        if ( $thumb ) set_post_thumbnail( $id, $thumb );
        $link = ebl_gs_logo_link( (int) $g->ID );
        if ( $link !== '' && ! preg_match( '#^[a-z][a-z0-9+.\-]*:#i', $link ) ) $link = 'https://' . ltrim( $link, '/' );
        update_post_meta( $id, '_logo_link', esc_url_raw( $link ) );
        update_post_meta( $id, '_logo_langs', ebl_gs_logo_langs( (int) $g->ID ) );
        update_post_meta( $id, '_logo_active', $g->post_status === 'publish' ? '1' : '0' );
        update_post_meta( $id, '_logo_gs_id', (int) $g->ID );
        $count++;
    }
    wp_safe_redirect( admin_url( 'edit.php?post_type=enroute_logo&page=ebl-logo-import&imported=' . $count ) );
    exit;
} );
