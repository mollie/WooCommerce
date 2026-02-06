// Enhanced Mollie Components with proper validation and state preservation
const SELECTOR_TOKEN_ELEMENT = '.cardToken';
const SELECTOR_MOLLIE_COMPONENTS_CONTAINER = '.mollie-components';
const SELECTOR_FORM = 'form';
const SELECTOR_MOLLIE_GATEWAY_CONTAINER = '.wc_payment_methods';
const SELECTOR_MOLLIE_NOTICE_CONTAINER = '#mollie-notice';

const returnFalse = () => false;
const returnTrue = () => true;

// Add logging utility
const log = ( message, ...args ) => {
	console.log( `[Mollie Components] ${ message }`, ...args );
};

// Component state preservation
const componentStates = new Map();

/**
 * Preserve component state before unmounting
 * @param {Map} mollieComponentsMap - Components map
 */
const preserveComponentStates = ( mollieComponentsMap ) => {
	mollieComponentsMap.forEach( ( component, name ) => {
		try {
			// Store component state if available
			if ( component && typeof component.getValue === 'function' ) {
				componentStates.set( name, component.getValue() );
			}
		} catch ( error ) {
			log( 'Could not preserve state for component:', name, error );
		}
	} );
};

/**
 * Restore component state after mounting
 * @param {Object} component - Mollie component
 * @param {string} name      - Component name
 */
const restoreComponentState = ( component, name ) => {
	if ( componentStates.has( name ) ) {
		try {
			const savedState = componentStates.get( name );
			if ( savedState && typeof component.setValue === 'function' ) {
				component.setValue( savedState );
			}
		} catch ( error ) {
			log( 'Could not restore state for component:', name, error );
		}
	}
};

/**
 * Check if value is empty (null, undefined, empty object, empty array, empty string)
 * @param {*} value - Value to check
 * @return {boolean} True if value is considered empty
 */
const isEmpty = ( value ) => {
	if ( value === null || value === undefined ) {
		return true;
	}
	if ( typeof value === 'object' ) {
		if ( Array.isArray( value ) ) {
			return value.length === 0;
		}
		return Object.keys( value ).length === 0;
	}
	if ( typeof value === 'string' ) {
		return value.length === 0;
	}
	return false;
};

/**
 * Check if value is a function
 * @param {*} value - Value to check
 * @return {boolean} True if value is a function
 */
const isFunction = ( value ) => typeof value === 'function';

/**
 * Create shallow copy of object or array
 * @param {Object|Array} obj - Object to copy
 * @return {Object|Array} Shallow copy of the object
 */
const shallowCopy = ( obj ) => {
	if ( Array.isArray( obj ) ) {
		return [ ...obj ];
	}
	if ( obj && typeof obj === 'object' ) {
		return { ...obj };
	}
	return obj;
};

/* -------------------------------------------------------------------
   Containers
   ---------------------------------------------------------------- */

/**
 * Get the main gateway container element
 * @param {Element} [container=document] - Container to search within
 * @return {Element|null} Gateway container element or null
 */
const gatewayContainer = ( container = document ) => {
	const gateway = container.querySelector(
		SELECTOR_MOLLIE_GATEWAY_CONTAINER
	);
	return gateway;
};

/**
 * Get container for specific gateway
 * @param {string}  gateway              - Gateway identifier
 * @param {Element} [container=document] - Container to search within
 * @return {Element|null} Gateway container element or null
 */
const containerForGateway = ( gateway, container = document ) => {
	const methodContainer = container.querySelector(
		`.payment_method_mollie_wc_gateway_${ gateway }`
	);
	return methodContainer;
};

/**
 * Get notice container element
 * @param {Element} [container=document] - Container to search within
 * @return {Element|null} Notice container element or null
 */
const noticeContainer = ( container = document ) => {
	const notice = container.querySelector( SELECTOR_MOLLIE_NOTICE_CONTAINER );
	return notice;
};

/**
 * Get components container within given container
 * @param {Element} container - Container to search within
 * @return {Element|null} Components container element or null
 */
const componentsContainerFromWithin = ( container ) => {
	if ( ! container ) {
		log( 'Components container search failed: no container provided' );
		return null;
	}
	const components = container.querySelector(
		SELECTOR_MOLLIE_COMPONENTS_CONTAINER
	);
	return components;
};

/**
 * Clear container content
 * @param {Element} container - Container to clear
 */
const cleanContainer = ( container ) => {
	if ( ! container ) {
		log( 'Cannot clean container: container is null' );
		return;
	}
	container.innerHTML = '';
};

/* -------------------------------------------------------------------
   Notice
   ---------------------------------------------------------------- */

/**
 * Create notice DOM element
 * @param {string} content - Notice content
 * @param {string} type    - Notice type (error, success, etc.)
 * @return {Element} Notice element
 */
const renderNoticeElement = ( content, type ) => {
	const noticeDiv = document.createElement( 'div' );
	noticeDiv.id = 'mollie-notice';
	noticeDiv.className = `woocommerce-${ type }`;
	noticeDiv.innerHTML = content;
	return noticeDiv;
};

/**
 * Display notice to user
 * @param {Object} jQueryInstance     - jQuery instance (for WooCommerce compatibility)
 * @param {Object} noticeData         - Notice configuration
 * @param {string} noticeData.content - Notice content
 * @param {string} noticeData.type    - Notice type
 */
const printNotice = ( jQueryInstance, noticeData ) => {
	const container = gatewayContainer();
	const form = container ? closestFormForElement( container ) : null;
	const formContainer = form ? form.parentElement : null;
	const existingMollieNotice = document.querySelector(
		SELECTOR_MOLLIE_NOTICE_CONTAINER
	);
	const renderedNotice = renderNoticeElement(
		noticeData.content,
		noticeData.type
	);

	if ( existingMollieNotice ) {
		existingMollieNotice.remove();
	}

	if ( ! formContainer ) {
		alert( noticeData.content );
		return;
	}

	formContainer.insertAdjacentElement( 'beforebegin', renderedNotice );
	scrollToNotice( jQueryInstance );
};

/**
 * Scroll to notice element
 * @param {Object} jQueryInstance - jQuery instance (for WooCommerce compatibility)
 */
const scrollToNotice = ( jQueryInstance ) => {
	const scrollToElement = noticeContainer() || gatewayContainer();

	if ( scrollToElement && jQueryInstance.scroll_to_notices ) {
		jQueryInstance.scroll_to_notices( jQueryInstance( scrollToElement ) );
	} else {
		if ( scrollToElement ) {
			scrollToElement.scrollIntoView( {
				behavior: 'smooth',
				block: 'center',
			} );
		}
	}
};

/* -------------------------------------------------------------------
   Token
   ---------------------------------------------------------------- */

/**
 * Create hidden token field in container
 * @param {Element} container - Container to add field to
 */
const createTokenFieldWithin = ( container ) => {
	const tokenInput = document.createElement( 'input' );
	tokenInput.type = 'hidden';
	tokenInput.name = 'cardToken';
	tokenInput.className = 'cardToken';
	tokenInput.value = '';
	container.appendChild( tokenInput );
};

/**
 * Get token element within container
 * @param {Element} container - Container to search within
 * @return {Element|null} Token element or null
 */
const tokenElementWithin = ( container ) => {
	if ( ! container ) {
		return null;
	}
	const token = container.querySelector( SELECTOR_TOKEN_ELEMENT );
	return token;
};

/**
 * Retrieve payment token from Mollie
 * @param {Object} mollie - Mollie instance
 * @return {Promise<string>} Payment token
 * @throws {Error} When token creation fails
 */
const retrievePaymentToken = async ( mollie ) => {
	const { token, error } = await mollie.createToken( SELECTOR_TOKEN_ELEMENT );

	if ( error ) {
		log( 'Token creation failed:', error );
		throw new Error( error.message || '' );
	}

	return token;
};

/**
 * Set token value to form field
 * @param {string}  token             - Payment token
 * @param {Element} tokenFieldElement - Token field element
 */
const setTokenValueToField = ( token, tokenFieldElement ) => {
	if ( ! tokenFieldElement ) {
		return;
	}
	tokenFieldElement.value = token;
	tokenFieldElement.setAttribute( 'value', token );
};

/* -------------------------------------------------------------------
   Form & Validation
   ---------------------------------------------------------------- */

/**
 * Find closest form element
 * @param {Element} element - Starting element
 * @return {Element|null} Form element or null
 */
const closestFormForElement = ( element ) => {
	if ( ! element ) {
		return null;
	}
	const form = element.closest( SELECTOR_FORM );
	return form;
};

/**
 * Remove Mollie form submission listeners
 * @param {Object} $form - Form jQuery object (for WooCommerce compatibility)
 */
const turnMollieComponentsSubmissionOff = ( $form ) => {
	$form.off( 'checkout_place_order', returnFalse );
	$form.off( 'submit', submitForm );
};

/**
 * Check if specific gateway is selected
 * @param {string} gateway - Gateway identifier
 * @return {boolean} True if gateway is selected
 */
const isGatewaySelected = ( gateway ) => {
	const selectedContainer = containerForGateway( gateway );
	if ( ! selectedContainer ) {
		return false;
	}

	const gatewayInput = selectedContainer.querySelector(
		`#payment_method_mollie_wc_gateway_${ gateway }`
	);
	const isSelected = gatewayInput ? gatewayInput.checked : false;
	return isSelected;
};

/**
 * Handle payment token creation
 * @param {Object} mollie - Mollie instance
 * @return {Promise<string>} Payment token
 */
const handleTokenCreation = async ( mollie ) => {
	return await retrievePaymentToken( mollie );
};

/**
 * Handle form submission error
 * @param {Object} jQueryInstance - jQuery instance (for WooCommerce compatibility)
 * @param {Error}  error          - Error object
 * @param {Object} messages       - Messages configuration
 * @param {Object} $form          - Form jQuery object (for WooCommerce compatibility)
 * @param {Object} $document      - Document jQuery object (for WooCommerce compatibility)
 */
const handleSubmissionError = (
	jQueryInstance,
	error,
	messages,
	$form,
	$document
) => {
	const content = error?.message || messages.defaultErrorMessage;
	if ( content ) {
		printNotice( jQueryInstance, { content, type: 'error' } );
	}

	// Use jQuery for WooCommerce compatibility
	$form.removeClass( 'processing' ).unblock();
	$document.trigger( 'checkout_error' );
};

/**
 * Complete form submission with token
 * @param {string}  token                   - Payment token
 * @param {Element} gatewayContainerElement - Gateway container element
 * @param {Object}  $form                   - Form jQuery object (for WooCommerce compatibility)
 */
const completeFormSubmission = ( token, gatewayContainerElement, $form ) => {
	turnMollieComponentsSubmissionOff( $form );

	if ( token ) {
		setTokenValueToField(
			token,
			tokenElementWithin( gatewayContainerElement )
		);
	}

	// Use jQuery for form submission (WooCommerce compatibility)
	$form.submit();
};

/**
 * Handle form submission for Mollie payment
 * @param {Event} evt - Form submission event
 */
const submitForm = async ( evt ) => {
	const {
		jQuery,
		mollie,
		gateway,
		gatewayContainer: gatewayContainerElement,
		messages,
	} = evt.data;
	const form = closestFormForElement( gatewayContainerElement );
	const $form = jQuery( form );
	const $document = jQuery( document.body );

	if ( ! isGatewaySelected( gateway ) ) {
		turnMollieComponentsSubmissionOff( $form );
		$form.submit();
		return;
	}

	evt.preventDefault();
	evt.stopImmediatePropagation();

	try {
		const token = await handleTokenCreation( mollie );
		completeFormSubmission( token, gatewayContainerElement, $form );
	} catch ( error ) {
		handleSubmissionError( jQuery, error, messages, $form, $document );
	}
};

/* -------------------------------------------------------------------
   Component Management
   ---------------------------------------------------------------- */

/**
 * Get component element by name within container
 * @param {string}  name             - Component name
 * @param {Element} containerElement - Container to search within
 * @return {Element|null} Component element or null
 */
const componentElementByNameFromWithin = ( name, containerElement ) => {
	if ( ! containerElement ) {
		return null;
	}
	const component = containerElement.querySelector(
		`.mollie-component--${ name }`
	);
	return component;
};

/**
 * Create HTML element with specified attributes
 * @param {string} tagName    - HTML tag name
 * @param {Object} attributes - Element attributes
 * @param {string} content    - Element content
 * @return {string} HTML string
 */
const createHtmlElement = ( tagName, attributes, content ) => {
	const attrString = Object.entries( attributes )
		.map( ( [ key, value ] ) => `${ key }="${ value }"` )
		.join( ' ' );
	return `<${ tagName } ${ attrString }>${ content }</${ tagName }>`;
};

/**
 * Create component label element
 * @param {Element} containerElement          - Container to add element to
 * @param {Object}  componentAttributes       - Component attributes
 * @param {string}  componentAttributes.label - Component label
 */
const createComponentLabelElementWithin = ( containerElement, { label } ) => {
	const labelHtml = createHtmlElement(
		'b',
		{ class: 'mollie-component-label' },
		label
	);
	containerElement.insertAdjacentHTML( 'beforebegin', labelHtml );
};

/**
 * Create component error container
 * @param {Element} containerElement         - Container to add element to
 * @param {Object}  componentAttributes      - Component attributes
 * @param {string}  componentAttributes.name - Component name
 */
const createComponentsErrorContainerWithin = ( containerElement, { name } ) => {
	const errorHtml = createHtmlElement(
		'div',
		{ role: 'alert', id: `${ name }-errors` },
		''
	);
	containerElement.insertAdjacentHTML( 'afterend', errorHtml );
};

/**
 * Get or create component by name
 * @param {string} name                - Component name
 * @param {Object} mollie              - Mollie instance
 * @param {Object} settings            - Component settings
 * @param {Map}    mollieComponentsMap - Components map
 * @return {Object} Mollie component
 */
const componentByName = ( name, mollie, settings, mollieComponentsMap ) => {
	if ( mollieComponentsMap.has( name ) ) {
		return mollieComponentsMap.get( name );
	}

	return mollie.createComponent( name, settings );
};

/**
 * Enhanced unmount with state preservation
 * @param {Map} mollieComponentsMap - Components map
 */
const unmountComponents = ( mollieComponentsMap ) => {
	preserveComponentStates( mollieComponentsMap );

	mollieComponentsMap.forEach( ( component, name ) => {
		try {
			component.unmount();
		} catch ( error ) {
			log( 'Error unmounting component:', name, error );
		}
	} );
};

/**
 * Check if components need remounting
 * @param {string} gatewayId           - Gateway identifier
 * @param {Map}    mollieComponentsMap - Components map
 * @return {boolean} True if remounting is needed
 */
const shouldRemountComponents = ( gatewayId, mollieComponentsMap ) => {
	const gatewayContainerElement = containerForGateway( gatewayId );
	const mollieComponentsContainer = componentsContainerFromWithin(
		gatewayContainerElement
	);

	if ( ! mollieComponentsContainer ) {
		return true;
	}

	const existingComponents =
		mollieComponentsContainer.querySelectorAll( '[id]' );
	const hasExistingComponents = existingComponents.length > 0;
	const hasComponentsInMap = mollieComponentsMap.size > 0;

	return ! hasExistingComponents || ! hasComponentsInMap;
};

/**
 * Create component container in DOM
 * @param {string}  componentName             - Component name
 * @param {Element} mollieComponentsContainer - Container for components
 */
const createComponentContainer = (
	componentName,
	mollieComponentsContainer
) => {
	const containerDiv = document.createElement( 'div' );
	containerDiv.id = componentName;
	mollieComponentsContainer.appendChild( containerDiv );
};

/**
 * Mount component to DOM
 * @param {Object} component     - Mollie component
 * @param {string} componentName - Component name
 */
const mountComponentToDom = ( component, componentName ) => {
	component.mount( `#${ componentName }` );

	setTimeout( () => {
		restoreComponentState( component, componentName );
	}, 100 );
};

/**
 * Setup component UI elements
 * @param {Element} currentComponentElement - Component DOM element
 * @param {Object}  componentAttributes     - Component attributes
 */
const setupComponentUi = ( currentComponentElement, componentAttributes ) => {
	createComponentLabelElementWithin(
		currentComponentElement,
		componentAttributes
	);
	createComponentsErrorContainerWithin(
		currentComponentElement,
		componentAttributes
	);
};

/**
 * Setup component error handling
 * @param {Object} component     - Mollie component
 * @param {string} componentName - Component name
 */
const setupComponentErrorHandling = ( component, componentName ) => {
	const componentError = document.querySelector(
		`#${ componentName }-errors`
	);
	component.addEventListener( 'change', ( event ) => {
		if ( event.error && event.touched ) {
			if ( componentError ) {
				componentError.textContent = event.error;
			}
		} else {
			if ( componentError ) {
				componentError.textContent = '';
			}
		}
	} );
};

/**
 * Mount single component
 * @param {Object}  mollie               - Mollie instance
 * @param {Object}  componentSettings    - Component settings
 * @param {Object}  componentAttributes  - Component attributes
 * @param {Map}     mollieComponentsMap  - Components map
 * @param {Element} baseContainerElement - Base container element
 */
const mountComponent = (
	mollie,
	componentSettings,
	componentAttributes,
	mollieComponentsMap,
	baseContainerElement
) => {
	const { name: componentName } = componentAttributes;
	const component = componentByName(
		componentName,
		mollie,
		componentSettings,
		mollieComponentsMap
	);
	const mollieComponentsContainer =
		componentsContainerFromWithin( baseContainerElement );

	createComponentContainer( componentName, mollieComponentsContainer );
	mountComponentToDom( component, componentName );

	const currentComponentElement = componentElementByNameFromWithin(
		componentName,
		baseContainerElement
	);

	if ( ! currentComponentElement ) {
		console.warn(
			`Component ${ componentName } not found in the DOM. Probably had problem during mount.`
		);
		return;
	}

	setupComponentUi( currentComponentElement, componentAttributes );
	setupComponentErrorHandling( component, componentName );

	mollieComponentsMap.set( componentName, component );
};

/**
 * Mount multiple components
 * @param {Object}  mollie               - Mollie instance
 * @param {Object}  componentSettings    - Component settings
 * @param {Array}   componentsAttributes - Array of component attributes
 * @param {Map}     mollieComponentsMap  - Components map
 * @param {Element} baseContainerElement - Base container element
 */
const mountComponents = (
	mollie,
	componentSettings,
	componentsAttributes,
	mollieComponentsMap,
	baseContainerElement
) => {
	componentsAttributes.forEach( ( componentAttributes ) =>
		mountComponent(
			mollie,
			componentSettings,
			componentAttributes,
			mollieComponentsMap,
			baseContainerElement
		)
	);
};

/**
 * Enhanced initialization with smart remounting
 * @param {Object} jQueryInstance                - jQuery instance (for WooCommerce compatibility)
 * @param {Object} mollie                        - Mollie instance
 * @param {Object} settings                      - Component settings
 * @param          settings.options
 * @param          settings.merchantProfileId
 * @param {Map}    mollieComponentsMap           - Components map
 * @param          settings.componentsSettings
 * @param          settings.componentsAttributes
 * @param          settings.enabledGateways
 * @param          settings.messages
 */
const initializeComponents = (
	jQueryInstance,
	mollie,
	{
		options,
		merchantProfileId,
		componentsSettings,
		componentsAttributes,
		enabledGateways,
		messages,
	},
	mollieComponentsMap
) => {
	enabledGateways.forEach( ( gatewayId ) => {
		const gatewayContainerElement = containerForGateway( gatewayId );
		const mollieComponentsContainer = componentsContainerFromWithin(
			gatewayContainerElement
		);
		const form = closestFormForElement( gatewayContainerElement );
		const $form = jQueryInstance( form );

		if ( ! gatewayContainerElement ) {
			console.warn(
				`Cannot initialize Mollie Components for gateway ${ gatewayId }.`
			);
			return;
		}

		if ( ! form ) {
			console.warn(
				'Cannot initialize Mollie Components, no form found.'
			);
			return;
		}

		if ( shouldRemountComponents( gatewayId, mollieComponentsMap ) ) {
			unmountComponents( mollieComponentsMap );
			cleanContainer( mollieComponentsContainer );
			createTokenFieldWithin( mollieComponentsContainer );

			mountComponents(
				mollie,
				componentsSettings[ gatewayId ],
				componentsAttributes,
				mollieComponentsMap,
				gatewayContainerElement
			);
		} else {
			log( 'Components already mounted for gateway:', gatewayId );
		}

		turnMollieComponentsSubmissionOff( $form );

		// Use jQuery for WooCommerce compatibility
		$form.on( 'checkout_place_order', returnFalse );
		$form.on(
			'submit',
			null,
			{
				jQuery: jQueryInstance,
				mollie,
				gateway: gatewayId,
				gatewayContainer: gatewayContainerElement,
				messages,
			},
			submitForm
		);
	} );
};

/**
 * Create initialization handler function
 * @param {Object} jQueryInstance           - jQuery instance (for WooCommerce compatibility)
 * @param {Object} mollie                   - Mollie instance
 * @param {Object} mollieComponentsSettings - Component settings
 * @param {Map}    mollieComponentsMap      - Components map
 * @return {Function} Initialization handler function
 */
const createInitializationHandler = (
	jQueryInstance,
	mollie,
	mollieComponentsSettings,
	mollieComponentsMap
) => {
	return function () {
		const copySettings = shallowCopy( mollieComponentsSettings );

		copySettings.enabledGateways =
			mollieComponentsSettings.enabledGateways.filter( ( gatewayId ) => {
				const gatewayContainerElement =
					containerForGateway( gatewayId );
				if ( ! gatewayContainerElement ) {
					log(
						'Gateway container not found, enabling fallback for:',
						gatewayId
					);
					const $form = jQueryInstance( 'form[name="checkout"]' );
					$form.on( 'checkout_place_order', returnTrue );
					return false;
				}
				return true;
			} );

		if ( copySettings.enabledGateways.length === 0 ) {
			return;
		}

		log(
			'Proceeding with enhanced initialization for gateways:',
			copySettings.enabledGateways
		);
		initializeComponents(
			jQueryInstance,
			mollie,
			copySettings,
			mollieComponentsMap
		);
	};
};

// initialization with throttling
( ( window ) => {
	const { Mollie, mollieComponentsSettings, jQuery } = window;

	if ( isEmpty( mollieComponentsSettings ) || ! isFunction( Mollie ) ) {
		return;
	}

	let eventName = 'updated_checkout';
	const mollieComponentsMap = new Map();
	const $document = jQuery( document );
	const { merchantProfileId, options, isCheckoutPayPage } =
		mollieComponentsSettings;
	const mollie = new Mollie( merchantProfileId, options );

	if ( isCheckoutPayPage ) {
		eventName = 'payment_method_selected';
		$document.on( eventName, () => {
			initializeComponents(
				jQuery,
				mollie,
				mollieComponentsSettings,
				mollieComponentsMap
			);
		} );
		return;
	}

	const initHandler = createInitializationHandler(
		jQuery,
		mollie,
		mollieComponentsSettings,
		mollieComponentsMap
	);

	// Throttle initialization to prevent excessive remounting
	let initTimeout;
	const throttledInitHandler = function () {
		clearTimeout( initTimeout );
		initTimeout = setTimeout( initHandler, 250 );
	};

	$document.on( eventName, throttledInitHandler );
	$document.on( 'update_checkout', throttledInitHandler );
} )( window );
