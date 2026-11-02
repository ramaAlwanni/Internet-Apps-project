import { useState } from "react";
import CustomInput from "./CustomInput";
import { Mail, Lock, User } from "lucide-react";
import { useNavigate } from "react-router-dom";
import { useDispatch, useSelector } from "react-redux";
import { registerUser } from "../authSlice";
import toast from "react-hot-toast";

const SignupForm = () => {
    const navigate = useNavigate();
    const dispatch = useDispatch();
    const { isLoading } = useSelector((state) => state.auth);

    
    const [formData, setFormData] = useState({
        name: "",
        email: "",
        password: "",
        password_confirmation: "",
    });

    const handleChange = (e) => {
        setFormData({ ...formData, [e.target.name]: e.target.value });
    };

    const handleSubmit = async (e) => {
        e.preventDefault();

      
        if (formData.password !== formData.password_confirmation) {
            toast.error("Passwords do not match!");
            return;
        }

        const resultAction = await dispatch(registerUser(formData));

        if (registerUser.fulfilled.match(resultAction)) {
            toast.success("Account created successfully!", {
                icon: "👏",
                style: {
                    background: "#f8e1e5",
                    color: "#5c212c",
                    border: "1px solid #823945",
                },
            });
            navigate("/home"); 
        } else {
            toast.error(
                resultAction.payload ||
                    "Registration failed. Please try again.",
            );
        }
    };

    return (
        <form
            onSubmit={handleSubmit}
            className="w-full max-w-[300px] flex flex-col items-center"
        >
            <h2 className="text-3xl font-bold text-[#5c212c] mb-8 text-center tracking-wide">
                Sign Up
            </h2>

            <CustomInput
                type="text"
                name="name"
                placeholder="Full Name"
                icon={User}
                value={formData.name}
                onChange={handleChange}
            />
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
            <CustomInput
                type="password"
                name="password_confirmation"
                placeholder="Confirm Password"
                icon={Lock}
                value={formData.password_confirmation}
                onChange={handleChange}
            />

            <button
                type="submit"
                disabled={isLoading}
                className="w-full mt-6 py-3 px-6 rounded-full bg-gradient-to-r from-[#bf707c] via-[#a85764] to-[#823945] text-[#fceee8] font-medium shadow-md hover:brightness-105 active:scale-[0.98] transition-all text-sm tracking-wide cursor-pointer disabled:opacity-50"
            >
                {isLoading ? "Creating account..." : "Sign Up"}
            </button>

            <div className="w-full flex justify-center items-center mt-8 text-[12px] font-medium text-[#732f3a]">
                <button
                    type="button"
                    onClick={() => navigate("/")}
                    className="hover:underline opacity-90 hover:opacity-100 cursor-pointer"
                >
                    Already have an account? Login
                </button>
            </div>
        </form>
    );
};

export default SignupForm;
