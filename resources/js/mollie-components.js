const SELECTOR_TOKEN_ELEMENT = '.cardToken'
const SELECTOR_MOLLIE_COMPONENT = '.mollie-component'
const SELECTOR_FORM = 'form'
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
        ${content}
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

function mountComponents (mollie, components, settings)
{
  for (const componentName in components) {
    const component = mollie.createComponent(componentName, settings)
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

function insertTokenField (componentsContainer)
{
  componentsContainer
    .querySelector('.mollie-components')
    .insertAdjacentHTML(
      'beforeend',
      '<input type="hidden" name="cardToken" class="cardToken" value="" />'
  )
}

function returnFalse ()
{
  return false
}

async function retrievePaymentToken (mollie)
{
  const { token, error } = await mollie.createToken(SELECTOR_TOKEN_ELEMENT)

  if (error) {
    throw new Error(error.message || '')
  }

  return token
}

function assignTokenValue (token, componentsContainer)
{
  const tokenFieldElement = componentsContainer.querySelector(SELECTOR_TOKEN_ELEMENT)

  if (!tokenFieldElement) {
    return
  }

  tokenFieldElement.value = token
  tokenFieldElement.setAttribute('value', token)
}

function componentsAlreadyExistsUnder (mollieComponents)
{
  return mollieComponents.querySelector(SELECTOR_MOLLIE_COMPONENT)
}

function turnMollieComponentsSubmissionOff (form)
{
  const $form = jQuery(form)

  $form.off('checkout_place_order', returnFalse)
  $form.off('submit', submitForm)
}

async function submitForm (evt)
{
  const { mollie, gateway, componentsContainer, messages } = evt.data

  const $form = jQuery(formFrom(componentsContainer))

  if (!document.querySelector(`#payment_method_mollie_wc_gateway_${gateway}`).checked) {
    turnMollieComponentsSubmissionOff($form)
    return
  }

  evt.preventDefault()
  evt.stopImmediatePropagation()

  let token = ''

  try {
    token = await retrievePaymentToken(mollie)
  } catch (error) {
    // const content = error.message ? error.message : messages.defaultErrorMessage
    // content && notice(content, 'error')
    // $form.removeClass('processing').unblock()
    // jQuery(document.body).trigger('checkout_error')
    // return
  }

  turnMollieComponentsSubmissionOff($form)

  token && assignTokenValue(token, componentsContainer)
  $form.submit()
}

function initializeComponentsWithSettings (mollieComponentsSettings)
{
  const merchantProfileId = mollieComponentsSettings.merchantProfileId || null
  const componentsSelectors = mollieComponentsSettings.components || []
  const componentSettings = mollieComponentsSettings.componentSettings || []
  const enabledGateways = mollieComponentsSettings.enabledGateways || []
  const messages = mollieComponentsSettings.messages || {}

  enabledGateways.forEach(gateway =>
  {
    const componentsContainer = document.querySelector(`.payment_method_mollie_wc_gateway_${gateway}`)

    if (!componentsContainer) {
      return
    }

    const form = formFrom(componentsContainer)
    const $form = jQuery(form)

    if (!form) {
      console.warn('Cannot mount Mollie Components, no form found.')
    }

    if (componentsAlreadyExistsUnder(componentsContainer)) {
      return
    }

    const mollie = mollieInstance(merchantProfileId, componentSettings)
    mountComponents(mollie, componentsSelectors, componentSettings[gateway])

    insertTokenField(componentsContainer)

    $form.on('checkout_place_order', returnFalse)
    $form.on(
      'submit',
      null,
      {
        mollie,
        gateway,
        componentsContainer,
        messages
      },
      submitForm
    )
  })
}

function mollieInstance (merchantProfileId, settings)
{
  if (null === mollie && merchantProfileId) {
    mollie = new Mollie(merchantProfileId, settings)
  }

  return mollie
}

(function (window, mollieComponentsSettings, Mollie, jQuery)
  {
    if (mollieComponentsSettings.isCheckout) {
      jQuery(document).on(
        'updated_checkout',
        () => initializeComponentsWithSettings(mollieComponentsSettings)
      )
    }

    if (mollieComponentsSettings.isCheckoutPayPage) {
      jQuery(document).on(
        'payment_method_selected',
        () => initializeComponentsWithSettings(mollieComponentsSettings)
      )
    }
  }
)(
  window,
  window.mollieComponentsSettings,
  window.Mollie,
  jQuery
)
