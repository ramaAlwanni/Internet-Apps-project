import {
    Calendar,
    Search,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
} from "lucide-react";

const AdminOrdersTable = ({ onViewDetails }) => {
    const orders = [
        {
            id: "#ORD-1004",
            customer: "Razan",
            date: "Aug 24, 2025 • 10:32 AM",
            total: "$1,647.00",
            status: "Delivered",
            statusBg: "bg-emerald-100 text-emerald-700",
        },
        {
            id: "#ORD-1003",
            customer: "Razan",
            date: "Aug 22, 2025 • 06:15 PM",
            total: "$598.00",
            status: "Processing",
            statusBg: "bg-purple-100 text-purple-700",
        },
        {
            id: "#ORD-1002",
            customer: "Razan",
            date: "Aug 18, 2025 • 01:42 PM",
            total: "$876.00",
            status: "Shipped",
            statusBg: "bg-blue-100 text-blue-700",
        },
        {
            id: "#ORD-1001",
            customer: "Razan",
            date: "Aug 15, 2025 • 11:20 AM",
            total: "$149.00",
            status: "Cancelled",
            statusBg: "bg-gray-200 text-gray-700",
        },
    ];

    return (
        <div className="w-full flex flex-col gap-6">
            <div className="w-full flex flex-col md:flex-row items-center justify-between gap-4">
                <div className="flex items-center gap-3 w-full md:w-auto">
                    <button className="bg-white/60 border border-[#823945]/20 rounded-full py-2.5 px-5 text-[#5c212c] text-xs font-semibold flex items-center gap-2 shadow-inner cursor-pointer">
                        <Calendar className="w-3.5 h-3.5 text-[#823945]" />
                        <span>All Dates</span>
                        <ChevronDown className="w-3.5 h-3.5 opacity-70" />
                    </button>

                    <button className="bg-white/60 border border-[#823945]/20 rounded-full py-2.5 px-5 text-[#5c212c] text-xs font-semibold flex items-center gap-2 shadow-inner cursor-pointer">
                        <span className="w-2 h-2 rounded-full bg-[#823945]"></span>
                        <span>All Statuses</span>
                        <ChevronDown className="w-3.5 h-3.5 opacity-70" />
                    </button>
                </div>

                <div className="relative w-full md:w-80">
                    <input
                        type="text"
                        placeholder="Search by order number..."
                        className="w-full bg-white/60 border border-[#823945]/20 rounded-full py-2.5 pl-10 pr-4 text-[#5c212c] placeholder-[#823945]/60 text-xs font-medium focus:outline-none focus:border-[#823945] shadow-inner"
                    />
                    <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-[#823945]/70" />
                </div>
            </div>

            <div className="w-full bg-[#df9fa8]/50 backdrop-blur-md rounded-[36px] p-6 border border-white/40 shadow-2xl flex flex-col">
                <div className="w-full overflow-x-auto">
                    <table className="w-full text-left border-collapse">
                        <thead>
                            <tr className="border-b border-[#823945]/15 text-[#5c212c] text-xs font-extrabold">
                                <th className="py-3 px-4">Order #</th>
                                <th className="py-3 px-4">Customer</th>
                                <th className="py-3 px-4">Date</th>
                                <th className="py-3 px-4">Total</th>
                                <th className="py-3 px-4">Status</th>
                                <th className="py-3 px-4 text-right">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[#823945]/10 text-xs font-medium text-[#5c212c]">
                            {orders.map((order, idx) => (
                                <tr
                                    key={idx}
                                    className="hover:bg-white/20 transition-colors"
                                >
                                    <td className="py-4 px-4 font-extrabold">
                                        {order.id}
                                    </td>
                                    <td className="py-4 px-4">
                                        {order.customer}
                                    </td>
                                    <td className="py-4 px-4 flex items-center gap-1.5 pt-5 text-[#732f3a]/90">
                                        <Calendar className="w-3.5 h-3.5 text-[#823945]" />
                                        <span>{order.date}</span>
                                    </td>
                                    <td className="py-4 px-4 font-extrabold text-[#5c212c]">
                                        {order.total}
                                    </td>
                                    <td className="py-4 px-4">
                                        <span
                                            className={`inline-block px-3 py-1 rounded-full text-[11px] font-bold ${order.statusBg}`}
                                        >
                                            • {order.status}
                                        </span>
                                    </td>
                                    <td className="py-4 px-4 text-right">
                                        <button
                                            type="button"
                                            onClick={() => onViewDetails(order)}
                                            className="py-1.5 px-4 rounded-full bg-gradient-to-r from-[#bf707c] via-[#a85764] to-[#823945] text-white text-[11px] font-semibold shadow-md hover:brightness-105 active:scale-[0.98] transition-all cursor-pointer"
                                        >
                                            View Details →
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between pt-5 mt-4 border-t border-[#823945]/15 text-xs font-semibold text-[#732f3a]">
                    <span>Showing 1 – 4 of 4 orders</span>
                    <div className="flex items-center gap-1">
                        <button className="w-7 h-7 rounded-full bg-white/50 hover:bg-white text-[#5c212c] flex items-center justify-center shadow-xs cursor-pointer">
                            <ChevronLeft className="w-4 h-4" />
                        </button>
                        <button className="w-7 h-7 rounded-full bg-[#823945] text-white flex items-center justify-center font-bold shadow-xs cursor-pointer">
                            1
                        </button>
                        <button className="w-7 h-7 rounded-full bg-white/50 hover:bg-white text-[#5c212c] flex items-center justify-center shadow-xs cursor-pointer">
                            <ChevronRight className="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default AdminOrdersTable;
