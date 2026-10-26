/**
 * External dependencies
 */
import fs from 'fs';
import { exec } from 'child_process';
import { promisify } from 'util';
import { Client as MollieClientApi } from 'mollie-api-typescript';
import {
	APIRequestContext,
	Page,
	VideoMode,
	ViewportSize,
} from '@playwright/test';
import {
	test as base,
	CustomerAccount,
	CustomerPaymentMethods,
	CustomerSubscriptions,
	expect,
	OrderReceived,
	WooCommerceApi,
	BaseExtend,
} from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import {
	MollieSettingsApiKeys,
	MollieSettingsPaymentMethods,
	MollieSettingsAdvanced,
	WooCommerceOrderEdit,
	MollieSettingsGateway,
} from './admin';
import {
	Checkout,
	ClassicCheckout,
	PayForOrder,
	MollieHostedCheckout,
	Product,
	Cart,
} from './frontend';
import { MollieApi, Utils } from '.';
import { MollieSettings, mollieApiKeys } from '../resources';

const execAsync = promisify( exec );

type TestBaseExtend = BaseExtend & {
	recordVideoOptions: {
		mode: VideoMode;
		size?: ViewportSize;
	};
	// Dashboard fixtures
	mollieApi: MollieApi;
	mollieClientApi: MollieClientApi;
	mollieSettingsApiKeys: MollieSettingsApiKeys;
	mollieSettingsPaymentMethods: MollieSettingsPaymentMethods;
	mollieSettingsAdvanced: MollieSettingsAdvanced;
	mollieSettingsGateway: MollieSettingsGateway;
	mollieApiMethod?: MollieSettings.ApiMethod;
	isMultistepCheckout?: boolean;
	// Mollie merchant profile whose API key the shard uses (see mollieApiKeys)
	mollieProfile: 'default' | 'nl';

	// Frontend fixtures
	visitorPage: Page;
	visitorRequest: APIRequestContext;
	visitorWooCommerceApi: WooCommerceApi;
	wooCommerceOrderEdit: WooCommerceOrderEdit;
	checkout: Checkout;
	classicCheckout: ClassicCheckout;
	payForOrder: PayForOrder;
	orderReceived: OrderReceived;
	mollieHostedCheckout: MollieHostedCheckout;
	product: Product;
	cart: Cart;

	// Complex fixtures
	utils: Utils;

	// Auto fixtures
	mollieLogOnFailure: void;
};

const test = base.extend< TestBaseExtend >( {
	recordVideoOptions: [ null, { option: true } ],
	// Mollie API errors only reach the plugin's WC log, so attach it to failed tests
	mollieLogOnFailure: [
		async ( {}, use, testInfo ) => {
			await use();
			if ( testInfo.status === testInfo.expectedStatus ) {
				return;
			}
			let log = '';
			try {
				// Async, so a slow wp-env call doesn't block the worker
				( { stdout: log } = await execAsync(
					'npx wp-env run tests-cli bash -c "tail -n 200 wp-content/uploads/wc-logs/mollie-payments-for-woocommerce-*.log"',
					{ encoding: 'utf8', timeout: 30_000 }
				) );
			} catch {
				return; // no log yet or wp-env not reachable
			}
			// Parallel shards share one site, so the log can hold other tests' entries
			const note = testInfo.config.workers > 1
				? 'Note: this shard runs tests in parallel, so the log can contain entries from other tests.\n\n'
				: '';
			await testInfo.attach( 'mollie-log', {
				body: note + log,
				contentType: 'text/plain',
			} );
		},
		{ auto: true },
	],
	// Dashboard pages operated by Admin
	mollieApi: async ( { request, requestUtils }, use ) => {
		await use( new MollieApi( { request, requestUtils } ) );
	},
	mollieProfile: [ 'default', { option: true } ],
	mollieClientApi: async ( { mollieProfile }, use ) => {
		await use(
			new MollieClientApi( {
				security: {
					apiKey: mollieApiKeys[ mollieProfile ].testApiKey,
				},
			} )
		);
	},
	mollieSettingsApiKeys: async ( { page }, use ) => {
		await use( new MollieSettingsApiKeys( { page } ) );
	},
	mollieSettingsPaymentMethods: async ( { page }, use ) => {
		await use( new MollieSettingsPaymentMethods( { page } ) );
	},
	mollieSettingsAdvanced: async ( { page }, use ) => {
		await use( new MollieSettingsAdvanced( { page } ) );
	},
	mollieSettingsGateway: async ( { page }, use, testInfo ) => {
		const gatewaySlug = testInfo.annotations?.find(
			( el ) => el.type === 'mollieGateway'
		)?.description;
		await use( new MollieSettingsGateway( { page, gatewaySlug } ) );
	},
	mollieApiMethod: [ null, { option: true } ],
	isMultistepCheckout: [ null, { option: true } ],
	wooCommerceOrderEdit: async ( { page }, use ) => {
		await use( new WooCommerceOrderEdit( { page } ) );
	},

	visitorPage: async ( { browser, recordVideoOptions }, use, testInfo ) => {
		// check if visitor is specified in test otherwise use guest
		const storageStateName =
			testInfo.annotations?.find( ( el ) => el.type === 'visitor' )
				?.description || 'guest';
		const storageStatePath = `${ process.env.STORAGE_STATE_PATH }/${ storageStateName }.json`;
		// apply current visitor's storage state to the context
		const context = await browser.newContext( {
			...testInfo.project.use, // Spread project's use config
			storageState: fs.existsSync( storageStatePath )
				? storageStatePath
				: undefined,
			...( recordVideoOptions && {
				recordVideo: {
					...recordVideoOptions,
					dir: testInfo.outputDir, // Override recordVideo to use correct output dir
				},
			} ),
		} );
		const page = await context.newPage();
		await use( page );

		// Save video path BEFORE closing
		const video = page.video();
		await page.close();
		await context.close();

		// Attach video to report after context is closed
		if ( video ) {
			const videoPath = await video.path();
			await testInfo.attach( 'video', {
				path: videoPath,
				contentType: 'video/webm',
			} );
		}
	},
	visitorRequest: async ( { visitorPage }, use ) => {
		const request = visitorPage.request;
		await use( request );
	},
	visitorWooCommerceApi: async ( { visitorRequest }, use ) => {
		await use( new WooCommerceApi( { request: visitorRequest } ) );
	},

	// Front pages operated by visitor
	checkout: async ( { visitorPage }, use ) => {
		await use( new Checkout( { page: visitorPage } ) );
	},
	classicCheckout: async ( { visitorPage }, use ) => {
		await use( new ClassicCheckout( { page: visitorPage } ) );
	},
	payForOrder: async ( { visitorPage }, use ) => {
		await use( new PayForOrder( { page: visitorPage } ) );
	},
	orderReceived: async ( { visitorPage }, use ) => {
		await use( new OrderReceived( { page: visitorPage } ) );
	},
	customerAccount: async ( { visitorPage }, use ) => {
		await use( new CustomerAccount( { page: visitorPage } ) );
	},
	customerPaymentMethods: async ( { visitorPage }, use ) => {
		await use( new CustomerPaymentMethods( { page: visitorPage } ) );
	},
	customerSubscriptions: async ( { visitorPage }, use ) => {
		await use( new CustomerSubscriptions( { page: visitorPage } ) );
	},
	mollieHostedCheckout: async ( { visitorPage }, use ) => {
		await use( new MollieHostedCheckout( { page: visitorPage } ) );
	},
	product: async ( { visitorPage }, use ) => {
		await use( new Product( { page: visitorPage } ) );
	},
	cart: async ( { visitorPage }, use ) => {
		await use( new Cart( { page: visitorPage } ) );
	},

	// Complex fixtures
	utils: async (
		{
			mollieApi,
			mollieApiMethod,
			plugins,
			wooCommerceUtils,
			requestUtils,
			wooCommerceApi,
			visitorWooCommerceApi,
			mollieSettingsApiKeys,
			mollieSettingsAdvanced,
		},
		use
	) => {
		await use(
			new Utils( {
				mollieApi,
				mollieApiMethod,
				plugins,
				wooCommerceUtils,
				requestUtils,
				wooCommerceApi,
				visitorWooCommerceApi,
				mollieSettingsApiKeys,
				mollieSettingsAdvanced,
			} )
		);
	},
} );

export { test, expect, TestBaseExtend };
