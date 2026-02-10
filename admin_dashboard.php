<?php
session_start();
require_once "db_connect.php";

// Check admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Flash messages
$flash = "";
if (!empty($_SESSION['flash_success'])) {
    $flash = "<div class='flash success'>{$_SESSION['flash_success']}</div>";
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    $flash = "<div class='flash error'>{$_SESSION['flash_error']}</div>";
    unset($_SESSION['flash_error']);
}

// Fetch problems
$stmt = $pdo->query("
    SELECT p.*, u.name AS user_name, u.email, u.phone
    FROM problems p
    JOIN users u ON p.user_id = u.user_id
    WHERE p.deleted_by_admin = 0
    ORDER BY p.created_at DESC
");
$problems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch pending response teams
$stmt2 = $pdo->query("SELECT * FROM users WHERE role='response' AND status='pending'");
$pending_responses = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// Fetch active teams
$teams = $pdo->query("SELECT user_id, name, category FROM users WHERE role='response' AND status='active'")->fetchAll();

// Fetch pending unban requests for notifications
$unbanRequests = $pdo->query("
    SELECT ur.*, u.name, u.email, u.phone 
    FROM unban_requests ur
    JOIN users u ON ur.user_id = u.user_id
    ORDER BY ur.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>GovConnect — Admin Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<style>
:root {
  --bg1:#0f172a; --bg2:#1e293b;
  --accent1:#667eea; --accent2:#764ba2;
  --success:#27ae60; --danger:#e74c3c;
  --muted:#94a3b8;
}
body {margin:0;font-family:Inter,sans-serif;background:linear-gradient(135deg,var(--bg1),var(--bg2));color:#fff;}
.topbar{display:flex;justify-content:space-between;align-items:center;padding:14px 28px;background:rgba(255,255,255,0.05);backdrop-filter:blur(10px);border-bottom:1px solid rgba(255,255,255,0.1);}
.brand{display:flex;align-items:center;gap:10px;font-weight:700}
.user-area{display:flex;align-items:center;gap:14px}
.btn-ghost{padding:8px 14px;border-radius:10px;border:1px solid rgba(255,255,255,0.15);background:rgba(255,255,255,0.05);color:#fff;cursor:pointer;}
.btn-ghost:hover{background:rgba(255,255,255,0.15)}
.container{max-width:1250px;margin:25px auto;padding:0 18px}
.flash{padding:14px;border-radius:12px;margin-bottom:20px;font-weight:600;text-align:center}
.flash.success{background:rgba(39,174,96,0.2);border:1px solid #27ae60;color:#2ecc71}
.flash.error{background:rgba(231,76,60,0.2);border:1px solid #e74c3c;color:#e74c3c}
#map{height:460px;border-radius:14px;margin-bottom:30px}
.legend{position:absolute;top:15px;right:15px;background:rgba(0,0,0,0.6);color:#fff;padding:10px 14px;border-radius:10px;font-size:19px}
.legend span{display:inline-block;width:14px;height:14px;margin-right:6px;border-radius:3px}
.tabs{display:flex;gap:18px;margin:20px 0;border-bottom:2px solid rgba(255,255,255,0.1)}
.tab-btn{position:relative;padding:10px 18px;cursor:pointer;font-weight:600;color:var(--muted);transition:0.3s}
.tab-btn.active{color:#fff}
.tab-btn.active::after{content:"";position:absolute;bottom:-2px;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--accent1),var(--accent2));border-radius:3px}
.problem-card{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:18px;margin-bottom:20px;overflow:hidden;box-shadow:0 8px 25px rgba(0,0,0,0.5);transition:transform .25s}
.problem-card:hover{transform:translateY(-3px)}
.problem-header{padding:16px 20px;background:rgba(255,255,255,0.05);display:flex;justify-content:space-between;align-items:center}
.problem-header h4{margin:0;font-size:21px}
.problem-body{padding:20px;font-size:17px;line-height:1.6}
.contact-card{background:rgba(255,255,255,0.05);padding:12px 16px;border-radius:12px;margin-top:12px;font-size:17px}
.problem-footer{display:flex;gap:10px;flex-wrap:wrap;padding:14px 20px;background:rgba(255,255,255,0.05);border-top:1px solid rgba(255,255,255,0.08)}
.footer-btn{padding:10px 14px;border-radius:10px;border:none;background:rgba(255,255,255,0.08);color:#fff;font-weight:600;cursor:pointer;transition:.25s}
.footer-btn:hover{background:rgba(255,255,255,0.2)}
select.footer-btn{appearance:none;padding-right:30px}
select.footer-btn option{background:#1e293b;color:#fff}
.badge{padding:6px 12px;border-radius:8px;font-size:15px;font-weight:600}
.status-pending{background:#f39c12}
.status-verified{background:#3498db}
.status-assigned{background:#9b59b6}
.status-resolved{background:#27ae60}
.status-rejected{background:#e74c3c}
.priority-low{background:#2ecc71}
.priority-medium{background:#f1c40f;color:#000}
.priority-high{background:#e67e22}
.priority-sos{background:#e74c3c}
.light-theme {--bg1:#f3e8ff;--bg2:#e9d5ff;--accent1:#a78bfa;--accent2:#c084fc;--muted:#6b7280;color:#1f1f1f;background:linear-gradient(135deg,var(--bg1),var(--bg2));}
.light-theme .btn-ghost,.light-theme .footer-btn{color:#333;background:rgba(255,255,255,0.8);border:1px solid #ddd;}
.light-theme .btn-ghost:hover,.light-theme .footer-btn:hover{background:rgba(255,255,255,1);}
.light-theme .problem-card{background:rgba(255,255,255,0.8);color:#222;}
.light-theme .problem-header{background:rgba(255,255,255,0.9);}
.light-theme .problem-footer{background:rgba(255,255,255,0.9);}
.light-theme .contact-card{background:rgba(255,255,255,0.9);}

/* Notification small card */
.notice-card {
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.12);
  padding: 10px 12px;
  border-radius: 10px;
  margin-bottom: 8px;
  cursor: pointer;
}
.notice-card:hover { background: rgba(255,255,255,0.12); }
.notice-muted { color: var(--muted); font-size: 13px; }
</style>
</head>
<body>

<header class="topbar">
  <div class="brand"><i class="fa-solid fa-shield-halved"></i> GovConnect Admin</div>
  <div class="user-area">
    <button id="themeToggle" class="btn-ghost"><i class="fa-solid fa-circle-half-stroke"></i> Theme</button>
    <span>Welcome, <?= htmlspecialchars($_SESSION['name']); ?></span>
    <button class="btn-ghost" onclick="location.href='logout.php'"><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
  </div>
</header>

<div class="container">
  <?= $flash ?>
  <h3><i class="fa-solid fa-map-location-dot"></i> Problem Map Overview</h3>
  <div id="map"></div>

  <div class="tabs">
    <div class="tab-btn active" data-tab="all">All</div>
    <div class="tab-btn" data-tab="police">Police</div>
    <div class="tab-btn" data-tab="fire">Fire</div>
    <div class="tab-btn" data-tab="medical">Medical</div>
    <div class="tab-btn" data-tab="gov">Government</div>
    <div class="tab-btn" data-tab="other">Other</div>
    <div class="tab-btn" data-tab="approval">Response Approvals</div>
    <div class="tab-btn" data-tab="finduser">Find User</div>
  </div>

  <!-- Problems -->
  <div id="problems-container">
    <?php foreach($problems as $row): 
      $statusClass = "status-".strtolower($row['status'] ?: 'pending');
      $prioClass = "priority-".strtolower($row['priority'] ?: 'low');
    ?>
    <div class="problem-card" data-category="<?= htmlspecialchars($row['category']) ?>" data-priority="<?= strtolower($row['priority']) ?>">
      <div class="problem-header">
        <h4>#<?= $row['problem_id'] ?> — <?= ucfirst($row['category']) ?></h4>
        <div>
          <span class="badge <?= $statusClass ?>"><?= ucfirst($row['status']) ?></span>
          <span class="badge <?= $prioClass ?>"><?= ucfirst($row['priority']) ?></span>
        </div>
      </div>
      <div class="problem-body">
        <p><b>Description:</b> <?= htmlspecialchars($row['description']) ?></p>
        <?php if($row['suggestion']): ?><p><b>Suggestion:</b> <?= htmlspecialchars($row['suggestion']) ?></p><?php endif; ?>
        <?php if(isset($row['report']) && !empty($row['report'])): ?>
          <p><b>Report:</b> <?= nl2br(htmlspecialchars($row['report'])) ?></p>
        <?php endif; ?>

        <!-- Feedback display -->
        <?php
        $fbStmt = $pdo->prepare("
            SELECT f.*, u.name AS uname 
            FROM feedbacks f
            JOIN users u ON f.user_id = u.user_id
            WHERE f.problem_id = ?
            ORDER BY f.created_at DESC
        ");
        $fbStmt->execute([$row['problem_id']]);
        $feedbacks = $fbStmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($feedbacks) > 0):
        ?>
        <div class="contact-card" style="margin-top:15px;background:rgba(255,255,255,0.08);">
          <b><i class="fa-solid fa-comment-dots"></i> User Feedback</b><br>
          <?php foreach($feedbacks as $f): ?>
            <p style="margin-top:8px;">
              <b><?= htmlspecialchars($f['uname']); ?></b> — ⭐ <?= str_repeat('★', (int)$f['rating']); ?><br>
              <?= nl2br(htmlspecialchars($f['comment'])); ?><br>
              <small style="color:var(--muted);"><?= $f['created_at']; ?></small>
            </p>
            <hr style="border:none;border-top:1px solid rgba(255,255,255,0.1)">
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <p><b>Location:</b> <?= htmlspecialchars($row['location']) ?></p>
        <div class="contact-card">
          <i class="fa fa-user"></i> <?= htmlspecialchars($row['user_name']) ?><br>
          <i class="fa fa-envelope"></i> <?= htmlspecialchars($row['email']) ?><br>
          <i class="fa fa-phone"></i> <?= htmlspecialchars($row['phone']) ?>
        </div>
      </div>
     <div class="problem-footer">
  <?php if($row['status']=='pending'): ?>
    <form method="post" action="admin_actions.php">
      <input type="hidden" name="problem_id" value="<?= $row['problem_id'] ?>">
      <button name="action" value="verify" class="footer-btn">✅ Accept</button>
      <button name="action" value="reject" class="footer-btn">❌ Reject</button>
    </form>
  <?php elseif($row['status']=='verified'): ?>
    <form method="post" action="admin_actions.php">
      <input type="hidden" name="problem_id" value="<?= $row['problem_id'] ?>">
      <select name="priority" class="footer-btn" required>
        <option value="">Set Priority</option>
        <option value="low">Low</option>
        <option value="medium">Medium</option>
        <option value="high">High</option>
        <option value="sos">SOS</option>
      </select>
      <select name="assigned_to" class="footer-btn" required>
        <option value="">Assign Team</option>
        <?php foreach($teams as $t): if($t['category']==$row['category']): ?>
          <option value="<?= $t['user_id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
        <?php endif; endforeach; ?>
      </select>
      <button name="action" value="assign" class="footer-btn">✔ Confirm</button>
    </form>
  <?php endif; ?>

  <!-- Delete button always visible -->
  <form method="post" action="admin_actions.php" onsubmit="return confirm('Delete this complaint?');">
    <input type="hidden" name="problem_id" value="<?= $row['problem_id'] ?>">
    <button name="action" value="delete" class="footer-btn">🗑 Delete</button>
  </form>
</div>

    </div>
    <?php endforeach; ?>
  </div>

  <!-- Response Approval Tab -->
  <div id="approval-container" style="display:none;">
    <h3>Pending Response Team Approvals</h3>
    <table style="width:100%;background:rgba(255,255,255,0.05);border-radius:12px;overflow:hidden">
      <tr style="background:rgba(255,255,255,0.1)"><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Actions</th></tr>
      <?php foreach($pending_responses as $r): ?>
      <tr>
        <td><?= $r['user_id'] ?></td>
        <td><?= htmlspecialchars($r['name']) ?></td>
        <td><?= htmlspecialchars($r['email']) ?></td>
        <td><?= htmlspecialchars($r['phone']) ?></td>
        <td>
          <form method="post" action="admin_actions.php" style="display:inline">
            <input type="hidden" name="user_id" value="<?= $r['user_id'] ?>">
            <button name="action" value="approve_team" class="footer-btn">✅ Approve</button>
            <button name="action" value="reject_team" class="footer-btn">❌ Reject</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <!-- Deleted Complaints Section (toggleable) -->
  <hr style="margin:40px 0;border:none;border-top:2px solid rgba(255,255,255,0.1)">
  <button class="btn-ghost" onclick="toggleDeleted()">🗑 View Deleted Complaints</button>

  <div id="deletedContainer" style="display:none;margin-top:20px;">
    <h3><i class="fa-solid fa-trash"></i> Deleted Complaints</h3>
    <table style="width:100%;background:rgba(255,255,255,0.05);border-radius:12px;">
      <tr style="background:rgba(255,255,255,0.1)">
        <th>Problem ID</th>
        <th>User ID</th>
        <th>User Name</th>
        <th>User Email</th>
        <th>Category</th>
        <th>Description</th>
        <th>Status</th>
        <th>Priority</th>
        <th>Deleted At</th>
      </tr>
      <?php
      $deleted = $pdo->query("SELECT * FROM deleted_problems ORDER BY deleted_at DESC")->fetchAll(PDO::FETCH_ASSOC);
      if (count($deleted) > 0):
        foreach($deleted as $d):
      ?>
      <tr>
        <td><?= $d['problem_id'] ?></td>
        <td><?= $d['user_id'] ?></td>
        <td><?= htmlspecialchars($d['user_name']) ?></td>
        <td><?= htmlspecialchars($d['user_email']) ?></td>
        <td><?= htmlspecialchars($d['category']) ?></td>
        <td><?= htmlspecialchars($d['description']) ?></td>
        <td><?= htmlspecialchars($d['status']) ?></td>
        <td><?= htmlspecialchars($d['priority']) ?></td>
        <td><?= $d['deleted_at'] ?></td>
      </tr>
      <?php
        endforeach;
      else:
      ?>
      <tr><td colspan="9" style="text-align:center;color:var(--muted)">No deleted complaints found.</td></tr>
      <?php endif; ?>
    </table>
  </div>

  <!-- Find User Tab -->
  <div id="finduser-container" style="display:none;margin-top:20px;">
    <h3><i class="fa-solid fa-user-magnifying-glass"></i> Find User
      <?php
        $countUnban = $pdo->query("SELECT COUNT(*) FROM unban_requests")->fetchColumn();
        if ($countUnban > 0) {
            echo "<span style='color:#f1c40f;font-size:18px;'> 🔔 ($countUnban unban request(s))</span>";
        }
      ?>
    </h3>

    <!-- Notification list: clicking a notification will open Find User with that user's email prefilled -->
    <?php if (count($unbanRequests) > 0): ?>
      <div style="margin-bottom:16px;">
        <?php foreach ($unbanRequests as $req): ?>
          <?php
            // prefer email for lookup, fallback to phone
            $lookup = $req['email'] ?: $req['phone'];
            $lookupEsc = htmlspecialchars($lookup);
            $nameEsc = htmlspecialchars($req['name']);
            $phoneEsc = htmlspecialchars($req['phone']);
            $emailEsc = htmlspecialchars($req['email']);
            $reasonEsc = htmlspecialchars($req['reason']);
            $createdEsc = htmlspecialchars($req['created_at']);
          ?>
          <div class="notice-card" onclick="window.location.href='admin_dashboard.php?tab=finduser&find_user=<?= urlencode($lookup) ?>'">
            <strong><?= $nameEsc ?></strong> — <?= $emailEsc ?> / <?= $phoneEsc ?>
            <div class="notice-muted">Unban requested: <?= $createdEsc ?> — <?= $reasonEsc ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="get" style="margin-bottom:20px;">
      <input type="text" name="find_user" placeholder="Enter Email or Phone..."
             style="padding:10px 14px;width:300px;border-radius:10px;border:none;outline:none;">
      <button class="footer-btn" type="submit"><i class="fa fa-search"></i> Search</button>
    </form>

    <?php
    if (isset($_GET['find_user']) && !empty(trim($_GET['find_user']))) {
        $search = trim($_GET['find_user']);
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR phone = ?");
        $stmt->execute([$search, $search]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user):
            // Fetch pending unban request for this user
            $unbanReq = $pdo->prepare("SELECT * FROM unban_requests WHERE user_id = ?");
            $unbanReq->execute([$user['user_id']]);
            $unban = $unbanReq->fetch(PDO::FETCH_ASSOC);
    ?>
      <div class="problem-card">
        <div class="problem-header">
          <h4>User ID: <?= $user['user_id'] ?> — <?= htmlspecialchars($user['name']) ?></h4>
          <span class="badge" style="background:#3498db;"><?= ucfirst($user['role']) ?></span>
        </div>
        <div class="problem-body">
          <p><b>Email:</b> <?= htmlspecialchars($user['email']) ?></p>
          <p><b>Phone:</b> <?= htmlspecialchars($user['phone']) ?></p>
          <p><b>Role:</b> <?= ucfirst($user['role']) ?></p>
          <p><b>Status:</b> <?= ucfirst($user['status']) ?></p>
          <?php if (!empty($user['category'])): ?>
            <p><b>Category:</b> <?= htmlspecialchars($user['category']) ?></p>
          <?php endif; ?>
          <p><b>Joined:</b> <?= htmlspecialchars($user['created_at'] ?? 'N/A') ?></p>
        </div>

        <div class="problem-footer">
          <form method="post" action="send_warning.php" style="width:100%;">
            <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
            <textarea name="warning_message" placeholder="Type a warning message..." required
                      style="width:100%;padding:10px;border-radius:10px;border:none;outline:none;margin-bottom:10px;height:100px;"></textarea>
            <button type="submit" class="footer-btn">⚠️ Send Warning</button>
          </form>

          <!-- Unban Request (if exists) -->
          <?php if ($unban): ?>
            <div class="contact-card" style="background:rgba(255,255,255,0.1);margin-top:10px;">
              <b>📝 Unban Request Pending</b><br>
              <b>Reason:</b> <?= nl2br(htmlspecialchars($unban['reason'])) ?><br>
              <small style="color:var(--muted)">Requested on <?= htmlspecialchars($unban['created_at']) ?></small>
              <br><br>
              <form method="post" action="ban_user.php" style="display:inline-block;">
                <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                <input type="hidden" name="search_value" value="<?= htmlspecialchars($search) ?>">
                <button name="action" value="approve_unban" class="footer-btn" style="background:#27ae60;">✅ Approve Unban</button>
              </form>
              <form method="post" action="ban_user.php" style="display:inline-block;margin-left:8px;">
                <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                <input type="hidden" name="search_value" value="<?= htmlspecialchars($search) ?>">
                <button name="action" value="reject_unban" class="footer-btn" style="background:#e74c3c;">❌ Reject</button>
              </form>
            </div>
          <?php endif; ?>

          <!-- Ban/Unban Section -->
          <form method="post" action="ban_user.php" style="margin-top:15px;">
            <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
            <input type="hidden" name="search_value" value="<?= htmlspecialchars($search) ?>">

            <?php if (empty($user['is_banned']) || !$user['is_banned']): ?>
              <h4 style="margin-top:10px;">🚫 Ban User</h4>
              <label>Ban Duration (in days):</label>
              <input type="number" name="ban_days" min="1" placeholder="e.g., 7"
                     style="width:80px;padding:6px;border-radius:8px;border:none;outline:none;">
              <label style="margin-left:10px;">
                <input type="checkbox" name="lifetime"> Lifetime Ban
              </label>
              <br><br>
              <button name="action" value="ban_user" class="footer-btn" style="background:#e74c3c;">🚷 Ban Account</button>
            <?php else: ?>
              <div class="contact-card" style="margin-top:10px;">
                <b>Status:</b> <span style="color:#e74c3c;">User is currently banned</span><br>
                <?php if (!empty($user['ban_until'])): ?>
                  <b>Banned Until:</b> <?= htmlspecialchars($user['ban_until']) ?><br>
                <?php else: ?>
                  <b>Type:</b> Permanent Ban<br>
                <?php endif; ?>
              </div>
              <button name="action" value="unban_user" class="footer-btn" style="background:#27ae60;">✅ Unban User</button>
            <?php endif; ?>
          </form>
        </div>
      </div>
    <?php
        else:
            echo "<div class='flash error'>No user found with that email or phone.</div>";
        endif;
    }
    ?>
  </div>

</div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
// Theme toggle
document.getElementById("themeToggle").addEventListener("click",()=>{document.body.classList.toggle("light-theme");});

// Map
// Map setup
var dhakaBounds = L.latLngBounds([23.65,90.30],[23.90,90.55]);
var map = L.map('map',{center:[23.78,90.40],zoom:12,maxBounds:dhakaBounds,maxBoundsViscosity:1});
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'&copy; OpenStreetMap',minZoom:11,maxZoom:16}).addTo(map);
<?php foreach($problems as $p): if(!empty($p['latitude']) && !empty($p['longitude'])):
$color = ($p['priority']=='sos' || $p['priority']=='high')?'red':(($p['priority']=='medium')?'orange':'blue');
if ($p['status']=='pending') $color = 'gray';
?>
L.circleMarker([<?= $p['latitude']?>,<?= $p['longitude']?>],{color:"<?= $color?>",radius:8})
  .addTo(map).bindPopup("<b><?= addslashes($p['category']) ?></b><br><?= addslashes($p['description']) ?>");
<?php endif; endforeach; ?>
// Toggle deleted complaints
function toggleDeleted(){
  const box = document.getElementById('deletedContainer');
  box.style.display = (box.style.display === 'none' || box.style.display === '') ? 'block' : 'none';
}

// ===== Tab memory (localStorage + respect ?tab=... in URL) =====
document.addEventListener("DOMContentLoaded", function() {
  const urlParams = new URLSearchParams(window.location.search);
  const urlTab = urlParams.get('tab');
  if (urlTab) {
    localStorage.setItem("adminActiveTab", urlTab);
  }

  const savedTab = localStorage.getItem("adminActiveTab") || "all";
  activateTab(savedTab);

  document.querySelectorAll('.tab-btn').forEach(btn=>{
    btn.addEventListener('click',()=>{
      const tab = btn.dataset.tab;
      localStorage.setItem("adminActiveTab", tab);
      activateTab(tab);
    });
  });
});

function activateTab(tab){
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  const active = document.querySelector(`.tab-btn[data-tab="${tab}"]`);
  if(active) active.classList.add('active');

  document.getElementById('approval-container').style.display = (tab === 'approval') ? 'block' : 'none';
  document.getElementById('finduser-container').style.display = (tab === 'finduser') ? 'block' : 'none';
  document.getElementById('problems-container').style.display = (tab !== 'approval' && tab !== 'finduser') ? 'block' : 'none';

  if (tab !== 'approval' && tab !== 'finduser') {
    document.querySelectorAll('.problem-card').forEach(c=>{
      c.style.display = (tab === 'all' || c.dataset.category === tab) ? 'block' : 'none';
    });
  }
}

// Auto-append redirect_tab input to forms so server can redirect back
document.addEventListener("submit", function(e){
  const currentTab = localStorage.getItem("adminActiveTab") || "all";
  if (e.target && e.target.tagName === "FORM") {
    if (!e.target.querySelector('input[name="redirect_tab"]')) {
      const hidden = document.createElement("input");
      hidden.type = "hidden";
      hidden.name = "redirect_tab";
      hidden.value = currentTab;
      e.target.appendChild(hidden);
    }
  }
}, true);
</script>
</body>
</html>
