import Header from '../../homePage/components/Header';
import CartView from '../components/CartView';
import { ShoppingCart } from 'lucide-react';

const CartPage = () => {
  return (
    <main className="min-h-screen w-full bg-metallic-shimmer py-8 px-4 md:px-12 flex flex-col items-center">
     
      <Header />

      <div className="w-full max-w-6xl mt-2">
        
        <div className="mb-6 px-2 flex items-center gap-2">
          <ShoppingCart className="w-6 h-6 text-[#5c212c]" />
          <div>
            <h2 className="text-2xl font-extrabold text-[#5c212c] tracking-tight">
              My Cart
            </h2>
            <p className="text-xs font-medium text-[#732f3a]/80 mt-0.5">
              Review your selected products and complete your order.
            </p>
          </div>
        </div>

        
        <CartView />
      </div>
    </main>
  );
};

export default CartPage;