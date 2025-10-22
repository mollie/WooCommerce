/**
 * External dependencies
 */
import fs from 'fs';
import { APIRequestContext, Page, VideoMode, ViewportSize } from '@playwright/test';
import {
	test as base,
	CustomerAccount,
	CustomerPaymentMethods,
	expect,
	OrderReceived,
	WooCommerceApi,
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
} from './frontend';
import { MollieApi, Utils } from '.';
import { MollieSettings } from '../resources';

type TestBaseExtend = {
	recordVideoOptions: {
		mode: VideoMode;
		size?: ViewportSize;
	}
	// Dashboard fixtures
	mollieApi: MollieApi;
	mollieSettingsApiKeys: MollieSettingsApiKeys;
	mollieSettingsPaymentMethods: MollieSettingsPaymentMethods;
	mollieSettingsAdvanced: MollieSettingsAdvanced;
	mollieSettingsGateway: MollieSettingsGateway;
	mollieApiMethod?: MollieSettings.ApiMethod;

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

	// Complex fixtures
	utils: Utils;
};

const test = base.extend< TestBaseExtend >( {
	recordVideoOptions: [ null, { option: true } ],
	// Dashboard pages operated by Admin
	mollieApi: async ( { request, requestUtils }, use ) => {
		await use( new MollieApi( { request, requestUtils } ) );
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
				}
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
	mollieHostedCheckout: async ( { visitorPage }, use ) => {
		await use( new MollieHostedCheckout( { page: visitorPage } ) );
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
