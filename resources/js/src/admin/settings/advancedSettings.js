(
    function ({_, jQuery}) {

        function mollie_settings__insertTextAtCursor(target, text, dontIgnoreSelection) {
            if (target.setRangeText) {
                if (!dontIgnoreSelection) {
                    // insert at end
                    target.setRangeText(text, target.value.length, target.value.length, "end");
                } else {
                    // replace selection
                    target.setRangeText(text, target.selectionStart, target.selectionEnd, "end");
                }
            } else {
                target.focus();
                document.execCommand("insertText", false /*no UI*/, text);
            }
            target.focus();
        }

        jQuery(document).ready(function ($) {
            $(".mollie-settings-advanced-payment-desc-label")
                .data("ignore-click", "false")
                .on("mousedown", function (e) {
                    const input = document.getElementById("mollie-payments-for-woocommerce_api_payment_description");
                    if (document.activeElement && input === document.activeElement) {
                        $(this).on("mouseup.molliesettings", function (e) {
                            $(this).data("ignore-click", "true");
                            $(this).off(".molliesettings");
                            const tag = $(this).data("tag");
                            const input = document.getElementById("mollie-payments-for-woocommerce_api_payment_description");
                            mollie_settings__insertTextAtCursor(input, tag, true);
                        });
                    }
                    let $this = $(this);
                    $(window).on("mouseup.molliesettings drag.molliesettings blur.molliesettings", function (e) {
                        $this.off(".molliesettings");
                        $(window).off(".molliesettings");
                    });
                })
                .on("click", function (e) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    if ($(this).data("ignore-click") === "false") {
                        const tag = $(this).data("tag");
                        const input = document.getElementById("mollie-payments-for-woocommerce_api_payment_description");
                        mollie_settings__insertTextAtCursor(input, tag, false);
                    } else {
                        $(this).data("ignore-click", "false");
                    }
                })
            ;
            registerManualCaptureFields();
        });
    }
)
(
    window
)

function registerManualCaptureFields() {
    const onHoldSelect = jQuery('[name="mollie-payments-for-woocommerce_place_payment_onhold"]');
    if (onHoldSelect.length === 0) {
        return;
    }
    toggleManualCaptureFields(onHoldSelect);
    onHoldSelect.on('change', function(){
        toggleManualCaptureFields(onHoldSelect);
    })
}

function toggleManualCaptureFields(onHoldSelect) {
    const currentValue = onHoldSelect.find('option:selected');
    if (currentValue.length === 0) {
        return;
    }

    const captureStatusChangeField = jQuery('[name="mollie-payments-for-woocommerce_capture_or_void"]');
    if (captureStatusChangeField.length === 0) {
        return;
    }

    const captureStatusChangeFieldParent = captureStatusChangeField.closest('tr');
    if (captureStatusChangeFieldParent.length === 0) {
        return;
    }

    if (currentValue.val() === 'later_capture') {
        captureStatusChangeFieldParent.show();
    } else {
        captureStatusChangeFieldParent.hide();
    }
}
