// http://jqueryboilerplate.com
; (function ($, window, document, undefined) {

    "use strict";

    var pluginName = "exopiteAntiSpamAjax",
        defaults = {};

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

            plugin.ajaxUrl = plugin.$element.data('ajax-url');

            if (!plugin.ajaxUrl.length) {
                return;
            }

            plugin.bindEvents();
        },
        bindEvents: function () {
            var plugin = this;

            plugin.$element.find('.eas-cf7-shortcode-load').off().on('click' + '.' + plugin._name, function (e) {
                plugin.ajaxLoad.call(plugin, e);
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
        setElements: function (plugin, response) {
            plugin.$element.html(response);
            var $form = plugin.$element.find('.wpcf7-form');
            var action = $form.attr('action').split("#");
            $form.attr('action', '#' + action[1]);

            plugin.$element.trigger("easSetElementsBefore", [plugin, response]);

            /**
             * @link https://wordpress.org/support/topic/init-function-wpcf7initform/
             */
            var $form = plugin.$element.find('.wpcf7 > form');

            wpcf7.initForm( $form );
            if ( wpcf7.cached ) {
               wpcf7.refill( $form );
            }

            wpcf7cf.initForm($form);

            $form.exopiteAntiSpam();

            plugin.$element.trigger("easSetElementsAfter", [plugin, response]);

        },
        ajaxLoad: function (e) {
            var plugin = this;
            e.preventDefault();

            var dataJSON = {
                'action': 'eas_get_contact_form_7_ajax',
                'cf7_id': plugin.$element.data('id'),
                'cf7_title': plugin.$element.data('title'),
                'eas_cf7_ajax': true,
            };

            $.ajax({
                cache: false,
                type: "POST",
                url: plugin.ajaxUrl,
                data: dataJSON,
                success: function( response ){
                    plugin.setElements(plugin, response);
                },
                error: function( xhr, status, error ) {
                    console.log( 'Status: ' + xhr.status );
                    console.log( 'Error: ' + xhr.responseText );
                },
            });

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


; (function ($, window, document, undefined) {

    "use strict";

    var pluginName = "exopiteAntiSpam",
        defaults = {};

    function Plugin(element, options) {
        this.element = element;
        this.settings = $.extend({}, defaults, options);
        this._defaults = defaults;
        this._name = pluginName;
        this.movedOrPressed = false;
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
            plugin.$acceptance = this.$element.find('.wpcf7-acceptance input[type="checkbox"]');
            plugin.$acceptanceToken = this.$element.find('#easacceptance');

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

            plugin.$element.parents('.wpcf7').on('wpcf7mailsent', function (e) {
                plugin.getElements(true, true);
            });

            plugin.$element.parents('.wpcf7').on('wpcf7invalid', function (e) {
                plugin.getElements(false, true);
            });

            plugin.$element.find('.wpcf7-acceptance').on('mousemove keypress', function (e) {
                plugin.movedOrPressed = true;
                plugin.$element.find('.wpcf7-acceptance').off('mousemove keypress');
            });

            plugin.$acceptance.on('click', function (e) {
                if ($(this).is(":checked") && plugin.$acceptanceToken.val() == '' && plugin.movedOrPressed) {
                    plugin.getAcceptanceToken();
                }

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
        doAjax: function (dataJSON, callback) {
            var plugin = this;
            $.ajax({
                cache: false,
                type: "POST",
                url: plugin.ajaxUrl,
                data: dataJSON,
                success: function (response) {
                    callback(response, status, plugin)
                },
                error: function( xhr, status, error ) {
                    console.log( 'Status: ' + xhr.status );
                    console.log( 'Error: ' + xhr.responseText );
                },
            });
        },
        setAcceptanceToken: function (response, status, plugin) {
            plugin.$acceptanceToken.val(response)
            plugin.$acceptance.parents('.wpcf7-acceptance').removeClass('loading');
        },
        getAcceptanceToken: function (getTimestamp, getExanspsel) {
            var plugin = this;
            plugin.$acceptance.parents('.wpcf7-acceptance').addClass('loading');

            var dataJSON = {
                'action': 'eap_get_acceptance_token',
            };

            plugin.doAjax(dataJSON, plugin.setAcceptanceToken);
        },
        processElements: function (response, status, plugin) {
            var data = {};
            try {
                data = JSON.parse(response);
                if (typeof data.timestamp !== 'undefined') {
                    plugin.$timestamp.val(data.timestamp);
                }

                if (typeof data.exanspsel !== 'undefined') {
                    plugin.$element.find('.eas-image-selector-images').find('input:checkbox').prop('checked', false);
                    plugin.$element.find('.exanspsel > .wpcf7-checkbox').html(data.exanspsel);
                    plugin.$exanspselAuth = plugin.$element.find('.exanspsel-auth');
                }
            } catch (error) {}
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
                'eas_cf7_ajax': true,
                // 'nonce': 'nonce-key',
            };

            plugin.doAjax(dataJSON, plugin.processElements);

            return data;
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

        $('.eas-cf7-shortcode').exopiteAntiSpamAjax();
        $('.wpcf7-form').exopiteAntiSpam();

        $('.eas-cf7-shortcode').on('easSetElementsAfter', function (event, plugin, response){
            var TextOJB = dnd_cf7_uploader.drag_n_drop_upload;
            plugin.$element.find('.wpcf7-drag-n-drop-file').CodeDropz_Uploader({
                'color'				:	'#fff',
                'ajax_url'			: 	dnd_cf7_uploader.ajax_url,
                'text'				: 	TextOJB.text,
                'separator'			: 	TextOJB.or_separator,
                'button_text'		:	TextOJB.browse,
                'server_max_error'	: 	TextOJB.server_max_error,
                'on_success'		:	function( input, progressBar, response ){

                    // Progressbar Object
                    var progressDetails = $('#' + progressBar, input.parents('.codedropz-upload-wrapper') );

                    // If it's complete remove disabled attribute in button
                    if( $('.in-progress', input.parents('form') ).length === 0 ) {
                        setTimeout(function(){ $('input[type="submit"]', input.parents('form')).removeAttr('disabled'); }, 1);
                    }

                    // Append hidden input field
                    progressDetails
                        .find('.dnd-upload-details')
                            .append('<span><input type="hidden" name="'+ input.attr('data-name') +'[]" value="'+ response.data.path +'/'+ response.data.file +'"></span>');
                }
            });
        });


    });

})( jQuery );
