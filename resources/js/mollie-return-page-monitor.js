/**
 * Mollie Return Page Extension - JavaScript Sugar on Top
 * Extends the generic Return Page Monitor with Mollie-specific features
 *
 * @since 1.0.0
 */
(function($) {
    'use strict';

    // Wait for the generic framework to be loaded
    $(document).ready(() => {
        if (!window.WCReturnPageMonitor || !window.WCReturnPageMonitor.getHooks) {
            return; // Generic framework not available
        }

        const monitor = window.WCReturnPageMonitor;
        const hooks = monitor.getHooks();

        // Only enhance for Mollie payments
        if (!monitor.config.payment_method.startsWith('mollie_wc_gateway_')) {
            return;
        }

        /**
         * MOLLIE-SPECIFIC ENHANCEMENTS
         */

        // Add Mollie branding to the status indicator
        hooks.addFilter('monitor.indicator.html', (html, message, config) => {
            const mollieHtml = `
                <div class="mollie-return-page-monitor" style="display: none;">
                    <div class="mollie-return-header">
                        <img src="${config.mollie_logo_url || ''}" alt="Mollie" class="mollie-logo" />
                        <span class="mollie-brand-text">Secure Payment Processing</span>
                    </div>
                    <div class="mollie-return-content">
                        <div class="mollie-payment-spinner">
                            <div class="mollie-spinner-ring"></div>
                            <div class="mollie-spinner-dot"></div>
                        </div>
                        <div class="mollie-status-text">
                            <strong class="mollie-status-title">${getPaymentMethodName(config.payment_method)}</strong>
                            <p class="mollie-status-message">${escapeHtml(message)}</p>
                        </div>
                    </div>
                    <div class="mollie-return-footer">
                        <small class="mollie-security-note">
                            <span class="mollie-shield-icon">🛡️</span>
                            ${config.security_message || 'Your payment is processed securely by Mollie'}
                        </small>
                    </div>
                </div>
            `;
            return mollieHtml;
        });

        // Mollie-specific insertion logic (prefer after order summary)
        hooks.addFilter('monitor.indicator.insertion_point', (defaultPoint, config) => {
            // Try Mollie-friendly insertion points first
            const mollieSelectors = [
                '.woocommerce-order-details__payment-method',
                '.woocommerce-order-overview',
                '.woocommerce-order-details',
                '.entry-content'
            ];

            for (const selector of mollieSelectors) {
                const $element = $(selector);
                if ($element.length > 0) {
                    return { element: $element, method: 'after' };
                }
            }

            return defaultPoint; // Fallback to generic logic
        });

        // Smart retry logic based on payment method
        hooks.addFilter('monitor.polling.interval', (defaultInterval, attempt, config) => {
            const paymentMethod = config.payment_method.replace('mollie_wc_gateway_', '');

            // Some payment methods are inherently slower
            const slowMethods = ['banktransfer', 'belfius', 'kbc'];
            const fastMethods = ['ideal', 'creditcard', 'paypal'];

            if (slowMethods.includes(paymentMethod)) {
                return Math.min(defaultInterval * 1.5, 5000); // Slower polling for slow methods
            } else if (fastMethods.includes(paymentMethod) && attempt <= 3) {
                return Math.max(defaultInterval * 0.8, 1500); // Faster initial polling for fast methods
            }

            return defaultInterval;
        });

        // Enhanced status handling for Mollie-specific scenarios
        hooks.addFilter('monitor.status.should_continue', (shouldContinue, response, attempt) => {
            // Mollie-specific status handling
            if (response.mollie_payment_status) {
                switch (response.mollie_payment_status) {
                    case 'paid':
                    case 'authorized':
                        handleMollieSuccess(response);
                        return false; // Stop polling

                    case 'failed':
                    case 'canceled':
                    case 'expired':
                        handleMollieFailure(response);
                        return false; // Stop polling

                    case 'pending':
                    case 'open':
                        // Continue polling, but update message based on method
                        updateMollieStatusMessage(response);
                        return true;
                }
            }

            return shouldContinue; // Fallback to generic logic
        });

        // Enhanced error handling for Mollie API issues
        hooks.addAction('monitor.status.error', (xhr, textStatus, errorThrown, attempt) => {
            // Check if it's a Mollie API error
            if (xhr.responseJSON && xhr.responseJSON.mollie_error) {
                const mollieError = xhr.responseJSON.mollie_error;
                console.warn('Mollie API Error:', mollieError);

                // Show user-friendly message for known Mollie errors
                if (mollieError.type === 'request' && mollieError.field === 'payment') {
                    updateIndicatorMessage('Payment information is being verified. Please wait...');
                } else if (mollieError.type === 'api') {
                    updateIndicatorMessage('Connecting to payment service. Please wait...');
                }
            }
        });

        // Analytics tracking for Mollie payments
        hooks.addAction('monitor.resolved', (response, finalStatus) => {
            // Track resolution in analytics (if available)
            if (typeof gtag !== 'undefined') {
                gtag('event', 'payment_resolution', {
                    'payment_method': monitor.config.payment_method,
                    'final_status': finalStatus,
                    'attempts': monitor.getCurrentStatus().attempts,
                    'value': response.order_total || 0
                });
            }

            // Track in Mollie analytics (if configured)
            if (window.mollieAnalytics && window.mollieAnalytics.track) {
                window.mollieAnalytics.track('payment_return_page_resolution', {
                    paymentMethod: monitor.config.payment_method.replace('mollie_wc_gateway_', ''),
                    status: finalStatus,
                    attempts: monitor.getCurrentStatus().attempts,
                    timeToResolution: Date.now() - monitor.startTime
                });
            }
        });

        // Add payment method specific animations
        hooks.addAction('monitor.indicator.created', (indicator, config) => {
            const paymentMethod = config.payment_method.replace('mollie_wc_gateway_', '');
            indicator.addClass(`mollie-method-${paymentMethod}`);

            // Add payment method icon
            const methodIcon = getMolliePaymentIcon(paymentMethod);
            if (methodIcon) {
                indicator.find('.mollie-status-title').prepend(`<img src="${methodIcon}" class="mollie-method-icon" alt="${paymentMethod}" />`);
            }
        });

        // Custom behavior for specific Mollie payment methods
        if (monitor.config.payment_method === 'mollie_wc_gateway_banktransfer') {
            // Bank transfer takes longer, show appropriate messaging
            hooks.addFilter('monitor.status.should_continue', (shouldContinue, response, attempt) => {
                if (attempt === 5) {
                    updateIndicatorMessage(
                        'Bank transfers can take several minutes to process. We\'ll continue checking...',
                        'info'
                    );
                } else if (attempt === 10) {
                    updateIndicatorMessage(
                        'Bank transfer processing is taking longer than usual. This is normal for bank transfers.',
                        'info'
                    );
                }
                return shouldContinue;
            });
        } else if (monitor.config.payment_method === 'mollie_wc_gateway_ideal') {
            // iDEAL should be fast, be more aggressive
            hooks.addFilter('monitor.polling.interval', (interval, attempt) => {
                return attempt <= 5 ? 1500 : interval; // Poll faster initially for iDEAL
            });
        }

        /**
         * HELPER FUNCTIONS
         */

        function getPaymentMethodName(paymentMethodId) {
            const names = {
                'mollie_wc_gateway_ideal': 'iDEAL',
                'mollie_wc_gateway_creditcard': 'Credit Card',
                'mollie_wc_gateway_paypal': 'PayPal',
                'mollie_wc_gateway_banktransfer': 'Bank Transfer',
                'mollie_wc_gateway_sofort': 'SOFORT Banking',
                'mollie_wc_gateway_belfius': 'Belfius Pay Button',
                'mollie_wc_gateway_kbc': 'KBC Payment Button'
            };

            return names[paymentMethodId] || 'Payment';
        }

        function getMolliePaymentIcon(method) {
            // Return URLs for payment method icons
            const baseUrl = monitor.config.mollie_icons_url || '';
            const icons = {
                'ideal': `${baseUrl}/ideal.svg`,
                'creditcard': `${baseUrl}/creditcard.svg`,
                'paypal': `${baseUrl}/paypal.svg`,
                'banktransfer': `${baseUrl}/banktransfer.svg`,
                'sofort': `${baseUrl}/sofort.svg`
            };

            return icons[method] || null;
        }

        function handleMollieSuccess(response) {
            updateIndicatorMessage('✅ Payment confirmed by Mollie!', 'success');

            // Show success animation
            monitor.statusIndicator.addClass('mollie-success-animation');

            // Confetti effect for high-value orders (if library available)
            if (window.confetti && (response.order_total || 0) > 100) {
                window.confetti({
                    particleCount: 50,
                    spread: 45,
                    origin: { y: 0.6 }
                });
            }
        }

        function handleMollieFailure(response) {
            updateIndicatorMessage('❌ Payment was not completed', 'error');

            // Add retry suggestion for failed payments
            setTimeout(() => {
                const retryButton = $('<button class="mollie-retry-button">Try Another Payment Method</button>');
                retryButton.on('click', () => {
                    window.location.href = monitor.config.checkout_url || '/checkout/';
                });
                monitor.statusIndicator.find('.mollie-return-footer').append(retryButton);
            }, 2000);
        }

        function updateMollieStatusMessage(response) {
            const paymentMethod = monitor.config.payment_method.replace('mollie_wc_gateway_', '');

            const methodMessages = {
                'ideal': 'Waiting for confirmation from your bank...',
                'creditcard': 'Processing your credit card payment...',
                'paypal': 'Confirming your PayPal payment...',
                'banktransfer': 'Waiting for your bank transfer...',
                'sofort': 'Processing your SOFORT payment...'
            };

            const message = methodMessages[paymentMethod] || 'Processing your payment...';
            updateIndicatorMessage(message, 'pending');
        }

        function updateIndicatorMessage(message, type = null) {
            const indicator = monitor.statusIndicator;
            if (!indicator) return;

            indicator.find('.mollie-status-message').text(message);

            if (type) {
                indicator
                    .removeClass('mollie-status-pending mollie-status-success mollie-status-error mollie-status-info')
                    .addClass(`mollie-status-${type}`);
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * MOLLIE-SPECIFIC MONITORING
         */

            // Monitor for webhook race conditions in real-time
        let webhookRaceDetector = {
                attempts: 0,

                detectRaceCondition: function() {
                    // If we're polling a lot, there might be a webhook race condition
                    if (monitor.getCurrentStatus().attempts > 8) {
                        console.warn('Potential Mollie webhook race condition detected');

                        // Send analytics event
                        if (typeof gtag !== 'undefined') {
                            gtag('event', 'webhook_race_suspected', {
                                'payment_method': monitor.config.payment_method,
                                'attempts': monitor.getCurrentStatus().attempts
                            });
                        }
                    }
                }
            };

        // Check for race conditions periodically
        hooks.addAction('monitor.status.checking', (attempt) => {
            webhookRaceDetector.detectRaceCondition();
        });

        // Store reference for debugging
        window.MollieReturnPageEnhancements = {
            monitor: monitor,
            webhookRaceDetector: webhookRaceDetector,
            paymentMethod: monitor.config.payment_method.replace('mollie_wc_gateway_', '')
        };

        console.log('🟢 Mollie Return Page enhancements loaded for:', monitor.config.payment_method);
    });

})(jQuery);
