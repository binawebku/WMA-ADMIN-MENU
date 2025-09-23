<?php
// Minimal WordPress hook stubs for testing.

define('ABSPATH', __DIR__);

$filters = [];
$actions = [];
$options = [
    'wma_admin_hidden_menus'    => [],
    'wma_admin_hidden_submenus' => [],
    'wma_admin_menu_labels'     => [],
    'wma_admin_submenu_labels'  => [],
];
$wp_settings_errors = [];
$settings_errors_calls = [];

function add_filter($tag, $callback, $priority = 10) {
    global $filters;
    $filters[$tag][$priority][] = $callback;
}

function apply_filters($tag, $value) {
    global $filters;
    if (empty($filters[$tag])) {
        return $value;
    }
    ksort($filters[$tag]);
    foreach ($filters[$tag] as $priority => $callbacks) {
        foreach ($callbacks as $cb) {
            $value = call_user_func($cb, $value);
        }
    }
    return $value;
}

function add_action($tag, $callback, $priority = 10) {
    global $actions;
    $actions[$tag][$priority][] = $callback;
}

function add_settings_error($setting, $code, $message, $type = 'error') {
    global $wp_settings_errors;
    if (!isset($wp_settings_errors[$setting])) {
        $wp_settings_errors[$setting] = [];
    }

    $wp_settings_errors[$setting][] = [
        'setting' => $setting,
        'code'    => $code,
        'message' => $message,
        'type'    => $type,
    ];
}

function do_action($tag, ...$args) {
    global $actions;
    if (empty($actions[$tag])) {
        return;
    }
    ksort($actions[$tag]);
    foreach ($actions[$tag] as $priority => $callbacks) {
        foreach ($callbacks as $cb) {
            call_user_func_array($cb, $args);
        }
    }
}

function add_options_page($page_title, $menu_title, $capability, $menu_slug, $callback = '') {
    global $submenu;
    if (!isset($submenu['options-general.php'])) {
        $submenu['options-general.php'] = [];
    }
    $submenu['options-general.php'][] = [$menu_title, $capability, $menu_slug];
    return $menu_slug;
}

function add_menu_page($page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null) {
    global $menu, $submenu;
    $menu[] = [$menu_title, $capability, $menu_slug];
    if (!isset($submenu[$menu_slug])) {
        $submenu[$menu_slug] = [];
    }
    $submenu[$menu_slug][] = [$menu_title, $capability, $menu_slug];
    return $menu_slug;
}

function get_option($option, $default = false) {
    global $options;
    return array_key_exists($option, $options) ? $options[$option] : $default;
}

function set_test_option($option, $value) {
    global $options;
    $options[$option] = $value;
}

function settings_errors($setting = '', $sanitize = false, $hide_on_update = false) {
    global $wp_settings_errors, $settings_errors_calls;

    $settings_errors_calls[] = $setting;

    $errors = [];

    if ($setting === '') {
        foreach ($wp_settings_errors as $group) {
            foreach ($group as $error) {
                $errors[] = $error;
            }
        }
    } elseif (isset($wp_settings_errors[$setting])) {
        $errors = $wp_settings_errors[$setting];
    }

    foreach ($errors as $error) {
        $type = isset($error['type']) ? $error['type'] : 'error';
        $message = isset($error['message']) ? $error['message'] : '';
        echo '<div class="notice notice-' . $type . '"><p>' . $message . '</p></div>';
    }

    return $errors;
}

function reset_settings_errors_state() {
    global $wp_settings_errors, $settings_errors_calls;
    $wp_settings_errors = [];
    $settings_errors_calls = [];
}

function reset_admin_structures() {
    global $menu, $submenu;
    $menu = [
        ['Dashboard', 'read', 'index.php'],
        ['Posts', 'edit_posts', 'edit.php'],
        ['Settings', 'manage_options', 'options-general.php'],
    ];
    $submenu = [
        'options-general.php' => [
            ['General', 'manage_options', 'options-general.php'],
            ['Writing', 'manage_options', 'options-writing.php'],
            ['Reading', 'manage_options', 'options-reading.php'],
        ],
        'edit.php' => [
            ['All Posts', 'edit_posts', 'edit.php'],
            ['Add New', 'edit_posts', 'post-new.php'],
        ],
    ];
}

require_once __DIR__ . '/../wma-admin-menu.php';

add_filter('wma_admin_hidden_menus', function($menus) {
    $menus[] = 'edit.php';
    return $menus;
});

add_filter('wma_admin_menu_order', function($order) {
    $order[] = 'options-general.php';
    $order[] = 'index.php';
    return $order;
});

add_filter('wma_admin_hidden_submenus', function($items) {
    $items[] = ['parent' => 'options-general.php', 'submenu' => 'options-writing.php'];
    return $items;
});

add_filter('wma_admin_submenu_order', function($order) {
    $order['options-general.php'] = ['options-reading.php', 'options-general.php'];
    return $order;
});

$tests_passed = true;
$results = [];

reset_admin_structures();
set_test_option('wma_admin_hidden_menus', []);
set_test_option('wma_admin_hidden_submenus', []);
set_test_option('wma_admin_menu_labels', []);
set_test_option('wma_admin_submenu_labels', []);

do_action('admin_menu');

$expected_menu_default = [
    ['Settings', 'manage_options', 'options-general.php'],
    ['Dashboard', 'read', 'index.php'],
];

$expected_submenu_settings = [
    ['Reading', 'manage_options', 'options-reading.php'],
    ['General', 'manage_options', 'options-general.php'],
    ['WMA Admin Menu', 'manage_options', 'wma-admin-menu'],
];

if ($menu !== $expected_menu_default) {
    $tests_passed = false;
    $results['default_menu'] = $menu;
}

if (!isset($submenu['options-general.php']) || $submenu['options-general.php'] !== $expected_submenu_settings) {
    $tests_passed = false;
    $results['default_submenu'] = isset($submenu['options-general.php']) ? $submenu['options-general.php'] : null;
}

reset_admin_structures();
set_test_option('wma_admin_hidden_menus', []);
set_test_option('wma_admin_hidden_submenus', []);
set_test_option('wma_admin_menu_labels', ['options-general.php' => 'Site Options']);
set_test_option('wma_admin_submenu_labels', [
    'options-general.php' => ['options-reading.php' => 'Reading Setup'],
]);

do_action('admin_menu');

$expected_menu_renamed = [
    ['Site Options', 'manage_options', 'options-general.php'],
    ['Dashboard', 'read', 'index.php'],
];

$expected_submenu_renamed = [
    ['Reading Setup', 'manage_options', 'options-reading.php'],
    ['General', 'manage_options', 'options-general.php'],
    ['WMA Admin Menu', 'manage_options', 'wma-admin-menu'],
];

if ($menu !== $expected_menu_renamed) {
    $tests_passed = false;
    $results['renamed_menu'] = $menu;
}

if (!isset($submenu['options-general.php']) || $submenu['options-general.php'] !== $expected_submenu_renamed) {
    $tests_passed = false;
    $results['renamed_submenu'] = isset($submenu['options-general.php']) ? $submenu['options-general.php'] : null;
}

reset_admin_structures();
set_test_option('wma_admin_hidden_menus', ['options-general.php']);
set_test_option('wma_admin_hidden_submenus', []);
set_test_option('wma_admin_menu_labels', ['options-general.php' => 'Site Options']);
set_test_option('wma_admin_submenu_labels', []);

do_action('admin_menu');

$expected_menu_hidden_settings = [
    ['Dashboard', 'read', 'index.php'],
    ['Site Options', 'manage_options', 'wma-admin-menu'],
];

if ($menu !== $expected_menu_hidden_settings) {
    $tests_passed = false;
    $results['hidden_settings_menu'] = $menu;
}


$fallback_entry = isset($submenu['wma-admin-menu'][0]) ? $submenu['wma-admin-menu'][0] : null;
$fallback_accessible = is_array($fallback_entry) && 'wma-admin-menu' === $fallback_entry[2];

if (!$fallback_accessible) {
    $tests_passed = false;
    $results['fallback_submenu'] = isset($submenu['wma-admin-menu']) ? $submenu['wma-admin-menu'] : null;
} elseif ('Site Options' !== $fallback_entry[0]) {
    $tests_passed = false;
    $results['fallback_label'] = $fallback_entry;
}

reset_settings_errors_state();
add_settings_error('general', 'settings_updated', 'Settings saved.', 'success');
add_settings_error('wma-admin-menu-reset', 'settings_reset', 'Settings reset.', 'success');

$settings_page = new WMA_Admin_Menu();

ob_start();
$settings_page->render_settings_page();
$settings_page_output = ob_get_clean();

if ($settings_errors_calls !== ['']) {
    $tests_passed = false;
    $results['settings_errors_calls'] = $settings_errors_calls;
}

if (strpos($settings_page_output, 'Settings saved.') === false) {
    $tests_passed = false;
    $results['settings_errors_general'] = $settings_page_output;
}

if (strpos($settings_page_output, 'Settings reset.') === false) {
    $tests_passed = false;
    $results['settings_errors_reset'] = $settings_page_output;
}

if ($tests_passed) {
    echo "All tests passed\n";
    exit(0);
}

echo "Tests failed\n";
var_export($results);
exit(1);
