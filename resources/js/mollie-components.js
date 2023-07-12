const SELECTOR_TOKEN_ELEMENT = '.cardToken'
const SELECTOR_MOLLIE_COMPONENTS_CONTAINER = '.mollie-components'
const SELECTOR_FORM = 'form'
const SELECTOR_MOLLIE_GATEWAY_CONTAINER = '.wc_payment_methods'
const SELECTOR_MOLLIE_GATEWAY_BLOCK_CONTAINER = '.wc-block-components-radio-control'

const SELECTOR_MOLLIE_NOTICE_CONTAINER = '#mollie-notice'

function returnFalse ()
{
  return false
}
function returnTrue ()
{
    return true
}

/* -------------------------------------------------------------------
   Containers
   ---------------------------------------------------------------- */
function gatewayContainer (container)
{
    let checkoutContainer = container ? container.querySelector(SELECTOR_MOLLIE_GATEWAY_CONTAINER) : null
    let blockContainer = container ? container.querySelector(SELECTOR_MOLLIE_GATEWAY_BLOCK_CONTAINER) : null
  return checkoutContainer ? checkoutContainer : blockContainer
}

function containerForGateway (gateway, container)
{
    if(!container) {
        return null
    }
    let parentInBlock = container.querySelector('label[for="radio-control-wc-payment-method-options-mollie_wc_gateway_creditcard"]');
    parentInBlock = parentInBlock ? parentInBlock.closest('div') : null
    const parentInClassic = container.querySelector(`.payment_method_mollie_wc_gateway_${gateway}`)
    return parentInBlock || parentInClassic
}

function noticeContainer (container)
{
  return container ? container.querySelector(SELECTOR_MOLLIE_NOTICE_CONTAINER) : null
}

function componentsContainerFromWithin (container)
{
  return container ? container.querySelector(SELECTOR_MOLLIE_COMPONENTS_CONTAINER) : null
}

function cleanContainer (container)
{
  if (!container) {
    return
  }

  container.innerText = ''
}

/* -------------------------------------------------------------------
   Notice
   ---------------------------------------------------------------- */
function renderNotice ({ content, type })
{
  return `
      <div id="mollie-notice" class="woocommerce-${type}">
        ${content}
      </div>
    `
}

function printNotice (jQuery, noticeData)
{
  const container = gatewayContainer(document)
  const formContainer = closestFormForElement(container).parentNode || null
  const mollieNotice = noticeContainer(document)
  const renderedNotice = renderNotice(noticeData)

  mollieNotice && mollieNotice.remove()

  if (!formContainer) {
    alert(noticeData.content)
    return
  }

  formContainer.insertAdjacentHTML('beforebegin', renderedNotice)

  scrollToNotice(jQuery)
}

function scrollToNotice (jQuery)
{
  var scrollToElement = noticeContainer(document)

  if (!scrollToElement) {
    scrollToElement = gatewayContainer(document)
  }

  jQuery.scroll_to_notices(jQuery(scrollToElement))
}

/* -------------------------------------------------------------------
   Token
   ---------------------------------------------------------------- */
function createTokenFieldWithin (container)
{
  container.insertAdjacentHTML(
    'beforeend',
    '<input type="hidden" name="cardToken" class="cardToken" value="" />'
  )
}

function tokenElementWithin (container)
{
  return container.querySelector(SELECTOR_TOKEN_ELEMENT)
}

async function retrievePaymentToken (mollie)
{
  const { token, error } = await mollie.createToken(SELECTOR_TOKEN_ELEMENT)
  if (error) {
    throw new Error(error.message || '')
  }

  return token
}

function setTokenValueToField (token, tokenFieldElement)
{
  if (!tokenFieldElement) {
    return
  }

  tokenFieldElement.value = token
  tokenFieldElement.setAttribute('value', token)
}

/* -------------------------------------------------------------------
   Form
   ---------------------------------------------------------------- */
function closestFormForElement (element)
{
  return element ? element.closest(SELECTOR_FORM) : null
}

function turnMollieComponentsSubmissionOff ($form)
{
  $form.off('checkout_place_order', returnFalse)
  $form.off('submit', submitForm)
}

function turnBlockListenerOff (target)
{
    target.off('click', submitForm)
}

function isGatewaySelected (gateway)
{
  const gatewayContainer = containerForGateway(gateway, document)
  const gatewayInput = gatewayContainer
    ? gatewayContainer.querySelector(`#payment_method_mollie_wc_gateway_${gateway}`)
    : null
    //if we are in blocks then the input is different
    const gatewayBlockInput = document.getElementById("radio-control-wc-payment-method-options-mollie_wc_gateway_creditcard")
    if(gatewayBlockInput){
        return gatewayBlockInput.checked || false
    }

  if (!gatewayInput) {
    return false
  }

  return gatewayInput.checked || false
}

async function submitForm (evt)
{
  let token = ''
  const { jQuery, mollie, gateway, gatewayContainer, messages } = evt.data
  const form = closestFormForElement(gatewayContainer)
  const $form = jQuery(form)
  const $document = jQuery(document.body)

  if (!isGatewaySelected(gateway)) {
    // Let other gateway to submit the form
    turnMollieComponentsSubmissionOff($form)
    $form.submit()
    return
  }

  evt.preventDefault()
  evt.stopImmediatePropagation()

  try {
    token = await retrievePaymentToken(mollie)
  } catch (error) {
    const content = { message = messages.defaultErrorMessage } = error
    content && printNotice(jQuery, { content, type: 'error' })

    $form.removeClass('processing').unblock()
    $document.trigger('checkout_error')
    return
  }

  turnMollieComponentsSubmissionOff($form)

  token && setTokenValueToField(token, tokenElementWithin(gatewayContainer))
    if(evt.type === 'click'){
        turnBlockListenerOff(jQuery(evt.target))
        let readyToSubmitBlock = new Event("mollie_components_ready_to_submit", {bubbles: true});
        document.documentElement.dispatchEvent(readyToSubmitBlock);
        return
    }

  $form.submit()
}

/* -------------------------------------------------------------------
   Component
   ---------------------------------------------------------------- */
function componentElementByNameFromWithin (name, container)
{
  return container ? container.querySelector(`.mollie-component--${name}`) : null
}

function createComponentLabelElementWithin (container, { label })
{
  container.insertAdjacentHTML(
    'beforebegin',
    `<b class="mollie-component-label">${label}</b>`
  )
}

function createComponentsErrorContainerWithin (container, { name })
{
  container.insertAdjacentHTML(
    'afterend',
    `<div role="alert" id="${name}-errors"></div>`
  )
}

function componentByName (name, mollie, settings, mollieComponentsMap)
{
  let component

  if (mollieComponentsMap.has(name)) {
    component = mollieComponentsMap.get(name)
  }
  if (!component) {
    component = mollie.createComponent(name, settings)
  }

  return component
}

function unmountComponents (mollieComponentsMap)
{
  mollieComponentsMap.forEach(component => component.unmount())
}

function mountComponent (
  mollie,
  componentSettings,
  componentAttributes,
  mollieComponentsMap,
  baseContainer
)
{
  const { name: componentName } = componentAttributes
  const component = componentByName(componentName, mollie, componentSettings, mollieComponentsMap)
  const mollieComponentsContainer = componentsContainerFromWithin(baseContainer)

  mollieComponentsContainer.insertAdjacentHTML('beforeend', `<div id="${componentName}"></div>`)
  component.mount(`#${componentName}`)

  const currentComponentElement = componentElementByNameFromWithin(componentName, baseContainer)
  if (!currentComponentElement) {
    console.warn(`Component ${componentName} not found in the DOM. Probably had problem during mount.`)
    return
  }

  createComponentLabelElementWithin(currentComponentElement, componentAttributes)
  createComponentsErrorContainerWithin(currentComponentElement, componentAttributes)
  let componentError = document.querySelector('#' + componentName + '-errors')
  component.addEventListener('change', event => {
      if (event.error && event.touched) {
          componentError.textContent = event.error
      } else {
          componentError.textContent = ''
      }
  })

  !mollieComponentsMap.has(componentName) && mollieComponentsMap.set(componentName, component)
}

function mountComponents (
  mollie,
  componentSettings,
  componentsAttributes,
  mollieComponentsMap,
  baseContainer
)
{
  componentsAttributes.forEach(
    componentAttributes => mountComponent(
      mollie,
      componentSettings,
      componentAttributes,
      mollieComponentsMap,
      baseContainer
    )
  )
}

/* -------------------------------------------------------------------
   Init
   ---------------------------------------------------------------- */

/**
 * Unmount and Mount the components if them already exists, create them if it's the first time
 * the components are created.
 */
function initializeComponents (
  jQuery,
  mollie,
  {
    options,
    merchantProfileId,
    componentsSettings,
    componentsAttributes,
    enabledGateways,
    messages
  },
  mollieComponentsMap
)
{

  /*
   * WooCommerce update the DOM when something on checkout page happen.
   * Mollie does not allow to keep a copy of the mounted components.
   *
   * We have to mount every time the components but we cannot recreate them.
   * But only unmount if they exists.
   */
    if (jQuery("#cardHolder").length > 0) {
        unmountComponents(mollieComponentsMap)
    }

  enabledGateways.forEach(gateway =>
  {
    const gatewayContainer = containerForGateway(gateway, document)
    const mollieComponentsContainer = componentsContainerFromWithin(gatewayContainer)
    const form = closestFormForElement(gatewayContainer)
    const $form = jQuery(form)

    if (!gatewayContainer) {
      console.warn(`Cannot initialize Mollie Components for gateway ${gateway}.`)
      return
    }

    if (!form) {
      console.warn('Cannot initialize Mollie Components, no form found.')
      return
    }

    // Remove old listener before add new ones or form will not be submitted
    turnMollieComponentsSubmissionOff($form)

    /*
     * Clean container for mollie components because we do not know in which context we may need
     * to create components.
     */
    cleanContainer(mollieComponentsContainer)
    createTokenFieldWithin(mollieComponentsContainer)

    mountComponents(
      mollie,
      componentsSettings[gateway],
      componentsAttributes,
      mollieComponentsMap,
      gatewayContainer
    )

    $form.on('checkout_place_order', returnFalse)
    $form.on(
      'submit',
      null,
      {
        jQuery,
        mollie,
        gateway,
        gatewayContainer,
        messages
      },
      submitForm
    )
      //waiting for the blocks to load, this should receive an event to look for the button instead
      setTimeout(function (){
          submitButton = jQuery(".wc-block-components-checkout-place-order-button")

          jQuery(submitButton).click(
              {
                  jQuery,
                  mollie,
                  gateway,
                  gatewayContainer,
                  messages
              },
              submitForm
          )
      },500)
  })
}

(
    function ({ _, Mollie, mollieComponentsSettings, jQuery })
    {
        if (_.isEmpty(mollieComponentsSettings) || !_.isFunction(Mollie)) {
            return
        }

        let eventName = 'updated_checkout'
        const mollieComponentsMap = new Map()
        const $document = jQuery(document)
        const { merchantProfileId, options, isCheckoutPayPage } = mollieComponentsSettings
        const mollie = new Mollie(merchantProfileId, options)


        if (isCheckoutPayPage) {
            eventName = 'payment_method_selected'
            $document.on(
                eventName,
                () => initializeComponents(
                    jQuery,
                    mollie,
                    mollieComponentsSettings,
                    mollieComponentsMap
                )
            )
            return
        }

        document.addEventListener("mollie_creditcard_component_selected", function(event) {
            setTimeout(function(){
                initializeComponents(
                    jQuery,
                    mollie,
                    mollieComponentsSettings,
                    mollieComponentsMap
                )
            },500);
        });

        function checkInit() {
            return function () {
                let copySettings = JSON.parse(JSON.stringify(mollieComponentsSettings))
                mollieComponentsSettings.enabledGateways.forEach(function (gateway, index) {
                    const gatewayContainer = containerForGateway(gateway, document)
                    if (!gatewayContainer) {
                        copySettings.enabledGateways.splice(index, 1)
                        const $form = jQuery('form[name="checkout"]')
                        $form.on('checkout_place_order', returnTrue)
                    }
                })
                if (_.isEmpty(copySettings.enabledGateways)) {
                    return
                }
                initializeComponents(
                    jQuery,
                    mollie,
                    copySettings,
                    mollieComponentsMap
                )
            };
        }

        $document.on(
            eventName,
            checkInit()
        )
        $document.on(
            'update_checkout',
            checkInit()
        )
    }
)
(
    window
)
