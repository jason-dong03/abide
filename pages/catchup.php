<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
  session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure'   => isset($_SERVER['HTTPS']),
  ]);
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// --- Auth gate ---
$user = $_SESSION['user'] ?? null;
if (!$user) {
  header('Location: /abide/index.php?action=welcome');
  exit;
}

// --- CSRF token ---
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf'];

// --- Demo data (static) ---
$plans = [
  [
    'title' => 'Warriors: Into The Wild',
    'items' => [
      [
        'title'      => 'Book One',
        'badge'      => 'overdue',
        'badge_text' => '3 days overdue',
        'ref'        => 'Pages 290 – 320',
        'meta'       => 'Originally due: January 10, 2025',
      ],
      [
        'title'      => 'Book Two',
        'badge'      => 'overdue',
        'badge_text' => '2 days overdue',
        'ref'        => 'Pages 1 – 60',
        'meta'       => 'Originally due: January 14, 2025',
      ],
    ],
  ],
  [
    'title' => 'Percy Jackson: The Lightning Thief',
    'items' => [
      [
        'title'      => 'Chapter 1: I Accidentally Vaporize My Pre-Algebra Teacher',
        'badge'      => 'due-soon',
        'badge_text' => '1 day overdue',
        'ref'        => 'Pages 28 – 45',
        'meta'       => 'Originally due: January 12, 2025',
      ],
    ],
  ],
];

// --- Session-backed set of completed item ids like "planIndex-itemIndex" ---
if (!isset($_SESSION['completed_readings']) || !is_array($_SESSION['completed_readings'])) {
  $_SESSION['completed_readings'] = [];
}
$completed = &$_SESSION['completed_readings'];

// --- Handle POST (PRG pattern so it updates immediately) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(400);
    exit('Invalid CSRF');
  }

  $action = $_POST['action'] ?? '';

  switch ($action) {
    case 'complete_selected': {
      $selected = $_POST['done'] ?? [];
      $count = 0;
      if (is_array($selected)) {
        foreach ($selected as $id) {
          $completed[$id] = true;
          $count++;
        }
      }
      $_SESSION['flash'] = [
        'type' => $count ? 'info' : 'secondary',
        'msg'  => $count ? "Marked {$count} reading(s) as complete." : "No readings selected."
      ];
      header('Location: ' . $_SERVER['REQUEST_URI']);
      exit;
    }
    case 'complete_all': {
      $count = 0;
      foreach ($plans as $pIdx => $plan) {
        foreach ($plan['items'] as $iIdx => $_) {
          $id = "{$pIdx}-{$iIdx}";
          if (empty($completed[$id])) {
            $completed[$id] = true;
            $count++;
          }
        }
      }
      $_SESSION['flash'] = [
        'type' => 'success',
        'msg'  => $count ? "YAY, you finished everything! 🎉" : "Already all caught up!"
      ];
      header('Location: ' . $_SERVER['REQUEST_URI']);
      exit;
    }
    case 'reset_demo': {
      $_SESSION['completed_readings'] = [];
      $_SESSION['flash'] = ['type' => 'secondary', 'msg' => 'Demo progress reset.'];
      header('Location: ' . $_SERVER['REQUEST_URI']);
      exit;
    }
  }
}

// --- Flash message (after PRG redirect) ---
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// --- Compute pending totals based on $completed ---
$pendingTotal = 0;
$remainingByPlan = [];
foreach ($plans as $pIdx => $plan) {
  $remain = 0;
  foreach ($plan['items'] as $iIdx => $_) {
    if (empty($completed["$pIdx-$iIdx"])) $remain++;
  }
  $remainingByPlan[$pIdx] = $remain;
  $pendingTotal += $remain;
}
$allDone = ($pendingTotal === 0);
if ($allDone && !$flash) {
  $flash = ['type' => 'success', 'msg' => 'YAY, you finished everything! 🎉'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>read | Catch-Up Readings</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="styles/theme.css" />
  <link rel="stylesheet" href="styles/catchup.css" />
  <style>
    /* Small local tweaks to align with your theme */
    .summary-row { gap: 1rem; }
    .summary-chip {
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      width: 110px; height: 110px; text-align: center;
      background: var(--glass-bg); border: 1px solid var(--glass-brd);
      border-radius: 16px; backdrop-filter: blur(14px) saturate(140%);
      -webkit-backdrop-filter: blur(14px) saturate(140%);
      box-shadow: 0 6px 18px rgba(0,0,0,.12), inset 0 1px rgba(255,255,255,.45);
    }
    .summary-chip .count { font-size: 1.6rem; font-weight: 800; line-height: 1; }
    .summary-chip .label { font-size: .8rem; color: var(--ink-muted); }

    .reset-holder { display:flex; justify-content:flex-end; }
    .btn-reset {
      border: 1px solid var(--glass-brd);
      background: rgba(255,255,255,.18);
      color: var(--ink);
      padding: .35rem .6rem; font-size: .8rem; border-radius: 8px;
    }
    .btn-reset:hover { background: rgba(255,255,255,.28); }

    .section-header {
      background: rgba(0,0,0,.12);               /* darker strip for contrast */
      border-color: rgba(255,255,255,.18);
      color: #0d1117;
    }
    .section-header .title { color:#0d1117; font-weight:600; }
  </style>
</head>
<body class="d-flex flex-column min-vh-100">

  <?php if (!empty($flash)): ?>
    <div class="position-fixed top-0 start-50 translate-middle-x mt-3 p-3" style="z-index:1080;">
      <div class="alert alert-<?= h($flash['type']) ?> alert-dismissible fade show shadow" role="alert" style="min-width:320px;max-width:640px;">
        <?= h($flash['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    </div>
  <?php endif; ?>

  <!-- Header -->
  <header>
    <nav class="navbar navbar-light bg-light px-3 py-3 shadow-sm">
      <a href="/abide/index.php?action=dashboard" class="btn-container text-center p-2 ps-1">&larr; back to dashboard</a>
      <span class="navbar-text ms-auto fw-bold pe-4">read</span>
    </nav>
  </header>

  <main class="container-xxl my-4">

    <!-- Summary -->
    <div class="tile p-3 p-md-4 mb-4">
      <div class="d-flex align-items-start justify-content-between summary-row">
        <div>
          <h3 class="mb-0 fw-bold">Catch-Up Readings</h3>
          <p class="text-muted small mb-0">Stay on track with your readings!</p>
        </div>

        <div class="d-flex flex-column align-items-end">
          <div class="summary-chip">
            <div class="count"><?= (int)$pendingTotal ?></div>
            <div class="label">Pending</div>
          </div>
          <form method="post" class="mt-2 reset-holder">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>" />
            <input type="hidden" name="action" value="reset_demo" />
            <button class="btn btn-reset">🔄 Reset Demo</button>
          </form>
        </div>
      </div>

      <!-- One aligned action bar with BOTH buttons -->
      <div class="action-bar mt-3 d-flex align-items-center gap-2">

        <!-- Submit the checkbox form from here -->
        <button type="submit"
                class="btn-primary-glass"
                form="selectForm"
                <?= $allDone ? 'disabled' : '' ?>>
          Mark as Complete
        </button>
      </div>
    </div>

    <!-- List with checkboxes (note id="selectForm") -->
    <form method="post" id="selectForm">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>" />
      <input type="hidden" name="action" value="complete_selected" />

      <?php foreach ($plans as $pIdx => $plan): ?>
        <?php if ($remainingByPlan[$pIdx] === 0) continue; ?>
        <div class="plan-section mb-4">
          <div class="section-header">
            <div class="title"><?= h($plan['title']) ?></div>
            <span class="badge badge-status-pending"><?= (int)$remainingByPlan[$pIdx] ?> pending</span>
          </div>

          <?php foreach ($plan['items'] as $iIdx => $item): ?>
            <?php if (!empty($completed["$pIdx-$iIdx"])) continue; ?>
            <article class="reading-card tile d-flex align-items-center">
              <div class="form-check me-3">
                <input class="form-check-input" type="checkbox" name="done[]" value="<?= $pIdx . '-' . $iIdx ?>" id="r-<?= $pIdx . '-' . $iIdx ?>" />
              </div>

              <label class="body w-100" for="r-<?= $pIdx . '-' . $iIdx ?>">
                <div class="title-row d-flex justify-content-between">
                  <h6 class="mb-0"><?= h($item['title']) ?></h6>
                  <?php if ($item['badge'] === 'overdue'): ?>
                    <span class="badge badge-overdue"><?= h($item['badge_text']) ?></span>
                  <?php elseif ($item['badge'] === 'due-soon'): ?>
                    <span class="badge badge-due-soon"><?= h($item['badge_text']) ?></span>
                  <?php endif; ?>
                </div>
                <p class="ref mb-0"><?= h($item['ref']) ?></p>
                <p class="meta mb-0"><?= h($item['meta']) ?></p>
              </label>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>

      <?php if ($allDone): ?>
        <div class="tile p-4 mt-4 text-center">
          <h5 class="mb-1">YAY, you finished everything! 🎉</h5>
          <p class="text-muted mb-0">Great job—no pending readings left.</p>
        </div>
      <?php endif; ?>
    </form>

  </main>

  <footer class="footer-overlay text-center mt-auto">
    <p class="text-black-50 small mb-0">© 2025 Jason, Eyuel, Gianna - University of Virginia</p>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Auto-dismiss any alert after 3 seconds
    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('.alert').forEach((node) => {
        const inst = bootstrap.Alert.getOrCreateInstance(node);
        setTimeout(() => inst.close(), 3000);
      });
    });
  </script>
</body>
</html>
