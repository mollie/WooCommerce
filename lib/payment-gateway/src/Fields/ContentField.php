<?php

declare (strict_types=1);
namespace Mollie\Inpsyde\PaymentGateway\Fields;

use Mollie\Inpsyde\PaymentGateway\PaymentGateway;
use Mollie\Inpsyde\PaymentGateway\SettingsFieldRendererInterface;
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
        $data = array_merge(['title' => '', 'disabled' => \false, 'class' => '', 'css' => '', 'placeholder' => '', 'type' => 'text', 'desc_tip' => \false, 'description' => '', 'custom_attributes' => []], $fieldConfig);
        $hasTitle = !empty($data['title']);
        $renderDirectly = $data['render_directly'] ?? \false;
        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
        ob_start();
        ?>
        <tr valign="top" id="<?php 
        echo $fieldKey;
        ?>">
            <?php 
        if ($hasTitle) {
            ?>
            <th scope="row" class="titledesc">
                <label for="<?php 
            echo esc_attr($fieldKey);
            ?>">
                    <?php 
            echo wp_kses_post((string) $data['title']);
            ?>
                    <?php 
            echo $gateway->get_tooltip_html($data);
            ?>
                </label>
            </th>
            <?php 
        }
        ?>
            <td colspan="<?php 
        echo $hasTitle ? '1' : '2';
        ?>"
                style="<?php 
        echo $hasTitle ? '' : 'padding-left: 0;';
        ?>">
                <?php 
        echo $renderDirectly ? wp_kses_post((string) $data['description']) : $gateway->get_description_html($data);
        ?>
            </td>
        </tr>
        <?php 
        return (string) ob_get_clean();
    }
}
