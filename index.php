<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start([
      'cookie_httponly' => true,
      'cookie_samesite' => 'Lax',
      'cookie_secure'   => isset($_SERVER['HTTPS']),
    ]); 
}
require_once __DIR__ . '/backend/db.php';
require_once __DIR__ . '/controllers/ReadController.php';

$action = $_GET['action'] ?? $_GET['actiontype'] ?? 'welcome';
$mode = $_GET['mode'] ?? '';
$cid = $_GET['cid'] ?? null;
$controller = new ReadController();

function json_response($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

switch ($action) {
  case "welcome":
    $controller -> showWelcome();
    break;
  case 'auth':
    $controller -> authUser($mode);
    break;
  case 'dashboard':
    $controller -> showDashboard();
    break;
  case 'start_create_challenge':
    $controller -> showCreateChallenge();
    break;
  case 'discover_challenges':
    $controller -> showDiscoverChallenges();
    break;
  case 'join_challenge':
    $cid = (int)($_GET['cid'] ?? 0);
    $uid = $_SESSION['user']['user_id'] ?? null;
    if (!$uid) {
        json_response(['success' => false, 'error' => 'Not logged in'], 401);
    }
    $controller -> joinChallenge($cid);
    json_response(['success' => $result, 'message' => 'Joined successfully']);
    break;
  case 'delete_challenge':
    $controller -> deleteChallenge($cid);
  case 'create_challenge':
    $controller->createChallenge();
    break;
  case 'profile':
    $controller ->showProfile();
    break;
  case 'logout':
    $controller -> logout();
    break;
  case 'friends':
    $controller->showFriends();
    break;
  case 'add_friend':
    $controller->addFriend();
    break;
  case 'catchup':
    $controller -> showCatchup();
    break;
  default:
    $controller->showWelcome();
    break;
}
