<?php 
// ================== DATABASE CONNECTION ==================
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "facerecognition";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

function esc($s) {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// ================== BACKEND ACTIONS ==================
if (isset($_GET['action'])) {
  $action = $_GET['action'];

  if ($action === 'get_state') {
    $users = [];
    $logs = [];

    $res = $conn->query("SELECT * FROM users ORDER BY id DESC");
    while ($row = $res->fetch_assoc()) {
      $users[] = $row;
    }

    // ✅ Fetch all logs for analytics
    $res = $conn->query("SELECT * FROM logs ORDER BY time DESC");
    while ($row = $res->fetch_assoc()) {
      $logs[] = $row;
    }

    header("Content-Type: application/json");
    echo json_encode(["users" => $users, "logs" => $logs]);
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>FaceAttend - Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="assets/style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .main thead tr th {
      color: #fff;
      font-size: medium;
    }
    table th, table td {
      padding: 10px;
      border-bottom: 1px solid #d9d3d3ff;
      text-align: left;
      border-left: 1px solid #d9d3d3ff;
      border-right: 1px solid #d9d3d3ff;
      border-top: 1px solid #d9d3d3ff;
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
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.95);
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
      color: #04cde8ff;
      transform: scale(1.05);
      /* Slight zoom */
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

    .chart-container {
      margin-top: 20px;
      padding: 15px;
      background: #0d1117;
      border-radius: 12px;
    }

    /* Container to place chart left & users right */
    .row-container {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 20px;
      margin-top: 20px;
    }

    /* Chart takes 50% width */
    .chart-container {
      flex: 1;
      padding: 15px;
      background: #0d1117;
      border-radius: 12px;
    }
    /* Users table takes 50% width */
    .users-container {
      flex: 2;
      padding: 15px;
      background: #0d1117;
      border-radius: 12px;
      color: white;
      margin-top: 20px;
    }

    /* Live clock */
    .clock {
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
        <h1>System Attendance</h1>
        <p>TechNest Facial Recognition</p>
      </div>
    </div>
    <div class="nav">
      <a href="index.php" class="active restricted" data-section="Dashboard"><i class="fa fa-home"></i> Dashboard</a>
      <a href="users.php"><i class="fa fa-users"></i> Manage Users</a>
      <a href="attendance.php"><i class="fa fa-calendar-check"></i> Attendance (Live)</a>
      <a href="reports.php"><i class="fa fa-chart-bar"></i> Reports</a>
      <a href="logs.php"><i class="fa fa-book"></i> Logs</a>
      <a href="settings.php" class="restricted" data-section="Settings"><i class="fa fa-cog"></i> Settings</a>
    </div>
    <div class="footer">Logged in as <b>User</b></div>
  </div>

  <div class="main" id="dashboardContent" style="display:none;">
    <div class="topbar">
      <h2>Dashboard</h2>
      <div class="clock" id="liveClock"></div>
      <div class="avatar"><i class="fa fa-user"></i></div>
    </div>

    <!-- Stats Cards -->
    <div class="grid">
      <div class="card"><div class="stat"><div class="left"><h3 id="totalUsers">0</h3><p>Total Registered Users</p></div><i class="fa fa-users"></i></div></div>
      <div class="card"><div class="stat"><div class="left"><h3 id="todayAttendance">0</h3><p>Today's Attendance</p></div><i class="fa fa-check"></i></div></div>
      <div class="card"><div class="stat"><div class="left"><h3 id="unrecogAttempts">0</h3><p>Unrecognized Attempts</p></div><i class="fa fa-sad-tear"></i></div></div>
    </div>

    <!-- Attendance Rate -->
    <h2>Attendance Rate</h2>
    <div class="grid">
      <div class="card"><div class="stat"><div class="left"><h3 id="rateToday">0%</h3><p>Today</p></div></div></div>
      <div class="card"><div class="stat"><div class="left"><h3 id="rateWeek">0%</h3><p>This Week</p></div></div></div>
      <div class="card"><div class="stat"><div class="left"><h3 id="rateMonth">0%</h3><p>This Month</p></div></div></div>
    </div>

    <!-- Chart + Recent Users side by side -->
    <div class="row-container">
      <!-- Chart Section -->
      <div class="chart-container">
        <canvas id="attendanceChart"></canvas>
        <div style="text-align:center; margin-top:10px;">
          <button class="btn" onclick="setChartType('bar')">Bar View</button>
          <button class="btn" onclick="setChartType('line')">Line View</button>
          <button class="btn" onclick="downloadChart()">Download Chart</button>
        </div>
      </div>

      <!-- Recent Users Section -->
      <div class="users-container">
        <h2>Recent Users</h2>
        <table>
          <thead><tr><th>Name</th><th>ID</th><th>Role</th></tr></thead>
          <tbody id="userTable"></tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<!-- Admin Modal -->
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
let USERS = [], LOGS = [];
let redirectUrl = null;
let currentSection = null;
let attendanceChart;
let chartType = "bar";

async function loadState() {
  const res = await fetch("index.php?action=get_state");
  const data = await res.json();
  USERS = data.users;
  LOGS = data.logs;
  refreshDashboard();
}

function refreshDashboard() {
  document.getElementById("totalUsers").innerText = USERS.length;

  const today = new Date().toISOString().slice(0,10);
  const thisWeek = new Date(); thisWeek.setDate(thisWeek.getDate() - 7);
  const thisMonth = new Date(); thisMonth.setDate(thisMonth.getDate() - 30);

  let todayLogs = LOGS.filter(l=>l.time.startsWith(today));
  let weekLogs = LOGS.filter(l=>new Date(l.time) >= thisWeek);
  let monthLogs = LOGS.filter(l=>new Date(l.time) >= thisMonth);

  document.getElementById("todayAttendance").innerText = todayLogs.filter(l=>l.recognized==1).length;
  document.getElementById("unrecogAttempts").innerText = todayLogs.filter(l=>l.recognized==0).length;

  // Attendance Rates
  const totalToday = todayLogs.length;
  const totalWeek = weekLogs.length;
  const totalMonth = monthLogs.length;
  const rateToday = totalToday ? Math.round((todayLogs.filter(l=>l.recognized==1).length/totalToday)*100) : 0;
  const rateWeek = totalWeek ? Math.round((weekLogs.filter(l=>l.recognized==1).length/totalWeek)*100) : 0;
  const rateMonth = totalMonth ? Math.round((monthLogs.filter(l=>l.recognized==1).length/totalMonth)*100) : 0;

  document.getElementById("rateToday").innerText = rateToday + "%";
  document.getElementById("rateWeek").innerText = rateWeek + "%";
  document.getElementById("rateMonth").innerText = rateMonth + "%";

  // Update Chart
  updateChart([rateToday, rateWeek, rateMonth]);

  // Recent Users
  const tbody = document.getElementById("userTable");
  tbody.innerHTML = "";
  USERS.slice(0,5).forEach(u=>{
    let tr = document.createElement("tr");
    tr.innerHTML = `<td>${u.name}</td><td>${u.id}</td><td>${u.role}</td>`;
    tbody.appendChild(tr);
  });
}

function updateChart(data) {
  const ctx = document.getElementById("attendanceChart").getContext("2d");
  if (attendanceChart) attendanceChart.destroy();
  attendanceChart = new Chart(ctx, {
    type: chartType,
    data: {
      labels: ["Today", "This Week", "This Month"],
      datasets: [{
        label: "Attendance Rate (%)",
        data: data,
        backgroundColor: ["#1d4ed8","#10b981","#f59e0b"],
        borderColor: "#fff",
        borderWidth: 2,
        fill: true
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, max: 100 } }
    }
  });
}

function setChartType(type) {
  chartType = type;
  refreshDashboard();
}

function downloadChart() {
  const link = document.createElement('a');
  link.href = attendanceChart.toBase64Image();
  link.download = 'attendance_chart.png';
  link.click();
}

// Live Clock
function updateClock() {
  const now = new Date();
  const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
                    hour: '2-digit', minute: '2-digit', second: '2-digit' };
  document.getElementById("liveClock").innerText = now.toLocaleString('en-US', options);
}
setInterval(updateClock, 1000);
updateClock();

// Lock Sections
document.querySelectorAll(".restricted").forEach(link=>{
  link.addEventListener("click", function(e){
    e.preventDefault();
    redirectUrl = this.getAttribute("href");
    currentSection = this.dataset.section || "Admin Area";
    document.getElementById("modalTitle").innerText = `🔒 ${currentSection} - Admin Confirmation`;
    document.getElementById("adminPass").value = "";
    document.getElementById("adminModal").style.display = "flex";
  });
});

// Dashboard lock on load
window.onload = function() {
  redirectUrl = "index.php";
  currentSection = "Dashboard";
  document.getElementById("modalTitle").innerText = `🔒 Dashboard - Admin Confirmation`;
  document.getElementById("adminPass").value = "";
  document.getElementById("adminModal").style.display = "flex";

  // 🔄 Auto-refresh chart & stats every 60 seconds after unlock
  setInterval(() => {
    if(document.getElementById("dashboardContent").style.display === "block") {
      loadState();
    }
  }, 60000); // 60000ms = 1 minute
}

function closeModal() {
  document.getElementById("adminModal").style.display = "none";
  document.getElementById("adminPass").value = "";
  redirectUrl = null;
  currentSection = null;
}

function confirmAdmin() {
  const pass = document.getElementById("adminPass").value;
  if(pass === "12345") {
    document.getElementById("adminModal").style.display = "none";
    document.getElementById("adminPass").value = "";
    if(redirectUrl === "index.php") {
      document.getElementById("dashboardContent").style.display = "block";
      loadState();
    } else {
      window.location.href = redirectUrl;
    }
  } else {
    alert("Access denied. Invalid Admin password.");
  }
}
</script>
</body>
</html>
