// http://jqueryboilerplate.com
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

            if (!plugin.ajaxUrl || !plugin.ajaxUrl.length) {
                return;
            }

            plugin.$exanspselAuth = this.$element.find('.exanspsel-auth');
            // Hidden fields of Contact Form 7 (no class or id).
            plugin.$timestamp = this.$element.find('input[name="eastimestamp"]');
            plugin.$acceptance = this.$element.find('.wpcf7-acceptance input[type="checkbox"]');
            plugin.$acceptanceToken = this.$element.find('input[name="easacceptance"]');

            // Proof-of-work: challenge and solution (hidden fields), worker URL and form ID from the PHP.
            var $settings = plugin.$element.find('.eas-ajax-url');
            plugin.$powChallenge = this.$element.find('input[name="easpowc"]');
            plugin.$powSolution = this.$element.find('input[name="easpows"]');
            plugin.pow = {
                active: !!$settings.data('pow') && plugin.$powChallenge.length > 0 && typeof window.Worker !== 'undefined',
                workerUrl: $settings.data('pow-worker'),
                formId: $settings.data('form-id'),
                ready: false,
                loading: false,
                waiting: false,
                worker: null
            };

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

            /**
             * Ignore a second submit (double click, Enter) while the first one is running: the second request
             * would get "token already used" and its error would replace the "mail sent" message.
             * Capture phase on the wrapper, so it runs before the submit handler of Contact Form 7.
             * Contact Form 7 sets data-status="submitting" until the response arrives; after 30 seconds
             * (e.g. network error, no response) a new submit is allowed again.
             */
            var wrapper = plugin.$element.closest('.wpcf7').get(0);
            if (wrapper && wrapper.addEventListener) {
                wrapper.addEventListener('submit', function (e) {
                    if (e.target !== plugin.element) {
                        return;
                    }
                    // Proof-of-work not solved yet: hold the submit, it is sent automatically when the solution is ready.
                    if (plugin.pow.active && !plugin.pow.ready) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        plugin.pow.waiting = true;
                        plugin.$element.find('[type="submit"]').addClass('eas-pow-waiting');
                        plugin.movedOrPressed = true;
                        plugin.requestPow();
                        return;
                    }
                    var now = new Date().getTime();
                    if (plugin.element.getAttribute('data-status') === 'submitting' && plugin.submittedAt && (now - plugin.submittedAt) < 30000) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        return;
                    }
                    plugin.submittedAt = now;
                }, true);
            }

            plugin.$element.parents('.wpcf7').on('wpcf7mailsent', function (e) {
                plugin.getElements(true, true);
                plugin.refreshAcceptanceToken();
                plugin.resetPow();
            });

            /**
             * Image captcha and acceptance tokens are single use, load new ones after every unsuccessful submission.
             * The timestamp is only reloaded if the server says it is expired or already used
             * (otherwise the minimum time would start again).
             */
            plugin.$element.parents('.wpcf7').on('wpcf7invalid wpcf7spam wpcf7mailfailed wpcf7unaccepted wpcf7aborted', function (e) {
                var detail = (e.originalEvent && e.originalEvent.detail) ? e.originalEvent.detail : e.detail;
                var refreshTimestamp = !!(detail && detail.apiResponse && detail.apiResponse.eas_refresh_timestamp);
                plugin.getElements(refreshTimestamp, true);
                plugin.refreshAcceptanceToken();
                // Every challenge is single use: a new one for the next submission.
                plugin.resetPow();
            });

            /**
             * Acceptance token: only after a real interaction of the visitor with the form (isTrusted: not triggered by a script).
             * Also for a checkbox which is checked by default ("default:on") and for keyboard, touch and voice control users.
             */
            plugin.$element.on('mousemove keydown touchstart focusin click', function (e) {
                if (!plugin.movedOrPressed && e.originalEvent && e.originalEvent.isTrusted) {
                    plugin.movedOrPressed = true;
                    plugin.maybeGetAcceptanceToken();
                    // Proof-of-work: solved while the visitor fills out the form (not on page load: battery, CPU).
                    plugin.requestPow();
                }
            });

            plugin.$acceptance.on('change', function (e) {
                plugin.maybeGetAcceptanceToken();
            });
        },
        maybeGetAcceptanceToken: function () {
            var plugin = this;
            if (plugin.movedOrPressed && plugin.$acceptanceToken.length && plugin.$acceptance.is(':checked') && plugin.$acceptanceToken.val() == '' && !plugin.loadingAcceptance) {
                plugin.getAcceptanceToken();
            }
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
                success: function (response, status) {
                    callback(response, status, plugin)
                },
                error: function( xhr, status, error ) {
                    // Allow a new request (e.g. the next click on the acceptance checkbox)
                    plugin.loadingAcceptance = false;
                    plugin.$element.find('.loading').removeClass('loading');
                    // Proof-of-work: the next submit asks again (e.g. after HTTP 429).
                    plugin.pow.loading = false;
                    plugin.pow.waiting = false;
                    plugin.$element.find('.eas-pow-waiting').removeClass('eas-pow-waiting');
                    console.log( 'Status: ' + xhr.status );
                    console.log( 'Error: ' + xhr.responseText );
                },
            });
        },
        requestPow: function () {
            var plugin = this;
            if (!plugin.pow.active || plugin.pow.ready || plugin.pow.loading) {
                return;
            }
            plugin.pow.loading = true;

            plugin.doAjax({
                'action': 'eap_reload_cf7_fields',
                'pow': plugin.pow.formId,
            }, plugin.processPow);
        },
        processPow: function (response, status, plugin) {
            var data = {};
            try {
                data = JSON.parse(response);
            } catch (error) {}

            if (!data.pow || !data.pow.c) {
                plugin.pow.loading = false;
                plugin.pow.waiting = false;
                plugin.$element.find('.eas-pow-waiting').removeClass('eas-pow-waiting');
                return;
            }

            plugin.$powChallenge.val(data.pow.c);

            if (plugin.pow.worker) {
                plugin.pow.worker.terminate();
            }

            var worker = new window.Worker(plugin.pow.workerUrl);
            plugin.pow.worker = worker;

            worker.onmessage = function (event) {
                worker.terminate();
                plugin.pow.worker = null;
                plugin.pow.loading = false;

                if (!event.data || !event.data.nonce) {
                    plugin.pow.waiting = false;
                    plugin.$element.find('.eas-pow-waiting').removeClass('eas-pow-waiting');
                    return;
                }

                plugin.$powSolution.val(event.data.nonce);
                plugin.pow.ready = true;

                if (plugin.pow.waiting) {
                    plugin.pow.waiting = false;
                    plugin.$element.find('.eas-pow-waiting').removeClass('eas-pow-waiting');
                    plugin.submitForm();
                }
            };

            worker.onerror = function () {
                plugin.pow.worker = null;
                plugin.pow.loading = false;
                plugin.pow.waiting = false;
                plugin.$element.find('.eas-pow-waiting').removeClass('eas-pow-waiting');
            };

            worker.postMessage({ salt: data.pow.s, bits: data.pow.b });
        },
        resetPow: function () {
            var plugin = this;
            if (!plugin.pow.active) {
                return;
            }
            if (plugin.pow.worker) {
                plugin.pow.worker.terminate();
                plugin.pow.worker = null;
            }
            plugin.pow.ready = false;
            plugin.pow.loading = false;
            plugin.$powChallenge.val('');
            plugin.$powSolution.val('');
            if (plugin.movedOrPressed) {
                plugin.requestPow();
            }
        },
        submitForm: function () {
            var plugin = this;
            // Fires the submit event (Contact Form 7 sends via AJAX), unlike form.submit().
            if (typeof plugin.element.requestSubmit === 'function') {
                plugin.element.requestSubmit();
            } else {
                plugin.$element.find('[type="submit"]').first().trigger('click');
            }
        },
        refreshAcceptanceToken: function () {
            var plugin = this;
            if (!plugin.$acceptanceToken.length) {
                return;
            }
            plugin.$acceptanceToken.val('');
            // Checkbox still checked (e.g. invalid submission): get a new token right away.
            plugin.maybeGetAcceptanceToken();
        },
        setAcceptanceToken: function (response, status, plugin) {
            plugin.loadingAcceptance = false;
            plugin.$acceptanceToken.val(response)
            plugin.$acceptance.parents('.wpcf7-acceptance').removeClass('loading');
        },
        getAcceptanceToken: function (getTimestamp, getExanspsel) {
            var plugin = this;
            plugin.loadingAcceptance = true;
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

        $('.wpcf7-form').exopiteAntiSpam();

    });

})( jQuery );
