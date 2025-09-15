import { useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';

const CallBack = () => {
  const navigate = useNavigate();
  const executeOnce = useRef(false); 
  useEffect(() => {
    if (executeOnce.current) return;
    executeOnce.current = true; 
    const params = new URLSearchParams(window.location.search);
    const accessToken = params.get('access_token');
    const refreshToken = params.get('refresh_token');
    if (accessToken && refreshToken) {
      localStorage.setItem('access_token', accessToken);
      localStorage.setItem('refresh_token', refreshToken);

      navigate('/dashboard', { replace: true });
    } else {
      navigate('/');
    }
  },[]);

  return <div className='d-flex flex-column justify-content-center text-center' style={{letterSpacing:2, fontSize:20, textTransform:'uppercase'}}>
    Processing Login...
    </div>;
};

export default CallBack;
