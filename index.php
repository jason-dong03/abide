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
$controller = new ReadController();

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

  default:
    $controller->showWelcome();
    break;
}
