# ICT Platform - Divi 5 Compatibility Guide

## Overview

ICT Platform v2.0.1+ is fully compatible with Divi 5 and the Divi Builder. This guide explains how the plugin works with Divi and what features are available.

## Compatibility Features

### ✅ Tested With
- **Divi 5.0+**
- **WordPress 6.4+**
- **PHP 8.1+**

### ✅ Divi Builder Support
The following ICT Platform custom post types are fully compatible with Divi Builder:
- Projects (`ict_project`)
- Resources (`ict_resource`)
- Equipment (`ict_equipment`)

You can use the Divi Visual Builder to design these post type pages.

### ✅ Conflict Prevention
- **React Version Management**: Automatically prevents React version conflicts with Divi Builder
- **CSS Isolation**: ICT Platform styles don't interfere with Divi styles
- **Script Prioritization**: Proper enqueueing ensures no JavaScript conflicts

## Installation on Divi Sites

1. **Install ICT Platform** as usual via WordPress admin
2. **Activate the plugin** - Divi compatibility is automatic
3. **Verify compatibility**: Look for success notice in admin if Divi is detected

## Using ICT Platform with Divi

### Shortcodes in Divi Modules
All ICT Platform shortcodes work perfectly in Divi's Code Module or Text Module:

```
[ict_project_dashboard]
[ict_time_tracker]
[ict_inventory_manager]
```

### Custom Post Types in Divi Builder
1. Navigate to Projects → Add New (or other CPT)
2. Click "Use Divi Builder"
3. Design your layout using Divi modules
4. ICT Platform metaboxes remain accessible

### Theme Builder Integration
- Use ICT Platform custom post types in Divi Theme Builder templates
- Create custom layouts for project archives, single projects, etc.
- Full access to ICT Platform custom fields in Dynamic Content

## Visual Builder Mode

When editing with Divi Visual Builder, ICT Platform:
- Automatically disables potentially conflicting scripts
- Maintains admin functionality
- Logs compatibility mode in browser console

## Known Limitations

1. **Advanced React Features**: Some ICT Platform React components may not render in Visual Builder preview (they work fine on the frontend)
2. **Admin Ajax**: Complex admin operations should be done outside Visual Builder mode

## Troubleshooting

### If you experience conflicts:

1. **Clear Cache**: Clear both WordPress and Divi caches
2. **Check Version**: Ensure you're running Divi 5.0+ and ICT Platform 2.0.1+
3. **Check Console**: Browser console will show "ICT Platform: Divi compatibility enabled"
4. **Disable/Re-enable**: Try deactivating and reactivating ICT Platform

### Still Having Issues?

Check these:
- ✓ WordPress is 6.4 or higher
- ✓ PHP is 8.1 or higher  
- ✓ Divi is 5.0 or higher
- ✓ No other plugin conflicts
- ✓ Theme is set to Divi or Divi child theme

## Technical Details

### How Compatibility Works

The plugin automatically detects Divi theme and:
1. Checks Divi version compatibility
2. Adjusts script enqueueing priorities
3. Prevents React version conflicts
4. Adds Divi Builder support to custom post types
5. Isolates CSS to prevent style conflicts

### Hooks and Filters

Developers can customize Divi integration:

```php
// Add more post types to Divi Builder support
add_filter('et_builder_post_types', function($post_types) {
    $post_types[] = 'your_custom_type';
    return $post_types;
});

// Detect Divi in your code
if (ICT_Divi_Compatibility::is_divi_active()) {
    // Divi-specific code
}

// Check if Visual Builder is active
if (ICT_Divi_Compatibility::is_divi_builder_active()) {
    // Visual Builder specific code
}
```

## Updates and Support

- **Automatic Updates**: Divi compatibility is maintained in all updates
- **Testing**: Each release is tested with latest Divi version
- **Documentation**: This guide is updated with new Divi releases

## Changelog

### v2.0.1 - Divi 5 Compatibility
- Added automatic Divi theme detection
- Implemented React conflict prevention
- Added Divi Builder support for custom post types
- Created CSS isolation for Visual Builder
- Added compatibility notices and checks

---

**Need Help?** Check the main ICT Platform documentation or contact support.
