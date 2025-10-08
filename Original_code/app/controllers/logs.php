<?php
// logs.php
// Logs page, fetching from index.php?action=get_state
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>FaceAttend - Logs</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="assets/style.css">
  <style>
    table th, table td {
      padding: 10px;
      border-bottom: 1px solid #d9d3d3ff;
      text-align: left;
      border-left: 1px solid #d9d3d3ff;
      border-right: 1px solid #d9d3d3ff;
      border-top: 1px solid #d9d3d3ff;
    }

    table thead tr th {
      color: #ffff;
      font-size: medium;
    }
    
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
      transition: all 0.3s ease;
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
      transform: scale(1.05);
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

    /* DateTime Style */
    .datetime {
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
        <p>Facial Recognition System</p>
      </div>
    </div>
    <div class="nav">
      <a href="index.php" class="restricted" data-section="Dashboard"><i class="fa fa-home"></i> Dashboard</a>
      <a href="users.php"><i class="fa fa-users"></i> Manage Users</a>
      <a href="attendance.php"><i class="fa fa-calendar-check"></i> Attendance (Live)</a>
      <a href="reports.php"><i class="fa fa-chart-bar"></i> Reports</a>
      <a href="logs.php" class="active restricted" data-section="Logs"><i class="fa fa-book"></i> Logs</a>
      <a href="settings.php" class="restricted" data-section="Settings"><i class="fa fa-cog"></i> Settings</a>
    </div>
    <div class="footer">Logged in as <b>User</b></div>
  </div>

  <div class="main" id="logsContent" style="display:none;">
    <div class="topbar">
      <div class="datetime" id="datetime"></div>
    </div>

    <h2>Attendance Logs</h2>
    <table>
      <thead>
        <tr>
          <th>Time</th>
          <th>Name</th>
          <th>ID</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody id="logsTable"></tbody>
    </table>
  </div>
</div>

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
let LOGS = [];
let redirectUrl = null;

async function loadLogs() {
  const res = await fetch("index.php?action=get_state");
  const data = await res.json();
  LOGS = data.logs;
  renderLogs();
}

function renderLogs() {
  const tbody = document.getElementById("logsTable");
  tbody.innerHTML = "";
  LOGS.forEach(log => {
    let statusText = "Unknown";
    if (log.recognized == 1) statusText = "Recognized";
    else if (log.recognized == 0) statusText = "Unrecognized";
    else if (log.recognized == 2) statusText = "New User Added";

    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>${log.time}</td>
      <td>${log.name}</td>
      <td>${log.user_id}</td>
      <td>${statusText}</td>
    `;
    tbody.appendChild(tr);
  });
}

// Lock on load
window.onload = function() {
  redirectUrl = "logs.php";
  document.getElementById("modalTitle").innerText = "🔒 Logs - Admin Confirmation";
  document.getElementById("adminPass").value = "";
  document.getElementById("adminModal").style.display = "flex";
  updateDateTime();
  setInterval(updateDateTime, 1000);
}

function closeModal() {
  document.getElementById("adminModal").style.display = "none";
  document.getElementById("adminPass").value = "";
  redirectUrl = null;
}

function confirmAdmin() {
  const pass = document.getElementById("adminPass").value;
  if(pass === "12345") { // Replace with real validation
    document.getElementById("adminModal").style.display = "none";
    document.getElementById("adminPass").value = "";
    document.getElementById("logsContent").style.display = "block";
    loadLogs();
  } else {
    alert("Access denied. Invalid Admin password.");
  }
}

// DateTime function
function updateDateTime() {
  const now = new Date();
  const options = { 
    weekday: 'long', 
    year: 'numeric', 
    month: 'long', 
    day: 'numeric', 
    hour: '2-digit', 
    minute: '2-digit', 
    second: '2-digit' 
  };
  document.getElementById("datetime").innerText = now.toLocaleDateString('en-US', options);
}
</script>
</body>
</html>
