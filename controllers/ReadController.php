<?php
declare(strict_types=1);
//require_once 'db.php';

final class ReadController {


    public function create_challenge(): void {
        $challenge_name = trim($_POST['cname'] ?? '');
        $desc = trim($_POST['desc'] ?? '');
        $startDate = trim($_POST['sdate'] ?? '');
        $endDate = trim($_POST['edate'] ?? '');
        $freq = trim($_POST['freq'] ?? '');
        $goal_num = trim($_POST['goal_num'] ?? '');
        $goal_type = trim($_POST['goal_type'] ?? '');
        $is_private = trim($_POST['private'] ?? '');

        if ($challenge_name == '' || $desc == '' ||
        $startDate =='' || $endDate == '' || $freq == ''||
        $goal_num == '' || $goal_type == ''){
            $_SESSION['error'] = 'One or more field has not been filled in, please try again.';
            session_write_close();
            header('Location: challengecreation.php');
            exit;
        } 
        $add_challenge = db::add_challenge($creator_id, $challenge_name, 
                                            $desc, $startDate, $endDate, 
                                            $freq, $goal_num, $goal_type,
                                            $is_private);
                                            //returns boolean success
        if($add_challenge){
            //redirect to dashboard
        }else{
            //send error 
        }
    }
}