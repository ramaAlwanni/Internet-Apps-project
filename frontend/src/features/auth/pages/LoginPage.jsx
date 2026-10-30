import { useNavigate } from 'react-router-dom';
import AuthLayout from '../components/AuthLayout';

const LoginPage = () => {
  const navigate = useNavigate();

  const handleLogin = (e) => {
    e.preventDefault();
    navigate('/home'); 
  };

  return (
    <main className="min-h-screen w-full flex items-center justify-center bg-metallic-shimmer p-4">
      <AuthLayout onLogin={handleLogin} />
    </main>
  );
};

export default LoginPage;