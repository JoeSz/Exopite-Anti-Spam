/* global console */
;(function($) {
    "use strict";

    $(document).ready(function () {

        $('#eas-activate-timestamp').on('click', function(e){

            if ($(this).is(':checked')) {
                $('.eas-timestamp-values').slideDown();
            } else {
                $('.eas-timestamp-values').slideUp();
            }

        });

        if ($('#eas-activate-timestamp').is(':checked')) {
            $('.eas-timestamp-values').show();
        }

        // Values of the limit of failed attempts, only while the limit is active
        $('#eas-activate-ratelimit').on('click', function(){

            if ($(this).is(':checked')) {
                $('.eas-ratelimit-values').slideDown();
            } else {
                $('.eas-ratelimit-values').slideUp();
            }

        });

        if ($('#eas-activate-ratelimit').is(':checked')) {
            $('.eas-ratelimit-values').show();
        }

        // Quarantine actions (release, delete, delete all): ask first
        $(document).on('submit', 'form[data-eas-confirm]', function (e) {
            var question = $(this).data('eas-confirm');

            if (question && !window.confirm(question)) {
                e.preventDefault();
            }
        });

    });

}(jQuery));
