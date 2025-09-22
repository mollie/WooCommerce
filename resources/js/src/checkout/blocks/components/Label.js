export const Label = ( { item } ) => {
	return (
		<>
			<span style={ { marginRight: '1em' } }>{ item.label.title }</span>
			{ item.label.icon && <img src={ item.label.icon } alt="" /> }
		</>
	);
};
