import { useDispatch, useSelector } from "react-redux";
import { useNavigate } from "react-router-dom";
import {
    Bell,
    X,
    Truck,
    CheckCircle2,
    ShoppingBag,
    Package,
    XCircle,
    ChevronRight,
} from "lucide-react";

import {
    allNotificationsRead,
    highlightOrder,
    notificationRead,
    selectNotifications,
} from "../../../redux/slices/notificationSlice";
import { formatRelativeTime } from "../utils/formatNotification";

const STATUS_ICONS = {
    pending: ShoppingBag,
    confirmed: CheckCircle2,
    processing: Package,
    shipped: Truck,
    delivered: CheckCircle2,
    cancelled: XCircle,
};

const STATUS_ICON_STYLES = {
    pending: "bg-[#823945]/10 text-[#823945]",
    confirmed: "bg-emerald-100 text-emerald-700",
    processing: "bg-purple-100 text-purple-700",
    shipped: "bg-blue-100 text-blue-700",
    delivered: "bg-emerald-100 text-emerald-700",
    cancelled: "bg-gray-200 text-gray-700",
};

function getIcon(notification) {
    if (notification.kind === "order-created") {
        return { Icon: ShoppingBag, style: "bg-[#823945]/10 text-[#823945]" };
    }

    const status = String(notification.status ?? "").toLowerCase();

    return {
        Icon: STATUS_ICONS[status] ?? Bell,
        style: STATUS_ICON_STYLES[status] ?? "bg-rose-100 text-[#823945]",
    };
}

const NotificationsDropdown = ({ isOpen, onClose }) => {
    const dispatch = useDispatch();
    const navigate = useNavigate();

    const notifications = useSelector(selectNotifications);
    const unreadCount = notifications.reduce(
        (count, item) => (item.read ? count : count + 1),
        0,
    );

    if (!isOpen) return null;

    const handleSelect = (notification) => {
        dispatch(notificationRead(notification.id));
        dispatch(highlightOrder(notification.orderId));
        onClose?.();

        navigate(
            notification.audience === "admin" ? "/admin/orders" : "/orders",
        );
    };

    return (
        <div className="absolute right-0 top-12 z-[9999] w-80 md:w-96 bg-[#f8e1e5]/95 backdrop-blur-2xl rounded-[28px] p-5 border border-white/80 shadow-[0_20px_50px_rgba(0,0,0,0.25)] animate-fadeIn">
            <div className="flex items-center justify-between pb-3 border-b border-[#823945]/15 mb-2">
                <div className="flex items-center gap-2">
                    <Bell className="w-4 h-4 text-[#823945]" />
                    <h3 className="text-sm font-extrabold text-[#5c212c]">
                        Notifications
                    </h3>
                    {unreadCount > 0 && (
                        <span className="px-1.5 py-0.5 rounded-full bg-[#823945] text-white text-[10px] font-extrabold leading-none">
                            {unreadCount}
                        </span>
                    )}
                </div>

                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => dispatch(allNotificationsRead())}
                        disabled={unreadCount === 0}
                        className="text-[11px] font-bold text-[#823945] bg-white/60 hover:bg-white px-2.5 py-1 rounded-full border border-white/50 transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                        Mark all as read
                    </button>
                    <button
                        type="button"
                        onClick={onClose}
                        className="w-6 h-6 rounded-full bg-white/60 hover:bg-white text-[#5c212c] flex items-center justify-center transition-colors cursor-pointer"
                    >
                        <X className="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>

            <div className="flex flex-col max-h-[380px] overflow-y-auto pr-1">
                {notifications.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 py-10 text-center">
                        <div className="w-11 h-11 rounded-full bg-white/60 flex items-center justify-center">
                            <Bell className="w-5 h-5 text-[#823945]" />
                        </div>
                        <p className="text-xs font-bold text-[#5c212c]">
                            You&apos;re all caught up
                        </p>
                        <p className="text-[11px] text-[#732f3a]/70">
                            New updates will appear here in real time.
                        </p>
                    </div>
                ) : (
                    notifications.map((item) => {
                        const { Icon, style } = getIcon(item);

                        return (
                            <button
                                key={item.id}
                                type="button"
                                onClick={() => handleSelect(item)}
                                className={`w-full text-left flex items-start gap-3 p-2.5 rounded-[18px] transition-all cursor-pointer group ${
                                    item.read
                                        ? "hover:bg-white/40"
                                        : "bg-white/45 hover:bg-white/60"
                                }`}
                            >
                                <div className="pt-2">
                                    {!item.read && (
                                        <span className="block w-2 h-2 rounded-full bg-rose-500"></span>
                                    )}
                                </div>

                                <div
                                    className={`w-9 h-9 rounded-full ${style} flex items-center justify-center shrink-0`}
                                >
                                    <Icon className="w-4 h-4" />
                                </div>

                                <div className="flex-1 min-w-0">
                                    <h4 className="text-xs font-bold text-[#5c212c] group-hover:text-[#823945] transition-colors">
                                        {item.title}
                                    </h4>
                                    <p className="text-[11px] font-medium text-[#732f3a]/80 leading-snug mt-0.5 line-clamp-2">
                                        {item.message}
                                    </p>
                                    <span className="text-[10px] font-semibold text-[#732f3a]/60 block mt-1">
                                        {formatRelativeTime(item.createdAt)}
                                    </span>
                                </div>

                                <ChevronRight className="w-3.5 h-3.5 text-[#823945]/50 group-hover:text-[#823945] shrink-0 self-center" />
                            </button>
                        );
                    })
                )}
            </div>
        </div>
    );
};

export default NotificationsDropdown;
