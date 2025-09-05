<?php
// Minimal WordPress hook stubs for testing.

define('ABSPATH', __DIR__);

$filters = [];
$actions = [];

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

require_once __DIR__ . '/../wma-admin-menu.php';

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

do_action('admin_menu');

$expected_menu = [
    ['Settings', 'manage_options', 'options-general.php'],
    ['Dashboard', 'read', 'index.php'],
];
$expected_submenu_settings = [
    ['Reading', 'manage_options', 'options-reading.php'],
    ['General', 'manage_options', 'options-general.php'],
];

if ($menu === $expected_menu && $submenu['options-general.php'] === $expected_submenu_settings) {
    echo "All tests passed\n";
    exit(0);
}

echo "Tests failed\n";
var_export(['menu' => $menu, 'submenu' => $submenu]);
exit(1);
