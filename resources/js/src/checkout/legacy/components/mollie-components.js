const SELECTOR_TOKEN_ELEMENT = '.cardToken';
const SELECTOR_MOLLIE_COMPONENTS_CONTAINER = '.mollie-components';
const SELECTOR_FORM = 'form';
const SELECTOR_MOLLIE_GATEWAY_CONTAINER = '.wc_payment_methods';
const SELECTOR_MOLLIE_NOTICE_CONTAINER = '#mollie-notice';
const SELECTOR_BLOCKS_SUBMIT_BUTTON = '.wc-block-components-checkout-place-order-button';

const $ = jQuery; // Keep jQuery reference for WooCommerce compatibility
const returnFalse = () => false;
const returnTrue = () => true;

// Add logging utility
const log = (message, ...args) => {
    console.log(`[Mollie Components] ${message}`, ...args);
};

/**
 * Check if value is empty (null, undefined, empty object, empty array, empty string)
 * @param {*} value - Value to check
 * @returns {boolean} True if value is considered empty
 */
const isEmpty = (value) => {
    if (value == null) return true;
    if (typeof value === 'object') {
        if (Array.isArray(value)) return value.length === 0;
        return Object.keys(value).length === 0;
    }
    if (typeof value === 'string') return value.length === 0;
    return false;
};

/**
 * Check if value is a function
 * @param {*} value - Value to check
 * @returns {boolean} True if value is a function
 */
const isFunction = (value) => typeof value === 'function';

/**
 * Create shallow copy of object or array
 * @param {Object|Array} obj - Object to copy
 * @returns {Object|Array} Shallow copy of the object
 */
const shallowCopy = (obj) => {
    if (Array.isArray(obj)) return [...obj];
    if (obj && typeof obj === 'object') return {...obj};
    return obj;
};

/* -------------------------------------------------------------------
   Containers
   ---------------------------------------------------------------- */

/**
 * Get the main gateway container element
 * @param {Element} [container=document] - Container to search within
 * @returns {Element|null} Gateway container element or null
 */
const gatewayContainer = (container = document) => {
    log('Getting gateway container');
    const gateway = container.querySelector(SELECTOR_MOLLIE_GATEWAY_CONTAINER);
    log('Gateway container found:', !!gateway);
    return gateway;
};

/**
 * Get container for specific gateway
 * @param {string} gateway - Gateway identifier
 * @param {Element} [container=document] - Container to search within
 * @returns {Element|null} Gateway container element or null
 */
const containerForGateway = (gateway, container = document) => {
    log('Getting container for gateway:', gateway);
    const gatewayContainer = container.querySelector(`.payment_method_mollie_wc_gateway_${gateway}`);
    log('Gateway container found:', !!gatewayContainer);
    return gatewayContainer;
};

/**
 * Get notice container element
 * @param {Element} [container=document] - Container to search within
 * @returns {Element|null} Notice container element or null
 */
const noticeContainer = (container = document) => {
    log('Getting notice container');
    const notice = container.querySelector(SELECTOR_MOLLIE_NOTICE_CONTAINER);
    log('Notice container found:', !!notice);
    return notice;
};

/**
 * Get components container within given container
 * @param {Element} container - Container to search within
 * @returns {Element|null} Components container element or null
 */
const componentsContainerFromWithin = (container) => {
    if (!container) {
        log('Components container search failed: no container provided');
        return null;
    }
    log('Getting components container within:', container);
    const components = container.querySelector(SELECTOR_MOLLIE_COMPONENTS_CONTAINER);
    log('Components container found:', !!components);
    return components;
};

/**
 * Clear container content
 * @param {Element} container - Container to clear
 */
const cleanContainer = (container) => {
    if (!container) {
        log('Cannot clean container: container is null');
        return;
    }
    log('Cleaning container:', container);
    container.innerHTML = '';
};

/* -------------------------------------------------------------------
   Notice
   ---------------------------------------------------------------- */

/**
 * Create notice DOM element
 * @param {string} content - Notice content
 * @param {string} type - Notice type (error, success, etc.)
 * @returns {Element} Notice element
 */
const renderNoticeElement = (content, type) => {
    log('Rendering notice element:', type, content);
    const noticeDiv = document.createElement('div');
    noticeDiv.id = 'mollie-notice';
    noticeDiv.className = `woocommerce-${type}`;
    noticeDiv.innerHTML = content;
    return noticeDiv;
};

/**
 * Display notice to user
 * @param {jQuery} jQueryInstance - jQuery instance (for WooCommerce compatibility)
 * @param {Object} noticeData - Notice configuration
 * @param {string} noticeData.content - Notice content
 * @param {string} noticeData.type - Notice type
 */
const printNotice = (jQueryInstance, noticeData) => {
    log('Printing notice:', noticeData);
    const container = gatewayContainer();
    const form = container ? closestFormForElement(container) : null;
    const formContainer = form ? form.parentElement : null;
    const existingMollieNotice = document.querySelector(SELECTOR_MOLLIE_NOTICE_CONTAINER);
    const renderedNotice = renderNoticeElement(noticeData.content, noticeData.type);

    if (existingMollieNotice) {
        log('Removing existing notice');
        existingMollieNotice.remove();
    }

    if (!formContainer) {
        log('No form container found, showing alert');
        alert(noticeData.content);
        return;
    }

    log('Inserting notice before form container');
    formContainer.insertAdjacentElement('beforebegin', renderedNotice);
    scrollToNotice(jQueryInstance);
};

/**
 * Scroll to notice element
 * @param {jQuery} jQueryInstance - jQuery instance (for WooCommerce compatibility)
 */
const scrollToNotice = (jQueryInstance) => {
    log('Scrolling to notice');
    const scrollToElement = noticeContainer() || gatewayContainer();

    if (scrollToElement && jQueryInstance.scroll_to_notices) {
        log('Using WooCommerce scroll_to_notices');
        jQueryInstance.scroll_to_notices(jQueryInstance(scrollToElement));
    } else {
        log('Fallback scroll behavior');
        if (scrollToElement) {
            scrollToElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
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
const createTokenFieldWithin = (container) => {
    log('Creating token field within container:', container);
    const tokenInput = document.createElement('input');
    tokenInput.type = 'hidden';
    tokenInput.name = 'cardToken';
    tokenInput.className = 'cardToken';
    tokenInput.value = '';
    container.appendChild(tokenInput);
    log('Token field created');
};

/**
 * Get token element within container
 * @param {Element} container - Container to search within
 * @returns {Element|null} Token element or null
 */
const tokenElementWithin = (container) => {
    if (!container) {
        log('Token element search failed: no container provided');
        return null;
    }
    log('Getting token element within container:', container);
    const token = container.querySelector(SELECTOR_TOKEN_ELEMENT);
    log('Token element found:', !!token);
    return token;
};

/**
 * Retrieve payment token from Mollie
 * @param {Object} mollie - Mollie instance
 * @returns {Promise<string>} Payment token
 * @throws {Error} When token creation fails
 */
const retrievePaymentToken = async (mollie) => {
    log('Retrieving payment token from Mollie');
    const {token, error} = await mollie.createToken(SELECTOR_TOKEN_ELEMENT);

    if (error) {
        log('Token creation failed:', error);
        throw new Error(error.message || '');
    }

    log('Payment token retrieved successfully');
    return token;
};

/**
 * Set token value to form field
 * @param {string} token - Payment token
 * @param {Element} tokenFieldElement - Token field element
 */
const setTokenValueToField = (token, tokenFieldElement) => {
    if (!tokenFieldElement) {
        log('Cannot set token: token field element is null');
        return;
    }
    log('Setting token value to field:', token);
    tokenFieldElement.value = token;
    tokenFieldElement.setAttribute('value', token);
};

/* -------------------------------------------------------------------
   Form
   ---------------------------------------------------------------- */

/**
 * Find closest form element
 * @param {Element} element - Starting element
 * @returns {Element|null} Form element or null
 */
const closestFormForElement = (element) => {
    if (!element) {
        log('Cannot find closest form: element is null');
        return null;
    }
    log('Finding closest form for element:', element);
    const form = element.closest(SELECTOR_FORM);
    log('Closest form found:', !!form);
    return form;
};

/**
 * Remove Mollie form submission listeners
 * @param {jQuery} $form - Form jQuery object (for WooCommerce compatibility)
 */
const turnMollieComponentsSubmissionOff = ($form) => {
    log('Turning off Mollie components submission listeners');
    $form.off('checkout_place_order', returnFalse);
    $form.off('submit', submitForm);
};

/**
 * Check if specific gateway is selected
 * @param {string} gateway - Gateway identifier
 * @returns {boolean} True if gateway is selected
 */
const isGatewaySelected = (gateway) => {
    log('Checking if gateway is selected:', gateway);
    const gatewayContainer = containerForGateway(gateway);
    if (!gatewayContainer) {
        log('Gateway not selected: container not found');
        return false;
    }

    const gatewayInput = gatewayContainer.querySelector(`#payment_method_mollie_wc_gateway_${gateway}`);
    const isSelected = gatewayInput ? gatewayInput.checked : false;
    log('Gateway selected:', isSelected);
    return isSelected;
};

/**
 * Handle payment token creation
 * @param {Object} mollie - Mollie instance
 * @returns {Promise<string>} Payment token
 */
const handleTokenCreation = async (mollie) => {
    log('Handling token creation');
    return await retrievePaymentToken(mollie);
};

/**
 * Handle form submission error
 * @param {jQuery} jQueryInstance - jQuery instance (for WooCommerce compatibility)
 * @param {Error} error - Error object
 * @param {Object} messages - Messages configuration
 * @param {jQuery} $form - Form jQuery object (for WooCommerce compatibility)
 * @param {jQuery} $document - Document jQuery object (for WooCommerce compatibility)
 */
const handleSubmissionError = (jQueryInstance, error, messages, $form, $document) => {
    log('Handling submission error:', error);
    const content = error?.message || messages.defaultErrorMessage;
    if (content) {
        printNotice(jQueryInstance, {content, type: 'error'});
    }

    // Use jQuery for WooCommerce compatibility
    $form.removeClass('processing').unblock();
    $document.trigger('checkout_error');
};

/**
 * Complete form submission with token
 * @param {string} token - Payment token
 * @param {Element} gatewayContainer - Gateway container element
 * @param {jQuery} $form - Form jQuery object (for WooCommerce compatibility)
 */
const completeFormSubmission = (token, gatewayContainer, $form) => {
    log('Completing form submission with token:', !!token);
    turnMollieComponentsSubmissionOff($form);

    if (token) {
        setTokenValueToField(token, tokenElementWithin(gatewayContainer));
    }

    // Use jQuery for form submission (WooCommerce compatibility)
    $form.submit();
};

/**
 * Handle form submission for Mollie payment
 * @param {Event} evt - Form submission event
 */
const submitForm = async (evt) => {
    log('Form submission handler called');
    const {jQuery, mollie, gateway, gatewayContainer, messages} = evt.data;
    const form = closestFormForElement(gatewayContainer);
    const $form = jQuery(form);
    const $document = jQuery(document.body);

    if (!isGatewaySelected(gateway)) {
        log('Gateway not selected, proceeding with normal submission');
        turnMollieComponentsSubmissionOff($form);
        $form.submit();
        return;
    }

    log('Preventing default form submission for Mollie processing');
    evt.preventDefault();
    evt.stopImmediatePropagation();

    try {
        const token = await handleTokenCreation(mollie);
        completeFormSubmission(token, gatewayContainer, $form);
    } catch (error) {
        handleSubmissionError(jQuery, error, messages, $form, $document);
    }
};

/* -------------------------------------------------------------------
   Component
   ---------------------------------------------------------------- */

/**
 * Get component element by name within container
 * @param {string} name - Component name
 * @param {Element} container - Container to search within
 * @returns {Element|null} Component element or null
 */
const componentElementByNameFromWithin = (name, container) => {
    if (!container) {
        log('Component element search failed: no container provided');
        return null;
    }
    log('Getting component element by name:', name);
    const component = container.querySelector(`.mollie-component--${name}`);
    log('Component element found:', !!component);
    return component;
};

/**
 * Create HTML element with specified attributes
 * @param {string} tagName - HTML tag name
 * @param {Object} attributes - Element attributes
 * @param {string} content - Element content
 * @returns {string} HTML string
 */
const createHtmlElement = (tagName, attributes, content) => {
    const attrString = Object.entries(attributes)
        .map(([key, value]) => `${key}="${value}"`)
        .join(' ');
    return `<${tagName} ${attrString}>${content}</${tagName}>`;
};

/**
 * Create component label element
 * @param {Element} container - Container to add element to
 * @param {Object} componentAttributes - Component attributes
 * @param {string} componentAttributes.label - Component label
 */
const createComponentLabelElementWithin = (container, {label}) => {
    log('Creating component label element:', label);
    const labelHtml = createHtmlElement('b', {class: 'mollie-component-label'}, label);
    container.insertAdjacentHTML('beforebegin', labelHtml);
};

/**
 * Create component error container
 * @param {Element} container - Container to add element to
 * @param {Object} componentAttributes - Component attributes
 * @param {string} componentAttributes.name - Component name
 */
const createComponentsErrorContainerWithin = (container, {name}) => {
    log('Creating component error container for:', name);
    const errorHtml = createHtmlElement('div', {role: 'alert', id: `${name}-errors`}, '');
    container.insertAdjacentHTML('afterend', errorHtml);
};

/**
 * Get or create component by name
 * @param {string} name - Component name
 * @param {Object} mollie - Mollie instance
 * @param {Object} settings - Component settings
 * @param {Map} mollieComponentsMap - Components map
 * @returns {Object} Mollie component
 */
const componentByName = (name, mollie, settings, mollieComponentsMap) => {
    log('Getting or creating component by name:', name);
    if (mollieComponentsMap.has(name)) {
        log('Component already exists in map');
        return mollieComponentsMap.get(name);
    }

    log('Creating new component');
    return mollie.createComponent(name, settings);
};

/**
 * Unmount all components from map
 * @param {Map} mollieComponentsMap - Components map
 */
const unmountComponents = (mollieComponentsMap) => {
    log('Unmounting all components, count:', mollieComponentsMap.size);
    mollieComponentsMap.forEach((component, name) => {
        log('Unmounting component:', name);
        component.unmount();
    });
};

/**
 * Create component container in DOM
 * @param {string} componentName - Component name
 * @param {Element} mollieComponentsContainer - Container for components
 */
const createComponentContainer = (componentName, mollieComponentsContainer) => {
    log('Creating component container for:', componentName);
    const containerDiv = document.createElement('div');
    containerDiv.id = componentName;
    mollieComponentsContainer.appendChild(containerDiv);
};

/**
 * Mount component to DOM
 * @param {Object} component - Mollie component
 * @param {string} componentName - Component name
 */
const mountComponentToDom = (component, componentName) => {
    log('Mounting component to DOM:', componentName);
    component.mount(`#${componentName}`);
};

/**
 * Setup component UI elements
 * @param {Element} currentComponentElement - Component DOM element
 * @param {Object} componentAttributes - Component attributes
 */
const setupComponentUi = (currentComponentElement, componentAttributes) => {
    log('Setting up component UI:', componentAttributes.name);
    createComponentLabelElementWithin(currentComponentElement, componentAttributes);
    createComponentsErrorContainerWithin(currentComponentElement, componentAttributes);
};

/**
 * Setup component error handling
 * @param {Object} component - Mollie component
 * @param {string} componentName - Component name
 */
const setupComponentErrorHandling = (component, componentName) => {
    log('Setting up error handling for component:', componentName);
    const componentError = document.querySelector(`#${componentName}-errors`);
    component.addEventListener('change', (event) => {
        if (event.error && event.touched) {
            log('Component error:', componentName, event.error);
            if (componentError) {
                componentError.textContent = event.error;
            }
        } else {
            log('Component error cleared:', componentName);
            if (componentError) {
                componentError.textContent = '';
            }
        }
    });
};

/**
 * Mount single component
 * @param {Object} mollie - Mollie instance
 * @param {Object} componentSettings - Component settings
 * @param {Object} componentAttributes - Component attributes
 * @param {Map} mollieComponentsMap - Components map
 * @param {Element} baseContainer - Base container element
 */
const mountComponent = (
    mollie,
    componentSettings,
    componentAttributes,
    mollieComponentsMap,
    baseContainer
) => {
    log('Mounting component:', componentAttributes.name);
    const {name: componentName} = componentAttributes;
    const component = componentByName(
        componentName,
        mollie,
        componentSettings,
        mollieComponentsMap
    );
    const mollieComponentsContainer = componentsContainerFromWithin(baseContainer);

    createComponentContainer(componentName, mollieComponentsContainer);
    mountComponentToDom(component, componentName);

    const currentComponentElement = componentElementByNameFromWithin(
        componentName,
        baseContainer
    );

    if (!currentComponentElement) {
        console.warn(
            `Component ${componentName} not found in the DOM. Probably had problem during mount.`
        );
        return;
    }

    setupComponentUi(currentComponentElement, componentAttributes);
    setupComponentErrorHandling(component, componentName);

    mollieComponentsMap.set(componentName, component);
    log('Component mounted successfully:', componentName);
};

/**
 * Mount multiple components
 * @param {Object} mollie - Mollie instance
 * @param {Object} componentSettings - Component settings
 * @param {Array} componentsAttributes - Array of component attributes
 * @param {Map} mollieComponentsMap - Components map
 * @param {Element} baseContainer - Base container element
 */
const mountComponents = (
    mollie,
    componentSettings,
    componentsAttributes,
    mollieComponentsMap,
    baseContainer
) => {
    log('Mounting multiple components, count:', componentsAttributes.length);
    componentsAttributes.forEach((componentAttributes) =>
        mountComponent(
            mollie,
            componentSettings,
            componentAttributes,
            mollieComponentsMap,
            baseContainer
        )
    );
};

/* -------------------------------------------------------------------
   Init
   ---------------------------------------------------------------- */

/**
 * Initialize Mollie payment components
 * @param {jQuery} jQueryInstance - jQuery instance (for WooCommerce compatibility)
 * @param {Object} mollie - Mollie instance
 * @param {Object} settings - Component settings
 * @param {Map} mollieComponentsMap - Components map
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
    log('Initializing Mollie components for gateways:', enabledGateways);
    unmountComponents(mollieComponentsMap);

    enabledGateways.forEach((gateway) => {
        log('Processing gateway:', gateway);
        const gatewayContainer = containerForGateway(gateway);
        const mollieComponentsContainer = componentsContainerFromWithin(gatewayContainer);
        const form = closestFormForElement(gatewayContainer);
        const $form = jQueryInstance(form);

        if (!gatewayContainer) {
            console.warn(
                `Cannot initialize Mollie Components for gateway ${gateway}.`
            );
            return;
        }

        if (!form) {
            console.warn('Cannot initialize Mollie Components, no form found.');
            return;
        }

        log('Setting up form listeners for gateway:', gateway);
        turnMollieComponentsSubmissionOff($form);
        cleanContainer(mollieComponentsContainer);
        createTokenFieldWithin(mollieComponentsContainer);

        mountComponents(
            mollie,
            componentsSettings[gateway],
            componentsAttributes,
            mollieComponentsMap,
            gatewayContainer
        );

        // Use jQuery for WooCommerce compatibility
        $form.on('checkout_place_order', returnFalse);
        $form.on('submit', null, {
            jQuery: jQueryInstance,
            mollie,
            gateway,
            gatewayContainer,
            messages,
        }, submitForm);

    });
};

/**
 * Create initialization handler function
 * @param {jQuery} jQueryInstance - jQuery instance (for WooCommerce compatibility)
 * @param {Object} mollie - Mollie instance
 * @param {Object} mollieComponentsSettings - Component settings
 * @param {Map} mollieComponentsMap - Components map
 * @returns {Function} Initialization handler function
 */
const createInitializationHandler = (jQueryInstance, mollie, mollieComponentsSettings, mollieComponentsMap) => {
    return function () {
        log('Initialization handler called');
        const copySettings = shallowCopy(mollieComponentsSettings);

        copySettings.enabledGateways = mollieComponentsSettings.enabledGateways.filter((gateway) => {
            const gatewayContainer = containerForGateway(gateway);
            if (!gatewayContainer) {
                log('Gateway container not found, enabling fallback for:', gateway);
                const $form = jQueryInstance('form[name="checkout"]');
                $form.on('checkout_place_order', returnTrue);
                return false;
            }
            return true;
        });

        if (copySettings.enabledGateways.length === 0) {
            log('No enabled gateways found, skipping initialization');
            return;
        }

        log('Proceeding with initialization for gateways:', copySettings.enabledGateways);
        initializeComponents(jQueryInstance, mollie, copySettings, mollieComponentsMap);
    };
};

((window) => {
    log('Starting Mollie Components initialization');
    const {Mollie, mollieComponentsSettings, jQuery} = window;

    if (isEmpty(mollieComponentsSettings) || !isFunction(Mollie)) {
        log('Missing dependencies, aborting initialization');
        return;
    }

    log('Dependencies found, proceeding with setup');
    let eventName = 'updated_checkout';
    const mollieComponentsMap = new Map();
    const $document = jQuery(document);
    const {merchantProfileId, options, isCheckoutPayPage} = mollieComponentsSettings;
    const mollie = new Mollie(merchantProfileId, options);

    if (isCheckoutPayPage) {
        log('Checkout pay page detected, using payment_method_selected event');
        eventName = 'payment_method_selected';
        $document.on(eventName, () => {
            log('Payment method selected event triggered');
            initializeComponents(
                jQuery,
                mollie,
                mollieComponentsSettings,
                mollieComponentsMap
            );
        });
        return;
    }

    log('Regular checkout page detected, setting up event handlers');
    const initHandler = createInitializationHandler(jQuery, mollie, mollieComponentsSettings, mollieComponentsMap);
    $document.on(eventName, initHandler);
    $document.on('update_checkout', initHandler);
    log('Event handlers registered for:', eventName, 'update_checkout');
})(window);
