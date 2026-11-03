const ProductCard = ({ product, onViewDetails }) => {
    return (
        <div className="w-full bg-[#fceee8]/60 backdrop-blur-sm rounded-[24px] p-3.5 border border-white/40 shadow-sm flex flex-col justify-between hover:shadow-md transition-shadow">
            <div>
                <div className="w-full h-36 rounded-[18px] overflow-hidden mb-3 border border-white/30 bg-gray-100">
                    <img
                        src={product.photo_url}
                        alt={product.name}
                        className="w-full h-full object-cover"
                    />
                </div>
                <span className="inline-block px-3 py-0.5 rounded-full bg-white/50 text-[10px] font-semibold text-[#823945] mb-1.5">
                    {product.category?.name || "Electronics"}
                </span>
                <h3 className="text-sm font-bold text-[#5c212c] truncate mb-1">
                    {product.name}
                </h3>
                <p className="text-base font-extrabold text-[#5c212c] mb-1">
                    ${product.price}
                </p>
                <div className="flex items-center gap-1.5 mb-3">
                    <span
                        className={`w-2 h-2 rounded-full ${product.stock > 0 ? "bg-emerald-500" : "bg-red-500"}`}
                    ></span>
                    <span className="text-[11px] font-medium text-[#732f3a]/80">
                        {product.stock > 0
                            ? `In Stock (${product.stock})`
                            : "Out of Stock"}
                    </span>
                </div>
            </div>

            <button
                type="button"
                onClick={() => onViewDetails(product)} 
                className="w-full py-2 px-4 rounded-full bg-gradient-to-r from-[#bf707c] via-[#a85764] to-[#823945] text-white text-xs font-semibold shadow-md hover:brightness-105 active:scale-[0.98] transition-all flex items-center justify-center gap-1 cursor-pointer"
            >
                <span>View Details</span>
                <span className="text-sm">→</span>
            </button>
        </div>
    );
};

export default ProductCard;
