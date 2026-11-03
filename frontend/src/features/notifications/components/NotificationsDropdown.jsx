import {
    Bell,
    X,
    Truck,
    CheckCircle2,
    ShoppingBag,
    Tag,
    UserCheck,
    ChevronRight,
} from "lucide-react";

const NotificationsDropdown = ({ isOpen, onClose }) => {
    if (!isOpen) return null;

    const notifications = [
        {
            id: 1,
            title: "Order #ORD-1004 shipped",
            desc: "Your order #ORD-1004 has been shipped and is on the way!",
            time: "Aug 27, 2025 • 10:24 AM",
            icon: Truck,
            iconBg: "bg-rose-100 text-[#823945]",
            isUnread: true,
        },
        {
            id: 2,
            title: "Order #ORD-1003 is processing",
            desc: "Your order #ORD-1003 is now being processed.",
            time: "Aug 22, 2025 • 06:15 PM",
            icon: CheckCircle2,
            iconBg: "bg-emerald-100 text-emerald-700",
            isUnread: true,
        },
        {
            id: 3,
            title: "New order placed",
            desc: "A new order #ORD-1005 has been placed.",
            time: "Aug 20, 2025 • 11:42 AM",
            icon: ShoppingBag,
            iconBg: "bg-[#823945]/10 text-[#823945]",
            isUnread: true,
        },
        {
            id: 4,
            title: "Special offer just for you!",
            desc: "Get 20% OFF on selected laptops. Don't miss out!",
            time: "Aug 18, 2025 • 09:30 AM",
            icon: Tag,
            iconBg: "bg-purple-100 text-purple-700",
            isUnread: true,
        },
        {
            id: 5,
            title: "Welcome to Dreamshop!",
            desc: "Thanks for joining us! We're happy to have you here.",
            time: "Aug 15, 2025 • 11:20 AM",
            icon: UserCheck,
            iconBg: "bg-stone-200 text-[#5c212c]",
            isUnread: true,
        },
    ];

    return (
        <div className="absolute right-0 top-12 z-[9999] w-80 md:w-96 bg-[#f8e1e5]/95 backdrop-blur-2xl rounded-[28px] p-5 border border-white/80 shadow-[0_20px_50px_rgba(0,0,0,0.25)] animate-fadeIn">
            <div className="flex items-center justify-between pb-3 border-b border-[#823945]/15 mb-2">
                <div className="flex items-center gap-2">
                    <Bell className="w-4 h-4 text-[#823945]" />
                    <h3 className="text-sm font-extrabold text-[#5c212c]">
                        Notifications
                    </h3>
                </div>

                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        className="text-[11px] font-bold text-[#823945] bg-white/60 hover:bg-white px-2.5 py-1 rounded-full border border-white/50 transition-colors cursor-pointer"
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
                {notifications.map((item) => {
                    const IconComponent = item.icon;
                    return (
                        <div
                            key={item.id}
                            className="flex items-start gap-3 p-2.5 rounded-[18px] hover:bg-white/50 transition-all cursor-pointer group"
                        >
                            <div className="pt-2">
                                {item.isUnread && (
                                    <span className="block w-2 h-2 rounded-full bg-rose-500"></span>
                                )}
                            </div>

                            <div
                                className={`w-9 h-9 rounded-full ${item.iconBg} flex items-center justify-center shrink-0`}
                            >
                                <IconComponent className="w-4 h-4" />
                            </div>

                            <div className="flex-1 min-w-0">
                                <h4 className="text-xs font-bold text-[#5c212c] group-hover:text-[#823945] transition-colors">
                                    {item.title}
                                </h4>
                                <p className="text-[11px] font-medium text-[#732f3a]/80 leading-snug mt-0.5 line-clamp-2">
                                    {item.desc}
                                </p>
                                <span className="text-[10px] font-semibold text-[#732f3a]/60 block mt-1">
                                    {item.time}
                                </span>
                            </div>

                            <ChevronRight className="w-3.5 h-3.5 text-[#823945]/50 group-hover:text-[#823945] shrink-0 self-center" />
                        </div>
                    );
                })}
            </div>
        </div>
    );
};

export default NotificationsDropdown;
