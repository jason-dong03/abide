import { handleGoogleLogin } from "../utilities/authUtil";

const LandingPage: React.FC = () => {
  const bg =
    "https://images.hdqwalls.com/wallpapers/mountains-retreat-minimal-beautiful-4k-vl.jpg";

  return (
    <div className="hero" style={{ backgroundImage: `url('${bg}')`}}>
      <div className="blur-container">
        <h1 className="display-4 fw-bold text-white mb-3">abide.</h1>
        <p className="lead text-white mb-4">
          Deepen your faith through daily Bible challenges, meaningful community,
          <br />and spiritual growth tracking
        </p>
        <button
          className="btn-container"
          onClick={handleGoogleLogin}
        >
          <img
            className="me-1 btn-logo"
            src="https://upload.wikimedia.org/wikipedia/commons/thumb/c/c1/Google_%22G%22_logo.svg/1200px-Google_%22G%22_logo.svg.png"
            alt="Google"
            width={18}
            height={18}
          />
          Login with Google
        </button>
      </div>

      <footer className="footer-overlay text-center">
        <p className="text-black-50 small mb-0">
          © 2025 Jason, Eyuel, Gianna - University of Virginia
        </p>
      </footer>
    </div>
  );
};

export default LandingPage;
