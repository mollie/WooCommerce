<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Utils;

use Mollie\WooCommerce\Notice\AdminNotice;

class IconFactory
{

    public $id;

    public function initIcon($gateway, $displayLogo, string $pluginUrl)
    {
        if ($displayLogo) {
            $default_icon = $this->getIconUrl($gateway->getMollieMethodId(), $pluginUrl);
            $gateway->icon = apply_filters($gateway->id . '_icon_url', $default_icon);
        }
    }

    /**
     * @return string
     */
    public function getIconUrl($gatewayId, $pluginUrl)
    {
        return $this->iconFactory($pluginUrl)->svgUrlForPaymentMethod($gatewayId);
    }

    /**
     * Singleton of the class that handles icons (API/fallback)
     * @return PaymentMethodsIconUrl|null
     */
    public function iconFactory(string $pluginUrl)
    {
        static $factory = null;
        if ($factory === null) {
            $factory = new PaymentMethodsIconUrl($pluginUrl);
        }

        return $factory;
    }

    public function processAdminOptionCustomLogo()
    {
        $mollieUploadDirectory = trailingslashit(wp_upload_dir()['basedir'])
            . 'mollie-uploads/' . $this->id;
        wp_mkdir_p($mollieUploadDirectory);
        $targetLocation = $mollieUploadDirectory . '/';
        $fileOptionName = $this->id . '_upload_logo';
        $enabledLogoOptionName = $this->id . '_enable_custom_logo';
        $gatewaySettings = get_option(sprintf('%s_settings', $this->id), []);
        if (!isset($_POST[$enabledLogoOptionName])) {
            $gatewaySettings["iconFileUrl"] = null;
            $gatewaySettings["iconFilePath"] = null;
            update_option(sprintf('%s_settings', $this->id), $gatewaySettings);
        }
        if (isset($_POST[$enabledLogoOptionName])
            && isset($_FILES[$fileOptionName])
            && $_FILES[$fileOptionName]['size'] > 0
        ) {
            if ($_FILES[$fileOptionName]['size'] <= 500000) {
                $fileName = preg_replace(
                    '#\s+#',
                    '_',
                    $_FILES[$fileOptionName]['name']
                );
                $tempName = $_FILES[$fileOptionName]['tmp_name'];
                move_uploaded_file($tempName, $targetLocation . $fileName);
                $gatewaySettings["iconFileUrl"] = trailingslashit(
                    wp_upload_dir()['baseurl']
                ) . 'mollie-uploads/'. $this->id .'/'. $fileName;
                $gatewaySettings["iconFilePath"] = trailingslashit(
                    wp_upload_dir()['basedir']
                ) . 'mollie-uploads/'. $this->id .'/'. $fileName;
                update_option(sprintf('%s_settings', $this->id), $gatewaySettings);
            } else {
                $notice = new AdminNotice();
                $message = sprintf(
                    esc_html__(
                        '%1$sMollie Payments for WooCommerce%2$s Unable to upload the file. Size must be under 500kb.',
                        'mollie-payments-for-woocommerce'
                    ),
                    '<strong>',
                    '</strong>'
                );
                $notice->addNotice('notice-error is-dismissible', $message);
            }
        }
    }
}
