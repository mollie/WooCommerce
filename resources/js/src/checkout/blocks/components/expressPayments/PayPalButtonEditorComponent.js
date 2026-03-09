export const PayPalButtonEditorComponent = ({ buttonAttributes = {} }) => {
    const style = {
        height: `${buttonAttributes.height || 48}px`,
        borderRadius: `${buttonAttributes.borderRadius || 4}px`,
        backgroundColor: '#ffc439',
        border: 'none',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        fontWeight: 'bold',
        color: '#003087',
        cursor: 'not-allowed',
    };

    return (
        <button
            id="mollie_paypal_button_editor"
            className="mollie-paypal-button-editor"
            style={style}
            disabled
        >
            <span>PayPal</span>
        </button>
    );
};

export default PayPalButtonEditorComponent;
