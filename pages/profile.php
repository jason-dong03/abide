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

function split_name(string $full_name): array {
    $full_name = trim(preg_replace('/\s+/', ' ', $full_name));
    if ($full_name === '') {
        return ['', ''];
    }

    $parts = explode(' ', $full_name);

    if (count($parts) === 1) {
        return [$parts[0], ''];
    }

    $first = array_shift($parts);
    $last  = implode(' ', $parts);
    return [$first, $last];
}

$user = $_SESSION['user'] ?? null;
if (!$user) {
  header('Location: login.php');
  exit;
}

[$first, $last] = split_name($user['name'] ?? '');


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $phone      = trim($_POST['phone_number'] ?? '');

    if ($first_name === '' || $last_name === '') {
        $_SESSION['error'] = 'First and last name are required.';
        header("Location: index.php?action=profile");
        exit;
    }

    // Check phone uniqueness
    if ($phone !== '' && Db::phone_in_use($phone, (int)$user['user_id'])) {
        $_SESSION['error'] = 'That phone number is already in use.';
        header("Location: index.php?action=profile");
        exit;
    }

    $ok = Db::update_user_profile(
        (int)$user['user_id'],
        $first_name,
        $last_name,
        $phone !== '' ? $phone : null
    );

    if ($ok) {
        $_SESSION['user']['first_name'] = $first_name;
        $_SESSION['user']['last_name']  = $last_name;
        $_SESSION['user']['name']       = $first_name . ' ' . $last_name;
        $_SESSION['user']['phone_number'] = $phone === '' ? null : $phone;

        $_SESSION['profile_success'] = true;
        header("Location: index.php?action=dashboard");
        exit;
    }

    $_SESSION['error'] = 'Could not update your profile.';
    header("Location: index.php?action=profile");
    exit;
}

$error = $_SESSION['error'] ?? null;
$success = $_SESSION['success'] ?? null;
unset($_SESSION['error'], $_SESSION['success']);
?>


<!DOCTYPE html>
<html lang="en">
<head> 
  <base href="/abide/">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>read | profile</title>
  <meta name="author" content="Jason D">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles/theme.css">
  <link rel="stylesheet" href="styles/profile.css">
</head>
<body class="d-flex flex-column min-vh-100"> 

<header>
  <nav class="navbar px-3 py-3 shadow-sm">
    <a href="index.php?action=dashboard" class="btn-container text-center p-2 ps-1">&larr; back to dashboard</a>
    <span class="navbar-text ms-auto fw-bold pe-4">read</span>
  </nav>
</header>

<ul class="nav nav-tabs nav-fill w-75 glass-pane rounded-4 overflow-hidden mt-5 mx-auto" id="profileTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active nav-link-skinny text-black" id="tab-profile" data-bs-toggle="tab" data-bs-target="#pane-profile" type="button" role="tab" aria-controls="pane-profile" aria-selected="true">
      <img src="assets/icons/user.svg" alt="" width="18" height="18">
      <span class="d-none d-md-inline">Profile</span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link nav-link-skinny text-black" id="tab-badges" data-bs-toggle="tab" data-bs-target="#pane-achievements" type="button" role="tab" aria-controls="pane-achievements" aria-selected="false">
      <img src="assets/icons/badge.svg" alt="" width="18" height="18">
      <span class="d-none d-md-inline">Achievements</span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link nav-link-skinny text-black" id="tab-alerts" data-bs-toggle="tab" data-bs-target="#pane-alerts" type="button" role="tab" aria-controls="pane-alerts" aria-selected="false">
      <img src="assets/icons/alert.svg" alt="" width="18" height="18">
      <span class="d-none d-md-inline">Alerts</span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link nav-link-skinny text-black" id="tab-settings" data-bs-toggle="tab" data-bs-target="#pane-settings" type="button" role="tab" aria-controls="pane-settings" aria-selected="false">
      <img src="assets/icons/setting.svg" alt="" width="18" height="18">
      <span class="d-none d-md-inline">Settings</span>
    </button>
  </li>
</ul>

<form method="post" action="index.php?action=profile">
  <input type="hidden" name="action" value="save_profile">
  <div class="tab-content w-75 mx-auto glass-pane rounded-4 p-4 mt-3" id="profileTabsContent">
      <div class="tab-pane show active fade" id="pane-profile" role="tabpanel">
      <div class="d-flex flex-column align-items-start">
        <div class="d-flex align-items-center gap-2 mb-2">
          <img src="assets/icons/user.svg" alt="Profile Icon" width="18" height="18">
          <h5 class="mb-0 bigger-text">Profile Information</h5>
        </div>

        <div class="d-flex flex-row mb-4 gap-5 align-items-center w-100 mt-3">
          <div class="position-relative">
            <div class="rounded-circle d-flex align-items-center justify-content-center profile">J</div>
            <div class="position-absolute bottom-0 end-0 profile-badge">
              <a href="#"><img src="assets/icons/camera-white.svg" alt="Change" width="20" height="20"></a>
            </div>
          </div>

          <div>
            <h6 class="fw-bold mb-1 text-brown">Profile Photo</h6>
            <p class="mb-2 text-muted small">Upload a photo to personalize your profile</p>
            <button class="btn btn-secondary-glass rounded-3 px-3 py-1">Change Photo</button>
          </div>
        </div>
      </div>

      <hr class="my-4 break">
      <div class="d-flex flex-column justify-content-start gap-4 w-75 mx-auto">
        <div>
			<!-- First Name -->
			<div class="mb-4">
				<label class="form-label mb-1 label-brown fw-bold">First Name</label>
				<div class="input-group">
					<input type="text"
						name="first_name"
						class="form-control rounded-8"
						value="<?= h($first) ?>"
						required>
					<span class="input-group-text input-icon">
					<img src="assets/icons/dark-edit.svg" width="16" height="16">
					</span>
				</div>
			</div>

			<!-- Last Name -->
			<div>
				<label class="form-label mb-1 label-brown fw-bold">Last Name</label>
				<div class="input-group">
					<input type="text"
						name="last_name"
						class="form-control rounded-8"
						value="<?= h($last) ?>"
						required>
					<span class="input-group-text input-icon">
					<img src="assets/icons/dark-edit.svg" width="16" height="16">
					</span>
				</div>
			</div>
        </div>

        <div>
          <label class="form-label mb-1 label-brown fw-bold">
            <img src="assets/icons/dark-@.svg" alt="Email" width="16" height="16" class="me-1">Email Address
          </label>
          <input type="email" class="form-control rounded-8" value="<?= h($user['email'])?> " readonly aria-label="User's email address">
          <small class="text-muted">Used for login and notifications</small>
        </div>

        <div>
          <label class="form-label mb-1 label-brown fw-bold">
            <img src="assets/icons/dark-phone.svg" alt="Phone" width="16" height="16" class="me-1">Phone Number
          </label>
          <div class="input-group">
            <input type="text" name="phone_number" class="form-control rounded-8" placeholder="Add phone number" value="<?= h($user['phone_number'] ?? '') ?>">
            <span class="input-group-text input-icon">
              <img src="assets/icons/dark-edit.svg" alt="Edit" width="16" height="16">
            </span>
          </div>
          <small class="text-muted">Optional: For SMS notifications and account recovery</small>
        </div>   
      </div>
      </div>
      <div class="tab-pane fade" id="pane-achievements" role="tabpanel">
        <div class="container-sections">
          <div class="row g-3 mb-3">
          <div class="col-12 col-md-4">
              <div class="metric-container h-100 d-flex flex-column justify-content-center align-items-center">
              <div class="metric-number">4</div>
              <div class="metric-label">Earned</div>
              </div>
          </div>
          <div class="col-12 col-md-4">
              <div class="metric-container h-100 d-flex flex-column justify-content-center align-items-center">
              <div class="metric-number">5</div>
              <div class="metric-label">In Progress</div>
              </div>
          </div>
          <div class="col-12 col-md-4">
              <div class="metric-container h-100 d-flex flex-column justify-content-center align-items-center">
              <div class="metric-number">9</div>
              <div class="metric-label">Total Available</div>
              </div>
          </div>
          </div>


          <section class="section-box mb-3 w-75 mx-auto">
          <div class="section-header d-flex align-items-center gap-2 ps-2">
              <img src="assets/icons/badge.svg" width="18" height="18" alt="">
              <h6 class="section-title mb-0">Earned Badges</h6>
          </div>
          <div class="section-body"></div>
          </section>


          <section class="section-box mb-3 w-75 mx-auto">
          <div class="section-header d-flex align-items-center gap-2 ps-2">
              <img src="assets/icons/badge.svg" width="18" height="18" alt="">
              <h6 class="section-title mb-0">In Progress</h6>
          </div>
          <div class="section-body"></div>
          </section>
    </div>
      </div>
      <div class="tab-pane fade" id="pane-alerts" role="tabpanel">
          <div class="d-flex flex-column align-items-start">        
              <h6 class="mb-0">Notification Preferences</h6> 
              <div class="d-flex align-items-center gap-1 mb-2 mt-4">
                  <img src="assets/icons/msg.svg" alt="Notif Icon" width="18" height="18">
                  <h5 class="mb-0 small fw-bold">How would you like to recieve notifications?</h5>
              </div>
              <!-- Email switch -->
              <div class="section-box d-flex justify-content-between align-items-center w-100 mb-3">
                <div class="d-flex align-items-center gap-3">
                  <img src="assets/icons/email.svg" alt="Email Icon" width="18" height="18">
                  <label class="form-check-label mb-0" for="notifEmail">Email</label>
                </div>
                <div class="form-check form-switch m-0">
                  <input class="form-check-input" type="checkbox" id="notifEmail">
                </div>
              </div>

              <!-- SMS switch -->
              <div class="section-box d-flex justify-content-between align-items-center w-100 mb-3">
                <div class="d-flex align-items-center gap-3">
                  <img src="assets/icons/call.svg" alt="Phone Icon" width="18" height="18">
                  <label class="form-check-label mb-0" for="notifSMS">SMS</label>
                </div>
                <div class="form-check form-switch m-0">
                  <input class="form-check-input" type="checkbox" id="notifSMS" disabled>
                </div>
              </div>
          </div>
              <!-- Push Notifications switch -->
              <div class="section-box d-flex justify-content-between align-items-center w-100 mb-2">
                <div class="d-flex align-items-center gap-3">
                  <img src="assets/icons/alert-brown.svg" alt="Bell Icon" width="18" height="18">
                  <label class="form-check-label mb-0" for="notifPush">Push</label>
                </div>
                <div class="form-check form-switch m-0">
                  <input class="form-check-input" type="checkbox" id="notifPush">
                </div>
              </div>


          <hr class="my-4 break">

          <div class="d-flex flex-column gap-2 w-100 ps-1">
              <div>
                  <h6 class="fw-bold"> Notiifcation Types</h6>
              </div> 
              <!-- Challenge Completion Reminders -->
              <div class="d-flex justify-content-between align-items-center w-100">
                <div class="d-flex flex-column align-items-start">
                  <h6 class="bigger-text">Challenge Completion Reminders</h6>
                  <p class="small-text mb-0">Reminders when you haven't completed daily challenges</p>
                </div>
                <div class="form-check form-switch m-0">
                  <input class="form-check-input" type="checkbox" id="notifCompletionReminders">
                </div>
              </div>

              <!-- Late Reading Alerts -->
              <div class="d-flex justify-content-between align-items-center w-100 ">
                <div class="d-flex flex-column align-items-start">
                  <div class="d-flex flex-row align-items-start gap-3">
                    <img src="assets/icons/clock.svg" alt="Clock Icon" width="18" height="18">
                    <h6 class="bigger-text">Late Reading Alerts</h6>
                  </div>
                  <p class="small-text mb-0">Get reminded when you miss your daily reading</p>
                </div>
                <div class="form-check form-switch m-0">
                  <input class="form-check-input" type="checkbox" id="notifLateReading">
                </div>
              </div>

              <!-- Friends Activity -->
              <div class="d-flex justify-content-between align-items-center w-100 mb-1">
                <div class="d-flex flex-column align-items-start">
                  <h6 class="bigger-text">Friend Activity Updates</h6>
                  <p class="small-text mb-0">Notifications when friends complete challenges</p>
                </div>
                <div class="form-check form-switch m-0">
                  <input class="form-check-input" type="checkbox" id="notifFriendsActivity">
                </div>
              </div>

          </div>
      </div>
      <div class="tab-pane fade" id="pane-settings" role="tabpanel">
          <div class="d-flex flex-column align-items-start">
              <h6 class="mb-0">App Preferences</h6>  
              <div class="d-flex flex-column align-items-start gap-1 mb-2 mt-4">
                  <h5 class="mb-0 fw-bold bigger-text">Theme Preferences</h5>
                  <p class ="small-text mb-0">Choose how the app appears</p>
              </div>
              <!-- Theme dropdown -->
              <div class="mb-3 w-100">
                  <select class="form-select custom-select" id="themeSelect">
                      <option selected>Light Mode</option>
                      <option>Dark Mode</option>
                      <option>Gradient 1</option>
                      <option>Gradient 2</option>
                      <option>Gradient 3</option>
                  </select>
              </div>
          </div>

          <hr class="my-4 break">

          <div class="d-flex flex-column gap-2 w-100 ps-2">
              <h6 class="fw-bold"> Navigation Preferences</h6>
              <!-- Display streaks switch -->
              <div class="d-flex justify-content-between align-items-center w-100 ">
                <div class="d-flex flex-column align-items-start">
                  <h6 class="bigger-text">Show Reading Streak on Dashboard</h6>
                  <p class="small-text mb-0">Display your current reading streak prominently</p>
                </div>
                <div class="form-check form-switch m-0">
                  <input class="form-check-input" type="checkbox" id="prefShowStreak" checked>
                </div>
              </div>

              <!-- Compact Challenge View -->
              <div class="d-flex justify-content-between align-items-center w-100 mb-1">
                <div class="d-flex flex-column align-items-start">
                  <h6 class="bigger-text">Compact Challenge View</h6>
                  <p class="small-text mb-0">Show challenges in a more condensed format</p>
                </div>
                <div class="form-check form-switch m-0">
                  <input class="form-check-input" type="checkbox" id="prefCompactView">
                </div>
              </div>
          </div>
      </div>
  </div>

  <div class="d-flex justify-content-center w-75 mx-auto mb-4 mt-4 gap-2">
    <button type="submit" class="btn btn-save rounded-3 px-3 py-1">
      <img src="assets/icons/save.svg" alt="Save" width="16" height="16" class="me-1">Save Profile
    </button>
    <button type="button" class="btn btn-secondary-glass rounded-8 d-flex align-items-center text-white">Reset Password</button>
  </div>
</form>

<footer class="footer-overlay text-center mt-auto">
  <p class="text-white small mb-0">© 2025 Jason, Eyuel, Gianna - University of Virginia</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
