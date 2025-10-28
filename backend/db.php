<?php
    final class Db {
        private static ?PDO $pdo = null;

        public static function pdo(): PDO {
            if (!self::$pdo) {
            $dsn = "pgsql:host=localhost;port=5432;dbname=read";
            self::$pdo = new PDO($dsn, "mum8ky", "-6lQ2HRQKTqJ", [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            }
            return self::$pdo;
        }
        public static function add_user(string $first_name, string $last_name, string $email, string $phone_number, string $password){
            $pdo = self::pdo();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = 'INSERT INTO users (first_name, last_name, email, phone_number, password_hash) VALUES (:fn, :ln, :e, :pn, :ph)';

            $stmt = $pdo -> prepare($sql);
            $stmt -> execute([
                ':fn' => $first_name,
                ':ln' => $last_name, 
                ':e' => $email,
                ':pn' => $phone_number,
                ':ph' => $hash,
            ]);
        }
        public static function find_user(string $email): ?array {
            $pdo = Db::pdo();
            $sql = 'SELECT user_id, first_name, last_name, email, password_hash, created_at
                                FROM users
                                WHERE email = :e LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':e' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            return $user ?: null; 
        }
        private static function get_password_hash(string $email): ?string {
            $pdo = self::pdo();
            $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE email = :e LIMIT 1');
            $stmt->execute([':e' => $email]);
            $hash = $stmt->fetchColumn();
            return $hash ?: null;
        }

        public static function verify_password(string $email, string $password){
            $user = Db::find_user($email);
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
    }
?>