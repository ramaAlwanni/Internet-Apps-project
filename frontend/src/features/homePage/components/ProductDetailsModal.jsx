import { useState } from "react";
import { useDispatch } from "react-redux";
import { addToCart } from "../../../redux/slices/cartSlice"; 
import { X, ShoppingCart, Zap, Package } from "lucide-react";
import toast from "react-hot-toast";

const ProductDetailsModal = ({ isOpen, onClose, product }) => {
    const dispatch = useDispatch();
    const [quantity, setQuantity] = useState(1);
    const [isLoading, setIsLoading] = useState(false);

    if (!isOpen || !product) return null;

    const handleAddToCart = async () => {
        setIsLoading(true);
        const resultAction = await dispatch(
            addToCart({
                product_id: product.id,
                quantity: quantity,
            }),
        );
        setIsLoading(false);

        if (addToCart.fulfilled.match(resultAction)) {
            toast.success("Product added to cart successfully! 😊", {
                style: {
                    background: "#f8e1e5",
                    color: "#5c212c",
                    fontWeight: "bold",
                },
                iconTheme: {
                    primary: "#823945",
                    secondary: "#fff",
                },
            });
            onClose();
        } else {
            toast.error(resultAction.payload || "Failed to add to cart 😢");
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-md transition-opacity animate-fadeIn">
            <div className="relative w-full max-w-3xl bg-[#f8e1e5]/90 backdrop-blur-xl rounded-[32px] p-6 md:p-8 border border-white/60 shadow-2xl flex flex-col md:flex-row gap-6">
                <button
                    onClick={onClose}
                    className="absolute top-5 right-5 w-8 h-8 rounded-full bg-white/60 hover:bg-white text-[#5c212c] flex items-center justify-center transition-colors shadow-sm cursor-pointer z-10"
                >
                    <X className="w-4 h-4" />
                </button>

                <div className="w-full md:w-1/2 flex flex-col gap-4">
                    <div className="w-full h-72 rounded-[24px] overflow-hidden border border-white/50 shadow-md bg-gray-100">
                        <img
                            src={product.photo_url}
                            alt={product.name}
                            className="w-full h-full object-cover"
                        />
                    </div>
                </div>

                <div className="w-full md:w-1/2 flex flex-col justify-between pt-2">
                    <div>
                        <span className="inline-block px-3 py-1 rounded-full bg-white/60 text-xs font-semibold text-[#823945] mb-2 shadow-xs">
                            {product.category?.name || "Electronics"}
                        </span>

                        <h2 className="text-2xl font-bold text-[#5c212c] mb-2">
                            {product.name}
                        </h2>

                        <p className="text-xs font-medium text-[#732f3a]/80 leading-relaxed mb-3">
                            {product.description}
                        </p>

                        <div className="flex items-center gap-1.5 mb-4 text-xs font-semibold text-[#823945]">
                            <div className="flex text-amber-500">★★★★★</div>
                            <span>4.8</span>
                            <span className="text-[#732f3a]/60">
                                (124 reviews)
                            </span>
                        </div>

                        <div className="text-2xl font-extrabold text-[#5c212c] mb-3">
                            ${product.price}
                        </div>

                        <div className="flex items-center gap-4 text-xs font-medium pb-4 border-b border-[#823945]/15 mb-4">
                            <div className="flex items-center gap-1.5">
                                <span
                                    className={`w-2.5 h-2.5 rounded-full ${product.stock > 0 ? "bg-emerald-500" : "bg-red-500"}`}
                                ></span>
                                <span className="text-[#5c212c]">
                                    {product.stock > 0
                                        ? "In Stock"
                                        : "Out of Stock"}
                                </span>
                            </div>
                            <div className="flex items-center gap-1.5 text-[#732f3a]/80">
                                <Package className="w-3.5 h-3.5" />
                                <span>Available: {product.stock}</span>
                            </div>
                        </div>

                        <div className="mb-5">
                            <label className="block text-xs font-bold text-[#5c212c] mb-2">
                                Quantity
                            </label>
                            <div className="inline-flex items-center bg-white/50 border border-[#823945]/20 rounded-full p-1 shadow-inner">
                                <button
                                    onClick={() =>
                                        setQuantity(Math.max(1, quantity - 1))
                                    }
                                    className="w-7 h-7 rounded-full bg-white/70 hover:bg-white text-[#5c212c] font-bold flex items-center justify-center transition-colors text-sm shadow-xs cursor-pointer"
                                >
                                    -
                                </button>
                                <span className="w-10 text-center text-xs font-bold text-[#5c212c]">
                                    {quantity}
                                </span>
                                <button
                                    onClick={() => setQuantity(quantity + 1)}
                                    className="w-7 h-7 rounded-full bg-white/70 hover:bg-white text-[#5c212c] font-bold flex items-center justify-center transition-colors text-sm shadow-xs cursor-pointer"
                                >
                                    +
                                </button>
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-col gap-2.5">
                        <button
                            type="button"
                            onClick={handleAddToCart}
                            disabled={isLoading}
                            className="w-full py-3 px-6 rounded-full bg-[#823945] text-white text-xs font-bold shadow-md hover:brightness-110 active:scale-[0.98] transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                        >
                            <ShoppingCart className="w-4 h-4" />
                            <span>
                                {isLoading ? "Adding..." : "Add to Cart"}
                            </span>
                        </button>

                        <button
                            type="button"
                            className="w-full py-2.5 px-6 rounded-full bg-white/60 border border-[#823945]/30 text-[#823945] text-xs font-bold hover:bg-white active:scale-[0.98] transition-all flex items-center justify-center gap-2 cursor-pointer shadow-xs"
                        >
                            <Zap className="w-3.5 h-3.5 fill-[#823945]" />
                            <span>Buy Now</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default ProductDetailsModal;
