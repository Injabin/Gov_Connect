<?php
session_start();
require_once "db_connect.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 🔹 Check if user is banned
$stmt = $pdo->prepare("SELECT is_banned, ban_until, ban_reason FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$is_banned = false;
$ban_message = "";
if ($user && $user['is_banned']) {
    $is_banned = true;
    if ($user['ban_until'] && strtotime($user['ban_until']) > time()) {
        $until = date("F j, Y, g:i a", strtotime($user['ban_until']));
        $ban_message = "⛔ You are temporarily banned until <b>$until</b>.";
    } elseif ($user['ban_until'] === null) {
        $ban_message = "⛔ Your account is permanently banned.";
    } else {
        // Ban expired — auto unban
        $pdo->prepare("UPDATE users SET is_banned = 0, ban_until = NULL, ban_reason = NULL WHERE user_id = ?")->execute([$user_id]);
        $is_banned = false;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Submit Problem — GovConnect</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --bg1:#0f172a;
    --bg2:#1e293b;
    --accent1:#667eea;
    --accent2:#764ba2;
    --danger:#e74c3c;
    --success:#27ae60;
    --muted:#94a3b8;
}
body {margin:0;font-family:'Inter',sans-serif;background:linear-gradient(135deg,var(--bg1),var(--bg2));color:#fff;}
a{text-decoration:none;}
.container{max-width:1100px;margin:40px auto;padding:0 24px;display:flex;flex-direction:column;gap:30px;}
.card{background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);backdrop-filter:blur(16px);border-radius:20px;padding:32px 40px;box-shadow:0 12px 35px rgba(0,0,0,0.5);}
h2{margin-top:0;margin-bottom:24px;font-size:35px;font-weight:700;color:#fff;}
h2 i{color:var(--accent1);}
.back-btn{display:inline-block;padding:12px 22px;border-radius:12px;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.12);color:#fff;font-weight:600;transition:.2s;}
.back-btn:hover{background:rgba(255,255,255,0.18);}
form{display:flex;flex-direction:column;gap:20px;}
.input-group{position:relative;}
.input-group i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#aaa;}
.input-group input,.input-group textarea{width:100%;padding:16px 16px 16px 44px;border-radius:12px;border:1px solid rgba(255,255,255,0.15);background:rgba(255,255,255,0.08);color:#fff;font-size:19px;box-sizing:border-box;transition:.2s;}
.input-group input:focus,.input-group textarea:focus{border-color:var(--accent1);outline:none;}
textarea{resize:vertical;height:140px;}
.input-group select{appearance:none;width:100%;padding:16px 16px 16px 44px;border-radius:12px;border:1px solid rgba(255,255,255,0.15);background:rgba(255,255,255,0.08);color:#fff;font-size:15px;cursor:pointer;transition:.2s;}
.input-group select:focus{border-color:var(--accent1);outline:none;}
.input-group select option{background:#1e293b;color:#fff;padding:10px 16px;}
.btn-primary,.btn-glass{display:block;width:100%;padding:16px;border-radius:12px;border:none;font-weight:600;cursor:pointer;transition:.2s;font-size:21px;}
.btn-primary{background:linear-gradient(90deg,var(--accent1),var(--accent2));color:#fff;}
.btn-primary:hover{transform:translateY(-3px);box-shadow:0 12px 25px rgba(0,0,0,0.4);}
.btn-glass{background:rgba(255,255,255,0.08);color:#fff;}
.btn-glass:hover{background:rgba(255,255,255,0.18);}
.file-upload{margin-top:10px;padding:25px;border:2px dashed rgba(255,255,255,0.25);border-radius:12px;text-align:center;color:#bbb;cursor:pointer;transition:.3s;position:relative;}
.file-upload:hover{border-color:var(--accent1);color:#fff;}
.file-upload input{display:none;}
.file-upload span{display:block;margin-top:10px;font-size:21px;color:#fff;}
#pickerMap{height:380px;border-radius:16px;display:none;margin-top:15px;border:1px solid rgba(255,255,255,0.1);}
@media (max-width:768px){.card{padding:24px 20px;}}
.ban-box{background:rgba(255,0,0,0.1);border:1px solid #e74c3c;padding:25px;border-radius:14px;text-align:center;font-size:18px;}
</style>
</head>
<body>

<div class="container">
<a href="user_dashboard.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>

<div class="card">
<?php if ($is_banned): ?>
    <h2><i class="fa-solid fa-ban"></i> Account Restricted</h2>
    <div class="ban-box">
        <?= $ban_message ?><br><br>
        <?php if (!empty($user['ban_reason'])): ?>
            <p><b>Reason:</b> <?= htmlspecialchars($user['ban_reason']) ?></p>
        <?php endif; ?>
        <form method="post" action="request_unban.php" style="margin-top:20px;">
            <input type="hidden" name="user_id" value="<?= $user_id ?>">
            <textarea name="appeal_message" placeholder="Explain why your account should be unbanned..." required
                      style="width:100%;height:120px;border-radius:10px;padding:10px;border:none;outline:none;margin-bottom:10px;"></textarea>
            <button type="submit" class="btn-primary"><i class="fa-solid fa-envelope-open-text"></i> Request Unban</button>
        </form>
    </div>
<?php else: ?>
    <h2><i class="fa-solid fa-bolt"></i> Submit New Problem</h2>

    <form method="POST" action="process_submit_problem.php" enctype="multipart/form-data">
        <div class="input-group">
            <i class="fa-solid fa-layer-group"></i>
            <select name="category" required>
                <option value="">Select Category</option>
                <option value="police">Police</option>
                <option value="medical">Medical</option>
                <option value="fire">Fire</option>
                <option value="gov">Government</option>
                <option value="other">Other</option>
            </select>
        </div>

        <div class="input-group">
            <i class="fa-solid fa-location-dot"></i>
            <input type="text" name="location_name" id="locationName" placeholder="Location" readonly required>
        </div>
        <div style="display:flex;gap:12px; flex-wrap:wrap;">
            <button type="button" class="btn-glass" id="useCurrent"><i class="fa-solid fa-location-crosshairs"></i> Use Current Location</button>
            <button type="button" class="btn-glass" id="pickLocation"><i class="fa-solid fa-map-pin"></i> Pick on Map</button>
        </div>
        <div id="pickerMap"></div>

        <div class="input-group">
            <i class="fa-solid fa-lightbulb"></i>
            <input type="text" name="suggestion" placeholder="Suggestion (optional)">
        </div>

        <div class="input-group">
            <i class="fa-solid fa-pen"></i>
            <textarea name="description" placeholder="Describe the issue..." required></textarea>
        </div>

        <label class="file-upload">
            <i class="fa-solid fa-photo-film"></i><br> Upload Images or Videos<br>
            <small>(You can select multiple files)</small>
            <input type="file" name="media_files[]" multiple accept="image/*,video/*" id="mediaInput">
            <span id="fileInfo">No files chosen</span>
        </label>

        <input type="hidden" name="latitude" id="latitude">
        <input type="hidden" name="longitude" id="longitude">

        <button type="submit" class="btn-primary"><i class="fa-solid fa-paper-plane"></i> Submit Report</button>
    </form>
<?php endif; ?>
</div>
</div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
// (Existing map + file input JS exactly same)
var dhakaBounds = L.latLngBounds([23.65,90.30],[23.90,90.55]);
let pickerMap,pickerMarker;
const pickerMapDiv=document.getElementById("pickerMap");
const pickBtn=document.getElementById("pickLocation");
const locName=document.getElementById("locationName");
const lat=document.getElementById("latitude");
const lon=document.getElementById("longitude");

document.getElementById("useCurrent")?.addEventListener("click",()=> {
    if(!navigator.geolocation){alert("Not supported");return;}
    navigator.geolocation.getCurrentPosition(async pos=>{
        lat.value=pos.coords.latitude; lon.value=pos.coords.longitude;
        try{
            const r=await fetch('resolve_location.php',{method:'POST',body:new URLSearchParams({lat:lat.value,lon:lon.value})});
            const j=await r.json(); locName.value=j.display_name||`${lat.value}, ${lon.value}`;
        }catch(e){locName.value=`${lat.value}, ${lon.value}`;}
    },()=>alert("Location error"));
});

pickBtn?.addEventListener("click",()=> {
    if(pickerMapDiv.style.display==="none"){
        pickerMapDiv.style.display="block";
        if(!pickerMap){
            pickerMap=L.map("pickerMap",{center:[23.78,90.40],zoom:12,minZoom:11,maxZoom:16,maxBounds:dhakaBounds,maxBoundsViscosity:1.0});
            L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",{attribution:"&copy; OpenStreetMap"}).addTo(pickerMap);
            pickerMap.on("click",async e=>{
                const la=e.latlng.lat,lo=e.latlng.lng;
                if(pickerMarker) pickerMap.removeLayer(pickerMarker);
                pickerMarker=L.marker([la,lo]).addTo(pickerMap);
                lat.value=la;lon.value=lo;
                try{
                    const r=await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${la}&lon=${lo}`);
                    const j=await r.json();locName.value=j.display_name||`${la}, ${lo}`;
                }catch{locName.value=`${la}, ${lo}`;}
            });
        } else pickerMap.invalidateSize();
    } else pickerMapDiv.style.display="none";
});

const mediaInput=document.getElementById("mediaInput");
const fileInfo=document.getElementById("fileInfo");
mediaInput?.addEventListener("change",()=>{
    const files=mediaInput.files;
    if(files.length===0) fileInfo.textContent="No files chosen";
    else if(files.length===1) fileInfo.textContent=files[0].name;
    else fileInfo.textContent=`${files.length} files selected`;
});
</script>
</body>
</html>
