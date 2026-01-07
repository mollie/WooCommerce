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

        /**
         * Initialize webhook test functionality
         */
        init: function () {
            this.cacheElements();
            if (this.button) {
                this.bindEvents();
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

            const nonce = this.button.dataset.nonce;

            // Show waiting message
            this.showResult(
                'info',
                mollieWebhookTestData.messages.waiting
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
            this.pollWebhookResult(testId);
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
            const maxAttempts = 20; // 20 attempts = ~10 seconds
            const pollInterval = 500; // 500ms between attempts

            if (attempt >= maxAttempts) {
                this.showResult('warning', mollieWebhookTestData.messages.timeout);
                this.setButtonState(false);
                this.showSpinner(false);
                return;
            }

            // Update message based on attempt count
            if (attempt === 10) {
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
            this.setButtonState(false);
            this.showSpinner(false);
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
