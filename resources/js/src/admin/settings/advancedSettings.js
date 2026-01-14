(function ({ _ }) {

    /* ===================================================================
       Payment Description Label Insertion
       =================================================================== */

    function mollie_settings__insertTextAtCursor(
        target,
        text,
        dontIgnoreSelection
    ) {
        if (target.setRangeText) {
            if (!dontIgnoreSelection) {
                // insert at end
                target.setRangeText(
                    text,
                    target.value.length,
                    target.value.length,
                    'end'
                );
            } else {
                // replace selection
                target.setRangeText(
                    text,
                    target.selectionStart,
                    target.selectionEnd,
                    'end'
                );
            }
        } else {
            target.focus();
            document.execCommand('insertText', false /*no UI*/, text);
        }
        target.focus();
    }

    /* ===================================================================
       Manual Capture Fields
       =================================================================== */

    function registerManualCaptureFields() {
        const onHoldSelect = document.querySelector(
            '[name="mollie-payments-for-woocommerce_place_payment_onhold"]'
        );
        if (!onHoldSelect) {
            return;
        }
        toggleManualCaptureFields(onHoldSelect);
        onHoldSelect.addEventListener('change', function () {
            toggleManualCaptureFields(onHoldSelect);
        });
    }

    function toggleManualCaptureFields(onHoldSelect) {
        const currentValue = onHoldSelect.querySelector('option:checked');
        if (!currentValue) {
            return;
        }

        const captureStatusChangeField = document.querySelector(
            '[name="mollie-payments-for-woocommerce_capture_or_void"]'
        );
        if (!captureStatusChangeField) {
            return;
        }

        const captureStatusChangeFieldParent = captureStatusChangeField.closest('tr');
        if (!captureStatusChangeFieldParent) {
            return;
        }

        if (currentValue.value === 'later_capture') {
            captureStatusChangeFieldParent.style.display = '';
        } else {
            captureStatusChangeFieldParent.style.display = 'none';
        }
    }

    /* ===================================================================
       Webhook Test Handler
       =================================================================== */

    const WebhookTest = {
        button: null,
        spinner: null,
        result: null,
        checkoutPanel: null,
        currentTestId: null,

        /**
         * Initialize webhook test functionality
         */
        init: function () {
            this.cacheElements();
            if (this.button) {
                this.bindEvents();
                this.createCheckoutPanel();
            }
        },

        /**
         * Cache DOM elements
         */
        cacheElements: function () {
            this.button = document.getElementById('mollie-webhook-test-button');
            if (this.button) {
                this.spinner = this.button.parentElement.querySelector('.spinner');
                this.result = document.getElementById('mollie-webhook-test-result');
            }
        },

        /**
         * Create checkout panel for showing checkout URL
         */
        createCheckoutPanel: function () {
            if (!this.result) {
                return;
            }

            // Create container for checkout instructions
            this.checkoutPanel = document.createElement('div');
            this.checkoutPanel.id = 'mollie-webhook-checkout-panel';
            this.checkoutPanel.className = 'mollie-webhook-checkout-panel';
            this.checkoutPanel.style.display = 'none';
            this.result.parentElement.insertBefore(this.checkoutPanel, this.result.nextSibling);
        },

        /**
         * Bind event handlers
         */
        bindEvents: function () {
            this.button.addEventListener('click', this.handleButtonClick.bind(this));
        },

        /**
         * Handle button click event
         */
        handleButtonClick: function (e) {
            e.preventDefault();

            if (this.button.disabled) {
                return;
            }

            this.startTest();
        },

        /**
         * Start webhook test
         */
        startTest: function () {
            this.setButtonState(true);
            this.showSpinner(true);
            this.clearResult();
            this.hideCheckoutPanel();

            const nonce = this.button.dataset.nonce;

            // Show initial message
            this.showResult(
                'info',
                mollieWebhookTestData.messages.creating || 'Creating test payment...'
            );

            // Initiate webhook test
            this.initiateWebhookTest(nonce);
        },

        /**
         * Initiate webhook test via fetch API
         *
         * @param {string} nonce Security nonce
         */
        initiateWebhookTest: function (nonce) {
            const formData = new FormData();
            formData.append('action', 'mollie_webhook_test_initiate');
            formData.append('nonce', nonce);

            fetch(mollieWebhookTestData.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
                .then(response => response.json())
                .then(this.handleInitiateSuccess.bind(this))
                .catch(this.handleInitiateError.bind(this));
        },

        /**
         * Handle successful test initiation
         *
         * @param {Object} response Response object
         */
        handleInitiateSuccess: function (response) {
            if (!response.success) {
                this.showResult(
                    'error',
                    response.data?.message || mollieWebhookTestData.messages.error
                );
                this.setButtonState(false);
                this.showSpinner(false);
                return;
            }

            const testId = response.data.test_id;
            const checkoutUrl = response.data.checkout_url;
            this.currentTestId = testId;

            if (checkoutUrl) {
                // Show checkout URL to user with instructions
                this.showCheckoutInstructions(checkoutUrl, testId);
            } else {
                // Fallback to old behavior if no checkout URL
                this.showResult('info', mollieWebhookTestData.messages.waiting);
                this.pollWebhookResult(testId);
            }
        },

        /**
         * Show checkout instructions to user
         *
         * @param {string} checkoutUrl Mollie checkout URL
         * @param {string} testId Test identifier
         */
        showCheckoutInstructions: function (checkoutUrl, testId) {
            this.showSpinner(false);
            this.setButtonState(true);

            // Update result area with instructions
            this.showResult(
                'info',
                mollieWebhookTestData.messages.checkoutRequired ||
                'Please complete the test payment to trigger the webhook.'
            );

            // Build checkout panel content
            const panelHtml = `
                <div class="mollie-checkout-instructions">
                    <p><strong>${mollieWebhookTestData.messages.step1 || 'Step 1:'}</strong> ${mollieWebhookTestData.messages.clickCheckout || 'Click the button below to open the Mollie test payment page in a new tab.'}</p>
                    <p>
                        <a href="${checkoutUrl}" target="_blank" class="button button-primary" id="mollie-open-checkout">
                            ${mollieWebhookTestData.messages.openCheckout || 'Open Test Payment Page'}
                        </a>
                    </p>
                    <p><strong>${mollieWebhookTestData.messages.step2 || 'Step 2:'}</strong> ${mollieWebhookTestData.messages.selectStatus || 'Select a payment status (e.g., "Paid" or "Failed") on the Mollie page.'}</p>
                    <p><strong>${mollieWebhookTestData.messages.step3 || 'Step 3:'}</strong> ${mollieWebhookTestData.messages.clickVerify || 'Come back here and click the button below to verify the webhook was received.'}</p>
                    <p>
                        <button type="button" class="button button-secondary" id="mollie-verify-webhook">
                            ${mollieWebhookTestData.messages.verifyWebhook || 'Verify Webhook'}
                        </button>
                        <button type="button" class="button" id="mollie-cancel-test">
                            ${mollieWebhookTestData.messages.cancelTest || 'Cancel Test'}
                        </button>
                    </p>
                </div>
            `;

            this.checkoutPanel.innerHTML = panelHtml;
            this.checkoutPanel.style.display = 'block';

            // Bind events for new buttons
            const verifyBtn = document.getElementById('mollie-verify-webhook');
            const cancelBtn = document.getElementById('mollie-cancel-test');

            if (verifyBtn) {
                verifyBtn.addEventListener('click', () => {
                    this.showSpinner(true);
                    this.showResult('info', mollieWebhookTestData.messages.waiting);
                    this.pollWebhookResult(testId);
                });
            }

            if (cancelBtn) {
                cancelBtn.addEventListener('click', () => {
                    this.cancelTest();
                });
            }
        },

        /**
         * Hide checkout panel
         */
        hideCheckoutPanel: function () {
            if (this.checkoutPanel) {
                this.checkoutPanel.style.display = 'none';
                this.checkoutPanel.innerHTML = '';
            }
        },

        /**
         * Cancel the current test
         */
        cancelTest: function () {
            this.currentTestId = null;
            this.hideCheckoutPanel();
            this.clearResult();
            this.setButtonState(false);
            this.showSpinner(false);
        },

        /**
         * Handle test initiation error
         *
         * @param {Error} error Error object
         */
        handleInitiateError: function (error) {
            const message = error.message || mollieWebhookTestData.messages.error;
            this.showResult('error', message);
            this.setButtonState(false);
            this.showSpinner(false);
        },

        /**
         * Poll for webhook test result
         *
         * @param {string} testId Test identifier
         * @param {number} attempt Current attempt number
         */
        pollWebhookResult: function (testId, attempt = 0) {
            const maxAttempts = 40; // 40 attempts = ~20 seconds
            const pollInterval = 500; // 500ms between attempts

            if (attempt >= maxAttempts) {
                this.showResult('warning', mollieWebhookTestData.messages.timeout);
                this.hideCheckoutPanel();
                this.setButtonState(false);
                this.showSpinner(false);
                return;
            }

            // Update message based on attempt count
            if (attempt === 20) {
                this.showResult('info', mollieWebhookTestData.messages.takingLong);
            }

            setTimeout(() => {
                const formData = new FormData();
                formData.append('action', 'mollie_webhook_test_check');
                formData.append('test_id', testId);
                formData.append('nonce', this.button.dataset.nonce);

                fetch(mollieWebhookTestData.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                    .then(response => response.json())
                    .then(response => {
                        if (response.success && response.data.completed) {
                            this.handleTestComplete(response.data);
                        } else {
                            // Continue polling
                            this.pollWebhookResult(testId, attempt + 1);
                        }
                    })
                    .catch(() => {
                        // On error, continue polling unless max attempts reached
                        this.pollWebhookResult(testId, attempt + 1);
                    });
            }, pollInterval);
        },

        /**
         * Handle completed webhook test
         *
         * @param {Object} data Test result data
         */
        handleTestComplete: function (data) {
            const resultType = data.webhook_received ? 'success' : 'error';
            const message = data.message || (
                data.webhook_received
                    ? mollieWebhookTestData.messages.success
                    : mollieWebhookTestData.messages.noWebhook
            );

            this.showResult(resultType, message);
            this.hideCheckoutPanel();
            this.setButtonState(false);
            this.showSpinner(false);
            this.currentTestId = null;
        },

        /**
         * Set button disabled state
         *
         * @param {boolean} disabled Whether button should be disabled
         */
        setButtonState: function (disabled) {
            this.button.disabled = disabled;
        },

        /**
         * Show/hide spinner
         *
         * @param {boolean} show Whether to show spinner
         */
        showSpinner: function (show) {
            if (this.spinner) {
                this.spinner.style.visibility = show ? 'visible' : 'hidden';
            }
        },

        /**
         * Show result message
         *
         * @param {string} type Result type (success, error, warning, info)
         * @param {string} message Message to display
         */
        showResult: function (type, message) {
            if (!this.result) {
                return;
            }

            this.result.className = 'mollie-webhook-test-result ' + type;
            this.result.innerHTML = message;
            this.result.style.display = 'block';
        },

        /**
         * Clear result message
         */
        clearResult: function () {
            if (!this.result) {
                return;
            }

            this.result.style.display = 'none';
            this.result.innerHTML = '';
            this.result.className = 'mollie-webhook-test-result';
        }
    };

    /* ===================================================================
       Payment Description Labels Handler
       =================================================================== */

    function initPaymentDescriptionLabels() {
        const labels = document.querySelectorAll('.mollie-settings-advanced-payment-desc-label');

        labels.forEach(function (label) {
            let ignoreClick = false;

            label.addEventListener('mousedown', function (e) {
                const input = document.getElementById(
                    'mollie-payments-for-woocommerce_api_payment_description'
                );

                if (document.activeElement && input === document.activeElement) {
                    const handleMouseUp = function (e) {
                        ignoreClick = true;
                        cleanup();

                        const tag = label.dataset.tag;
                        mollie_settings__insertTextAtCursor(input, tag, true);
                    };

                    const cleanup = function () {
                        label.removeEventListener('mouseup', handleMouseUp);
                        window.removeEventListener('mouseup', cleanupWindow);
                        window.removeEventListener('drag', cleanupWindow);
                        window.removeEventListener('blur', cleanupWindow);
                    };

                    const cleanupWindow = function () {
                        cleanup();
                    };

                    label.addEventListener('mouseup', handleMouseUp);
                    window.addEventListener('mouseup', cleanupWindow);
                    window.addEventListener('drag', cleanupWindow);
                    window.addEventListener('blur', cleanupWindow);
                }
            });

            label.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopImmediatePropagation();

                if (!ignoreClick) {
                    const tag = label.dataset.tag;
                    const input = document.getElementById(
                        'mollie-payments-for-woocommerce_api_payment_description'
                    );
                    mollie_settings__insertTextAtCursor(input, tag, false);
                } else {
                    ignoreClick = false;
                }
            });
        });
    }

    /* ===================================================================
       Document Ready
       =================================================================== */

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initPaymentDescriptionLabels();
            registerManualCaptureFields();
            WebhookTest.init();
        });
    } else {
        initPaymentDescriptionLabels();
        registerManualCaptureFields();
        WebhookTest.init();
    }

})(window);
