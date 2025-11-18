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

$challenge_id = (int)($_SESSION['cid'] ?? 0);
$user_id = $_SESSION['user']['user_id'];

if (!$user_id) {
    header('Location: index.php?action=dashboard');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="/abide/">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Challenge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/theme.css">
    <link rel="stylesheet" href="styles/edit_challenge.css">
</head>
<body>
    <div class="edit-container">
        <div class="edit-content">
            <a href="index.php?action=challenge&cid=<?php echo $challenge_id; ?>" class="back-button">
                ← back to dashboard
            </a>

            <div class="edit-card">
                <h1 class="page-title">✏️ Edit Challenge</h1>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Challenge Title</label>
                        <input 
                            type="text" 
                            name="title" 
                            class="form-input" 
                            value="<?php echo htmlspecialchars($challenge['title']); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea 
                            name="description" 
                            class="form-textarea"
                        ><?php echo htmlspecialchars($challenge['description']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">End Date</label>
                        <input 
                            type="date" 
                            name="end_date" 
                            class="form-input" 
                            value="<?php echo $challenge['end_date']; ?>"
                            required
                        >
                        <p class="info-text">
                            Start date: <?php echo date('M j, Y', strtotime($challenge['start_date'])); ?> (cannot be changed)
                        </p>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Challenge Settings</label>
                        <p class="info-text">
                            <strong>Frequency:</strong> <?php echo ucfirst($challenge['frequency']); ?><br>
                            <strong>Goal:</strong> <?php echo $challenge['target_amount']; ?> <?php echo $challenge['goal_unit']; ?><br>
                            <em>These settings cannot be changed after creation</em>
                        </p>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary-glass">
                            💾 Save Changes
                        </button>
                        <a href="challenge_detail.php?id=<?php echo $challenge_id; ?>" class="btn-secondary-glass" style="display: flex; align-items: center; justify-content: center; text-decoration: none;">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>