<?php
declare(strict_types=1);
require_once __DIR__ . '/../backend/db.php'; 
final class ReadController {
    public function showWelcome(): void {
        require __DIR__ . '/../pages/welcome.php';
    }
    public function showDashboard($uid): void {
        $challenges = Db::get_challenges_for_user($uid);
        $_SESSION['challenges'] = $challenges;
        $_SESSION['missed_readings'] = Db::missed_readings($uid);
        $_SESSION['friends_list'] = Db::get_friends($uid);
        $_SESSION['notification_count']= Db::get_notification_count($uid);
        require __DIR__ . '/../pages/dashboard.php';
    }

    public function showCreateChallenge(): void{
        require __DIR__ . '/../pages/challengecreation.php';
    }
   public function handleEditChallenge($uid,$cid,$title,$description, $end_date, $frequency, $target_amount, $goal_unit): bool {
        if (!Db::is_challenge_owner($uid, $cid)) {
            return false;
        }
        return Db::update_challenge_full(
            $cid,
            $title,
            $description,
            $end_date,
            $frequency,
            $target_amount,
            $goal_unit
        );
    }
    public function showDiscoverChallenges(): void{
        $all_challenges = Db::get_all_challenges();
        $_SESSION['all_challenges'] = $all_challenges;
        require __DIR__ . '/../pages/discover.php';
    }
    public function showChallenge($uid, $cid){ 
        $_SESSION['cid'] = $cid;
        $_SESSION['challenge'] = Db::get_challenge_info($uid, $cid);
        $_SESSION['pid'] = Db::get_participant_id($uid, $cid);
        $_SESSION['participants'] = Db::get_all_participants($cid);
        $_SESSION['readings'] = Db::get_user_reading_status($uid, $cid);
        require __DIR__ . '/../pages/challenge.php';
    }

    public function showProfile(): void{
        require __DIR__ . '/../pages/profile.php';
    }

    public function showUpcoming($uid): void{
        $_SESSION['upcoming_readings'] = Db::upcoming_readings($uid);
        require __DIR__ . '/../pages/upcoming.php';
    }
    public function showCatchup(): void{
        $uid = $_SESSION['user']['user_id'];
        $_SESSION['missed_readings'] = Db::missed_readings($uid);
        require __DIR__ . '/../pages/catchup.php';
    }
    public function authUser($mode): void{
        if (!isset($_POST['csrf']) || $_POST['csrf'] !== ($_SESSION['csrf'] ?? '')) {
            http_response_code(400);
            header('Location: index.php?action=welcome');
            exit;
        }
        if ($mode === "register"){
            $fname = trim($_POST['first_name'] ?? '');
            $lname = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $password_confirm = trim($_POST['password_confirm'] ?? '');

            if ($fname === '' || $lname === '' || $email === '' || $username === '' || $password === '') {
                $_SESSION['error'] = "All fields must be filled out!";
                session_write_close();
                header('Location: index.php?action=welcome');
                exit;
            }
            // regex pattern for strong password (https://uibakery.io/regex-library/password-regex-php)
            $password_regex = '/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{8,}$/';
            if (!preg_match($password_regex, $password)) {
                $_SESSION['error'] = 'Password must be at least 8 chars and include uppercase, lowercase, a number, and a special character.';
                header('Location: index.php?action=welcome'); exit;
            }

            if (!hash_equals($password, $password_confirm)) {
                $_SESSION['error'] = 'Passwords do not match.';
                header('Location: index.php?action=welcome'); exit;
            }

            //email validation regex (https://emailregex.com/index.html)
            $email_regex = '/^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/';
            if (!preg_match($email_regex, $email)) {
                $_SESSION['error'] = "Please enter a valid email address (e.g., name@example.com).";
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
                $_SESSION['user']['user_id'] = $user['user_id'];
                $streakInfo = Db::record_login_streak((int)$user['user_id']);
                $_SESSION['user']['login_streak_current'] = $streakInfo['current'];
                $_SESSION['user']['login_streak_longest'] = $streakInfo['longest'];
                session_write_close();
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
                $streakInfo = Db::record_login_streak((int)$user['user_id']);
                $_SESSION['user']['login_streak_current'] = $streakInfo['current'];
                $_SESSION['user']['login_streak_longest'] = $streakInfo['longest'];
                session_write_close();
                header('Location: index.php?action=dashboard');
                exit;  
            }else{
                $_SESSION['error'] = "Incorrect password, try again!";
                session_write_close();
                header('Location: index.php?action=welcome');
                exit;
            }
        }
    }
    public function createChallenge($uid): void {
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
        $cid = db::add_challenge($uid, $challenge_name, 
        $desc, $startDate, $endDate, 
        $freq, $goal_num, $goal_type,
        $is_private);
        $add_challenge_partcipiant = Db::add_challenge_participant($uid, $cid);
        if(!$cid || !$add_challenge_partcipiant){
            $_SESSION['error']= "Something went wrong, challenge could not be created. Try again later!";
            session_write_close();
        }
        header('Location: index.php?action=dashboard');
        exit;
    }
    public function joinChallenge($cid){
        $uid = $_SESSION['user']['user_id'];
        $add_challenge_partcipiant = Db::add_challenge_participant($uid, $cid);
        if($add_challenge_partcipiant){
            $_SESSION['sucess'] = "Successfully joined the challenge! Happy Reading!";
        } else{
            $_SESSION['error'] = "Something went wrong joining the challenge, try again.";
        }
        session_write_close();
        header('Location: index.php?action=dashboard');
        exit;
    }
    public function deleteChallenge(int $uid, int $cid): bool {
        return Db::delete_challenge($uid, $cid);
    }
    public function logout(){
        session_unset();
        session_destroy();
        header('Location: index.php?action=welcome');
        exit;
    }

    /* API HANDLERS IN CONTROLLER */
    public function handleAddReading(int $uid): int|false {
        $challenge_id = intval($_POST['challenge_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $start_page = !empty($_POST['start_page']) ? intval($_POST['start_page']) : null;
        $end_page = !empty($_POST['end_page']) ? intval($_POST['end_page']) : null;
        $due_date = $_POST['due_date'] ?? '';
        
        if (!$title || !$due_date) {
            return false;
        }

        $is_owner = Db::is_challenge_owner($uid, $challenge_id);
        if (!$is_owner) {
            return false;
        }

        $order_num = Db::get_challenge_order_num($challenge_id);

        if (empty($description) && $start_page && $end_page) {
            $description = "pages {$start_page}-{$end_page}";
        }

        $reading_id = Db::add_reading(
            $challenge_id,
            $title,
            $description,
            $start_page,
            $end_page,
            $due_date,
            $order_num
        );

        if (!$reading_id) {
            return false;
        }
        return (int)$reading_id;
    }
    public function handleEditReading($uid){
        $challenge_id = intval($_POST['challenge_id'] ?? 0);
        $title = trim($_POST['edit_title'] ?? '');
        $description = trim($_POST['edit_description'] ?? '');
        $reading_id = trim($_POST['reading_id'] ?? 0);

        $is_owner = Db::is_challenge_owner($uid, $challenge_id);

        if (!$title || !$is_owner) {
            return false;
        }
        Db::update_reading($reading_id, $title, $description);
        return true;
    }

    public function handleDeleteReading($uid, $challenge_id, $readingID){
        $is_owner = Db::is_challenge_owner($uid, $challenge_id);
        if(!$is_owner){
            return false;
        }
        Db::delete_reading($readingID);
        return true;
    }

    public function handleCompleteReading($uid){
        $participant_id = intval($_POST['participant_id'] ?? 0);
        $reading_id = intval($_POST['reading_id'] ?? 0);

        $participant = Db::getUserIdByParticipantId($participant_id);
        if (!$participant || $participant['user_id'] != $uid) {
            return false;
        }
        Db::complete_reading($participant_id, $reading_id);

       
        return true;
    }
    public function handleUncompleteReading($uid){
        $participant_id = intval($_POST['participant_id'] ?? 0);
        $reading_id = intval($_POST['reading_id'] ?? 0);

        $participant = Db::getUserIdByParticipantId($participant_id);
        if (!$participant || $participant['user_id'] != $uid) {
            return false;
        }
        Db::uncomplete_reading($participant_id, $reading_id);
        return true;
    }
    public function handleLeaveChallenge($uid, $pid){
      
        $participant = Db::getUserIdByParticipantId($pid);
        if (!$participant || $participant['user_id'] != $uid) {
            return false;
        }
        Db::leave_challenge($pid);  
        return true;
    }
    
}

