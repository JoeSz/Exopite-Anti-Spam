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

    });

}(jQuery));
