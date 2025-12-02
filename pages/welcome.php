<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' =>
true, 'cookie_samesite' => 'Lax', 'cookie_secure' => isset($_SERVER['HTTPS']),
]); } 

function h(string $s): string { 
  return htmlspecialchars($s, ENT_QUOTES |ENT_SUBSTITUTE, 'UTF-8'); 
} 
if (empty($_SESSION['csrf'])){ 
  $_SESSION['csrf'] =bin2hex(random_bytes(32)); 
} 
$csrf = $_SESSION['csrf']; 
$error =$_SESSION['error'] ?? null; 
unset($_SESSION['error']);

$view = $_SESSION['view'] ?? 'main';

$register_form = $_SESSION['register_form'] ?? [];
$login_form = $_SESSION['login_form']    ?? [];

unset($_SESSION['error'], $_SESSION['view'], $_SESSION['register_form'], $_SESSION['login_form']);

$reg_first = $register_form['first_name'] ?? '';
$reg_last = $register_form['last_name'] ?? '';
$reg_email = $register_form['email'] ?? '';
$reg_user = $register_form['username'] ?? '';

$login_email = $login_form['email']?? '';
?>

<html>
  <head>
    <base href="/abide/">
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta
      name="description"
      content="A reading website that helps you track your reading!"
    />
    <meta name="author" content="Jason D, Eyuel T, Gianna M" />
    <title>read — a simple reading tracker</title>

    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet"
      integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
      crossorigin="anonymous"
    />

    <link rel="stylesheet" href="styles/theme.css" />
    <link rel="stylesheet" href="styles/index.css" />
    <link rel="stylesheet" href="styles/typewriter.css" />
  </head>

  <body>
    <!-- error alert -->
    <?php if (!empty($error)): ?>
      <div class="position-fixed top-0 start-50 translate-middle-x mt-3 p-3" style="z-index:1080;">
        <div class="alert alert-danger alert-dismissible text-center fade show shadow" role="alert" style="min-width:320px;max-width:640px;">
          <?= h($error) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      </div>
    <?php endif; ?>
    <!-- main container -->
    <div id="main" class="outer-container">
      <div
        class="blur-container d-flex flex-column align-items-center justify-content-center text-center p-5"
      >
        <h1 class="display-4 fw-bold text-black mt-3">
          <span class="tw">
            <span class="tw__text">read</span>
          </span>
        </h1>
        <p class="small">a simple reading tracker</p>

        <a
          id="register-btn"
          role="button"
          tabindex="0"
          class="btn-container register p-2 w-50 mx-auto mt-3"
          >Register</a
        >

        <a
          id="login-btn"
          role="button"
          tabindex="0"
          class="btn-container p-2 w-50 mx-auto"
          >Login</a
        >
      </div>
    </div>


    <!-- registration -->
    <div id="register" class="d-none outer-container">
      <div class="blur-container p-4 d-flex flex-column h-100 w-75">
        <h2 class="outline-text mb-3">Create Account</h2>

        <!-- POSTS to index.php?action=register -->
        <form
          class="auth-form d-flex flex-column text-start gap-3"
          method="post"
          action="index.php?action=auth&mode=register"
          autocomplete="off"
          autocorrect="off"
          autocapitalize="off"
          spellcheck="false"
          novalidate
        >
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>" />
          <div class="name-grid"></div>

          <div class="reg-top">
            <div class="avatar-column">
              <div id="avatar-upload" class="avatar-uploader">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none"
                  stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="8" r="4"></circle>
                  <path d="M5.5 21a6.5 6.5 0 0 1 13 0"></path>
                </svg>
              </div>
              <input type="file" id="profile-pic" accept="image/*" hidden />
            </div>

            <div class="name-grid">
              <div>
                <label for="first_name">First Name</label>
                <input
                  type="text"
                  id="first_name"
                  name="first_name"  
                  placeholder="First name"
                  value="<?= h($reg_first) ?>"
                  required
                />
              </div>
              <div>
                <label for="last_name">Last Name</label>
                <input
                  type="text"
                  id="last_name"
                  name="last_name"  
                  placeholder="Last name"
                  value="<?= h($reg_last) ?>"
                  required
                />
              </div>
            </div>
          </div>

          <div class="row g-2">
            <div class="col-12 col-md-6">
              <label for="email">Email</label>
              <input
                type="email"
                id="email"
                name="email"
                placeholder="you@example.com"
                value="<?= h($reg_email) ?>"
                required
                autocomplete="off"
              />
            </div>
            <div class="col-12 col-md-6">
              <label for="username">Username</label>
              <input type="text" id="username" name ="username" placeholder="username" value="<?= h($reg_user) ?>"/>
            </div>
          </div>

          <div class="row g-2 mb-1">
            <div class="col-12 col-md-6">
              <label for="password">Password</label>
              <input
                type="password"
                id="password"
                name="password"
                placeholder="Create a password"
                required
                minlength="8"
                autocomplete="new-password"
              />
              <div class="password-rules mt-2">
                <p class="fw-semibold mb-1">Your password must:</p>
                <ul class="rules-list list-unstyled">
                  <li id="rule-length" class="invalid">be at least 8 characters long</li>
                  <li id="rule-number" class="invalid">include at least one number (0–9), one lowercase (a-z), one uppercase (A-Z)</li>
                  <li id="rule-special" class="invalid">include at least one special character (!@#$%^&*)</li>
                </ul>
              </div>
            </div>

            <div class="col-12 col-md-6">
              <label for="password-repeat">Repeat Password</label>
              <input
                type="password"
                id="password-repeat"
                name="password_confirm"
                placeholder="Repeat password"
                required
              />
              <div class="password-rules mt-2">
                <ul class="rules-list list-unstyled">
                  <li id="rule-match" class="invalid">passwords match</li>
                </ul>
              </div>
            </div>

          </div>

          <div class="auth-actions d-flex flex-column w-50 mx-auto gap-3 mt-3">
            <button type="submit" class="btn-primary-glass">Submit</button>
            <button type="button" id="back-btn-reg" class="btn-secondary-glass">
              Back
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- login -->
    <div id="login" class="d-none outer-container">
      <div class="blur-container p-4 d-flex flex-column h-100 w-50">
        <h2 class="outline-text mb-3">Login</h2>

        <form
          class="auth-form d-flex flex-column text-start gap-3"
          method="post"
          action="index.php?action=auth&mode=login"
          autocomplete="off"
          autocorrect="off"
          autocapitalize="off"
          spellcheck="false"
          novalidate
        >
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>" />
          <input type="hidden" name="name" value="" />

          <div>
            <label for="login-email">Email</label>
            <input
              type="email"
              id="login-email"
              name="email"
              placeholder="you@example.com"
              value="<?= h($login_email) ?>"
              required
              autocomplete="email"
            />
          </div>

          <div>
            <label for="login-pwd">Password</label>
            <input
              type="password"
              id="login-pwd"
              name="password"
              placeholder="password"
              required
              minlength="8"
              autocomplete="current-password"
            />
          </div>

          <div class="auth-actions d-flex flex-column w-50 mx-auto gap-3 mt-3">
            <button type="submit" class="btn-primary-glass">Login</button>
            <button
              type="button"
              id="back-btn-login"
              class="btn-secondary-glass"
            >
              Back
            </button>
            <p class="small text-center mt-2 mb-0">
              Forgot password? click <a href="index.php?action=forgot">here</a>
            </p>
          </div>
        </form>
      </div>
    </div>

    <footer class="footer-overlay text-center">
      <p class="text-black-50 small mb-0">
        © 2025 Jason, Eyuel, Gianna — University of Virginia
      </p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
      const avatar = document.getElementById("avatar-upload");
      const fileInput = document.getElementById("profile-pic");
      const icon = document.getElementById("avatar-icon");

      avatar?.addEventListener("click", () => fileInput.click());
      fileInput?.addEventListener("change", (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = (event) => {
          avatar.style.backgroundImage = `url(${event.target.result})`;
          avatar.style.backgroundSize = "cover";
          avatar.style.backgroundPosition = "center";
          icon.style.display = "none";
        };
        reader.readAsDataURL(file);
      });

      const show = (id) => document.getElementById(id)?.classList.remove("d-none");
      const hide = (id) => document.getElementById(id)?.classList.add("d-none");

      const registerBtn = document.getElementById("register-btn");
      const loginBtn    = document.getElementById("login-btn");
      const backReg     = document.getElementById("back-btn-reg");
      const backLogin   = document.getElementById("back-btn-login");

      registerBtn?.addEventListener("click", () => {
        hide("main");
        show("register");
        hide("login");
        setTimeout(() => document.getElementById("first_name")?.focus(), 0);
      });

      loginBtn?.addEventListener("click", () => {
        hide("main");
        hide("register");
        show("login");
        setTimeout(() => document.getElementById("login-email")?.focus(), 0);
      });

      backReg?.addEventListener("click", () => {
        show("main");
        hide("register");
        hide("login");
      });

      backLogin?.addEventListener("click", () => {
        show("main");
        hide("register");
        hide("login");
      });

      window.addEventListener("DOMContentLoaded", () => {
        const initialView = "<?= h($view) ?>"; // 'main' | 'register' | 'login'

        if (initialView === "register") {
          hide("main");
          show("register");
          hide("login");
          document.getElementById("first_name")?.focus();
        } else if (initialView === "login") {
          hide("main");
          hide("register");
          show("login");
          document.getElementById("login-email")?.focus();
        } else {
          show("main");
          hide("register");
          hide("login");
        }

        // auto-dismiss bootstrap alert after 5s
        const alertNode = document.querySelector(".alert");
        if (alertNode && window.bootstrap?.Alert) {
          const alert = bootstrap.Alert.getOrCreateInstance(alertNode);
          setTimeout(() => alert.close(), 5000);
        }
      });
    </script>
    <script>
      const passwordInput  = document.getElementById('password');
      const passwordRepeat = document.getElementById('password-repeat');

      const rules = {
        length:  document.getElementById('rule-length'),
        number:  document.getElementById('rule-number'),
        special: document.getElementById('rule-special'),
        match:   document.getElementById('rule-match'),
      };

      function updatePasswordRules() {
        if (!passwordInput || !passwordRepeat) return;

        const pass    = passwordInput.value;
        const confirm = passwordRepeat.value;

        const checks = {
          length:  pass.length >= 8,
          // at least 1 digit, 1 lowercase letter, 1 uppercase letter
          number:
            /\d/.test(pass) &&        // has digit
            /[a-z]/.test(pass) &&     // has lowercase
            /[A-Z]/.test(pass),       // has uppercase
          special: /[!@#$%^&*]/.test(pass),
          match:   pass !== '' && pass === confirm,
        };

        for (const key in rules) {
          const el = rules[key];
          if (!el) continue;

          el.classList.toggle('valid', checks[key]);
          el.classList.toggle('invalid', !checks[key]);
        }
      }
      passwordInput?.addEventListener('input', updatePasswordRules);
      passwordRepeat?.addEventListener('input', updatePasswordRules);
    </script>
    <script>
      $(document).ready(function () {
        $(".alert").delay(2500).fadeOut(600);
      });
    </script>
    <script src="utils/theme-toggle.js"></script>
  </body>
</html>
