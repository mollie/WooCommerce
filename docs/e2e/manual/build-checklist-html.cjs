// Builds an interactive HTML test-run page from the manual checklist Markdown.
// Usage (from the repo root): node docs/e2e/manual/build-checklist-html.cjs [name] [title]
// name: Markdown file name without .md in this folder (default 8.2.0-phase2-manual-tests); the HTML and the results key use the same name.
// Results are kept in the browser (localStorage), so rebuilding the page keeps them.
const fs = require( 'fs' );
const path = require( 'path' );

const root = path.resolve( __dirname, '../../..' );
const md = require( path.join( root, 'node_modules/markdown-it' ) )( {
	html: false,
	linkify: true,
} );

const name = process.argv[ 2 ] || '8.2.0-phase2-manual-tests';
const title = process.argv[ 3 ] || 'Mollie 8.2.0 Test Run';
const src = path.join( __dirname, name + '.md' );
const out = path.join( __dirname, name + '.html' );
const exportName = name === '8.2.0-phase2-manual-tests' ? 'mollie-8.2.0-phase2-results' : name + '-results';
const body = md.render( fs.readFileSync( src, 'utf8' ) );
const built = new Date().toISOString().slice( 0, 16 ).replace( 'T', ' ' );

const page = `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${ title }</title>
<style>
:root{--bg:#fff;--fg:#1a1a1a;--muted:#666;--line:#ddd;--card:#fafafa;--code:#f0f0f0;
--pass:#1e7d32;--pass-bg:#e8f5e9;--fail:#c62828;--fail-bg:#fdecea;--block:#b26a00;--block-bg:#fff4e0;--none:#888;--accent:#2457c5}
@media (prefers-color-scheme: dark){:root{--bg:#16181c;--fg:#e6e6e6;--muted:#9aa0a6;--line:#33363c;--card:#1e2126;--code:#2a2d33;
--pass:#6fcf75;--pass-bg:#1d3320;--fail:#ff8a80;--fail-bg:#3a1f1f;--block:#ffb74d;--block-bg:#3a2d17;--none:#888;--accent:#8ab4f8}}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--fg);font:15px/1.5 "Segoe UI",Arial,sans-serif}
main{max-width:1000px;margin:0 auto;padding:0 16px 80px}
h1{font-size:24px;margin-top:20px}h2{font-size:20px;border-bottom:1px solid var(--line);padding-bottom:4px;margin-top:32px}
h3{font-size:17px;margin-top:24px}h4{font-size:15px;margin-top:18px}
code{font-family:Consolas,monospace;font-size:13px;background:var(--code);padding:0 4px;border-radius:3px;word-break:break-word}
table{border-collapse:collapse;width:100%;font-size:14px;display:block;overflow-x:auto}td,th{border:1px solid var(--line);padding:4px 8px;vertical-align:top}
a{color:var(--accent)}
#bar{position:sticky;top:0;z-index:5;background:var(--bg);border-bottom:1px solid var(--line);padding:8px 16px}
#bar .row{max-width:1000px;margin:0 auto;display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.stat{font-weight:600;padding:2px 8px;border-radius:10px;font-size:13px}
.stat.pass{color:var(--pass);background:var(--pass-bg)}.stat.fail{color:var(--fail);background:var(--fail-bg)}
.stat.blocked{color:var(--block);background:var(--block-bg)}.stat.none{color:var(--muted);background:var(--card)}
#bar button,#bar select,#bar label.btn{font:inherit;font-size:13px;padding:3px 10px;border:1px solid var(--line);background:var(--card);color:var(--fg);border-radius:6px;cursor:pointer}
#bar .spacer{flex:1}
li.case{list-style:none;margin:14px 0 14px -22px;padding:10px 12px;border:1px solid var(--line);border-left:5px solid var(--none);border-radius:6px;background:var(--card)}
li.case.pass{border-left-color:var(--pass)}li.case.fail{border-left-color:var(--fail)}li.case.blocked{border-left-color:var(--block)}
li.case.hidden{display:none}
.res{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-top:8px;padding-top:8px;border-top:1px dashed var(--line)}
.res button{font:inherit;font-size:13px;padding:3px 12px;border:1px solid var(--line);border-radius:14px;background:var(--bg);color:var(--fg);cursor:pointer}
.res button.on.pass{background:var(--pass);color:#fff;border-color:var(--pass)}
.res button.on.fail{background:var(--fail);color:#fff;border-color:var(--fail)}
.res button.on.blocked{background:var(--block);color:#fff;border-color:var(--block)}
.res .date{font-size:12px;color:var(--muted)}
.res textarea{width:100%;min-height:38px;font:inherit;font-size:13px;padding:6px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--fg);resize:vertical}
li.task{list-style:none;margin-left:-22px}
li.task input{margin-right:6px}
.meta{color:var(--muted);font-size:12px;margin-top:6px}
</style>
</head>
<body>
<div id="bar"><div class="row">
<span class="stat pass" id="cPass">0 pass</span><span class="stat fail" id="cFail">0 fail</span>
<span class="stat blocked" id="cBlocked">0 blocked</span><span class="stat none" id="cNone">0 not run</span>
<select id="filter" aria-label="Filter cases"><option value="all">All cases</option><option value="none">Not run</option>
<option value="pass">Pass</option><option value="fail">Fail</option><option value="blocked">Blocked</option></select>
<span class="spacer"></span>
<button id="expMd" title="Download results as a Markdown table">Export MD</button>
<button id="expJson" title="Download results as JSON (backup)">Export JSON</button>
<label class="btn" title="Load a JSON backup">Import JSON<input id="imp" type="file" accept="application/json" hidden></label>
<button id="reset" title="Clear all results in this browser">Reset</button>
</div></div>
<main>
<p class="meta">Page built ${ built } from ${ name }.md. Results are saved in this browser only. Use Export JSON as a backup.</p>
${ body }
</main>
<script>
(function () {
	var KEY = '${ name === '8.2.0-phase2-manual-tests' ? 'mollie-8.2.0-phase2-manual-results' : 'mollie-' + name + '-results' }';
	var STATES = [ [ 'pass', 'Pass' ], [ 'fail', 'Fail' ], [ 'blocked', 'Blocked' ] ];
	var filter = document.getElementById( 'filter' );
	var data = { cases: {}, tasks: {} };
	try { data = JSON.parse( localStorage.getItem( KEY ) ) || data; } catch ( e ) {}
	data.cases = data.cases || {}; data.tasks = data.tasks || {};
	function save() { try { localStorage.setItem( KEY, JSON.stringify( data ) ); } catch ( e ) {} }
	function today() { return new Date().toISOString().slice( 0, 10 ); }

	// Strip the "[ ] " / "[x] " prefix from a list item and return its state.
	function stripBox( li ) {
		var node = li.firstChild;
		// Loose lists wrap items in <p> after a whitespace text node
		while ( node && ( ( node.nodeType === 3 && ! node.nodeValue.trim() ) || ( node.nodeType === 1 && node.tagName === 'P' ) ) ) {
			node = node.nodeType === 3 ? node.nextSibling : node.firstChild;
		}
		if ( ! node || node.nodeType !== 3 ) { return null; }
		var m = node.nodeValue.match( /^\\[( |x)\\] / );
		if ( ! m ) { return null; }
		node.nodeValue = node.nodeValue.slice( 4 );
		return m[ 1 ] === 'x';
	}

	var cases = [];
	document.querySelectorAll( 'main li' ).forEach( function ( li ) {
		var strong = li.querySelector( 'strong' );
		var isCase = strong && /^[A-Z][A-Z0-9]*-\\d+$/.test( strong.textContent ) && /^\\s*\\[ \\] /.test( li.textContent );
		var checked = stripBox( li );
		if ( checked === null ) { return; }
		if ( isCase ) {
			var id = strong.textContent;
			var title = ( strong.nextSibling && strong.nextSibling.nodeValue || '' ).trim();
			li.classList.add( 'case' ); li.id = id; li.dataset.id = id;
			var r = data.cases[ id ] || ( data.cases[ id ] = { status: '', date: '', notes: '' } );
			var box = document.createElement( 'div' ); box.className = 'res';
			STATES.forEach( function ( s ) {
				var b = document.createElement( 'button' ); b.type = 'button'; b.textContent = s[ 1 ]; b.className = s[ 0 ];
				b.addEventListener( 'click', function () {
					r.status = r.status === s[ 0 ] ? '' : s[ 0 ];
					r.date = r.status ? today() : '';
					save(); paint( li, r ); count();
				} );
				box.appendChild( b );
			} );
			var date = document.createElement( 'span' ); date.className = 'date'; box.appendChild( date );
			var notes = document.createElement( 'textarea' ); notes.placeholder = 'Notes, order number, evidence'; notes.value = r.notes || '';
			notes.addEventListener( 'input', function () { r.notes = notes.value; save(); } );
			box.appendChild( notes );
			li.appendChild( box );
			cases.push( { li: li, id: id, title: title } );
			paint( li, r );
		} else {
			var key = li.textContent.trim().slice( 0, 80 );
			var cb = document.createElement( 'input' ); cb.type = 'checkbox';
			cb.checked = key in data.tasks ? data.tasks[ key ] : checked;
			cb.addEventListener( 'change', function () { data.tasks[ key ] = cb.checked; save(); } );
			li.classList.add( 'task' ); li.insertBefore( cb, li.firstChild );
		}
	} );

	function paint( li, r ) {
		li.classList.remove( 'pass', 'fail', 'blocked' );
		if ( r.status ) { li.classList.add( r.status ); }
		li.querySelectorAll( '.res button' ).forEach( function ( b ) { b.classList.toggle( 'on', b.classList.contains( r.status ) ); } );
		li.querySelector( '.res .date' ).textContent = r.date ? 'Set ' + r.date : '';
		applyFilter();
	}
	function count() {
		var c = { pass: 0, fail: 0, blocked: 0, none: 0 };
		cases.forEach( function ( x ) { c[ ( data.cases[ x.id ] || {} ).status || 'none' ]++; } );
		document.getElementById( 'cPass' ).textContent = c.pass + ' pass';
		document.getElementById( 'cFail' ).textContent = c.fail + ' fail';
		document.getElementById( 'cBlocked' ).textContent = c.blocked + ' blocked';
		document.getElementById( 'cNone' ).textContent = c.none + ' not run';
	}
	function applyFilter() {
		var f = filter.value;
		cases.forEach( function ( x ) {
			var s = ( data.cases[ x.id ] || {} ).status || 'none';
			x.li.classList.toggle( 'hidden', f !== 'all' && f !== s );
		} );
	}
	filter.addEventListener( 'change', applyFilter );

	function download( name, text, type ) {
		var a = document.createElement( 'a' );
		a.href = URL.createObjectURL( new Blob( [ text ], { type: type } ) ); a.download = name; a.click();
		setTimeout( function () { URL.revokeObjectURL( a.href ); }, 1000 );
	}
	document.getElementById( 'expJson' ).addEventListener( 'click', function () {
		download( '${ exportName }.json', JSON.stringify( data, null, 2 ), 'application/json' );
	} );
	document.getElementById( 'expMd' ).addEventListener( 'click', function () {
		var label = { pass: 'PASS', fail: 'FAIL', blocked: 'BLOCKED' };
		var rows = [ '| ID | Case | Result | Date | Notes |', '| --- | --- | --- | --- | --- |' ];
		cases.forEach( function ( x ) {
			var r = data.cases[ x.id ] || {};
			var n = ( r.notes || '' ).replace( /\\r?\\n/g, ' ' ).replace( /\\|/g, '/' );
			rows.push( '| ' + x.id + ' | ' + x.title.replace( /\\|/g, '/' ) + ' | ' + ( label[ r.status ] || 'NOT RUN' ) + ' | ' + ( r.date || '' ) + ' | ' + n + ' |' );
		} );
		download( '${ exportName }.md', rows.join( '\\n' ) + '\\n', 'text/markdown' );
	} );
	document.getElementById( 'imp' ).addEventListener( 'change', function ( e ) {
		var f = e.target.files[ 0 ]; if ( ! f ) { return; }
		f.text().then( function ( t ) {
			// Merge: imported cases replace only the same IDs, other results stay
			try { var d = JSON.parse( t ); Object.assign( data.cases, d.cases || {} ); Object.assign( data.tasks, d.tasks || {} ); save(); location.reload(); }
			catch ( err ) { alert( 'This file is not a results JSON.' ); }
		} );
	} );
	document.getElementById( 'reset' ).addEventListener( 'click', function () {
		if ( confirm( 'Clear all results in this browser? Export JSON first if you need them.' ) ) {
			try { localStorage.removeItem( KEY ); } catch ( e ) {}
			location.reload();
		}
	} );
	count();
})();
</script>
</body>
</html>
`;

fs.writeFileSync( out, page );
console.log( 'Written ' + out );
