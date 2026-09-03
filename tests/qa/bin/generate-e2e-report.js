#!/usr/bin/env node
/**
 * External dependencies
 */
const path = require( 'path' );
const fs = require( 'fs' );
const { chromium } = require( 'playwright-core' );

const REPO_ROOT = path.resolve( __dirname, '..', '..', '..' );
// Pass a report folder name (relative to repo root) as the first CLI arg to
// scrape a non-default report, e.g. `playwright-report-order-api`.
const REPORT_DIR = process.argv[ 2 ] || 'playwright-report';
const REPORT_HTML = path.join( REPO_ROOT, REPORT_DIR, 'index.html' );
const OUTPUT_DIR = path.join( REPO_ROOT, 'docs', 'e2e', 'reports' );

// Version currently under QA. Update per run (matches the `dev/qa/updates-for-x.y.z` branch).
const VERSION = '8.1.10';

const PROJECT_DISPLAY_NAMES = {
	'payment-api': 'Payment API',
	'order-api': 'Order API',
	'refund-payment-api': 'Refund Payment API',
	'refund-order-api': 'Refund Order API',
	'multistep-payment-api': 'Multistep Payment API',
	'multistep-order-api': 'Multistep Order API',
};

const OUTCOME_STATUS = {
	expected: 'Passed',
	unexpected: 'Failed',
	flaky: 'Flaky',
	skipped: 'Skipped',
};

const STATUS_ICON = {
	Passed: '✅',
	Failed: '❌',
	Flaky: '⚠️',
	Skipped: '⏭️',
};

const CASE_ID_PATTERN = /^C(\d+)\s*\|\s*(.+)$/;

function projectDisplayName( projectId ) {
	if ( PROJECT_DISPLAY_NAMES[ projectId ] ) {
		return PROJECT_DISPLAY_NAMES[ projectId ];
	}
	return projectId
		.split( '-' )
		.map( ( word ) => word.charAt( 0 ).toUpperCase() + word.slice( 1 ) )
		.join( ' ' );
}

function topFolder( fileName ) {
	return fileName.split( '/' )[ 0 ];
}

function folderSortKey( folder ) {
	const match = folder.match( /^(\d+)/ );
	return match ? Number( match[ 1 ] ) : Number.MAX_SAFE_INTEGER;
}

function sortedFolderKeys( folders ) {
	return Object.keys( folders ).sort( ( a, b ) => {
		const keyA = folderSortKey( a );
		const keyB = folderSortKey( b );
		return keyA !== keyB ? keyA - keyB : a.localeCompare( b );
	} );
}

function slugify( text ) {
	return text
		.toLowerCase()
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^-+|-+$/g, '' );
}

function filterFolders( folders, predicate ) {
	const result = {};
	for ( const [ folder, files ] of Object.entries( folders ) ) {
		for ( const [ file, rows ] of Object.entries( files ) ) {
			const matching = rows.filter( predicate );
			if ( matching.length ) {
				result[ folder ] = result[ folder ] || {};
				result[ folder ][ file ] = matching;
			}
		}
	}
	return result;
}

async function scrapeReport() {
	if ( ! fs.existsSync( REPORT_HTML ) ) {
		throw new Error( `No report found at ${ REPORT_HTML }. Run the Playwright tests first.` );
	}

	const browser = await chromium.launch();
	const page = await browser.newPage();
	const url = 'file:///' + REPORT_HTML.replace( /\\/g, '/' );
	await page.goto( url );
	await page.waitForSelector( '.chip-header' );

	const scrapeCurrentView = async () => {
		const collapsedHeaders = await page.$$( '.chip-header[aria-expanded="false"]' );
		for ( const header of collapsedHeaders ) {
			await header.click();
		}
		await page.waitForTimeout( 300 );

		return page.evaluate( () => {
			const chips = Array.from( document.querySelectorAll( '.chip' ) );
			const out = [];
			for ( const chip of chips ) {
				const fileName = chip.querySelector( '.chip-header-allow-selection' )?.textContent || '';
				const testEls = chip.querySelectorAll( '.test-file-test' );
				for ( const testEl of testEls ) {
					const outcomeClass = Array.from( testEl.classList ).find( ( c ) =>
						c.startsWith( 'test-file-test-outcome-' )
					);
					const outcome = outcomeClass ? outcomeClass.replace( 'test-file-test-outcome-', '' ) : 'unknown';
					const title = testEl.querySelector( '.test-file-title' )?.textContent || '';
					const labels = Array.from( testEl.querySelectorAll( '.label' ) ).map( ( l ) => l.textContent );
					out.push( { fileName, outcome, title, labels } );
				}
			}
			return out;
		} );
	};

	// The default view doesn't render skipped rows at all (confirmed empirically:
	// the "All" count excludes them entirely, not just visually collapses them) -
	// they only appear once the report is filtered to them. Flaky is filtered the
	// same way in the UI, so it's fetched defensively too even though no run so far
	// has had any - better than silently dropping them if one ever does.
	const allRows = [ ...( await scrapeCurrentView() ) ];
	for ( const filter of [ 'skipped', 'flaky' ] ) {
		await page.evaluate( ( f ) => {
			window.location.hash = `#?q=s:${ f }`;
		}, filter );
		await page.waitForTimeout( 500 );
		allRows.push( ...( await scrapeCurrentView() ) );
	}

	await browser.close();
	return allRows;
}

function parseTitle( rawTitle ) {
	const segments = rawTitle.split( '›' ).map( ( s ) => s.trim() );
	const lastSegment = segments[ segments.length - 1 ];
	const match = lastSegment.match( CASE_ID_PATTERN );
	if ( match ) {
		return { id: `C${ match[ 1 ] }`, title: match[ 2 ].trim() };
	}
	return { id: '', title: lastSegment };
}

function resolveProject( labels ) {
	// The report always renders the project name as the first label on a test row.
	return labels[ 0 ] || 'unknown';
}

function statusCell( status ) {
	const icon = STATUS_ICON[ status ];
	return icon ? `${ icon } ${ status }` : status;
}

function stripStatusIcon( statusCellText ) {
	for ( const icon of Object.values( STATUS_ICON ) ) {
		if ( statusCellText.startsWith( icon ) ) {
			return statusCellText.slice( icon.length ).trim();
		}
	}
	return statusCellText.trim();
}

function buildReportsByProject( rawRows ) {
	const byProject = {};

	for ( const row of rawRows ) {
		const project = resolveProject( row.labels );
		if ( project.startsWith( 'setup-' ) ) {
			continue;
		}
		const status = OUTCOME_STATUS[ row.outcome ] || row.outcome;
		const { id, title } = parseTitle( row.title );
		const folder = topFolder( row.fileName );

		if ( ! byProject[ project ] ) {
			byProject[ project ] = {};
		}
		if ( ! byProject[ project ][ folder ] ) {
			byProject[ project ][ folder ] = {};
		}
		if ( ! byProject[ project ][ folder ][ row.fileName ] ) {
			byProject[ project ][ folder ][ row.fileName ] = [];
		}
		byProject[ project ][ folder ][ row.fileName ].push( { id, title, status } );
	}

	return byProject;
}

function parseExistingReport( markdown ) {
	const folders = {};
	let currentFolder = null;
	let currentFile = null;

	// Only the per-file tables between these markers are canonical test data; the
	// table of contents / summary / failed-tests sections are derived and re-rendered
	// fresh every run, so they must never be read back in as source rows. Reports
	// written before this marker existed have no TOC/summary to misparse, so treat the
	// whole file as data for those (backwards compatibility for the first re-render).
	const hasMarkers = markdown.includes( '<!-- BEGIN DATA -->' );
	let inData = ! hasMarkers;

	for ( const line of markdown.split( '\n' ) ) {
		if ( hasMarkers ) {
			if ( line.trim() === '<!-- BEGIN DATA -->' ) {
				inData = true;
				continue;
			}
			if ( line.trim() === '<!-- END DATA -->' ) {
				inData = false;
				continue;
			}
		}
		if ( ! inData ) {
			continue;
		}

		const folderMatch = line.match( /^## (?:<a[^>]*><\/a>)?(.+)$/ );
		if ( folderMatch ) {
			currentFolder = folderMatch[ 1 ].trim();
			folders[ currentFolder ] = folders[ currentFolder ] || {};
			currentFile = null;
			continue;
		}

		const fileMatch = line.match( /^### (?:<a[^>]*><\/a>)?(.+)$/ );
		if ( fileMatch ) {
			currentFile = fileMatch[ 1 ].trim();
			if ( currentFolder ) {
				folders[ currentFolder ][ currentFile ] = folders[ currentFolder ][ currentFile ] || [];
			}
			continue;
		}

		if ( line.startsWith( '|' ) && line.endsWith( '|' ) && currentFolder && currentFile ) {
			// Split on pipes that aren't escaped (a literal "|" inside a title is rendered as "\|").
			const cells = line.slice( 1, -1 ).split( /(?<!\\)\|/ ).map( ( c ) => c.trim() );
			if ( cells.length === 3 ) {
				const [ id, rawTitle, rawStatus ] = cells;
				if ( id === 'ID' || /^-+$/.test( id ) ) {
					continue; // header / separator row
				}
				const title = rawTitle.replace( /\\\|/g, '|' );
				const status = stripStatusIcon( rawStatus );
				folders[ currentFolder ][ currentFile ].push( { id, title, status } );
			}
		}
	}

	return folders;
}

function mergeFolders( existingFolders, newFolders ) {
	const byId = new Map();
	for ( const [ folder, files ] of Object.entries( existingFolders ) ) {
		for ( const [ file, rows ] of Object.entries( files ) ) {
			rows.forEach( ( row, index ) => {
				if ( row.id ) {
					byId.set( row.id, { folder, file, index } );
				}
			} );
		}
	}

	for ( const [ folder, files ] of Object.entries( newFolders ) ) {
		for ( const [ file, rows ] of Object.entries( files ) ) {
			for ( const row of rows ) {
				const existingLocation = row.id && byId.get( row.id );
				if ( existingLocation ) {
					existingFolders[ existingLocation.folder ][ existingLocation.file ][ existingLocation.index ].status =
						row.status;
					continue;
				}

				existingFolders[ folder ] = existingFolders[ folder ] || {};
				existingFolders[ folder ][ file ] = existingFolders[ folder ][ file ] || [];
				existingFolders[ folder ][ file ].push( row );
				if ( row.id ) {
					byId.set( row.id, {
						folder,
						file,
						index: existingFolders[ folder ][ file ].length - 1,
					} );
				}
			}
		}
	}

	return existingFolders;
}

function renderMarkdown( project, folders ) {
	const displayName = projectDisplayName( project );
	const lines = [];

	lines.push( `# Test run for v${ VERSION } - ${ displayName }`, '' );

	const allRows = Object.values( folders )
		.flatMap( ( files ) => Object.values( files ) )
		.flat();
	const counts = allRows.reduce( ( acc, row ) => {
		acc[ row.status ] = ( acc[ row.status ] || 0 ) + 1;
		return acc;
	}, {} );
	const total = allRows.length;
	const sortedFolders = sortedFolderKeys( folders );

	// Table of contents
	lines.push( '## Table of Contents', '' );
	lines.push( '- [Summary](#summary)' );
	for ( const folder of sortedFolders ) {
		lines.push( `- [${ folder }](#${ slugify( folder ) })` );
		for ( const file of Object.keys( folders[ folder ] ).sort() ) {
			lines.push( `  - [${ file }](#${ slugify( file ) })` );
		}
	}
	lines.push( '- [Failed Tests](#failed-tests)', '' );

	// Summary
	lines.push( '## <a id="summary"></a>Summary', '' );
	lines.push( '| Status | Count | Rate |', '|--------|-------|------|' );
	for ( const status of Object.keys( STATUS_ICON ) ) {
		if ( ! counts[ status ] ) {
			continue;
		}
		const rate = ( ( counts[ status ] / total ) * 100 ).toFixed( 1 );
		lines.push( `| ${ statusCell( status ) } | ${ counts[ status ] } | ${ rate }% |` );
	}
	lines.push( `| **Total** | **${ total }** | 100% |`, '' );

	// Canonical per-file data - only content between these markers is read back in
	// as source rows on the next merge (see parseExistingReport).
	lines.push( '<!-- BEGIN DATA -->', '' );

	for ( const folder of sortedFolders ) {
		lines.push( `## <a id="${ slugify( folder ) }"></a>${ folder }`, '' );
		const files = folders[ folder ];
		const sortedFiles = Object.keys( files ).sort();
		for ( const file of sortedFiles ) {
			lines.push( `### <a id="${ slugify( file ) }"></a>${ file }`, '' );
			lines.push( '| ID | Title | Status |', '|----|-------|--------|' );
			for ( const row of files[ file ] ) {
				const escapedTitle = row.title.replace( /\|/g, '\\|' );
				lines.push( `| ${ row.id } | ${ escapedTitle } | ${ statusCell( row.status ) } |` );
			}
			lines.push( '' );
		}
	}

	lines.push( '<!-- END DATA -->', '' );

	// Failed tests, grouped by folder / file, for a quick end-of-report roundup.
	lines.push( '## <a id="failed-tests"></a>Failed Tests', '' );
	const failedFolders = filterFolders( folders, ( row ) => row.status === 'Failed' );
	const failedFolderKeys = sortedFolderKeys( failedFolders );
	if ( ! failedFolderKeys.length ) {
		lines.push( 'No failed tests.', '' );
	} else {
		for ( const folder of failedFolderKeys ) {
			lines.push( `### <a id="failed-${ slugify( folder ) }"></a>${ folder }`, '' );
			const files = failedFolders[ folder ];
			for ( const file of Object.keys( files ).sort() ) {
				lines.push( `**${ file }**`, '' );
				lines.push( '| ID | Title | Status |', '|----|-------|--------|' );
				for ( const row of files[ file ] ) {
					const escapedTitle = row.title.replace( /\|/g, '\\|' );
					lines.push( `| ${ row.id } | ${ escapedTitle } | ${ statusCell( row.status ) } |` );
				}
				lines.push( '' );
			}
		}
	}

	return lines.join( '\n' ).trimEnd() + '\n';
}

( async () => {
	console.log( `Reading ${ REPORT_DIR }...` );
	const rawRows = await scrapeReport();
	console.log( `Found ${ rawRows.length } test results.` );

	const byProject = buildReportsByProject( rawRows );

	fs.mkdirSync( OUTPUT_DIR, { recursive: true } );

	for ( const [ project, newFolders ] of Object.entries( byProject ) ) {
		const displayName = projectDisplayName( project );
		const outputPath = path.join( OUTPUT_DIR, `Test run for v${ VERSION } - ${ displayName }.md` );

		let folders = newFolders;
		if ( fs.existsSync( outputPath ) ) {
			const existingFolders = parseExistingReport( fs.readFileSync( outputPath, 'utf8' ) );
			folders = mergeFolders( existingFolders, newFolders );
			console.log( `Merging into existing ${ outputPath }` );
		}

		const markdown = renderMarkdown( project, folders );
		fs.writeFileSync( outputPath, markdown );
		console.log( `Wrote ${ outputPath }` );
	}

	console.log( 'Done.' );
} )().catch( ( err ) => {
	console.error( err );
	process.exit( 1 );
} );
