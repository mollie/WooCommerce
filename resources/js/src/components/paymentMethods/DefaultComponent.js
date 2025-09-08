import { IssuerSelect } from '../paymentFields/IssuerSelect';

/**
 * Default Payment Component
 * Handles payment methods with issuer selection (banks) or simple content display
 */
const DefaultComponent = ({
                              item,
                              activePaymentMethod,
                              // Store props injected by withMollieStore HOC
                              selectedIssuer,
                              setSelectedIssuer
                          }) => {
    const issuerKey = `mollie-payments-for-woocommerce_issuer_${activePaymentMethod}`;

    let itemContent = null;
    if (item.content && item.content !== '') {
        itemContent = <p>{item.content}</p>;
    }

    if (item.issuers && item.issuers.length > 0) {
        return (
            <div>
                {itemContent}
                <IssuerSelect
                    issuerKey={issuerKey}
                    issuers={item.issuers}
                    selectedIssuer={selectedIssuer}
                    updateIssuer={setSelectedIssuer}
                />
            </div>
        );
    }

    // Simple content display for payment methods without special requirements
    return <div>{itemContent}</div>;
};

export default DefaultComponent;
