import { Calendar, Truck, ArrowLeft } from "lucide-react";
import productImg from "../../../assets/login-hero.jpg";

const OrderDetails = ({ order, onBack }) => {
    if (!order) {
        return (
            <div className="w-full lg:w-[380px] bg-white/40 backdrop-blur-md rounded-[32px] p-6 border border-white/50 shadow-xl flex items-center justify-center shrink-0 min-h-[300px]">
                <p className="text-xs font-semibold text-[#732f3a]/80">
                    Select an order to view its details.
                </p>
            </div>
        );
    }

    const dateObj = new Date(order.created_at);
    const formattedDate = dateObj.toLocaleDateString("en-US", {
        month: "short",
        day: "numeric",
        year: "numeric",
    });
    const formattedTime = dateObj.toLocaleTimeString("en-US", {
        hour: "2-digit",
        minute: "2-digit",
    });

    return (
        <div className="w-full lg:w-[380px] bg-white/40 backdrop-blur-md rounded-[32px] p-6 border border-white/50 shadow-xl flex flex-col justify-between shrink-0">
            <div>
                <div className="flex items-center justify-between pb-4 border-b border-[#823945]/15 mb-4">
                    <div>
                        <h3 className="text-lg font-extrabold text-[#5c212c]">
                            Order Details
                        </h3>
                        <span className="text-xs font-bold text-[#823945]">
                            #ORD-{order.id}
                        </span>
                    </div>
                    <span className="px-3 py-1 rounded-full bg-amber-100 text-amber-800 text-[11px] font-bold capitalize">
                        • {order.status}
                    </span>
                </div>

                <div className="grid grid-cols-2 gap-2 p-3 bg-white/40 rounded-[20px] border border-white/30 text-[11px] mb-5">
                    <div className="flex items-center gap-2 text-[#732f3a]">
                        <Calendar className="w-4 h-4 text-[#823945]" />
                        <div>
                            <p className="font-semibold text-[#5c212c]">
                                {formattedDate}
                            </p>
                            <p className="opacity-70 text-[10px]">
                                {formattedTime}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2 text-[#732f3a] border-l border-[#823945]/15 pl-2">
                        <Truck className="w-4 h-4 text-[#823945]" />
                        <div>
                            <p className="font-semibold text-[#5c212c]">
                                Status
                            </p>
                            <p className="opacity-70 text-[10px] capitalize">
                                {order.status}
                            </p>
                        </div>
                    </div>
                </div>

                <div className="mb-4">
                    <span className="text-xs font-bold text-[#5c212c] block mb-3">
                        Items ({order.items?.length || 0})
                    </span>
                    <div className="flex flex-col gap-3 max-h-[220px] overflow-y-auto pr-1">
                        {order.items?.map((item) => (
                            <div
                                key={item.id}
                                className="flex items-center gap-3 bg-white/30 p-2 rounded-[16px] border border-white/30"
                            >
                                <div className="w-12 h-12 rounded-[12px] overflow-hidden border border-white/40 shrink-0 bg-gray-100">
                                    <img
                                        src={
                                            item.product?.photo_url ||
                                            productImg
                                        }
                                        alt={item.product_name}
                                        className="w-full h-full object-cover"
                                    />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <h5 className="text-xs font-bold text-[#5c212c] truncate">
                                        {item.product_name}
                                    </h5>
                                    <p className="text-xs font-extrabold text-[#823945]">
                                        ${item.unit_price}
                                    </p>
                                    <span className="text-[10px] text-[#732f3a]/70">
                                        Qty: {item.quantity}
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            <div className="pt-4 border-t border-[#823945]/15">
                <div className="flex justify-between text-xs font-medium text-[#732f3a] mb-1">
                    <span>Subtotal ({order.items?.length || 0} items)</span>
                    <span className="font-bold text-[#5c212c]">
                        ${order.total_amount}
                    </span>
                </div>
                <div className="flex justify-between text-xs font-medium text-[#732f3a] mb-3">
                    <span>Shipping</span>
                    <span className="font-bold text-emerald-600">Free</span>
                </div>
                <div className="flex justify-between text-base font-extrabold text-[#5c212c] mb-4">
                    <span>Total</span>
                    <span>${order.total_amount}</span>
                </div>

                {onBack && (
                    <button
                        type="button"
                        onClick={onBack}
                        className="w-full py-2.5 px-4 rounded-full bg-[#823945] text-white text-xs font-bold shadow-md hover:brightness-110 active:scale-[0.98] transition-all flex items-center justify-center gap-2 cursor-pointer"
                    >
                        <ArrowLeft className="w-3.5 h-3.5" />
                        <span>Back to Orders</span>
                    </button>
                )}
            </div>
        </div>
    );
};

export default OrderDetails;
