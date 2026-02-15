/**
 * HNG Admin Analytics Hub Scripts
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        /**
         * Update URL with new filters
         */
        function updateUrl() {
            const period = $('#hng-period-selector').val();
            const compare = $('#hng-compare-selector').val();
            const source = $('#hng-source-selector').val();
            const tab = new URLSearchParams(window.location.search).get('tab') || 'overview';
            
            let url = '?page=hng-analytics&tab=' + tab + '&period=' + period + '&source=' + source;
            if (compare) {
                url += '&compare=' + compare;
            }
            window.location.href = url;
        }

        // Filter selectors
        $('#hng-period-selector, #hng-compare-selector, #hng-source-selector').on('change', updateUrl);

        // Export report
        $('#hng-export-report').on('click', function() {
            alert(hngAnalyticsPage.i18n.exportInDevelopment);
        });
    });

})(jQuery);
