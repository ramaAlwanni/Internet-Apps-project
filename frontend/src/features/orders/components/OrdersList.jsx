import { ChevronRight } from "lucide-react";
import productImg from "../../../assets/login-hero.jpg";

const OrdersList = ({ onSelectOrder }) => {
    const orders = [
        {
            id: "#ORD-1004",
            date: "Aug 24, 2025",
            time: "10:32 AM",
            itemsCount: "3 items",
            total: "$1,047.00",
            status: "Delivered",
            statusBg: "bg-emerald-100 text-emerald-700",
            hasMore: true,
        },
        {
            id: "#ORD-1003",
            date: "Aug 22, 2025",
            time: "06:15 PM",
            itemsCount: "2 items",
            total: "$1,298.00",
            status: "Processing",
            statusBg: "bg-purple-100 text-purple-700",
            hasMore: false,
        },
        {
            id: "#ORD-1002",
            date: "Aug 18, 2025",
            time: "01:42 PM",
            itemsCount: "3 items",
            total: "$876.00",
            status: "Shipped",
            statusBg: "bg-blue-100 text-blue-700",
            hasMore: true,
        },
        {
            id: "#ORD-1001",
            date: "Aug 15, 2025",
            time: "11:20 AM",
            itemsCount: "1 item",
            total: "$149.00",
            status: "Cancelled",
            statusBg: "bg-gray-200 text-gray-700",
            hasMore: false,
        },
    ];

    return (
        <div className="w-full flex flex-col gap-4">
            {orders.map((order, index) => (
                <div
                    key={index}
                    onClick={() => onSelectOrder(order)}
                    className="w-full bg-white/30 backdrop-blur-sm border border-white/40 hover:bg-white/40 rounded-[24px] p-4 transition-all cursor-pointer shadow-xs flex flex-col sm:flex-row items-center justify-between gap-4"
                >
                    <div className="min-w-[120px]">
                        <h4 className="text-sm font-extrabold text-[#5c212c]">
                            {order.id}
                        </h4>
                        <p className="text-xs font-semibold text-[#732f3a]/80 mt-0.5">
                            {order.date}
                        </p>
                        <p className="text-[10px] text-[#732f3a]/60">
                            {order.time}
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <div className="w-10 h-10 rounded-[10px] overflow-hidden border border-white/40 shrink-0">
                            <img
                                src={productImg}
                                alt="Thumb"
                                className="w-full h-full object-cover"
                            />
                        </div>
                        {order.itemsCount !== "1 item" && (
                            <div className="w-10 h-10 rounded-[10px] overflow-hidden border border-white/40 shrink-0">
                                <img
                                    src={productImg}
                                    alt="Thumb"
                                    className="w-full h-full object-cover"
                                />
                            </div>
                        )}
                        {order.hasMore && (
                            <div className="w-10 h-10 rounded-[10px] bg-white/50 border border-white/40 flex items-center justify-center text-xs font-bold text-[#823945]">
                                +1
                            </div>
                        )}
                        <span className="text-[11px] font-medium text-[#732f3a]/70 ml-1">
                            {order.itemsCount}
                        </span>
                    </div>

                    <div className="flex items-center gap-4">
                        <div className="text-right">
                            <p className="text-sm font-extrabold text-[#5c212c]">
                                {order.total}
                            </p>
                            <p className="text-[10px] text-[#732f3a]/60">
                                Total Amount
                            </p>
                        </div>

                        <span
                            className={`px-3 py-1 rounded-full text-[11px] font-bold ${order.statusBg}`}
                        >
                            • {order.status}
                        </span>

                        <div className="w-8 h-8 rounded-full bg-white/50 hover:bg-white flex items-center justify-center text-[#823945] shadow-xs">
                            <ChevronRight className="w-4 h-4" />
                        </div>
                    </div>
                </div>
            ))}
        </div>
    );
};

export default OrdersList;
