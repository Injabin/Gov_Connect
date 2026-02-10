<?php
session_start();
require_once "db_connect.php";

// Check login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

// 🔹 Fetch user ban status
$userStmt = $pdo->prepare("SELECT ban_until FROM users WHERE user_id = ?");
$userStmt->execute([$user_id]);
$userData = $userStmt->fetch(PDO::FETCH_ASSOC);

$isBanned = false;
$banMessage = "";
if (!empty($userData['ban_until'])) {
    $banUntil = $userData['ban_until'];
    if ($banUntil === 'permanent') {
        $isBanned = true;
        $banMessage = "Your account is permanently banned from submitting new problems.";
    } elseif (strtotime($banUntil) > time()) {
        $isBanned = true;
        $remaining = ceil((strtotime($banUntil) - time()) / 86400);
        $banMessage = "Your account is temporarily banned for $remaining more day(s).";
    }
}

// 🔹 Fetch all user reports — ALWAYS visible to user, ignore admin/team deletions
$reportsStmt = $pdo->prepare("
    SELECT * FROM problems 
    WHERE user_id = :user_id
    ORDER BY created_at DESC
");
$reportsStmt->execute(['user_id' => $user_id]);
$reports = $reportsStmt->fetchAll(PDO::FETCH_ASSOC);


// Fetch active problem IDs (for feedback validation)
$activeStmt = $pdo->prepare("SELECT problem_id FROM problems");
$activeStmt->execute();
$activeReports = $activeStmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch map reports
$mapStmt = $pdo->prepare("SELECT * FROM problems WHERE status IN ('verified','assigned')");
$mapStmt->execute();
$mapReports = $mapStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch feedbacks already submitted by user
$fbStmt = $pdo->prepare("SELECT problem_id FROM feedbacks WHERE user_id = ?");
$fbStmt->execute([$user_id]);
$feedbacksDone = $fbStmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>GovConnect — Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<style>
:root {
  --bg1:#0f172a;
  --bg2:#1e293b;
  --accent1:#667eea;
  --accent2:#764ba2;
  --success:#27ae60;
  --danger:#e74c3c;
  --muted:#94a3b8;
}

/* Light theme */
body.light {
  --bg1:#e6e1f9;
  --bg2:#f4f0ff;
  --accent1:#b48cf0;
  --accent2:#d1a9ff;
  --success:#2ecc71;
  --danger:#e74c3c;
  --muted:#555;
  color:#222;
  background: linear-gradient(135deg,var(--bg1),var(--bg2));
}

/* Base */
body {
  margin:0;
  font-family: 'Inter', sans-serif;
  background: linear-gradient(135deg,var(--bg1),var(--bg2));
  color:#fff;
}

/* Topbar */
.topbar {
  display:flex;
  justify-content:space-between;
  align-items:center;
  padding:14px 28px;
  background: rgba(255,255,255,0.05);
  backdrop-filter: blur(12px);
  border-bottom:1px solid rgba(255,255,255,0.08);
}
.brand { display:flex;align-items:center;gap:10px;font-weight:700; }
.brand i { font-size:25px; }
.user-area { display:flex;align-items:center;gap:14px; }
.user-area span { font-weight:600; }
.btn-ghost {
  background: rgba(255,255,255,0.08);
  border:1px solid rgba(255,255,255,0.12);
  padding:8px 14px;
  border-radius:10px;
  color:#fff;
  cursor:pointer;
  transition:all .25s;
}
.btn-ghost:hover { background: rgba(255,255,255,0.18); }

body.light .btn-ghost {
  background: rgba(0,0,0,0.05);
  border:1px solid rgba(0,0,0,0.1);
  color:#333;
}
body.light .btn-ghost:hover { background: rgba(0,0,0,0.1); }

.container { max-width:1100px; margin:30px auto; padding:0 18px; }

.card {
  background: rgba(255,255,255,0.05);
  border:1px solid rgba(255,255,255,0.08);
  backdrop-filter: blur(14px);
  border-radius:14px;
  padding:18px;
  margin-bottom:20px;
  box-shadow:0 6px 25px rgba(0,0,0,0.4);
}
body.light .card {
  background: rgba(255,255,255,0.9);
  border:1px solid rgba(0,0,0,0.1);
  color:#222;
  box-shadow:0 4px 15px rgba(0,0,0,0.1);
}

#map { height:420px; border-radius:12px; }

.cta-btn, .sos-btn {
  display:block;
  width:100%;
  padding:14px;
  border:none;
  border-radius:12px;
  color:#fff;
  font-weight:700;
  cursor:pointer;
  transition: all .25s;
}
.cta-btn {
  background: linear-gradient(90deg,var(--accent1),var(--accent2));
  font-size:20px;
  margin-top:14px;
}
.cta-btn:hover { transform:translateY(-3px); box-shadow:0 10px 30px rgba(0,0,0,0.4); }

.sos-btn {
  background: linear-gradient(90deg,#ff4d4d,#ff7a1a);
  font-size:25px;
  margin-top:10px;
}
.sos-btn:hover { transform:translateY(-3px); box-shadow:0 10px 30px rgba(0,0,0,0.5); }

.warning {
  background: rgba(255,0,0,0.08);
  border:1px solid rgba(255,0,0,0.2);
  padding:10px;
  border-radius:10px;
  font-size:22px;
  margin-bottom:20px;
}
.flash { margin-bottom:20px; padding:12px; border-radius:10px; font-weight:600; }
.flash.success { background: rgba(39,174,96,0.2); color: #2ecc71; border:1px solid #27ae60; }
.flash.error { background: rgba(231,76,60,0.2); color: #e74c3c; border:1px solid #e74c3c; }

table { width:100%; border-collapse:collapse; font-size:19px; }
thead { background:rgba(255,255,255,0.08); }
thead th { text-align:left; padding:10px; }
tbody td { padding:10px; border-top:1px solid rgba(255,255,255,0.06); }
.status-badge {
  padding:4px 10px;
  border-radius:999px;
  font-size:21px;
  font-weight:600;
  color:#fff;
}
.status-pending{ background:#f39c12; }
.status-verified{ background:var(--success); }
.status-assigned{ background:#0ea5a1; }
.status-resolved{ background:#2b7cff; }
.status-rejected{ background:var(--danger); }

.feedback-box { margin-top:10px; display:none; }
.feedback-box textarea {
  width:100%;
  border-radius:10px;
  padding:8px;
  border:none;
  resize:none;
  font-size:16px;
}
.feedback-box button {
  margin-top:6px;
  padding:8px 14px;
  border:none;
  border-radius:8px;
  background:var(--success);
  color:#fff;
  cursor:pointer;
}

/* Appeal box */
.appeal-box {
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.1);
  padding:18px;
  border-radius:14px;
  margin-top:15px;
}
.appeal-box textarea {
  width:100%;
  padding:10px;
  border-radius:10px;
  border:none;
  resize:none;
  font-size:16px;
  margin-bottom:10px;
}
.appeal-box button {
  background: linear-gradient(90deg,var(--accent1),var(--accent2));
  border:none;
  border-radius:10px;
  padding:10px 16px;
  color:#fff;
  font-weight:600;
  cursor:pointer;
}
</style>
</head>
<body>

<header class="topbar">
  <div class="brand"><i class="fa-solid fa-shield-halved"></i> GovConnect</div>
  <div class="user-area">
    <span>Welcome, <?= htmlspecialchars($_SESSION['name']); ?></span>
    <button class="btn-ghost" id="themeToggle"><i class="fa-solid fa-sun"></i> Theme</button>
    <button class="btn-ghost" onclick="location.href='profile.php'"><i class="fa-solid fa-user"></i> Profile</button>
    <button class="btn-ghost" onclick="location.href='logout.php'"><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
  </div>
</header>

<div class="container">
  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="flash success"><?= $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?></div>
  <?php elseif (!empty($_SESSION['flash_error'])): ?>
    <div class="flash error"><?= $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?></div>
  <?php endif; ?>

  <div class="card">
    <h4><i class="fa-solid fa-map-location-dot"></i> Active Issues Map</h4>
    <div id="map"></div>
  </div>

  <?php if ($isBanned): ?>
    <div class="card" style="background:rgba(255,0,0,0.1);border:1px solid rgba(255,0,0,0.2);">
      <h3><i class="fa-solid fa-ban"></i> Account Banned</h3>
      <p><?= $banMessage ?></p>
      <div class="appeal-box">
        <form action="submit_unban_appeal.php" method="POST">
          <textarea name="reason" rows="3" placeholder="Explain why your account should be unbanned..." required></textarea>
          <button type="submit"><i class="fa-solid fa-envelope"></i> Submit Unban Appeal</button>
        </form>
      </div>
    </div>
  <?php else: ?>
    <button class="cta-btn" onclick="location.href='submit_problem.php'"><i class="fa-solid fa-plus"></i> Submit New Problem</button>
    <button class="sos-btn" onclick="triggerSOS()">⚠️ Emergency SOS</button>
  <?php endif; ?>

  <script>
  function triggerSOS(){
      if(!navigator.geolocation){alert("Location not supported");return;}
      navigator.geolocation.getCurrentPosition(pos=>{
          const lat=pos.coords.latitude, lng=pos.coords.longitude;
          window.location.href=`process_submit_problem.php?sos=1&lat=${lat}&lng=${lng}`;
      },()=>alert("Failed to get location"));
  }
  </script>

  <div class="warning"><i class="fa-solid fa-triangle-exclamation"></i> Submitting false or misleading reports is a punishable offense.</div>

  <div class="card">
    <h4><i class="fa-solid fa-clipboard-list"></i> My Reports</h4>
    <table>
      <thead>
        <tr><th>ID</th><th>Category</th><th>Description</th><th>Location</th><th>Status</th><th>Suggestion</th><th>Submitted</th><th>Action</th></tr>
      </thead>
      <tbody>
      <?php if (count($reports)>0): foreach($reports as $row): ?>
        <?php $s = strtolower($row['status'] ?: 'pending'); ?>
        <tr>
          <td><?= $row['problem_id'] ?></td>
          <td><?= ucfirst(htmlspecialchars($row['category'])) ?></td>
          <td><?= htmlspecialchars($row['description']) ?></td>
          <td><?= htmlspecialchars($row['location_name'] ?: $row['location']) ?></td>
          <td><span class="status-badge status-<?= $s ?>"><?= ucfirst($row['status'] ?: 'Pending') ?></span></td>
          <td><?= htmlspecialchars($row['suggestion']) ?></td>
          <td><?= $row['created_at'] ?></td>
          <td>
            <?php if ($s === 'resolved'): ?>
                <?php if (!in_array($row['problem_id'],$feedbacksDone)): ?>
                    <button class="btn-ghost" onclick="toggleFeedback(<?= $row['problem_id'] ?>)">Give Feedback</button>
                    <div class="feedback-box" id="fb<?= $row['problem_id'] ?>">
                      <form action="submit_feedback.php" method="POST">
                        <input type="hidden" name="problem_id" value="<?= $row['problem_id'] ?>">
                        <textarea name="comment" rows="2" placeholder="Write your feedback..." required></textarea>
                        <select name="rating" required>
                          <option value="">Rate</option>
                          <option>1</option>
                          <option>2</option>
                          <option>3</option>
                          <option>4</option>
                          <option>5</option>
                        </select>
                        <button type="submit">Send</button>
                      </form>
                    </div>
                <?php else: ?>
                    <span style="color:var(--muted);font-weight:600;">Feedback submitted</span>
                <?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="8" style="text-align:center;color:var(--muted)">No reports yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const themeToggle = document.getElementById('themeToggle');
themeToggle.addEventListener('click', ()=>{
  document.body.classList.toggle('light');
  const icon = themeToggle.querySelector('i');
  icon.classList.toggle('fa-moon');
  icon.classList.toggle('fa-sun');
});

setTimeout(()=>document.querySelectorAll('.flash').forEach(el=>{
  el.style.transition="opacity 1s"; el.style.opacity="0"; setTimeout(()=>el.remove(),1000);
}),8000);

function toggleFeedback(id){
  const box = document.getElementById('fb'+id);
  box.style.display = box.style.display === 'block' ? 'none' : 'block';
}
</script>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
var dhakaBounds = L.latLngBounds([23.65, 90.30], [23.90, 90.55]);
var map = L.map('map', {
  center: [23.78, 90.40],
  zoom: 12,
  maxBounds: dhakaBounds,
  maxBoundsViscosity: 1.0,
  minZoom: 11,
  maxZoom: 16
});
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);
<?php foreach($mapReports as $r): if(!empty($r['latitude']) && !empty($r['longitude'])): ?>
L.marker([<?= (float)$r['latitude']?>,<?= (float)$r['longitude']?>])
  .addTo(map).bindPopup("<b><?= addslashes($r['category']) ?></b><br><?= addslashes($r['description']) ?>");
<?php endif; endforeach; ?>
</script>
</body>
</html>
