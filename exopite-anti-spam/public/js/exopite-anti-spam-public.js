// http://jqueryboilerplate.com
; (function ($, window, document, undefined) {

    "use strict";

    var pluginName = "exopiteAntiSpam",
        defaults = {
            propertyName: "value"
        };

    function Plugin(element, options) {
        this.element = element;
        this.settings = $.extend({}, defaults, options);
        this._defaults = defaults;
        this._name = pluginName;
        this.init();
    }

    $.extend(Plugin.prototype, {
        init: function () {
            var plugin = this;
            plugin.buildCache();

            plugin.ajaxUrl = plugin.$element.find('.eas-ajax-url').data('ajax-url');

            if (!plugin.ajaxUrl.length) {
                return;
            }

            plugin.$exanspselAuth = this.$element.find('.exanspsel-auth');
            plugin.$timestamp = this.$element.find('.eastimestamp');

            plugin.bindEvents();
            plugin.ajaxLoad();
        },
        ajaxLoad: function () {
            var plugin = this;
            if ( plugin.$element.find('.eas-ajax-url').data('ajax-load') ) {
                plugin.getElements(true, true);
            }
        },
        bindEvents: function () {
            var plugin = this;
            $(document).on('wpcf7mailsent', function () {
                plugin.getElements(true,true);
            });
            $(document).on('wpcf7invalid', function () {
                plugin.getElements(false, true);
            });


        },
        destroy: function () {
            this.unbindEvents();
            this.$element.removeData();
        },
        unbindEvents: function () {
            this.$element.off('.' + this._name);
        },
        buildCache: function () {
            this.$element = $(this.element);
        },
        getElements: function (getTimestamp, getExanspsel) {
            var plugin = this;
            var data = {};

            var exanspselAuth = '';
            if (getExanspsel && plugin.$exanspselAuth.length) {
                exanspselAuth = plugin.$exanspselAuth.val();
                plugin.$element.find('.eas-image-selector').addClass('loading');
            }

            var timestamp = '';
            if (getTimestamp && plugin.$timestamp.length) {
                timestamp = true;
            }

            var dataJSON = {
                'action': 'eap_reload_cf7_fields',
                'exanspselAuth': exanspselAuth,
                'timestamp': timestamp,
                // 'nonce': 'nonce-key',
            };

            $.ajax({
                cache: false,
                type: "POST",
                url: plugin.ajaxUrl,
                data: dataJSON,
                success: function( response ){
                    try {
                        data = JSON.parse(response);
                        plugin.setElements( plugin, data );
                    } catch (error) {}
                },
                error: function( xhr, status, error ) {
                    console.log( 'Status: ' + xhr.status );
                    console.log( 'Error: ' + xhr.responseText );
                },
            });

            return data;
        },
        setElements: function (plugin, data) {

            if (typeof data.timestamp !== 'undefined') {
                plugin.$timestamp.val(data.timestamp);
            }

            if (typeof data.exanspsel !== 'undefined') {
                plugin.$element.find('.eas-image-selector-images').find('input:checkbox').prop('checked', false);
                plugin.$element.find('.exanspsel > .wpcf7-checkbox').html(data.exanspsel);
                plugin.$exanspselAuth = plugin.$element.find('.exanspsel-auth');
            }

        },

    });

    $.fn[pluginName] = function (options) {
        return this.each(function () {
            if (!$.data(this, "plugin_" + pluginName)) {
                $.data(this, "plugin_" + pluginName, new Plugin(this, options));
            }
        });
    };

})(jQuery, window, document);


(function( $ ) {
	'use strict';

    $(function() {

        $('.wpcf7-form').exopiteAntiSpam();

    });

})( jQuery );
