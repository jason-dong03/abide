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
//require_once __DIR__ . '/db.php';
require_once __DIR__ . '../controllers/ReadController.php';

$action = $_GET['action'] ?? 'welcome';
$controller = new AnagramsGameController();

switch ($action) {
  case 'create_challenge':
    $controller->createChallenge();
    break;
  default:
    $controller->showWelcome();
    break;
}
