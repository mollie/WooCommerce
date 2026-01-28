/**
 * External dependencies
 */
import { expect, WooCommerceApi } from '@inpsyde/playwright-utils/build';

type ExpectedNote = string | RegExp | { note: string | RegExp; count?: number };
type AssertOptions = {
	assertionPrefix?: string;
	clearHtmlTags?: boolean;
	isSoftAssertion?: boolean;
};

/**
 * Clears HTML tags from the string
 *
 * @param text
 */
const stripHtmlTags = ( text: string ): string =>
	text
		.replace( /<[^>]*>/g, '' )
		.replace( /\s+/g, ' ' )
		.trim();

/**
 * Asserts that the actual notes match the expected notes expected amount of times.
 *
 * @param actualNotes
 * @param expectedNotes
 * @param options
 */
const assertNotes = async (
	actualNotes: string[],
	expectedNotes: ExpectedNote[],
	options: AssertOptions = {}
) => {
	const {
		assertionPrefix = '',
		clearHtmlTags = true,
		isSoftAssertion = true,
	} = options;

	if ( clearHtmlTags ) {
		actualNotes = actualNotes.map( stripHtmlTags );
	}

	for ( const expected of expectedNotes ) {
		const note =
			typeof expected === 'string' || expected instanceof RegExp
				? expected
				: expected.note;
		const count =
			typeof expected === 'string' || expected instanceof RegExp
				? 1
				: expected.count ?? 1;

		const matches = actualNotes.filter( ( n ) =>
			note instanceof RegExp ? note.test( n ) : n.includes( note )
		);

		const expectFn = isSoftAssertion ? expect.soft : expect;
		await expectFn(
			matches,
			`${ assertionPrefix }Assert note "${ note }" is present ${ count } time(s)`
		).toHaveLength( count );
	}
};

/**
 * Asserts order notes for a given order ID.
 *
 * @param wooCommerceApi
 * @param orderId
 * @param expectedNotes
 * @param options
 */
export const assertOrderNotes = async (
	wooCommerceApi: WooCommerceApi,
	orderId: number,
	expectedNotes: ExpectedNote[],
	options?: AssertOptions
) => {
	const orderNotes = await wooCommerceApi.getOrderNotes( orderId );
	const notes = orderNotes.map( ( orderNote ) => orderNote.note );
	await assertNotes( notes, expectedNotes, options );
};

/**
 * Asserts subscription notes for a given subscription ID.
 *
 * @param wooCommerceApi
 * @param subscriptionId
 * @param expectedNotes
 * @param options
 */
export const assertSubscriptionNotes = async (
	wooCommerceApi: WooCommerceApi,
	subscriptionId: number,
	expectedNotes: ExpectedNote[],
	options?: AssertOptions
) => {
	const subscriptionNotes = await wooCommerceApi.getSubscriptionNotes(
		subscriptionId
	);
	const notes = subscriptionNotes.map( ( note ) => note.note );
	await assertNotes( notes, expectedNotes, options );
};
