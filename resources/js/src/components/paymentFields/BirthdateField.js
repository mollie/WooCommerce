export const BirthdateField = ({ label, value, onChange }) => {
    const handleChange = (e) => onChange(e.target.value);
    const className = "wc-block-components-text-input wc-block-components-address-form__billing-birthdate";

    return (
        <div className="custom-input">
            <label htmlFor="billing-birthdate" dangerouslySetInnerHTML={{ __html: label }}></label>
            <input
                type="date"
                className={className}
                name="billing-birthdate"
                id="billing-birthdate"
                value={value}
                onChange={handleChange}
            />
        </div>
    );
};
