# Subcategory Grid Shortcode

This WordPress plugin displays a responsive grid of subcategories (or any hierarchical taxonomy terms) with featured images. It includes infinite scroll and server-side HTML caching for performance.

## Features

- Infinite scroll of subcategory cards
- Supports any public taxonomy
- Displays only terms with featured images
- Responsive grid layout
- Server-side caching with WordPress transients

## REQUIREMENTS
In order for featured images to work, you MUST use the Categories Images plugin.  This can be found at https://wordpress.org/plugins/categories-images/

## Installation

1. Upload the plugin to `/wp-content/plugins/subcategory-grid-shortcode/`
2. Activate via WordPress Admin > Plugins
3. Place the shortcode on any page or taxonomy archive

## Usage

Shortcode example:

```shortcode
[subcat_grid term_id="4" taxonomy="category" cache_ttl="3600"]



