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

$user = $_SESSION['user'] ?? null;
if (!$user) {
  header('Location: /abide/index.php?action=welcome');
  exit;
}

if (empty($_SESSION['csrf'])) {
   $_SESSION['csrf'] = bin2hex(random_bytes(32)); 
}
$csrf = $_SESSION['csrf'];

$upcoming_readings = $_SESSION['upcoming_readings'] ?? [];

$upcomingByChallenge = [];

foreach ($upcoming_readings as $row) {
    $cid = $row['challenge_id'];

    if (!isset($upcomingByChallenge[$cid])) {
        $upcomingByChallenge[$cid] = [
            'challenge_id' => $cid,
            'challenge_title' => $row['challenge_title'],
            'readings' => []
        ];
    }

    $upcomingByChallenge[$cid]['readings'][] = [
        'reading_id'=> $row['reading_id'],
        'reading_title'=> $row['reading_title'],
        'reading_due_date'=> $row['reading_due_date'],
        'reading_start_page' => $row['reading_start_page'],
        'reading_end_page' => $row['reading_end_page'],
        'participant_id' => $row['participant_id'],
        'is_completed' => $row['is_completed'] ?? false,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <base href="/abide/">
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>read | Upcoming Readings</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="styles/theme.css" />
  <link rel="stylesheet" href="styles/upcoming.css" />
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

  <header>
    <nav class="navbar navbar-light bg-light px-3 py-3 shadow-sm">
      <a href="index.php?action=dashboard" class="btn-container text-center p-2 ps-1">&larr; back to dashboard</a>
      <span class="navbar-text ms-auto fw-bold pe-4">read</span>
    </nav>
  </header>

  <main class="container-xxl my-4">
    <div class="tile p-3 p-md-4 mb-4">
      <div class="d-flex align-items-center justify-content-between summary-row">
        <div>
          <h3 class="mb-0 fw-bold">Upcoming Readings</h3>
          <p class="text-muted small mb-0 mt-2">Stay ahead with your reading schedule!</p>
        </div>

        <div class="d-flex flex-column align-items-end">
          <div class="summary-chip">
            <div class="count"><?= count($upcoming_readings)?></div>
            <div class="label">Scheduled</div>
          </div>
        </div>
      </div>
    </div>

    <form method="post" id="upcomingForm" class="mt-3">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>" />
      <?php foreach ($upcomingByChallenge as $challenge):?>
        <div class="plan-section mb-4">
          <div class="section-header">
            <div class="title"><?= h($challenge['challenge_title']) ?></div>
            <span class="badge badge-status-upcoming"><?= count($challenge['readings']) ?> scheduled</span>
          </div>

          <?php foreach ($challenge['readings'] as $reading): 
            $due = new DateTime($reading['reading_due_date']);
            $now = new DateTime('today');

            $daysUntil = $now->diff($due)->days;
            $isFuture = $due > $now;
            $isToday = $due->format('Y-m-d') === $now->format('Y-m-d');

            $badge = null;
            $badge_text = '';

            if ($isToday) {
                $badge = 'due-today';      
                $badge_text = "Due Today";
            } elseif ($isFuture && $daysUntil <= 3) {
                $badge = 'due-soon';       
                $badge_text = "Due in {$daysUntil} days";
            }      
            ?>
           <article class="reading-card tile d-flex align-items-center <?= $reading['is_completed'] ? 'completed' : '' ?>">
              <div class="form-check me-3">
                <input
                  class="form-check-input upcoming-checkbox"
                  type="checkbox"
                  data-reading-id="<?= (int)$reading['reading_id'] ?>"
                  data-participant-id="<?= (int)$reading['participant_id'] ?>"
                  data-completed="<?= $reading['is_completed'] ? 'true' : 'false' ?>"
                  <?= $reading['is_completed'] ? 'checked' : '' ?>
                />
              </div>
              <label class="body w-100">
                <div class="title-row d-flex justify-content-between">
                  <h6 class="mb-0"><?= h($reading['reading_title']) ?></h6>
                  <?php if ($badge === 'due-today'): ?>
                    <span class="badge badge-due-today"><?= h($badge_text) ?></span>
                  <?php elseif ($badge === 'due-soon'): ?>
                    <span class="badge badge-due-soon"><?= h($badge_text) ?></span>
                  <?php endif; ?>
                </div>
                <p class="ref mb-0">Pages <?= $reading['reading_start_page']?> - <?= $reading['reading_end_page']?></p>
                <p class="meta mb-0">Due: <?= h($reading['reading_due_date']) ?></p>
              </label>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>

      <?php if (count($upcoming_readings) === 0): ?>
        <div class="tile p-4 mt-4 text-center">
          <h5 class="mb-1">You completed all scheduled readings!</h5>
          <p class="text-muted mb-0">Check back later or join a challenge!</p>
        </div>
      <?php endif; ?>
    </form>

  </main>

  <footer class="footer-overlay text-center mt-auto">
    <p class="text-black-50 small mb-0">© 2025 Jason, Eyuel, Gianna - University of Virginia</p>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
   function completeReadingFromCatchup(readingId, participantId, checkbox) {
      if (!participantId) {
        alert('You must be a participant to complete readings');
        checkbox.checked = false;
        return;
      }
      fetch('index.php?action=complete_reading', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `participant_id=${encodeURIComponent(participantId)}&reading_id=${encodeURIComponent(readingId)}`
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            location.reload();
          } else {
            alert(data.message || 'Error updating reading');
            checkbox.checked = false;
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Error updating reading');
          checkbox.checked = false;
        });
    }
    document.querySelectorAll('.upcoming-checkbox').forEach(cb => {
      cb.addEventListener('change', () => {
        if (!cb.checked) {
          cb.checked = true;
          return;
        }
        const readingId = cb.dataset.readingId;
        const participantId = cb.dataset.participantId;

        completeReadingFromCatchup(readingId, participantId, cb);
      });
    });

  </script>
  <script src="utils/theme-toggle.js"></script>
</body>
</html>