import { mollieComponentsManager } from '../../checkout/blocks/services/mollieComponentsManager';
import { MOLLIE_STORE_KEY } from '../../checkout/blocks/store';

/**
 * Custom hook for TokenManager integration
 * @param paymentMethod
 */
export const useTokenManager = ( paymentMethod ) => {
	const { useState, useEffect, useCallback } = wp.element;
	const { useSelect, useDispatch } = wp.data;

	const { canCreateToken, isComponentReady, tokenError, componentError } =
		useSelect(
			( select ) => ( {
				canCreateToken: select( MOLLIE_STORE_KEY ).getCanCreateToken(),
				isComponentReady:
					select( MOLLIE_STORE_KEY ).getIsComponentReady(),
				tokenError: select( MOLLIE_STORE_KEY ).getTokenError(),
				componentError: select( MOLLIE_STORE_KEY ).getComponentError(),
			} ),
			[]
		);

	const createToken = useCallback( async () => {
		if ( ! canCreateToken ) {
			throw new Error( 'Cannot create token: components not ready' );
		}

		return await mollieComponentsManager.createToken();
	}, [ canCreateToken ] );

	const mountComponents = useCallback(
		async ( config, container ) => {
			return await mollieComponentsManager.mountComponents(
				paymentMethod,
				config,
				container
			);
		},
		[ paymentMethod ]
	);

	const unmountComponents = useCallback( async () => {
		return await mollieComponentsManager.unmountComponents( paymentMethod );
	}, [ paymentMethod ] );

	return {
		createToken,
		mountComponents,
		unmountComponents,
		canCreateToken,
		isComponentReady,
		tokenError,
		componentError,
		isReady: mollieComponentsManager.isReady(),
	};
};
