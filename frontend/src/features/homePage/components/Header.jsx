import { useState } from "react";
import { useNavigate, useLocation } from "react-router-dom";
import { useDispatch, useSelector } from "react-redux";
import { LogOut, User as UserIcon } from "lucide-react";
import toast from "react-hot-toast";
import NotificationsDropdown from "../../notifications/components/NotificationsDropdown";
import { logoutUser } from "../../auth/authSlice";

const Header = () => {
    const navigate = useNavigate();
    const location = useLocation();
    const dispatch = useDispatch();

    const { user } = useSelector((state) => state.auth);
    const [isNotificationsOpen, setIsNotificationsOpen] = useState(false);

    const isHome = location.pathname === "/home";
    const isCart = location.pathname === "/cart";
    const isOrders = location.pathname === "/orders";

    const handleLogout = async () => {
        await dispatch(logoutUser());

        toast.success("Signed out successfully! ❤️", {
            icon: "✨",
            style: {
                background: "#f8e1e5",
                color: "#5c212c",
                border: "1px solid #823945",
            },
        });

        navigate("/");
    };

    return (
        <header className="relative z-[500] w-full max-w-6xl mx-auto h-16 bg-[#df9fa8]/85 backdrop-blur-md rounded-full px-8 flex items-center justify-between border border-white/40 shadow-lg mb-8">
            {/* Logo */}
            <div
                className="flex items-center gap-2 cursor-pointer"
                onClick={() => navigate("/home")}
            >
                <div className="w-6 h-6 rounded-full bg-[#823945]/20 flex items-center justify-center text-[#5c212c] font-bold text-xs">
                    🌸
                </div>
                <h1 className="text-xl font-bold text-[#5c212c]">Dreamshop</h1>
            </div>

            {/* Navigation */}
            <nav className="flex items-center gap-8">
                <button
                    onClick={() => navigate("/home")}
                    className={`text-sm font-semibold transition-colors pb-1 cursor-pointer ${
                        isHome
                            ? "text-[#5c212c] border-b-2 border-[#5c212c]"
                            : "text-[#732f3a] hover:text-[#5c212c]"
                    }`}
                >
                    Products
                </button>

                <button
                    onClick={() => navigate("/cart")}
                    className={`text-sm font-semibold transition-colors pb-1 cursor-pointer ${
                        isCart
                            ? "text-[#5c212c] border-b-2 border-[#5c212c]"
                            : "text-[#732f3a] hover:text-[#5c212c]"
                    }`}
                >
                    Cart
                </button>

                <button
                    onClick={() => navigate("/orders")}
                    className={`text-sm font-semibold transition-colors pb-1 cursor-pointer ${
                        isOrders
                            ? "text-[#5c212c] border-b-2 border-[#5c212c]"
                            : "text-[#732f3a] hover:text-[#5c212c]"
                    }`}
                >
                    My Orders
                </button>
            </nav>

            {/* Controls & Profile */}
            <div className="flex items-center gap-5">
                {/* Notifications */}
                <div className="relative">
                    {/* <button
                        type="button"
                        onClick={() =>
                            setIsNotificationsOpen(!isNotificationsOpen)
                        }
                        className="relative w-9 h-9 rounded-full bg-white/50 hover:bg-white text-[#5c212c] flex items-center justify-center transition-all cursor-pointer shadow-xs border border-white/40"
                    >
                        <Bell className="w-4 h-4 text-[#823945]" />
                        <span className="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-rose-600 text-white text-[10px] font-extrabold flex items-center justify-center shadow-xs">
                            3
                        </span>
                    </button> */}

                    <NotificationsDropdown
                        isOpen={isNotificationsOpen}
                        onClose={() => setIsNotificationsOpen(false)}
                    />
                </div>

                {/* User Info & Logout Button */}
                <div className="flex items-center gap-3">
                    <div className="flex items-center gap-1.5 text-[#5c212c] text-sm font-bold bg-white/40 px-3 py-1.5 rounded-full border border-white/40">
                        <UserIcon className="w-4 h-4 text-[#823945]" />
                        <span>Hello, {user?.name || "Razan"}</span>
                    </div>

                    <button
                        type="button"
                        onClick={handleLogout}
                        title="Logout"
                        className="w-8 h-8 rounded-full bg-white/50 hover:bg-rose-600 text-[#823945] hover:text-white flex items-center justify-center transition-all cursor-pointer shadow-xs border border-white/40 active:scale-95"
                    >
                        <LogOut className="w-4 h-4" />
                    </button>
                </div>
            </div>
        </header>
    );
};

export default Header;
