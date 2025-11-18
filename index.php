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

$action = $_GET['action'] ?? $_POST['action'] ?? 'welcome';
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
  case 'delete_challenge':
    $uid = $_SESSION['user']['user_id'] ?? null;
    $cid = (int)($_GET['challenge_id'] ?? 0);
    if (!$uid || !$cid) {
        json_response([
            'success' => false,
            'message' => 'Invalid request'
        ], 400);
    }
    $ok = $controller->deleteChallenge($uid, $cid);
    json_response([
        'success' => $ok,
        'message' => $ok ? "Challenge deleted" : "Failed to delete challenge"
    ]);
    break;
  case 'create_challenge':
    $controller->createChallenge();
    break;
  case 'challenge':
    $controller->showChallenge($cid);
    break;
  case 'edit_challenge':
    $cid = (int)($_GET['challenge_id'] ?? 0);
    $controller ->editChallenge($cid);
    break;

  /* JSON API CALLS*/
   case 'join_challenge':
    $cid = (int)($_GET['cid'] ?? 0);
    $uid = $_SESSION['user']['user_id'] ?? null;
    if (!$uid) {
        json_response(['success' => false, 'error' => 'Not logged in'], 401);
    }
    $result = $controller -> joinChallenge($cid);
    json_response(['success' => (bool)$result, 'message' => $result? "Joined successfully" : "Failed to join challenge"]);
    break;
  case 'add_reading': 
      $uid = $_SESSION['user']['user_id'] ?? null;
      if (!$uid) {
          json_response(['success' => false, 'message' => 'Not logged in'], 401);
      }
      $readingId = $controller->handleAddReading((int)$uid);
      if ($readingId === false) {
          json_response([
              'success' => false,
              'message' => 'Failed to add reading',
          ], 400);
      }
      json_response([
          'success'=> true,
          'message'=> 'Reading added successfully',
          'reading_id'=> $readingId,
      ]);
      break;
  case 'delete_reading': 
      $uid = $_SESSION['user']['user_id'] ?? null;
      $readingID = (int)($_POST['reading_id'] ?? 0);
      $challenge_id = (int)($_POST['cid'] ?? 0);
      if (!$uid) {
          json_response(['success' => false, 'message' => 'Not logged in'], 401);
      }
      $ok = $controller->handleDeleteReading($uid, $challenge_id, $readingID);

      json_response([
          'success' => $ok,
          'message' => $ok ? "Reading deleted" : "Failed to delete reading"
      ]);
      break;
  case 'complete_reading': {
      $uid = $_SESSION['user']['user_id'] ?? null;
      if (!$uid) {
          json_response(['success' => false, 'message' => 'Not logged in'], 401);
      }

      $readingId= $_POST['reading_id'] ?? null;
      $participantId = $_POST['participant_id'] ?? null;

      if (!$readingId || !$participantId) {
          json_response(['success' => false, 'message' => 'Missing reading_id or participant_id'], 400);
      }
      $ok = $controller->handleCompleteReading($uid);

      json_response([
          'success' => (bool)$ok,
          'message' => $ok ? 'Reading completed' : 'Failed to complete reading',
      ]);
      break;
  }
  case 'uncomplete_reading': {
      $uid = $_SESSION['user']['user_id'] ?? null;
      if (!$uid) {
          json_response(['success' => false, 'message' => 'Not logged in'], 401);
      }

      $readingId= $_POST['reading_id'] ?? null;
      $participantId = $_POST['participant_id'] ?? null;

      if (!$readingId || !$participantId) {
          json_response(['success' => false, 'message' => 'Missing reading_id or participant_id'], 400);
      }

      $ok = $controller->handleUncompleteReading($uid);

      json_response([
          'success' => (bool)$ok,
          'message' => $ok ? 'Reading marked incomplete' : 'Failed to update reading',
      ]);
      break;
  }
  case 'leave_challenge': {
      $uid = $_SESSION['user']['user_id'] ?? null;
      if (!$uid) {
          json_response(['success' => false, 'message' => 'Not logged in'], 401);
      }

      $participant_id = $_POST['participant_id'] ?? null;
      if (!$participant_id) {
          json_response(['success' => false, 'message' => 'Missing participant_id'], 400);
      }

      $ok = $controller->handleLeaveChallenge($uid, $participant_id);

      json_response([
          'success' => (bool)$ok,
          'message' => $ok ? 'Left challenge' : 'Failed to leave challenge',
      ]);
      break;
  }
  default:
    $controller->showWelcome();
    break;
}
