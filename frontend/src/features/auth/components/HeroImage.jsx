import heroImg from '../../../assets/login-hero.jpg';

const HeroImage = () => {
  return (
    <div className="relative w-full md:w-[380px] h-[400px] md:h-[500px] rounded-[40px] overflow-hidden shadow-[0_25px_50px_rgba(0,0,0,0.35)] border-2 border-white/50 z-20 -mb-12 md:-mb-0 md:-mr-16 flex-shrink-0">
      <img
        src={heroImg}
        alt="Login Visual"
        className="w-full h-full object-cover"
      />
    </div>
  );
};

export default HeroImage;