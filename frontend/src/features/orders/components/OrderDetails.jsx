import { Calendar, Truck, ArrowLeft } from "lucide-react";
import productImg from "../../../assets/login-hero.jpg";

const OrderDetails = () => {
    return (
        <div className="w-full lg:w-[380px] bg-white/40 backdrop-blur-md rounded-[32px] p-6 border border-white/50 shadow-xl flex flex-col justify-between shrink-0">
            <div>
                <div className="flex items-center justify-between pb-4 border-b border-[#823945]/15 mb-4">
                    <div>
                        <h3 className="text-lg font-extrabold text-[#5c212c]">
                            Order Details
                        </h3>
                        <span className="text-xs font-bold text-[#823945]">
                            #ORD-1004
                        </span>
                    </div>
                    <span className="px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-[11px] font-bold">
                        • Delivered
                    </span>
                </div>

                <div className="grid grid-cols-2 gap-2 p-3 bg-white/40 rounded-[20px] border border-white/30 text-[11px] mb-5">
                    <div className="flex items-center gap-2 text-[#732f3a]">
                        <Calendar className="w-4 h-4 text-[#823945]" />
                        <div>
                            <p className="font-semibold text-[#5c212c]">
                                Aug 24, 2025
                            </p>
                            <p className="opacity-70 text-[10px]">10:32 AM</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2 text-[#732f3a] border-l border-[#823945]/15 pl-2">
                        <Truck className="w-4 h-4 text-[#823945]" />
                        <div>
                            <p className="font-semibold text-[#5c212c]">
                                Delivered on
                            </p>
                            <p className="opacity-70 text-[10px]">
                                Aug 27, 2025
                            </p>
                        </div>
                    </div>
                </div>

                <div className="mb-4">
                    <span className="text-xs font-bold text-[#5c212c] block mb-3">
                        Items (3)
                    </span>
                    <div className="flex flex-col gap-3 max-h-[220px] overflow-y-auto pr-1">
                        <div className="flex items-center gap-3">
                            <div className="w-12 h-12 rounded-[12px] overflow-hidden border border-white/40 shrink-0">
                                <img
                                    src={productImg}
                                    alt="Item"
                                    className="w-full h-full object-cover"
                                />
                            </div>
                            <div className="flex-1 min-w-0">
                                <h5 className="text-xs font-bold text-[#5c212c] truncate">
                                    Wireless Headphones
                                </h5>
                                <p className="text-xs font-extrabold text-[#823945]">
                                    $299.00
                                </p>
                                <span className="text-[10px] text-[#732f3a]/70">
                                    Qty: 1
                                </span>
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            <div className="w-12 h-12 rounded-[12px] overflow-hidden border border-white/40 shrink-0">
                                <img
                                    src={productImg}
                                    alt="Item"
                                    className="w-full h-full object-cover"
                                />
                            </div>
                            <div className="flex-1 min-w-0">
                                <h5 className="text-xs font-bold text-[#5c212c] truncate">
                                    Travel Backpack
                                </h5>
                                <p className="text-xs font-extrabold text-[#823945]">
                                    $149.00
                                </p>
                                <span className="text-[10px] text-[#732f3a]/70">
                                    Qty: 1
                                </span>
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            <div className="w-12 h-12 rounded-[12px] overflow-hidden border border-white/40 shrink-0">
                                <img
                                    src={productImg}
                                    alt="Item"
                                    className="w-full h-full object-cover"
                                />
                            </div>
                            <div className="flex-1 min-w-0">
                                <h5 className="text-xs font-bold text-[#5c212c] truncate">
                                    UltraBook Pro 15"
                                </h5>
                                <p className="text-xs font-extrabold text-[#823945]">
                                    $1,199.00
                                </p>
                                <span className="text-[10px] text-[#732f3a]/70">
                                    Qty: 1
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* ملخص الحساب والإجمالي */}
            <div className="pt-4 border-t border-[#823945]/15">
                <div className="flex justify-between text-xs font-medium text-[#732f3a] mb-1">
                    <span>Subtotal (3 items)</span>
                    <span className="font-bold text-[#5c212c]">$1,647.00</span>
                </div>
                <div className="flex justify-between text-xs font-medium text-[#732f3a] mb-3">
                    <span>Shipping</span>
                    <span className="font-bold text-emerald-600">Free</span>
                </div>
                <div className="flex justify-between text-base font-extrabold text-[#5c212c] mb-4">
                    <span>Total</span>
                    <span>$1,647.00</span>
                </div>

                <button
                    type="button"
                    className="w-full py-2.5 px-4 rounded-full bg-[#823945] text-white text-xs font-bold shadow-md hover:brightness-110 active:scale-[0.98] transition-all flex items-center justify-center gap-2 cursor-pointer"
                >
                    <ArrowLeft className="w-3.5 h-3.5" />
                    <span>Back to Orders</span>
                </button>
            </div>
        </div>
    );
};

export default OrderDetails;
