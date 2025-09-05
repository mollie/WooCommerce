/**
 * Generic Return Page Monitor - The JavaScript Swiss Army Knife
 * Works with any payment method that implements the framework interfaces
 *
 * @since 1.0.0
 */
(function($) {
    'use strict';

    class ReturnPageMonitor {
        constructor(config = {}) {
            this.config = Object.assign({
                order_id: null,
                order_key: null,
                payment_method: null,
                rest_url: '',
                nonce: '',
                retry_count: 10,
                interval: 2500,
                messages: {}
            }, window.WCReturnPageConfig || {}, config);

            this.attempts = 0;
            this.isPolling = false;
            this.pollTimeout = null;
            this.statusIndicator = null;
            this.hooks = this.createHookSystem();

            this.init();
        }

        /**
         * Create a simple hook system for extensibility
         */
        createHookSystem() {
            const hooks = {
                filters: {},
                actions: {}
            };

            return {
                addFilter: (name, callback, priority = 10) => {
                    if (!hooks.filters[name]) hooks.filters[name] = [];
                    hooks.filters[name].push({ callback, priority });
                    hooks.filters[name].sort((a, b) => a.priority - b.priority);
                },

                applyFilters: (name, value, ...args) => {
                    if (!hooks.filters[name]) return value;
                    return hooks.filters[name].reduce((acc, filter) => {
                        return filter.callback(acc, ...args);
                    }, value);
                },

                addAction: (name, callback, priority = 10) => {
                    if (!hooks.actions[name]) hooks.actions[name] = [];
                    hooks.actions[name].push({ callback, priority });
                    hooks.actions[name].sort((a, b) => a.priority - b.priority);
                },

                doAction: (name, ...args) => {
                    if (!hooks.actions[name]) return;
                    hooks.actions[name].forEach(action => {
                        action.callback(...args);
                    });
                }
            };
        }

        init() {
            if (!this.isValidConfig()) {
                this.hooks.doAction('monitor.init.failed', this.config);
                return;
            }

            this.hooks.doAction('monitor.init.start', this.config);

            // Allow extensions to modify initialization
            if (this.hooks.applyFilters('monitor.should_start', true, this.config)) {
                this.createStatusIndicator();
                this.startPolling();
            }

            this.hooks.doAction('monitor.init.complete', this.config);
        }

        isValidConfig() {
            const required = ['order_id', 'order_key', 'payment_method', 'rest_url', 'nonce'];
            return required.every(key => this.config[key]);
        }

        createStatusIndicator() {
            const message = this.getMessage('loading');
            const indicatorHtml = this.hooks.applyFilters('monitor.indicator.html',
                this.getDefaultIndicatorHtml(message), message, this.config);

            this.statusIndicator = $(indicatorHtml);

            // Find the best insertion point
            const insertionPoint = this.hooks.applyFilters('monitor.indicator.insertion_point',
                this.findInsertionPoint(), this.config);

            this.insertIndicator(insertionPoint);
            this.hooks.doAction('monitor.indicator.created', this.statusIndicator, this.config);
        }

        getDefaultIndicatorHtml(message) {
            return `
                <div class="wc-return-page-monitor" style="display: none;">
                    <div class="return-page-content">
                        <div class="return-page-spinner"></div>
                        <span class="return-page-text">${this.escapeHtml(message)}</span>
                    </div>
                </div>
            `;
        }

        findInsertionPoint() {
            const selectors = [
                '.woocommerce-order-details',
                '.entry-content',
                '.woocommerce-thankyou-order-received',
                'main',
                'body'
            ];

            for (const selector of selectors) {
                const $element = $(selector);
                if ($element.length > 0) {
                    return { element: $element, method: 'before' };
                }
            }

            return { element: $('body'), method: 'prepend' };
        }

        insertIndicator(insertionPoint) {
            if (insertionPoint.method === 'before') {
                insertionPoint.element.before(this.statusIndicator);
            } else {
                insertionPoint.element.prepend(this.statusIndicator);
            }

            this.showIndicator();
        }

        showIndicator() {
            if (this.statusIndicator) {
                this.statusIndicator.slideDown(300);
                this.hooks.doAction('monitor.indicator.shown', this.statusIndicator);
            }
        }

        hideIndicator() {
            if (this.statusIndicator) {
                this.statusIndicator.slideUp(300, () => {
                    this.statusIndicator.remove();
                    this.hooks.doAction('monitor.indicator.hidden');
                });
            }
        }

        updateIndicator(message, statusClass = null) {
            if (!this.statusIndicator) return;

            this.statusIndicator.find('.return-page-text').text(message);

            if (statusClass) {
                this.statusIndicator
                    .removeClass('status-loading status-success status-error status-timeout')
                    .addClass(`status-${statusClass}`);
            }

            this.hooks.doAction('monitor.indicator.updated', message, statusClass);
        }

        startPolling() {
            if (this.isPolling) return;

            this.isPolling = true;
            this.hooks.doAction('monitor.polling.started', this.config);
            this.checkStatus();
        }

        stopPolling() {
            this.isPolling = false;
            if (this.pollTimeout) {
                clearTimeout(this.pollTimeout);
                this.pollTimeout = null;
            }
            this.hooks.doAction('monitor.polling.stopped');
        }

        checkStatus() {
            if (!this.isPolling) return;

            this.attempts++;
            this.hooks.doAction('monitor.status.checking', this.attempts);

            const url = this.buildStatusUrl();
            const requestConfig = this.hooks.applyFilters('monitor.request.config', {
                url: url,
                method: 'GET',
                headers: { 'X-WP-Nonce': this.config.nonce },
                timeout: 10000
            }, this.attempts);

            $.ajax(requestConfig)
                .done((response) => this.handleStatusResponse(response))
                .fail((xhr, textStatus, errorThrown) => this.handleStatusError(xhr, textStatus, errorThrown));
        }

        buildStatusUrl() {
            return `${this.config.rest_url}status/${this.config.order_id}?` +
                `key=${encodeURIComponent(this.config.order_key)}&` +
                `payment_method=${encodeURIComponent(this.config.payment_method)}`;
        }

        handleStatusResponse(response) {
            this.hooks.doAction('monitor.status.response', response, this.attempts);

            // Allow extensions to override status handling
            const shouldContinue = this.hooks.applyFilters('monitor.status.should_continue',
                this.defaultStatusHandler(response), response, this.attempts);

            if (!shouldContinue) {
                return;
            }

            // Continue polling or trigger fallback
            if (this.attempts >= this.config.retry_count) {
                this.triggerFallback();
            } else {
                this.scheduleNextCheck();
            }
        }

        defaultStatusHandler(response) {
            // Payment resolved - stop polling
            if (!response.needs_payment || response.status === 'success') {
                this.handleResolution(response, 'success');
                return false;
            }

            // Payment failed - stop polling
            if (response.status === 'failed' || response.status === 'cancelled') {
                this.handleResolution(response, 'failed');
                return false;
            }

            // Still pending - continue polling
            return true;
        }

        handleStatusError(xhr, textStatus, errorThrown) {
            this.hooks.doAction('monitor.status.error', xhr, textStatus, errorThrown, this.attempts);

            console.warn('Return Page Monitor: Status check failed', { textStatus, errorThrown, attempt: this.attempts });

            if (this.attempts >= this.config.retry_count) {
                this.handleResolution({ status: 'error' }, 'error');
            } else {
                this.scheduleNextCheck();
            }
        }

        scheduleNextCheck() {
            const interval = this.hooks.applyFilters('monitor.polling.interval',
                this.config.interval, this.attempts, this.config);

            this.pollTimeout = setTimeout(() => {
                this.checkStatus();
            }, interval);
        }

        triggerFallback() {
            this.hooks.doAction('monitor.fallback.triggered', this.config);

            this.updateIndicator(this.getMessage('timeout'), 'timeout');

            const url = `${this.config.rest_url}trigger/${this.config.order_id}`;
            const requestData = this.hooks.applyFilters('monitor.fallback.request_data', {
                key: this.config.order_key,
                payment_method: this.config.payment_method
            }, this.config);

            $.ajax({
                url: url,
                method: 'POST',
                headers: { 'X-WP-Nonce': this.config.nonce },
                data: requestData,
                timeout: 15000
            })
                .done((response) => this.handleFallbackResponse(response))
                .fail((xhr, textStatus, errorThrown) => this.handleFallbackError(xhr, textStatus, errorThrown));
        }

        handleFallbackResponse(response) {
            this.hooks.doAction('monitor.fallback.response', response);

            if (!response.needs_payment || response.new_status === 'success') {
                this.handleResolution(response, 'success');
            } else {
                this.handleResolution(response, 'timeout');
            }
        }

        handleFallbackError(xhr, textStatus, errorThrown) {
            this.hooks.doAction('monitor.fallback.error', xhr, textStatus, errorThrown);
            this.handleResolution({ status: 'error' }, 'error');
        }

        handleResolution(response, finalStatus) {
            this.stopPolling();
            this.hooks.doAction('monitor.resolved', response, finalStatus);

            const message = this.getMessage(finalStatus);
            this.updateIndicator(message, finalStatus);

            // Auto-hide or reload based on status
            const hideDelay = this.hooks.applyFilters('monitor.resolution.hide_delay',
                finalStatus === 'success' ? 3000 : 8000, finalStatus, response);

            if (hideDelay > 0) {
                setTimeout(() => {
                    if (finalStatus === 'success') {
                        // Allow extensions to override reload behavior
                        if (this.hooks.applyFilters('monitor.resolution.should_reload', true, response)) {
                            window.location.reload();
                        }
                    }
                    this.hideIndicator();
                }, hideDelay);
            }
        }

        getMessage(type) {
            return this.config.messages[type] || `Status: ${type}`;
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Public API for extensions
        getHooks() {
            return this.hooks;
        }

        getCurrentStatus() {
            return {
                attempts: this.attempts,
                isPolling: this.isPolling,
                config: this.config
            };
        }
    }

    // Global factory function
    window.createReturnPageMonitor = function(customConfig) {
        return new ReturnPageMonitor(customConfig);
    };

    // Auto-initialize if config is available
    $(document).ready(() => {
        if (window.WCReturnPageConfig && window.WCReturnPageConfig.order_id) {
            const monitor = new ReturnPageMonitor();

            // Make monitor globally accessible for debugging/extensions
            window.WCReturnPageMonitor = monitor;
        }
    });

})(jQuery);
