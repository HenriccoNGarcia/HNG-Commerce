/**
 * HNG Admin Feedback Page Scripts
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Show screenshot required warning for corrections
        $('#feedback_type').on('change', function() {
            if ($(this).val() === 'correcao') {
                $('#screenshot-required').show();
                $('#screenshot-note').show();
                $('#feedback_screenshot').prop('required', true);
            } else {
                $('#screenshot-required').hide();
                $('#screenshot-note').hide();
                $('#feedback_screenshot').prop('required', false);
            }
        });

        // Validate file size
        $('#feedback_screenshot').on('change', function() {
            const file = this.files[0];
            if (file && file.size > 5 * 1024 * 1024) {
                alert(hngFeedbackPage.i18n.fileTooLarge);
                $(this).val('');
            }
        });
    });

})(jQuery);
