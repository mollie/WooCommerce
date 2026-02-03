/**
 * Internal dependencies
 */
import { MollieSettings } from './types';

export const mollieApiKeys: {
	[ key: string ]: MollieSettings.ApiKeys;
} = {
	empty: {
		testModeEnabled: 'yes',
		liveApiKey: '',
		testApiKey: '',
	},
	default: {
		testModeEnabled: 'yes',
		liveApiKey: process.env.MOLLIE_LIVE_API_KEY,
		testApiKey: process.env.MOLLIE_TEST_API_KEY,
	},
};
