const SELECTOR_TOKEN_ELEMENT = '.cardToken';
const SELECTOR_MOLLIE_COMPONENTS_CONTAINER = '.mollie-components';
const SELECTOR_FORM = 'form';
const SELECTOR_MOLLIE_GATEWAY_CONTAINER = '.wc_payment_methods';
const SELECTOR_MOLLIE_NOTICE_CONTAINER = '#mollie-notice';
const SELECTOR_BLOCKS_SUBMIT_BUTTON = '.wc-block-components-checkout-place-order-button';

const $ = jQuery;
const returnFalse = () => false;
const returnTrue = () => true;

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
    const $gateway = $(container).find(SELECTOR_MOLLIE_GATEWAY_CONTAINER);
    return $gateway.length ? $gateway.get(0) : null;
};

/**
 * Get container for specific gateway
 * @param {string} gateway - Gateway identifier
 * @param {Element} [container=document] - Container to search within
 * @returns {Element|null} Gateway container element or null
 */
const containerForGateway = (gateway, container = document) => {
    const $gatewayContainer = $(container).find(`.payment_method_mollie_wc_gateway_${gateway}`);
    return $gatewayContainer.length ? $gatewayContainer.get(0) : null;
};

/**
 * Get notice container element
 * @param {Element} [container=document] - Container to search within
 * @returns {Element|null} Notice container element or null
 */
const noticeContainer = (container = document) => {
    const $notice = $(container).find(SELECTOR_MOLLIE_NOTICE_CONTAINER);
    return $notice.length ? $notice.get(0) : null;
};

/**
 * Get components container within given container
 * @param {Element} container - Container to search within
 * @returns {Element|null} Components container element or null
 */
const componentsContainerFromWithin = (container) => {
    if (!container) return null;
    const $components = $(container).find(SELECTOR_MOLLIE_COMPONENTS_CONTAINER);
    return $components.length ? $components.get(0) : null;
};

/**
 * Clear container content
 * @param {Element} container - Container to clear
 */
const cleanContainer = (container) => {
    if (!container) return;
    $(container).empty();
};

/* -------------------------------------------------------------------
   Notice
   ---------------------------------------------------------------- */

/**
 * Create notice DOM element
 * @param {string} content - Notice content
 * @param {string} type - Notice type (error, success, etc.)
 * @returns {jQuery} Notice element
 */
const renderNoticeElement = (content, type) =>
    $('<div>')
        .attr('id', 'mollie-notice')
        .addClass(`woocommerce-${type}`)
        .html(content);

/**
 * Display notice to user
 * @param {jQuery} jQueryInstance - jQuery instance
 * @param {Object} noticeData - Notice configuration
 * @param {string} noticeData.content - Notice content
 * @param {string} noticeData.type - Notice type
 */
const printNotice = (jQueryInstance, noticeData) => {
    const container = gatewayContainer();
    const form = container ? closestFormForElement(container) : null;
    const formContainer = form ? $(form).parent().get(0) : null;
    const $mollieNotice = $(SELECTOR_MOLLIE_NOTICE_CONTAINER);
    const $renderedNotice = renderNoticeElement(noticeData.content, noticeData.type);

    $mollieNotice.remove();

    if (!formContainer) {
        alert(noticeData.content);
        return;
    }

    $(formContainer).before($renderedNotice);
    scrollToNotice(jQueryInstance);
};

/**
 * Scroll to notice element
 * @param {jQuery} jQueryInstance - jQuery instance
 */
const scrollToNotice = (jQueryInstance) => {
    const scrollToElement = noticeContainer() || gatewayContainer();

    if (scrollToElement) {
        jQueryInstance.scroll_to_notices(jQueryInstance(scrollToElement));
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
    $(container).append('<input type="hidden" name="cardToken" class="cardToken" value="" />');
};

/**
 * Get token element within container
 * @param {Element} container - Container to search within
 * @returns {Element|null} Token element or null
 */
const tokenElementWithin = (container) => {
    if (!container) return null;
    const $token = $(container).find(SELECTOR_TOKEN_ELEMENT);
    return $token.length ? $token.get(0) : null;
};

/**
 * Retrieve payment token from Mollie
 * @param {Object} mollie - Mollie instance
 * @returns {Promise<string>} Payment token
 * @throws {Error} When token creation fails
 */
const retrievePaymentToken = async (mollie) => {
    const {token, error} = await mollie.createToken(SELECTOR_TOKEN_ELEMENT);

    if (error) {
        throw new Error(error.message || '');
    }

    return token;
};

/**
 * Set token value to form field
 * @param {string} token - Payment token
 * @param {Element} tokenFieldElement - Token field element
 */
const setTokenValueToField = (token, tokenFieldElement) => {
    if (!tokenFieldElement) return;
    $(tokenFieldElement).val(token).attr('value', token);
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
    if (!element) return null;
    const $form = $(element).closest(SELECTOR_FORM);
    return $form.length ? $form.get(0) : null;
};

/**
 * Remove Mollie form submission listeners
 * @param {jQuery} $form - Form jQuery object
 */
const turnMollieComponentsSubmissionOff = ($form) => {
    $form.off('checkout_place_order', returnFalse);
    $form.off('submit', submitForm);
};

/**
 * Check if specific gateway is selected
 * @param {string} gateway - Gateway identifier
 * @returns {boolean} True if gateway is selected
 */
const isGatewaySelected = (gateway) => {
    const gatewayContainer = containerForGateway(gateway);
    if (!gatewayContainer) return false;

    const $gatewayInput = $(gatewayContainer).find(`#payment_method_mollie_wc_gateway_${gateway}`);
    return $gatewayInput.length ? $gatewayInput.is(':checked') : false;
};

/**
 * Handle payment token creation
 * @param {Object} mollie - Mollie instance
 * @returns {Promise<string>} Payment token
 */
const handleTokenCreation = async (mollie) => {
    return await retrievePaymentToken(mollie);
};

/**
 * Handle form submission error
 * @param {jQuery} jQueryInstance - jQuery instance
 * @param {Error} error - Error object
 * @param {Object} messages - Messages configuration
 * @param {jQuery} $form - Form jQuery object
 * @param {jQuery} $document - Document jQuery object
 */
const handleSubmissionError = (jQueryInstance, error, messages, $form, $document) => {
    const content = error?.message || messages.defaultErrorMessage;
    if (content) {
        printNotice(jQueryInstance, {content, type: 'error'});
    }

    $form.removeClass('processing').unblock();
    $document.trigger('checkout_error');
};

/**
 * Complete form submission with token
 * @param {string} token - Payment token
 * @param {Element} gatewayContainer - Gateway container element
 * @param {jQuery} $form - Form jQuery object
 */
const completeFormSubmission = (token, gatewayContainer, $form) => {
    turnMollieComponentsSubmissionOff($form);

    if (token) {
        setTokenValueToField(token, tokenElementWithin(gatewayContainer));
    }

    $form.submit();
};

/**
 * Handle form submission for Mollie payment
 * @param {Event} evt - Form submission event
 */
const submitForm = async (evt) => {
    const {jQuery, mollie, gateway, gatewayContainer, messages} = evt.data;
    const form = closestFormForElement(gatewayContainer);
    const $form = jQuery(form);
    const $document = jQuery(document.body);

    if (!isGatewaySelected(gateway)) {
        turnMollieComponentsSubmissionOff($form);
        $form.submit();
        return;
    }

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
    if (!container) return null;
    const $component = $(container).find(`.mollie-component--${name}`);
    return $component.length ? $component.get(0) : null;
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
    const labelHtml = createHtmlElement('b', {class: 'mollie-component-label'}, label);
    $(container).before(labelHtml);
};

/**
 * Create component error container
 * @param {Element} container - Container to add element to
 * @param {Object} componentAttributes - Component attributes
 * @param {string} componentAttributes.name - Component name
 */
const createComponentsErrorContainerWithin = (container, {name}) => {
    const errorHtml = createHtmlElement('div', {role: 'alert', id: `${name}-errors`}, '');
    $(container).after(errorHtml);
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
    if (mollieComponentsMap.has(name)) {
        return mollieComponentsMap.get(name);
    }

    return mollie.createComponent(name, settings);
};

/**
 * Unmount all components from map
 * @param {Map} mollieComponentsMap - Components map
 */
const unmountComponents = (mollieComponentsMap) => {
    mollieComponentsMap.forEach((component) => component.unmount());
};

/**
 * Create component container in DOM
 * @param {string} componentName - Component name
 * @param {Element} mollieComponentsContainer - Container for components
 */
const createComponentContainer = (componentName, mollieComponentsContainer) => {
    $(mollieComponentsContainer).append(`<div id="${componentName}"></div>`);
};

/**
 * Mount component to DOM
 * @param {Object} component - Mollie component
 * @param {string} componentName - Component name
 */
const mountComponentToDom = (component, componentName) => {
    component.mount(`#${componentName}`);
};

/**
 * Setup component UI elements
 * @param {Element} currentComponentElement - Component DOM element
 * @param {Object} componentAttributes - Component attributes
 */
const setupComponentUi = (currentComponentElement, componentAttributes) => {
    createComponentLabelElementWithin(currentComponentElement, componentAttributes);
    createComponentsErrorContainerWithin(currentComponentElement, componentAttributes);
};

/**
 * Setup component error handling
 * @param {Object} component - Mollie component
 * @param {string} componentName - Component name
 */
const setupComponentErrorHandling = (component, componentName) => {
    const $componentError = $(`#${componentName}-errors`);
    component.addEventListener('change', (event) => {
        if (event.error && event.touched) {
            $componentError.text(event.error);
        } else {
            $componentError.text('');
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
   Block Submit Button Management
   ---------------------------------------------------------------- */

/**
 * Setup event listeners for block submit buttons
 * @param {jQuery} jQueryInstance - jQuery instance
 * @param {Object} mollie - Mollie instance
 * @param {string} gateway - Gateway identifier
 * @param {Element} gatewayContainer - Gateway container element
 * @param {Object} messages - Messages configuration
 */
const setupBlockSubmitButtonListener = (jQueryInstance, mollie, gateway, gatewayContainer, messages) => {
    const eventData = {
        jQuery: jQueryInstance,
        mollie,
        gateway,
        gatewayContainer,
        messages
    };

    $(document).off('click', SELECTOR_BLOCKS_SUBMIT_BUTTON, submitForm);
    $(document).on('click', SELECTOR_BLOCKS_SUBMIT_BUTTON, eventData, submitForm);

    const $existingButton = $(SELECTOR_BLOCKS_SUBMIT_BUTTON);
    if ($existingButton.length) {
        $existingButton.off('click', submitForm);
        $existingButton.on('click', eventData, submitForm);
    }
};

/* -------------------------------------------------------------------
   Init
   ---------------------------------------------------------------- */

/**
 * Initialize Mollie payment components
 * @param {jQuery} jQueryInstance - jQuery instance
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
    unmountComponents(mollieComponentsMap);

    enabledGateways.forEach((gateway) => {
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

        $form.on('checkout_place_order', returnFalse);
        $form.on('submit', null, {
            jQuery: jQueryInstance,
            mollie,
            gateway,
            gatewayContainer,
            messages,
        }, submitForm);

        setupBlockSubmitButtonListener(jQueryInstance, mollie, gateway, gatewayContainer, messages);
    });
};

/**
 * Create initialization handler function
 * @param {jQuery} jQueryInstance - jQuery instance
 * @param {Object} mollie - Mollie instance
 * @param {Object} mollieComponentsSettings - Component settings
 * @param {Map} mollieComponentsMap - Components map
 * @returns {Function} Initialization handler function
 */
const createInitializationHandler = (jQueryInstance, mollie, mollieComponentsSettings, mollieComponentsMap) => {
    return function () {
        const copySettings = shallowCopy(mollieComponentsSettings);

        copySettings.enabledGateways = mollieComponentsSettings.enabledGateways.filter((gateway) => {
            const gatewayContainer = containerForGateway(gateway);
            if (!gatewayContainer) {
                const $form = jQueryInstance('form[name="checkout"]');
                $form.on('checkout_place_order', returnTrue);
                return false;
            }
            return true;
        });

        if (copySettings.enabledGateways.length === 0) {
            return;
        }

        initializeComponents(jQueryInstance, mollie, copySettings, mollieComponentsMap);
    };
};

((window) => {
    const {Mollie, mollieComponentsSettings, jQuery} = window;
    if (isEmpty(mollieComponentsSettings) || !isFunction(Mollie)) {
        return;
    }

    let eventName = 'updated_checkout';
    const mollieComponentsMap = new Map();
    const $document = jQuery(document);
    const {merchantProfileId, options, isCheckoutPayPage} = mollieComponentsSettings;
    const mollie = new Mollie(merchantProfileId, options);

    if (isCheckoutPayPage) {
        eventName = 'payment_method_selected';
        $document.on(eventName, () =>
            initializeComponents(
                jQuery,
                mollie,
                mollieComponentsSettings,
                mollieComponentsMap
            )
        );
        return;
    }

    const initHandler = createInitializationHandler(jQuery, mollie, mollieComponentsSettings, mollieComponentsMap);
    $document.on(eventName, initHandler);
    $document.on('update_checkout', initHandler);
})(window);
