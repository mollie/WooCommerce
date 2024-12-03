/**
 * External dependencies
 */
import fs from 'fs';
import { APIRequestContext, Page } from '@playwright/test';
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
import { MollieApi, Transaction, Utils } from '.';

const test = base.extend< {
	// Dashboard fixtures
	mollieApi: MollieApi;
	mollieSettingsApiKeys: MollieSettingsApiKeys;
	mollieSettingsPaymentMethods: MollieSettingsPaymentMethods;
	mollieSettingsAdvanced: MollieSettingsAdvanced;
	mollieSettingsGateway: MollieSettingsGateway;

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
	transaction: Transaction;
} >( {
	// Dashboard pages operated by Admin
	mollieApi: async ( { request }, use ) => {
		await use( new MollieApi( { request } ) );
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
	wooCommerceOrderEdit: async ( { page }, use ) => {
		await use( new WooCommerceOrderEdit( { page } ) );
	},

	visitorPage: async ( { browser }, use, testInfo ) => {
		// check if visitor is specified in test otherwise use guest
		const storageStateName =
			testInfo.annotations?.find( ( el ) => el.type === 'visitor' )
				?.description || 'guest';
		const storageStatePath = `${ process.env.STORAGE_STATE_PATH }/${ storageStateName }.json`;
		// apply current visitor's storage state to the context
		const context = await browser.newContext( {
			storageState: fs.existsSync( storageStatePath )
				? storageStatePath
				: undefined,
		} );
		const page = await context.newPage();
		await use( page );
		await page.close();
		await context.close();
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
	transaction: async (
		{
			wooCommerceUtils,
			orderReceived,
			checkout,
			classicCheckout,
			payForOrder,
			mollieHostedCheckout,
			utils,
		},
		use
	) => {
		await use(
			new Transaction( {
				wooCommerceUtils,
				orderReceived,
				checkout,
				classicCheckout,
				payForOrder,
				mollieHostedCheckout,
				utils,
			} )
		);
	},
} );

export { test, expect };
