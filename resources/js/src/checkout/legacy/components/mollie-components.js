const SELECTOR_TOKEN_ELEMENT = '.cardToken';
const SELECTOR_MOLLIE_COMPONENTS_CONTAINER = '.mollie-components';
const SELECTOR_FORM = 'form';
const SELECTOR_MOLLIE_GATEWAY_CONTAINER = '.wc_payment_methods';
const SELECTOR_MOLLIE_NOTICE_CONTAINER = '#mollie-notice';
const SELECTOR_BLOCKS_SUBMIT_BUTTON = '.wc-block-components-checkout-place-order-button';

const DOMCache = {
    document: null,
    body: null,

    init() {
        this.document = document;
        this.body = document.body;
        return this;
    },

    clear() {
        // Clear cache when DOM might have changed significantly
        this.document = document;
        this.body = document.body;
    }
};

// Initialize cache
DOMCache.init();

function returnFalse() {
    return false;
}

function returnTrue() {
    return true;
}

function shallowCopy(obj) {
    if (Array.isArray(obj)) {
        return [...obj];
    }
    if (obj && typeof obj === 'object') {
        return {...obj};
    }
    return obj;
}

/* -------------------------------------------------------------------
   Containers
   ---------------------------------------------------------------- */
function gatewayContainer(container = DOMCache.document) {
    return container?.querySelector(SELECTOR_MOLLIE_GATEWAY_CONTAINER) ?? null;
}

function containerForGateway(gateway, container = DOMCache.document) {
    return container?.querySelector(
        `.payment_method_mollie_wc_gateway_${gateway}`
    ) ?? null;
}

function noticeContainer(container = DOMCache.document) {
    return container?.querySelector(SELECTOR_MOLLIE_NOTICE_CONTAINER) ?? null;
}

function componentsContainerFromWithin(container) {
    return container?.querySelector(SELECTOR_MOLLIE_COMPONENTS_CONTAINER) ?? null;
}

function cleanContainer(container) {
    if (!container) {
        return;
    }
    container.innerText = '';
}

/* -------------------------------------------------------------------
   Notice
   ---------------------------------------------------------------- */
function renderNotice({content, type}) {
    return `
      <div id="mollie-notice" class="woocommerce-${type}">
        ${content}
      </div>
    `;
}

function printNotice(jQuery, noticeData) {
    const container = gatewayContainer();
    const formContainer = closestFormForElement(container)?.parentNode ?? null;
    const mollieNotice = noticeContainer();
    const renderedNotice = renderNotice(noticeData);

    mollieNotice?.remove();

    if (!formContainer) {
        alert(noticeData.content);
        return;
    }

    formContainer.insertAdjacentHTML('beforebegin', renderedNotice);
    scrollToNotice(jQuery);
}

function scrollToNotice(jQuery) {
    let scrollToElement = noticeContainer() || gatewayContainer();

    if (scrollToElement) {
        jQuery.scroll_to_notices(jQuery(scrollToElement));
    }
}

/* -------------------------------------------------------------------
   Token
   ---------------------------------------------------------------- */
function createTokenFieldWithin(container) {
    container.insertAdjacentHTML(
        'beforeend',
        '<input type="hidden" name="cardToken" class="cardToken" value="" />'
    );
}

function tokenElementWithin(container) {
    return container?.querySelector(SELECTOR_TOKEN_ELEMENT) ?? null;
}

async function retrievePaymentToken(mollie) {
    const {token, error} = await mollie.createToken(SELECTOR_TOKEN_ELEMENT);

    if (error) {
        throw new Error(error.message || '');
    }

    return token;
}

function setTokenValueToField(token, tokenFieldElement) {
    if (!tokenFieldElement) {
        return;
    }

    tokenFieldElement.value = token;
    tokenFieldElement.setAttribute('value', token);
}

/* -------------------------------------------------------------------
   Form
   ---------------------------------------------------------------- */
function closestFormForElement(element) {
    return element?.closest(SELECTOR_FORM) ?? null;
}

function turnMollieComponentsSubmissionOff($form) {
    $form.off('checkout_place_order', returnFalse);
    $form.off('submit', submitForm);
}

function turnBlockListenerOff(target) {
    target.off('click', submitForm);
}

function isGatewaySelected(gateway) {
    const gatewayContainer = containerForGateway(gateway);
    const gatewayInput = gatewayContainer?.querySelector(
        `#payment_method_mollie_wc_gateway_${gateway}`
    ) ?? null;

    return !!gatewayInput?.checked;
}

async function submitForm(evt) {
    let token = '';
    const {jQuery, mollie, gateway, gatewayContainer, messages} = evt.data;
    const form = closestFormForElement(gatewayContainer);
    const $form = jQuery(form);
    const $document = jQuery(DOMCache.body);

    if (!isGatewaySelected(gateway)) {
        // Let other gateway submit the form
        turnMollieComponentsSubmissionOff($form);
        $form.submit();
        return;
    }

    evt.preventDefault();
    evt.stopImmediatePropagation();

    try {
        token = await retrievePaymentToken(mollie);
    } catch (error) {
        const content = error?.message || messages.defaultErrorMessage;
        if (content) {
            printNotice(jQuery, {content, type: 'error'});
        }

        $form.removeClass('processing').unblock();
        $document.trigger('checkout_error');
        return;
    }

    turnMollieComponentsSubmissionOff($form);

    if (token) {
        setTokenValueToField(token, tokenElementWithin(gatewayContainer));
    }

    $form.submit();
}

/* -------------------------------------------------------------------
   Component
   ---------------------------------------------------------------- */
function componentElementByNameFromWithin(name, container) {
    return container?.querySelector(`.mollie-component--${name}`) ?? null;
}

function createComponentLabelElementWithin(container, {label}) {
    container.insertAdjacentHTML(
        'beforebegin',
        `<b class="mollie-component-label">${label}</b>`
    );
}

function createComponentsErrorContainerWithin(container, {name}) {
    container.insertAdjacentHTML(
        'afterend',
        `<div role="alert" id="${name}-errors"></div>`
    );
}

function componentByName(name, mollie, settings, mollieComponentsMap) {
    if (mollieComponentsMap.has(name)) {
        return mollieComponentsMap.get(name);
    }

    const component = mollie.createComponent(name, settings);
    return component;
}

function unmountComponents(mollieComponentsMap) {
    mollieComponentsMap.forEach((component) => component.unmount());
}

function mountComponent(
    mollie,
    componentSettings,
    componentAttributes,
    mollieComponentsMap,
    baseContainer
) {
    const {name: componentName} = componentAttributes;
    const component = componentByName(
        componentName,
        mollie,
        componentSettings,
        mollieComponentsMap
    );
    const mollieComponentsContainer = componentsContainerFromWithin(baseContainer);

    mollieComponentsContainer.insertAdjacentHTML(
        'beforeend',
        `<div id="${componentName}"></div>`
    );
    component.mount(`#${componentName}`);

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

    createComponentLabelElementWithin(currentComponentElement, componentAttributes);
    createComponentsErrorContainerWithin(currentComponentElement, componentAttributes);

    const componentError = DOMCache.document.querySelector(`#${componentName}-errors`);
    component.addEventListener('change', (event) => {
        if (event.error && event.touched) {
            componentError.textContent = event.error;
        } else {
            componentError.textContent = '';
        }
    });

    if (!mollieComponentsMap.has(componentName)) {
        mollieComponentsMap.set(componentName, component);
    }
}

function mountComponents(
    mollie,
    componentSettings,
    componentsAttributes,
    mollieComponentsMap,
    baseContainer
) {
    componentsAttributes.forEach((componentAttributes) =>
        mountComponent(
            mollie,
            componentSettings,
            componentAttributes,
            mollieComponentsMap,
            baseContainer
        )
    );
}

/* -------------------------------------------------------------------
   Block Submit Button Observer
   ---------------------------------------------------------------- */
function createBlockSubmitButtonObserver(jQuery, mollie, gateway, gatewayContainer, messages) {
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    const submitButton = node.matches?.(SELECTOR_BLOCKS_SUBMIT_BUTTON)
                        ? node
                        : node.querySelector?.(SELECTOR_BLOCKS_SUBMIT_BUTTON);

                    if (submitButton) {
                        jQuery(submitButton).off('click', submitForm); // Remove any existing listeners
                        jQuery(submitButton).on('click', {
                            jQuery,
                            mollie,
                            gateway,
                            gatewayContainer,
                            messages,
                        }, submitForm);
                    }
                }
            });
        });
    });

    observer.observe(DOMCache.body, {
        childList: true,
        subtree: true
    });

    const existingButton = DOMCache.document.querySelector(SELECTOR_BLOCKS_SUBMIT_BUTTON);
    if (existingButton) {
        jQuery(existingButton).off('click', submitForm);
        jQuery(existingButton).on('click', {
            jQuery,
            mollie,
            gateway,
            gatewayContainer,
            messages,
        }, submitForm);
    }

    return observer;
}

/* -------------------------------------------------------------------
   Init
   ---------------------------------------------------------------- */

/**
 * Unmount and Mount the components if them already exists, create them if it's the first time
 * the components are created.
 */
function initializeComponents(
    jQuery,
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
) {
    // Clear DOM cache as DOM might have been updated
    DOMCache.clear();

    /*
     * WooCommerce updates the DOM when something on checkout page happens.
     * Mollie does not allow to keep a copy of the mounted components.
     * We have to mount every time the components but we cannot recreate them.
     */
    unmountComponents(mollieComponentsMap);

    // Store observers to clean them up later
    const observers = [];

    enabledGateways.forEach((gateway) => {
        const gatewayContainer = containerForGateway(gateway);
        const mollieComponentsContainer = componentsContainerFromWithin(gatewayContainer);
        const form = closestFormForElement(gatewayContainer);
        const $form = jQuery(form);

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

        // Remove old listener before add new ones or form will not be submitted
        turnMollieComponentsSubmissionOff($form);

        /*
         * Clean container for mollie components because we do not know in which context we may need
         * to create components.
         */
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
            jQuery,
            mollie,
            gateway,
            gatewayContainer,
            messages,
        }, submitForm);

        const observer = createBlockSubmitButtonObserver(jQuery, mollie, gateway, gatewayContainer, messages);
        observers.push(observer);
    });

    // Store observers reference for cleanup if needed
    if (typeof window.mollieObservers === 'undefined') {
        window.mollieObservers = [];
    }
    window.mollieObservers.push(...observers);
}

(function ({_, Mollie, mollieComponentsSettings, jQuery}) {
    if (_.isEmpty(mollieComponentsSettings) || !_.isFunction(Mollie)) {
        return;
    }

    let eventName = 'updated_checkout';
    const mollieComponentsMap = new Map();
    const $document = jQuery(DOMCache.document);
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

    function checkInit() {
        return function () {
            const copySettings = shallowCopy(mollieComponentsSettings);

            // Create new array for enabled gateways to avoid mutating original
            copySettings.enabledGateways = [...mollieComponentsSettings.enabledGateways];

            // Filter out non-existent gateways
            copySettings.enabledGateways = copySettings.enabledGateways.filter((gateway) => {
                const gatewayContainer = containerForGateway(gateway);
                if (!gatewayContainer) {
                    const $form = jQuery('form[name="checkout"]');
                    $form.on('checkout_place_order', returnTrue);
                    return false;
                }
                return true;
            });

            if (copySettings.enabledGateways.length === 0) {
                return;
            }

            initializeComponents(jQuery, mollie, copySettings, mollieComponentsMap);
        };
    }

    $document.on(eventName, checkInit());
    $document.on('update_checkout', checkInit());
})(window);
