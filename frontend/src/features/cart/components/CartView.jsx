import { useEffect } from "react";
import { useDispatch, useSelector } from "react-redux";
import {
    fetchCart,
    checkoutCart,
    removeFromCart,
    updateCartQuantity,
} from "../../../redux/slices/cartSlice";
import { Trash2, Clock } from "lucide-react";
import toast from "react-hot-toast";

const CartView = () => {
    const dispatch = useDispatch();
    const { cartItems, latestOrder, isLoading } = useSelector(
        (state) => state.cart,
    );

    useEffect(() => {
        dispatch(fetchCart());
    }, [dispatch]);

    const totalAmount = cartItems.reduce(
        (acc, item) => acc + Number(item.subtotal),
        0,
    );

    
    const handleQuantityChange = async (productId, currentQty, change) => {
        const newQty = currentQty + change;
        if (newQty < 1) return;

        const resultAction = await dispatch(
            updateCartQuantity({ productId, quantity: newQty }),
        );

        if (updateCartQuantity.fulfilled.match(resultAction)) {
            toast.success("Cart updated successfully! 😊", {
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
            dispatch(fetchCart()); 
        } else {
            toast.error(resultAction.payload || "Failed to update quantity 😢");
        }
    };

    const handleRemoveItem = async (productId) => {
        const resultAction = await dispatch(removeFromCart(productId));

        if (removeFromCart.fulfilled.match(resultAction)) {
            toast.success("Product removed from cart successfully! 😊", {
                style: {
                    background: "#f8e1e5",
                    color: "#5c212c",
                    fontWeight: "bold",
                },
                iconTheme: { primary: "#823945", secondary: "#fff" },
            });
            dispatch(fetchCart());
        } else {
            toast.error(resultAction.payload || "Failed to remove product 😢");
        }
    };

    const handleCreateOrder = async () => {
        const resultAction = await dispatch(checkoutCart());

        if (checkoutCart.fulfilled.match(resultAction)) {
            toast.success("Order created successfully! 😊", {
                style: {
                    background: "#f8e1e5",
                    color: "#5c212c",
                    fontWeight: "bold",
                },
                iconTheme: { primary: "#823945", secondary: "#fff" },
            });
        } else {
            toast.error(resultAction.payload || "Failed to create order 😢");
        }
    };

    if (isLoading) {
        return (
            <div className="text-center py-12 text-[#5c212c] font-semibold">
                Loading...
            </div>
        );
    }

    return (
        <div className="w-full bg-[#df9fa8]/50 backdrop-blur-md rounded-[36px] p-6 md:p-8 border border-white/40 shadow-2xl flex flex-col lg:flex-row gap-6">
            
            <div className="w-full lg:w-7/12 bg-white/30 backdrop-blur-sm rounded-[28px] p-5 border border-white/40 shadow-sm flex flex-col gap-4">
                <div className="flex items-center justify-between pb-3 border-b border-[#823945]/15">
                    <span className="text-sm font-bold text-[#5c212c]">
                        Selected Products
                    </span>
                    <span className="px-2.5 py-0.5 rounded-full bg-[#823945]/10 text-xs font-semibold text-[#823945]">
                        {cartItems.length} items
                    </span>
                </div>

                {cartItems.length === 0 ? (
                    <div className="text-center py-10 text-[#5c212c] font-medium text-xs">
                        {latestOrder
                            ? "Order created successfully! 🎉"
                            : "Your cart is empty."}
                    </div>
                ) : (
                    cartItems.map((item) => (
                        <div
                            key={item.id}
                            className="flex flex-col sm:flex-row items-center justify-between gap-4 p-3 bg-white/40 rounded-[20px] border border-white/30"
                        >
                            <div className="flex items-center gap-3 w-full sm:w-auto">
                                <div className="w-16 h-16 rounded-[14px] overflow-hidden border border-white/40 shrink-0 bg-gray-100">
                                    <img
                                        src={item.product?.photo_url}
                                        alt={item.product?.name}
                                        className="w-full h-full object-cover"
                                    />
                                </div>
                                <div>
                                    <span className="text-[10px] font-semibold text-[#823945] uppercase">
                                        {item.product?.category?.name ||
                                            "Electronics"}
                                    </span>
                                    <h4 className="text-xs font-bold text-[#5c212c] mb-1">
                                        {item.product?.name}
                                    </h4>
                                    <p className="text-sm font-extrabold text-[#5c212c]">
                                        ${item.product?.price}
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-center justify-between sm:justify-end w-full sm:w-auto gap-4">
                           
                                <div className="flex items-center bg-white/60 border border-[#823945]/20 rounded-full p-1 shadow-inner">
                                    <button
                                        onClick={() =>
                                            handleQuantityChange(
                                                item.product_id,
                                                item.quantity,
                                                -1,
                                            )
                                        }
                                        className="w-6 h-6 rounded-full bg-white text-[#5c212c] text-xs font-bold flex items-center justify-center shadow-xs cursor-pointer hover:bg-gray-100"
                                    >
                                        -
                                    </button>
                                    <span className="w-8 text-center text-xs font-bold text-[#5c212c]">
                                        {item.quantity}
                                    </span>
                                    <button
                                        onClick={() =>
                                            handleQuantityChange(
                                                item.product_id,
                                                item.quantity,
                                                1,
                                            )
                                        }
                                        className="w-6 h-6 rounded-full bg-white text-[#5c212c] text-xs font-bold flex items-center justify-center shadow-xs cursor-pointer hover:bg-gray-100"
                                    >
                                        +
                                    </button>
                                </div>

                                <span className="text-sm font-bold text-[#5c212c] min-w-[70px] text-right">
                                    ${item.subtotal}
                                </span>

                                <button
                                    onClick={() =>
                                        handleRemoveItem(item.product_id)
                                    }
                                    className="text-[#823945] hover:text-red-700 transition-colors p-1 cursor-pointer"
                                    title="Remove"
                                >
                                    <Trash2 className="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    ))
                )}
            </div>

            
            <div className="w-full lg:w-5/12 bg-white/30 backdrop-blur-sm rounded-[28px] p-6 border border-white/40 shadow-sm flex flex-col justify-between">
                <div>
                    <h3 className="text-base font-bold text-[#5c212c] pb-4 border-b border-[#823945]/15 mb-4">
                        Order Summary
                    </h3>

                    <div className="flex items-center justify-between text-xs font-medium text-[#732f3a] mb-3">
                        <span>Subtotal ({cartItems.length} items)</span>
                        <span className="font-bold text-[#5c212c]">
                            ${totalAmount.toFixed(2)}
                        </span>
                    </div>

                    <div className="flex items-center justify-between text-xs font-medium pb-4 border-b border-[#823945]/15 mb-4 text-[#732f3a]">
                        <span>Shipping</span>
                        <span className="font-bold text-emerald-600">Free</span>
                    </div>

                    <div className="flex items-center justify-between text-base font-extrabold text-[#5c212c] mb-4">
                        <span>Total</span>
                        <span>
                            $
                            {latestOrder
                                ? latestOrder.total_amount
                                : totalAmount.toFixed(2)}
                        </span>
                    </div>

                    {latestOrder && (
                        <div className="bg-white/50 rounded-2xl p-4 border border-[#823945]/20 mb-4 flex flex-col gap-2">
                            <div className="flex items-center justify-between text-xs">
                                <span className="font-bold text-[#5c212c]">
                                    Order ID:
                                </span>
                                <span className="font-semibold text-[#732f3a]">
                                    #{latestOrder.id}
                                </span>
                            </div>
                            <div className="flex items-center justify-between text-xs">
                                <span className="font-bold text-[#5c212c]">
                                    Status:
                                </span>
                                <span className="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-bold flex items-center gap-1">
                                    <Clock className="w-3 h-3" />{" "}
                                    {latestOrder.status}
                                </span>
                            </div>
                            <div className="flex items-center justify-between text-xs">
                                <span className="font-bold text-[#5c212c]">
                                    Date:
                                </span>
                                <span className="font-semibold text-[#732f3a]">
                                    {new Date(
                                        latestOrder.created_at,
                                    ).toLocaleDateString()}
                                </span>
                            </div>
                        </div>
                    )}
                </div>

                <button
                    type="button"
                    onClick={handleCreateOrder}
                    disabled={cartItems.length === 0}
                    className="w-full py-3 px-6 rounded-full bg-[#823945] text-white text-xs font-bold shadow-md hover:brightness-110 active:scale-[0.98] transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                >
                    <span>Create Order</span>
                    <span>→</span>
                </button>
            </div>
        </div>
    );
};

export default CartView;
