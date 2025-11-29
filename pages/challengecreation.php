<?php
  declare(strict_types=1);
  if (session_status() === PHP_SESSION_NONE) {
      session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure'   => isset($_SERVER['HTTPS']),
      ]);
  }
  function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }

  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
  }
  $csrf = $_SESSION['csrf'];

  $error = $_SESSION['error'] ?? null;
  unset($_SESSION['error']); 

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <base href="/abide/">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>read | create challenge</title>
  <meta name="author" content="Gianna M">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles/challengecreation.css">
</head>

<body>
  <header>
    <nav class="navbar navbar-light bg-light px-3 py-3 shadow-sm">
      <a href="index.php?action=dashboard" class="btn-container text-center p-2 ps-1">&larr; back to dashboard</a>
      <span class="navbar-text ms-auto fw-bold pe-4">read</span>
    </nav>
  </header>
  <?php if ($error): ?>
    <div class="alert alert-danger text-center w-50 mx-auto mb-0 mt-5">
      <?= h($error) ?>
    </div>
  <?php endif; ?>
  <div class="container my-4">
    <div class="reading-challenge-header text-center mb-5 my-5 g-4">
      <h2>Create Your Reading Challenge!</h2>
    </div>

    <form id="challenge-form" class="form-view" method="post" action="index.php?action=create_challenge">
      <div class="row g-5 align-items-start flex-wrap">
        <div class="tile col-md-8 col-12">
          <div class="mb-3 mt-4">
            <label for="title" class="form-label fw-semibold">Challenge Title</label>
            <input type="text" class="form-control" placeholder="Enter your challenge title" id="title" name="title"
              required>
          </div>


          <div class="mb-3">
            <label for="description" class="form-label fw-semibold">Description</label>
            <textarea id="description" name="description" class="form-control" rows="4"
              placeholder="Describe your challenge" required></textarea>
          </div>


          <div class="row g-3 mt-3">
            <div class="col-md-3">
              <label for="start-date" class="form-label fw-semibold">Start Date</label>
              <input type="date" id="start-date" name="start-date" class="form-control" required>
            </div>
            <div class="col-md-3">
              <label for="end-date" class="form-label fw-semibold">End Date</label>
              <input type="date" id="end-date" name="end-date" class="form-control" required>
            </div>
          </div>


          <div class="row g-3 mt-4 col-8">
            <div class="g-3 mb-2">
              <label for="checkin_deadlines" class="form-label fw-semibold">Set Challenge Check-ins</label><br>
              <label for="checkin_deadlines">How frequently will reading progress be checked?</label><br>
              <!-- deadline selection -->
              <select id="checkin_deadlines" name="timeframe" class="form-select w-50" aria-label="Select check-in interval" required>
                <option value="" disabled selected>Select</option>
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
              </select>
            </div>

            <div class="g-3 mb-4">
              <label for="goal_num" class="form-label mt-3">How many pages/chapters will your challenge
                cover?</label>
              <div class="d-flex row g-3">
                <div class="col-4 align-items-center">
                  <input id = "goal_num" type="number" id="goal_num" name="goal_num" class="form-control" min="1" max="10000"
                    placeholder="Enter number" required>
                </div>
                <div class="col-4">
                  <select id="goal_type" name="goal_type" class="form-select" aria-label="Select goal type" required>
                    <option value="" disabled selected>Select</option>
                    <option value="pages">Pages</option>
                    <option value="chapters">Chapters</option>
                  </select>
                </div>
              </div>
            </div>
          </div>

          <div class="form-check mt-4 mb-4">
            <input type="checkbox" id="private" name="private" class="form-check-input">
            <label for="private">Optional: Private Challenge (invite only)</label>
          </div>
        </div>

        <div class="col-md-4 col-12 mb-4">
          <div class="tile p-4 shadow-sm rounded text-start">
            <span class="fw-semibold d-block mb-2">Tips</span>
            <ul class="mb-0 ps-3">
              <li>Choose a memorable name for your challenge</li>
              <li>Start dates can be today or in the future</li>
              <li>Private challenges are only visible to invited members</li>
              <li>Create realistic target goals according to your schedule</li>
            </ul>
          </div>
        </div>
        <div class="g-3 align-items-center text-center mt-3">
          <button type="submit" class="btn btn-primary-glass px-4">Create Challenge</button>
        </div>
      </div>
    </form>
  </div>
  <footer class="footer-overlay text-center mt-auto">
    <p class="text-black-50 small mb-0">© 2025 Jason, Eyuel, Gianna - University of Virginia</p>
  </footer>
  <script>
  (() => {
    'use strict'
    const forms = document.querySelectorAll('form')

    Array.from(forms).forEach(form => {
      form.addEventListener('submit', event => {
        if (!form.checkValidity()) {
          event.preventDefault()
          event.stopPropagation()
        }
        form.classList.add('was-validated')
      }, false)
    })
  })()
</script>
<script src="utils/theme-toggle.js"></script>
</body>

</html>