<?php include "db.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Attendance (Live) - FaceAttend</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="assets/style.css">
  <style>
    /* Live clock (match index.php & users.php style) */
    #clock {
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

    .attendance-container {
      display: flex;
      gap: 20px;
      margin-top: 20px;
    }
    .camera-box {
      flex: 2;
      background: #111827;
      border-radius: 12px;
      padding: 10px;
      text-align: center;
    }
    .camera-box video {
      width: 100%;
      border-radius: 12px;
      background: black;
    }
    .controls {
      margin-top: 10px;
    }
    .log-box {
      flex: 1;
      background: #1f2937;
      border-radius: 12px;
      padding: 15px;
      color: #fff;
    }
    .log-list {
      max-height: 400px;
      overflow-y: auto;
    }
  </style>
</head>
<body>
<div class="app">
  <!-- Sidebar -->
  <div class="sidebar">
    <div class="brand">
      <div class="logo">FR</div>
      <div>
        <h1>System Attendance</h1>
        <p>Facial Recognition System</p>
      </div>
    </div>
    <div class="nav">
      <a href="index.php"><i class="fa fa-home"></i> Dashboard</a>
      <a href="users.php"><i class="fa fa-users"></i> Manage Users</a>
      <a href="attendance.php" class="active"><i class="fa fa-calendar-check"></i> Attendance (Live)</a>
      <a href="reports.php"><i class="fa fa-chart-bar"></i> Reports</a>
      <a href="logs.php"><i class="fa fa-book"></i> Logs</a>
      <a href="settings.php"><i class="fa fa-cog"></i> Settings</a>
    </div>
    <div class="footer">Logged in as <b>Admin</b></div>
  </div>

  <!-- Main -->
  <div class="main">
    <div class="topbar">
      <h2>Live Attendance</h2>
      <div class="top-actions">
        <span id="clock"></span>
      </div>
    </div>

    <div class="attendance-container">
      <!-- Camera Section -->
      <div class="camera-box">
        <video id="cameraFeed" autoplay muted playsinline></video>
        <div class="controls">
          <button class="btn" onclick="startCamera()"><i class="fa fa-play"></i> Start</button>
          <button class="btn secondary" onclick="stopCamera()"><i class="fa fa-stop"></i> Stop</button>
        </div>
      </div>

      <!-- Recognition Log -->
      <div class="log-box">
        <h3>Recognition Log</h3>
        <div class="log-list" id="recognitionLog">
          <?php
          $res = $conn->query("SELECT * FROM logs ORDER BY time DESC LIMIT 10");
          while($row = $res->fetch_assoc()) {
            $status = $row['recognized'] ? "✅ Recognized" : "❌ Unrecognized";
            echo "<p><b>{$row['time']}</b> - {$row['name']} ({$row['user_id']}) - $status</p>";
          }
          ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
let stream;

// Camera control
async function startCamera() {
  try {
    stream = await navigator.mediaDevices.getUserMedia({ video: true });
    document.getElementById("cameraFeed").srcObject = stream;
  } catch (err) {
    alert("Error accessing camera: " + err);
  }
}

function stopCamera() {
  if (stream) {
    let tracks = stream.getTracks();
    tracks.forEach(track => track.stop());
    document.getElementById("cameraFeed").srcObject = null;
  }
}

// Live Clock (with "at" + 2-digit hour like index.php)
function updateClock() {
  const now = new Date();
  const options = { weekday: "long", year: "numeric", month: "long", day: "numeric" };

  let hours = now.getHours();
  let minutes = now.getMinutes();
  let seconds = now.getSeconds();
  let ampm = hours >= 12 ? "PM" : "AM";

  hours = hours % 12;
  hours = hours ? hours : 12; // 0 should be 12
  hours = String(hours).padStart(2, "0");
  minutes = String(minutes).padStart(2, "0");
  seconds = String(seconds).padStart(2, "0");

  document.getElementById("clock").textContent =
    now.toLocaleDateString("en-US", options) +
    " at " + hours + ":" + minutes + ":" + seconds + " " + ampm;
}

setInterval(updateClock, 1000);
updateClock();
</script>
</body>
</html>
