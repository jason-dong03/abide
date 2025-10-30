<?php
declare(strict_types=1);
require_once __DIR__ . '/../backend/db.php'; 
final class ReadController {


    public function showWelcome(): void {
        require __DIR__ . '/../pages/welcome.php';
    }
    public function showDashboard(): void {
        
        $challenges = Db::get_challenges_for_user($_SESSION['user']['user_id']);
        $_SESSION['challenges'] = $challenges;
        require __DIR__ . '/../pages/dashboard.php';
    }
    
    public function showCreateChallenge(): void{
        require __DIR__ . '/../pages/challengecreation.php';
    }

    public function showDiscoverChallenges(): void{
        $all_challenges = Db::get_all_challenges();
        $_SESSION['all_challenges'] = $all_challenges;
        require __DIR__ . '/../pages/discover.php';
    }
    public function authUser($mode): void{
         if (!isset($_POST['csrf']) || $_POST['csrf'] !== ($_SESSION['csrf'] ?? '')) {
            http_response_code(400);
            exit('Invalid CSRF');
        }
        if ($mode === "register"){
            $fname = trim($_POST['first_name'] ?? '');
            $lname = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');


            if ($fname === '' || $lname === '' || $email === '' || $username === '' || $password === '') {
                $_SESSION['error'] = "All fields must be filled out!";
                session_write_close();
                header('Location: index.php?action=welcome');
                exit;
            }
            $_SESSION['user'] = [ 
            'name' => $fname . " ". $lname,
            'email' => $email,
            'username' => $username
            ];
            $user = Db::find_user_by_email($email);
            if($user){ 
                $_SESSION['error'] = "This email is already registered, please sign in.";
                session_write_close();
                header('Location: index.php?action=welcome');
                exit;
            }else{
                Db::add_user($fname, $lname, $email, $username, $password);
                $user = Db::find_user_by_email($email);
                session_write_close();
                $_SESSION['user']['user_id'] = $user['user_id'];
                header('Location: index.php?action=dashboard');
                exit;     
            }
        }else{ //login
            $email = trim($_POST['email'] ?? '');
            $password = trim($_POST['password'] ?? '');
            if ($email === '' || $password === '') {
                $_SESSION['error'] = "All fields must be filled out!";
                session_write_close();
                header('Location: index.php?action=welcome');
                exit;
            }
            $user = Db::find_user_by_email($email);
            if($user && Db::verify_password($email, $password)){ 
                $_SESSION['user'] = [ 
                'name' => $user['first_name'] . ' ' . $user['last_name'],
                'email' => $email,
                'username' => $user['username'],
                'user_id' => $user['user_id']
                ];
                session_write_close();
                header('Location: index.php?action=dashboard');
                exit;  
            }else{
                $_SESSION['error'] = "Account not found, please sign up!";
                session_write_close();
                header('Location: index.php?action=welcome');
                exit;
            }
        }
    }
    public function createChallenge(): void {
        $challenge_name = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $startDate = trim($_POST['start-date'] ?? '');
        $endDate = trim($_POST['end-date'] ?? '');
        $freq = trim($_POST['timeframe'] ?? '');
        $goal_num = trim($_POST['goal_num'] ?? '');
        $goal_type = trim($_POST['goal_type'] ?? '');
        $is_private = isset($_POST['private']);

        if ($challenge_name === '' || $desc === '' ||
        $startDate ==='' || $endDate == '' || $freq === ''||
        $goal_num === '' || $goal_type === ''){
            $_SESSION['error'] = 'One or more field has not been filled in, please try again.';
            session_write_close();
            header('Location: index.php?action=start_create_challenge');
            exit;
        } 
        $add_challenge = db::add_challenge($_SESSION['user']['user_id'], $challenge_name, 
        $desc, $startDate, $endDate, 
        $freq, $goal_num, $goal_type,
        $is_private);
        if(!$add_challenge){
            $_SESSION['error']= "Something went wrong, challenge could not be created. Try again later!";
            session_write_close();
        }
        header('Location: index.php?action=dashboard');
        exit;
    }
    public function logout(){
        session_unset();
        session_destroy();
        header('Location: index.php?action=welcome');
        exit;
    }
}