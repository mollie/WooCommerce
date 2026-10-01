/**
 * What the block editor shows for the Express Component: a static box, no session, no mollie.js.
 *
 * @param {Object} props
 * @param {string} props.label The localized placeholder text.
 */
export const MollieExpressEditorComponent = ( {
	label = 'Express checkout',
} ) => (
	<div
		className="mollie-express-component mollie-express-component--editor"
		style={ {
			minHeight: '48px',
			border: '1px dashed currentColor',
			borderRadius: '4px',
			display: 'flex',
			alignItems: 'center',
			justifyContent: 'center',
			opacity: 0.6,
		} }
	>
		{ label }
	</div>
);

export default MollieExpressEditorComponent;
