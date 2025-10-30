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

$challenges = $_SESSION['all_challenges'] ?? [];
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

function get_challenge_status(string $start, string $end, DateTimeImmutable $today): string {
    $s = new DateTimeImmutable($start);
    $e = new DateTimeImmutable($end);
    
    if ($today < $s) return 'Starting Soon';
    if ($today > $e) return 'Completed';
    return 'Active';
}

function get_duration_days(string $start, string $end): int {
    $s = new DateTimeImmutable($start);
    $e = new DateTimeImmutable($end);
    return max(1, (int)$e->diff($s)->days + 1);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <base href="/abide/">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>read | discover challenges</title>
  <meta name="author" content="Eyuel T">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles/theme.css">
  <link rel="stylesheet" href="styles/discover.css">
</head>
<body class="d-flex flex-column min-vh-100">

  <header>
    <nav class="navbar px-3 py-3 shadow-sm">
      <a href="index.php?action=dashboard" class="btn-container text-center p-2 ps-1">&larr; back to dashboard</a>
      <span class="navbar-text ms-auto fw-bold pe-4">read</span>
    </nav>
  </header>

  <main class="container-xxl my-4">
    <div class="row gx-4 gy-4">
      <aside class="col-12 col-lg-3">
        <div class="glass-pane p-4 d-flex flex-column">
          <div class="d-flex align-items-center mb-2">
            <h6 class="fw-semibold mb-0">Filters</h6>
            <button class="btn-outline-glass ms-auto px-2 py-1 small">Clear</button>
          </div>
          <hr class="my-3">

          <div class="filter-block">
            <p class="fw-semibold small mb-2">Duration</p>
            <label class="form-check small"><input class="form-check-input" type="checkbox"> Short (≤30 days)</label>
            <label class="form-check small"><input class="form-check-input" type="checkbox"> Medium (31–60 days)</label>
            <label class="form-check small"><input class="form-check-input" type="checkbox"> Long (60+ days)</label>
          </div>

          <div class="filter-block">
            <p class="fw-semibold small mb-2">Status</p>
            <label class="form-check small"><input class="form-check-input" type="checkbox"> Active</label>
            <label class="form-check small"><input class="form-check-input" type="checkbox"> Starting Soon</label>
            <label class="form-check small"><input class="form-check-input" type="checkbox"> Completed</label>
          </div>

          <div class="filter-block">
            <p class="fw-semibold small mb-2">Privacy</p>
            <label class="form-check small"><input class="form-check-input" type="checkbox"> Public</label>
            <label class="form-check small"><input class="form-check-input" type="checkbox"> Private</label>
          </div>

          <div class="filter-block">
            <p class="fw-semibold small mb-2">Difficulty</p>
            <label class="form-check small"><input class="form-check-input" type="checkbox"> Beginner</label>
            <label class="form-check small"><input class="form-check-input" type="checkbox"> Intermediate</label>
            <label class="form-check small"><input class="form-check-input" type="checkbox"> Advanced</label>
          </div>

          <div class="filter-block">
            <p class="fw-semibold small mb-2">Group Size</p>
            <select class="form-select form-select-sm" aria-label="Select group size">
              <option>Any size</option>
              <option>Solo</option>
              <option>Small (2–10)</option>
              <option>Medium (11–50)</option>
              <option>Large (51+)</option>
            </select>
          </div>
        </div>
      </aside>

      <div class="col-12 col-lg-9">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <p class="text-white small mb-0"><?= count($challenges) ?> challenges found</p>
          <select id="sort" class="form-select form-select-sm w-auto" aria-label="Sort challenges">
            <option>Best Match</option>
            <option>Newest</option>
            <option>Most Participants</option>
            <option>Duration (Short→Long)</option>
          </select>
        </div>

        <div class="results-grid">
          <?php foreach ($challenges as $ch): 
            $title = $ch['title'] ?? 'Untitled';
            $desc  = $ch['description'] ?? '';
            $end   = $ch['end_date'];
            $start = $ch['start_date'];
            $pct   = pct_progress($start, $end, $today);
            $day   = day_number($start, $end, $today);
            $duration = get_duration_days($start, $end);
            $status = get_challenge_status($start, $end, $today);
            $participants = (int)($ch['participants'] ?? 0);
            $cid = (int)$ch['challenge_id'];
            $creator_name = h($ch['creator_first_name'] ?? 'Unknown') . ' ' . h($ch['creator_last_name'] ?? '');
            $is_private = false;
          ?>
            <article class="challenge-card glass-pane p-4">
              <h5 class="fw-semibold mb-2"><?= h($title) ?></h5>
              
              <div class="badges mb-3">
                <?php if ($status === 'Active'): ?>
                  <span class="badge badge-status-active">Active</span>
                <?php elseif ($status === 'Completed'): ?>
                  <span class="badge badge-status-completed">Completed</span>
                <?php else: ?>
                  <span class="badge" style="background: rgba(255, 193, 7, 0.2); color: #856404;">Starting Soon</span>
                <?php endif; ?>
                
                <span class="badge"><?= $duration ?> days</span>
                <?php if ($is_private === true): ?>
                    <span class="badge badge-private">Private</span>
                  <?php else: ?>
                     <span class="badge badge-public">Public</span>
                <?php endif; ?>
              </div>

              <?php if ($desc !== ''): ?>
                <p class="small text-muted mb-3"><?= h(substr($desc, 0, 100)) ?><?= strlen($desc) > 100 ? '...' : '' ?></p>
              <?php endif; ?>

              <div class="meta small mb-3">
                <span>👤 <?= $creator_name ?></span> • <span>👥 <?= $participants ?></span>
              </div>

              <div class="progress-wrap">
                <div class="progress-bar" style="width:<?= $pct ?>%"></div>
              </div>

              <a href="index.php?action=challenge&cid=<?= $cid ?>" class="stretched-link" aria-label="View <?= h($title) ?>"></a>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </main>

  <footer class="footer-overlay text-center mt-auto">
    <p class="small mb-0">© 2025 Jason, Eyuel, Gianna - University of Virginia</p>
  </footer>

</body>
</html>