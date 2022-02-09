<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Components;

class AcceptedLocaleValuesDictionary
{
    /**
     * @var string
     */
    public const DEFAULT_LOCALE_VALUE = 'en_US';

    /**
     * @var string[]
     */
    public const ALLOWED_LOCALES_KEYS_MAP = [
        'en_US',
        'nl_NL',
        'nl_BE',
        'fr_FR',
        'fr_BE',
        'de_DE',
        'de_AT',
        'de_CH',
        'es_ES',
        'ca_ES',
        'pt_PT',
        'it_IT',
        'nb_NO',
        'sv_SE',
        'fi_FI',
        'da_DK',
        'is_IS',
        'hu_HU',
        'pl_PL',
        'lv_LV',
        'lt_LT',
    ];
}
