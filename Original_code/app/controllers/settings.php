<?php
include "db.php"; 

// Load current settings
$sql = "SELECT * FROM settings WHERE id = 1";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $settings = $result->fetch_assoc();
} else {
    $conn->query("INSERT INTO settings (id) VALUES (1)");
    $settings = $conn->query("SELECT * FROM settings WHERE id = 1")->fetch_assoc();
}

// Fetch roles
$roles = [];
$r = $conn->query("SELECT * FROM roles ORDER BY role_name ASC");
while ($row = $r->fetch_assoc()) {
    $roles[] = $row;
}

// Handle updates
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['save_system'])) {
        $system_name = $_POST['system_name'];
        $institution = $_POST['institution'];
        $timezone = $_POST['timezone'];
        $datetime_format = $_POST['datetime_format'];
        $conn->query("UPDATE settings SET system_name='$system_name', institution='$institution', timezone='$timezone', datetime_format='$datetime_format' WHERE id=1");
        $msg = "System configuration updated successfully!";
    }
    if (isset($_POST['update_account'])) {
        $username = $_POST['admin_username'];
        $role = $_POST['default_role'];
        $policy = $_POST['password_policy'];
        $conn->query("UPDATE settings SET admin_username='$username', default_role='$role', password_policy='$policy' WHERE id=1");
        $msg = "Account & Roles updated successfully!";
    }
    if (isset($_POST['apply_attendance'])) {
        $camera = $_POST['default_camera'];
        $sensitivity = $_POST['recognition_sensitivity'];
        $samples = $_POST['samples_per_user'];
        $cutoff = $_POST['cutoff_time'];
        $conn->query("UPDATE settings SET default_camera='$camera', recognition_sensitivity='$sensitivity', samples_per_user='$samples', cutoff_time='$cutoff' WHERE id=1");
        $msg = "Attendance settings updated!";
    }
    if (isset($_POST['save_security'])) {
        $timeout = $_POST['session_timeout'];
        $access = $_POST['access_level'];
        $conn->query("UPDATE settings SET session_timeout='$timeout', access_level='$access' WHERE id=1");
        $msg = "Security settings updated!";
    }
    if (isset($_POST['backup_now'])) {
        $timestamp = date("Y-m-d H:i:s");
        $conn->query("UPDATE settings SET last_backup='$timestamp' WHERE id=1");
        $msg = "Database backup timestamp updated!";
    }
    header("Location: settings.php?msg=" . urlencode($msg));
    exit;
}

$notif = isset($_GET['msg']) ? $_GET['msg'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Settings - TechNest Recognition</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="assets/style.css">
<style>
/* ====== Settings Styles ====== */
.settings-section { 
    background:#0d1117; 
    padding:20px; 
    border-radius:10px;
    margin-bottom:20px;
    border:1px solid #333;
}
.settings-section h3 {
    margin-top:0;
}
.form-row {
    display:flex;
    gap:15px;
    margin-bottom:15px;
}
.form-row div {
    flex:1;
}
.form-row input, .form-row select {
    width:100%;
    padding:8px;
    border:1px solid #555;
    border-radius:8px;
    background:#161b22;
    color:#fff;
}
.btn {
    padding:8px 15px;
    border-radius:6px;
    cursor:pointer;
}
.alert {
    padding:10px;
    background:#198754;
    color:#fff;
    border-radius:6px;
    margin-bottom:15px;
}

/* ====== Admin Modal ====== */
.modal {
    display: none;
    position: fixed;
    top:0; left:0;
    width:100%; height:100%;
    background: rgba(0,0,0,0.6);
    justify-content: center;
    align-items: center;
    z-index: 9999;
}
.modal-content {
    background: linear-gradient(to bottom left, #172554, #0c4a6e, #1e40af);
    border-radius: 12px;
    padding: 25px;
    width: 390px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
    animation: fadeIn .3s ease;
}
.modal-content h3 {
    margin: 0 0 10px;
    font-size: 20px;
    font-weight: 600;
    color: #ffff;
}
.modal-content p {
    margin: 5px 0 15px;
    font-size: 14px;
    color: #ffff;
}
.modal-content input:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 5px rgba(0,123,255,0.5);
}
.modal-content input {
    width: 100%;
    padding: 10px;
    margin-bottom: 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
}
.modal-actions {
    display: flex;
    justify-content: space-between;
}
.btn {
    flex: 1;
    margin: 0 5px;
    padding: 10px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease; /* smooth hover animation */
}
.btn-confirm {
    background: #03366dff;
    color: #fff;
}
.btn-cancel {
    background: #03366dff;
    color: #fff;
}
.btn:hover {
    opacity: 0.9;
    transform: scale(1.05); /* Slight zoom */
}
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* ====== Live Date/Time (same as logs.php) ====== */
.datetime-box {
      position: fixed;
      top: 55px;
      left: 780px;
      font-size: 14px;
      color: #fff;
      background: #172554;
      padding: 6px 14px;
      border-radius: 8px;
      font-weight: 500;
      z-index: 1000;
      text-align: center;
}
</style>
</head>
<body>
<div class="app">
<div class="sidebar">
  <div class="brand">
    <div class="logo">FR</div>
    <div>
      <h1>Face Attendance</h1>
      <p>Facial Recognition IS</p>
    </div>
  </div>
  <div class="nav">
    <a href="index.php"><i class="fa fa-home"></i> Dashboard</a>
    <a href="users.php"><i class="fa fa-users"></i> Manage Users</a>
    <a href="attendance.php"><i class="fa fa-calendar-check"></i> Attendance (Live)</a>
    <a href="reports.php"><i class="fa fa-chart-bar"></i> Reports</a>
    <a href="logs.php"><i class="fa fa-book"></i> Logs</a>
    <a href="settings.php" class="active"><i class="fa fa-cog"></i> Settings</a>
  </div>
  <div class="footer">Logged in as <b>Admin</b></div>
</div>


<!-- ====== Wrap main content to hide until unlocked ====== -->
<div class="main" id="settingsContent" style="display:none;">
  <div class="topbar">

    <div class="datetime-box">
      <span id="liveDate"></span>
      <span id="liveTime"></span>
    </div>
  </div>

<h2>Settings</h2>
<?php if ($notif): ?>
<div class="alert"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($notif) ?></div>
<?php endif; ?>

<form method="POST" class="settings-section"> 
<h3><i class="fa fa-cogs"></i> System Configuration</h3>
<div class="form-row">
<div>
<label>System Name</label>
<input type="text" name="system_name" value="<?= $settings['system_name'] ?>">
</div>
<div>
<label>Institution / Organization</label>
<input type="text" name="institution" value="<?= $settings['institution'] ?>">
</div>
</div>
<div class="form-row">
<div>
<label>Timezone</label>
<select name="timezone">
<option <?= $settings['timezone']=="Asia/Manila"?"selected":"" ?>>Asia/Manila</option>
<option <?= $settings['timezone']=="UTC"?"selected":"" ?>>UTC</option>
<option <?= $settings['timezone']=="America/New_York"?"selected":"" ?>>America/New_York</option>
<option <?= $settings['timezone']=="Europe/London"?"selected":"" ?>>Europe/London</option>
</select>
</div>
<div>
<label>Date/Time Format</label>
<select name="datetime_format">
<option <?= $settings['datetime_format']=="Y-m-d H:i"?"selected":"" ?>>YYYY-MM-DD HH:MM</option>
<option <?= $settings['datetime_format']=="d/m/Y H:i"?"selected":"" ?>>DD/MM/YYYY HH:MM</option>
<option <?= $settings['datetime_format']=="m/d/Y h:i A"?"selected":"" ?>>MM/DD/YYYY hh:mm AM/PM</option>
</select>
</div>
</div>
<button class="btn" name="save_system">Save</button>
</form>

<form method="POST" class="settings-section"> 
<h3><i class="fa fa-user-shield"></i> Account & Roles</h3>
<div class="form-row">
<div>
<label>Admin Username</label>
<input type="text" name="admin_username" value="<?= $settings['admin_username'] ?>">
</div>
<div>
<label>Default Role</label>
<select name="default_role">
<?php foreach ($roles as $r): ?>
<option <?= $settings['default_role']==$r['role_name']?"selected":"" ?>><?= $r['role_name'] ?></option>
<?php endforeach; ?>
</select>
</div>
</div>
<div class="form-row">
<div>
<label>Password Policy</label>
<select name="password_policy">
<option <?= $settings['password_policy']=="Normal"?"selected":"" ?>>Normal</option>
<option <?= $settings['password_policy']=="Strong (8+ chars, symbols)"?"selected":"" ?>>Strong (8+ chars, symbols)</option>
</select>
</div>
</div>
<button class="btn" name="update_account">Update</button>
</form>

<form method="POST" class="settings-section"> 
<h3><i class="fa fa-calendar-check"></i> Attendance Settings</h3>
<div class="form-row">
<div>
<label>Default Camera</label>
<select name="default_camera">
<option <?= $settings['default_camera']=="Built-in Webcam"?"selected":"" ?>>Built-in Webcam</option>
<option <?= $settings['default_camera']=="External USB Camera"?"selected":"" ?>>External USB Camera</option>
<option <?= $settings['default_camera']=="IP Camera"?"selected":"" ?>>IP Camera</option>
</select>
</div>
<div>
<label>Recognition Sensitivity</label>
<select name="recognition_sensitivity">
<option <?= $settings['recognition_sensitivity']=="Low"?"selected":"" ?>>Low</option>
<option <?= $settings['recognition_sensitivity']=="Medium"?"selected":"" ?>>Medium</option>
<option <?= $settings['recognition_sensitivity']=="High"?"selected":"" ?>>High</option>
</select>
</div>
</div>
<div class="form-row">
<div>
<label>Samples per User</label>
<input type="number" name="samples_per_user" min="1" max="20" value="<?= $settings['samples_per_user'] ?>">
</div>
<div>
<label>Attendance Cut-off Time</label>
<input type="time" name="cutoff_time" value="<?= $settings['cutoff_time'] ?>">
</div>
</div>
<button class="btn" name="apply_attendance">Apply</button>
</form>

<form method="POST" class="settings-section"> 
<h3><i class="fa fa-database"></i> Database & Backup</h3>
<div class="form-row">
<div>
<label>Database Status</label>
<input type="text" value="Connected" readonly>
</div>
<div>
<label>Last Backup</label>
<input type="text" value="<?= $settings['last_backup'] ?: 'Not yet' ?>" readonly>
</div>
</div>
<button class="btn" name="backup_now">Backup Now</button>
<button class="btn secondary" disabled>Restore</button>
</form>

<form method="POST" class="settings-section"> 
<h3><i class="fa fa-lock"></i> Security</h3>
<div class="form-row">
<div>
<label>Session Timeout (minutes)</label>
<input type="number" name="session_timeout" min="1" value="<?= $settings['session_timeout'] ?>">
</div>
<div>
<label>Access Level</label>
<select name="access_level">
<option <?= $settings['access_level']=="Admin Only"?"selected":"" ?>>Admin Only</option>
</select>
</div>
</div>
<button class="btn" name="save_security">Save Security</button>
</form>

<div class="settings-section">
<h3><i class="fa fa-info-circle"></i> About</h3>
<p><b>TechNest Recognition System</b> v1.0</p>
<p>Developed by Team TechNest, 2025</p>
<p>Capstone Project — Facial Recognition Information System</p>
</div>
</div>
</div>

<!-- ====== Admin Modal for Settings ====== -->
<div class="modal" id="adminModal">
  <div class="modal-content">
    <h3 id="modalTitle">🔒 Admin Confirmation</h3>
    <p>Please enter Admin password to continue.</p>
    <input type="password" id="adminPass" placeholder="Enter password">
    <div class="modal-actions">
      <button class="btn btn-confirm" onclick="confirmAdmin()">Unlock</button>
      <button class="btn btn-cancel" onclick="closeModal()">Cancel</button>
    </div>
  </div>
</div>

<script>
// Use the same password as Dashboard
const ADMIN_PASSWORD = "12345";

// Live DateTime updater
function updateDateTime() {
  const now = new Date();
  const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
  document.getElementById("liveDate").innerText = now.toLocaleDateString(undefined, options) + " at ";

  // ✅ Updated block: 12-hour format with leading zeros + AM/PM
  let hours = now.getHours();
  let minutes = now.getMinutes().toString().padStart(2, "0");
  let seconds = now.getSeconds().toString().padStart(2, "0");
  let ampm = hours >= 12 ? "PM" : "AM";
  hours = hours % 12;
  hours = hours ? hours : 12; 
  hours = hours.toString().padStart(2, "0");
  document.getElementById("liveTime").innerText = `${hours}:${minutes}:${seconds} ${ampm}`;
}
setInterval(updateDateTime, 1000);
updateDateTime();

window.onload = function() {
    document.getElementById("settingsContent").style.display = "none";
    document.getElementById("modalTitle").innerText = "🔒 Settings - Admin Confirmation";
    document.getElementById("adminPass").value = "";
    document.getElementById("adminModal").style.display = "flex";
}

function closeModal() {
    document.getElementById("adminModal").style.display = "none";
    document.getElementById("adminPass").value = "";
}

function confirmAdmin() {
    const pass = document.getElementById("adminPass").value;
    if(pass === ADMIN_PASSWORD) {
        document.getElementById("adminModal").style.display = "none";
        document.getElementById("adminPass").value = "";
        document.getElementById("settingsContent").style.display = "block";
    } else {
        alert("Access denied. Invalid Admin password.");
    }
}
</script>
</body>
</html>
