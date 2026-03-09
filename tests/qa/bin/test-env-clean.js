#!/usr/bin/env node
/**
 * External dependencies
 */
const { execSync } = require( 'child_process' );

const commands = [
	{
		description: 'Activate storefront theme',
		command: 'wp-env run tests-cli wp theme deactivate storefront',
	},
	{
		description: 'Uninstall WooCommerce',
		command: 'wp-env run tests-cli -- wp plugin delete woocommerce',
	},
];

console.log( 'Cleaning test environment...\n' );

commands.forEach( ( item, index ) => {
	try {
		console.log( `${ index + 1 }. ${ item.description }` );
		execSync( item.command, { stdio: 'inherit' } );
		console.log( '✅ Success\n' );
	} catch ( error ) {
		console.error( `❌ Failed: ${ item.description }` );
		console.error( `Command: ${ item.command }` );
		console.error( `Error: ${ error.message }\n` );
		process.exit( 1 );
	}
} );

console.log( '🧹 Test environment clean complete!' );
