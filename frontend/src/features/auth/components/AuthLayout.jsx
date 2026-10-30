import HeroImage from './HeroImage';
import LoginForm from './LoginForm';

const AuthLayout = ({ onLogin }) => {
  return (
    <div className="relative z-10 flex flex-col md:flex-row items-center justify-center w-full max-w-4xl p-4">
      <HeroImage />

      <div className="w-full md:w-[540px] bg-[#df9fa8]/80 backdrop-blur-md rounded-[38px] p-8 md:pl-28 md:pr-14 md:py-16 shadow-[0_25px_60px_rgba(0,0,0,0.3)] border border-white/40 flex items-center justify-center z-10">
        <LoginForm onSubmit={onLogin} />
      </div>
    </div>
  );
};

export default AuthLayout;