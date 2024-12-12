const DEFAULT_STATE = {
    cachedAvailableGateways: {},
};

const actions = {
    setAvailableGateways(country, currency, gateways) {
        return {
            type: 'SET_AVAILABLE_GATEWAYS',
            country,
            currency,
            gateways,
        };
    },
};

const reducer = (state = DEFAULT_STATE, action) => {
    switch (action.type) {
        case 'SET_AVAILABLE_GATEWAYS':
            return {
                ...state,
                cachedAvailableGateways: {
                    ...state.cachedAvailableGateways,
                    [`${action.currency}-${action.country}`]: action.gateways,
                },
            };
        default:
            return state;
    }
};

const selectors = {
    getAvailableGateways(state) {
        return state.cachedAvailableGateways || {};
    },
};

wp.data.registerStore('mollie/available-gateways', {
    reducer,
    actions,
    selectors,
});
