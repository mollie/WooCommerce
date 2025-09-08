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

            componentInitialized: select(MOLLIE_STORE_KEY).getComponentInitialized(),
            componentInitializing: select(MOLLIE_STORE_KEY).getComponentInitializing(),
            componentError: select(MOLLIE_STORE_KEY).getComponentError(),
            tokenCreating: select(MOLLIE_STORE_KEY).getTokenCreating(),
            tokenCreated: select(MOLLIE_STORE_KEY).getTokenCreated(),
            tokenError: select(MOLLIE_STORE_KEY).getTokenError(),
            isComponentReady: select(MOLLIE_STORE_KEY).getIsComponentReady(),
            isTokenReady: select(MOLLIE_STORE_KEY).getIsTokenReady(),
            canCreateToken: select(MOLLIE_STORE_KEY).getCanCreateToken(),
        }), []);

        // Store actions
        const storeActions = useDispatch(MOLLIE_STORE_KEY);
        const {
            setSelectedIssuer,
            setInputPhone,
            setInputBirthdate,
            setInputCompany,
            setCardToken,
            updatePhonePlaceholderByCountry,

            setComponentInitialized,
            setComponentError,
            clearComponentError,
            setTokenCreating,
            setTokenCreated,
            setTokenError,
            clearTokenError,
            clearTokenData
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

                componentInitialized={storeData.componentInitialized}
                componentInitializing={storeData.componentInitializing}
                componentError={storeData.componentError}
                tokenCreating={storeData.tokenCreating}
                tokenCreated={storeData.tokenCreated}
                tokenError={storeData.tokenError}
                isComponentReady={storeData.isComponentReady}
                isTokenReady={storeData.isTokenReady}
                canCreateToken={storeData.canCreateToken}

                // Store actions
                setSelectedIssuer={setSelectedIssuer}
                setInputPhone={setInputPhone}
                setInputBirthdate={setInputBirthdate}
                setInputCompany={setInputCompany}
                setCardToken={setCardToken}
                updatePhonePlaceholderByCountry={updatePhonePlaceholderByCountry}

                setComponentInitialized={setComponentInitialized}
                setComponentError={setComponentError}
                clearComponentError={clearComponentError}
                setTokenCreating={setTokenCreating}
                setTokenCreated={setTokenCreated}
                setTokenError={setTokenError}
                clearTokenError={clearTokenError}
                clearTokenData={clearTokenData}
            />
        );
    };

    WithMollieStoreComponent.displayName = `withMollieStore(${WrappedComponent.displayName || WrappedComponent.name || 'Component'})`;

    return WithMollieStoreComponent;
};

export default withMollieStore;
