function usingGateway(gateway)
{
    return jQuery('form[name="checkout"] input[name="payment_method"]:checked').val() === gateway;
}
function showField(billingField, positionField, fieldMarkup)
{
    if ((billingField).length <= 0) {
        jQuery(positionField)
            .closest('p')
            .after(fieldMarkup);
    }
}
function requireField(inputName, fieldId)
{
    jQuery('form[name="checkout"] input[name=' + inputName + ']').attr('required', '');
    jQuery('form[name="checkout"] p#' + fieldId).addClass('validate-required');
    jQuery('form[name="checkout"] p#' + fieldId + ' label span').replaceWith('<abbr class="required" title="required">*</abbr>');
}
function removeField(fieldId)
{
    jQuery('form[name="checkout"] p#' + fieldId).remove();
}
function unrequireField(inputName, fieldId)
{
    jQuery('form[name="checkout"] input[name=' + inputName + ']').removeAttr('required');
    jQuery('form[name="checkout"] p#' + fieldId).removeClass('validate-required');
    jQuery('form[name="checkout"] p#' + fieldId + ' label abbr').replaceWith('<span class="optional">(optional)</span>');
}
function restoreOriginalField(billingField, positionField, fieldMarkup, inputName, fieldId, originalField)
{
    let field = jQuery('form[name="checkout"] input[name=' + inputName + ']');
    let currentVisibility = field.is(':visible');
    let currentRequired = field.prop('required');
    if (currentVisibility !== originalField.isVisible) {
        if (originalField.isVisible) {
            showField(billingField, positionField, fieldMarkup);
        } else {
            removeField(fieldId);
        }
    }
    if (currentRequired !== originalField.isRequired) {
        if (originalField.isRequired) {
            requireField(inputName, fieldId);
        } else {
            unrequireField(inputName, fieldId);
        }
    }
}
export function saveOriginalField(inputName, originalField)
{
    let field = jQuery('form[name="checkout"] input[name=' + inputName + ']').closest('p');
    let isVisible = field.is(':visible');
    let isRequired = field.prop('required') || field.hasClass('validate-required');
    originalField = { isVisible, isRequired };
    return originalField;
}
export function maybeRequireField(billingField, positionField, fieldMarkup, inputName, fieldId, originalField, gateway)
{
    if (usingGateway(gateway)) {
        showField(billingField, positionField, fieldMarkup);
        requireField(inputName, fieldId);
        return jQuery('form[name="checkout"] p#' + fieldId);
    } else {
        restoreOriginalField(billingField, positionField, fieldMarkup, inputName, fieldId, originalField);
        return false;
    }
}
