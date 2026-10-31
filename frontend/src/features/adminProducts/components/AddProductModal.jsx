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
import productImg from "../../../assets/login-hero.jpg";

const AddProductModal = ({ isOpen, onClose }) => {
    if (!isOpen) return null;

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

                <div className="grid grid-cols-1 md:grid-cols-12 gap-6 items-start">
                    <div className="md:col-span-5 flex flex-col gap-4">
                        <div className="w-full border-2 border-dashed border-[#823945]/30 rounded-[24px] p-6 flex flex-col items-center justify-center bg-white/30 hover:bg-white/40 transition-colors cursor-pointer text-center">
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
                        </div>

                        <div className="relative w-full h-36 rounded-[24px] overflow-hidden border border-white/60 shadow-md group">
                            <img
                                src={productImg}
                                alt="Preview"
                                className="w-full h-full object-cover"
                            />
                            <button
                                type="button"
                                className="absolute top-2.5 right-2.5 w-7 h-7 rounded-full bg-rose-600/90 hover:bg-rose-700 text-white flex items-center justify-center shadow-md transition-all cursor-pointer"
                            >
                                <Trash2 className="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>

                    <div className="md:col-span-7 flex flex-col gap-4">
                        <div className="flex flex-col gap-1.5">
                            <label className="text-xs font-bold text-[#5c212c]">
                                Product Name *
                            </label>
                            <div className="relative">
                                <input
                                    type="text"
                                    placeholder="Enter product name..."
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
                                    rows="3"
                                    placeholder="Enter product description..."
                                    className="w-full bg-white/60 border border-[#823945]/20 rounded-[20px] p-3 pr-10 text-[#5c212c] placeholder-[#823945]/50 text-xs font-medium focus:outline-none focus:border-[#823945] shadow-inner resize-none"
                                ></textarea>
                                <FileText className="absolute right-3.5 top-4 w-4 h-4 text-[#823945]/60 pointer-events-none" />
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div className="flex flex-col gap-1.5">
                                <label className="text-xs font-bold text-[#5c212c]">
                                    Price ($) *
                                </label>
                                <div className="relative">
                                    <input
                                        type="number"
                                        placeholder="Enter price..."
                                        className="w-full bg-white/60 border border-[#823945]/20 rounded-full py-2.5 pl-4 pr-10 text-[#5c212c] placeholder-[#823945]/50 text-xs font-medium focus:outline-none focus:border-[#823945] shadow-inner"
                                    />
                                    <DollarSign className="absolute right-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-[#823945]/60 pointer-events-none" />
                                </div>
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <label className="text-xs font-bold text-[#5c212c]">
                                    Category *
                                </label>
                                <div className="relative">
                                    <select className="w-full appearance-none bg-white/60 border border-[#823945]/20 rounded-full py-2.5 pl-4 pr-10 text-[#5c212c] text-xs font-medium focus:outline-none focus:border-[#823945] shadow-inner cursor-pointer">
                                        <option value="" disabled selected>
                                            Select category...
                                        </option>
                                        <option value="laptops">Laptops</option>
                                        <option value="headphones">
                                            Headphones
                                        </option>
                                        <option value="accessories">
                                            Accessories
                                        </option>
                                    </select>
                                    <ChevronDown className="absolute right-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-[#823945]/60 pointer-events-none" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="flex items-center justify-end gap-3 pt-3 border-t border-[#823945]/15">
                    <button
                        type="button"
                        className="py-2.5 px-6 rounded-full bg-white/60 hover:bg-white text-[#5c212c] text-xs font-bold border border-[#823945]/20 shadow-sm flex items-center gap-2 transition-all cursor-pointer"
                    >
                        <RefreshCw className="w-3.5 h-3.5 text-[#823945]" />
                        <span>Reset</span>
                    </button>

                    <button
                        type="button"
                        onClick={onClose}
                        className="py-2.5 px-7 rounded-full bg-[#823945] hover:bg-[#6e2f3a] text-white text-xs font-bold shadow-md flex items-center gap-2 transition-all cursor-pointer"
                    >
                        <Plus className="w-4 h-4" />
                        <span>Add Product</span>
                    </button>
                </div>
            </div>
        </div>
    );
};

export default AddProductModal;
