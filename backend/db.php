<?php
    final class Db {
        private static ?PDO $pdo = null;

        private static function pdo() {
    if (self::$pdo !== null) {
        return self::$pdo;
    }

    $dsn = "pgsql:host=host.docker.internal;port=5432;dbname=read_db";
        self::$pdo = new PDO($dsn, "jasondong", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    return self::$pdo;
}

    public static function add_user(string $first_name, string $last_name, string $email, string $username, string $password){
        $pdo = Db::pdo();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = 'INSERT INTO read_users (first_name, last_name, email, username, password_hash) VALUES (:fn, :ln, :e, :u, :ph)';

        $stmt = $pdo -> prepare($sql);
        $stmt -> execute([
            ':fn' => $first_name,
            ':ln' => $last_name, 
            ':e' => $email,
            ':u' => $username,
            ':ph' => $hash,
        ]);
    }
    public static function find_user_by_email(string $email): ?array {
        $pdo = Db::pdo();
        $sql = 'SELECT user_id, first_name, last_name, email, username, password_hash, created_at
                            FROM read_users
                            WHERE email = :e LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':e' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null; 
    }
    private static function get_password_hash(string $email): ?string {
        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT password_hash FROM read_users WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => $email]);
        $hash = $stmt->fetchColumn();
        return $hash ?: null;
    }

    public static function verify_password(string $email, string $password){
        $user = Db::find_user_by_email($email);
        if(!$user){
            return false;
        }
        return password_verify($password, $user['password_hash']);
    }

    public static function record_login_event(int $user_id): void {
        $pdo = Db::pdo();

        $sql = "
            INSERT INTO login_events (user_id, logged_in_at, logged_in_date)
            SELECT :u, NOW(), CURRENT_DATE
            WHERE NOT EXISTS (
                SELECT 1
                FROM login_events
                WHERE user_id = :u
                AND logged_in_date = CURRENT_DATE
            )
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':u' => $user_id]);
    }

    public static function get_login_streak(int $user_id): int {
        $pdo = Db::pdo();
        $sql = "
            SELECT logged_in_date
            FROM login_events
            WHERE user_id = :u
            ORDER BY logged_in_date DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':u' => $user_id]);
        $days = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!$days) {
            return 0;
        }

        $today = new DateTimeImmutable('today');
        $todayStr = $today->format('Y-m-d');

        if (in_array($todayStr, $days, true)) {
            $expected = $today;
        } else {
            $expected = $today->modify('-1 day');
        }
        
        $streak = 0;
        foreach ($days as $dayStr) {
            $day = new DateTimeImmutable($dayStr);
            if ($day > $expected) {
                continue;
            }
            if ($day < $expected) {
                break;
            }
            $streak++;
            $expected = $expected->modify('-1 day');
        }
        return $streak;
    }


    public static function add_challenge($creator_id, $challenge_name, $desc, $startDate, $endDate, $freq, $goal_num, $goal_type, $is_private){
        $pdo = Db::pdo();
        $sql = "INSERT INTO challenges (creator_id, title, 
        description, start_date, end_date,frequency, target_amount,
        goal_unit,is_private) VALUES (
        :creator_id,
        :challenge_name,
        :description,
        :start_date,
        :end_date,
        :frequency,
        :goal_num,
        :goal_type,
        :is_private::boolean)";
        $stmt = $pdo->prepare($sql);
     
        $stmt->bindValue(':creator_id',$creator_id,PDO::PARAM_INT);
        $stmt->bindValue(':challenge_name', $challenge_name, PDO::PARAM_STR);
        $stmt->bindValue(':description',$desc,PDO::PARAM_STR);
        $stmt->bindValue(':start_date',$startDate,PDO::PARAM_STR);
        $stmt->bindValue(':end_date',$endDate,PDO::PARAM_STR);
        $stmt->bindValue(':frequency',$freq,PDO::PARAM_STR);  
        $stmt->bindValue(':goal_num',$goal_num,PDO::PARAM_INT);
        $stmt->bindValue(':goal_type',$goal_type,PDO::PARAM_STR);  
        $stmt->bindValue(':is_private',$is_private,PDO::PARAM_BOOL);  
        $stmt->execute();

        return (int) $pdo->lastInsertId();
    }
    public static function get_challenges_for_user($uid){
        $pdo = Db::pdo();
        $sql = 'SELECT DISTINCT c.*, 
                CASE WHEN c.creator_id = :u THEN 1 ELSE 0 END as is_creator
        FROM challenges c
        LEFT JOIN challenge_participants cp ON c.challenge_id = cp.challenge_id
        WHERE c.creator_id = :u OR cp.user_id = :u
        ORDER BY c.challenge_id ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':u' => $uid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function get_all_challenges(){
        $pdo = Db::pdo();
        $sql = '
            SELECT 
                c.*,
                u.first_name as creator_first_name,
                u.last_name as creator_last_name
            FROM challenges c
            LEFT JOIN read_users u ON c.creator_id = u.user_id
            ORDER BY c.created_at DESC ';
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /* FRIENDS DB */
    public static function get_friends(int $user_id): array {
        $pdo = Db::pdo();
        $sql = "
            SELECT u.user_id, u.first_name, u.last_name, u.username, u.email
            FROM friends f
            JOIN read_users u ON u.user_id = f.friend_id
            WHERE f.user_id = :uid
            ORDER BY u.first_name ASC;
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':uid' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function get_notification_count($uid){
        $pdo = Db::pdo();
        $sql = "SELECT COUNT(*) FROM friend_requests 
        WHERE recipient_id = :u AND status = 'pending'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':u' => $uid]);
        return (int)$stmt ->fetchColumn();
    }
    public static function get_user_notifications($uid){
        $pdo = Db::pdo();
        $sql = "SELECT fr.request_id, fr.created_at, ru.user_id,ru.first_name, ru.last_name, ru.username
        FROM friend_requests fr
        JOIN read_users ru ON ru.user_id = fr.requester_id
        WHERE fr.recipient_id = :u AND fr.status = 'pending'
        ORDER BY fr.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':u' => $uid]);
        return $stmt ->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function get_all_users($uid){
        $pdo =Db::pdo();
        $sql ="SELECT user_id, first_name, last_name, username FROM read_users
        WHERE user_id != :u
        ORDER BY first_name, last_name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':u'=>$uid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function get_friends_id($uid){
        $pdo =Db::pdo();
        $sql ="SELECT friend_id FROM friends WHERE user_id = :u";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':u'=>$uid]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    public static function get_pending_requests_sent_by_me($uid){
        $pdo = Db::pdo();
        $sql = "SELECT recipient_id FROM friend_requests 
        WHERE requester_id = :u AND status = 'pending'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':u' =>$uid]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    public static function is_friends($uid, $recipient_id){
        $pdo = Db::pdo();
        $sql="SELECT 1 FROM friends WHERE user_id = :u AND friend_id = :r";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':u'=>$uid, ':r'=>$recipient_id]);
        if ($stmt->fetch()) { //returns something, so yes already friends
            return true;
        }
        return false;
    }
    public static function upsert_request($uid, $recipient_id){
        $pdo = Db::pdo();
        $sql = "INSERT INTO friend_requests (requester_id, recipient_id, status, created_at, updated_at)
        VALUES (:u, :r, 'pending', NOW(), NOW())
        ON CONFLICT (requester_id, recipient_id) 
        DO UPDATE SET status = 'pending', updated_at = NOW()
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':u'=>$uid, ':r'=>$recipient_id]);
    }
    public static function get_request_detail($uid, $request_id){
        $pdo = Db::pdo();
        $sql = " SELECT requester_id, recipient_id FROM friend_requests 
        WHERE request_id = :r AND recipient_id = :u AND status = 'pending'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':r'=>$request_id,':u'=> $uid]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public static function add_friend(int $uid, int $other_id, int $request_id): void {
        $pdo = Db::pdo();
        $pdo->beginTransaction();

        // insert in both directions
        $sql = "INSERT INTO friends (user_id, friend_id, created_at)
                VALUES (:u1, :u2, NOW()),
                    (:u2, :u1, NOW())
                ON CONFLICT DO NOTHING";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':u1' => $uid,
            ':u2' => $other_id
        ]);
        $updateRequestSql = "UPDATE friend_requests
        SET status = 'accepted', updated_at = NOW()
        WHERE request_id = :r";
        $stmt = $pdo->prepare($updateRequestSql);
        $stmt->execute([':r' => $request_id]);

        $pdo->commit();
    }

    public static function reject_request($uid, $request_id){
        $pdo = Db::pdo();
        $sql = "UPDATE friend_requests 
        SET status = 'declined', updated_at = NOW()
        WHERE request_id = :r AND recipient_id = :u AND status = 'pending'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':r' =>$request_id, ':u'=>$uid]);
    }
    /* FRIEND SECTION END  */


    public static function add_challenge_participant($uid, $cid){
        $pdo = Db::pdo();
        $sql = "INSERT INTO challenge_participants (user_id, challenge_id) VALUES (:u, :c)";
        $stmt = $pdo -> prepare($sql);
        $stmt->execute([':u' => $uid, ':c'=> $cid]);
    }
    public static function is_challenge_owner(int $uid, int $cid): bool {
        $pdo = Db::pdo();
        $sql = 'SELECT COUNT(*) FROM challenges 
                WHERE challenge_id = :cid AND creator_id = :uid';
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':cid', $cid, PDO::PARAM_INT);
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->execute();
        
        return (int)$stmt->fetchColumn() > 0;
    }
    public static function is_participant(int $challenge_id, int $user_id): bool {
        $pdo = Db::pdo();
        $sql = "SELECT COUNT(*) FROM challenge_participants 
                WHERE challenge_id = :cid AND user_id = :uid";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':cid', $challenge_id, PDO::PARAM_INT);
        $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn() > 0;
    }
    public static function delete_challenge(int $user_id,int $challenge_id): bool {
        $pdo = Db::pdo();        
        try {                
            $sql = "SELECT creator_id FROM challenges WHERE challenge_id = :challenge_id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':challenge_id', $challenge_id, PDO::PARAM_INT);
            $stmt->execute();
            $creator_id = $stmt->fetchColumn();      
            if (!$creator_id || $creator_id != $user_id) {
                return false;
            }
            $sql = "DELETE FROM challenge_participants WHERE challenge_id = :challenge_id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':challenge_id', $challenge_id, PDO::PARAM_INT);
            $stmt->execute();
            
            
            $sql = "DELETE FROM challenges WHERE challenge_id = :challenge_id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':challenge_id', $challenge_id, PDO::PARAM_INT);
            $stmt->execute();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    public static function count_participants(int $cid): int {
        $pdo = Db::pdo();
        $sql = "SELECT COUNT(*) 
                FROM challenge_participants 
                WHERE challenge_id = :challenge_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':challenge_id', $cid, PDO::PARAM_INT);
        $stmt->execute();
        
        return (int) $stmt->fetchColumn();
    }
    public static function get_challenge_info($uid, $cid) {
        $pdo = Db::pdo();
        $sql = "SELECT c.*, 
            u.first_name || ' ' || u.last_name as creator_name,
            CASE WHEN c.creator_id = :u THEN true ELSE false END as is_owner
            FROM challenges c
            JOIN read_users u ON u.user_id = c.creator_id
            WHERE c.challenge_id = :c
        ";
        $stmt = $pdo ->prepare($sql);
        $stmt->execute([':u' => $uid, ':c'=> $cid]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public static function get_participant_id($uid, $cid){
        $pdo = Db::pdo();
        $sql = "
            SELECT participant_id FROM challenge_participants 
            WHERE challenge_id = :c AND user_id = :u
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':u' => $uid, ':c'=> $cid]);
        return $stmt->fetchColumn();
    }
    public static function get_all_participants($cid){
        $pdo = Db::pdo();
        $sql = "
            SELECT cp.participant_id as participant_id,
                cp.user_id,
                u.username,
                u.first_name,
                u.last_name,
                cp.joined_at
            FROM challenge_participants cp
            JOIN read_users u ON u.user_id = cp.user_id
            WHERE cp.challenge_id = :c
            ORDER BY cp.joined_at
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':c'=> $cid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function get_user_reading_status($pid, $cid){
        $pdo = Db::pdo();
        $sql = "
            SELECT cr.*,
                CASE WHEN rc.participant_id IS NOT NULL THEN true ELSE false END as is_completed,
                rc.completed_at
            FROM challenge_readings cr
            LEFT JOIN reading_completions rc ON rc.reading_id = cr.reading_id 
                AND rc.participant_id = :p
            WHERE cr.challenge_id = :c
            ORDER BY cr.order_num
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':p'=> $pid, ':c'=> $cid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    //maintains reading order when displayed (chap 1, chap 2, etc)
    public static function get_challenge_order_num($cid){
        $pdo = Db::pdo();
        $sql = "SELECT COALESCE(MAX(order_num), 0) + 1 as next_order FROM challenge_readings WHERE challenge_id = :c";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':c' => $cid]);
        return  $stmt->fetch()['next_order'];
    }
    public static function add_reading($challenge_id, $title, $description, $start_page, $end_page,$due_date, $order_num){
        $pdo = Db::pdo();
        $sql = "INSERT INTO challenge_readings (challenge_id, title, description, start_page, end_page, due_date, order_num)
        VALUES (:cid, :t, :d, :s, :e, :dd, :o)
        RETURNING reading_id";
        $stmt = $pdo->prepare($sql);
        $stmt ->execute([':cid' => $challenge_id, 
        ':t' => $title,
        ':d' => $description, 
        ':s' => $start_page, 
        ':e' => $end_page, 
        ':dd' => $due_date, 
        ':o' => $order_num]);
        return $stmt->fetch()['reading_id'];
    }
    public static function update_reading($reading_id, $title, $description){
        $pdo = Db::pdo();
        $sql = "UPDATE challenge_readings SET title = :t, description = :d WHERE reading_id = :r";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':t'=>$title, ':d'=> $description,':r' =>$reading_id]);
    }
    public static function delete_reading($reading_id){
        $pdo = Db::pdo();
        $sql = "DELETE FROM challenge_readings WHERE reading_id = :r";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':r' =>$reading_id]);
    }
    public static function getUserIdByParticipantId($pid){
        $pdo = Db::pdo();
        $sql = "SELECT user_id FROM challenge_participants WHERE participant_id = :pid";
        $stmt = $pdo->prepare($sql); 
        $stmt->execute([':pid' => $pid]); 
        return $stmt->fetch();
    }
    public static function complete_reading($pid, $reading_id){
        $pdo = Db::pdo();
        $sql = "INSERT INTO reading_completions (participant_id, reading_id, completed_at)
        VALUES (:p, :r, NOW())
        ON CONFLICT (participant_id, reading_id) DO NOTHING";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':p' =>$pid, ':r' =>$reading_id]);
    }
    public static function uncomplete_reading($pid, $reading_id){
        $pdo = Db::pdo();
        $sql = "DELETE FROM reading_completions WHERE participant_id = :p AND reading_id = :r";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':p' =>$pid, ':r' =>$reading_id]);
    }
    public static function leave_challenge($pid){
        $pdo = Db::pdo();
        $sql = "DELETE FROM challenge_participants WHERE participant_id = :p";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':p' => $pid]);
    }
    public static function missed_readings($uid){
        $pdo = Db::pdo();
        $sql = "SELECT c.challenge_id,
            c.title AS challenge_title,
            cr.reading_id,
            cr.start_page AS reading_start_page,
            cr.end_page AS reading_end_page,
            cr.title AS reading_title,
            cr.due_date as reading_due_date,
            cp.participant_id
        FROM challenge_participants AS cp
        JOIN challenges AS c  ON cp.challenge_id = c.challenge_id
        JOIN challenge_readings AS cr ON cr.challenge_id = c.challenge_id
        LEFT JOIN reading_completions AS rc 
        ON rc.participant_id = cp.participant_id
        AND rc.reading_id = cr.reading_id
        WHERE cp.user_id = :u       
        AND cr.due_date < CURRENT_DATE 
        AND rc.reading_id IS NULL     
        ORDER BY c.challenge_id, cr.due_date;";

        $stmt = $pdo -> prepare($sql);
        $stmt -> execute([':u' => $uid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function update_challenge_full($cid,$title,$description,$end_date,$frequency,$target_amount,$goal_unit): bool {
        $pdo = Db::pdo();
        $sql = "
            UPDATE challenges
            SET title= :t,
                description= :d,
                end_date= :end_date,
                frequency= :freq,
                target_amount = :target,
                goal_unit = :unit
            WHERE challenge_id = :cid
        ";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':t'=> $title,
            ':d'=> $description,
            ':end_date' => $end_date, 
            ':freq'=> $frequency,
            ':target'=> $target_amount,
            ':unit'=> $goal_unit,
            ':cid'=> $cid,
        ]);
    }
}
?>