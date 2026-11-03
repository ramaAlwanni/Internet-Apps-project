import { useState } from "react";
import Header from "../../homePage/components/Header";
import OrdersList from "../components/OrdersList";
import OrderDetailsModal from "../components/OrderDetailsModal";
import { ClipboardList } from "lucide-react";

const OrdersPage = () => {
    const [selectedOrder, setSelectedOrder] = useState(null);
    const [isModalOpen, setIsModalOpen] = useState(false);

    const handleOpenModal = (order) => {
        setSelectedOrder(order);
        setIsModalOpen(true);
    };

    return (
        <main className="min-h-screen w-full bg-metallic-shimmer py-8 px-4 md:px-12 flex flex-col items-center relative">
            <Header />

            <div className="w-full max-w-6xl mt-2">
                <div className="mb-6 px-2 flex items-center gap-2.5">
                    <ClipboardList className="w-6 h-6 text-[#5c212c]" />
                    <div>
                        <h2 className="text-2xl font-extrabold text-[#5c212c] tracking-tight">
                            My Orders
                        </h2>
                        <p className="text-xs font-medium text-[#732f3a]/80 mt-0.5">
                            Track your orders and view their details.
                        </p>
                    </div>
                </div>

                <div className="w-full bg-[#df9fa8]/50 backdrop-blur-md rounded-[36px] p-6 md:p-8 border border-white/40 shadow-2xl">
                    <OrdersList onSelectOrder={handleOpenModal} />
                </div>
            </div>

            <OrderDetailsModal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                order={selectedOrder}
            />
        </main>
    );
};

export default OrdersPage;
