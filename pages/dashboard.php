<?php
  declare(strict_types=1);
  if (session_status() === PHP_SESSION_NONE) {
      session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure'   => isset($_SERVER['HTTPS']),
      ]);
  }

  $user = $_SESSION['user'] ?? null;

  if (!$user) {
    header('Location: /abide/index.php?action=welcome');
    exit;
  }

  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32)); 
  }
  $csrf = $_SESSION['csrf'];
  $error = $_SESSION['error'] ?? null;
  unset($_SESSION['error']); 

  function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }


function streak_emoji(int $streak): string {
    if ($streak >= 30) {
        return '🌴'; // insane!
    } elseif ($streak >= 7) {
        return '🌳'; // strong
    } elseif ($streak >= 3) {
        return '🪴'; // getting there
    } elseif ($streak >= 1) {
        return '🌱'; // just started
    }
    return '💤'; // n/a
}


$friends = $_SESSION['friends_list'] ?? [];
$notification_count = $_SESSION['notification_count'] ?? 0;

$challenges = $_SESSION['challenges'] ?? [];
$missed_readings = $_SESSION['missed_readings'] ?? [];
$upcoming_readings = $_SESSION['upcoming_readings'] ?? [];

$today = new DateTimeImmutable('today');

$friendsCount = count($friends);
$streakCount  = Db::get_login_streak((int)$user['user_id']);

$activeChallenges = [];
$completedChallenges = [];

foreach ($challenges as $ch) {
    $endDate = new DateTimeImmutable($ch['end_date']);
    $isFinished = !empty($ch['is_finished']); 

    if ($isFinished) {
        $completedChallenges[] = $ch;
    } else {
        $activeChallenges[] = $ch;
    }
}

function pct_progress(string $start, string $end, DateTimeImmutable $today): int {
    $s = new DateTimeImmutable($start);
    $e = new DateTimeImmutable($end);
    $total = max(1, (int)$e->diff($s)->days + 1);
    $elapsed = (int)$today->diff($s)->invert ? min($total, (int)$today->diff($s)->days + 1) : 0;
    return (int)round(100 * $elapsed / $total);
}

function day_number(string $start, string $end, DateTimeImmutable $today): int {
    $s = new DateTimeImmutable($start);
    $e = new DateTimeImmutable($end);
    if ($today < $s) return 1;
    $total = max(1, (int)$e->diff($s)->days + 1);
    $elapsed = min($total, (int)$today->diff($s)->days + 1);
    return max(1, $elapsed);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <base href="/abide/">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="A reading website that helps you track your reading!">
  <meta name="author" content="Gianna M">
  <title>read | dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles/theme.css">
  <link rel="stylesheet" href="styles/dashboard.css">
</head>

<body class="d-flex flex-column min-vh-100">
  <header>
    <nav class="navbar navbar-light bg-light bg-opacity-75 shadow-sm backdrop-blur">
      <div class="container-fluid px-5 py-2 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
          <span class="fw-bold pe-4">read</span>
        </div>
        <div class="d-flex align-items-center gap-4">
           <button onclick="startTour()" class="btn btn-link p-0 nav-icon-link" aria-label="Help & Tour" title="Start guided tour">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--ink)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"></circle>
              <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
              <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
          </button>

          <div class="notification-icon-wrapper">
            <button id="notificationBtn" class="btn btn-link p-0 nav-icon-link" aria-label="Notifications">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                stroke="var(--ink)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 12a6 6 0 0 0-12 0c0 3-1 5-2 6h16c-1-1-2-3-2-6"></path>
                <path d="M13.73 20a2 2 0 0 1-3.46 0"></path>
              </svg>
              <span id="notificationBadge" class="notification-badge" style="display: none;">0</span>
            </button>
            <div id="notificationsDropdown" class="notifications-dropdown">
              <div class="p-3 border-bottom">
                <strong>Notifications</strong>
              </div>
              <div id="notificationsList" >
                <div class="notification-empty">No notifications</div>
              </div>
            </div>
          </div>
          <a href="index.php?action=profile" class="nav-icon-link d-flex align-items-center gap-2" aria-label="Profile">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
              stroke="var(--ink)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="8" r="4"></circle>
              <path d="M5.5 21a6.5 6.5 0 0 1 13 0"></path>
            </svg>
            <span class="d-none d-lg-inline">Profile</span>
          </a>
          <a href="index.php?action=logout" class="nav-icon-link d-flex align-items-center gap-1" aria-label="Logout">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
              stroke="var(--ink)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
              <path d="M16 17l5-5-5-5"></path>
              <path d="M21 12H9"></path>
            </svg>
            <span>Logout</span>
          </a>
        </div>
      </div>
    </nav>
  </header>

  <!-- main -->
  <main class="container my-4">
   <div class="row g-3 mb-4">
    <?php $isEmpty = empty($challenges); ?>
    <div class="col-md-8">
      <div class="card glass-primary clickable-card p-4 position-relative">
        <span class="fw-semibold mb-1"><?= $isEmpty ? 'Start reading something, '. h($user['name']) : 'Continue Reading, ' . h($user['name']) ?></span>
        <p class="mb-0 subtitle">View your scheduled readings and stay on track <span class="text-success" style="font-size:11px; <?= count($upcoming_readings) > 0? "" :"display:none;"?>"><?= count($upcoming_readings)?> available reading(s)</span></p>
        <a href="index.php?action=upcoming" class="stretched-link" aria-label="View your scheduled readings and check off progress" tabindex="0"></a>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card <?= count($missed_readings) >0?"glass-danger ": "glass-success"?> clickable-card p-4 position-relative">
        <span class="catchup-label mb-1">Catch Up</span>
        <p class="catchup-desc mb-0 subtitle">
          You have <span class="<?= count($missed_readings) >0?"emph ": "text-success"?> "> <?= count($missed_readings)?> missed readings</span>
        </p>
        <a href="index.php?action=catchup" class="stretched-link" aria-label="Access and catch up with late readings" tabindex="0"></a>
      </div>
    </div>
  </div>


    <!-- badges -->
<div class="card glass-card kpi-strip mb-4">
    <div class="kpi">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
        stroke="var(--ink)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"></circle>
        <path d="M12 7v5l3 2"></path>
      </svg>
      <div class="kpi-meta">
        <span class="kpi-label">Active</span>
        <span class="kpi-value"><?= count($activeChallenges)?></span>
      </div>
    </div>

    <div class="kpi">
     <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
        stroke="var(--ink)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="5 13 9 17 19 7"></polyline>
      </svg>
      <div class="kpi-meta">
        <span class="kpi-label">Completed</span>
        <span class="kpi-value"><?= count($completedChallenges)?></span>
      </div>
    </div>

    <div class="kpi">
     <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
        stroke="var(--ink)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
        <line x1="8" y1="2" x2="8" y2="6"></line>
        <line x1="16" y1="2" x2="16" y2="6"></line>
        <line x1="3" y1="10" x2="21" y2="10"></line>
        <polyline points="9 16 11.5 18.5 15 15"></polyline>
      </svg>
      <div class="kpi-meta">
       <?php $streakCurrent = (int)($user['login_streak_current'] ?? 0);
          $streakEmoji = streak_emoji($streakCurrent); ?>
        <span class="kpi-label">Streak</span>
        <span class="kpi-value">
          <?= $streakCurrent ?> <?= $streakEmoji ?>  <span class="text-muted small fw-light" style="font-size: 12px;">(best: <?= (int)($user['login_streak_longest'] ?? 0) ?>)</span>
        </span>
      </div>
    </div>


    <div class="kpi">
     <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
        stroke="var(--ink)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="9" cy="8" r="3"></circle>
        <circle cx="17" cy="9" r="3"></circle>
        <path d="M4 20a5 5 0 0 1 10 0"></path>
        <path d="M14 20a4 4 0 0 1 7 0"></path>
      </svg>
      <div class="kpi-meta">
        <span class="kpi-label">Friends</span>
        <span class="kpi-value"><?= count($friends)?></span>
      </div>
    </div>
  </div>


    <!-- challenges & freinds section -->
    <div class="flex-container g-4 align-items-stretch equal-panels">
      <?php $isEmpty = empty($challenges); ?>
      <div class="col-lg-9">
        <div class="card glass-card p-4 <?= $isEmpty ? '' : 'h-100' ?>">
          <div class="d-flex align-items-center mb-3">
            <svg class="me-2" width="24" height="24" viewBox="0 0 24 24" fill="none"
              stroke="var(--ink)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M6 3h12a1 1 0 0 1 1 1v17l-7-4-7 4V4a1 1 0 0 1 1-1z"></path>
            </svg>
            <span class="fw-semibold mb-0">Your Challenges</span>
            <div class="ms-auto d-flex gap-2">
              <a href="index.php?action=start_create_challenge" class="btn btn-create d-flex align-items-center gap-1">
               <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                  stroke="var(--ink)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="12" y1="5" x2="12" y2="19"></line>
                  <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
              </a>
              <a href="index.php?action=discover_challenges" class="btn btn-discover">Discover</a>
            </div>
          </div>

            <div class="challenge-filters mb-3 d-flex gap-2">
              <button class="filter-btn active" data-filter="active" onclick="toggleFilter('active')">
                Active
              </button>
              <button class="filter-btn" data-filter="expired" onclick="toggleFilter('expired')">
                Expired
              </button>
              <button class="filter-btn" data-filter="completed" onclick="toggleFilter('completed')">
                Completed
              </button>
            </div>

          <?php if ($isEmpty): ?>
              <p class="text-muted small mb-0">No challenges yet.</p>
            <?php else: ?>
              <div class="challenge-list d-flex flex-column gap-3">
                <?php foreach ($challenges as $ch): 
                      $title = $ch['title'] ?? 'Untitled';
                      $desc = $ch['description'] ?? '';
                      $end = $ch['end_date'];
                      $start = $ch['start_date'];
                      $pct = pct_progress($start, $end, $today);
                      $day = day_number($start, $end, $today);
                      $endsPretty = date('M j, Y', strtotime($end));
                      $cid = (int)$ch['challenge_id'];
                      $participants = Db::count_participants($cid);
                      $isOwner = Db::is_challenge_owner($user['user_id'], $cid);
                      $endDate = (new DateTimeImmutable($end))->setTime(0, 0, 0);
                      $isExpired = $endDate < $today; 
                      $isCompletedChallenge = (bool)$ch['is_finished'];
                      $status = 'active';
                      if ($isCompletedChallenge) {
                          $status = 'completed';
                      } elseif ($isExpired) {
                          $status = 'expired';
                      }
                     
                ?>
                  <div class="card glass-card p-3 position-relative challenge <?= $isExpired ? 'disabled-challenge' : '' ?> <?= $isCompletedChallenge ? 'completed-challenge' : '' ?>"
                    data-status="<?= $status ?>">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <strong><?= h($title) ?></strong>
                      <div class="d-flex gap-2">
                        <span class="badge badge-active text-center fw-normal" style="<?= $isCompletedChallenge? "": "display:none;"?>">Completed</span>
                        <span class="badge <?= $isOwner ? 'badge-owner' : 'badge-member' ?> text-center fw-normal"><?= $isOwner? "Owner": "Member"?></span>
                        <span class="badge <?= $isExpired ? 'badge-inactive' : 'badge-active' ?> text-center fw-normal"><?= $isExpired? "Inactive": "Active"?></span>
                        <span class="badge bg-brown text-center fw-normal">Day <?= $day ?></span>
                      </div>
                    </div>

                    <?php if ($desc !== ''): ?>
                      <p class="mb-2 small"><?= h($desc) ?></p>
                    <?php endif; ?>

                    <div class="progress mb-2" style="height:20px;">
                      <div class="progress-bar" style="width:<?= $pct ?>%"></div>
                    </div>

                    <div class="d-flex justify-content-between small text-muted">
                      <span>Ends <?= h($endsPretty) ?></span>
                      <span><?= $participants ?> participants</span>
                    </div>

                    <?php if (!$isExpired && !$isCompletedChallenge): ?> 
                        <a href="index.php?action=challenge&cid=<?= $cid ?>" class="stretched-link" aria-label="View challenge"></a>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

      <!-- friens-->
      <aside class="col-lg-3 friends-sidebar ms-auto">
        <div class="card glass-card p-4 h-100 d-flex flex-column">
          <div class="d-flex align-items-center mb-3">
           <svg class="me-2" width="24" height="24" viewBox="0 0 24 24" fill="none"
              stroke="var(--ink)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="9" cy="8" r="3"></circle>
              <circle cx="17" cy="9" r="3"></circle>
              <path d="M4 20a5 5 0 0 1 10 0"></path>
              <path d="M14 20a4 4 0 0 1 7 0"></path>
            </svg>
            <span class="fw-semibold mb-0">Friends</span>
          </div>  
          <input type="text" id="friendSearch" class="form-control form-control-sm mb-3" placeholder="Search…" aria-label="Search friends">    
          <div class="friends-list d-flex flex-column gap-1">
            <?php if (empty($friends)): ?>
              <p class="text-muted small mb-3">No friends yet</p>
            <?php else: ?>
              <?php foreach ($friends as $friend): 
                $fid = (int)$friend['user_id'];
                $fname = $friend['first_name'] . ' ' . $friend['last_name'];?>
                <div class="d-flex align-items-center gap-2 friend-item" 
                role ="button" 
                data-friend-id="<?= $fid?>"
                data-friend-name = "<?=$fname?>">
                  <svg width="24" height="24" viewBox="0 0 24 24 " fill="none"
                    stroke="var(--ink)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="8" r="4"></circle>
                    <path d="M5.5 21a6.5 6.5 0 0 1 13 0"></path>
                  </svg>
                  <span class="small"><?= h($fname) ?></span>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <button id ="addFriendBtn" class="btn btn-discover mt-auto w-100" aria-label="Add Friends">Add</button>
        </div>
      </aside>
    </div>

  </main>

  <footer class="text-center mt-auto py-3 small footer-label">
    © 2025 Jason, Eyuel, Gianna – University of Virginia
  </footer>

   <div class="modal fade" id="addFriendModal" tabindex="-1" aria-labelledby="addFriendModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="addFriendModalLabel">Add Friends</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="text" id="userSearchInput" class="form-control mb-3" placeholder="Search users...">
          <div id="usersList" class="modal-user-list">
            <div class="text-center py-4">
              <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

<div id="friendActionsTooltip" class="friend-tooltip" style ="display:none;"> 
  <p id="friendActionsTooltipHeader" class="ps-2 mt-1 mb-1 fw-semibold">Name</p>
  <button type ="button" class="dropdown-item" id="friendActionMessage">Message</button>
  <button type ="button" class="dropdown-item text-danger" id="friendActionRemove">Remove</button>
</div>

<div id="flashBanner" class="flash-banner" style="display:none;"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  let allUsers = [];
  let notificationCount = <?= $notification_count ?>;
  
  updateNotificationBadge(notificationCount);
  
  const friendTooltip = document.getElementById('friendActionsTooltip');
  let currFriendId = null;
  let currFriendName = '';


  document.getElementById('notificationBtn').addEventListener('click', function(e) {
    e.stopPropagation();
    const dropdown = document.getElementById('notificationsDropdown');
    dropdown.classList.toggle('show');
    if (dropdown.classList.contains('show')) {
      loadNotifications();
    }
  });

  document.querySelectorAll('.friend-item').forEach(item =>{
    item.addEventListener('click', function(e) {
      e.stopPropagation();
      const rect = item.getBoundingClientRect();
      currFriendId = parseInt(item.dataset.friendId, 10);
      currFriendName = item.dataset.friendName;

      friendTooltip.style.display = 'block';
      friendTooltip.style.top = `${rect.bottom + window.scrollY + 4}px`;
      friendTooltip.style.left = `${rect.left + window.scrollX}px`;
      document.getElementById('friendActionsTooltipHeader').textContent = currFriendName;
    });
  });


  friendTooltip.addEventListener('click', (e) => {
    e.stopPropagation(); 
  });

  document.getElementById('friendActionRemove').addEventListener('click', ()=>{
    if(!currFriendId){ return; }
    fetch('index.php?action=remove_friend', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `friend_id=${currFriendId}`
    })
   /* .then(response => response.text())
    .then(txt => {
        console.log('RAW RESPONSE:', txt);
    })*/
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        document.querySelectorAll('.friend-item').forEach(el => {
          if (parseInt(el.dataset.friendId, 10) === currFriendId) {
            el.remove();
          }
        });
        friendTooltip.style.display = 'none';
        location.reload();
      } else {
        alert(data.message || 'Failed to remove friend');
      }
    })
    .catch(err => {
      console.error(err);
      alert('Error removing friend');
    });
  });
  
  document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('notificationsDropdown');
    const btn = document.getElementById('notificationBtn');
    if (!dropdown.contains(e.target) && !btn.contains(e.target)) {
      dropdown.classList.remove('show');
    }

    friendTooltip.style.display = 'none';
  });
  

  document.getElementById('addFriendBtn').addEventListener('click', function() {
    const modal = new bootstrap.Modal(document.getElementById('addFriendModal'));
    modal.show();
    loadAllUsers();
  });
  
  document.getElementById('userSearchInput').addEventListener('input', function(e) {
    filterUsers(e.target.value);
  });

  document.getElementById('friendActionMessage').addEventListener('click', () => {
    if (!currFriendId) return;
    const body = prompt(`Send a message to ${currFriendName}:`);
    if (!body) return;

    fetch('index.php?action=send_message', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `recipient_id=${encodeURIComponent(currFriendId)}&body=${encodeURIComponent(body)}`
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        friendTooltip.style.display = 'none';
        showFlash("Message successfully sent!");
      } else {
        showFlash((data.message || "Fail to send message"), "error");
      }
    })
    .catch(err => {
      console.error(err);
      alert('Error sending message');
    });
  });

function updateNotificationBadge(count) {
    notificationCount = count;
    const badge = document.getElementById('notificationBadge');
    if (count > 0) {
      badge.textContent = count;
      badge.style.display = 'flex';
    } else {
      badge.style.display = 'none';
    }
  }
  
function loadNotifications() {
    fetch('index.php?action=get_notifications', {
      method: 'GET',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        displayNotifications(data.notifications);
        updateNotificationBadge(data.notifications.length);
      }
    });
  }
  
function displayNotifications(notifications) {
    const container = document.getElementById('notificationsList');
    
    if (!notifications || notifications.length === 0) {
      container.innerHTML = '<div class="notification-empty">No notifications</div>';
      return;
    }
    
    container.innerHTML = notifications.map(n => {
      if (n.type === 'request') {
        return `
          <div class="notification-item">
            <div class="d-flex align-items-center gap-2 mb-2">
              <svg width="24" height="24" viewBox="0 0 24 24 " fill="none"
                stroke="var(--ink)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="6" r="4"></circle>
                <path d="M5.5 18a6.5 6.5 0 0 1 13 0"></path>
              </svg>
              <div>
                <strong>${escapeHtml(n.first_name + ' ' + n.last_name)}</strong>
                <div class="small text-muted">@${escapeHtml(n.username)}</div>
              </div>
            </div>
            <div class="notification-actions">
              <button class="btn btn-sm btn-primary" onclick="acceptRequest(${n.request_id})">Accept</button>
              <button class="btn btn-sm btn-outline-secondary" onclick="rejectRequest(${n.request_id})">Decline</button>
            </div>
          </div>
        `;
      } else if (n.type === 'message') {
        return `
          <div class="notification-item d-flex justify-content-between align-items-start">
            <div>
              <div class="d-flex align-items-center gap-2 mb-1">
                <svg width="24" height="24" viewBox="0 0 24 24 " fill="none"
                  stroke="var(--ink)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="6" r="4"></circle>
                  <path d="M5.5 18a6.5 6.5 0 0 1 13 0"></path>
                </svg>
                <div>
                  <strong>${escapeHtml(n.first_name + ' ' + n.last_name)}</strong>
                  <div class="small text-muted">@${escapeHtml(n.username)}</div>
                </div>
              </div>
              <div class="small">${escapeHtml(n.message_body)}</div>
            </div>
            <button class="btn btn-sm btn-outline-secondary" onclick="dismissMessage(${n.message_id})">✕</button>
          </div>
        `;
      }
      return '';
    }).join('');
  }

function dismissMessage(messageId) {
    fetch('index.php?action=dismiss_message', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `message_id=${messageId}`
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        loadNotifications();
      } else {
        alert(data.message || 'Failed to dismiss message');
      }
    })
    .catch(err => {
      console.error(err);
      alert('Error dismissing message');
    });
  }

  
function acceptRequest(requestId) {
    fetch('index.php?action=accept_request', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `request_id=${requestId}`
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        location.reload();
      } else {
        alert(data.message);
      }
    })
    /*.then(response => response.text())
    .then(txt => {
        console.log('RAW RESPONSE:', txt);
    })*/
    .catch(error => {
      console.error('Error:', error);
    });
  }
  
function rejectRequest(requestId) {
    fetch('index.php?action=reject_request', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `request_id=${requestId}`
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        location.reload();
      } else {
        alert(data.message);
      }
    })
     .catch(error => {
      console.error('Error:', error);
    });
  }
  
function loadAllUsers() {
    fetch('index.php?action=get_all_users', {
      method: 'GET',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        allUsers = data.users;
        displayUsers(allUsers);
      }
    })
    .catch(error => {
      console.error('Error:', error);
    });
  }
  
function displayUsers(users) {
    const container = document.getElementById('usersList');
    
    if (users.length === 0) {
      container.innerHTML = '<div class="text-center text-muted py-4">No users found</div>';
      return;
    }
    
    container.innerHTML = users.map(u => {
      let btnHtml = '';
      if (u.status === 'friends') {
        btnHtml = '<button class="btn btn-sm btn-success btn-add-friend" disabled>Friends</button>';
      } else if (u.status === 'requested') {
        btnHtml = '<button class="btn btn-sm btn-secondary btn-add-friend" disabled>Requested</button>';
      } else {
        btnHtml = `<button class="btn btn-sm btn-primary btn-add-friend" onclick="sendFriendRequest(${u.user_id}, this)">Add</button>`;
      }
      
      return `
        <div class="user-item">
          <div class="d-flex align-items-center gap-2">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
              stroke="var(--ink)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="8" r="4"></circle>
              <path d="M5.5 21a6.5 6.5 0 0 1 13 0"></path>
            </svg>
            <div>
              <div><strong>${escapeHtml(u.first_name + ' ' + u.last_name)}</strong></div>
              <div class="small text-muted">@${escapeHtml(u.username)}</div>
            </div>
          </div>
          ${btnHtml}
        </div>
      `;
    }).join('');
  }
  
function filterUsers(search) {
    const filtered = allUsers.filter(u => {
      const name = (u.first_name + ' ' + u.last_name).toLowerCase();
      const username = u.username.toLowerCase();
      const term = search.toLowerCase();
      return name.includes(term) || username.includes(term);
    });
    displayUsers(filtered);
  }

const friendSearchInput = document.getElementById('friendSearch');

if (friendSearchInput) {
    friendSearchInput.addEventListener('input', function () {
      const term = this.value.trim().toLowerCase();

      document.querySelectorAll('.friends-list .friend-item').forEach(item => {
        const name = (item.dataset.friendName || item.textContent).toLowerCase();

        if (!term || name.includes(term)) {
          item.style.display = '';
        } else {
          item.style.display = 'none';
        }
      });
    });
  }

  
function sendFriendRequest(userId, btn) {
    btn.disabled = true;
    btn.textContent = 'Sending...';
    
    fetch('index.php?action=send_request', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `recipient_id=${userId}`
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        btn.textContent = 'Requested';
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-secondary');
      
        const user = allUsers.find(u => u.user_id === userId);
        if (user) user.status = 'requested';
      } else {
        alert(data.message);
        btn.disabled = false;
        btn.textContent = 'Add';
      }
    })
    .catch(error => {
      btn.textContent = 'Add';
      btn.disabled = false;
      console.error('Error:', error);
    });
  }
  
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
  let flashTimeout;

function showFlash(message, type = 'success') {
    const banner = document.getElementById('flashBanner');
    if (!banner) return;

    banner.textContent = message;
    banner.className = 'flash-banner' + (type === 'error' ? ' error' : '');
    banner.style.display = 'block';

    clearTimeout(flashTimeout);
    flashTimeout = setTimeout(() => {
      banner.style.display = 'none';
    }, 2500); 
  }
document.addEventListener('DOMContentLoaded', function() {
  loadFilterState();
  applyFilters();
});

function toggleFilter(filterType) {
  const clickedBtn = document.querySelector(`.filter-btn[data-filter="${filterType}"]`);
  const isActive = clickedBtn.classList.contains('active');

document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
  if (!isActive) {
    clickedBtn.classList.add('active');
  }

  saveFilterState();
  applyFilters();
}

function saveFilterState() {
  const activeFilters = [];
  document.querySelectorAll('.filter-btn.active').forEach(btn => {
    activeFilters.push(btn.dataset.filter);
  });
  localStorage.setItem('challengeFilters', JSON.stringify(activeFilters));
}

function loadFilterState() {
  const saved = localStorage.getItem('challengeFilters');
  if (!saved) {
    return;
  }

  const activeFilters = JSON.parse(saved);

  document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.classList.remove('active');
  });

  activeFilters.forEach(filter => {
    const btn = document.querySelector(`.filter-btn[data-filter="${filter}"]`);
    if (btn) {
      btn.classList.add('active');
    }
  });
}

function applyFilters() {
  const activeFilters = [];
  document.querySelectorAll('.filter-btn.active').forEach(btn => {
    activeFilters.push(btn.dataset.filter); // "active", "expired", "completed"
  });

  const cards = document.querySelectorAll('.challenge');


  if (activeFilters.length === 0) {
    cards.forEach(card => {
      card.style.display = '';
    });
    return;
  }

  cards.forEach(card => {
    const status = card.dataset.status; 
    if (activeFilters.includes(status)) {
      card.style.display = '';
    } else {
      card.style.display = 'none';
    }
  });
}
</script>
<script src="utils/tour-guide.js"></script>
<script src="utils/theme-toggle.js"></script>
</body>
</html>
