<?php

declare(strict_types=1);

namespace Inpsyde\PaymentGateway\Fields;

use Inpsyde\PaymentGateway\PaymentGateway;
use Inpsyde\PaymentGateway\SettingsFieldRendererInterface;

/**
 * The field for rendering basic HTML content.
 *
 * Main attributes: title, description.
 * If title is missing, the description will take the whole row.
 */
class ContentField implements SettingsFieldRendererInterface
{
    public function render(string $fieldId, array $fieldConfig, PaymentGateway $gateway): string
    {
        $fieldKey = $gateway->get_field_key($fieldId);
        $data = array_merge([
            'title' => '',
            'disabled' => false,
            'class' => '',
            'css' => '',
            'placeholder' => '',
            'type' => 'text',
            'desc_tip' => false,
            'description' => '',
            'custom_attributes' => [],
        ], $fieldConfig);

        $hasTitle = !empty($data['title']);

        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
        ob_start();
        ?>
        <tr valign="top">
            <?php if ($hasTitle) : ?>
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($fieldKey); ?>">
                    <?php echo wp_kses_post((string) $data['title']); ?>
                    <?php echo $gateway->get_tooltip_html($data); ?>
                </label>
            </th>
            <?php endif; ?>
            <td colspan="<?php echo $hasTitle ? '1' : '2'; ?>"
                style="<?php echo $hasTitle ? '' : 'padding-left: 0;'; ?>">
                <?php echo $gateway->get_description_html($data); ?>
            </td>
        </tr>
        <?php

        return (string) ob_get_clean();
    }
}
