export const Label = ({item}) => {
    console.log(item.label.title, item.label.icon); // Debugging purposes
    return (
        <>
            <span style={{marginRight: '1em'}}>{item.label.title}</span>
            {item.label.icon && <img src={item.label.icon} alt=""/>}
        </>
    );
};
