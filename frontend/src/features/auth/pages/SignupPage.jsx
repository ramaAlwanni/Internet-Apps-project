import { useNavigate } from 'react-router-dom';
import AuthLayout from '../components/AuthLayout';
import SignupForm from '../components/SignupForm';

const SignupPage = () => {
  const navigate = useNavigate();

  const handleSignup = (e) => {
    e.preventDefault();
  
    navigate('/home'); 
  };

  return (
    <main className="min-h-screen w-full flex items-center justify-center bg-metallic-shimmer p-4">
   
      <AuthLayout>
        <SignupForm onSubmit={handleSignup} />
      </AuthLayout>
    </main>
  );
};

export default SignupPage;