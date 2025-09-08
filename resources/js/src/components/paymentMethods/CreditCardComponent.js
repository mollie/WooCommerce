import { CreditCardField } from '../paymentFields/CreditCardField';

const CreditCardComponent = ({
                                 item,
                                 useEffect,
                                 activePaymentMethod,
                                 containerRef,
                                 tokenManager,
                                 isComponentReady,
                                 componentError,
                                 // Store props from withMollieStore HOC
                                 componentInitialized,
                                 tokenCreated,
                                 canCreateToken
                             }) => {

    // Handle component initialization status
    useEffect(() => {
        if (activePaymentMethod === 'mollie_wc_gateway_creditcard') {
            // Dispatch event to indicate credit card method is selected
            const creditCardSelected = new Event("mollie_creditcard_component_selected", {
                bubbles: true
            });
            document.documentElement.dispatchEvent(creditCardSelected);
        }
    }, [activePaymentMethod]);

    // Display component status
    const getComponentStatus = () => {
        if (componentError) {
            return <div className="mollie-error">Error: {componentError}</div>;
        }

        if (!componentInitialized) {
            return <div className="mollie-loading">Initializing payment components...</div>;
        }

        if (!isComponentReady) {
            return <div className="mollie-loading">Loading payment form...</div>;
        }

        return null;
    };

    return (
        <div className="mollie-creditcard-component">
            {getComponentStatus()}

            {/* Legacy content fallback */}
            {item.content && !isComponentReady && (
                <CreditCardField content={item.content} />
            )}
        </div>
    );
};

export default CreditCardComponent;
