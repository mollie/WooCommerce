<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\MerchantCapture\UI;

class OrderActionBlock
{
    /**
     * @param array<mixed> $paragraphs
     */
    public function __invoke(array $paragraphs): void
    {
        echo "<li class='wide'>";
        foreach ($paragraphs as $paragraph) {
            ?>
            <p><?php echo wp_kses($paragraph, ['mark' => ['class' => []], 'span' => []]); ?></p>
            <?php
        }
        echo '</li>';
    }
}
