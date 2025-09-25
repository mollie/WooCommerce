/*
 * ATTENTION: The "eval" devtool has been used (maybe by default in mode: "development").
 * This devtool is neither made for production nor for readable output files.
 * It uses "eval()" calls to create a separate source file in the browser devtools.
 * If you are trying to read the output file, select a different devtool (https://webpack.js.org/configuration/devtool/)
 * or disable the default devtool with "devtool: false".
 * If you are looking for production-ready output files, see mode: "production" (https://webpack.js.org/configuration/mode/).
 */
/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./resources/js/blocks/index.js":
/*!**************************************************!*\
  !*** ./resources/js/blocks/index.js + 6 modules ***!
  \**************************************************/
/***/ (() => {

eval("{\n;// external \"React\"\nconst external_React_namespaceObject = window[\"React\"];\n;// external [\"wp\",\"i18n\"]\nconst external_wp_i18n_namespaceObject = window[\"wp\"][\"i18n\"];\n;// external [\"wc\",\"wcBlocksRegistry\"]\nconst external_wc_wcBlocksRegistry_namespaceObject = window[\"wc\"][\"wcBlocksRegistry\"];\n;// external [\"wp\",\"htmlEntities\"]\nconst external_wp_htmlEntities_namespaceObject = window[\"wp\"][\"htmlEntities\"];\n;// external [\"wc\",\"wcSettings\"]\nconst external_wc_wcSettings_namespaceObject = window[\"wc\"][\"wcSettings\"];\n;// external [\"wp\",\"hooks\"]\nconst external_wp_hooks_namespaceObject = window[\"wp\"][\"hooks\"];\n;// ./resources/js/blocks/index.js\n\n\n\n\n\n\n\ninpsydeGateways.forEach(name => {\n  const settings = (0,external_wc_wcSettings_namespaceObject.getSetting)(`${name}_data`, {});\n  const checkoutFieldsHookName = `${name}_checkout_fields`;\n  const savedTokenFieldsHookName = `${name}_saved_token_fields`;\n  const iconsHookName = `${name}_payment_method_icons`;\n  const defaultLabel = (0,external_wp_i18n_namespaceObject.__)('Syde Payment Gateway', 'syde-payment-gateway');\n  const label = (0,external_wp_htmlEntities_namespaceObject.decodeEntities)(settings.title) || defaultLabel;\n  const Content = props => {\n    const [components, setComponents] = (0,external_React_namespaceObject.useState)([]);\n    (0,external_React_namespaceObject.useEffect)(() => {\n      setComponents(external_wp_hooks_namespaceObject.defaultHooks.applyFilters(checkoutFieldsHookName, []));\n    }, []);\n    /**\n     * If no external plugins/slot-fills are configured,\n     * we default to displaying the method description\n     */\n    if (!Array.isArray(components) || !components.length) {\n      const DefaultPlugin = () => (0,external_wp_htmlEntities_namespaceObject.decodeEntities)(settings.description || '');\n      return (0,external_React_namespaceObject.createElement)(DefaultPlugin, null);\n    }\n    return (0,external_React_namespaceObject.createElement)(external_React_namespaceObject.Fragment, null, components.map(Component => (0,external_React_namespaceObject.createElement)(Component, {\n      ...props\n    })));\n  };\n  /**\n   * Label component\n   *\n   * @param {*} props Props from payment API.\n   */\n  const Label = props => {\n    const {\n      PaymentMethodLabel,\n      PaymentMethodIcons\n    } = props.components;\n    return (0,external_React_namespaceObject.createElement)(external_React_namespaceObject.Fragment, null, (0,external_React_namespaceObject.createElement)(PaymentMethodLabel, {\n      text: label\n    }), (0,external_React_namespaceObject.createElement)(PaymentMethodIcons, {\n      icons: external_wp_hooks_namespaceObject.defaultHooks.applyFilters(iconsHookName, settings.icons)\n    }));\n  };\n  const SavedTokenContent = props => {\n    const [components, setComponents] = (0,external_React_namespaceObject.useState)([]);\n    (0,external_React_namespaceObject.useEffect)(() => {\n      setComponents(external_wp_hooks_namespaceObject.defaultHooks.applyFilters(savedTokenFieldsHookName, []));\n    }, []);\n    /**\n     * If no external plugins/slot-fills are configured,\n     * we default to not displaying anything\n     */\n    if (!Array.isArray(components) || !components.length) {\n      return null;\n    }\n    return (0,external_React_namespaceObject.createElement)(external_React_namespaceObject.Fragment, null, components.map(Component => (0,external_React_namespaceObject.createElement)(Component, {\n      ...props\n    })));\n  };\n\n  /**\n   * Payment method config object.\n   */\n  const PaymentMethodArgs = {\n    name: name,\n    label: (0,external_React_namespaceObject.createElement)(Label, null),\n    content: (0,external_React_namespaceObject.createElement)(Content, null),\n    edit: (0,external_React_namespaceObject.createElement)(Content, null),\n    savedTokenComponent: (0,external_React_namespaceObject.createElement)(SavedTokenContent, null),\n    icons: settings.icons,\n    canMakePayment: () => true,\n    ariaLabel: label,\n    supports: {\n      features: settings.supports\n    }\n  };\n  console.log(`Registering Payment Method \"${name}\"`, PaymentMethodArgs);\n  if (settings.placeOrderButtonLabel) {\n    PaymentMethodArgs.placeOrderButtonLabel = settings.placeOrderButtonLabel;\n  }\n  (0,external_wc_wcBlocksRegistry_namespaceObject.registerPaymentMethod)(PaymentMethodArgs);\n});\n\n//# sourceURL=webpack://@inpsyde/generic-payment-gateway/./resources/js/blocks/index.js_+_6_modules?\n}");

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module can't be inlined because the eval devtool is used.
/******/ 	var __webpack_exports__ = {};
/******/ 	__webpack_modules__["./resources/js/blocks/index.js"]();
/******/ 	
/******/ })()
;