import { X, Calendar, Truck, ArrowLeft } from 'lucide-react';
import productImg from '../../../assets/login-hero.jpg';

const OrderDetailsModal = ({ isOpen, onClose, order }) => {
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-md transition-opacity animate-fadeIn">
      <div className="relative w-full max-w-lg bg-[#f8e1e5]/90 backdrop-blur-xl rounded-[32px] p-6 md:p-8 border border-white/60 shadow-2xl flex flex-col justify-between">
        
        {/* زر الإغلاق العلوي */}
        <button
          onClick={onClose}
          className="absolute top-5 right-5 w-8 h-8 rounded-full bg-white/60 hover:bg-white text-[#5c212c] flex items-center justify-center transition-colors shadow-sm cursor-pointer z-10"
        >
          <X className="w-4 h-4" />
        </button>

        <div>
          {/* العلوية: رقم الطلب والحالة */}
          <div className="flex items-center justify-between pb-4 border-b border-[#823945]/15 mb-4 pr-8">
            <div>
              <h3 className="text-xl font-extrabold text-[#5c212c]">Order Details</h3>
              <span className="text-xs font-bold text-[#823945]">{order?.id || '#ORD-1004'}</span>
            </div>
            <span className="px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-[11px] font-bold">
              • {order?.status || 'Delivered'}
            </span>
          </div>

          {/* التواريخ وحالة التوصيل */}
          <div className="grid grid-cols-2 gap-2 p-3 bg-white/40 rounded-[20px] border border-white/30 text-[11px] mb-5 shadow-inner">
            <div className="flex items-center gap-2 text-[#732f3a]">
              <Calendar className="w-4 h-4 text-[#823945]" />
              <div>
                <p className="font-semibold text-[#5c212c]">{order?.date || 'Aug 24, 2025'}</p>
                <p className="opacity-70 text-[10px]">10:32 AM</p>
              </div>
            </div>
            <div className="flex items-center gap-2 text-[#732f3a] border-l border-[#823945]/15 pl-2">
              <Truck className="w-4 h-4 text-[#823945]" />
              <div>
                <p className="font-semibold text-[#5c212c]">Delivered on</p>
                <p className="opacity-70 text-[10px]">Aug 27, 2025</p>
              </div>
            </div>
          </div>

          {/* قائمة المنتجات في الطلب */}
          <div className="mb-4">
            <span className="text-xs font-bold text-[#5c212c] block mb-3">Items (3)</span>
            <div className="flex flex-col gap-3 max-h-[200px] overflow-y-auto pr-1">
              {['Wireless Headphones', 'Travel Backpack', 'UltraBook Pro 15"'].map((item, idx) => (
                <div key={idx} className="flex items-center gap-3 p-2 bg-white/30 rounded-[16px] border border-white/20">
                  <div className="w-12 h-12 rounded-[12px] overflow-hidden border border-white/40 shrink-0">
                    <img src={productImg} alt="Item" className="w-full h-full object-cover" />
                  </div>
                  <div className="flex-1 min-w-0">
                    <h5 className="text-xs font-bold text-[#5c212c] truncate">{item}</h5>
                    <p className="text-xs font-extrabold text-[#823945]">$299.00</p>
                    <span className="text-[10px] text-[#732f3a]/70">Qty: 1</span>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* ملخص الحساب والإجمالي */}
        <div className="pt-4 border-t border-[#823945]/15 mt-2">
          <div className="flex justify-between text-xs font-medium text-[#732f3a] mb-1">
            <span>Subtotal (3 items)</span>
            <span className="font-bold text-[#5c212c]">$1,647.00</span>
          </div>
          <div className="flex justify-between text-xs font-medium text-[#732f3a] mb-3">
            <span>Shipping</span>
            <span className="font-bold text-emerald-600">Free</span>
          </div>
          <div className="flex justify-between text-base font-extrabold text-[#5c212c] mb-4">
            <span>Total</span>
            <span>{order?.total || '$1,647.00'}</span>
          </div>

          <button
            type="button"
            onClick={onClose}
            className="w-full py-2.5 px-4 rounded-full bg-[#823945] text-white text-xs font-bold shadow-md hover:brightness-110 active:scale-[0.98] transition-all flex items-center justify-center gap-2 cursor-pointer"
          >
            <ArrowLeft className="w-3.5 h-3.5" />
            <span>Back to Orders</span>
          </button>
        </div>

      </div>
    </div>
  );
};

export default OrderDetailsModal;