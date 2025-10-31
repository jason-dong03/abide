<?php
session_start();
require_once "db.php";

// Get the currently logged-in user ID from session
$user_id = $_SESSION['user_id'] ?? null;

// Get search term from GET request
$term = $_GET['q'] ?? '';
$term = trim($term);

// If user is not logged in, return empty list
if (!$user_id) {
    echo json_encode([]);
    exit;
}

$pdo = Db::pdo();

// Search for users whose first or last name matches,
// but do NOT return the current user themselves.
$sql = "
    SELECT user_id, first_name, last_name, email
    FROM users
    WHERE (first_name ILIKE :t OR last_name ILIKE :t)
    AND user_id != :uid
    LIMIT 10;
";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    't' => "%$term%",
    'uid' => $user_id
]);

// Return results as JSON
header("Content-Type: application/json");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
