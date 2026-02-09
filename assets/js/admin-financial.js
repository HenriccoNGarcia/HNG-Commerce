/**
 * HNG Admin Financial Dashboard Scripts
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Period selector change
        $('#hng-period-selector').on('change', function() {
            const period = $(this).val();
            const compare = $('#hng-compare-selector').val();
            let url = '?page=hng-financial&period=' + period;
            if (compare) {
                url += '&compare=' + compare;
            }
            window.location.href = url;
        });

        // Compare selector change
        $('#hng-compare-selector').on('change', function() {
            const compare = $(this).val();
            const period = $('#hng-period-selector').val();
            let url = '?page=hng-financial&period=' + period;
            if (compare) {
                url += '&compare=' + compare;
            }
            window.location.href = url;
        });

        // Refresh dashboard
        $('.hng-refresh-dashboard').on('click', function() {
            location.reload();
        });

        // Export PDF
        $('.hng-export-pdf').on('click', function() {
            alert(hngFinancialPage.i18n.exportInDevelopment);
        });
    });

})(jQuery);
