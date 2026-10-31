import { useState } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { Bell } from 'lucide-react';
import NotificationsDropdown from '../../notifications/components/NotificationsDropdown';

const AdminHeader = () => {
  const navigate = useNavigate();
  const location = useLocation();
  const [isNotificationsOpen, setIsNotificationsOpen] = useState(false);

  const isAdminOrders = location.pathname === '/admin/orders';

  return (
    <header className="relative z-[500] w-full max-w-6xl mx-auto h-16 bg-[#df9fa8]/80 backdrop-blur-md rounded-full px-8 flex items-center justify-between border border-white/40 shadow-lg mb-8">
      
      <div 
        className="flex items-center gap-2 cursor-pointer" 
        onClick={() => navigate('/admin/orders')}
      >
        <div className="w-6 h-6 rounded-full bg-[#823945]/20 flex items-center justify-center text-[#5c212c] font-bold text-xs">
          🌸
        </div>
        <h1 className="text-xl font-bold text-[#5c212c]">Dreamshop</h1>
      </div>

    
      <nav className="flex items-center gap-8">
        <button
          onClick={() => navigate('/admin/products')}
          className="text-sm font-semibold text-[#732f3a] hover:text-[#5c212c] transition-colors pb-1 cursor-pointer"
        >
          Products
        </button>

        <button
          onClick={() => navigate('/admin/orders')}
          className={`text-sm font-semibold transition-colors pb-1 cursor-pointer ${
            isAdminOrders 
              ? 'text-[#5c212c] border-b-2 border-[#5c212c]' 
              : 'text-[#732f3a] hover:text-[#5c212c]'
          }`}
        >
          Orders
        </button>
      </nav>

  
      <div className="flex items-center gap-4">
        <div className="relative">
          <button
            type="button"
            onClick={() => setIsNotificationsOpen(!isNotificationsOpen)}
            className="relative w-9 h-9 rounded-full bg-white/50 hover:bg-white text-[#5c212c] flex items-center justify-center transition-all cursor-pointer shadow-xs border border-white/40"
          >
            <Bell className="w-4 h-4 text-[#823945]" />
            <span className="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-rose-600 text-white text-[10px] font-extrabold flex items-center justify-center shadow-xs">
              3
            </span>
          </button>

          <NotificationsDropdown
            isOpen={isNotificationsOpen}
            onClose={() => setIsNotificationsOpen(false)}
          />
        </div>

        <div className="flex items-center gap-2 text-[#5c212c] text-sm font-medium">
          <span className="text-xs">👤</span>
          <span>Hello, Razan</span>
          <span className="text-xs">▾</span>
        </div>
      </div>
    </header>
  );
};


export default AdminHeader;