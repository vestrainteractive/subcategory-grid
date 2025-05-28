
# Subcategory Grid Shortcode

This WordPress plugin displays a responsive grid of subcategories (or any hierarchical taxonomy terms) with featured images. It includes infinite scroll, server-side HTML caching for performance, and admin tools for cache management.

## Features

- Infinite scroll of subcategory cards
- Supports any public taxonomy
- Displays only terms with featured images
- Responsive grid layout
- HTML and ID list caching with transients
- Admin page to clear or preload cache

## Installation

1. Upload the plugin to `/wp-content/plugins/subcategory-grid-shortcode/`
2. Activate via WordPress Admin > Plugins
3. Place the shortcode on any page or taxonomy archive

## Usage

Shortcode example:

    [subcat_grid term_id="4" taxonomy="category" cache_ttl="3600"]

- `term_id`: ID of the parent term
- `taxonomy`: e.g., `category`, `locations`
- `cache_ttl`: Cache lifetime in seconds (optional, default is 3600)

## Admin Tools

- Go to WordPress Admin → **Subcategory Grid**
- Click "Clear All Cache" to remove all term + HTML cache
- Click "Preload All Pages" to prime the cache for all public parent terms
