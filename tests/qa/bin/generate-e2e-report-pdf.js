#!/usr/bin/env node
/**
 * External dependencies
 */
const path = require( 'path' );
const fs = require( 'fs' );
const MarkdownIt = require( 'markdown-it' );
const { chromium } = require( 'playwright-core' );

const REPO_ROOT = path.resolve( __dirname, '..', '..', '..' );
const REPORTS_DIR = path.join( REPO_ROOT, 'docs', 'e2e', 'reports' );

const PAGE_TEMPLATE = ( title, body ) => `<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>${ title }</title>
<style>
	@page { size: A4; margin: 15mm 12mm; }
	body {
		font-family: -apple-system, Segoe UI, Helvetica, Arial, sans-serif;
		font-size: 11px;
		color: #1a1a1a;
		line-height: 1.4;
	}
	h1 { font-size: 20px; margin-bottom: 4px; }
	h2 {
		font-size: 15px;
		margin-top: 24px;
		padding-top: 4px;
		border-top: 2px solid #333;
	}
	h3 {
		font-size: 12px;
		margin-top: 14px;
		margin-bottom: 4px;
		color: #444;
	}
	a { color: #1a56c4; text-decoration: none; }
	ul { page-break-inside: avoid; margin: 2px 0; }
	li { margin: 1px 0; }
	table {
		width: 100%;
		border-collapse: collapse;
		margin-bottom: 10px;
		page-break-inside: auto;
	}
	thead { display: table-header-group; }
	tr { page-break-inside: avoid; }
	th, td {
		border: 1px solid #ccc;
		padding: 3px 6px;
		text-align: left;
		vertical-align: top;
	}
	th { background: #f0f0f0; }
	td:first-child, th:first-child { width: 90px; white-space: nowrap; }
	td:last-child, th:last-child { width: 70px; white-space: nowrap; }
	tr:nth-child(even) td { background: #fafafa; }
</style>
</head>
<body>
${ body }
</body>
</html>`;

( async () => {
	if ( ! fs.existsSync( REPORTS_DIR ) ) {
		throw new Error( `No reports found at ${ REPORTS_DIR }.` );
	}

	const mdFiles = fs.readdirSync( REPORTS_DIR ).filter( ( f ) => f.endsWith( '.md' ) );
	if ( ! mdFiles.length ) {
		throw new Error( `No .md reports found in ${ REPORTS_DIR }.` );
	}

	// html: true so the id anchors the report embeds for its table of contents
	// (e.g. `<a id="...">`) survive into the rendered page instead of being escaped.
	const md = new MarkdownIt( { html: true } );
	const browser = await chromium.launch();

	for ( const file of mdFiles ) {
		const title = file.replace( /\.md$/, '' );
		const markdown = fs.readFileSync( path.join( REPORTS_DIR, file ), 'utf8' );
		const html = PAGE_TEMPLATE( title, md.render( markdown ) );

		const page = await browser.newPage();
		await page.setContent( html, { waitUntil: 'load' } );
		const pdfPath = path.join( REPORTS_DIR, `${ title }.pdf` );
		await page.pdf( { path: pdfPath, format: 'A4', printBackground: true } );
		await page.close();

		console.log( `Wrote ${ pdfPath }` );
	}

	await browser.close();
} )().catch( ( err ) => {
	console.error( err );
	process.exit( 1 );
} );
