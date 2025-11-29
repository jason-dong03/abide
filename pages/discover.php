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
$uid = $user['user_id'] ?? null;

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
    <div class="row">
      <!-- Sidebar: Search & Filters -->
      <aside class="col-12 col-lg-3 mb-4">
        <div class="glass-pane p-4">
          <!-- Search Bar -->
          <div class="mb-4">
            <label class="form-label fw-semibold mb-2">Search</label>
            <div class="search-input-wrapper">
              <input 
                type="text" 
                class="search-input" 
                id="searchInput" 
                placeholder="Search by name..."
                autocomplete="off"
              >
              <span class="search-icon">🔍</span>
            </div>
          </div>

          <hr class="my-3">

          <!-- Sort -->
          <div class="mb-4">
            <label class="form-label fw-semibold mb-2">Sort By</label>
            <select id="sortSelect" class="form-select" aria-label="Sort challenges">
              <option value="newest">Newest First</option>
              <option value="participants">Most Participants</option>
              <option value="duration-asc">Duration (Short→Long)</option>
              <option value="duration-desc">Duration (Long→Short)</option>
            </select>
          </div>

          <hr class="my-3">

          <!-- Filter Chips -->
          <div class="filters-section">
            <div class="d-flex align-items-center justify-content-between mb-3">
              <label class="form-label fw-semibold mb-0">Filters</label>
              <button class="btn-outline-glass px-2 py-1 small" id="clearFilters">Clear</button>
            </div>

            <div class="mb-3">
              <p class="small fw-semibold mb-2">Status</p>
              <div class="d-flex flex-wrap gap-2">
                <div class="filter-chip" data-filter="status" data-value="active">Active</div>
                <div class="filter-chip" data-filter="status" data-value="starting">Starting Soon</div>
                <div class="filter-chip" data-filter="status" data-value="completed">Completed</div>
              </div>
            </div>

            <div class="mb-3">
              <p class="small fw-semibold mb-2">Duration</p>
              <div class="d-flex flex-wrap gap-2">
                <div class="filter-chip" data-filter="duration" data-value="short">≤30d</div>
                <div class="filter-chip" data-filter="duration" data-value="medium">31-60d</div>
                <div class="filter-chip" data-filter="duration" data-value="long">60+d</div>
              </div>
            </div>

            <div class="mb-2">
              <p class="small fw-semibold mb-2">Privacy</p>
              <div class="d-flex flex-wrap gap-2">
                <div class="filter-chip" data-filter="privacy" data-value="public">Public</div>
                <div class="filter-chip" data-filter="privacy" data-value="private">Private</div>
              </div>
            </div>
          </div>
        </div>
      </aside>

      <!-- Main Content: Results -->
      <div class="col-12 col-lg-9">
        <!-- Results Container -->
        <div class="results-container">
          <p class="text-white mb-3"><span id="resultCount"><?= count($challenges) ?></span> challenges found</p>
          <div class="results-grid" id="resultsGrid">
            <?php foreach ($challenges as $ch): 
              $title = $ch['title'] ?? 'Untitled';
              $desc  = $ch['description'] ?? '';
              $end   = $ch['end_date'];
              $start = $ch['start_date'];
              $pct   = pct_progress($start, $end, $today);
              $day   = day_number($start, $end, $today);
              $duration = get_duration_days($start, $end);
              $status = get_challenge_status($start, $end, $today);
              $cid = (int)$ch['challenge_id'];
              $participants = Db::count_participants($cid); 
              $creator_id = (int)($ch['creator_id'] ?? 0);
              $creator_name = h($ch['creator_first_name'] ?? 'Unknown') . ' ' . h($ch['creator_last_name'] ?? '');
              $is_private = (bool)($ch['is_private'] ?? false);
              
              $statusClass = strtolower(str_replace(' ', '-', $status));
              $privacyValue = $is_private ? 'private' : 'public';
              $durationCategory = $duration <= 30 ? 'short' : ($duration <= 60 ? 'medium' : 'long');
              
              $isOwner = ($uid && $creator_id === $uid);
              $isParticipant = ($uid && Db::is_participant($cid, $uid));
            ?>
            <article 
              class="challenge-card glass-pane p-4" 
              data-title="<?= h(strtolower($title)) ?>"
              data-status="<?= $statusClass ?>"
              data-duration="<?= $durationCategory ?>"
              data-privacy="<?= $privacyValue ?>"
              data-participants="<?= $participants ?>"
              data-duration-days="<?= $duration ?>"
              data-created="<?= h($ch['created_at'] ?? '') ?>"
              data-challenge-id="<?= $cid ?>"
              data-is-owner="<?= $isOwner ? 'true' : 'false' ?>"
              data-is-participant="<?= $isParticipant ? 'true' : 'false' ?>"         
              data-full-desc="<?= h($desc) ?>"
              data-creator="<?= $creator_name ?>"
              data-start-date="<?= h($start) ?>"
              data-end-date="<?= h($end) ?>"
              onclick="openModal(this)"
            >
              <h5 class="fw-semibold mb-2"><?= h($title) ?></h5>
              
              <div class="badges mb-3">
                <?php if ($status === 'Active'): ?>
                  <span class="badge badge-status-active">Active</span>
                <?php elseif ($status === 'Completed'): ?>
                  <span class="badge badge-status-completed">Completed</span>
                <?php else: ?>
                  <span class="badge" style="background: rgba(255, 193, 7, 0.2); color: #856404;">Starting Soon</span>
                <?php endif; ?>
                <?php if ($is_private): ?>
                  <span class="badge badge-private">Private</span>
                <?php else: ?>
                  <span class="badge badge-public">Public</span>
                <?php endif; ?>
                <span class="badge badge-duration fw-normal"><?= $duration ?> days</span>
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
            </article>
          <?php endforeach; ?>
          </div>
        </div>

        <div class="no-results" id="noResults" style="display: none;">
          <h3>No challenges found</h3>
          <p>Try adjusting your search or filters</p>
        </div>
      </div>
    </div>
  </main>

  <!-- Modal -->
  <div class="modal-backdrop-custom" id="modalBackdrop" onclick="closeModal()"></div>
  <div class="modal-custom" id="challengeModal">
    <div class="modal-content-custom">
      <div class="modal-header-custom">
        <div>
          <h3 id="modalTitle" class="mb-2"></h3>
          <div id="modalBadges" class="badges mb-0"></div>
        </div>
        <button class="modal-close" onclick="closeModal()">&times;</button>
      </div>
      
      <div class="modal-body">
        <p id="modalDescription" class="mb-3 fw-light"></p>
        
        <div class="mb-3">
          <p>Creator: <span class ="text-muted" id="modalCreator"></span></p>
        </div>
        
        <div class="mb-3">
          <p>Duration: <span class ="text-muted" id="modalDuration"></span></p> 
        </div>
        
        <div class="mb-3">
          <p>Dates: <span class ="text-muted" id="modalDates"></span></p> 
        </div>
        
        <div class="mb-3">
          <p>Participants:  <span class ="text-muted" id="modalParticipants"></span></p> 
        </div>
        
        <div class="progress-wrap">
          <div class="progress-bar" id="modalProgress"></div>
        </div>
      </div>
      
      <div class="modal-actions" id="modalActions"></div>
    </div>
  </div>

  <footer class="footer-overlay text-center mt-auto">
    <p class="small mb-0">© 2025 Jason, Eyuel, Gianna - University of Virginia</p>
  </footer>

  <script>
    const searchInput = document.getElementById('searchInput');
    const filterChips = document.querySelectorAll('.filter-chip');
    const clearFiltersBtn = document.getElementById('clearFilters');
    const sortSelect = document.getElementById('sortSelect');
    const resultsGrid = document.getElementById('resultsGrid');
    const resultCount = document.getElementById('resultCount');
    const noResults = document.getElementById('noResults');
    
    let activeFilters = {
      status: new Set(),
      duration: new Set(),
      privacy: new Set()
    };
  
    searchInput.addEventListener('input', function() {
      filterChallenges();
    });
    
    filterChips.forEach(chip => {
      chip.addEventListener('click', function() {
        const filterType = this.dataset.filter;
        const filterValue = this.dataset.value;
        
        if (this.classList.contains('active')) {
          this.classList.remove('active');
          activeFilters[filterType].delete(filterValue);
        } else {
          this.classList.add('active');
          activeFilters[filterType].add(filterValue);
        }
        
        filterChallenges();
      });
    });
    
    clearFiltersBtn.addEventListener('click', function() {
      searchInput.value = '';
      filterChips.forEach(chip => chip.classList.remove('active'));
      activeFilters = {
        status: new Set(),
        duration: new Set(),
        privacy: new Set()
      };
      filterChallenges();
    });
    
    sortSelect.addEventListener('change', function() {
      sortChallenges(this.value);
    });
    
    function filterChallenges() {
      const searchTerm = searchInput.value.toLowerCase().trim();
      const cards = Array.from(resultsGrid.querySelectorAll('.challenge-card'));
      let visibleCount = 0;
      
      cards.forEach(card => {
        let show = true;
        
        if (searchTerm) {
          const title = card.dataset.title;
          try {
            const regex = new RegExp(searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'i');
            if (!regex.test(title)) {
              show = false;
            }
          } catch {
            if (!title.includes(searchTerm)) {
              show = false;
            }
          }
        }
        
        if (activeFilters.status.size > 0) {
          const cardStatus = card.dataset.status;
          const statusMatch = Array.from(activeFilters.status).some(status => {
            if (status === 'starting') return cardStatus === 'starting-soon';
            return cardStatus === status;
          });
          if (!statusMatch) show = false;
        }
        
        if (activeFilters.duration.size > 0) {
          if (!activeFilters.duration.has(card.dataset.duration)) {
            show = false;
          }
        }
        
        if (activeFilters.privacy.size > 0) {
          if (!activeFilters.privacy.has(card.dataset.privacy)) {
            show = false;
          }
        }
        
        card.style.display = show ? '' : 'none';
        if (show) visibleCount++;
      });
      
      resultCount.textContent = visibleCount;
      noResults.style.display = visibleCount === 0 ? 'block' : 'none';
      resultsGrid.style.display = visibleCount === 0 ? 'none' : '';
    }
    
    function sortChallenges(sortType) {
      const cards = Array.from(resultsGrid.querySelectorAll('.challenge-card'));
      
      cards.sort((a, b) => {
        switch(sortType) {
          case 'newest':
            return new Date(b.dataset.created) - new Date(a.dataset.created);
          case 'participants':
            return parseInt(b.dataset.participants) - parseInt(a.dataset.participants);
          case 'duration-asc':
            return parseInt(a.dataset.durationDays) - parseInt(b.dataset.durationDays);
          case 'duration-desc':
            return parseInt(b.dataset.durationDays) - parseInt(a.dataset.durationDays);
          default:
            return 0;
        }
      });
      
      cards.forEach(card => resultsGrid.appendChild(card));
    }
    
    function openModal(card) {
      const modal = document.getElementById('challengeModal');
      const backdrop = document.getElementById('modalBackdrop');
      

      document.getElementById('modalTitle').textContent = card.querySelector('h5').textContent;
      document.getElementById('modalDescription').textContent = card.dataset.fullDesc || 'No description provided.';
      document.getElementById('modalCreator').textContent = card.dataset.creator;
      document.getElementById('modalDuration').textContent = card.dataset.durationDays + ' days';
      document.getElementById('modalDates').textContent = card.dataset.startDate + ' to ' + card.dataset.endDate;
      document.getElementById('modalParticipants').textContent = card.dataset.participants;
      

      const badgesHtml = card.querySelector('.badges').innerHTML;
      document.getElementById('modalBadges').innerHTML = badgesHtml;
      

      const progressWidth = card.querySelector('.progress-bar').style.width;
      document.getElementById('modalProgress').style.width = progressWidth;
      

      const actionsDiv = document.getElementById('modalActions');
      const challengeId = card.dataset.challengeId;
      const isOwner = card.dataset.isOwner === 'true';
      const isParticipant = card.dataset.isParticipant === 'true';
      
       if (isOwner) {
        actionsDiv.innerHTML = `
          <button class="btn-delete" onclick="deleteChallenge(${challengeId})">
            Delete Challenge
          </button>
        `;
      } else if (isParticipant) {
        actionsDiv.innerHTML = `
          <button class="btn-join" disabled style="opacity: 0.6; cursor: not-allowed;">
            Already Joined ✓
          </button>
        `;
      } else {
        actionsDiv.innerHTML = `
          <button class="btn-join" onclick="joinChallenge(${challengeId})">
            Join Challenge
          </button>
        `;
      }
      

      modal.classList.add('show');
      backdrop.classList.add('show');
      document.body.style.overflow = 'hidden';
    }
    
    function closeModal() {
      const modal = document.getElementById('challengeModal');
      const backdrop = document.getElementById('modalBackdrop');
      
      modal.classList.remove('show');
      backdrop.classList.remove('show');
      document.body.style.overflow = '';
    }
    
    function joinChallenge(challengeId) {
      window.location.href = 'index.php?action=join_challenge&cid=' + challengeId;
    }
    
    function deleteChallenge(challengeId) {
      const confirmed = confirm(
          "Are you sure you want to delete this challenge?\nThis action cannot be undone."
      );
      if (!confirmed) return;
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
    
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        closeModal();
      }
    });
  </script>
  <script src="utils/theme-toggle.js"></script>
</body>
</html>