/**
 * Devsync_FeaturedProduct
 *
 * Knockout uiComponent that keeps the salable quantity up to date in real time.
 *
 * It renders the initial quantity (server-side value passed through jsLayout)
 * and then polls the module endpoint every `refreshInterval` seconds, updating
 * a single Knockout observable so only the number re-renders — never the page.
 */
define([
    'uiComponent',
    'ko',
    'jquery'
], function (Component, ko, $) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Devsync_FeaturedProduct/stock',
            stockUrl: '',
            refreshInterval: 10,
            initialQty: 0
        },

        /**
         * @returns {Object} chainable
         */
        initialize: function () {
            this._super();

            this.qty = ko.observable(this._normalizeQty(this.initialQty));
            this.isUpdating = ko.observable(false);

            this._startPolling();

            return this;
        },

        /**
         * Coerce any incoming value to a non-negative number of units, keeping
         * decimals for qty-decimal products (sold by weight/length) but dropping
         * meaningless trailing zeros (5.0000 -> 5).
         *
         * @param {*} value
         * @returns {Number}
         * @private
         */
        _normalizeQty: function (value) {
            var qty = Number(value);

            if (!isFinite(qty) || qty < 0) {
                return 0;
            }

            return Number.isInteger(qty) ? qty : parseFloat(qty.toFixed(4));
        },

        /**
         * Schedule the recurring stock refresh (clamped to a sane minimum).
         *
         * @private
         */
        _startPolling: function () {
            var self = this,
                seconds = parseInt(this.refreshInterval, 10),
                intervalMs;

            if (!this.stockUrl) {
                return;
            }

            intervalMs = (isNaN(seconds) || seconds < 2 ? 10 : seconds) * 1000;

            this.pollTimer = setInterval(function () {
                self._fetchStock();
            }, intervalMs);
        },

        /**
         * Fetch the current salable quantity and update the observable.
         *
         * @private
         */
        _fetchStock: function () {
            var self = this;

            if (this.isUpdating()) {
                return;
            }

            this.isUpdating(true);

            $.ajax({
                url: this.stockUrl,
                type: 'GET',
                dataType: 'json',
                cache: false,
                showLoader: false
            }).done(function (response) {
                if (response && response.success) {
                    self.qty(self._normalizeQty(response.qty));
                }
            }).always(function () {
                self.isUpdating(false);
            });
        }
    });
});
