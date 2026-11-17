import { useState } from "react";
import { useNavigate, useLocation } from "react-router-dom";
import NotificationsDropdown from "../../notifications/components/NotificationsDropdown";
import NotificationBell from "../../notifications/components/NotificationBell";
import { useAdminOrderNotifications } from "../../notifications/hooks/useAdminOrderNotifications";

const AdminHeader = () => {
    const navigate = useNavigate();
    const location = useLocation();
    const [isNotificationsOpen, setIsNotificationsOpen] = useState(false);

    // Mounted on every admin page, so new orders notify the admin even while
    // they are managing products instead of watching the orders table.
    useAdminOrderNotifications(true);

    const isAdminOrders = location.pathname === "/admin/orders";

    return (
        <header className="relative z-[500] w-full max-w-6xl mx-auto h-16 bg-[#df9fa8]/80 backdrop-blur-md rounded-full px-8 flex items-center justify-between border border-white/40 shadow-lg mb-8">
            <div
                className="flex items-center gap-2 cursor-pointer"
                onClick={() => navigate("/admin/orders")}
            >
                <div className="w-6 h-6 rounded-full bg-[#823945]/20 flex items-center justify-center text-[#5c212c] font-bold text-xs">
                    🌸
                </div>
                <h1 className="text-xl font-bold text-[#5c212c]">Dreamshop</h1>
            </div>

            <nav className="flex items-center gap-8">
                <button
                    onClick={() => navigate("/admin/products")}
                    className="text-sm font-semibold text-[#732f3a] hover:text-[#5c212c] transition-colors pb-1 cursor-pointer"
                >
                    Products
                </button>

                <button
                    onClick={() => navigate("/admin/orders")}
                    className={`text-sm font-semibold transition-colors pb-1 cursor-pointer ${
                        isAdminOrders
                            ? "text-[#5c212c] border-b-2 border-[#5c212c]"
                            : "text-[#732f3a] hover:text-[#5c212c]"
                    }`}
                >
                    Orders
                </button>
            </nav>

            <div className="flex items-center gap-4">
                <div className="relative">
                    <NotificationBell
                        isOpen={isNotificationsOpen}
                        onClick={() =>
                            setIsNotificationsOpen(!isNotificationsOpen)
                        }
                    />

                    <NotificationsDropdown
                        isOpen={isNotificationsOpen}
                        onClose={() => setIsNotificationsOpen(false)}
                    />
                </div>

                <div className="flex items-center gap-2 text-[#5c212c] text-sm font-medium">
                    <span className="text-xs">👤</span>
                    <span>Hello</span>
                    <span className="text-xs">▾</span>
                </div>
            </div>
        </header>
    );
};

export default AdminHeader;
