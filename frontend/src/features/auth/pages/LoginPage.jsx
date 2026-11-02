import AuthLayout from "../components/AuthLayout";
import LoginForm from "../components/LoginForm";

const LoginPage = () => {
    return (
        <main className="min-h-screen w-full flex items-center justify-center bg-metallic-shimmer p-4">
            <AuthLayout>
                <LoginForm />
            </AuthLayout>
        </main>
    );
};

export default LoginPage;
