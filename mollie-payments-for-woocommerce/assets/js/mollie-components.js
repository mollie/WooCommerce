const SELECTOR_TOKEN_ELEMENT = '#cardToken'
const SELECTOR_MOLLIE_COMPONENT = '.mollie-component'
const SELECTOR_FORM = 'form.checkout'
const MOLLIE_COMPONENTS_CONTAINER = '.wc_payment_methods'

let mollie = null

function formFrom (element)
{
  return element.closest(SELECTOR_FORM)
}

function componentsContainer ()
{
  return document.querySelector(MOLLIE_COMPONENTS_CONTAINER)
}

function notice (content, type)
{
  const container = componentsContainer()
  const formWrapper = formFrom(container).parentNode || null
  const mollieNotice = document.querySelector('#mollie-notice')
  const template = `
      <div id="mollie-notice" class="woocommerce-${type}">
        Credit Card: ${content}
      </div>
    `

  mollieNotice && mollieNotice.remove()

  if (!formWrapper) {
    alert(content)
    return
  }

  formWrapper.insertAdjacentHTML('beforebegin', template)

  scrollToNotice()
}

function scrollToNotice ()
{
  var scrollElement = document.querySelector('#mollie-notice')

  if (!scrollElement) {
    scrollElement = document.querySelector(MOLLIE_COMPONENTS_CONTAINER)
  }
  jQuery.scroll_to_notices(jQuery(scrollElement))
}

function mountComponents (mollie, components, options)
{
  for (const componentName in components) {
    const component = mollie.createComponent(componentName, options)
    const container = document.querySelector('.mollie-components')

    container.insertAdjacentHTML('beforeend', `<div id="${componentName}"></div>`)
    component.mount(`#${componentName}`)

    const currentComponent = document.querySelector(`.mollie-component--${componentName}`)

    if (!currentComponent) {
      continue
    }

    currentComponent.insertAdjacentHTML(
      'beforebegin',
      `<b class="mollie-component-label">${components[componentName].label}</b>`
    )
    currentComponent.insertAdjacentHTML(
      'afterend',
      `<div role="alert" id="${componentName}-errors"></div>`
    )
  }
}

function insertTokenFieldIn (element)
{
  element.insertAdjacentHTML(
    'beforeend',
    '<input type="hidden" name="cardToken" id="cardToken" value="" />'
  )
}

function returnFalse ()
{
  return false
}

async function retrievePaymentToken (mollie)
{
  // TODO There's a problem with invalid request errors. see https://molliehq.slack.com/archives/CD7BV7JBX/p1574787556115400

  const { token, error } = await mollie.createToken()

  if (error) {
    throw new Error(error.message || '')
  }

  return token
}

function assignTokenValue (token)
{
  const tokenFieldElement = tokenField()

  if (!tokenFieldElement) {
    return
  }

  tokenFieldElement.value = token
  tokenFieldElement.setAttribute('value', token)
}

function tokenField ()
{
  return document.querySelector(SELECTOR_TOKEN_ELEMENT)
}

function componentsAlreadyExistsUnder (mollieComponents)
{
  return mollieComponents.querySelector(SELECTOR_MOLLIE_COMPONENT)
}

function turnMollieComponentsSubmissionOff (form)
{
  form.off('checkout_place_order', returnFalse)
  form.off('submit', submitForm)
}

async function submitForm (evt)
{
  const container = componentsContainer()
  const form = jQuery(formFrom(container))

  // TODO This has to work with other gateways too
  if (!document.querySelector('#payment_method_mollie_wc_gateway_creditcard').checked) {
    turnMollieComponentsSubmissionOff(form)
    return
  }

  evt.preventDefault()
  evt.stopImmediatePropagation()

  const mollie = mollieInstance()
  let token = ''

  try {
    token = await retrievePaymentToken(mollie)
  } catch (error) {
    // TODO Add default error message
    error.message && notice(error.message, 'error')
    return
  }

  assignTokenValue(token)

  turnMollieComponentsSubmissionOff(form)

  form.submit()
}

function initializeComponentsWithSettings (mollieComponentsSettings)
{
  const merchantProfileId = mollieComponentsSettings.merchantProfileId || null
  const componentsSelectors = mollieComponentsSettings.components || []
  const componentOptions = mollieComponentsSettings.componentOptions || []

  const paymentMethodWithComponents = document.querySelector('#payment_method_mollie_wc_gateway_creditcard')
  if (!paymentMethodWithComponents) {
    return
  }

  const container = componentsContainer()
  const form = formFrom(container)
  const $form = jQuery(form)

  if (!form) {
    console.warn('Cannot mount Mollie Components, no form found.')
  }

  if (componentsAlreadyExistsUnder(container)) {
    return
  }

  // TODO Must be insert into the #mollie_components
  insertTokenFieldIn(form)

  const mollie = mollieInstance(merchantProfileId, componentOptions)
  mountComponents(mollie, componentsSelectors, componentOptions)

  // TODO What if this is not the latest callback executed? If the next will return true this
  //      will not block the checkout.
  $form.on('checkout_place_order', returnFalse)
  // TODO Not trigger when in checkout pay page
  $form.on('submit', submitForm)
}

function mollieInstance (merchantProfileId, componentOptions)
{
  if (null === mollie && merchantProfileId) {
    mollie = new Mollie(merchantProfileId, componentOptions)
  }

  return mollie
}

class MollieComponents
{
  constructor (jQuery, settings, componentsWrapper)
  {
    this.jQuery = jQuery
    this.settings = settings
    this.componentsWrapper = componentsWrapper
  }
}

(function (window, mollieComponentsSettings, Mollie, jQuery)
  {
    jQuery(document).on(
      'updated_checkout',
      () => initializeComponentsWithSettings(mollieComponentsSettings)
    )

    // TODO This create conflicts with the one above
    // jQuery(document).on(
    //   'payment_method_selected',
    //   () => initializeComponentsWithSettings(mollieComponentsSettings)
    // )
  }
)(
  window,
  window.mollieComponentsSettings,
  window.Mollie,
  jQuery
)
