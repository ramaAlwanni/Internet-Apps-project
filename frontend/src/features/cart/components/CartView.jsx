import { Trash2 } from 'lucide-react';
import productImg from '../../../assets/login-hero.jpg';

const CartView = () => {
  return (
    <div className="w-full bg-[#df9fa8]/50 backdrop-blur-md rounded-[36px] p-6 md:p-8 border border-white/40 shadow-2xl flex flex-col lg:flex-row gap-6">
      
      
      <div className="w-full lg:w-7/12 bg-white/30 backdrop-blur-sm rounded-[28px] p-5 border border-white/40 shadow-sm flex flex-col gap-4">
        <div className="flex items-center justify-between pb-3 border-b border-[#823945]/15">
          <span className="text-sm font-bold text-[#5c212c]">Selected Products</span>
          <span className="px-2.5 py-0.5 rounded-full bg-[#823945]/10 text-xs font-semibold text-[#823945]">
            3 items
          </span>
        </div>

       
        <div className="flex flex-col sm:flex-row items-center justify-between gap-4 p-3 bg-white/40 rounded-[20px] border border-white/30">
          <div className="flex items-center gap-3 w-full sm:w-auto">
            <div className="w-16 h-16 rounded-[14px] overflow-hidden border border-white/40 shrink-0">
              <img src={productImg} alt="Product" className="w-full h-full object-cover" />
            </div>
            <div>
              <span className="text-[10px] font-semibold text-[#823945] uppercase">Laptops</span>
              <h4 className="text-xs font-bold text-[#5c212c] mb-1">UltraBook Pro 15"</h4>
              <p className="text-sm font-extrabold text-[#5c212c]">$1,199.00</p>
              <div className="flex items-center gap-1.5 mt-1">
                <span className="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span className="text-[10px] font-medium text-[#732f3a]/80">In Stock</span>
              </div>
            </div>
          </div>

          <div className="flex items-center justify-between sm:justify-end w-full sm:w-auto gap-4">
            <div className="flex items-center bg-white/60 border border-[#823945]/20 rounded-full p-1 shadow-inner">
              <button className="w-6 h-6 rounded-full bg-white text-[#5c212c] text-xs font-bold flex items-center justify-center shadow-xs cursor-pointer">-</button>
              <span className="w-8 text-center text-xs font-bold text-[#5c212c]">1</span>
              <button className="w-6 h-6 rounded-full bg-white text-[#5c212c] text-xs font-bold flex items-center justify-center shadow-xs cursor-pointer">+</button>
            </div>
            <span className="text-sm font-bold text-[#5c212c] min-w-[70px] text-right">$1,199.00</span>
            <button className="text-[#823945] hover:text-red-700 transition-colors p-1 cursor-pointer" title="Remove">
              <Trash2 className="w-4 h-4" />
            </button>
          </div>
        </div>

        
        <div className="flex flex-col sm:flex-row items-center justify-between gap-4 p-3 bg-white/40 rounded-[20px] border border-white/30">
          <div className="flex items-center gap-3 w-full sm:w-auto">
            <div className="w-16 h-16 rounded-[14px] overflow-hidden border border-white/40 shrink-0">
              <img src={productImg} alt="Product" className="w-full h-full object-cover" />
            </div>
            <div>
              <span className="text-[10px] font-semibold text-[#823945] uppercase">Headphones</span>
              <h4 className="text-xs font-bold text-[#5c212c] mb-1">Wireless Headphones</h4>
              <p className="text-sm font-extrabold text-[#5c212c]">$299.00</p>
              <div className="flex items-center gap-1.5 mt-1">
                <span className="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span className="text-[10px] font-medium text-[#732f3a]/80">In Stock</span>
              </div>
            </div>
          </div>

          <div className="flex items-center justify-between sm:justify-end w-full sm:w-auto gap-4">
            <div className="flex items-center bg-white/60 border border-[#823945]/20 rounded-full p-1 shadow-inner">
              <button className="w-6 h-6 rounded-full bg-white text-[#5c212c] text-xs font-bold flex items-center justify-center shadow-xs cursor-pointer">-</button>
              <span className="w-8 text-center text-xs font-bold text-[#5c212c]">2</span>
              <button className="w-6 h-6 rounded-full bg-white text-[#5c212c] text-xs font-bold flex items-center justify-center shadow-xs cursor-pointer">+</button>
            </div>
            <span className="text-sm font-bold text-[#5c212c] min-w-[70px] text-right">$598.00</span>
            <button className="text-[#823945] hover:text-red-700 transition-colors p-1 cursor-pointer" title="Remove">
              <Trash2 className="w-4 h-4" />
            </button>
          </div>
        </div>

        
        <div className="flex flex-col sm:flex-row items-center justify-between gap-4 p-3 bg-white/40 rounded-[20px] border border-white/30">
          <div className="flex items-center gap-3 w-full sm:w-auto">
            <div className="w-16 h-16 rounded-[14px] overflow-hidden border border-white/40 shrink-0">
              <img src={productImg} alt="Product" className="w-full h-full object-cover" />
            </div>
            <div>
              <span className="text-[10px] font-semibold text-[#823945] uppercase">Backpacks</span>
              <h4 className="text-xs font-bold text-[#5c212c] mb-1">Travel Backpack</h4>
              <p className="text-sm font-extrabold text-[#5c212c]">$149.00</p>
              <div className="flex items-center gap-1.5 mt-1">
                <span className="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span className="text-[10px] font-medium text-[#732f3a]/80">In Stock</span>
              </div>
            </div>
          </div>

          <div className="flex items-center justify-between sm:justify-end w-full sm:w-auto gap-4">
            <div className="flex items-center bg-white/60 border border-[#823945]/20 rounded-full p-1 shadow-inner">
              <button className="w-6 h-6 rounded-full bg-white text-[#5c212c] text-xs font-bold flex items-center justify-center shadow-xs cursor-pointer">-</button>
              <span className="w-8 text-center text-xs font-bold text-[#5c212c]">1</span>
              <button className="w-6 h-6 rounded-full bg-white text-[#5c212c] text-xs font-bold flex items-center justify-center shadow-xs cursor-pointer">+</button>
            </div>
            <span className="text-sm font-bold text-[#5c212c] min-w-[70px] text-right">$149.00</span>
            <button className="text-[#823945] hover:text-red-700 transition-colors p-1 cursor-pointer" title="Remove">
              <Trash2 className="w-4 h-4" />
            </button>
          </div>
        </div>

      </div>

      
      <div className="w-full lg:w-5/12 bg-white/30 backdrop-blur-sm rounded-[28px] p-6 border border-white/40 shadow-sm flex flex-col justify-between">
        <div>
          <h3 className="text-base font-bold text-[#5c212c] pb-4 border-b border-[#823945]/15 mb-4">
            Order Summary
          </h3>
          
          <div className="flex items-center justify-between text-xs font-medium text-[#732f3a] mb-3">
            <span>Subtotal (3 items)</span>
            <span className="font-bold text-[#5c212c]">$1,947.00</span>
          </div>

          <div className="flex items-center justify-[#732f3a] justify-between text-xs font-medium pb-4 border-b border-[#823945]/15 mb-4">
            <span>Shipping</span>
            <span className="font-bold text-emerald-600">Free</span>
          </div>

          <div className="flex items-center justify-between text-base font-extrabold text-[#5c212c] mb-6">
            <span>Total</span>
            <span>$1,947.00</span>
          </div>
        </div>

        <button
          type="button"
          className="w-full py-3 px-6 rounded-full bg-[#823945] text-white text-xs font-bold shadow-md hover:brightness-110 active:scale-[0.98] transition-all flex items-center justify-center gap-2 cursor-pointer"
        >
          <span>Create Order</span>
          <span>→</span>
        </button>
      </div>

    </div>
  );
};

export default CartView;