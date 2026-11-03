import Header from "../components/Header";
import { useState } from "react";
import ProductCard from "../components/ProductCard";
import ProductDetailsModal from "../components/ProductDetailsModal";
import { Search, Filter } from "lucide-react";

const HomePage = () => {
    const [isModalOpen, setIsModalOpen] = useState(false);

    return (
        <main className="min-h-screen w-full bg-metallic-shimmer py-8 px-4 md:px-12 flex flex-col items-center">
            <Header />

            <div className="w-full max-w-6xl">
                <div className="mb-6 px-2">
                    <h2 className="text-3xl font-extrabold text-[#5c212c] tracking-tight">
                        Our Products
                    </h2>
                    <p className="text-sm font-medium text-[#732f3a]/80 mt-1">
                        Discover the best products, just for you.
                    </p>
                </div>

                <div className="w-full bg-[#df9fa8]/50 backdrop-blur-md rounded-[36px] p-6 md:p-8 border border-white/40 shadow-2xl">
                    <div className="w-full flex flex-col md:flex-row items-center justify-between gap-4 mb-6">
                        <div className="relative w-full md:w-1/2">
                            <input
                                type="text"
                                placeholder="Search for products..."
                                className="w-full bg-white/60 border border-[#823945]/20 rounded-full py-2.5 pl-10 pr-4 text-[#5c212c] placeholder-[#823945]/60 text-sm font-medium focus:outline-none focus:border-[#823945] shadow-inner"
                            />
                            <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-[#823945]/70" />
                        </div>

                        <div className="relative w-full md:w-auto">
                            <button className="w-full md:w-auto bg-white/60 border border-[#823945]/20 rounded-full py-2.5 px-6 text-[#5c212c] text-sm font-semibold flex items-center justify-between gap-3 shadow-inner">
                                <Filter className="w-4 h-4 text-[#823945]" />
                                <span>All Categories</span>
                                <span className="text-xs">▾</span>
                            </button>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                        <ProductCard
                            onViewDetails={() => setIsModalOpen(true)}
                        />
                        <ProductCard
                            onViewDetails={() => setIsModalOpen(true)}
                        />
                        <ProductCard
                            onViewDetails={() => setIsModalOpen(true)}
                        />
                        <ProductCard
                            onViewDetails={() => setIsModalOpen(true)}
                        />
                        <ProductCard
                            onViewDetails={() => setIsModalOpen(true)}
                        />
                        <ProductCard
                            onViewDetails={() => setIsModalOpen(true)}
                        />
                        <ProductCard
                            onViewDetails={() => setIsModalOpen(true)}
                        />
                        <ProductCard
                            onViewDetails={() => setIsModalOpen(true)}
                        />
                    </div>
                </div>
            </div>

            {/* نافذة تفاصيل المنتج المنبثقة */}
            <ProductDetailsModal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
            />
        </main>
    );
};

export default HomePage;
