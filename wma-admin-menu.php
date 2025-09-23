<?php
/**
 * Plugin Name: WMA Admin Menu
 * Description: Provides functions to hide and rearrange admin menu and submenu items.
 * Version: 1.0.4
 * Author: Wan Mohd Aiman Binawebpro.com
 * Author URI: https://binawebpro.com
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

    private const VERSION                = '1.0.4';
    private const OPTION_GROUP           = 'wma_admin_menu';
    private const OPTION_HIDDEN_MENUS    = 'wma_admin_hidden_menus';
    private const OPTION_HIDDEN_SUBMENUS = 'wma_admin_hidden_submenus';
    private const OPTION_MENU_LABELS     = 'wma_admin_menu_labels';
    private const OPTION_SUBMENU_LABELS  = 'wma_admin_submenu_labels';
    private const SETTINGS_PAGE_SLUG     = 'wma-admin-menu';
    private const SETTINGS_CAPABILITY    = 'manage_options';
    private const SETTINGS_PAGE_TITLE    = 'WMA Admin Menu';

    /**
     * Tracks whether a fallback menu has been registered for the settings page.
     *
     * @var bool
     */
    private $fallback_menu_registered = false;

    public function __construct() {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_menu', [ $this, 'add_settings_page' ], 998 );
        add_action( 'admin_menu', [ $this, 'modify_menus' ], 999 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
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

        register_setting(
            self::OPTION_GROUP,
            self::OPTION_MENU_LABELS,
            [
                'type'              => 'array',
                'default'           => [],
                'sanitize_callback' => [ $this, 'sanitize_menu_label_map' ],
            ]
        );

        register_setting(
            self::OPTION_GROUP,
            self::OPTION_SUBMENU_LABELS,
            [
                'type'              => 'array',
                'default'           => [],
                'sanitize_callback' => [ $this, 'sanitize_submenu_label_map' ],
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
        $this->fallback_menu_registered = false;

        $hidden_menus = $this->collect_hidden_menu_slugs( $this->get_hidden_menu_slugs() );

        if ( in_array( 'options-general.php', $hidden_menus, true ) && $this->register_fallback_menu() ) {
            return;
        }

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

        if ( function_exists( 'settings_errors' ) ) {
            // Display core success and error notices alongside the plugin reset notice.
            settings_errors();
        }

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
     * Enqueue JavaScript and CSS for the plugin settings page.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     */
    public function enqueue_assets( $hook_suffix = '' ) {
        $expected_hook = 'settings_page_' . self::SETTINGS_PAGE_SLUG;

        if ( $expected_hook !== $hook_suffix ) {
            return;
        }

        if ( ! function_exists( 'wp_enqueue_style' ) || ! function_exists( 'wp_enqueue_script' ) ) {
            return;
        }

        if ( ! function_exists( 'plugins_url' ) ) {
            return;
        }

        $style_url  = plugins_url( 'assets/admin-menu.css', __FILE__ );
        $script_url = plugins_url( 'assets/admin-menu.js', __FILE__ );

        wp_enqueue_style(
            'wma-admin-menu',
            $style_url,
            [],
            $this->get_asset_version( 'assets/admin-menu.css' )
        );

        wp_enqueue_script(
            'wma-admin-menu',
            $script_url,
            [],
            $this->get_asset_version( 'assets/admin-menu.js' ),
            true
        );
    }

    /**
     * Determine whether a submenu parent contains any checked items.
     *
     * @param string $parent_slug Parent menu slug.
     * @param array  $checked_items Checked submenu values.
     * @return bool
     */
    private function has_checked_submenu( $parent_slug, array $checked_items ) {
        $prefix = $parent_slug . '|';

        foreach ( $checked_items as $value ) {
            if ( is_array( $value ) ) {
                continue;
            }

            $value = (string) $value;

            if ( '' === $value ) {
                continue;
            }

            if ( 0 === strpos( $value, $prefix ) ) {
                return true;
            }
        }

        return false;
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

        $this->apply_menu_label_overrides( $menu, $submenu );
    }

    /**
     * Removes top-level menu items using the `wma_admin_hidden_menus` filter.
     *
     * @param array $menu          Global menu array passed by reference.
     * @param array $stored_hidden Menu slugs stored via the settings page.
     */
    private function hide_menus( array &$menu, array $stored_hidden = [] ) {
        $to_hide = $this->collect_hidden_menu_slugs( $stored_hidden );

        if ( empty( $to_hide ) ) {
            return;
        }

        foreach ( $menu as $index => $data ) {
            if ( empty( $data[2] ) ) {
                continue;
            }

            $slug = $data[2];

            if ( ! in_array( $slug, $to_hide, true ) ) {
                continue;
            }

            if ( $this->should_preserve_settings_menu( $slug ) ) {
                continue;
            }

            unset( $menu[ $index ] );
        }
    }

    /**
     * Combine stored and filtered menu slugs targeted for removal.
     *
     * @param array $stored_hidden Menu slugs stored via the settings page.
     * @return array
     */
    private function collect_hidden_menu_slugs( array $stored_hidden = [] ) {
        $filtered = apply_filters( 'wma_admin_hidden_menus', [] );
        $filtered = is_array( $filtered ) ? $filtered : [];

        return $this->normalize_slugs( array_merge( $stored_hidden, $filtered ) );
    }

    /**
     * Determine whether the Settings menu should remain visible to expose the plugin page.
     *
     * @param string $slug Menu slug being evaluated.
     * @return bool
     */
    private function should_preserve_settings_menu( $slug ) {
        if ( 'options-general.php' !== $slug ) {
            return false;
        }

        if ( $this->fallback_menu_registered ) {
            return false;
        }

        return $this->is_plugin_settings_submenu_under_settings();
    }

    /**
     * Check whether the plugin settings page is currently registered beneath Settings.
     *
     * @return bool
     */
    private function is_plugin_settings_submenu_under_settings() {
        global $submenu;

        if ( ! isset( $submenu['options-general.php'] ) || ! is_array( $submenu['options-general.php'] ) ) {
            return false;
        }

        foreach ( $submenu['options-general.php'] as $item ) {
            if ( isset( $item[2] ) && self::SETTINGS_PAGE_SLUG === $item[2] ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Register a fallback top-level menu to ensure the settings screen remains accessible.
     *
     * @return bool True when the fallback menu is registered.
     */
    private function register_fallback_menu() {
        if ( ! function_exists( 'add_menu_page' ) ) {
            return false;
        }

        add_menu_page(
            self::SETTINGS_PAGE_TITLE,
            self::SETTINGS_PAGE_TITLE,
            self::SETTINGS_CAPABILITY,
            self::SETTINGS_PAGE_SLUG,
            [ $this, 'render_settings_page' ]
        );

        $this->fallback_menu_registered = true;

        return true;
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
     * Apply stored label overrides to the menu and submenu structures.
     *
     * @param array $menu    Global menu array passed by reference.
     * @param array $submenu Global submenu array passed by reference.
     */
    private function apply_menu_label_overrides( array &$menu, array &$submenu ) {
        $menu_labels    = $this->get_menu_label_overrides();
        $submenu_labels = $this->get_submenu_label_overrides();

        if ( ! empty( $menu_labels ) && is_array( $menu ) ) {
            foreach ( $menu as $index => $data ) {
                if ( ! isset( $data[2] ) ) {
                    continue;
                }

                $slug = $data[2];

                if ( isset( $menu_labels[ $slug ] ) && '' !== $menu_labels[ $slug ] ) {
                    $menu[ $index ][0] = $menu_labels[ $slug ];
                }
            }
        }

        if ( ! empty( $submenu_labels ) && is_array( $submenu ) ) {
            foreach ( $submenu_labels as $parent_slug => $children ) {
                if ( ! isset( $submenu[ $parent_slug ] ) || ! is_array( $children ) ) {
                    continue;
                }

                foreach ( $submenu[ $parent_slug ] as $child_index => $child_data ) {
                    if ( ! isset( $child_data[2] ) ) {
                        continue;
                    }

                    $child_slug = $child_data[2];

                    if ( isset( $children[ $child_slug ] ) && '' !== $children[ $child_slug ] ) {
                        $submenu[ $parent_slug ][ $child_index ][0] = $children[ $child_slug ];
                    }
                }
            }
        }

        $this->apply_fallback_menu_labels( $menu, $submenu, $menu_labels );
    }

    /**
     * Synchronize fallback menu titles with rename overrides.
     *
     * @param array $menu         Global menu array passed by reference.
     * @param array $submenu      Global submenu array passed by reference.
     * @param array $menu_labels  Stored menu label overrides.
     */
    private function apply_fallback_menu_labels( array &$menu, array &$submenu, array $menu_labels ) {
        if ( ! $this->fallback_menu_registered ) {
            return;
        }

        $fallback_label = '';

        if ( isset( $menu_labels[ self::SETTINGS_PAGE_SLUG ] ) && '' !== $menu_labels[ self::SETTINGS_PAGE_SLUG ] ) {
            $fallback_label = $menu_labels[ self::SETTINGS_PAGE_SLUG ];
        } elseif ( isset( $menu_labels['options-general.php'] ) && '' !== $menu_labels['options-general.php'] ) {
            $fallback_label = $menu_labels['options-general.php'];
        }

        if ( '' === $fallback_label ) {
            return;
        }

        if ( is_array( $menu ) ) {
            foreach ( $menu as $index => $data ) {
                if ( isset( $data[2] ) && self::SETTINGS_PAGE_SLUG === $data[2] ) {
                    $menu[ $index ][0] = $fallback_label;
                }
            }
        }

        if ( isset( $submenu[ self::SETTINGS_PAGE_SLUG ] ) && is_array( $submenu[ self::SETTINGS_PAGE_SLUG ] ) ) {
            foreach ( $submenu[ self::SETTINGS_PAGE_SLUG ] as $child_index => $child_data ) {
                if ( isset( $child_data[2] ) && self::SETTINGS_PAGE_SLUG === $child_data[2] ) {
                    $submenu[ self::SETTINGS_PAGE_SLUG ][ $child_index ][0] = $fallback_label;
                }
            }
        }
    }

    /**
     * Render checkbox controls for top-level menus.
     */
    private function render_menu_checkboxes() {
        $menu_items     = $this->get_menu_items();
        $checked_items  = $this->get_option_array( self::OPTION_HIDDEN_MENUS );

        if ( empty( $menu_items ) ) {
            echo '<p>' . $this->escape_html( 'No top-level menus are available.' ) . '</p>';
            return;
        }

        echo '<div class="wma-admin-menu__menu-group" data-wma-menu-group="true">';

        foreach ( $menu_items as $slug => $data ) {
            $label          = isset( $data['label'] ) ? $data['label'] : $slug;
            $original_label = isset( $data['original_label'] ) ? $data['original_label'] : $label;
            $custom_label   = isset( $data['custom_label'] ) ? $data['custom_label'] : '';

            $is_checked  = in_array( $slug, $checked_items, true ) ? ' checked="checked"' : '';
            $row_classes = 'wma-admin-menu__menu-row' . ( '' !== $custom_label ? ' has-custom-label' : '' );
            $checkbox_id = 'wma-admin-menu-toggle-' . md5( $slug );
            $input_id    = 'wma-admin-menu-label-' . md5( 'label-' . $slug );

            echo '<div class="' . $this->escape_attr( $row_classes ) . '">';
            echo '<div class="wma-admin-menu__menu-primary">';
            echo '<input type="checkbox" id="' . $this->escape_attr( $checkbox_id ) . '" name="' . $this->escape_attr( self::OPTION_HIDDEN_MENUS ) . '[]" value="' . $this->escape_attr( $slug ) . '"' . $is_checked . ' />';
            echo '<label for="' . $this->escape_attr( $checkbox_id ) . '" class="wma-admin-menu__menu-name">' . $this->escape_html( $label ) . '</label>';
            echo '</div>';

            echo '<div class="wma-admin-menu__menu-rename">';
            echo '<label class="wma-admin-menu__field-label" for="' . $this->escape_attr( $input_id ) . '">';
            echo $this->escape_html( 'Rename' ) . '<span class="wma-admin-menu__sr-only"> ' . $this->escape_html( $original_label ) . '</span>';
            echo '</label>';
            echo '<input type="text" id="' . $this->escape_attr( $input_id ) . '" class="wma-admin-menu__text-input" name="' . $this->escape_attr( self::OPTION_MENU_LABELS ) . '[' . $this->escape_attr( $slug ) . ']" value="' . $this->escape_attr( $custom_label ) . '" placeholder="' . $this->escape_attr( $original_label ) . '" />';
            echo '</div>';
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Render checkbox controls for submenu items.
     */
    private function render_submenu_checkboxes() {
        $submenu_items       = $this->get_submenu_items();
        $checked_items       = $this->get_option_array( self::OPTION_HIDDEN_SUBMENUS );

        if ( empty( $submenu_items ) ) {
            echo '<p>' . $this->escape_html( 'No submenu items are available.' ) . '</p>';
            return;
        }

        echo '<div class="wma-admin-menu__submenu-group" data-wma-submenu-group="true">';

        foreach ( $submenu_items as $parent_slug => $data ) {
            $items                 = isset( $data['items'] ) ? $data['items'] : [];
            $parent_label          = isset( $data['parent_label'] ) ? $data['parent_label'] : $parent_slug;
            $parent_original_label = isset( $data['parent_original_label'] ) ? $data['parent_original_label'] : $parent_label;
            $parent_custom_label   = isset( $data['parent_custom_label'] ) ? $data['parent_custom_label'] : '';

            if ( empty( $items ) ) {
                continue;
            }

            $has_checked_submenu = $this->has_checked_submenu( $parent_slug, $checked_items );
            $has_custom_child    = false;

            foreach ( $items as $item_data ) {
                if ( isset( $item_data['custom_label'] ) && '' !== $item_data['custom_label'] ) {
                    $has_custom_child = true;
                    break;
                }
            }

            $should_expand = $has_checked_submenu || $has_custom_child || '' !== $parent_custom_label;
            $row_classes   = 'wma-admin-menu__submenu-row';

            if ( $should_expand ) {
                $row_classes .= ' is-open';
            }

            if ( $has_custom_child || '' !== $parent_custom_label ) {
                $row_classes .= ' has-custom-label';
            }
            $container_id    = 'wma-admin-menu-submenu-' . md5( $parent_slug );
            $expanded_attr   = $should_expand ? 'true' : 'false';
            $aria_hidden_attr = $should_expand ? 'false' : 'true';

            echo '<div class="' . $this->escape_attr( $row_classes ) . '" data-wma-submenu-row="true">';
            echo '<div class="wma-admin-menu__submenu-header">';
            echo '<button type="button" class="wma-admin-menu__submenu-toggle" aria-expanded="' . $this->escape_attr( $expanded_attr ) . '" aria-controls="' . $this->escape_attr( $container_id ) . '">';
            echo '<span class="wma-admin-menu__submenu-title">' . $this->escape_html( $parent_label ) . '</span>';
            echo '<span class="wma-admin-menu__submenu-icon" aria-hidden="true"></span>';
            echo '</button>';
            echo '<div class="wma-admin-menu__submenu-parent-field">';
            $parent_input_id = 'wma-admin-menu-parent-label-' . md5( 'parent-' . $parent_slug );
            echo '<label class="wma-admin-menu__field-label" for="' . $this->escape_attr( $parent_input_id ) . '">';
            echo $this->escape_html( 'Rename' ) . '<span class="wma-admin-menu__sr-only"> ' . $this->escape_html( $parent_original_label ) . '</span>';
            echo '</label>';
            echo '<input type="text" id="' . $this->escape_attr( $parent_input_id ) . '" class="wma-admin-menu__text-input" name="' . $this->escape_attr( self::OPTION_MENU_LABELS ) . '[' . $this->escape_attr( $parent_slug ) . ']" value="' . $this->escape_attr( $parent_custom_label ) . '" placeholder="' . $this->escape_attr( $parent_original_label ) . '" />';
            echo '</div>';
            echo '</div>';

            echo '<div id="' . $this->escape_attr( $container_id ) . '" class="wma-admin-menu__submenu-items" aria-hidden="' . $this->escape_attr( $aria_hidden_attr ) . '">';

            foreach ( $items as $slug => $item_data ) {
                $child_label          = isset( $item_data['label'] ) ? $item_data['label'] : $slug;
                $child_original_label = isset( $item_data['original_label'] ) ? $item_data['original_label'] : $child_label;
                $child_custom_label   = isset( $item_data['custom_label'] ) ? $item_data['custom_label'] : '';

                $value        = $parent_slug . '|' . $slug;
                $is_checked   = in_array( $value, $checked_items, true ) ? ' checked="checked"' : '';
                $child_row_id = 'wma-admin-menu-submenu-checkbox-' . md5( $value );
                $child_input_id = 'wma-admin-menu-submenu-label-' . md5( 'label-' . $value );
                $child_row_classes = 'wma-admin-menu__submenu-item' . ( '' !== $child_custom_label ? ' has-custom-label' : '' );

                echo '<div class="' . $this->escape_attr( $child_row_classes ) . '">';
                echo '<div class="wma-admin-menu__submenu-item-primary">';
                echo '<input type="checkbox" id="' . $this->escape_attr( $child_row_id ) . '" name="' . $this->escape_attr( self::OPTION_HIDDEN_SUBMENUS ) . '[]" value="' . $this->escape_attr( $value ) . '"' . $is_checked . ' />';
                echo '<label for="' . $this->escape_attr( $child_row_id ) . '" class="wma-admin-menu__submenu-name">' . $this->escape_html( $child_label ) . '</label>';
                echo '</div>';
                echo '<div class="wma-admin-menu__submenu-item-field">';
                echo '<label class="wma-admin-menu__field-label" for="' . $this->escape_attr( $child_input_id ) . '">';
                echo $this->escape_html( 'Rename' ) . '<span class="wma-admin-menu__sr-only"> ' . $this->escape_html( $child_original_label ) . '</span>';
                echo '</label>';
                echo '<input type="text" id="' . $this->escape_attr( $child_input_id ) . '" class="wma-admin-menu__text-input" name="' . $this->escape_attr( self::OPTION_SUBMENU_LABELS ) . '[' . $this->escape_attr( $parent_slug ) . '][' . $this->escape_attr( $slug ) . ']" value="' . $this->escape_attr( $child_custom_label ) . '" placeholder="' . $this->escape_attr( $child_original_label ) . '" />';
                echo '</div>';
                echo '</div>';
            }

            echo '</div>';
            echo '</div>';
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
     * Retrieve stored top-level menu label overrides.
     *
     * @return array
     */
    private function get_menu_label_overrides() {
        if ( function_exists( 'get_option' ) ) {
            $value = get_option( self::OPTION_MENU_LABELS, [] );
        } else {
            $value = [];
        }

        return $this->sanitize_menu_label_map( $value );
    }

    /**
     * Retrieve stored submenu label overrides.
     *
     * @return array
     */
    private function get_submenu_label_overrides() {
        if ( function_exists( 'get_option' ) ) {
            $value = get_option( self::OPTION_SUBMENU_LABELS, [] );
        } else {
            $value = [];
        }

        return $this->sanitize_submenu_label_map( $value );
    }

    /**
     * Fetch menu items from the global menu array, ensuring stored selections remain visible.
     *
     * @return array
     */
    private function get_menu_items() {
        global $menu;

        $items          = [];
        $label_overrides = $this->get_menu_label_overrides();

        if ( is_array( $menu ) ) {
            foreach ( $menu as $data ) {
                if ( empty( $data[2] ) ) {
                    continue;
                }

                $slug            = $data[2];
                $original_label  = isset( $data[0] ) ? $this->prepare_menu_label( $data[0] ) : '';
                $original_label  = '' !== $original_label ? $original_label : $this->generate_fallback_label( $slug );
                $custom_label    = isset( $label_overrides[ $slug ] ) ? $label_overrides[ $slug ] : '';
                $display_label   = '' !== $custom_label ? $custom_label : $original_label;

                $items[ $slug ] = [
                    'label'          => $display_label,
                    'original_label' => $original_label,
                    'custom_label'   => $custom_label,
                ];
            }
        }

        foreach ( $this->get_hidden_menu_slugs() as $slug ) {
            if ( isset( $items[ $slug ] ) ) {
                continue;
            }

            $original_label = $this->generate_fallback_label( $slug );
            $custom_label   = isset( $label_overrides[ $slug ] ) ? $label_overrides[ $slug ] : '';
            $display_label  = '' !== $custom_label ? $custom_label : $original_label;

            $items[ $slug ] = [
                'label'          => $display_label,
                'original_label' => $original_label,
                'custom_label'   => $custom_label,
            ];
        }

        foreach ( $label_overrides as $slug => $custom_label ) {
            if ( '' === $custom_label ) {
                continue;
            }

            if ( isset( $items[ $slug ] ) ) {
                $items[ $slug ]['label']        = $custom_label;
                $items[ $slug ]['custom_label'] = $custom_label;
                continue;
            }

            $items[ $slug ] = [
                'label'          => $custom_label,
                'original_label' => $this->generate_fallback_label( $slug ),
                'custom_label'   => $custom_label,
            ];
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

        $items                = [];
        $menu_items           = $this->get_menu_items();
        $menu_label_overrides = $this->get_menu_label_overrides();
        $submenu_overrides    = $this->get_submenu_label_overrides();

        if ( is_array( $submenu ) ) {
            foreach ( $submenu as $parent_slug => $children ) {
                if ( ! is_array( $children ) ) {
                    continue;
                }

                foreach ( $children as $child ) {
                    if ( empty( $child[2] ) ) {
                        continue;
                    }

                    $child_slug          = $child[2];
                    $original_child      = isset( $child[0] ) ? $this->prepare_menu_label( $child[0] ) : '';
                    $original_child      = '' !== $original_child ? $original_child : $this->generate_fallback_label( $child_slug );
                    $custom_child        = isset( $submenu_overrides[ $parent_slug ][ $child_slug ] ) ? $submenu_overrides[ $parent_slug ][ $child_slug ] : '';
                    $display_child       = '' !== $custom_child ? $custom_child : $original_child;

                    if ( ! isset( $items[ $parent_slug ] ) ) {
                        $parent_original = isset( $menu_items[ $parent_slug ] ) ? $menu_items[ $parent_slug ]['original_label'] : $this->generate_fallback_label( $parent_slug );
                        $parent_custom   = isset( $menu_label_overrides[ $parent_slug ] ) ? $menu_label_overrides[ $parent_slug ] : '';
                        $parent_display  = '' !== $parent_custom ? $parent_custom : $parent_original;

                        $items[ $parent_slug ] = [
                            'parent_label'          => $parent_display,
                            'parent_original_label' => $parent_original,
                            'parent_custom_label'   => $parent_custom,
                            'items'                 => [],
                        ];
                    }

                    $items[ $parent_slug ]['items'][ $child_slug ] = [
                        'label'          => $display_child,
                        'original_label' => $original_child,
                        'custom_label'   => $custom_child,
                    ];
                }
            }
        }

        foreach ( $this->get_hidden_submenu_pairs() as $item ) {
            $parent_slug = $item['parent'];
            $child_slug  = $item['submenu'];

            if ( ! isset( $items[ $parent_slug ] ) ) {
                $parent_original = isset( $menu_items[ $parent_slug ] ) ? $menu_items[ $parent_slug ]['original_label'] : $this->generate_fallback_label( $parent_slug );
                $parent_custom   = isset( $menu_label_overrides[ $parent_slug ] ) ? $menu_label_overrides[ $parent_slug ] : '';
                $parent_display  = '' !== $parent_custom ? $parent_custom : $parent_original;

                $items[ $parent_slug ] = [
                    'parent_label'          => $parent_display,
                    'parent_original_label' => $parent_original,
                    'parent_custom_label'   => $parent_custom,
                    'items'                 => [],
                ];
            }

            if ( ! isset( $items[ $parent_slug ]['items'][ $child_slug ] ) ) {
                $original_child = $this->generate_fallback_label( $child_slug );
                $custom_child   = isset( $submenu_overrides[ $parent_slug ][ $child_slug ] ) ? $submenu_overrides[ $parent_slug ][ $child_slug ] : '';
                $display_child  = '' !== $custom_child ? $custom_child : $original_child;

                $items[ $parent_slug ]['items'][ $child_slug ] = [
                    'label'          => $display_child,
                    'original_label' => $original_child,
                    'custom_label'   => $custom_child,
                ];
            }
        }

        foreach ( $submenu_overrides as $parent_slug => $children ) {
            if ( ! is_array( $children ) ) {
                continue;
            }

            if ( ! isset( $items[ $parent_slug ] ) ) {
                $parent_original = isset( $menu_items[ $parent_slug ] ) ? $menu_items[ $parent_slug ]['original_label'] : $this->generate_fallback_label( $parent_slug );
                $parent_custom   = isset( $menu_label_overrides[ $parent_slug ] ) ? $menu_label_overrides[ $parent_slug ] : '';
                $parent_display  = '' !== $parent_custom ? $parent_custom : $parent_original;

                $items[ $parent_slug ] = [
                    'parent_label'          => $parent_display,
                    'parent_original_label' => $parent_original,
                    'parent_custom_label'   => $parent_custom,
                    'items'                 => [],
                ];
            }

            foreach ( $children as $child_slug => $custom_child ) {
                if ( isset( $items[ $parent_slug ]['items'][ $child_slug ] ) ) {
                    $items[ $parent_slug ]['items'][ $child_slug ]['label']        = $custom_child;
                    $items[ $parent_slug ]['items'][ $child_slug ]['custom_label'] = $custom_child;
                    continue;
                }

                $items[ $parent_slug ]['items'][ $child_slug ] = [
                    'label'          => $custom_child,
                    'original_label' => $this->generate_fallback_label( $child_slug ),
                    'custom_label'   => $custom_child,
                ];
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
     * Build a cache-busting version string for plugin assets.
     *
     * @param string $relative_path Asset path relative to the plugin root.
     * @return string
     */
    private function get_asset_version( $relative_path ) {
        $relative_path = ltrim( (string) $relative_path, '/' );
        $path          = dirname( __FILE__ ) . '/' . $relative_path;

        if ( file_exists( $path ) ) {
            $mtime = filemtime( $path );

            if ( false !== $mtime ) {
                return (string) $mtime;
            }
        }

        return self::VERSION;
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
     * Sanitize menu label overrides stored via settings.
     *
     * @param mixed $values Raw input values.
     * @return array
     */
    public function sanitize_menu_label_map( $values ) {
        if ( ! is_array( $values ) ) {
            return [];
        }

        $sanitized = [];

        foreach ( $values as $slug => $label ) {
            if ( is_array( $label ) ) {
                continue;
            }

            $slug = trim( (string) $slug );

            if ( '' === $slug ) {
                continue;
            }

            $label = $this->sanitize_label_value( $label );

            if ( '' === $label ) {
                continue;
            }

            $sanitized[ $slug ] = $label;
        }

        return $sanitized;
    }

    /**
     * Sanitize submenu label overrides stored via settings.
     *
     * @param mixed $values Raw input values.
     * @return array
     */
    public function sanitize_submenu_label_map( $values ) {
        if ( ! is_array( $values ) ) {
            return [];
        }

        $sanitized = [];

        foreach ( $values as $parent_slug => $children ) {
            if ( ! is_array( $children ) ) {
                continue;
            }

            $parent_slug = trim( (string) $parent_slug );

            if ( '' === $parent_slug ) {
                continue;
            }

            foreach ( $children as $child_slug => $label ) {
                if ( is_array( $label ) ) {
                    continue;
                }

                $child_slug = trim( (string) $child_slug );

                if ( '' === $child_slug ) {
                    continue;
                }

                $label = $this->sanitize_label_value( $label );

                if ( '' === $label ) {
                    continue;
                }

                if ( ! isset( $sanitized[ $parent_slug ] ) ) {
                    $sanitized[ $parent_slug ] = [];
                }

                $sanitized[ $parent_slug ][ $child_slug ] = $label;
            }
        }

        return $sanitized;
    }

    /**
     * Normalize rename strings by trimming and stripping tags.
     *
     * @param mixed $label Raw rename value.
     * @return string
     */
    private function sanitize_label_value( $label ) {
        $label = is_scalar( $label ) ? (string) $label : '';

        if ( function_exists( 'wp_strip_all_tags' ) ) {
            $label = wp_strip_all_tags( $label );
        } else {
            $label = strip_tags( $label );
        }

        $label = preg_replace( '/\s+/u', ' ', $label );

        return trim( (string) $label );
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
