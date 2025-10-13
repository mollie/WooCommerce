/**
 * Internal dependencies
 */
import { test as setup } from '../../utils';
import {
	taxSettings,
	mollieApiKeys,
	MollieSettings,
	StoreSettings,
	shopSettings,
} from '../../resources';

type EnvConfig = {
	title: string;
	store?: StoreSettings;
	mollie?: {
		cleanDb?: boolean;
		apiKeys?: MollieSettings.ApiKeys;
		apiMethod?: MollieSettings.ApiMethod;
	};
};

const configureEnv = ( data: EnvConfig ) => {
	const { title, store, mollie } = data;

	if ( store ) {
		setup( `${ title } Setup store settings`, async ( { utils } ) => {
			await utils.configureStore( store );
		} );
	}

	if ( mollie ) {
		const { cleanDb, apiKeys, apiMethod } = mollie;

		setup( `${ title } Install/activate Mollie`, async ( { utils } ) => {
			await utils.installActivateMollie();
		} );

		if ( cleanDb ) {
			setup( `${ title } Clean Mollie DB`, async ( { mollieApi } ) => {
				await mollieApi.setMollieApiKeys( mollieApiKeys.default );
				await mollieApi.cleanMollieDb();
			} );
		}

		if ( apiKeys ) {
			setup( `${ title } Set API keys`, async ( { mollieApi } ) => {
				await mollieApi.setMollieApiKeys( apiKeys );
			} );
		}

		if ( apiMethod ) {
			setup( `${ title } Set API method`, async ( { mollieApi } ) => {
				await mollieApi.setApiMethod( apiMethod );
			} );
		}
	}
};

configureEnv( {
	title: 'setup:checkout:block;',
	store: { enableClassicPages: false },
} );

configureEnv( {
	title: 'setup:checkout:classic;',
	store: { enableClassicPages: true },
} );

configureEnv( {
	title: 'setup:tax:inc;',
	store: { taxes: taxSettings.including },
} );

configureEnv( {
	title: 'setup:tax:exc;',
	store: { taxes: taxSettings.excluding },
} );

configureEnv( {
	title: 'setup:mollie:germany;',
	store: shopSettings.germany.general,
	mollie: {
		cleanDb: true,
		apiKeys: mollieApiKeys.default,
	},
} );
