<?php
/**
 * Plugin Name: WMA Admin Menu
 * Description: Provides functions to hide and rearrange admin menu and submenu items.
 * Version: 1.0.0
 * Author: Wan Mohd Aiman Binawebpro.com
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

    private const OPTION_GROUP           = 'wma_admin_menu';
    private const OPTION_HIDDEN_MENUS    = 'wma_admin_hidden_menus';
    private const OPTION_HIDDEN_SUBMENUS = 'wma_admin_hidden_submenus';
    private const SETTINGS_PAGE_SLUG     = 'wma-admin-menu';
    private const SETTINGS_CAPABILITY    = 'manage_options';
    private const SETTINGS_PAGE_TITLE    = 'WMA Admin Menu';

    public function __construct() {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_menu', [ $this, 'add_settings_page' ], 998 );
        add_action( 'admin_menu', [ $this, 'modify_menus' ], 999 );
    }

    /**
     * Register plugin settings and settings fields.
     */
    public function register_settings() {
        if ( ! function_exists( 'register_setting' ) ) {
            return;
        }

        register_setting(
            self::OPTION_GROUP,
            self::OPTION_HIDDEN_MENUS,
            [
                'type'              => 'array',
                'default'           => [],
                'sanitize_callback' => [ $this, 'sanitize_checkbox_values' ],
            ]
        );

        register_setting(
            self::OPTION_GROUP,
            self::OPTION_HIDDEN_SUBMENUS,
            [
                'type'              => 'array',
                'default'           => [],
                'sanitize_callback' => [ $this, 'sanitize_checkbox_values' ],
            ]
        );

        if ( ! function_exists( 'add_settings_section' ) || ! function_exists( 'add_settings_field' ) ) {
            return;
        }

        add_settings_section(
            'wma_admin_menu_visibility',
            self::SETTINGS_PAGE_TITLE,
            '__return_false',
            self::SETTINGS_PAGE_SLUG
        );

        add_settings_field(
            self::OPTION_HIDDEN_MENUS,
            'Hide top-level menus',
            [ $this, 'render_menu_checklist' ],
            self::SETTINGS_PAGE_SLUG,
            'wma_admin_menu_visibility',
            [
                'type' => 'menu',
            ]
        );

        add_settings_field(
            self::OPTION_HIDDEN_SUBMENUS,
            'Hide submenu items',
            [ $this, 'render_menu_checklist' ],
            self::SETTINGS_PAGE_SLUG,
            'wma_admin_menu_visibility',
            [
                'type' => 'submenu',
            ]
        );
    }

    /**
     * Register the plugin settings page within the Settings menu.
     */
    public function add_settings_page() {
        if ( ! function_exists( 'add_options_page' ) ) {
            return;
        }

        add_options_page(
            self::SETTINGS_PAGE_TITLE,
            self::SETTINGS_PAGE_TITLE,
            self::SETTINGS_CAPABILITY,
            self::SETTINGS_PAGE_SLUG,
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Render the plugin settings page.
     */
    public function render_settings_page() {
        if ( function_exists( 'current_user_can' ) && ! current_user_can( self::SETTINGS_CAPABILITY ) ) {
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>' . $this->escape_html( self::SETTINGS_PAGE_TITLE ) . '</h1>';
        echo '<form method="post" action="options.php">';

        if ( function_exists( 'settings_fields' ) ) {
            settings_fields( self::OPTION_GROUP );
        }

        if ( function_exists( 'do_settings_sections' ) ) {
            do_settings_sections( self::SETTINGS_PAGE_SLUG );
        }

        if ( function_exists( 'submit_button' ) ) {
            submit_button();
        } else {
            echo '<p><input type="submit" value="' . $this->escape_attr( 'Save Changes' ) . '" /></p>';
        }

        echo '</form>';
        echo '</div>';
    }

    /**
     * Render a checkbox list of menu or submenu items.
     *
     * @param array $args Field arguments.
     */
    public function render_menu_checklist( array $args ) {
        $type = isset( $args['type'] ) ? $args['type'] : 'menu';

        if ( 'submenu' === $type ) {
            $this->render_submenu_checkboxes();
            return;
        }

        $this->render_menu_checkboxes();
    }

    /**
     * Hide or reorder menu and submenu items based on filters.
     */
    public function modify_menus() {
        global $menu, $submenu;

        $hidden_menus    = $this->get_hidden_menu_slugs();
        $hidden_submenus = $this->get_hidden_submenu_pairs();

        $this->hide_menus( $menu, $hidden_menus );
        $this->reorder_menus( $menu );

        $this->hide_submenus( $submenu, $hidden_submenus );
        $this->reorder_submenus( $submenu );
    }

    /**
     * Removes top-level menu items using the `wma_admin_hidden_menus` filter.
     *
     * @param array $menu          Global menu array passed by reference.
     * @param array $stored_hidden Menu slugs stored via the settings page.
     */
    private function hide_menus( array &$menu, array $stored_hidden = [] ) {
        $filtered = apply_filters( 'wma_admin_hidden_menus', [] );
        $filtered = is_array( $filtered ) ? $filtered : [];

        $to_hide = $this->normalize_slugs( array_merge( $stored_hidden, $filtered ) );

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
     * @param array $submenu       Global submenu array passed by reference.
     * @param array $stored_hidden Submenu definitions stored via the settings page.
     */
    private function hide_submenus( array &$submenu, array $stored_hidden = [] ) {
        $filtered = apply_filters( 'wma_admin_hidden_submenus', [] );
        $filtered = is_array( $filtered ) ? $filtered : [];

        $to_hide = $this->normalize_hidden_submenu_items( array_merge( $stored_hidden, $filtered ) );

        if ( empty( $to_hide ) ) {
            return;
        }

        foreach ( $to_hide as $item ) {
            $parent       = $item['parent'];
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

    /**
     * Render checkbox controls for top-level menus.
     */
    private function render_menu_checkboxes() {
        $menu_items    = $this->get_menu_items();
        $checked_items = $this->get_option_array( self::OPTION_HIDDEN_MENUS );

        if ( empty( $menu_items ) ) {
            echo '<p>' . $this->escape_html( 'No top-level menus are available.' ) . '</p>';
            return;
        }

        echo '<fieldset class="wma-admin-menu__checkbox-group">';

        foreach ( $menu_items as $slug => $label ) {
            $is_checked = in_array( $slug, $checked_items, true ) ? ' checked="checked"' : '';
            echo '<label><input type="checkbox" name="' . $this->escape_attr( self::OPTION_HIDDEN_MENUS ) . '[]" value="' . $this->escape_attr( $slug ) . '"' . $is_checked . ' /> ' . $this->escape_html( $label ) . '</label><br />';
        }

        echo '</fieldset>';
    }

    /**
     * Render checkbox controls for submenu items.
     */
    private function render_submenu_checkboxes() {
        $submenu_items = $this->get_submenu_items();
        $checked_items = $this->get_option_array( self::OPTION_HIDDEN_SUBMENUS );

        if ( empty( $submenu_items ) ) {
            echo '<p>' . $this->escape_html( 'No submenu items are available.' ) . '</p>';
            return;
        }

        echo '<div class="wma-admin-menu__submenu-group">';

        foreach ( $submenu_items as $parent_slug => $data ) {
            $parent_label = isset( $data['parent_label'] ) ? $data['parent_label'] : $parent_slug;
            echo '<p><strong>' . $this->escape_html( $parent_label ) . '</strong></p>';

            if ( empty( $data['items'] ) ) {
                continue;
            }

            foreach ( $data['items'] as $slug => $label ) {
                $value      = $parent_slug . '|' . $slug;
                $is_checked = in_array( $value, $checked_items, true ) ? ' checked="checked"' : '';
                echo '<label class="wma-admin-menu__submenu-item"><input type="checkbox" name="' . $this->escape_attr( self::OPTION_HIDDEN_SUBMENUS ) . '[]" value="' . $this->escape_attr( $value ) . '"' . $is_checked . ' /> ' . $this->escape_html( $label ) . '</label><br />';
            }
        }

        echo '</div>';
    }

    /**
     * Retrieve an option as an array, providing compatibility with non-WordPress contexts.
     *
     * @param string $option Option name.
     * @return array
     */
    private function get_option_array( $option ) {
        if ( function_exists( 'get_option' ) ) {
            $value = get_option( $option, [] );
        } else {
            $value = [];
        }

        return is_array( $value ) ? $value : [];
    }

    /**
     * Fetch menu items from the global menu array, ensuring stored selections remain visible.
     *
     * @return array
     */
    private function get_menu_items() {
        global $menu;

        $items = [];

        if ( is_array( $menu ) ) {
            foreach ( $menu as $data ) {
                if ( empty( $data[2] ) ) {
                    continue;
                }

                $slug  = $data[2];
                $label = isset( $data[0] ) ? $this->prepare_menu_label( $data[0] ) : $slug;
                $items[ $slug ] = $label;
            }
        }

        foreach ( $this->get_hidden_menu_slugs() as $slug ) {
            if ( isset( $items[ $slug ] ) ) {
                continue;
            }

            $items[ $slug ] = $this->generate_fallback_label( $slug );
        }

        return $items;
    }

    /**
     * Fetch submenu items keyed by parent slug, ensuring stored selections remain visible.
     *
     * @return array
     */
    private function get_submenu_items() {
        global $submenu;

        $items       = [];
        $menu_labels = $this->get_menu_items();

        if ( is_array( $submenu ) ) {
            foreach ( $submenu as $parent_slug => $children ) {
                if ( ! is_array( $children ) ) {
                    continue;
                }

                foreach ( $children as $child ) {
                    if ( empty( $child[2] ) ) {
                        continue;
                    }

                    $child_slug  = $child[2];
                    $child_label = isset( $child[0] ) ? $this->prepare_menu_label( $child[0] ) : $child_slug;

                    if ( ! isset( $items[ $parent_slug ] ) ) {
                        $items[ $parent_slug ] = [
                            'parent_label' => isset( $menu_labels[ $parent_slug ] ) ? $menu_labels[ $parent_slug ] : $this->generate_fallback_label( $parent_slug ),
                            'items'        => [],
                        ];
                    }

                    $items[ $parent_slug ]['items'][ $child_slug ] = $child_label;
                }
            }
        }

        foreach ( $this->get_hidden_submenu_pairs() as $item ) {
            $parent_slug = $item['parent'];
            $child_slug  = $item['submenu'];

            if ( ! isset( $items[ $parent_slug ] ) ) {
                $items[ $parent_slug ] = [
                    'parent_label' => $this->generate_fallback_label( $parent_slug ),
                    'items'        => [],
                ];
            }

            if ( ! isset( $items[ $parent_slug ]['items'][ $child_slug ] ) ) {
                $items[ $parent_slug ]['items'][ $child_slug ] = $this->generate_fallback_label( $child_slug );
            }
        }

        return $items;
    }

    /**
     * Normalize and de-duplicate an array of slugs.
     *
     * @param array $values Raw values.
     * @return array
     */
    private function normalize_slugs( array $values ) {
        $normalized = [];

        foreach ( $values as $value ) {
            if ( is_array( $value ) ) {
                continue;
            }

            $value = trim( (string) $value );

            if ( '' === $value ) {
                continue;
            }

            $normalized[] = $value;
        }

        return array_values( array_unique( $normalized ) );
    }

    /**
     * Retrieve the stored menu slugs that should be hidden.
     *
     * @return array
     */
    private function get_hidden_menu_slugs() {
        return $this->normalize_slugs( $this->get_option_array( self::OPTION_HIDDEN_MENUS ) );
    }

    /**
     * Retrieve stored submenu definitions as normalized arrays.
     *
     * @return array
     */
    private function get_hidden_submenu_pairs() {
        $stored = $this->get_option_array( self::OPTION_HIDDEN_SUBMENUS );
        $items  = [];

        foreach ( $stored as $value ) {
            if ( is_array( $value ) ) {
                continue;
            }

            $parts = explode( '|', (string) $value, 2 );

            if ( 2 !== count( $parts ) ) {
                continue;
            }

            $parent  = trim( $parts[0] );
            $submenu = trim( $parts[1] );

            if ( '' === $parent || '' === $submenu ) {
                continue;
            }

            $items[] = [
                'parent'  => $parent,
                'submenu' => $submenu,
            ];
        }

        return $this->deduplicate_submenu_items( $items );
    }

    /**
     * Normalize hidden submenu definitions and remove duplicates.
     *
     * @param array $items Raw items from options and filters.
     * @return array
     */
    private function normalize_hidden_submenu_items( array $items ) {
        $normalized = [];

        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $parent  = isset( $item['parent'] ) ? trim( (string) $item['parent'] ) : '';
            $submenu = isset( $item['submenu'] ) ? trim( (string) $item['submenu'] ) : '';

            if ( '' === $parent || '' === $submenu ) {
                continue;
            }

            $normalized[] = [
                'parent'  => $parent,
                'submenu' => $submenu,
            ];
        }

        return $this->deduplicate_submenu_items( $normalized );
    }

    /**
     * Remove duplicate submenu definitions.
     *
     * @param array $items Normalized submenu items.
     * @return array
     */
    private function deduplicate_submenu_items( array $items ) {
        $unique = [];
        $result = [];

        foreach ( $items as $item ) {
            $key = $item['parent'] . '|' . $item['submenu'];

            if ( isset( $unique[ $key ] ) ) {
                continue;
            }

            $unique[ $key ] = true;
            $result[]       = $item;
        }

        return $result;
    }

    /**
     * Sanitize checkbox values stored by WordPress.
     *
     * @param mixed $values Raw checkbox submission.
     * @return array
     */
    public function sanitize_checkbox_values( $values ) {
        if ( ! is_array( $values ) ) {
            return [];
        }

        $sanitized = [];

        foreach ( $values as $value ) {
            if ( is_array( $value ) ) {
                continue;
            }

            $value = trim( (string) $value );

            if ( '' === $value ) {
                continue;
            }

            $sanitized[] = $value;
        }

        return array_values( array_unique( $sanitized ) );
    }

    /**
     * Prepare menu labels by stripping markup.
     *
     * @param string $label Menu label string.
     * @return string
     */
    private function prepare_menu_label( $label ) {
        $label = is_string( $label ) ? $label : '';

        if ( function_exists( 'wp_strip_all_tags' ) ) {
            $label = wp_strip_all_tags( $label );
        } else {
            $label = strip_tags( $label );
        }

        return trim( $label );
    }

    /**
     * Generate a human-readable fallback label from a slug.
     *
     * @param string $slug Menu slug.
     * @return string
     */
    private function generate_fallback_label( $slug ) {
        $slug = trim( (string) $slug );

        if ( '' === $slug ) {
            return '';
        }

        $label = str_replace( [ '-', '_' ], ' ', $slug );
        $label = preg_replace( '/\s+/', ' ', $label );

        return ucwords( $label );
    }

    /**
     * Escape HTML content safely when WordPress helpers are unavailable.
     *
     * @param string $text Raw text.
     * @return string
     */
    private function escape_html( $text ) {
        if ( function_exists( 'esc_html' ) ) {
            return esc_html( $text );
        }

        return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
    }

    /**
     * Escape attribute content safely when WordPress helpers are unavailable.
     *
     * @param string $text Raw text.
     * @return string
     */
    private function escape_attr( $text ) {
        if ( function_exists( 'esc_attr' ) ) {
            return esc_attr( $text );
        }

        return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
    }
}

new WMA_Admin_Menu();
