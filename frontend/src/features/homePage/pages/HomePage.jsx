import Header from "../components/Header";
import { useState, useEffect } from "react";
import { useDispatch, useSelector } from "react-redux";
import { fetchCategories } from "../../../redux/slices/categorySlice";
import { fetchProducts } from "../../../redux/slices/productSlice";
import ProductCard from "../components/ProductCard";
import ProductDetailsModal from "../components/ProductDetailsModal";
import { Search, Filter } from "lucide-react";

const HomePage = () => {
    const dispatch = useDispatch();
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [selectedCategory, setSelectedCategory] = useState("All Categories");
    const [selectedCategoryId, setSelectedCategoryId] = useState("");
    const [isDropdownOpen, setIsDropdownOpen] = useState(false);
    const [currentPage, setCurrentPage] = useState(1);

    const { categories } = useSelector((state) => state.category);
    const { products, lastPage, isLoading } = useSelector(
        (state) => state.product,
    );

    const [selectedProduct, setSelectedProduct] = useState(null);

    useEffect(() => {
        dispatch(fetchCategories());
        dispatch(
            fetchProducts({
                categoryId: selectedCategoryId,
                page: currentPage,
            }),
        );
    }, [dispatch, selectedCategoryId, currentPage]);

    const handleSelectCategory = (cat) => {
        setCurrentPage(1);
        if (!cat) {
            setSelectedCategory("All Categories");
            setSelectedCategoryId("");
        } else {
            setSelectedCategory(cat.name);
            setSelectedCategoryId(cat.id);
        }
        setIsDropdownOpen(false);
    };

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
                            <button
                                onClick={() =>
                                    setIsDropdownOpen(!isDropdownOpen)
                                }
                                className="w-full md:w-auto bg-white/60 border border-[#823945]/20 rounded-full py-2.5 px-6 text-[#5c212c] text-sm font-semibold flex items-center justify-between gap-3 shadow-inner"
                            >
                                <div className="flex items-center gap-2">
                                    <Filter className="w-4 h-4 text-[#823945]" />
                                    <span>{selectedCategory}</span>
                                </div>
                                <span className="text-xs">▾</span>
                            </button>

                            {isDropdownOpen && (
                                <div className="absolute right-0 mt-2 w-full md:w-56 bg-white rounded-2xl shadow-xl border border-[#823945]/10 py-2 z-50">
                                    <button
                                        onClick={() =>
                                            handleSelectCategory(null)
                                        }
                                        className={`w-full text-left px-4 py-2 text-sm font-medium transition-colors ${
                                            selectedCategoryId === ""
                                                ? "bg-[#df9fa8]/30 text-[#5c212c] font-bold"
                                                : "text-[#5c212c] hover:bg-[#df9fa8]/25"
                                        }`}
                                    >
                                        All Categories
                                    </button>

                                    {categories.map((cat) => (
                                        <button
                                            key={cat.id}
                                            onClick={() =>
                                                handleSelectCategory(cat)
                                            }
                                            className={`w-full text-left px-4 py-2 text-sm font-medium transition-colors ${
                                                selectedCategoryId === cat.id
                                                    ? "bg-[#df9fa8]/30 text-[#5c212c] font-bold"
                                                    : "text-[#5c212c] hover:bg-[#df9fa8]/25"
                                            }`}
                                        >
                                            {cat.name}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>

                    {isLoading ? (
                        <div className="text-center py-12 text-[#5c212c] font-semibold">
                            Loading products...
                        </div>
                    ) : products.length === 0 ? (
                        <div className="text-center py-12 text-[#5c212c] font-semibold">
                            No products found.
                        </div>
                    ) : (
                        <>
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                                {products.map((product) => (
                                    <ProductCard
                                        key={product.id}
                                        product={product}
                                        onViewDetails={(prod) => {
                                            setSelectedProduct(prod);
                                            setIsModalOpen(true);
                                        }}
                                    />
                                ))}
                            </div>

                            <div className="flex items-center justify-center gap-4 mt-8">
                                <button
                                    onClick={() =>
                                        setCurrentPage((prev) =>
                                            Math.max(prev - 1, 1),
                                        )
                                    }
                                    disabled={currentPage === 1}
                                    className="px-4 py-2 rounded-full bg-white/70 border border-[#823945]/20 text-[#5c212c] text-sm font-semibold disabled:opacity-40 shadow-sm hover:bg-white transition-all cursor-pointer"
                                >
                                    Previous
                                </button>

                                <span className="text-sm font-bold text-[#5c212c]">
                                    Page {currentPage} of {lastPage}
                                </span>

                                <button
                                    onClick={() =>
                                        setCurrentPage((prev) =>
                                            Math.min(prev + 1, lastPage),
                                        )
                                    }
                                    disabled={currentPage === lastPage}
                                    className="px-4 py-2 rounded-full bg-white/70 border border-[#823945]/20 text-[#5c212c] text-sm font-semibold disabled:opacity-40 shadow-sm hover:bg-white transition-all cursor-pointer"
                                >
                                    Next
                                </button>
                            </div>
                        </>
                    )}
                </div>
            </div>

            <ProductDetailsModal
                isOpen={isModalOpen}
                onClose={() => {
                    setIsModalOpen(false);
                    setSelectedProduct(null);
                }}
                product={selectedProduct}
            />
        </main>
    );
};

export default HomePage;
