import { select, dispatch } from '@wordpress/data';
import { MOLLIE_STORE_KEY } from '../store';

/**
 * Mollie Components Token Manager
 * Handles Mollie Components lifecycle, token creation, and validation
 * Integrates with WordPress Redux store and provides promise-based API
 */
export class MollieComponentsManager {
	constructor() {
		this.mollie = null;
		this.components = new Map();
		this.isInitialized = false;
		this.initializationPromise = null;
		this.activeGateway = null;

		// Bind methods to maintain context
		this.initialize = this.initialize.bind( this );
		this.createToken = this.createToken.bind( this );
		this.cleanup = this.cleanup.bind( this );
	}

	/**
	 * Initialize Mollie Components SDK
	 * @param {Object} config                   - Mollie configuration
	 * @param {string} config.merchantProfileId - Merchant profile ID
	 * @param {Object} config.options           - Mollie options
	 * @return {Promise<void>}
	 */
	async initialize( config ) {
		if ( this.initializationPromise ) {
			return this.initializationPromise;
		}

		this.initializationPromise = this._initializeMollie( config );
		return this.initializationPromise;
	}

	/**
	 * Internal initialization method
	 * @param config
	 * @private
	 */
	async _initializeMollie( config ) {
		try {
			// Update store initialization state
			dispatch( MOLLIE_STORE_KEY ).setComponentInitializing( true );
			dispatch( MOLLIE_STORE_KEY ).clearComponentError();

			// Initialize Mollie instance
			this.mollie = new window.Mollie(
				config.merchantProfileId,
				config.options
			);
			this.isInitialized = true;

			// Update store state
			dispatch( MOLLIE_STORE_KEY ).setComponentInitialized( true );
			dispatch( MOLLIE_STORE_KEY ).setComponentInitializing( false );

			console.log( 'Mollie TokenManager initialized successfully' );
		} catch ( error ) {
			this.isInitialized = false;
			dispatch( MOLLIE_STORE_KEY ).setComponentError( error.message );
			dispatch( MOLLIE_STORE_KEY ).setComponentInitializing( false );
			throw new Error(
				`Failed to initialize Mollie Components: ${ error.message }`
			);
		}
	}

	/**
	 * Mount payment components for a specific gateway
	 * @param {string}      gateway              - Gateway identifier
	 * @param {Array}       componentsAttributes - Array of component configurations
	 * @param {Object}      componentsConfig     - Components configuration
	 * @param {HTMLElement} container            - Container element
	 * @return {Promise<void>}
	 */
	async mountComponents(
		gateway,
		componentsAttributes,
		componentsConfig,
		container
	) {
		if ( ! this.isInitialized ) {
			throw new Error( 'TokenManager not initialized' );
		}

		if ( ! componentsConfig ) {
			console.warn(
				'Components configuration not ready, skipping mount'
			);
			return;
		}

		const paymentMethodId = gateway.replace( 'mollie_wc_gateway_', '' );

		if (
			! componentsConfig[ paymentMethodId ] ||
			typeof componentsConfig[ paymentMethodId ] !== 'object'
		) {
			console.warn(
				'Invalid components configuration structure, skipping mount'
			);
			return;
		}

		try {
			await this.unmountComponents( gateway );
			dispatch( MOLLIE_STORE_KEY ).setComponentMounting( gateway, true );
			const gatewayComponents = new Map();
			for ( const componentAttributes of componentsAttributes ) {
				const component = await this._mountSingleComponent(
					componentAttributes,
					componentsConfig[ paymentMethodId ],
					container
				);
				gatewayComponents.set( componentAttributes.name, component );
			}
			this.components.set( gateway, gatewayComponents );
			this.activeGateway = gateway;

			dispatch( MOLLIE_STORE_KEY ).setComponentMounted( gateway, true );
			dispatch( MOLLIE_STORE_KEY ).setComponentMounting( gateway, false );
			dispatch( MOLLIE_STORE_KEY ).clearComponentError();
		} catch ( error ) {
			dispatch( MOLLIE_STORE_KEY ).setComponentError( error.message );
			dispatch( MOLLIE_STORE_KEY ).setComponentMounting( gateway, false );
			throw error;
		}
	}
	/**
	 * Mount a single component with proper wrapper structure
	 * @param componentAttributes
	 * @param settings
	 * @param container
	 * @private
	 */
	async _mountSingleComponent( componentAttributes, settings, container ) {
		const { name, label } = componentAttributes;

		try {
			// Create wrapper container (not styled)
			const wrapperContainer = document.createElement( 'div' );
			wrapperContainer.id = name;
			wrapperContainer.className = `mollie-component-wrapper mollie-component-wrapper--${ name }`;
			wrapperContainer.setAttribute( 'data-component', name );
			container.appendChild( wrapperContainer );

			// Add component label
			if ( label ) {
				const labelElement = document.createElement( 'label' );
				labelElement.className = 'mollie-component-label';
				labelElement.setAttribute( 'for', name );
				labelElement.innerHTML = label;
				wrapperContainer.parentNode.insertBefore(
					labelElement,
					wrapperContainer
				);
			}

			// Create error container
			const errorContainer = document.createElement( 'div' );
			errorContainer.id = `${ name }-errors`;
			errorContainer.setAttribute( 'role', 'alert' );
			errorContainer.className = 'mollie-component-error';
			wrapperContainer.parentNode.insertBefore(
				errorContainer,
				wrapperContainer.nextSibling
			);

			// Create Mollie component (this will create the inner styled div)
			const component = this.mollie.createComponent( name, settings );
			component.mount( `#${ name }` );

			// Add event listeners - apply states to wrapper
			component.addEventListener( 'change', ( event ) => {
				if ( event.error && event.touched ) {
					wrapperContainer.classList.add( 'is-invalid' );
					wrapperContainer.classList.remove( 'is-valid' );
					errorContainer.textContent = event.error;
					wp.data
						.dispatch( MOLLIE_STORE_KEY )
						.setComponentError( event.error );
				} else {
					wrapperContainer.classList.remove( 'is-invalid' );
					errorContainer.textContent = '';
					dispatch( MOLLIE_STORE_KEY ).clearComponentError();
				}
			} );

			component.addEventListener( 'focus', () => {
				wrapperContainer.classList.add( 'has-focus' );
				wp.data
					.dispatch( MOLLIE_STORE_KEY )
					.setComponentFocused( name, true );
			} );

			component.addEventListener( 'blur', () => {
				wrapperContainer.classList.remove( 'has-focus' );
				wp.data
					.dispatch( MOLLIE_STORE_KEY )
					.setComponentFocused( name, false );
			} );

			return component;
		} catch ( error ) {
			throw new Error(
				`Failed to mount component ${ name }: ${ error.message }`
			);
		}
	}
	/**
	 * Unmount components for a gateway
	 * @param {string} gateway - Gateway identifier
	 * @return {Promise<void>}
	 */
	async unmountComponents( gateway ) {
		const gatewayComponents = this.components.get( gateway );
		if ( ! gatewayComponents ) {
			return;
		}

		try {
			// Unmount all components
			for ( const [ name, component ] of gatewayComponents ) {
				try {
					component.unmount();

					// Clean up DOM elements
					const componentElement = document.getElementById(
						`${ name }`
					);
					const errorElement = document.getElementById(
						`${ name }-errors`
					);
					const labelElement = document.querySelector(
						`.mollie-component-label[for="${ name }"]`
					);

					[ componentElement, errorElement, labelElement ].forEach(
						( el ) => {
							if ( el ) {
								el.remove();
							}
						}
					);
				} catch ( error ) {
					console.warn(
						`Failed to unmount component ${ name }:`,
						error
					);
				}
			}

			this.components.delete( gateway );

			// Update store state
			dispatch( MOLLIE_STORE_KEY ).setComponentMounted( gateway, false );

			if ( this.activeGateway === gateway ) {
				this.activeGateway = null;
			}
		} catch ( error ) {
			console.error(
				`Failed to unmount components for gateway ${ gateway }:`,
				error
			);
			throw error;
		}
	}

	/**
	 * Create payment token
	 * @return {Promise<string>} Payment token
	 */
	async createToken() {
		if ( ! this.isInitialized || ! this.mollie ) {
			throw new Error( 'Mollie Components not initialized' );
		}

		if ( ! this.activeGateway ) {
			throw new Error( 'No active payment gateway' );
		}

		try {
			dispatch( MOLLIE_STORE_KEY ).setTokenCreating( true );
			dispatch( MOLLIE_STORE_KEY ).clearTokenError();

			// Create token using Mollie Components
			const { token, error } = await this.mollie.createToken();

			if ( error ) {
				throw new Error( error.message || 'Token creation failed' );
			}

			if ( ! token ) {
				throw new Error( 'No token received from Mollie Components' );
			}

			// Update store with token
			dispatch( MOLLIE_STORE_KEY ).setCardToken( token );
			dispatch( MOLLIE_STORE_KEY ).setTokenCreated( true );
			dispatch( MOLLIE_STORE_KEY ).setTokenCreating( false );

			return token;
		} catch ( error ) {
			dispatch( MOLLIE_STORE_KEY ).setTokenError( error.message );
			dispatch( MOLLIE_STORE_KEY ).setTokenCreating( false );
			dispatch( MOLLIE_STORE_KEY ).setTokenCreated( false );
			throw error;
		}
	}

	/**
	 * Check if components are ready for token creation
	 * @return {boolean}
	 */
	isReady() {
		return (
			this.isInitialized &&
			this.activeGateway &&
			this.components.has( this.activeGateway ) &&
			! select( MOLLIE_STORE_KEY ).getComponentError()
		);
	}

	/**
	 * Get current gateway
	 * @return {string|null}
	 */
	getActiveGateway() {
		return this.activeGateway;
	}

	/**
	 * Clean up all resources
	 */
	cleanup() {
		try {
			// Unmount all components
			for ( const gateway of this.components.keys() ) {
				this.unmountComponents( gateway );
			}

			this.components.clear();
			this.mollie = null;
			this.isInitialized = false;
			this.initializationPromise = null;
			this.activeGateway = null;

			// Reset store state
			dispatch( MOLLIE_STORE_KEY ).setComponentInitialized( false );
			dispatch( MOLLIE_STORE_KEY ).clearTokenData();
			dispatch( MOLLIE_STORE_KEY ).clearComponentError();
		} catch ( error ) {
			console.error( 'TokenManager cleanup failed:', error );
		}
	}
}

export const mollieComponentsManager = new MollieComponentsManager();
