/**
 * External dependencies
 */
import type { Project } from '@playwright/test';
/**
 * Internal dependencies
 */
import type { TestBaseExtend } from './utils';
import { MollieSettings } from './resources';

/**
 * Types
 */
type ApiMethod = MollieSettings.ApiMethod;

type ShardProject = Project< TestBaseExtend >;

type MollieState = keyof typeof SETUP_STATES;

interface Shard {
	/** Shard name, appended after `shard:<api>-api:`. */
	shard: string;
	/** Mollie state the shard needs; resolves to its setup project. */
	state: MollieState;
	testMatch: RegExp;
	grep?: RegExp;
	grepInvert?: RegExp;
	fullyParallel?: boolean;
	/** Run in multistep checkout mode (sets `use.isMultistepCheckout`). */
	multistep?: boolean;
	/** Skip under the Orders API (behaviour is API-method-agnostic). */
	paymentOnly?: boolean;
}

/**
 * Distinct Mollie environment states, named after the resulting environment.
 * Composed states reuse the WooCommerce checkout-layout tasks (02-woocommerce)
 * and/or the multistep-checkout task (04-multistep) together with the
 * layout-agnostic Mollie reconnect (03-mollie); the rest map to a single
 * self-contained task.
 */
const SETUP_STATES = {
	'mollie:uninstalled': {
		testMatch: /03-mollie\.setup\.ts/,
		grep: /setup:mollie:uninstalled;/,
	},
	// Installed but not connected to the Mollie API.
	'mollie:disconnected': {
		testMatch: /03-mollie\.setup\.ts/,
		grep: /setup:mollie:disconnected;/,
	},
	'mollie:reconnected': {
		testMatch: /03-mollie\.setup\.ts/,
		grep: /setup:mollie:reconnected;/,
	},
	'mollie:reconnected:block': {
		testMatch: /(02-woocommerce|03-mollie)\.setup\.ts/,
		grep: /setup:checkout:block;|setup:mollie:reconnected;/,
	},
	'mollie:reconnected:classic': {
		testMatch: /(02-woocommerce|03-mollie)\.setup\.ts/,
		grep: /setup:checkout:classic;|setup:mollie:reconnected;/,
	},
	'mollie:reconnected:block:card-components-disabled': {
		testMatch: /(02-woocommerce|03-mollie)\.setup\.ts/,
		grep: /setup:checkout:block;|setup:mollie:card-disabled;/,
	},
	'mollie:reconnected:classic:card-components-disabled': {
		testMatch: /(02-woocommerce|03-mollie)\.setup\.ts/,
		grep: /setup:checkout:classic;|setup:mollie:card-disabled;/,
	},
	'mollie:reconnected:classic:subscription': {
		testMatch: /(02-woocommerce|03-mollie)\.setup\.ts/,
		grep: /setup:checkout:classic;|setup:mollie:subscription;/,
	},
	// Multistep checkout (Germanized + Germanized Pro + multistep toggle, via
	// 04-multistep) layered on the block/classic layout.
	'mollie:reconnected:block:multistep': {
		testMatch: /(02-woocommerce|03-mollie|04-multistep)\.setup\.ts/,
		grep: /setup:checkout:block;|setup:multistep:checkout;|setup:mollie:reconnected;/,
	},
	'mollie:reconnected:classic:multistep': {
		testMatch: /(02-woocommerce|03-mollie|04-multistep)\.setup\.ts/,
		grep: /setup:checkout:classic;|setup:multistep:checkout;|setup:mollie:reconnected;/,
	},
} as const;

const shards: Shard[] = [
	{
		shard: 'plugin-foundation',
		state: 'mollie:uninstalled',
		testMatch: /01-plugin-foundation\/.*\.spec\.ts/,
		paymentOnly: true,
	},
	{
		shard: 'merchant-setup',
		state: 'mollie:reconnected',
		testMatch: /02-merchant-setup\/.*\.spec\.ts/,
		paymentOnly: true,
	},
	{
		shard: 'plugin-settings:advanced',
		state: 'mollie:reconnected:classic',
		testMatch: /03-plugin-settings\/mollie-settings-advanced\.spec\.ts/,
		paymentOnly: true,
	},
	{
		shard: 'plugin-settings:api-keys',
		state: 'mollie:disconnected',
		testMatch: /03-plugin-settings\/mollie-settings-api-keys\.spec\.ts/,
		paymentOnly: true,
	},
	{
		shard: 'plugin-settings:gateway',
		state: 'mollie:reconnected',
		testMatch: /03-plugin-settings\/mollie-settings-gateway\.spec\.ts/,
		paymentOnly: true,
	},
	{
		shard: 'plugin-settings:surcharge',
		state: 'mollie:reconnected:classic',
		testMatch: /03-plugin-settings\/surcharge\/surcharge\.spec\.ts/,
		paymentOnly: true,
	},
	{
		shard: 'frontend-ui',
		state: 'mollie:reconnected:classic',
		testMatch: /04-frontend-ui\/.*\.spec\.ts/,
	},
	{
		shard: 'transaction:eur-block',
		state: 'mollie:reconnected:block',
		testMatch: /05-transaction\/eur-block\.spec\.ts/,
		fullyParallel: true,
	},
	{
		shard: 'transaction:eur-classic',
		state: 'mollie:reconnected:classic',
		testMatch: /05-transaction\/eur-classic\.spec\.ts/,
		fullyParallel: true,
	},
	// Non-EUR specs mutate the WC store currency, so their tests must run
	// sequentially (fullyParallel: false) to avoid racing on shared state.
	{
		shard: 'transaction:non-eur-block',
		state: 'mollie:reconnected:block',
		testMatch: /05-transaction\/non-eur-block\.spec\.ts/,
		fullyParallel: false,
	},
	{
		shard: 'transaction:non-eur-classic',
		state: 'mollie:reconnected:classic',
		testMatch: /05-transaction\/non-eur-classic\.spec\.ts/,
		fullyParallel: false,
	},
	{
		shard: 'transaction:eur-block-card-disabled-components',
		state: 'mollie:reconnected:block:card-components-disabled',
		testMatch: /05-transaction\/eur-block-card-disabled-components\.spec\.ts/,
	},
	{
		shard: 'transaction:eur-classic-card-disabled-components',
		state: 'mollie:reconnected:classic:card-components-disabled',
		testMatch: /05-transaction\/eur-classic-card-disabled-components\.spec\.ts/,
	},
	{
		shard: 'refund',
		state: 'mollie:reconnected:block',
		testMatch: /refund\.spec\.ts/,
		fullyParallel: true,
	},
	{
		shard: 'subscription',
		state: 'mollie:reconnected:classic:subscription',
		testMatch: /07-subscription\/.*\.spec\.ts/,
	},
	// Multistep: same transaction specs, isMultistepCheckout drives the extra
	// checkout screens. Non-EUR stays sequential for the same
	// currency-mutation reason as the regular transaction shards.
	{
		shard: 'multistep:eur-block',
		state: 'mollie:reconnected:block:multistep',
		testMatch: /05-transaction\/eur-block\.spec\.ts/,
		grep: /Transaction/,
		grepInvert: /Transaction - Pay for order/,
		fullyParallel: true,
		multistep: true,
	},
	{
		shard: 'multistep:eur-classic',
		state: 'mollie:reconnected:classic:multistep',
		testMatch: /05-transaction\/eur-classic\.spec\.ts/,
		grep: /Transaction/,
		grepInvert: /Transaction - Pay for order/,
		fullyParallel: true,
		multistep: true,
	},
	{
		shard: 'multistep:non-eur-block',
		state: 'mollie:reconnected:block:multistep',
		testMatch: /05-transaction\/non-eur-block\.spec\.ts/,
		grep: /Transaction/,
		grepInvert: /Transaction - Pay for order/,
		fullyParallel: false,
		multistep: true,
	},
	{
		shard: 'multistep:non-eur-classic',
		state: 'mollie:reconnected:classic:multistep',
		testMatch: /05-transaction\/non-eur-classic\.spec\.ts/,
		grep: /Transaction/,
		grepInvert: /Transaction - Pay for order/,
		fullyParallel: false,
		multistep: true,
	},
];

/**
 * Shards that run for the given API method. Shards flagged `paymentOnly` are
 * unaffected by the Mollie API method, so they run only under the default
 * payment API instead of being duplicated across both methods.
 */
function shardsFor( api: ApiMethod ): Shard[] {
	return shards.filter( ( s ) => api === 'payment' || ! s.paymentOnly );
}

/**
 * Builds the per-state setup projects for a given Mollie API method, limited to
 * the states actually consumed by that API's shards. Each is a thin project
 * that greps the relevant setup task(s) and runs after the base
 * `setup-woocommerce`. The `mollieApiMethod` on the project drives how the
 * Mollie setup task configures the plugin (Payments vs Orders API).
 */
export function buildSetupProjects( api: ApiMethod ): ShardProject[] {
	const usedStates = new Set( shardsFor( api ).map( ( s ) => s.state ) );

	return ( Object.keys( SETUP_STATES ) as MollieState[] )
		.filter( ( state ) => usedStates.has( state ) )
		.map( ( state ) => {
			const { testMatch, grep } = SETUP_STATES[ state ];

			const project: ShardProject = {
				name: `setup:${ api }-api:${ state }`,
				testMatch,
				grep,
				dependencies: [ 'setup-woocommerce' ],
				fullyParallel: false,
			};

			if ( api === 'order' ) {
				project.use = { mollieApiMethod: 'order' };
			}

			return project;
		} );
}

/**
 * Builds the project shards for a given Mollie API method. Each shard depends
 * on its matching per-state setup project; order-api shards run against the
 * Orders API and multistep shards run in multistep checkout mode.
 */
export function buildShards( api: ApiMethod ): ShardProject[] {
	return shardsFor( api ).map( ( { shard, state, multistep, ...rest } ) => {
		const project: ShardProject = {
			name: `shard:${ api }-api:${ shard }`,
			dependencies: [ `setup:${ api }-api:${ state }` ],
			...rest,
		};

		if ( api === 'order' || multistep ) {
			project.use = {
				...( api === 'order' ? { mollieApiMethod: 'order' } : {} ),
				...( multistep ? { isMultistepCheckout: true } : {} ),
			};
		}

		return project;
	} );
}