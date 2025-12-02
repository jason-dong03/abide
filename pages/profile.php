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
	<style>
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
	</style>
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
    <button class="nav-link active nav-link-skinny" id="tab-profile" data-bs-toggle="tab" data-bs-target="#pane-profile" type="button" role="tab" aria-controls="pane-profile" aria-selected="true">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
          stroke="var(--ink)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="7" r="4"></circle>
          <path d="M5.5 18a6.5 6.5 0 0 1 13 0"></path>
        </svg>
      <span class="d-none d-md-inline">Profile</span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link nav-link-skinny" id="tab-badges" data-bs-toggle="tab" data-bs-target="#pane-achievements" type="button" role="tab" aria-controls="pane-achievements" aria-selected="false">
      <svg
        width="18"
        height="18"
        viewBox="0 0 24 24"
        fill="none"
        stroke="var(--ink)"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
      >
        <path d="M8 21h8"></path>
        <path d="M12 14.5v4"></path>
        <path d="M7 4h10"></path>
        <path d="M17 4v5a5 5 0 0 1-10 0V4"></path>
        <path d="M4 4h3"></path>
        <path d="M17 4h3"></path>
    </svg>
      <span class="d-none d-md-inline">Achievements</span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link nav-link-skinny" id="tab-alerts" data-bs-toggle="tab" data-bs-target="#pane-alerts" type="button" role="tab" aria-controls="pane-alerts" aria-selected="false">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
        stroke="var(--ink)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M18 9a6 6 0 0 0-12 0c0 3-1 5-2 6h16c-1-1-2-3-2-6"></path>
        <path d="M13.73 19a2 2 0 0 1-3.46 0"></path>
      </svg>
      <span class="d-none d-md-inline">Alerts</span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link nav-link-skinny" id="tab-settings" data-bs-toggle="tab" data-bs-target="#pane-settings" type="button" role="tab" aria-controls="pane-settings" aria-selected="false">
     <svg
      width="18"
      height="18"
      viewBox="0 0 24 24"
      fill="none"
      xmlns="http://www.w3.org/2000/svg">
      <path
        d="M6.995,19.461a10.065,10.065,0,0,1-2.171-.9.756.756,0,0,1-.382-.7l.132-2.067a.151.151,0,0,0-.044-.116l-.707-.708a.149.149,0,0,0-.106-.043h-.01l-2.075.129-.047,0a.75.75,0,0,1-.654-.384,10.071,10.071,0,0,1-.9-2.176.755.755,0,0,1,.226-.766l1.559-1.376a.149.149,0,0,0,.05-.113V9.25a.151.151,0,0,0-.05-.113L.254,7.761a.754.754,0,0,1-.226-.766,10.115,10.115,0,0,1,.9-2.177.75.75,0,0,1,.654-.382h.047l2.075.129h.01a.153.153,0,0,0,.106-.044l.7-.7a.15.15,0,0,0,.043-.116L4.436,1.632a.754.754,0,0,1,.382-.7,10.115,10.115,0,0,1,2.177-.9.751.751,0,0,1,.766.226L9.137,1.813a.151.151,0,0,0,.113.05h.988a.149.149,0,0,0,.113-.05L11.728.254a.751.751,0,0,1,.766-.226,10.071,10.071,0,0,1,2.176.9.753.753,0,0,1,.383.7l-.129,2.075a.151.151,0,0,0,.043.116l.7.7a.155.155,0,0,0,.107.044h.009l2.075-.129H17.9a.752.752,0,0,1,.654.382,10.07,10.07,0,0,1,.9,2.177.753.753,0,0,1-.226.766L17.676,9.137a.152.152,0,0,0-.051.113v.988a.152.152,0,0,0,.051.113l1.559,1.376a.753.753,0,0,1,.226.766,10.026,10.026,0,0,1-.9,2.176.751.751,0,0,1-.654.384l-.047,0-2.075-.129h-.01a.149.149,0,0,0-.106.043l-.7.7a.154.154,0,0,0-.043.116l.129,2.075a.744.744,0,0,1-.383.7,10.011,10.011,0,0,1-2.171.9.746.746,0,0,1-.767-.226l-1.371-1.557a.149.149,0,0,0-.113-.051h-1a.152.152,0,0,0-.113.051L7.761,19.235a.751.751,0,0,1-.766.226ZM4.883,13.907l.708.707a1.649,1.649,0,0,1,.48,1.273l-.1,1.582a8.373,8.373,0,0,0,.988.409l1.055-1.194a1.652,1.652,0,0,1,1.238-.558h1a1.649,1.649,0,0,1,1.238.56l1.049,1.191a8.413,8.413,0,0,0,.989-.41l-.1-1.59a1.653,1.653,0,0,1,.481-1.27l.7-.7a1.664,1.664,0,0,1,1.167-.483l.1,0,1.59.1a8.376,8.376,0,0,0,.412-.994l-1.194-1.055a1.652,1.652,0,0,1-.558-1.238V9.25a1.652,1.652,0,0,1,.558-1.238l1.194-1.055a8.274,8.274,0,0,0-.412-.994l-1.59.1c-.033,0-.068,0-.1,0a1.642,1.642,0,0,1-1.169-.484l-.7-.7a1.65,1.65,0,0,1-.481-1.269l.1-1.59a8.748,8.748,0,0,0-.994-.413l-1.055,1.2a1.652,1.652,0,0,1-1.238.558H9.25a1.652,1.652,0,0,1-1.238-.558L6.958,1.61a8.8,8.8,0,0,0-.994.413l.1,1.59a1.65,1.65,0,0,1-.481,1.269l-.7.7a1.638,1.638,0,0,1-1.168.484c-.033,0-.067,0-.1,0l-1.59-.1a8.748,8.748,0,0,0-.413.994l1.2,1.055A1.652,1.652,0,0,1,3.363,9.25v.988a1.652,1.652,0,0,1-.558,1.238l-1.2,1.055a8.666,8.666,0,0,0,.413.994l1.59-.1.1,0A1.638,1.638,0,0,1,4.883,13.907Zm.106-4.168a4.75,4.75,0,1,1,4.75,4.75A4.756,4.756,0,0,1,4.989,9.739Zm1.5,0a3.25,3.25,0,1,0,3.25-3.25A3.254,3.254,0,0,0,6.489,9.739Z"
        transform="translate(2.261 0.8)"
        fill="var(--ink)"
      >
      </path>
    </svg>
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
           <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
              stroke="var(--ink)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="8" r="4"></circle>
              <path d="M5.5 21a6.5 6.5 0 0 1 13 0"></path>
            </svg>
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
            <svg width="18" height="18" viewBox="0 0 24 24"
              fill="none"
              stroke="var(--ink)"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round">
              <path d="M16.5 3.5L20.5 7.5L9 19H5V15L16.5 3.5Z"></path>
              <path d="M14 5.5L18.5 10"></path>
            </svg>
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
            <svg width="18" height="18" viewBox="0 0 24 24"
              fill="none"
              stroke="var(--ink)"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round">
              <path d="M16.5 3.5L20.5 7.5L9 19H5V15L16.5 3.5Z"></path>
              <path d="M14 5.5L18.5 10"></path>
            </svg>
					</span>
				</div>
			</div>
        </div>

        <div>
          <label class="form-label mb-1 label-brown fw-bold">
           <span class ="text-success fw-normal">@ </span>Email Address
          </label>
          <input type="email" class="form-control rounded-8" value="<?= h($user['email'])?> " readonly aria-label="User's email address">
          <small class="text-muted">Used for login and notifications</small>
        </div>

        <div>
          <label class="form-label mb-1 label-brown fw-bold">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
              stroke="var(--ink)"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round">
              <rect x="7" y="3" width="10" height="18" rx="2" ry="2"></rect>
              <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
          Phone Number
          </label>
          <div class="input-group">
            <input type="text" name="phone_number" class="form-control rounded-8" placeholder="Add phone number" value="<?= h($user['phone_number'] ?? '') ?>">
            <span class="input-group-text input-icon">
            <svg width="18" height="18" viewBox="0 0 24 24"
              fill="none"
              stroke="var(--ink)"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round">
              <path d="M16.5 3.5L20.5 7.5L9 19H5V15L16.5 3.5Z"></path>
              <path d="M14 5.5L18.5 10"></path>
            </svg>
            </span>
          </div>
          <small class="text-muted">Optional: For SMS notifications and account recovery</small>
        </div>   
      </div>
      </div>
      <div class="tab-pane fade" id="pane-achievements" role="tabpanel">
        <div class="container-sections ">
          <div class="d-flex justify-content-between align-items-center w-50 mx-auto mb-3 px-4">
						<!-- badges earned -->
						<div class="summary-chip">
							<div class="count">0</div>
							<div class="label">Earned</div>
						</div>

						<!-- in progress badges -->
						<div class="summary-chip">
							<div class="count">0</div>
							<div class="label">In Progress</div>
						</div>

						<!-- available badges  -->
						<div class="summary-chip">
							<div class="count">0</div>
							<div class="label">Total Available</div>
						</div>
          </div>

          <section class="section-box mb-3 w-75 mx-auto">
          <div class="section-header d-flex align-items-center gap-2 ps-2">
               <svg
                  width="18"
                  height="18"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="var(--ink)"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                >
                  <path d="M8 21h8"></path>
                  <path d="M12 14.5v4"></path>
                  <path d="M7 4h10"></path>
                  <path d="M17 4v5a5 5 0 0 1-10 0V4"></path>
                  <path d="M4 4h3"></path>
                  <path d="M17 4h3"></path>
              </svg>
              <h6 class="section-title mb-0">Earned Badges</h6>
          </div>
          <div class="section-body"></div>
          </section>


          <section class="section-box mb-3 w-75 mx-auto">
          <div class="section-header d-flex align-items-center gap-2 ps-2">
               <svg
                  width="18"
                  height="18"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="var(--ink)"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                >
                  <path d="M8 21h8"></path>
                  <path d="M12 14.5v4"></path>
                  <path d="M7 4h10"></path>
                  <path d="M17 4v5a5 5 0 0 1-10 0V4"></path>
                  <path d="M4 4h3"></path>
                  <path d="M17 4h3"></path>
              </svg>
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
              <svg
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              >         
              <path d="M4 5h16a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2h-7l-3 3v-3H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z"></path>
    
              </svg>

                  <h5 class="mb-0 small fw-bold">How would you like to recieve notifications?</h5>
              </div>
              <!-- Email switch -->
              <div class="section-box d-flex justify-content-between align-items-center w-100 mb-3">
                <div class="d-flex align-items-center gap-3">
                  <label class="form-check-label mb-0" for="notifEmail"><span class="email-at me-3">@</span>Email</label>
                </div>
                <div class="form-check form-switch m-0">
                  <input class="form-check-input" type="checkbox" id="notifEmail">
                </div>
              </div>

              <div class="section-box d-flex justify-content-between align-items-center w-100 mb-3">
                <div class="d-flex align-items-center gap-3">
                  <svg width="18" height="18" viewBox="0 0 24 24"
                    fill="none"
                    stroke="var(--ink)"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round">
                    <path d="M16.5 3.5L20.5 7.5L9 19H5V15L16.5 3.5Z"></path>
                    <path d="M14 5.5L18.5 10"></path>
                  </svg>
                  <label class="form-check-label mb-0" for="notifSMS">SMS</label>
                </div>
                <div class="form-check form-switch m-0">
                  <input class="form-check-input" type="checkbox" id="notifSMS" disabled>
                </div>
              </div>
          </div>
          <div class="section-box d-flex justify-content-between align-items-center w-100 mb-2">
            <div class="d-flex align-items-center gap-3">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                stroke="var(--ink)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 11.5a6 6 0 0 0-12 0c0 3-1 5-2 6h16c-1-1-2-3-2-6"></path>
                <path d="M13.73 20.5a2 2 0 0 1-3.46 0"></path>
              </svg>
              <label class="form-check-label mb-0" for="notifPush">Push</label>
            </div>
            <div class="form-check form-switch m-0">
              <input class="form-check-input" type="checkbox" id="notifPush" disabled>
            </div>
          </div>
          <hr class="my-4 break">
          <div class="d-flex flex-column gap-2 w-100 ps-1">
              <div>
                  <h6 class="fw-bold"><u>Notification Types</u></h6>
              </div> 
							<div class="d-flex flex-column gap-4">
								<!-- challenge completion reminders -->
								<div class="d-flex justify-content-between align-items-center w-100">
									<div class="d-flex flex-column align-items-start">
										<div class="d-flex flex-row align-items-start gap-3">
											<svg
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      >
                        <path d="M7 4L4.5 2"></path>
                        <path d="M17 4L19.5 2"></path>

                        <path d="M3 6L5 7.5"></path>
                        <path d="M21 6L19 7.5"></path>

                        <circle cx="12" cy="13" r="6"></circle>

                        <path d="M12 13V10"></path>
                        <path d="M12 13L15 14.5"></path>
                      </svg>

											<h6 class="bigger-text fw-bold">Challenge Completion Reminders</h6>
										</div>
										<p class="small-text mb-0">Reminders when you haven't completed daily challenges</p>
									</div>
									<div class="form-check form-switch m-0">
										<input class="form-check-input" type="checkbox" id="notifCompletionReminders">
									</div>
								</div>

								<!-- alerts for reading -->
								<div class="d-flex justify-content-between align-items-center w-100 ">
									<div class="d-flex flex-column align-items-start">
										<div class="d-flex flex-row align-items-start gap-3">
											<svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M12 7v5l3 2"></path>
                      </svg>
											<h6 class="bigger-text fw-bold">Late Reading Alerts</h6>
										</div>
										<p class="small-text mb-0">Get reminded when you miss your daily reading</p>
									</div>
									<div class="form-check form-switch m-0">
										<input class="form-check-input" type="checkbox" id="notifLateReading">
									</div>
								</div>

								<!-- frienz activity -->
								<div class="d-flex justify-content-between align-items-center w-100 mb-1">
									<div class="d-flex flex-column align-items-start">
										<div class="d-flex flex-row align-itmes-start gap-3">
											 <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="9" cy="8" r="3"></circle>
                        <circle cx="17" cy="9" r="3"></circle>
                        <path d="M4 20a5 5 0 0 1 10 0"></path>
                        <path d="M14 20a4 4 0 0 1 7 0"></path>
                      </svg>
											<h6 class="bigger-text fw-bold">Friend Activity Updates</h6>
										</div>
										<p class="small-text mb-0">Notifications when friends complete challenges</p>
									</div>
									<div class="form-check form-switch m-0">
										<input class="form-check-input" type="checkbox" id="notifFriendsActivity">
									</div>
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
                  </select>
              </div>
          </div>

          <hr class="my-4 break">

          <div class="d-flex flex-column gap-2 w-100 ps-2">
              <h6 class="fw-bold"><u>Navigation Preferences</u></h6>
							<div class="d-flex flex-column gap-4">
								<!-- Display streaks switch -->
								<div class="d-flex justify-content-between align-items-center w-100">
									<div class="d-flex flex-column align-items-start">
										<h6 class="bigger-text fw-bold">Show Reading Streak on Dashboard</h6>
										<p class="small-text mb-0">Display your current reading streak prominently</p>
									</div>
									<div class="form-check form-switch m-0">
										<input class="form-check-input" type="checkbox" id="prefShowStreak" checked>
									</div>
								</div>

								<!-- Compact Challenge View -->
								<div class="d-flex justify-content-between align-items-center w-100 mb-1">
									<div class="d-flex flex-column align-items-start">
										<h6 class="bigger-text fw-bold">Compact Challenge View</h6>
										<p class="small-text mb-0">Show challenges in a more condensed format</p>
									</div>
									<div class="form-check form-switch m-0">
										<input class="form-check-input" type="checkbox" id="prefCompactView">
									</div>
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
  <p class="small mb-0">© 2025 Jason, Eyuel, Gianna - University of Virginia</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
      $(function(){
        $(".summary-chip").hover(
          function(){ $(this).css("transform", "scale(1.03)") },
          function(){ $(this).css("transform", "scale(1)") }
        );
      });
</script>
<script src="utils/theme-toggle.js"></script>
</body>
</html>
