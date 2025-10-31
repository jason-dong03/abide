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

$error = $_SESSION['error'] ?? null;
unset($_SESSION['error']); 

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}



$challenges = $_SESSION['challenges'] ?? [];
$today = new DateTimeImmutable('today');

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
  <!-- Navbar -->
  <header>
    <nav class="navbar navbar-light bg-light bg-opacity-75 shadow-sm backdrop-blur">
      <div class="container-fluid px-5 py-2 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
          <span class="fw-bold pe-4">read</span>
        </div>
        <div class="d-flex align-items-center gap-4">
          <!-- Profile button with text on large screens -->
          <a href="index.php?action=profile" class="nav-icon-link d-flex align-items-center gap-2" aria-label="Profile">
            <img src="assets/icons/dark-user-circle.svg" width="24" height="24" alt="Profile Icon">
            <span class="d-none d-lg-inline">Profile</span>
          </a>
          <a href="index.php?action=logout" class="nav-icon-link d-flex align-items-center gap-1" aria-label="Logout">
            <img src="assets/icons/dark-logout.svg" alt="Logout Icon" width="20" height="20">
            <span>Logout</span>
          </a>
        </div>
      </div>
    </nav>
  </header>

  <!-- Main -->
  <main class="container my-4">

    <!-- Primary Actions -->
   <div class="row g-3 mb-4">
     <?php $isEmpty = empty($challenges); ?>
    <div class="col-md-8">
      <div class="card glass-primary clickable-card p-4 position-relative">
        <span class="fw-semibold mb-1"><?= $isEmpty ? 'Start reading something, '. h($user['name']) : 'Continue Reading, ' . h($user['name']) ?></span>
        <p class="mb-0 subtitle">View your scheduled readings and stay on track</p>
        <a href="today.html" class="stretched-link" aria-label="View your scheduled readings and check off progress" tabindex="0"></a>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card glass-danger clickable-card p-4 position-relative">
        <span class="catchup-label mb-1">Catch Up</span>
        <p class="catchup-desc mb-0 subtitle">
          You have <span class="emph">2 missed readings</span>
        </p>
        <a href="/abide/index.php?action=catchup" class="stretched-link" aria-label="Access and catch up with late readings" tabindex="0"></a>
      </div>
    </div>
  </div>


    <!-- KPI Strip -->
<div class="card glass-card kpi-strip mb-4">
    <div class="kpi">
      <img src="assets/icons/dark-clock.svg" width="18" height="18" alt="">
      <div class="kpi-meta">
        <span class="kpi-label">Active</span>
        <span class="kpi-value">3</span>
      </div>
    </div>

    <div class="kpi">
      <img src="assets/icons/dark-check.svg" width="18" height="18" alt="">
      <div class="kpi-meta">
        <span class="kpi-label">Completed</span>
        <span class="kpi-value">2</span>
      </div>
    </div>

    <div class="kpi">
      <img src="assets/icons/dark-calendar.svg" width="18" height="18" alt="">
      <div class="kpi-meta">
        <span class="kpi-label">Streaks</span>
        <span class="kpi-value">33 🔥</span>
      </div>
    </div>


    <div class="kpi">
      <img src="assets/icons/dark-friends.svg" width="18" height="18" alt="">
      <div class="kpi-meta">
        <span class="kpi-label">Friends</span>
        <span class="kpi-value">15</span>
      </div>
    </div>
  </div>


    <!-- challenges & freinds section -->
    <div class="row g-4 align-items-stretch equal-panels">
      <div class="row g-4 align-items-stretch equal-panels">
        <?php $isEmpty = empty($challenges); ?>
        <div class="col-lg-9">
          <div class="card glass-card p-4 <?= $isEmpty ? '' : 'h-100' ?>">
            <div class="d-flex align-items-center mb-3">
              <img src="assets/icons/dark-bookmark.svg" width="20" height="20" class="me-2" alt="">
              <span class="fw-semibold mb-0">Your Challenges</span>
              <div class="ms-auto d-flex gap-2">
                <a href="index.php?action=start_create_challenge" class="btn btn-create d-flex align-items-center gap-1">
                  <img src="assets/icons/plus.svg" width="16" height="16" alt=""> <span>Create</span>
                </a>
                <a href="index.php?action=discover_challenges" class="btn btn-discover">Discover</a>
              </div>
            </div>

            <?php if ($isEmpty): ?>
              <p class="text-muted small mb-0">No challenges yet.</p>
            <?php else: ?>
              <div class="challenge-list d-flex flex-column gap-3">
                <?php foreach ($challenges as $ch): 
                      $title = $ch['title'] ?? 'Untitled';
                      $desc  = $ch['description'] ?? '';
                      $end   = $ch['end_date'];
                      $start = $ch['start_date'];
                      $pct   = pct_progress($start, $end, $today);
                      $day   = day_number($start, $end, $today);
                      $endsPretty = date('M j, Y', strtotime($end));
                      $participants = (int)($ch['participants'] ?? 0);
                      $cid = (int)$ch['challenge_id'];
                ?>
                  <div class="card glass-card p-3 position-relative challenge">
                    <div class="d-flex justify-content-between mb-2">
                      <strong><?= h($title) ?></strong>
                      <span class="badge bg-brown text-center">Day <?= $day ?></span>
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

                    <a href="/abide/index.php?action=challenge&cid=<?= $cid ?>" class="stretched-link" aria-label="View challenge"></a>
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
            <img src="assets/icons/dark-friends.svg" width="20" height="20" class="me-2" alt="">
            <span class="fw-semibold mb-0">Friends</span>
            <button class="btn btn-link p-0 ms-auto" aria-label="Add Friend">
              <img src="assets/icons/plus-brown.svg" width="16" height="16" alt="">
            </button>
          </div>

          <input type="text" class="form-control form-control-sm mb-3" placeholder="Search…" aria-label="Search friends">

          <div class="friends-list d-flex flex-column gap-1">
            <div class="d-flex align-items-center gap-2 friend-item">
              <img src="assets/icons/dark-profile-circle-fill.svg" width="32" height="32" alt=""><span class="small">Alice Johnson</span>
            </div>
            <div class="d-flex align-items-center gap-2 friend-item">
              <img src="assets/icons/dark-profile-circle-fill.svg" width="32" height="32" alt=""><span class="small">John Doe</span>
            </div>
            <div class="d-flex align-items-center gap-2 friend-item">
              <img src="assets/icons/dark-profile-circle-fill.svg" width="32" height="32" alt=""><span class="small">Isaac Newton</span>
            </div>
          </div>

          <a class="btn btn-discover mt-auto w-100" role="button" href="today.html">Invite</a>
        </div>
      </aside>
    </div>

  </main>

  <footer class="text-center mt-auto py-3 small text-black">
    © 2025 Jason, Eyuel, Gianna – University of Virginia
  </footer>
</body>
</html>
