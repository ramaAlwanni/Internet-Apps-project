import { useSelector } from "react-redux";
import { Bell } from "lucide-react";

import { selectUnreadNotificationCount } from "../../../redux/slices/notificationSlice";

const NotificationBell = ({ isOpen, onClick }) => {
    const unreadCount = useSelector(selectUnreadNotificationCount);
    const label =
        unreadCount > 0
            ? `Notifications (${unreadCount} unread)`
            : "Notifications";

    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={label}
            aria-expanded={isOpen}
            className="relative w-9 h-9 rounded-full bg-white/50 hover:bg-white text-[#5c212c] flex items-center justify-center transition-all cursor-pointer shadow-xs border border-white/40 active:scale-95"
        >
            <Bell className="w-4 h-4 text-[#823945]" />
            {unreadCount > 0 && (
                <span className="absolute -top-1 -right-1 min-w-4 h-4 px-1 rounded-full bg-rose-600 text-white text-[10px] font-extrabold flex items-center justify-center shadow-xs">
                    {unreadCount > 9 ? "9+" : unreadCount}
                </span>
            )}
        </button>
    );
};

export default NotificationBell;
