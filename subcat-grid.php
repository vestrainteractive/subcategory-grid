<?php
/**
 * Plugin Name: Subcategories Grid Shortcode (Safe + Cached)
 * Description: Infinite scroll taxonomy grid with server-side HTML caching and sanitized transient keys.
 * Version: 1.0
 * Author: Vestra Interactive
 */

if (!defined('ABSPATH')) exit;

function i411_subcategories_grid_shortcode($atts) {
    $atts = shortcode_atts([
        'taxonomy' => '',
        'term_id'  => '',
        'per_page' => 10,
        'cache_ttl' => HOUR_IN_SECONDS
    ], $atts, 'subcategories_grid');

    if (!empty($atts['term_id']) && !empty($atts['taxonomy'])) {
        $term = get_term(intval($atts['term_id']), sanitize_key($atts['taxonomy']));
    } elseif (is_category() || is_tax()) {
        $term = get_queried_object();
    } else {
        return '<p><strong>Subcategories Grid:</strong> Invalid archive context.</p>';
    }

    if (empty($term) || is_wp_error($term)) return '';

    $term_id    = $term->term_id;
    $taxonomy   = $atts['taxonomy'] ?: $term->taxonomy;
    $per_page   = intval($atts['per_page']);
    $cache_ttl  = intval($atts['cache_ttl']);
    $safe_tax   = sanitize_key($taxonomy);
    $cache_key  = "i411_grid_terms_{$safe_tax}_{$term_id}";

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

    wp_enqueue_script('i411-subcat-ajax', plugin_dir_url(__FILE__) . 'i411-subcat.js', ['jquery'], '2.4', true);
    wp_localize_script('i411-subcat-ajax', 'i411SubcatData', [
        'ajax_url'   => admin_url('admin-ajax.php'),
        'term_ids'   => $term_ids,
        'per_page'   => $per_page,
        'taxonomy'   => $taxonomy,
        'cache_ttl'  => $cache_ttl,
    ]);

    ob_start(); ?>
    <div id="i411-subcat-container" class="i411-subcategory-grid" data-page="1"></div>
    <div id="i411-subcat-loading" style="text-align:center; margin-top: 1rem;">Loading more...</div>
    <?php return ob_get_clean();
}
add_shortcode('subcategories_grid', 'i411_subcategories_grid_shortcode');

add_action('wp_ajax_i411_load_subcategories', 'i411_load_subcategories');
add_action('wp_ajax_nopriv_i411_load_subcategories', 'i411_load_subcategories');

function i411_load_subcategories() {
    $term_ids  = array_map('intval', $_POST['term_ids'] ?? []);
    $taxonomy  = sanitize_key($_POST['taxonomy'] ?? '');
    $page      = max(1, intval($_POST['page'] ?? 1));
    $per_page  = max(1, intval($_POST['per_page'] ?? 10));
    $cache_ttl = max(1, intval($_POST['cache_ttl'] ?? HOUR_IN_SECONDS));

    $start = ($page - 1) * $per_page;
    $slice = array_slice($term_ids, $start, $per_page);
    $safe_tax = sanitize_key($taxonomy);
    $html_cache_key = "i411_grid_html_{$safe_tax}_{$page}";

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
        <div class="i411-subcategory-card">
            <a href="<?php echo esc_url($link); ?>" class="i411-subcategory-link">
                <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($term->name); ?>" class="i411-subcategory-image" loading="lazy">
                <h3 class="i411-subcategory-title"><?php echo esc_html($term->name); ?></h3>
            </a>
        </div>
        <?php
    }
    $html = ob_get_clean();
    set_transient($html_cache_key, $html, $cache_ttl);

    wp_send_json_success(['html' => $html, 'done' => ($start + $per_page) >= count($term_ids)]);
}

add_action('wp_enqueue_scripts', function () {
    wp_register_style('i411-subcategory-style', false);
    wp_enqueue_style('i411-subcategory-style');
    wp_add_inline_style('i411-subcategory-style', '
        .i411-subcategory-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            margin: 2rem 0;
        }

        @media (max-width: 980px) {
            .i411-subcategory-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .i411-subcategory-grid {
                grid-template-columns: 1fr;
            }
        }
    ');
});
