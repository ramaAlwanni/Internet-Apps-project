import { useState } from "react";
import { useDispatch } from "react-redux";
import {
    addCategory,
    fetchCategories,
} from "../../../redux/slices/categorySlice";
import { X, Tag, Plus, RefreshCw } from "lucide-react";
import toast from "react-hot-toast";

const AddCategoryModal = ({ isOpen, onClose }) => {
    const dispatch = useDispatch();
    const [name, setName] = useState("");
    const [isLoading, setIsLoading] = useState(false);

    if (!isOpen) return null;

    const handleSubmit = async (e) => {
        e.preventDefault();
        setIsLoading(true);

        const resultAction = await dispatch(addCategory({ name }));
        setIsLoading(false);

        if (addCategory.fulfilled.match(resultAction)) {
            toast.success("Category added successfully! 😊", {
                style: {
                    background: "#f8e1e5",
                    color: "#5c212c",
                    fontWeight: "bold",
                },
                iconTheme: { primary: "#823945", secondary: "#fff" },
            });
            setName("");
            dispatch(fetchCategories());
            onClose();
        } else {
            toast.error("Failed to add category 😢");
        }
    };

    return (
        <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/40 backdrop-blur-md animate-fadeIn">
            <div className="relative w-full max-w-md bg-[#f8e1e5]/95 backdrop-blur-2xl rounded-[32px] p-6 border border-white/60 shadow-2xl flex flex-col gap-6">
                <div className="flex items-center justify-between pb-3 border-b border-[#823945]/15">
                    <button
                        type="button"
                        onClick={onClose}
                        className="w-8 h-8 rounded-full bg-white/60 hover:bg-white text-[#5c212c] flex items-center justify-center transition-colors shadow-sm cursor-pointer"
                    >
                        <X className="w-4 h-4" />
                    </button>
                    <h3 className="text-xl font-extrabold text-[#5c212c]">
                        Add Category
                    </h3>
                </div>

                <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                    <div className="flex flex-col gap-1.5">
                        <label className="text-xs font-bold text-[#5c212c]">
                            Category Name *
                        </label>
                        <div className="relative">
                            <input
                                type="text"
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                placeholder="Enter category name..."
                                required
                                className="w-full bg-white/60 border border-[#823945]/20 rounded-full py-2.5 pl-4 pr-10 text-[#5c212c] placeholder-[#823945]/50 text-xs font-medium focus:outline-none focus:border-[#823945] shadow-inner"
                            />
                            <Tag className="absolute right-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-[#823945]/60 pointer-events-none" />
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-3 pt-3 border-t border-[#823945]/15">
                        <button
                            type="button"
                            onClick={() => setName("")}
                            className="py-2 px-5 rounded-full bg-white/60 hover:bg-white text-[#5c212c] text-xs font-bold border border-[#823945]/20 shadow-sm flex items-center gap-1.5 cursor-pointer"
                        >
                            <RefreshCw className="w-3 h-3 text-[#823945]" />
                            <span>Reset</span>
                        </button>

                        <button
                            type="submit"
                            disabled={isLoading}
                            className="py-2 px-6 rounded-full bg-[#823945] hover:bg-[#6e2f3a] text-white text-xs font-bold shadow-md flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                        >
                            <Plus className="w-3.5 h-3.5" />
                            <span>
                                {isLoading ? "Adding..." : "Add Category"}
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
};

export default AddCategoryModal;
