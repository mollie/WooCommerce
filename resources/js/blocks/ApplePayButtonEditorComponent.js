export const ApplePayButtonEditorComponent = ({ buttonAttributes = {} }) => {
    const { useMemo } = wp.element;
    const style = useMemo(() => ({
        height: `${buttonAttributes.height || 48}px`,
        borderRadius: `${buttonAttributes.borderRadius || 4}px`
    }), [buttonAttributes.height, buttonAttributes.borderRadius]);

    return (
        <button
            id="mollie_applepay_button"
            className="apple-pay-button apple-pay-button-black"
            style={style}
        >
        </button>
    );
};
export default ApplePayButtonEditorComponent;
