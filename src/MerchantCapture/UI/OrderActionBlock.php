<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\MerchantCapture\UI;

class OrderActionBlock
{
    public function __invoke(array $paragraphs)
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
