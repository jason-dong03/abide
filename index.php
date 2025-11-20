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
$cid = isset($_GET['cid']) ? (int)$_GET['cid'] : null;

$controller = new ReadController();

function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function require_user_id(): int {
    $uid = $_SESSION['user']['user_id'] ?? null;
    if (!$uid) {
        header('Location: index.php?action=welcome');
        exit;
    }
    return (int)$uid;
}


function post_int(string $key): int {
    return isset($_POST[$key]) ? (int)$_POST[$key] : 0;
}


switch($action){
  case 'welcome':
    $controller->showWelcome($uid);
    break;

  case 'auth':
    $controller->authUser($mode);
    break;
  default:
    $uid = require_user_id();
    switch ($action) {
      case 'dashboard':
        $controller->showDashboard($uid);
        break;

      case 'start_create_challenge':
        $controller->showCreateChallenge($uid);
        break;
      case 'discover_challenges':
        $controller->showDiscoverChallenges();
        break;
      case 'upcoming':
        $controller->showUpcoming($uid);
        break;
      case 'profile':
        $controller->showProfile();
        break;
      case 'logout':
        $controller->logout();
         break;
      case 'add_friend':
        $controller->addFriend();
        break;
      case 'catchup':
        $controller->showCatchup();
        break;
      case 'create_challenge':
        $controller->createChallenge($uid);
        break;
      case 'challenge':
        $controller->showChallenge($uid, $cid);
        break;
      /* CHALLENGE JSON */
      case 'edit_challenge':
      {
        $cid = post_int('challenge_id');
        $title = trim($_POST['ch_title'] ?? '');
        $desc = trim($_POST['ch_description'] ?? '');
        $end_date = trim($_POST['ch_end_date'] ?? '');
        $frequency = trim($_POST['ch_frequency'] ?? '');
        $target_amount = post_int('ch_target_amount');
        $goal_unit = trim($_POST['ch_goal_unit'] ?? '');
        if (!$cid || $title === '' || $end_date === '' || $target_amount <= 0 || $goal_unit === '' || $frequency === ''){
            json_response(['success' => false, 'message' => 'Missing or invalid fields'], 400);
        }
        $allowedFreq  = ['daily', 'weekly', 'monthly'];
        $allowedUnits = ['pages', 'chapters'];
        if (!in_array($frequency, $allowedFreq, true) || !in_array($goal_unit, $allowedUnits, true)) {
            json_response(['success' => false, 'message' => 'Invalid frequency or goal unit'], 400);
        }
        $ok = $controller->handleEditChallenge(
            $uid,
            $cid,
            $title,
            $desc,
            $end_date,
            $frequency,
            $target_amount,
            $goal_unit
        );
        json_response([
            'success' => (bool)$ok,
            'message' => $ok ? 'Challenge updated' : 'Failed to update challenge',
        ]);
        break;
      }
      /*  FRIENDS / NOTIFICATIONS API  */
      case 'get_notifications':
      {
        $uid = require_user_id();
        $notifications = Db::get_user_notifications($uid);

        json_response([
            'success' => true,
            'notifications' => $notifications,
        ]);
        break;
      }
      case 'get_all_users':
      {
        $uid = require_user_id();
        $users = Db::get_all_users($uid);
        $friends = Db::get_friends_id($uid);               
        $requested = Db::get_pending_requests_sent_by_me($uid); 
        foreach ($users as &$u) {
            if (in_array($u['user_id'], $friends, true)) {
                $u['status'] = 'friends';
            } elseif (in_array($u['user_id'], $requested, true)) {
                $u['status'] = 'requested';
            } else {
                $u['status'] = 'none';
            }
        }
        unset($u);
        json_response(['success' => true, 'users' => $users]);
        break;
      }
      case 'send_request':
      {
        $uid = require_user_id();
        $recipient_id = post_int('recipient_id');
        if (!$recipient_id) {
            json_response(['success' => false, 'error' => 'Invalid recipient id'], 400);
        }
        if (Db::is_friends($uid, $recipient_id)) {
            json_response(['success' => false, 'error' => 'Already friends'], 400);
        }
        Db::upsert_request($uid, $recipient_id);
        json_response(['success' => true, 'message' => 'Friend request sent']);
        break;
      }
      case 'accept_request':
      {
        $uid = require_user_id();
        $request_id = post_int('request_id');

        if (!$request_id) {
            json_response(['success' => false, 'error' => 'Invalid request'], 400);
        }
        $request = Db::get_request_detail($uid, $request_id);
        if (!$request) {
            json_response(['success' => false, 'error' => 'Request not found'], 404);
        }
        $other_id = (int)$request['requester_id']; 
        Db::add_friend($uid, $other_id, $request_id);
        json_response(['success' => true, 'message' => 'Friend request accepted']);
        break;
      }
      case 'reject_request':
      {
        $uid = require_user_id();
        $request_id = post_int('request_id');

        if (!$request_id) {
            json_response(['success' => false, 'error' => 'Invalid request'], 400);
        }

        $rows = Db::reject_request($uid, $request_id); 

        if ($rows === 0) {
            json_response(['success' => false, 'error' => 'Request not found'], 404);
        }

        json_response(['success' => true, 'message' => 'Friend request rejected']);
        break;
      }
      case 'remove_friend': {
        $uid = require_user_id();
        $friend_id = (int)($_POST['friend_id'] ?? 0);

        if (!$friend_id) {
            json_response(['success' => false, 'message' => 'Missing friend_id'], 400);
        }

        $ok = Db::remove_friend($uid, $friend_id);
        json_response([
            'success' => $ok,
            'message' => $ok ? 'Friend removed' : 'Failed to remove friend',
        ]);
        break;
      }
      /* MSG ROUTES */
    case 'send_message': {
      $uid = require_user_id(); 
      $recipient_id = (int)($_POST['recipient_id'] ?? 0);
      $body = trim($_POST['body'] ?? '');

      if (!$recipient_id || $body === '') {
          json_response(['success' => false, 'message' => 'Missing recipient or message'], 400);
      }
      if (!Db::is_friends($uid, $recipient_id)) {
          json_response(['success' => false, 'message' => 'You can only message friends'], 403);
      }

      $mid = Db::send_message($uid, $recipient_id, $body);
      json_response([
          'success' => true,
          'message' => 'Message sent',
          'message_id' => $mid,
      ]);
      break;
    }
    case 'dismiss_message': {
      $uid = require_user_id();
      $message_id = (int)($_POST['message_id'] ?? 0);
      if (!$message_id) {
          json_response(['success' => false, 'message' => 'Missing message_id'], 400);
      }
      $ok = Db::dismiss_message($uid, $message_id);
      json_response([
          'success' => $ok,
          'message' => $ok ? 'Message dismissed' : 'Not found',
      ]);
      break;
    }
      /* CHALLENGE JSON API */

    case 'join_challenge': {
      $uid = require_user_id();
      $cid = isset($_GET['cid']) ? (int)$_GET['cid'] : 0;
      if (!$cid) {
          json_response(['success' => false, 'message' => 'Invalid challenge id'], 400);
      }
      $result = $controller->joinChallenge($cid);
      json_response([
          'success' => (bool)$result,
          'message' => $result ? 'Joined successfully' : 'Failed to join challenge',
      ]);
      break;
    }
    case 'add_reading':{
      $uid = require_user_id();
      $readingId = $controller->handleAddReading($uid);
      if ($readingId === false) {
          json_response([
              'success' => false,
              'message' => 'Failed to add reading',
          ], 400);
      }
      json_response([
          'success' => true,
          'message' => 'Reading added successfully',
          'reading_id' => $readingId,
      ]);
      break;
    }
    case 'edit_reading':{
      $uid = require_user_id();
      $ok  = $controller->handleEditReading($uid);
      json_response([
          'success' => (bool)$ok,
          'message' => $ok ? 'Reading edited successfully' : 'Failed to edit reading',
      ]);
      break;
    }
    case 'delete_reading':{
      $uid = require_user_id();
      $readingID = post_int('reading_id');
      $challenge_id = post_int('cid');
      if (!$readingID || !$challenge_id) {
          json_response(['success' => false, 'message' => 'Invalid reading or challenge id'], 400);
      }
      $ok = $controller->handleDeleteReading($uid, $challenge_id, $readingID);
      json_response([
          'success' => (bool)$ok,
          'message' => $ok ? 'Reading deleted' : 'Failed to delete reading',
      ]);
      break;
    }
    case 'complete_reading':{
      $uid = require_user_id();
      $readingId = post_int('reading_id');
      $participantId = post_int('participant_id');

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
    case 'uncomplete_reading':{
      $uid = require_user_id();
      $readingId = post_int('reading_id');
      $participantId = post_int('participant_id');

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
    case 'leave_challenge':{
      $uid = require_user_id();
      $participantId = post_int('participant_id');
      if (!$participantId) {
          json_response(['success' => false, 'message' => 'Missing participant_id'], 400);
      }
      $ok = $controller->handleLeaveChallenge($uid, $participantId);
      json_response([
          'success' => (bool)$ok,
          'message' => $ok ? 'Left challenge' : 'Failed to leave challenge',
      ]);
      break;
    }
    case 'delete_challenge':{
      $uid = require_user_id();
      $cid = isset($_GET['challenge_id']) ? (int)$_GET['challenge_id'] : 0;
      if (!$cid) {
          json_response(['success' => false, 'message' => 'Invalid request'], 400);
      }

      $ok = $controller->deleteChallenge($uid, $cid);
      json_response([
          'success' => (bool)$ok,
          'message' => $ok ? 'Challenge deleted' : 'Failed to delete challenge',
      ]);
      break;
    }
    default:
      $controller->showWelcome();
      break;
    }
}
