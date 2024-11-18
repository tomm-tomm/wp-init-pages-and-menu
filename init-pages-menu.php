<?php
/**
 * Create default pages and menus for a WordPress theme after loading WordPress.
 *
 * @package WordPress
 * @subpackage Random Theme
 * @since Random Theme 1.0.0
 */

/*
 * Delete existing pages and create default pages
 */

// @return: new pages created in database
function insert_default_pages() {

    // Initialize wpdb object
    global $wpdb;

    // Delete existing pages
    $wpdb->query( "DELETE p, tr, pm
                   FROM {$wpdb->prefix}posts p
                   LEFT JOIN {$wpdb->prefix}term_relationships tr
                        ON (p.id = tr.object_id)
                   LEFT JOIN {$wpdb->prefix}postmeta pm
                        ON (p.id = pm.post_id)
                   WHERE p.post_type = 'page'" );

    // Random list of pages and subpages
    $page_array = array(
        'Home'           => array(),
        'About'          => array( 'About - Subpage 1',
                                   'About - Subpage 2',
                                   'About - Subpage 3' ),
        'Information'    => array( 'Info - Subpage 1',
                                   'Info - Subpage 2' ),
        'Contact'        => array(),
        'Privacy Policy' => array() );

    // Date of creation
    $now = date( 'Y-m-d H:i:s' );

    // Declare order of page in page list
    // NOTE:
    // Pages in WordPress will be clearly sorted
    $page_order = 0;

    // Create and insert pages to database
    foreach ( $page_array as $page_title => $subpage_array ) {

        // Initialize WP database
        global $wpdb;

        // Check if page exists
        $page_exists = $wpdb->get_row( "SELECT *
                                        FROM $wpdb->posts
                                        WHERE post_title = '" . $page_title . "' AND
                                              post_type = 'page'" );

        // If page doesn't exist
        if ( !$page_exists ) {

            // Increment page order
            // NOTE:
            // Increment by 10 provides room for easier addition of new pages between existing pages in page list
            $page_order += 10;

            // Create page object
            $page_main = array(
                'post_date'   => $now,
                'post_title'  => $page_title,
                'post_status' => 'publish',
                'post_type'   => 'page',
                'menu_order'  => $page_order
            );

            // Insert page into database and return page ID/error
            $parent_id = wp_insert_post( $page_main );

            // Declare variable indicating whether the parent page has a child page
            $parent_has_subpage = 0;

            // If inserting was successful
            if ( $parent_id &&
                 !is_wp_error( $parent_id ) ) {

                // Declare/reset order of subpage in subpage list
                $subpage_order = 0;

                // Create and insert subpages to database
                foreach ( $subpage_array as $key => $subpage_title ) {

                    // Increment subpage order
                    $subpage_order += 10;

                    // Create subpage object
                    $page_sub = array(
                        'post_date'   => $now,
                        'post_title'  => $subpage_title,
                        'post_status' => 'publish',
                        'post_type'   => 'page',
                        'post_parent' => $parent_id,
                        'menu_order'  => $subpage_order
                    );

                    // Insert the subpage into the database
                    wp_insert_post( $page_sub );

                    // Parent page has a child page
                    $parent_has_subpage = 1;

                }

            }

            // Setting the "go to first child" template on the parent page if it has some child pages
            if ( $parent_has_subpage == 1 ) {

                $wpdb->insert( $wpdb->prefix . 'postmeta',
                               array( 'post_id'    => $parent_id,
                                      'meta_key'   => '_wp_page_template',
                                      'meta_value' => 'page-gotochild.php' ) );

            }

            // Set "Home" page as front page
            if ( $page_title == 'Home' ) {

                // Set static page as front page
                update_option( 'show_on_front', 'page' );

                // Set home page as front page
                update_option( 'page_on_front', $parent_id );

            }

        }

    }

    // Set WP option indicating that default pages were created
    // NOTE:
    // Prevent duplicate pages from being created the next time WP is loaded
    update_option( 'default_pages_created', 1 );

}

// Check if default pages have already been created
$pages_created = get_option( 'default_pages_created' );

// If default pages haven't been created
if ( $pages_created != 1 ) {

    // Create default pages after loading WordPress
    add_action( 'init', 'insert_default_pages' );

}

/*
 * Menu item generator
 */

// @params:
// $menu_id (int)       => WP menu identificator
// $page_id (int)       => WP page identificator
// $page_title (string) => WP page title
// $page_slug (string)  => WP page slug
// @return: created menu items
function generate_menu_item( $menu_id, $page_id, $page_title, $page_slug ) {

    // Load arrays with menu items
    global $menu_items;

    // Creating first-level menu items
    // NOTE:
    // If the first-level menu item has a submenu (if the page is parent), set the post as an empty custom link to make it unclickable
    if ( isset( $menu_items[ $page_slug . '-menu' ] ) ) {

        $menu_item_id = wp_update_nav_menu_item( $menu_id, 0, array(
            'menu-item-object-id' => $page_id,
            'menu-item-object'    => 'page',
            'menu-item-type'      => 'custom',
            'menu-item-title'     => sprintf( __( '%s', 'text_domain' ), $page_title ),
            'menu-item-status'    => 'publish'
        ) );

    } else {

        // If the first-level menu item doesn't have a submenu (if the page is not parent), set the "post_type" for menu item
        // NOTE:
        // Exception for the home page: set the menu item type as "custom" and set the URL
        // Because the home page is set as the front page (no "home" subpage is needed in the URL)
        if ( $page_slug == 'home' ) {
            $menu_item_type = 'custom';
            $menu_item_url = SITE_URI;
        } else {
            $menu_item_type = 'post_type';
            $menu_item_url = '';
        }

        // Set a post as a page
        $menu_item_id = wp_update_nav_menu_item( $menu_id, 0, array(
            'menu-item-object-id' => $page_id,
            'menu-item-object'    => 'page',
            'menu-item-type'      => $menu_item_type,
            'menu-item-title'     => sprintf( __( '%s', 'text_domain' ), $page_title ),
            'menu-item-url'       => $menu_item_url,
            'menu-item-status'    => 'publish'
        ) );

    }

    // Creating second level menu items
    // If the first-level menu item has a submenu (if the page is parent)
    if ( isset( $menu_items[ $page_slug . '-menu' ] ) ) {

        // For each page child
        foreach ( $menu_items[ $page_slug . '-menu' ] as $subpage_title => $subpage_slug ) {

            // Get page ID
            $subpage_data = get_page_by_path( $subpage_slug );
            $subpage_id = $subpage_data->ID;

            // Create the second-level menu item
            wp_update_nav_menu_item( $menu_id, 0, array(
                'menu-item-object-id' => $subpage_id,
                'menu-item-parent-id' => $menu_item_id,
                'menu-item-object'    => 'page',
                'menu-item-type'      => 'post_type',
                'menu-item-title'     => sprintf( __( '%s', 'text_domain' ), $subpage_title ),
                'menu-item-status'    => 'publish'
            ) );

        }

    }

}

/*
 * Create default menus
 */

// @return: created menus and locations
// $menu_name (string)            => new menu name
// $menu_location_target (string) => new menu location (called in create_menus() function)
// $menu_items_array (array)      => WP page title
function generate_site_nav_menu( $menu_name, $menu_location_target, $menu_items_array ) {

    // Create menu with name
    wp_create_nav_menu( $menu_name );

    // Get the menu ID
    $menu_name_obj = get_term_by( 'name', $menu_name, 'nav_menu' );
    $menu_id = $menu_name_obj->term_id;

    // If the menu has items
    if ( !empty( $menu_items_array ) ) {

        // Create the first-level menu items
        foreach( $menu_items_array as $page_title => $page_slug ) {

            // Get the page ID (the page slug is the same as the target of the menu item location)
            $page_data = get_page_by_path( $page_slug );
            $page_id = $page_data->ID;

            // Generate the menu item
            generate_menu_item( $menu_id, $page_id, $page_title, $page_slug );

        }

    }

    // Assign location to the menu
    $locations_name_arr = get_theme_mod( 'nav_menu_locations' );
    $locations_name_arr[ $menu_location_target ] = $menu_name_obj->term_id;
    set_theme_mod( 'nav_menu_locations', $locations_name_arr );

}

/*
 * Navigation generator
 */

// @param:
// $menus (array) => the list of page menus (called in register_and_create_menus() function)
// @return: created menus
function create_menus( $menus ) {

    // Set menus as global
    global $menu_items;

    // Menu items list
    // @params in arrays:
    // menu name (string)
    // menu slug (string)

    // Main menu items
    $menu_items[ 'main-menu' ] = array(
        'Home'        => 'home',
        'About'       => 'about',
        'Information' => 'information',
        'Contact'     => 'contact' );

    // Sidebar menu items
    // NOTE:
    // Menu for pages without subpages has no sidebar (applies to Home and Information pages)
    $menu_items[ 'about-menu' ] = array(
        'About Subpage 1' => 'about/about-subpage-1',
        'About Subpage 2' => 'about/about-subpage-2',
        'About Subpage 3' => 'about/about-subpage-3' );

    $menu_items[ 'browse-menu' ] = array(
        'Information Subpage 1' => 'information/information-subpage-1',
        'Information Subpage 2' => 'information/information-subpage-2' );

    $menu_items[ 'quick-menu' ] = array();

    // For each menu
    foreach ( $menus as $key => $value ) {

        // Generate menu
        generate_site_nav_menu( $value, $key, $menu_items[ $key ] );

    }

    // Set WP option indicating that default menus were created
    // NOTE:
    // Prevent duplicate menus from being created the next time WP is loaded
    update_option( 'default_menus_created', 1 );

}

/*
 * Register and create default menus
 */

// @return: registered and created default menus
function register_and_create_menus() {

    // Menu list
    // NOTE:
    // Always set the main menu after setting the sidebars, as the sidebars will be added to the main menu as a second level.
    // Create sidebar menus first is needed.
    $menus = array(
        // Sidebar menus
        // And second-level menu items of the main menu also
        'about-menu'        => 'About menu',
        'browse-menu'       => 'Browse menu',
        // Main menu
        'main-menu'         => 'Main menu',
        // Quick menu
        'quick-menu'        => 'Quick menu'
    );

    // Register menus (WP function)
    register_nav_menus( $menus );

    // Create a menus if it is not already created
    $menus_created = get_option( 'default_menus_created' );

    if ( $menus_created != 1 ) {
        create_menus( $menus );
    }

}

add_action( 'init', 'register_and_create_menus' );