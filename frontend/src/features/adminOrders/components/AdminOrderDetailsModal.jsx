import {
    X,
    ClipboardList,
    ShoppingBag,
    Calendar,
    User,
    RefreshCw,
    ChevronDown,
} from "lucide-react";
import productImg from "../../../assets/login-hero.jpg";

const AdminOrderDetailsModal = ({ isOpen, onClose, order }) => {
    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/40 backdrop-blur-md animate-fadeIn">
            <div className="relative w-full max-w-2xl bg-[#f8e1e5]/95 backdrop-blur-2xl rounded-[32px] p-6 md:p-8 border border-white/60 shadow-2xl flex flex-col gap-6">
                <div className="flex items-center justify-between pb-2">
                    <button
                        type="button"
                        onClick={onClose}
                        className="w-8 h-8 rounded-full bg-white/60 hover:bg-white text-[#5c212c] flex items-center justify-center transition-colors shadow-sm cursor-pointer"
                    >
                        <X className="w-4 h-4" />
                    </button>

                    <div className="flex items-center gap-2">
                        <h3 className="text-xl font-bold text-[#5c212c]">
                            Order Details
                        </h3>
                        <ClipboardList className="w-5 h-5 text-[#823945]" />
                    </div>
                </div>

                <div className="bg-white/40 border border-white/40 rounded-[24px] p-4 grid grid-cols-2 sm:grid-cols-4 gap-4 divide-y sm:divide-y-0 sm:divide-x divide-[#823945]/15 text-center">
                    <div className="flex flex-col items-center justify-center p-1">
                        <span className="text-[11px] font-semibold text-[#732f3a]/80 mb-1.5">
                            Order Status
                        </span>
                        <span className="px-3 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold inline-flex items-center gap-1">
                            <span className="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                            {order?.status || "Delivered"}
                        </span>
                    </div>

                    <div className="flex flex-col items-center justify-center p-1 pt-2 sm:pt-1">
                        <span className="text-[11px] font-semibold text-[#732f3a]/80 mb-1">
                            Order #
                        </span>
                        <span className="text-sm font-extrabold text-[#5c212c]">
                            {order?.id || "#ORD-1004"}
                        </span>
                    </div>

                    <div className="flex flex-col items-center justify-center p-1 pt-2 sm:pt-1">
                        <span className="text-[11px] font-semibold text-[#732f3a]/80 mb-1">
                            Order Date
                        </span>
                        <div className="flex items-center gap-1 text-xs font-bold text-[#5c212c]">
                            <Calendar className="w-3.5 h-3.5 text-[#823945]" />
                            <span>Aug 24, 2025</span>
                        </div>
                        <span className="text-[10px] text-[#732f3a]/60">
                            10:32 AM
                        </span>
                    </div>

                    <div className="flex flex-col items-center justify-center p-1 pt-2 sm:pt-1">
                        <span className="text-[11px] font-semibold text-[#732f3a]/80 mb-1">
                            Customer
                        </span>
                        <div className="flex items-center gap-1 text-xs font-bold text-[#5c212c]">
                            <User className="w-3.5 h-3.5 text-[#823945]" />
                            <span>{order?.customer || "Razan"}</span>
                        </div>
                    </div>
                </div>

                <div>
                    <div className="flex items-center justify-end gap-1.5 mb-2.5">
                        <span className="text-xs font-bold text-[#5c212c]">
                            Products in This Order
                        </span>
                        <ShoppingBag className="w-4 h-4 text-[#823945]" />
                    </div>

                    <div className="bg-white/40 border border-white/40 rounded-[24px] overflow-hidden">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="bg-white/30 border-b border-[#823945]/15 text-[#5c212c] text-[11px] font-extrabold">
                                    <th className="py-2.5 px-4">Product</th>
                                    <th className="py-2.5 px-4 text-center">
                                        Quantity
                                    </th>
                                    <th className="py-2.5 px-4 text-center">
                                        Unit Price
                                    </th>
                                    <th className="py-2.5 px-4 text-right">
                                        Total Price
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[#823945]/10 text-xs font-semibold text-[#5c212c]">
                                <tr className="hover:bg-white/20 transition-colors">
                                    <td className="py-3 px-4 flex items-center gap-3">
                                        <div className="w-10 h-10 rounded-[12px] overflow-hidden border border-white/40 shrink-0">
                                            <img
                                                src={productImg}
                                                alt="Prod"
                                                className="w-full h-full object-cover"
                                            />
                                        </div>
                                        <span>Wireless Headphones</span>
                                    </td>
                                    <td className="py-3 px-4 text-center">1</td>
                                    <td className="py-3 px-4 text-center">
                                        $299.00
                                    </td>
                                    <td className="py-3 px-4 text-right font-extrabold">
                                        $299.00
                                    </td>
                                </tr>
                                <tr className="hover:bg-white/20 transition-colors">
                                    <td className="py-3 px-4 flex items-center gap-3">
                                        <div className="w-10 h-10 rounded-[12px] overflow-hidden border border-white/40 shrink-0">
                                            <img
                                                src={productImg}
                                                alt="Prod"
                                                className="w-full h-full object-cover"
                                            />
                                        </div>
                                        <span>Travel Backpack</span>
                                    </td>
                                    <td className="py-3 px-4 text-center">1</td>
                                    <td className="py-3 px-4 text-center">
                                        $149.00
                                    </td>
                                    <td className="py-3 px-4 text-right font-extrabold">
                                        $149.00
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="flex flex-col sm:flex-row items-center justify-between gap-4 pt-2">
                    <div className="w-full sm:w-auto bg-white/50 border border-white/40 rounded-[20px] px-6 py-3 flex flex-col items-center justify-center shadow-sm">
                        <span className="text-[11px] font-semibold text-[#732f3a]/80">
                            Order Total
                        </span>
                        <span className="text-2xl font-black text-[#5c212c]">
                            {order?.total || "$598.00"}
                        </span>
                    </div>

                    <div className="flex items-center gap-3 w-full sm:w-auto justify-end">
                        <div className="flex flex-col gap-1">
                            <span className="text-[10px] font-bold text-[#5c212c]">
                                Update Order Status:
                            </span>
                            <div className="relative">
                                <select className="appearance-none bg-white/70 border border-[#823945]/20 rounded-full py-2.5 pl-4 pr-10 text-xs font-bold text-[#5c212c] focus:outline-none focus:border-[#823945] cursor-pointer shadow-xs">
                                    <option value="Processing">
                                        Processing
                                    </option>
                                    <option value="Shipped">Shipped</option>
                                    <option value="Delivered">Delivered</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                                <ChevronDown className="absolute right-3.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[#823945] pointer-events-none" />
                            </div>
                        </div>

                        <button
                            type="button"
                            className="mt-4 sm:mt-4 py-2.5 px-5 rounded-full bg-[#823945] hover:bg-[#6e2f3a] text-white text-xs font-bold shadow-md active:scale-[0.98] transition-all flex items-center gap-2 cursor-pointer"
                        >
                            <RefreshCw className="w-3.5 h-3.5" />
                            <span>Update Status</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default AdminOrderDetailsModal;
