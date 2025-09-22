<?php
// Minimal WordPress hook stubs for testing.

define('ABSPATH', __DIR__);

$filters = [];
$actions = [];
$options = [
    'wma_admin_hidden_menus'    => [],
    'wma_admin_hidden_submenus' => [],
];

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
set_test_option('wma_admin_hidden_menus', ['options-general.php']);
set_test_option('wma_admin_hidden_submenus', []);

do_action('admin_menu');

$expected_menu_hidden_settings = [
    ['Dashboard', 'read', 'index.php'],
    ['WMA Admin Menu', 'manage_options', 'wma-admin-menu'],
];

if ($menu !== $expected_menu_hidden_settings) {
    $tests_passed = false;
    $results['hidden_settings_menu'] = $menu;
}

$fallback_accessible = isset($submenu['wma-admin-menu'][0][2]) && 'wma-admin-menu' === $submenu['wma-admin-menu'][0][2];

if (!$fallback_accessible) {
    $tests_passed = false;
    $results['fallback_submenu'] = isset($submenu['wma-admin-menu']) ? $submenu['wma-admin-menu'] : null;
}

if ($tests_passed) {
    echo "All tests passed\n";
    exit(0);
}

echo "Tests failed\n";
var_export($results);
exit(1);
