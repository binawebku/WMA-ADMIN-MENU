# WMA Admin Menu

Provides utility hooks to hide or rearrange menu and submenu items in the WordPress admin area.

## Usage

```php
// Hide top-level menus.
add_filter( 'wma_admin_hidden_menus', function( $menus ) {
    $menus[] = 'edit.php'; // Hide Posts menu.
    return $menus;
} );

// Reorder top-level menus.
add_filter( 'wma_admin_menu_order', function( $order ) {
    $order[] = 'options-general.php'; // Settings first.
    $order[] = 'index.php';          // Dashboard second.
    return $order;
} );

// Hide submenus.
add_filter( 'wma_admin_hidden_submenus', function( $items ) {
    $items[] = [
        'parent'  => 'options-general.php',  // Parent menu slug.
        'submenu' => 'options-writing.php', // Submenu slug to hide.
    ];
    return $items;
} );

// Reorder submenus.
add_filter( 'wma_admin_submenu_order', function( $order ) {
    $order['options-general.php'] = [
        'options-reading.php',
        'options-general.php',
    ];
    return $order;
} );
```
