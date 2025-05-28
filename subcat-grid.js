jQuery(document).ready(function($) {
    let page = 1;
    let loading = false;
    let done = false;

    const container = $('#subcat-grid-container');
    const loadingEl = $('#subcat-grid-loading');

    function loadMore() {
        if (loading || done) return;
        loading = true;

        $.post(SubcatGridData.ajax_url, {
            action: 'subcat_grid_load',
            term_ids: SubcatGridData.term_ids,
            taxonomy: SubcatGridData.taxonomy,
            page: page,
            per_page: SubcatGridData.per_page,
            cache_ttl: SubcatGridData.cache_ttl
        }, function(response) {
            if (response.success) {
                container.append(response.data.html);
                if (response.data.done) {
                    done = true;
                    loadingEl.hide();
                } else {
                    page++;
                }
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
