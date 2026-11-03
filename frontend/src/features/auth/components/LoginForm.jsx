import CustomInput from "./CustomInput";
import { Mail, Lock } from "lucide-react";

const LoginForm = ({ onSubmit }) => {
    return (
        <form
            onSubmit={onSubmit}
            className="w-full max-w-[300px] flex flex-col items-center"
        >
            <h2 className="text-3xl font-bold text-[#5c212c] mb-10 text-center tracking-wide">
                Login
            </h2>

            <CustomInput type="email" placeholder="Email" icon={Mail} />
            <CustomInput type="password" placeholder="Password" icon={Lock} />

            <button
                type="submit"
                className="w-full mt-6 py-3 px-6 rounded-full bg-gradient-to-r from-[#bf707c] via-[#a85764] to-[#823945] text-[#fceee8] font-medium shadow-md hover:brightness-105 active:scale-[0.98] transition-all text-sm tracking-wide cursor-pointer"
            >
                Login
            </button>

            <div className="w-full flex justify-between items-center mt-14 text-[12px] font-medium text-[#732f3a]">
                <button
                    type="button"
                    className="hover:underline opacity-90 hover:opacity-100"
                >
                    Create an account
                </button>
                <button
                    type="button"
                    className="hover:underline opacity-90 hover:opacity-100"
                >
                    Forgot password
                </button>
            </div>
        </form>
    );
};

export default LoginForm;
