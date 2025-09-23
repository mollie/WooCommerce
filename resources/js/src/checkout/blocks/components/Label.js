export const Label = ( { item } ) => {
	return (
		<>
			<span style={ { marginRight: '1em' } }>{ item.label.title }</span>
			{ item.label.iconsArray && item.label.iconsArray.length > 0 && (
				<span className="mollie-icons-container">
					{ item.label.iconsArray.map( ( icon, index ) => (
						<img
							src={ icon.src }
							alt={ icon.alt }
							className="mollie-gateway-icon"
						/>
					) ) }
				</span>
			) }
		</>
	);
};
