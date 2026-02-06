# [2.8.0](https://github.com/inpsyde/payment-gateway/compare/2.7.1...2.8.0) (2025-07-02)


### Bug Fixes

* 🚀 Update payment gateway active check to use direct property access ([94268ce](https://github.com/inpsyde/payment-gateway/commit/94268cee859aa859bfc0be0bf4e6f43ff41f6f02))
* Update `orderButtonText` to return empty string instead of null. ([bf9648c](https://github.com/inpsyde/payment-gateway/commit/bf9648c64b205cdd23ea090a1f03ea9f667b12b2))


### Features

* 🚀 Add method to check if payment method is enabled ([fc98a88](https://github.com/inpsyde/payment-gateway/commit/fc98a881a3b34fa9efa62ed885e41ebf1cfe35e4))

## [2.7.1](https://github.com/inpsyde/payment-gateway/compare/2.7.0...2.7.1) (2025-06-20)


### Bug Fixes

* 🔧 Change WooCommerce hook from `woocommerce_blocks_loaded` to `woocommerce_init` ([#50](https://github.com/inpsyde/payment-gateway/issues/50)) ([b9919e8](https://github.com/inpsyde/payment-gateway/commit/b9919e8f98dcf8cee99b2dfe0cdbdc89f47c641b))

# [2.7.0](https://github.com/inpsyde/payment-gateway/compare/2.6.0...2.7.0) (2025-04-02)


### Bug Fixes

* Add id in ContentField ([a3747d9](https://github.com/inpsyde/payment-gateway/commit/a3747d90f9b804598158289434f41a2f8b32ef1b))


### Features

* Add render_directly option in ContentField ([6becaaf](https://github.com/inpsyde/payment-gateway/commit/6becaaf304cbe676f648f40b769ceccdfb8b36a6))

# [2.6.0](https://github.com/inpsyde/payment-gateway/compare/2.5.0...2.6.0) (2025-03-14)


### Features

* Allow to override savedTokenComponent in blocks ([#48](https://github.com/inpsyde/payment-gateway/issues/48)) ([11d99eb](https://github.com/inpsyde/payment-gateway/commit/11d99ebf4fff9272884c87bac89055148d3b09a8))

# [2.5.0](https://github.com/inpsyde/payment-gateway/compare/2.4.1...2.5.0) (2025-03-14)


### Bug Fixes

* 📝 Remove redundant `get_form_fields` method and streamline form fields handling ([#47](https://github.com/inpsyde/payment-gateway/issues/47)) ([0b17b89](https://github.com/inpsyde/payment-gateway/commit/0b17b89e26460ba0e02e10fe559e61823f1e5051)), closes [#123](https://github.com/inpsyde/payment-gateway/issues/123)


### Features

* Add support for plugin_slug property ([#45](https://github.com/inpsyde/payment-gateway/issues/45)) ([d7fd64c](https://github.com/inpsyde/payment-gateway/commit/d7fd64c96b649d14796368b76ca86fb4b7ece525))

## [2.4.1](https://github.com/inpsyde/payment-gateway/compare/2.4.0...2.4.1) (2025-02-26)


### Bug Fixes

* ✨ Fix disabled payment methods appearing in block editor ([#44](https://github.com/inpsyde/payment-gateway/issues/44)) ([d225863](https://github.com/inpsyde/payment-gateway/commit/d225863654c6efbd1e1dad95fe0c877f9191bf8d))
* 🛠️ Normalize file paths before comparison in `getPluginFileUrlFromAbsolutePath` ([#43](https://github.com/inpsyde/payment-gateway/issues/43)) ([3de5321](https://github.com/inpsyde/payment-gateway/commit/3de5321fb6dac121ec20940758e7d4edb4e64e6f))

# [2.4.0](https://github.com/inpsyde/payment-gateway/compare/2.3.1...2.4.0) (2025-02-13)


### Features

* Add a filter for Label icons ([8030e3f](https://github.com/inpsyde/payment-gateway/commit/8030e3f1977fc552d77dfe3cab5f8e871ccb7bf9))

## [2.3.1](https://github.com/inpsyde/payment-gateway/compare/2.3.0...2.3.1) (2025-02-05)


### Bug Fixes

* Make icon service optional, provide default icon value as fallback ([#41](https://github.com/inpsyde/payment-gateway/issues/41)) ([9d1a9b7](https://github.com/inpsyde/payment-gateway/commit/9d1a9b70e495250e19445e10be732f4d699fa945))

# [2.3.0](https://github.com/inpsyde/payment-gateway/compare/2.2.2...2.3.0) (2025-01-31)


### Features

* Add service definition for icon property ([#40](https://github.com/inpsyde/payment-gateway/issues/40)) ([ef92746](https://github.com/inpsyde/payment-gateway/commit/ef927461518f027836c3f9a9230dd126b88c3ddb))

## [2.2.2](https://github.com/inpsyde/payment-gateway/compare/2.2.1...2.2.2) (2025-01-22)


### Bug Fixes

* Avoid excessive re-rendering by leveraging useEffect ([0500255](https://github.com/inpsyde/payment-gateway/commit/050025532c80da0c4bab13fb6d71913cc68f27fc))

## [2.2.1](https://github.com/inpsyde/payment-gateway/compare/2.2.0...2.2.1) (2024-12-20)


### Bug Fixes

* Use different base for fallback keys ([b0857e0](https://github.com/inpsyde/payment-gateway/commit/b0857e096147219d49255706b76f3efc39014be7))

# [2.2.0](https://github.com/inpsyde/payment-gateway/compare/2.1.1...2.2.0) (2024-12-19)


### Features

* Introduce IconProviderInterface and icon rendering ([#39](https://github.com/inpsyde/payment-gateway/issues/39)) ([a3f8085](https://github.com/inpsyde/payment-gateway/commit/a3f8085935a3be15d17567db8db8aa6de2e4db81))

## [2.1.1](https://github.com/inpsyde/payment-gateway/compare/2.1.0...2.1.1) (2024-12-13)


### Bug Fixes

* Pass props when creating components instead of filter ([#38](https://github.com/inpsyde/payment-gateway/issues/38)) ([2aa1ab1](https://github.com/inpsyde/payment-gateway/commit/2aa1ab1f685d18a9ff86c57b456a7f6a410115d0))

# [2.1.0](https://github.com/inpsyde/payment-gateway/compare/2.0.0...2.1.0) (2024-12-11)


### Features

* Pass WooCommerce props to filter. Fix block registration ([9584b97](https://github.com/inpsyde/payment-gateway/commit/9584b97445455593bfaca092ff84469eeac2d6d8))

# [2.0.0](https://github.com/inpsyde/payment-gateway/compare/1.6.0...2.0.0) (2024-12-10)


### Features

* Introduce high-level API ([#36](https://github.com/inpsyde/payment-gateway/issues/36)) ([a84a200](https://github.com/inpsyde/payment-gateway/commit/a84a20094fdd2670c04c8f1a4005fa1a3452c698))


### BREAKING CHANGES

* Default/Fallback behaviour is moved into `DefaultPaymentMethodDefinitionTrait`, so existing integrations may see differences in behaviour when upgrading.

* Refactor README to clarify design considerations and architecture for WooCommerce payment gateways.

* Improve README with emojis for better visual appeal.

Adds a unit test
Moves some of the defaults/fallbacks from PaymentGateway into DefaultPaymentMethodDefinitionTrait

* Refactor and clean up code in PaymentMethodDefinition, NoopGatewayIconsRenderer, and PaymentGateway classes.

* Introduce high-level API for settings customization

* Update README.md

# [1.6.0](https://github.com/inpsyde/payment-gateway/compare/1.5.2...1.6.0) (2024-11-19)


### Features

* Filterable checkout components ([#34](https://github.com/inpsyde/payment-gateway/issues/34)) ([76f8c16](https://github.com/inpsyde/payment-gateway/commit/76f8c1626f3360b9763c6210393a6f7a40a0ccd7)), closes [#35](https://github.com/inpsyde/payment-gateway/issues/35)

## [1.5.2](https://github.com/inpsyde/payment-gateway/compare/1.5.1...1.5.2) (2024-11-13)


### Bug Fixes

* allow node 22 ([1a28fdd](https://github.com/inpsyde/payment-gateway/commit/1a28fdd38be46459d1c63cd99192d9f2bc22e25d))
* allow node 22 ([93a85e6](https://github.com/inpsyde/payment-gateway/commit/93a85e69eb329620dc636180298253ac18742a66))

## [1.5.1](https://github.com/inpsyde/payment-gateway/compare/1.5.0...1.5.1) (2024-09-23)


### Bug Fixes

* Fix PaymentGatewayBlocks::$gateway type. ([#31](https://github.com/inpsyde/payment-gateway/issues/31)) ([58cd64c](https://github.com/inpsyde/payment-gateway/commit/58cd64cb64bc11fcd1a89d9aa0690d90738162a4))

# [1.5.0](https://github.com/inpsyde/payment-gateway/compare/1.4.1...1.5.0) (2024-09-20)


### Bug Fixes

* code style, remove PaymentGatewayBlocks::$name type ([32ed490](https://github.com/inpsyde/payment-gateway/commit/32ed490e73414383c6f6eb34529a483468afb56c))


### Features

* Allow to interrupt refund with a message ([e5c6e10](https://github.com/inpsyde/payment-gateway/commit/e5c6e10d06b228483b8725cfbd41686fb0c5b776))

## [1.4.1](https://github.com/inpsyde/payment-gateway/compare/1.4.0...1.4.1) (2024-09-18)


### Bug Fixes

* use variable in process_refund() ([afabd5c](https://github.com/inpsyde/payment-gateway/commit/afabd5cc6e08a830238af5067500df3fb462644f))

# [1.4.0](https://github.com/inpsyde/payment-gateway/compare/1.3.2...1.4.0) (2024-09-13)


### Features

* [PROD-169] Implement l10n for errors & order notes ([#26](https://github.com/inpsyde/payment-gateway/issues/26)) ([7806c79](https://github.com/inpsyde/payment-gateway/commit/7806c798bc1a6e450f91d703fc94f7c07e8aea96)), closes [#1](https://github.com/inpsyde/payment-gateway/issues/1) [#2](https://github.com/inpsyde/payment-gateway/issues/2)

## [1.3.2](https://github.com/inpsyde/payment-gateway/compare/1.3.1...1.3.2) (2024-07-12)


### Bug Fixes

* Keep frontend source and composer.json in releases ([ee1f8a3](https://github.com/inpsyde/payment-gateway/commit/ee1f8a3642d9240927b8df39b70e8022b000ad4f))

## [1.3.1](https://github.com/inpsyde/payment-gateway/compare/1.3.0...1.3.1) (2024-07-01)


### Bug Fixes

* Do not use PHP_INT_MIN for hooks ([8e5e15d](https://github.com/inpsyde/payment-gateway/commit/8e5e15d4f09364333b5937a2d09b44210fe0c2f4))
* Move comment outside of expression ([c80b6cd](https://github.com/inpsyde/payment-gateway/commit/c80b6cde33661b53fe0ac73824f5eaae3fda9db7))

# [1.3.0](https://github.com/inpsyde/payment-gateway/compare/1.2.0...1.3.0) (2024-06-05)


### Features

* Add locators for method title & description ([#24](https://github.com/inpsyde/payment-gateway/issues/24)) ([27a73a9](https://github.com/inpsyde/payment-gateway/commit/27a73a90965af918da92185526315a97ab8b8c48))

# [1.2.0](https://github.com/inpsyde/payment-gateway/compare/1.1.1...1.2.0) (2024-06-05)


### Features

* Check required services during init  ([79ae2b9](https://github.com/inpsyde/payment-gateway/commit/79ae2b9bb178376419c91b418bdfa2b593b99346)), closes [#15](https://github.com/inpsyde/payment-gateway/issues/15)

## [1.1.1](https://github.com/inpsyde/payment-gateway/compare/1.1.0...1.1.1) (2024-06-04)


### Bug Fixes

* don't register gateway for blocks if disabled ([e577b52](https://github.com/inpsyde/payment-gateway/commit/e577b522ef0452104a2e54c499f39e9953876a38))

# [1.1.0](https://github.com/inpsyde/payment-gateway/compare/1.0.1...1.1.0) (2024-06-04)


### Features

* Add a new service to allow skipping blocks registration ([#21](https://github.com/inpsyde/payment-gateway/issues/21)) ([e1978d1](https://github.com/inpsyde/payment-gateway/commit/e1978d19654a2c685265a9d32fa12ff49c7b6249))

# 1.0.0 (2024-05-21)


### Bug Fixes

* specify playwright dependency ([1060e5c](https://github.com/inpsyde/payment-gateway/commit/1060e5cafece37c465e6e78077d2c7378f723b46))
