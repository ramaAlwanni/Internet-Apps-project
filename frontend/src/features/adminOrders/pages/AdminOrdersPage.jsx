import { useState } from "react";
import AdminHeader from "../components/AdminHeader";
import AdminOrdersTable from "../components/AdminOrdersTable";
import AdminOrderDetailsModal from "../components/AdminOrderDetailsModal";
import { ClipboardList } from "lucide-react";

const AdminOrdersPage = () => {
    const [selectedOrder, setSelectedOrder] = useState(null);
    const [isModalOpen, setIsModalOpen] = useState(false);

    const handleOpenModal = (order) => {
        setSelectedOrder(order);
        setIsModalOpen(true);
    };

    return (
        <main className="min-h-screen w-full bg-metallic-shimmer py-8 px-4 md:px-12 flex flex-col items-center relative">
            <AdminHeader />

            <div className="w-full max-w-6xl mt-2">
                <div className="mb-6 px-2 flex items-center gap-2.5">
                    <ClipboardList className="w-6 h-6 text-[#5c212c]" />
                    <div>
                        <h2 className="text-2xl font-extrabold text-[#5c212c] tracking-tight">
                            Manage Orders
                        </h2>
                        <p className="text-xs font-medium text-[#732f3a]/80 mt-0.5">
                            View and track all customer orders across the
                            platform.
                        </p>
                    </div>
                </div>

                <AdminOrdersTable onViewDetails={handleOpenModal} />
            </div>

            <AdminOrderDetailsModal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                order={selectedOrder}
            />
        </main>
    );
};

export default AdminOrdersPage;
