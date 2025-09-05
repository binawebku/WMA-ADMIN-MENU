<?php
/**
 * Plugin Name: WMA Admin Menu
 * Description: Provides functions to hide and rearrange admin menu and submenu items.
 * Version: 1.0.0
 * Author: OpenAI Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class WMA_Admin_Menu
 *
 * Offers filters to hide and reorder submenu items within the WordPress admin area.
 */
class WMA_Admin_Menu {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'modify_menus' ], 999 );
    }

    /**
     * Hide or reorder menu and submenu items based on filters.
     */
    public function modify_menus() {
        global $menu, $submenu;

        $this->hide_menus( $menu );
        $this->reorder_menus( $menu );

        $this->hide_submenus( $submenu );
        $this->reorder_submenus( $submenu );
    }

    /**
     * Removes top-level menu items using the `wma_admin_hidden_menus` filter.
     *
     * @param array $menu Global menu array passed by reference.
     */
    private function hide_menus( array &$menu ) {
        $to_hide = apply_filters( 'wma_admin_hidden_menus', [] );

        if ( empty( $to_hide ) ) {
            return;
        }

        foreach ( $menu as $index => $data ) {
            if ( isset( $data[2] ) && in_array( $data[2], $to_hide, true ) ) {
                unset( $menu[ $index ] );
            }
        }
    }

    /**
     * Reorders top-level menu items using the `wma_admin_menu_order` filter.
     *
     * @param array $menu Global menu array passed by reference.
     */
    private function reorder_menus( array &$menu ) {
        $desired_order = apply_filters( 'wma_admin_menu_order', [] );

        if ( empty( $desired_order ) ) {
            return;
        }

        $current = $menu;
        $sorted  = [];

        foreach ( $desired_order as $slug ) {
            foreach ( $current as $index => $data ) {
                if ( isset( $data[2] ) && $data[2] === $slug ) {
                    $sorted[] = $data;
                    unset( $current[ $index ] );
                }
            }
        }

        // Append remaining items that were not explicitly ordered.
        $menu = array_merge( $sorted, $current );
    }

    /**
     * Removes submenu items using the `wma_admin_hidden_submenus` filter.
     *
     * @param array $submenu Global submenu array passed by reference.
     */
    private function hide_submenus( array &$submenu ) {
        $to_hide = apply_filters( 'wma_admin_hidden_submenus', [] );

        foreach ( $to_hide as $item ) {
            if ( empty( $item['parent'] ) || empty( $item['submenu'] ) ) {
                continue;
            }

            $parent  = $item['parent'];
            $submenu_slug = $item['submenu'];

            if ( ! isset( $submenu[ $parent ] ) ) {
                continue;
            }

            foreach ( $submenu[ $parent ] as $index => $data ) {
                if ( isset( $data[2] ) && $data[2] === $submenu_slug ) {
                    unset( $submenu[ $parent ][ $index ] );
                }
            }
        }
    }

    /**
     * Reorders submenu items using the `wma_admin_submenu_order` filter.
     *
     * Filter format:
     *     [
     *         'parent-slug' => [ 'submenu-slug-1', 'submenu-slug-2' ]
     *     ]
     * Any submenu slugs omitted from the array maintain their relative order
     * after the specified items.
     *
     * @param array $submenu Global submenu array passed by reference.
     */
    private function reorder_submenus( array &$submenu ) {
        $order_map = apply_filters( 'wma_admin_submenu_order', [] );

        foreach ( $order_map as $parent => $desired_order ) {
            if ( ! isset( $submenu[ $parent ] ) || ! is_array( $desired_order ) ) {
                continue;
            }

            $current      = $submenu[ $parent ];
            $sorted_items = [];

            foreach ( $desired_order as $slug ) {
                foreach ( $current as $index => $data ) {
                    if ( isset( $data[2] ) && $data[2] === $slug ) {
                        $sorted_items[] = $data;
                        unset( $current[ $index ] );
                    }
                }
            }

            // Append remaining items that were not explicitly ordered.
            $submenu[ $parent ] = array_merge( $sorted_items, $current );
        }
    }
}

new WMA_Admin_Menu();
