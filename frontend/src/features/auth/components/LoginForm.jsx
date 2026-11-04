import { useState } from "react";
import CustomInput from "./CustomInput";
import { Mail, Lock } from "lucide-react";
import { useNavigate } from "react-router-dom";
import { useDispatch, useSelector } from "react-redux";
import { loginUser } from "../authSlice";
import toast from "react-hot-toast";

const LoginForm = () => {
    const navigate = useNavigate();
    const dispatch = useDispatch();
    const { isLoading } = useSelector((state) => state.auth);

    const [formData, setFormData] = useState({
        email: "",
        password: "",
    });

    const handleChange = (e) => {
        setFormData({ ...formData, [e.target.name]: e.target.value });
    };

    const handleSubmit = async (e) => {
        e.preventDefault();

        if (!formData.email.trim() || !formData.password.trim()) {
            toast.error("Please fill in all fields!");
            return;
        }

       
        if (formData.password.length < 8) {
            toast.error("Password must be at least 8 characters long!");
            return;
        }

        const resultAction = await dispatch(loginUser(formData));

        if (loginUser.fulfilled.match(resultAction)) {
            toast.success("Logged in successfully! ❤️", {
                icon: "👏",
                style: {
                    background: "#f8e1e5",
                    color: "#5c212c",
                    border: "1px solid #823945",
                },
            });

            const resData = resultAction.payload;
            const userObj = resData.user || resData.data?.user;
            const userRole = userObj?.role;

            if (userRole === "admin") {
                navigate("/admin/products");
            } else {
                navigate("/home");
            }
        } else {
           
            toast.error(
                resultAction.payload || "Invalid email or password. Please try again."
            );
        }
    };

    return (
        <form
            onSubmit={handleSubmit}
            className="w-full max-w-[300px] flex flex-col items-center"
        >
            <h2 className="text-3xl font-bold text-[#5c212c] mb-10 text-center tracking-wide">
                Login
            </h2>

            <CustomInput
                type="email"
                name="email"
                placeholder="Email"
                icon={Mail}
                value={formData.email}
                onChange={handleChange}
            />
            <CustomInput
                type="password"
                name="password"
                placeholder="Password"
                icon={Lock}
                value={formData.password}
                onChange={handleChange}
            />

            <button
                type="submit"
                disabled={isLoading}
                className="w-full mt-6 py-3 px-6 rounded-full bg-gradient-to-r from-[#bf707c] via-[#a85764] to-[#823945] text-[#fceee8] font-medium shadow-md hover:brightness-105 active:scale-[0.98] transition-all text-sm tracking-wide cursor-pointer disabled:opacity-50"
            >
                {isLoading ? "Logging in..." : "Login"}
            </button>

            <div className="w-full flex justify-between items-center mt-14 text-[12px] font-medium text-[#732f3a]">
                <button
                    type="button"
                    onClick={() => navigate("/signup")}
                    className="hover:underline opacity-90 hover:opacity-100 cursor-pointer"
                >
                    Create an account
                </button>
                <button
                    type="button"
                    className="hover:underline opacity-90 hover:opacity-100 cursor-pointer"
                >
                    Forgot password
                </button>
            </div>
        </form>
    );
};

export default LoginForm;