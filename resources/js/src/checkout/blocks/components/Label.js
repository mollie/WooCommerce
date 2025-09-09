export const Label = ( { item } ) => {
	return (
		<>
			<div dangerouslySetInnerHTML={ { __html: item.label } } />
		</>
	);
};
