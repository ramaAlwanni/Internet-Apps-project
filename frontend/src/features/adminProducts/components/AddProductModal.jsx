import { useState } from "react";
import { useDispatch, useSelector } from "react-redux";
import { addProduct, fetchProducts } from "../../../redux/slices/productSlice";
import {
    X,
    Upload,
    Trash2,
    Tag,
    FileText,
    DollarSign,
    ChevronDown,
    RefreshCw,
    Plus,
} from "lucide-react";
import toast from "react-hot-toast";

const AddProductModal = ({ isOpen, onClose }) => {
    const dispatch = useDispatch();
    const { categories } = useSelector((state) => state.category);

    const [name, setName] = useState("");
    const [description, setDescription] = useState("");
    const [price, setPrice] = useState("");
    const [stock, setStock] = useState("");
    const [categoryId, setCategoryId] = useState("");
    const [photo, setPhoto] = useState(null);
    const [previewUrl, setPreviewUrl] = useState(null);
    const [isLoading, setIsLoading] = useState(false);

    if (!isOpen) return null;

    const handleImageChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            setPhoto(file);
            setPreviewUrl(URL.createObjectURL(file));
        }
    };

    const handleReset = () => {
        setName("");
        setDescription("");
        setPrice("");
        setStock("");
        setCategoryId("");
        setPhoto(null);
        setPreviewUrl(null);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setIsLoading(true);

        const resultAction = await dispatch(
            addProduct({
                name,
                description,
                price,
                stock: stock || 10,
                category_id: categoryId,
                photo,
            }),
        );

        setIsLoading(false);

        if (addProduct.fulfilled.match(resultAction)) {
            toast.success("Product added successfully! 😊", {
                style: {
                    background: "#f8e1e5",
                    color: "#5c212c",
                    fontWeight: "bold",
                },
                iconTheme: {
                    primary: "#823945",
                    secondary: "#fff",
                },
            });
            handleReset();
            dispatch(fetchProducts());
            onClose();
        }
    };

    return (
        <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/40 backdrop-blur-md animate-fadeIn">
            <div className="relative w-full max-w-3xl bg-[#f8e1e5]/95 backdrop-blur-2xl rounded-[32px] p-6 md:p-8 border border-white/60 shadow-2xl flex flex-col gap-6">
                <div className="flex items-center justify-between pb-3 border-b border-[#823945]/15">
                    <button
                        type="button"
                        onClick={onClose}
                        className="w-8 h-8 rounded-full bg-white/60 hover:bg-white text-[#5c212c] flex items-center justify-center transition-colors shadow-sm cursor-pointer"
                    >
                        <X className="w-4 h-4" />
                    </button>

                    <div className="flex items-center gap-2">
                        <h3 className="text-xl font-extrabold text-[#5c212c]">
                            Add Product
                        </h3>
                        <div className="w-7 h-7 rounded-full bg-[#823945]/15 flex items-center justify-center text-[#823945]">
                            📦
                        </div>
                    </div>
                </div>

                <form
                    onSubmit={handleSubmit}
                    className="grid grid-cols-1 md:grid-cols-12 gap-6 items-start"
                >
                    <div className="md:col-span-5 flex flex-col gap-4">
                        <label className="w-full border-2 border-dashed border-[#823945]/30 rounded-[24px] p-6 flex flex-col items-center justify-center bg-white/30 hover:bg-white/40 transition-colors cursor-pointer text-center">
                            <input
                                type="file"
                                accept="image/png, image/jpeg"
                                onChange={handleImageChange}
                                className="hidden"
                            />
                            <div className="w-10 h-10 rounded-full bg-[#823945]/10 text-[#823945] flex items-center justify-center mb-2">
                                <Upload className="w-5 h-5" />
                            </div>
                            <h4 className="text-xs font-bold text-[#5c212c] mb-1">
                                Upload Product Image
                            </h4>
                            <p className="text-[10px] text-[#732f3a]/70 leading-tight">
                                Click to upload or drag and drop
                                <br />
                                (JPG, PNG)
                            </p>
                        </label>

                        {previewUrl && (
                            <div className="relative w-full h-36 rounded-[24px] overflow-hidden border border-white/60 shadow-md group">
                                <img
                                    src={previewUrl}
                                    alt="Preview"
                                    className="w-full h-full object-cover"
                                />
                                <button
                                    type="button"
                                    onClick={() => {
                                        setPhoto(null);
                                        setPreviewUrl(null);
                                    }}
                                    className="absolute top-2.5 right-2.5 w-7 h-7 rounded-full bg-rose-600/90 hover:bg-rose-700 text-white flex items-center justify-center shadow-md transition-all cursor-pointer"
                                >
                                    <Trash2 className="w-3.5 h-3.5" />
                                </button>
                            </div>
                        )}
                    </div>

                    <div className="md:col-span-7 flex flex-col gap-4">
                        <div className="flex flex-col gap-1.5">
                            <label className="text-xs font-bold text-[#5c212c]">
                                Product Name *
                            </label>
                            <div className="relative">
                                <input
                                    type="text"
                                    value={name}
                                    onChange={(e) => setName(e.target.value)}
                                    placeholder="Enter product name..."
                                    required
                                    className="w-full bg-white/60 border border-[#823945]/20 rounded-full py-2.5 pl-4 pr-10 text-[#5c212c] placeholder-[#823945]/50 text-xs font-medium focus:outline-none focus:border-[#823945] shadow-inner"
                                />
                                <Tag className="absolute right-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-[#823945]/60 pointer-events-none" />
                            </div>
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <label className="text-xs font-bold text-[#5c212c]">
                                Description *
                            </label>
                            <div className="relative">
                                <textarea
                                    rows="2"
                                    value={description}
                                    onChange={(e) =>
                                        setDescription(e.target.value)
                                    }
                                    placeholder="Enter product description..."
                                    required
                                    className="w-full bg-white/60 border border-[#823945]/20 rounded-[20px] p-3 pr-10 text-[#5c212c] placeholder-[#823945]/50 text-xs font-medium focus:outline-none focus:border-[#823945] shadow-inner resize-none"
                                ></textarea>
                                <FileText className="absolute right-3.5 top-4 w-4 h-4 text-[#823945]/60 pointer-events-none" />
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div className="flex flex-col gap-1.5">
                                <label className="text-xs font-bold text-[#5c212c]">
                                    Price ($) *
                                </label>
                                <div className="relative">
                                    <input
                                        type="number"
                                        step="0.01"
                                        value={price}
                                        onChange={(e) =>
                                            setPrice(e.target.value)
                                        }
                                        placeholder="0.00"
                                        required
                                        className="w-full bg-white/60 border border-[#823945]/20 rounded-full py-2.5 pl-4 pr-8 text-[#5c212c] placeholder-[#823945]/50 text-xs font-medium focus:outline-none focus:border-[#823945] shadow-inner"
                                    />
                                    <DollarSign className="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-[#823945]/60 pointer-events-none" />
                                </div>
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <label className="text-xs font-bold text-[#5c212c]">
                                    Stock *
                                </label>
                                <input
                                    type="number"
                                    value={stock}
                                    onChange={(e) => setStock(e.target.value)}
                                    placeholder="Qty..."
                                    required
                                    className="w-full bg-white/60 border border-[#823945]/20 rounded-full py-2.5 px-4 text-[#5c212c] placeholder-[#823945]/50 text-xs font-medium focus:outline-none focus:border-[#823945] shadow-inner"
                                />
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <label className="text-xs font-bold text-[#5c212c]">
                                    Category *
                                </label>
                                <div className="relative">
                                    <select
                                        value={categoryId}
                                        onChange={(e) =>
                                            setCategoryId(e.target.value)
                                        }
                                        required
                                        className="w-full appearance-none bg-white/60 border border-[#823945]/20 rounded-full py-2.5 pl-3 pr-8 text-[#5c212c] text-xs font-medium focus:outline-none focus:border-[#823945] shadow-inner cursor-pointer truncate"
                                    >
                                        <option value="" disabled>
                                            Select...
                                        </option>
                                        {categories.map((cat) => (
                                            <option key={cat.id} value={cat.id}>
                                                {cat.name}
                                            </option>
                                        ))}
                                    </select>
                                    <ChevronDown className="absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[#823945]/60 pointer-events-none" />
                                </div>
                            </div>
                        </div>

                        <div className="flex items-center justify-end gap-3 pt-3 border-t border-[#823945]/15 mt-2">
                            <button
                                type="button"
                                onClick={handleReset}
                                className="py-2 px-5 rounded-full bg-white/60 hover:bg-white text-[#5c212c] text-xs font-bold border border-[#823945]/20 shadow-sm flex items-center gap-1.5 transition-all cursor-pointer"
                            >
                                <RefreshCw className="w-3 h-3 text-[#823945]" />
                                <span>Reset</span>
                            </button>

                            <button
                                type="submit"
                                disabled={isLoading}
                                className="py-2 px-6 rounded-full bg-[#823945] hover:bg-[#6e2f3a] text-white text-xs font-bold shadow-md flex items-center gap-1.5 transition-all cursor-pointer disabled:opacity-50"
                            >
                                <Plus className="w-3.5 h-3.5" />
                                <span>
                                    {isLoading ? "Adding..." : "Add Product"}
                                </span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    );
};

export default AddProductModal;
