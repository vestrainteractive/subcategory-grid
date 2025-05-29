jQuery(document).ready(function($) {
    let page = 1;
    let loading = false;
    let done = false;

    const container = $('#i411-subcat-container');
    const loadingEl = $('#i411-subcat-loading');

    function loadMore() {
        if (loading || done) return;
        loading = true;
        console.log('[i411] Loading page', page);

        $.post(i411SubcatData.ajax_url, {
            action: 'i411_load_subcategories',
            term_ids: i411SubcatData.term_ids,
            taxonomy: i411SubcatData.taxonomy,
            page: page,
            per_page: i411SubcatData.per_page,
            cache_ttl: i411SubcatData.cache_ttl
        }, function(response) {
            if (response.success) {
                container.append(response.data.html);
                if (response.data.done) {
                    done = true;
                    loadingEl.hide();
                    console.log('[i411] All subcategories loaded.');
                } else {
                    page++;
                }
            } else {
                console.error('[i411] AJAX response failed.');
            }
            loading = false;
        });
    }

    if (loadingEl.length > 0) {
        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting) {
                loadMore();
            }
        }, { threshold: 1.0 });

        observer.observe(loadingEl[0]);
    }
});
