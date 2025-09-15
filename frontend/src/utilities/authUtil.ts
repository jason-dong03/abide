import axios from 'axios';

export const handleGoogleLogin= async()=>{
        //user calls backend api fetch, backend sends google details to frontend alongside a access token
        try{
            const url = "http://127.0.0.1:8000/api/auth/google/login/";
            const response = await axios.get(url);
            if (response.status === 200) {
                window.location.href = response.data.redirect_url; // Redirect user to callback
            } else {
                console.error("Failed to start Google login:", response);
            }
            
        }catch(err){
            console.log(err);
            throw err;
        }
    }