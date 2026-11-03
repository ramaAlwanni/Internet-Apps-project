const CustomInput = ({
    type = "text",
    placeholder,
    icon: Icon,
    value,
    onChange,
}) => {
    return (
        <div className="relative w-full mb-5">
            <input
                type={type}
                placeholder={placeholder}
                value={value}
                onChange={onChange}
                className="w-full bg-[#f4d6db]/40 border border-[#823945]/30 rounded-full py-3 pl-5 pr-10 text-[#5c212c] placeholder-[#823945]/70 focus:outline-none focus:border-[#823945] transition-all text-sm font-medium shadow-inner autofill:bg-transparent autofill:text-[#5c212c]"
                style={{
                    WebkitBoxShadow: "0 0 0 30px transparent inset",
                    WebkitTextFillColor: "#5c212c",
                }}
            />
            {Icon && (
                <div className="absolute right-4 top-1/2 -translate-y-1/2 text-[#823945]">
                    <Icon className="w-4 h-4" />
                </div>
            )}
        </div>
    );
};

export default CustomInput;
