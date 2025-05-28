<?php
/**
 * Plugin Name: Subcategory Grid Shortcode
 * Description: Infinite scroll taxonomy grid with server-side HTML caching and admin tools for cache control.
 * Version: 1.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) exit;

// === SHORTCODE ===
function subcat_grid_shortcode($atts) {
    $atts = shortcode_atts([
        'taxonomy' => '',
        'term_id'  => '',
        'per_page' => 10,
        'cache_ttl' => HOUR_IN_SECONDS
    ], $atts, 'subcat_grid');

    if (!empty($atts['term_id']) && !empty($atts['taxonomy'])) {
        $term = get_term(intval($atts['term_id']), sanitize_key($atts['taxonomy']));
    } elseif (is_category() || is_tax()) {
        $term = get_queried_object();
    } else {
        return '<p><strong>Subcategory Grid:</strong> Invalid archive context.</p>';
    }

    if (empty($term) || is_wp_error($term)) return '';

    $term_id   = $term->term_id;
    $taxonomy  = $atts['taxonomy'] ?: $term->taxonomy;
    $per_page  = intval($atts['per_page']);
    $cache_ttl = intval($atts['cache_ttl']);
    $safe_tax  = sanitize_key($taxonomy);
    $cache_key = "subcat_grid_terms_{$safe_tax}_{$term_id}";

    $term_ids = get_transient($cache_key);

    if (!$term_ids || !is_array($term_ids)) {
        $term_ids = [];
        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'parent'     => $term_id,
            'hide_empty' => true,
            'fields'     => 'all',
        ]);
        if (!is_wp_error($terms)) {
            foreach ($terms as $child) {
                $image_id = get_term_meta($child->term_id, 'z_taxonomy_image_id', true);
                if (!empty($image_id)) {
                    $term_ids[] = $child->term_id;
                }
            }
        }
        set_transient($cache_key, $term_ids, $cache_ttl);
    }

    wp_enqueue_script('subcat-grid-js', plugin_dir_url(__FILE__) . 'subcat-grid.js', ['jquery'], '1.0', true);
    wp_localize_script('subcat-grid-js', 'SubcatGridData', [
        'ajax_url'   => admin_url('admin-ajax.php'),
        'term_ids'   => $term_ids,
        'per_page'   => $per_page,
        'taxonomy'   => $taxonomy,
        'cache_ttl'  => $cache_ttl,
    ]);

    ob_start(); ?>
    <div id="subcat-grid-container" class="subcat-grid" data-page="1"></div>
    <div id="subcat-grid-loading" style="text-align:center; margin-top: 1rem;">Loading more...</div>
    <?php return ob_get_clean();
}
add_shortcode('subcat_grid', 'subcat_grid_shortcode');

// === AJAX HANDLER ===
add_action('wp_ajax_subcat_grid_load', 'subcat_grid_load');
add_action('wp_ajax_nopriv_subcat_grid_load', 'subcat_grid_load');

function subcat_grid_load() {
    $term_ids  = array_map('intval', $_POST['term_ids'] ?? []);
    $taxonomy  = sanitize_key($_POST['taxonomy'] ?? '');
    $page      = max(1, intval($_POST['page'] ?? 1));
    $per_page  = max(1, intval($_POST['per_page'] ?? 10));
    $cache_ttl = max(1, intval($_POST['cache_ttl'] ?? HOUR_IN_SECONDS));

    $start = ($page - 1) * $per_page;
    $slice = array_slice($term_ids, $start, $per_page);
    $safe_tax = sanitize_key($taxonomy);
    $html_cache_key = "subcat_grid_html_{$safe_tax}_{$page}";

    if ($html = get_transient($html_cache_key)) {
        wp_send_json_success(['html' => $html, 'done' => ($start + $per_page) >= count($term_ids)]);
    }

    if (empty($slice)) {
        wp_send_json_success(['html' => '', 'done' => true]);
    }

    $terms = get_terms([
        'taxonomy' => $taxonomy,
        'include'  => $slice,
        'orderby'  => 'include',
        'hide_empty' => false,
    ]);

    if (is_wp_error($terms)) {
        wp_send_json_success(['html' => '', 'done' => true]);
    }

    ob_start();
    foreach ($terms as $term) {
        $image = function_exists('z_taxonomy_image_url') ? z_taxonomy_image_url($term->term_id, 'full') : '';
        $link  = get_term_link($term);
        if (!$image || is_wp_error($link)) continue;
        ?>
        <div class="subcat-grid-card">
            <a href="<?php echo esc_url($link); ?>" class="subcat-grid-link">
                <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($term->name); ?>" class="subcat-grid-image" loading="lazy">
                <h3 class="subcat-grid-title"><?php echo esc_html($term->name); ?></h3>
            </a>
        </div>
        <?php
    }
    $html = ob_get_clean();
    set_transient($html_cache_key, $html, $cache_ttl);

    wp_send_json_success(['html' => $html, 'done' => ($start + $per_page) >= count($term_ids)]);
}

// === ADMIN TOOLS ===
add_action('admin_menu', function () {
    add_menu_page(
        'Subcategory Grid',
        'Subcategory Grid',
        'manage_options',
        'subcat-grid-admin',
        'subcat_grid_admin_page',
        'dashicons-update',
        80
    );
});

function subcat_grid_admin_page() {
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['subcat_clear_cache'])) {
        global $wpdb;
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_subcat_grid_%'");
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_subcat_grid_%'");
        echo '<div class="notice notice-success"><p><strong>All Subcategory Grid cache entries cleared.</strong></p></div>';
    }

    if (isset($_POST['subcat_preload'])) {
        $count = 0;
        $taxonomies = get_taxonomies(['public' => true], 'names');
        foreach ($taxonomies as $taxonomy) {
            $parents = get_terms(['taxonomy' => $taxonomy, 'parent' => 0, 'hide_empty' => false]);
            foreach ($parents as $parent) {
                $term_ids = [];
                $children = get_terms(['taxonomy' => $taxonomy, 'parent' => $parent->term_id, 'hide_empty' => true]);
                foreach ($children as $child) {
                    $image_id = get_term_meta($child->term_id, 'z_taxonomy_image_id', true);
                    if (!empty($image_id)) $term_ids[] = $child->term_id;
                }
                set_transient("subcat_grid_terms_{$taxonomy}_{$parent->term_id}", $term_ids, HOUR_IN_SECONDS);
                foreach (array_chunk($term_ids, 10) as $page => $slice) {
                    $terms = get_terms(['taxonomy' => $taxonomy, 'include' => $slice, 'orderby' => 'include', 'hide_empty' => false]);
                    ob_start();
                    foreach ($terms as $term) {
                        $image = function_exists('z_taxonomy_image_url') ? z_taxonomy_image_url($term->term_id, 'full') : '';
                        $link = get_term_link($term);
                        if (!$image || is_wp_error($link)) continue;
                        echo '<div class="subcat-grid-card"><a href="' . esc_url($link) . '" class="subcat-grid-link"><img src="' . esc_url($image) . '" alt="' . esc_attr($term->name) . '" class="subcat-grid-image" loading="lazy"><h3 class="subcat-grid-title">' . esc_html($term->name) . '</h3></a></div>';
                    }
                    $html = ob_get_clean();
                    set_transient("subcat_grid_html_{$taxonomy}_" . ($page + 1), $html, HOUR_IN_SECONDS);
                    $count++;
                }
            }
        }
        echo '<div class="notice notice-success"><p><strong>Preloaded HTML for ' . esc_html($count) . ' pages.</strong></p></div>';
    }

    echo '<div class="wrap"><h1>Subcategory Grid Tools</h1><form method="post"><p><button type="submit" name="subcat_clear_cache" class="button button-primary">Clear All Cache</button> <button type="submit" name="subcat_preload" class="button">Preload All Pages</button></p></form></div>';
}
