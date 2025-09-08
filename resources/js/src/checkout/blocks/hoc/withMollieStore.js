import { MOLLIE_STORE_KEY } from "../store";

/**
 * Higher-Order Component that connects components to the Mollie Redux store
 * Provides store state and actions as props to wrapped components
 */
const withMollieStore = (WrappedComponent) => {
    const WithMollieStoreComponent = (props) => {
        const { useSelect, useDispatch } = wp.data;

        // Store selectors
        const storeData = useSelect((select) => ({
            selectedIssuer: select(MOLLIE_STORE_KEY).getSelectedIssuer(),
            inputPhone: select(MOLLIE_STORE_KEY).getInputPhone(),
            inputBirthdate: select(MOLLIE_STORE_KEY).getInputBirthdate(),
            inputCompany: select(MOLLIE_STORE_KEY).getInputCompany(),
            phonePlaceholder: select(MOLLIE_STORE_KEY).getPhonePlaceholder(),
            cardToken: select(MOLLIE_STORE_KEY).getCardToken(),
        }), []);

        // Store actions
        const storeActions = useDispatch(MOLLIE_STORE_KEY);
        const {
            setSelectedIssuer,
            setInputPhone,
            setInputBirthdate,
            setInputCompany,
            setCardToken,
            updatePhonePlaceholderByCountry
        } = storeActions;

        return (
            <WrappedComponent
                {...props}
                // Store state
                selectedIssuer={storeData.selectedIssuer}
                inputPhone={storeData.inputPhone}
                inputBirthdate={storeData.inputBirthdate}
                inputCompany={storeData.inputCompany}
                phonePlaceholder={storeData.phonePlaceholder}
                cardToken={storeData.cardToken}
                // Store actions
                setSelectedIssuer={setSelectedIssuer}
                setInputPhone={setInputPhone}
                setInputBirthdate={setInputBirthdate}
                setInputCompany={setInputCompany}
                setCardToken={setCardToken}
                updatePhonePlaceholderByCountry={updatePhonePlaceholderByCountry}
            />
        );
    };

    WithMollieStoreComponent.displayName = `withMollieStore(${WrappedComponent.displayName || WrappedComponent.name || 'Component'})`;

    return WithMollieStoreComponent;
};

export default withMollieStore;
