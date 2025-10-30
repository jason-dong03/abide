<?php
    final class Db {
        private static ?PDO $pdo = null;

        public static function pdo(): PDO {
            if (!self::$pdo) {
            $dsn = "pgsql:host=host.docker.internal;port=5432;dbname=read_db";
            self::$pdo = new PDO($dsn, "jasondong", "", [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            }
            return self::$pdo;
        }
        public static function add_user(string $first_name, string $last_name, string $email, string $username, string $password){
            $pdo = Db::pdo();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = 'INSERT INTO users (first_name, last_name, email, username, password_hash) VALUES (:fn, :ln, :e, :u, :ph)';

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
                                FROM users
                                WHERE email = :e LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':e' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            return $user ?: null; 
        }
        private static function get_password_hash(string $email): ?string {
            $pdo = Db::pdo();
            $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE email = :e LIMIT 1');
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
            $sql = "
                INSERT INTO login_events (user_id)
                SELECT :u
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM login_events
                    WHERE user_id = :u
                    AND date_trunc('day', logged_in_at) = date_trunc('day', now())
                )";
            self::pdo()->prepare($sql)->execute([':u' => $user_id]);
        }

        public static function add_challenge($creator_id, $challenge_name, $desc, $startDate, $endDate, $freq, $goal_num, $goal_type, $is_private): void{
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
            :target_amount,
            :goal_unit,
            :is_private )";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':creator_id'       => $creator_id,
                ':challenge_name'=> $challenge_name,
                ':description'   => $desc,
                ':start_date'    => $startDate,
                ':end_date'      => $endDate,
                ':frequency'     => $freq,
                ':target_amount' => $goal_num,
                ':goal_unit'     => $goal_type,
                ':is_private'    => $is_private,
            ]);
        }
        public static function get_challenges_for_user($uid){
            $pdo = Db::pdo();
            $sql = 'SELECT * FROM challenges WHERE creator_id = :u ORDER BY challenge_id ASC';
            
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
                LEFT JOIN users u ON c.creator_id = u.user_id
                ORDER BY c.created_at DESC ';
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
?>