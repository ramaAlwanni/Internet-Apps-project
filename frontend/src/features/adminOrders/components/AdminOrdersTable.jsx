import { useEffect, useState } from "react";
import { useDispatch, useSelector } from "react-redux";
import { fetchAdminOrders } from "../../../redux/slices/adminOrderSlice";
import { Calendar, ChevronLeft, ChevronRight } from "lucide-react";
import AdminOrderDetailsModal from "./AdminOrderDetailsModal";

const AdminOrdersTable = () => {
    const dispatch = useDispatch();
    const { orders, loading, error } = useSelector(
        (state) => state.adminOrders,
    );

    const [selectedOrder, setSelectedOrder] = useState(null);
    const [isModalOpen, setIsModalOpen] = useState(false);

    useEffect(() => {
        dispatch(fetchAdminOrders());
    }, [dispatch]);

    const handleViewDetails = (order) => {
        setSelectedOrder(order);
        setIsModalOpen(true);
    };

    const handleCloseModal = () => {
        setIsModalOpen(false);
        setSelectedOrder(null);
    };

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
            case "pending":
            default:
                return "bg-amber-100 text-amber-700";
        }
    };

    if (loading) {
        return (
            <div className="text-center py-10 text-[#5c212c] font-bold">
                Loading orders...
            </div>
        );
    }

    if (error) {
        return (
            <div className="text-center py-10 text-rose-600 font-bold">
                Error: {error}
            </div>
        );
    }

    return (
        <div className="w-full flex flex-col gap-6">
            <div className="w-full flex flex-col md:flex-row items-center justify-between gap-4">
                <div className="flex items-center gap-3 w-full md:w-auto"></div>

                <div className="relative w-full md:w-80"></div>
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
                            {orders && orders.length > 0 ? (
                                orders.map((order) => {
                                    const dateObj = new Date(order.created_at);
                                    const formattedDate =
                                        dateObj.toLocaleDateString("en-US", {
                                            month: "short",
                                            day: "numeric",
                                            year: "numeric",
                                        }) +
                                        " • " +
                                        dateObj.toLocaleTimeString("en-US", {
                                            hour: "2-digit",
                                            minute: "2-digit",
                                        });

                                    return (
                                        <tr
                                            key={order.id}
                                            className="hover:bg-white/20 transition-colors"
                                        >
                                            <td className="py-4 px-4 font-extrabold">
                                                #ORD-{order.id}
                                            </td>
                                            <td className="py-4 px-4">
                                                {order.user?.name || "Unknown"}
                                            </td>
                                            <td className="py-4 px-4 flex items-center gap-1.5 pt-5 text-[#732f3a]/90">
                                                <Calendar className="w-3.5 h-3.5 text-[#823945]" />
                                                <span>{formattedDate}</span>
                                            </td>
                                            <td className="py-4 px-4 font-extrabold text-[#5c212c]">
                                                ${order.total_amount}
                                            </td>
                                            <td className="py-4 px-4">
                                                <span
                                                    className={`inline-block px-3 py-1 rounded-full text-[11px] font-bold capitalize ${getStatusStyle(order.status)}`}
                                                >
                                                    • {order.status}
                                                </span>
                                            </td>
                                            <td className="py-4 px-4 text-right">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        handleViewDetails(order)
                                                    }
                                                    className="py-1.5 px-4 rounded-full bg-gradient-to-r from-[#bf707c] via-[#a85764] to-[#823945] text-white text-[11px] font-semibold shadow-md hover:brightness-105 active:scale-[0.98] transition-all cursor-pointer"
                                                >
                                                    View Details →
                                                </button>
                                            </td>
                                        </tr>
                                    );
                                })
                            ) : (
                                <tr>
                                    <td
                                        colSpan="6"
                                        className="text-center py-6 text-[#5c212c]/70"
                                    >
                                        No orders found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between pt-5 mt-4 border-t border-[#823945]/15 text-xs font-semibold text-[#732f3a]">
                    <span>Showing {orders.length} orders</span>
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

            <AdminOrderDetailsModal
                key={selectedOrder?.id || "modal"}
                isOpen={isModalOpen}
                onClose={handleCloseModal}
                order={selectedOrder}
                onStatusUpdated={() => {
                    dispatch(fetchAdminOrders());
                }}
            />
        </div>
    );
};

export default AdminOrdersTable;
