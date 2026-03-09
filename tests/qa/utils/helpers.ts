/**
 * External dependencies
 */
import { WooCommerceApi } from '@inpsyde/playwright-utils/build';
/**
 * Sets annotation about tested customer
 * If tested customer is registered (has non-empty username),
 * then his billing.country will be added to annotation for example: customer-germany
 * Only one customer per country is used.
 * customer-germany - is a name of storage state file to use in the test.
 *
 * @default {} - empty annotation for guest
 * @param customer
 * @return object with test annotation
 */
export function annotateVisitor( customer: WooCommerce.CreateCustomer ) {
	const storageStateName = getCustomerStorageStateName( customer );
	return {
		annotation: {
			type: 'visitor',
			description: storageStateName,
		},
	};
}

/**
 * Sets annotation about tested gateway (used for mollieSettingsGateway fixture)
 *
 * @param gatewaySlug
 */
export function annotateGateway( gatewaySlug: string ) {
	return {
		annotation: {
			type: 'mollieGateway',
			description: gatewaySlug,
		},
	};
}

/**
 * Builds storage state file name for customer
 *
 * @param customer
 * @return 'goest' or 'customer-<country>'
 */
export function getCustomerStorageStateName(
	customer: WooCommerce.CreateCustomer
) {
	// check is tested customer is guest (has empty username)
	if ( ! customer.username ) {
		return 'guest';
	}
	// for registered customers
	const visitorCountry = codeToCountry( customer.billing.country );
	return `customer-${ visitorCountry }`;
}

export async function updateCurrencyIfNeeded(
	wooCommerceApi: WooCommerceApi,
	currency: string | undefined
) {
	if ( currency && currency !== 'EUR' ) {
		await wooCommerceApi.updateGeneralSettings( {
			woocommerce_currency: currency,
		} );
	}
}

/**
 * Converts customer.billing.country (code) to country name
 *
 * @param countryCode
 * @return country name in lower case
 */
export function codeToCountry( countryCode: string ) {
	const countries = {
		AF: 'afghanistan',
		AL: 'albania',
		DZ: 'algeria',
		AD: 'andorra',
		AO: 'angola',
		AG: 'antigua-and-barbuda',
		AR: 'argentina',
		AM: 'armenia',
		AU: 'australia',
		AT: 'austria',
		AZ: 'azerbaijan',
		BS: 'bahamas',
		BH: 'bahrain',
		BD: 'bangladesh',
		BB: 'barbados',
		BY: 'belarus',
		BE: 'belgium',
		BZ: 'belize',
		BJ: 'benin',
		BT: 'bhutan',
		BO: 'bolivia',
		BA: 'bosnia-and-herzegovina',
		BW: 'botswana',
		BR: 'brazil',
		BN: 'brunei',
		BG: 'bulgaria',
		BF: 'burkina-faso',
		BI: 'burundi',
		CV: 'cape-verde',
		KH: 'cambodia',
		CM: 'cameroon',
		CA: 'canada',
		CF: 'central-african-republic',
		TD: 'chad',
		CL: 'chile',
		CN: 'china',
		CO: 'colombia',
		KM: 'comoros',
		CG: 'congo',
		CD: 'democratic-republic-of-the-congo',
		CR: 'costa-rica',
		CI: 'cote-d-ivoire',
		HR: 'croatia',
		CU: 'cuba',
		CY: 'cyprus',
		CZ: 'czech-republic',
		DK: 'denmark',
		DJ: 'djibouti',
		DM: 'dominica',
		DO: 'dominican-republic',
		EC: 'ecuador',
		EG: 'egypt',
		SV: 'el-salvador',
		GQ: 'equatorial-guinea',
		ER: 'eritrea',
		EE: 'estonia',
		SZ: 'eswatini',
		ET: 'ethiopia',
		FJ: 'fiji',
		FI: 'finland',
		FR: 'france',
		GA: 'gabon',
		GM: 'gambia',
		GE: 'georgia',
		DE: 'germany',
		GH: 'ghana',
		GR: 'greece',
		GD: 'grenada',
		GT: 'guatemala',
		GN: 'guinea',
		GW: 'guinea-bissau',
		GY: 'guyana',
		HT: 'haiti',
		HN: 'honduras',
		HU: 'hungary',
		IS: 'iceland',
		IN: 'india',
		ID: 'indonesia',
		IR: 'iran',
		IQ: 'iraq',
		IE: 'ireland',
		IL: 'israel',
		IT: 'italy',
		JM: 'jamaica',
		JP: 'japan',
		JO: 'jordan',
		KZ: 'kazakhstan',
		KE: 'kenya',
		KI: 'kiribati',
		KP: 'north-korea',
		KR: 'south-korea',
		KW: 'kuwait',
		KG: 'kyrgyzstan',
		LA: 'laos',
		LV: 'latvia',
		LB: 'lebanon',
		LS: 'lesotho',
		LR: 'liberia',
		LY: 'libya',
		LI: 'liechtenstein',
		LT: 'lithuania',
		LU: 'luxembourg',
		MG: 'madagascar',
		MW: 'malawi',
		MY: 'malaysia',
		MV: 'maldives',
		ML: 'mali',
		MT: 'malta',
		MH: 'marshall-islands',
		MR: 'mauritania',
		MU: 'mauritius',
		MX: 'mexico',
		FM: 'micronesia',
		MD: 'moldova',
		MC: 'monaco',
		MN: 'mongolia',
		ME: 'montenegro',
		MA: 'morocco',
		MZ: 'mozambique',
		MM: 'myanmar',
		NA: 'namibia',
		NR: 'nauru',
		NP: 'nepal',
		NL: 'netherlands',
		NZ: 'new-zealand',
		NI: 'nicaragua',
		NE: 'niger',
		NG: 'nigeria',
		MK: 'north-macedonia',
		NO: 'norway',
		OM: 'oman',
		PK: 'pakistan',
		PW: 'palau',
		PS: 'palestine',
		PA: 'panama',
		PG: 'papua-new-guinea',
		PY: 'paraguay',
		PE: 'peru',
		PH: 'philippines',
		PL: 'poland',
		PT: 'portugal',
		QA: 'qatar',
		RO: 'romania',
		RU: 'russia',
		RW: 'rwanda',
		KN: 'saint-kitts-and-nevis',
		LC: 'saint-lucia',
		VC: 'saint-vincent-and-the-grenadines',
		WS: 'samoa',
		SM: 'san-marino',
		ST: 'sao-tome-and-principe',
		SA: 'saudi-arabia',
		SN: 'senegal',
		RS: 'serbia',
		SC: 'seychelles',
		SL: 'sierra-leone',
		SG: 'singapore',
		SK: 'slovakia',
		SI: 'slovenia',
		SB: 'solomon-islands',
		SO: 'somalia',
		ZA: 'south-africa',
		SS: 'south-sudan',
		ES: 'spain',
		LK: 'sri-lanka',
		SD: 'sudan',
		SR: 'suriname',
		SE: 'sweden',
		CH: 'switzerland',
		SY: 'syria',
		TW: 'taiwan',
		TJ: 'tajikistan',
		TZ: 'tanzania',
		TH: 'thailand',
		TL: 'timor-leste',
		TG: 'togo',
		TO: 'tonga',
		TT: 'trinidad-and-tobago',
		TN: 'tunisia',
		TR: 'turkey',
		TM: 'turkmenistan',
		TV: 'tuvalu',
		UG: 'uganda',
		UA: 'ukraine',
		AE: 'united-arab-emirates',
		GB: 'united-kingdom',
		US: 'usa',
		UY: 'uruguay',
		UZ: 'uzbekistan',
		VU: 'vanuatu',
		VA: 'vatican-city',
		VE: 'venezuela',
		VN: 'vietnam',
		YE: 'yemen',
		ZM: 'zambia',
		ZW: 'zimbabwe',
	};
	return countries[ countryCode ];
}
