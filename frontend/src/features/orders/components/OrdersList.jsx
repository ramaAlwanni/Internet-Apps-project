import { useEffect, useState } from "react";
import { useDispatch, useSelector } from "react-redux";
import { fetchMyOrders } from "../../../redux/slices/orderSlice";
import { ChevronRight } from "lucide-react";


const OrdersList = ({ onSelectOrder }) => {
    const dispatch = useDispatch();
    const [requestedPage, setRequestedPage] = useState(1);
    const { orders, currentPage, lastPage, total, isLoading, isError, message } =
        useSelector((state) => state.order);

    useEffect(() => {
        dispatch(fetchMyOrders(requestedPage));
    }, [dispatch, requestedPage]);


    const getStatusStyle = (status) => {
        switch (status?.toLowerCase()) {
            case "delivered":
                return "bg-emerald-100 text-emerald-700";
            case "processing":
                return "bg-purple-100 text-purple-700";
            case "shipped":
                return "bg-blue-100 text-blue-700";
            case "cancelled":
                return "bg-gray-200 text-gray-700";
            default:
                return "bg-amber-100 text-amber-800"; 
        }
    };

    if (isLoading) {
        return <div className="text-center py-12 text-[#5c212c] font-semibold">Loading orders...</div>;
    }

    if (isError) {
        return <div role="alert" className="text-center py-12 text-red-700 font-semibold">{message}</div>;
    }

    if (!orders || orders.length === 0) {
        return <div className="text-center py-12 text-[#5c212c] font-medium">No orders found.</div>;
    }

    return (
        <div className="w-full flex flex-col gap-4">
            {orders.map((order) => {
                const dateObj = new Date(order.created_at);
                const formattedDate = dateObj.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
                const formattedTime = dateObj.toLocaleTimeString("en-US", { hour: "2-digit", minute: "2-digit" });
                const itemsCount = order.items?.length || 0;

                return (
                    <div
                        key={order.id}
                        onClick={() => onSelectOrder(order)}
                        className="w-full bg-white/30 backdrop-blur-sm border border-white/40 hover:bg-white/40 rounded-[24px] p-4 transition-all cursor-pointer shadow-xs flex flex-col sm:flex-row items-center justify-between gap-4"
                    >
                        <div className="min-w-[120px]">
                            <h4 className="text-sm font-extrabold text-[#5c212c]">
                                #ORD-{order.id}
                            </h4>
                            <p className="text-xs font-semibold text-[#732f3a]/80 mt-0.5">
                                {formattedDate}
                            </p>
                            <p className="text-[10px] text-[#732f3a]/60">
                                {formattedTime}
                            </p>
                        </div>

                        <div className="flex items-center gap-2">
                            {/* {order.items?.slice(0, 2).map((item, idx) => (
                                <div key={idx} className="w-10 h-10 rounded-[10px] overflow-hidden border border-white/40 shrink-0 bg-gray-100">
                                  
                                </div>
                            ))} */}
                            {itemsCount > 2 && (
                                <div className="w-10 h-10 rounded-[10px] bg-white/50 border border-white/40 flex items-center justify-center text-xs font-bold text-[#823945]">
                                    +{itemsCount - 2}
                                </div>
                            )}
                            <span className="text-[11px] font-medium text-[#732f3a]/70 ml-1">
                                {itemsCount} {itemsCount === 1 ? "item" : "items"}
                            </span>
                        </div>

                        <div className="flex items-center gap-4">
                            <div className="text-right">
                                <p className="text-sm font-extrabold text-[#5c212c]">
                                    ${order.total_amount}
                                </p>
                                <p className="text-[10px] text-[#732f3a]/60">
                                    Total Amount
                                </p>
                            </div>

                            <span className={`px-3 py-1 rounded-full text-[11px] font-bold capitalize ${getStatusStyle(order.status)}`}>
                                • {order.status}
                            </span>

                            <div className="w-8 h-8 rounded-full bg-white/50 hover:bg-white flex items-center justify-center text-[#823945] shadow-xs">
                                <ChevronRight className="w-4 h-4" />
                            </div>
                        </div>
                    </div>
                );
            })}
            {lastPage > 1 && (
                <div className="flex items-center justify-center gap-4 pt-4">
                    <button
                        type="button"
                        onClick={() => setRequestedPage(currentPage - 1)}
                        disabled={currentPage <= 1 || isLoading}
                        className="px-4 py-2 rounded-full bg-white/70 border border-[#823945]/20 text-[#5c212c] text-sm font-semibold disabled:opacity-40 shadow-sm hover:bg-white transition-all cursor-pointer"
                    >
                        Previous
                    </button>
                    <span className="text-sm font-bold text-[#5c212c]">
                        Page {currentPage} of {lastPage} ({total} orders)
                    </span>
                    <button
                        type="button"
                        onClick={() => setRequestedPage(currentPage + 1)}
                        disabled={currentPage >= lastPage || isLoading}
                        className="px-4 py-2 rounded-full bg-white/70 border border-[#823945]/20 text-[#5c212c] text-sm font-semibold disabled:opacity-40 shadow-sm hover:bg-white transition-all cursor-pointer"
                    >
                        Next
                    </button>
                </div>
            )}
        </div>
    );
};

export default OrdersList;