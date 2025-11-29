<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure'   => isset($_SERVER['HTTPS']),
    ]);
}

$error = $_SESSION['error'] ?? null;
unset($_SESSION['error']);

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$user = $_SESSION['user']?? null;
$challenge_id = $_SESSION['cid']?? null;
$user_id = $user['user_id']?? null;
$challenge = $_SESSION['challenge']?? null;
$participant_id = $_SESSION['pid']?? null;
$participants = $_SESSION['participants'] ?? [];
$readings = $_SESSION['readings']?? [];

if (!$challenge_id || !$user_id) {
    header('Location: index.php?action=dashboard');
    exit;
}

if (!$challenge) {
    header('Location: index.php?action=dashboard');
    exit;
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

$is_owner= !empty($challenge['is_owner']);
$is_participant = !empty($participant_id);

$total_readings= count($readings);
$completed_readings= array_filter($readings, fn($r) => !empty($r['is_completed']));
$completed_count= count($completed_readings);
$progress= $total_readings > 0 ? round(($completed_count / $total_readings) * 100) : 0;

$end_date = new DateTime($challenge['end_date']);
$today = new DateTime();
$days_left = $today->diff($end_date)->days;
if ($end_date < $today) {
    $days_left = 0;
}

function isToday($date): bool {
    return date('Y-m-d', strtotime($date)) === date('Y-m-d');
}

function isPast($date): bool {
    return strtotime($date) < strtotime('today');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="/abide/">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($challenge['title']) ?> - details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/theme.css">
    <link rel="stylesheet" href="styles/challenge.css">
</head>
<body>
    <div class="challenge-detail-container">
        <div class="challenge-content">
            <a href="index.php?action=dashboard" class="back-button">← back to dashboard</a>
            <div class="challenge-header">
                <div class="header-top">
                    <div>
                        <h1 class="challenge-title"><?= h($challenge['title']) ?> </h1>
                        <p class="challenge-description"><?= h($challenge['description']) ?></p>
                        <p class="creator-info">
                            Created by <strong><?= h($challenge['creator_name']) ?></strong>
                        </p>
                    </div>
                    
                    <?php if ($is_owner): ?>
                        <!-- owner view -->
                        <div class="header-actions">
                            <button onclick="handleCompleteChallenge(event)" class ="complete-btn">
                                Complete
                            </button>
                            <button onclick="showEditChallenge()" class="btn-primary-glass">
                                Edit
                            </button>
                            <button onclick="showDeleteConfirm()" class ="delete-btn">
                                Delete
                            </button>                   
                        </div>
                    <?php elseif ($is_participant): ?>
                        <!-- partipciant view -->
                        <button onclick="showLeaveConfirm()" style="background: rgba(255, 193, 7, 0.85); color: #1e1e1e; padding: 0.55rem 1.1rem; border: none; border-radius: 10px; cursor: pointer; font-weight: 500;">
                            Leave Challenge
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- stats table -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Days Left</div>
                    <div class="stat-value"><?= $days_left ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">Participants</div>
                    <div class="stat-value"><?= count($participants) ?></div>
                    <div class="participant-avatars">
                        <?php 
                        $display_limit = 5;
                        for ($i = 0; $i < min($display_limit, count($participants)); $i++):
                        ?>
                            <div class="avatar">👤</div>
                        <?php endfor; ?>
                        <?php if (count($participants) > $display_limit): ?>
                            <div class="avatar avatar-more">+<?= count($participants) - $display_limit ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- progress -->
                <div class="stat-card">
                    <div class="stat-label">Your Progress</div>
                    <div class="stat-value"><?= $progress ?>%</div>
                    <div class="stat-subtext"><?= $completed_count ?> / <?= $total_readings ?> readings</div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?= $progress ?>%"></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Goal</div>
                    <div class="stat-value" style="font-size: 1.5rem;">
                        <?= $challenge['target_amount'] ?> <?= h($challenge['goal_unit']) ?>
                    </div>
                    <div class="stat-subtext">
                        Check-in: <strong><?= h($challenge['frequency']) ?></strong>
                    </div>
                </div>
            </div>
            <div class="readings-section">
                <div class="section-header">
                    <h2 class="section-title">Reading Schedule</h2>
                    <?php if ($is_owner): ?>
                        <button onclick="showAddReading()" class="btn-primary-glass">
                            Add Reading
                        </button>
                    <?php endif; ?>
                </div>

                <?php if (empty($readings)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📖</div>
                        <p>No readings scheduled yet</p>
                        <?php if ($is_owner): ?>
                            <p>Add your first reading to get started!</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="reading-list">
                        <?php foreach ($readings as $reading): 
                            $is_today = isToday($reading['due_date']);
                            $is_past = isPast($reading['due_date']);
                            $is_completed = !empty($reading['is_completed']);
                        ?>
                           <div class="reading-item <?= $is_completed ? 'completed' : '' ?>" 
                                data-reading-id="<?= (int)$reading['reading_id'] ?>"
                                data-title="<?= h($reading['title']) ?>"
                                data-description="<?= h($reading['description']) ?>"
                                data-due-date="<?= h($reading['due_date']) ?>"
                                data-start-page="<?= (int)($reading['start_page'] ?? 0) ?>"
                                data-end-page="<?= (int)($reading['end_page'] ?? 0) ?>"
                            >
                                <div class="reading-content">
                                    <div class="reading-checkbox <?= $is_completed ? 'checked' : '' ?>"
                                         onclick="toggleReading(<?= (int)$reading['reading_id'] ?>, <?= $is_completed ? 'true' : 'false' ?>)">
                                        <?php if ($is_completed): ?>✓<?php endif; ?>
                                    </div>

                                    <div class="reading-info">
                                        <div class="reading-title"><?= h($reading['title']) ?></div>
                                        <div class="reading-description"><?= h($reading['description']) ?></div>
                                        <div class="reading-due <?= $is_past && !$is_completed ? 'overdue' : '' ?>">
                                            Due: <?= date('M j, Y', strtotime($reading['due_date'])) ?>
                                            <?php if ($is_today): ?>
                                                <span class="today-badge">• Today</span>
                                            <?php endif; ?>
                                            <?php if ($is_past && !$is_completed): ?>
                                                <span class="overdue-badge">• Overdue</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if ($is_owner): ?>
                                        <button class="btn-primary-glass" type="button" onclick="openEditReading(this)">
                                            Edit
                                        </button>
                                        <button class="delete-btn" onclick="deleteReading(<?= (int)$reading['reading_id'] ?>)">
                                            Remove
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="participants-section">
                <h2 class="section-title mb-4">👥 Participants (<?= count($participants) ?>)</h2>
                <div class="participants-grid">
                    <?php foreach ($participants as $participant): ?>
                        <div class="participant-card">
                            <div class="participant-avatar">👤</div>
                            <div class="participant-name">
                                <?= h($participant['first_name'] . ' ' . $participant['last_name']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="addReadingModal" class="modal-overlay">
        <div class="modal-content">
            <h2 class="modal-title">Add New Reading</h2>
            <form id="addReadingForm" onsubmit="handleAddReading(event)">
                <div class="form-group">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-input" placeholder="e.g., Chapter 1: Into the Wild" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-input" placeholder="e.g., Introduction and setup">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Start Page</label>
                        <input type="number" name="start_page" class="form-input" placeholder="1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Page</label>
                        <input type="number" name="end_page" class="form-input" placeholder="30">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Due Date *</label>
                    <input type="date" name="due_date" class="form-input" required>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="closeModal('addReadingModal')" class="btn-secondary-glass">Cancel</button>
                    <button type="submit" class="btn-primary-glass">Add Reading</button>
                </div>
            </form>
        </div>
    </div>
    <div id="editReadingModal" class="modal-overlay">
        <div class="modal-content">
            <h2 class="modal-title">Edit Reading:</h2>
            <form id="editReadingForm" onsubmit="handleEditReading(event)">
                <input type="hidden" name="reading_id" id="edit-reading-id">

                <div class="form-group">
                    <label class="form-label">Title</label>
                    <input type="text" name="edit_title" id="edit-title" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <input type="text" name="edit_description" id="edit-description" class="form-input">
                </div>
              
                <div class="modal-actions">
                    <button type="button" onclick="closeModal('editReadingModal')" class="btn-secondary-glass">Cancel</button>
                    <button type="submit" class="btn-primary-glass">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    <div id="editChallengeModal" class="modal-overlay">
        <div class="modal-content">
            <h2 class="modal-title">Edit Challenge</h2>
            <form id="editChallengeForm" onsubmit="handleEditChallenge(event)">
            <div class="form-group">
                <label class="form-label">Title</label>
                <input
                type="text"
                name="ch_title"
                id="ch_title"
                class="form-input"
                required
                value="<?= h($challenge['title'] ?? '') ?>"
                >
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <input
                type="text"
                name="ch_description"
                id="ch_description"
                class="form-input"
                value="<?= h($challenge['description'] ?? '') ?>"
                >
            </div>

            <div class="form-group">
                <label class="form-label">End Date</label>
                <input
                type="date"
                name="ch_end_date"
                id="ch_end_date"
                class="form-input"
                required
                value="<?= h($challenge['end_date'] ?? '') ?>"
                >
            </div>

            <div class="form-row">
                <div class="form-group">
                <label class="form-label">Frequency</label>
                <select name="ch_frequency" id="ch_frequency" class="form-input" required>
                    <option value="daily"   <?= ($challenge['frequency'] ?? '') === 'daily'   ? 'selected' : '' ?>>Daily</option>
                    <option value="weekly"  <?= ($challenge['frequency'] ?? '') === 'weekly'  ? 'selected' : '' ?>>Weekly</option>
                    <option value="monthly" <?= ($challenge['frequency'] ?? '') === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                </select>
                </div>

                <div class="form-group">
                <label class="form-label">Goal</label>
                <div style="display:flex; gap:.5rem;">
                    <input
                    type="number"
                    name="ch_target_amount"
                    id="ch_target_amount"
                    class="form-input"
                    min="1"
                    required
                    value="<?= (int)($challenge['target_amount'] ?? 0) ?>"
                    style="max-width: 100px;"
                    >
                    <select name="ch_goal_unit" id="ch_goal_unit" class="form-input" required>
                    <option value="pages"<?= ($challenge['goal_unit'] ?? '') === 'pages'    ? 'selected' : '' ?>>pages</option>
                    <option value="chapters" <?= ($challenge['goal_unit'] ?? '') === 'chapters' ? 'selected' : '' ?>>chapters</option>
                    </select>
                </div>
                </div>
            </div>

            <div class="modal-actions">
                <button
                type="button"
                onclick="closeModal('editChallengeModal')"
                class="btn-secondary-glass"
                >
                Cancel
                </button>
                <button type="submit" class="btn-primary-glass">
                Save Changes
                </button>
            </div>
            </form>
        </div>
        </div>

    <div id="deleteModal" class="modal-overlay">
        <div class="modal-content">
            <h2 class="modal-title">Delete Challenge?</h2>
            <p style="color: var(--ink-muted); margin-bottom: 1.5rem;">
                Are you sure you want to delete this challenge? This action cannot be undone.
            </p>
            <div class="modal-actions">
                <button onclick="closeModal('deleteModal')" class="btn-secondary-glass">Cancel</button>
                <button onclick="handleDeleteChallenge()" style="background: #dc3545; color: white; flex: 1; padding: 0.75rem; border: none; border-radius: 10px; cursor: pointer; font-weight: 500; font-size: 1rem;">Delete</button>
            </div>
        </div>
    </div>

    <div id="leaveModal" class="modal-overlay">
        <div class="modal-content">
            <h2 class="modal-title">Leave Challenge?</h2>
            <p style="color: var(--ink-muted); margin-bottom: 1.5rem;">
                Are you sure you want to leave this challenge? Your progress will be lost.
            </p>
            <div class="modal-actions">               
                <button onclick="closeModal('leaveModal')" class="btn-secondary-glass">Cancel</button>
                <button onclick="handleLeaveChallenge()" style="background: #ff9800; color: white; flex: 1; padding: 0.75rem; border: none; border-radius: 10px; cursor: pointer; font-weight: 500; font-size: 1rem;">Leave</button>
            </div>
        </div>
    </div>
    <script>
        const challengeId = <?= (int)$challenge_id ?>;
        const participantId = <?= $participant_id ? (int)$participant_id : 'null' ?>;

        const userId = <?= (int)$user_id ?>;

        //console.log(`uid : ${userId}, pid: ${participantId}, cid: ${challengeId}`);
        function toggleReading(readingId, isCompleted) {
            if (!participantId) {
                alert('You must be a participant to complete readings');
                return;
            }
            const action = isCompleted ? 'uncomplete' : 'complete';
            
            fetch(`index.php?action=${action}_reading`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `participant_id=${participantId}&reading_id=${readingId}`
            })
            /*.then(response => response.text())
            .then(txt => {
                console.log('RAW RESPONSE:', txt);
            })*/
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log("successfully added!");
                    location.reload();
                } else {
                    alert(data.message || 'Error updating reading');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating reading');
            });
        }

        function showAddReading() {
            document.getElementById('addReadingModal').classList.add('active');
        }

        function showEditReading() {
            console.log("edit clicked");
            document.getElementById('editReadingModal').classList.add('active');
        }

        function showDeleteConfirm() {
            document.getElementById('deleteModal').classList.add('active');
        }

        function showLeaveConfirm() {
            document.getElementById('leaveModal').classList.add('active');
        }

       function openEditReading(button) {
            const item = button.closest('.reading-item');
            if (!item) return;

            const id = item.dataset.readingId;
            const title = item.dataset.title || '';
            const description = item.dataset.description || '';
        
            document.getElementById('editReadingModal').classList.add('active');

            document.getElementById('edit-reading-id').value = id;
            document.getElementById('edit-title').value = title;
            document.getElementById('edit-description').value = description;           
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function handleCompleteChallenge(event){
            event.preventDefault();
            if (!confirm('Are you sure you finished this challenge?')) {
                return;
            }
            fetch(`index.php?action=complete_challenge`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `cid=${challengeId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = "index.php?action=dashboard";
                } else {
                    alert(data.message || "Error completing challenge");
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
        function handleAddReading(event) {
            event.preventDefault();

            const formData = new FormData(event.target);
            formData.append('challenge_id', challengeId);

            fetch('index.php?action=add_reading', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Error adding reading');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error adding reading');
            });
        }
        function handleEditReading(event) {
            event.preventDefault();

            const formData = new FormData(event.target);
            formData.append('challenge_id', challengeId);

            fetch('index.php?action=edit_reading', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Error editing reading');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error adding reading');
            });
        }

        function deleteReading(readingId) {
            if (!confirm('Are you sure you want to delete this reading?')) {
                return;
            }

            fetch(`index.php?action=delete_reading`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `reading_id=${readingId}&cid=${challengeId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || "Error deleting reading");
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting reading');
            });
        }

        function handleDeleteChallenge() {
            fetch(`index.php?action=delete_challenge&challenge_id=${challengeId}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'index.php?action=dashboard';
                } else {
                    alert(data.message || 'Error deleting challenge');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting challenge');
            });
        }

        function handleLeaveChallenge() {
            fetch('index.php?action=leave_challenge', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `participant_id=${participantId}`
            })
            /*.then(response => response.text())
            .then(txt => {
                console.log('RAW RESPONSE:', txt);
            })*/
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'index.php?action=dashboard';
                } else {
                    alert(data.message || 'Error leaving challenge');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error leaving challenge');
            });
        }
        function showEditChallenge() {
            document.getElementById('editChallengeModal').classList.add('active');
        }
        function handleEditChallenge(event) {
            event.preventDefault();

            const formData = new FormData(event.target);
            formData.append('challenge_id', challengeId);

            fetch('index.php?action=edit_challenge', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Error editing challenge');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error editing challenge');
            });
        }

        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>
    <script src="utils/theme-toggle.js"></script>
</body>
</html>
