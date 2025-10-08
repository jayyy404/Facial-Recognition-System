<?php  
include "db.php";   

// Helper function to escape output 
function esc($s) {     
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); 
}  

// Fetch summary data
$totalUsersQuery = $conn->query("SELECT COUNT(*) AS total FROM users");
$totalUsers = $totalUsersQuery->fetch_assoc()['total'];

$totalRecognizedQuery = $conn->query("SELECT COUNT(*) AS total FROM attendance WHERE status='present'");
$totalRecognized = $totalRecognizedQuery->fetch_assoc()['total'];

$totalUnrecognizedQuery = $conn->query("SELECT COUNT(*) AS total FROM attendance WHERE status='unrecognized'");
$totalUnrecognized = $totalUnrecognizedQuery->fetch_assoc()['total'];
?> 

<!DOCTYPE html> 
<html lang="en"> 
<head>   
  <meta charset="UTF-8">   
  <title>Reports - FaceAttend</title>   
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">   
  <link rel="stylesheet" href="assets/style.css">   
  <style>     
    .reports-menu {
      display: flex;
      gap: 15px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }
    .reports-menu button {
      flex: 1;
      padding: 10px;
      border: none;
      border-radius: 8px;
      font-size: 14px;
      cursor: pointer;
      transition: all 0.3s ease;
      background: #1e5cff;
      color: #fff;
    }
    .reports-menu button:hover {
      background: #0366d6;
      transform: scale(1.05);
    }
    .report-section { display: none; }
    .report-section.active { display: block; }
    .report-card {
      background: linear-gradient(to bottom left, #1d4ed8, #1e3a8a, #082f49);
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 3px 6px rgba(0,0,0,0.1);
      color: #fff;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
    }
    th, td {
      border: 1px solid #ccc;
      padding: 8px; 
      text-align: center;
      color: #fff;
    }
    th { background: #024186; color: #fff; }
    input, select {
      margin-right: 10px;
      padding: 6px;
      border-radius: 5px;
      border: none;
    }
    .btn { 
      padding: 8px 12px;
      background: #1e5cff;
      color: #fff;
      border: none;
      border-radius: 6px;
      cursor:pointer;
    }

    /* Live date & time style (same as index.php) */
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
  <!-- Sidebar -->   
  <div class="sidebar">     
    <div class="brand">       
      <div class="logo">FR</div>       
      <div>         
        <h1>Face Attendance</h1>         
        <p>Facial Recognition System</p>       
      </div>     
    </div>     
    <div class="nav">       
      <a href="index.php"><i class="fa fa-home"></i> Dashboard</a>       
      <a href="users.php"><i class="fa fa-users"></i> Manage Users</a>       
      <a href="attendance.php"><i class="fa fa-calendar-check"></i> Attendance (Live)</a>       
      <a href="reports.php" class="active"><i class="fa fa-chart-bar"></i> Reports</a>       
      <a href="logs.php"><i class="fa fa-book"></i> Logs</a>       
      <a href="settings.php"><i class="fa fa-cog"></i> Settings</a>     
    </div>     
    <div class="footer">Logged in as <b>Admin</b></div>   
  </div>    

  <!-- Main Reports Content -->   
  <div class="main">     
    <h2>Reports</h2> 

    <!-- Live Date & Time -->
    <div class="datetime" id="datetime"></div>

    <div class="reports-menu">       
      <button onclick="showReport('monthly')">📅 Monthly Attendance</button>       
      <button onclick="showReport('custom')">⚙️ Custom Report</button>       
      <button onclick="showReport('summary')">📊 Summary</button>       
      <button onclick="showReport('exceptions')">🚨 Exceptions</button>       
      <button onclick="showReport('individual')">👤 Individual User</button>     
    </div>      

    <!-- Monthly Attendance --> 
    <div id="monthly" class="report-section active">   
      <div class="report-card">     
        <h3>Monthly Attendance Report</h3>     
        <p>Overview of attendance for each month by user.</p>     
        <?php     
        // Fetch all attendance grouped by month     
        $monthlyQuery = $conn->query("         
          SELECT u.id AS user_id, u.name, u.role, u.dept,                 
          DATE_FORMAT(a.date, '%Y-%m') AS month,                
          a.status         
          FROM attendance a         
          JOIN users u ON a.user_id=u.id         
          ORDER BY month DESC, u.name ASC     
        ");      

        if($monthlyQuery->num_rows > 0){         
          $currentMonth = '';         
          while($row = $monthlyQuery->fetch_assoc()){             
            if($row['month'] !== $currentMonth){                 
              if($currentMonth !== '') echo "</table><br>";                 
              $currentMonth = $row['month'];                 
              echo "<h4>".esc($currentMonth)."</h4>";                 
              echo "<table>                         
                <tr>                           
                  <th>User ID</th>                           
                  <th>Name</th>                           
                  <th>Role</th>                           
                  <th>Dept</th>                           
                  <th>Status</th>                         
                </tr>";             
            }             
            echo "<tr>                     
              <td>".esc($row['user_id'])."</td>                     
              <td>".esc($row['name'])."</td>                     
              <td>".esc($row['role'])."</td>                     
              <td>".esc($row['dept'])."</td>                     
              <td>".esc($row['status'])."</td>                   
            </tr>";         
          }         
          echo "</table>";     
        } else {         
          echo "<p>No attendance records found.</p>";     
        }     
        ?>   
      </div> 
    </div>      

    <div id="custom" class="report-section">   
      <div class="report-card">     
        <h3>Custom Report Generator</h3>     
        <form method="GET">       
          <label>From: <input type="date" name="from" required></label>       
          <label>To: <input type="date" name="to" required></label>       
          <button type="submit" class="btn">Generate</button>     
        </form>     
        <div id="customTable">     
        <?php     
        if(isset($_GET['from'], $_GET['to'])){       
          $from = $conn->real_escape_string($_GET['from']);       
          $to = $conn->real_escape_string($_GET['to']);       
          $customQuery = $conn->query("         
            SELECT a.date, u.id AS user_id, u.name, u.role, u.dept, a.status          
            FROM attendance a         
            JOIN users u ON a.user_id=u.id         
            WHERE a.date BETWEEN '$from' AND '$to'         
            ORDER BY a.date DESC, u.name ASC       
          ");        

          if($customQuery->num_rows > 0){         
            echo "<table>                 
              <tr>                   
                <th>Date</th>                   
                <th>User ID</th>                   
                <th>Name</th>                   
                <th>Role</th>                   
                <th>Dept</th>                   
                <th>Status</th>                 
              </tr>";         
            while($row = $customQuery->fetch_assoc()){           
              echo "<tr>                   
                <td>".esc($row['date'])."</td>                   
                <td>".esc($row['user_id'])."</td>                   
                <td>".esc($row['name'])."</td>                   
                <td>".esc($row['role'])."</td>                   
                <td>".esc($row['dept'])."</td>                   
                <td>".esc($row['status'])."</td>                 
              </tr>";         
            }         
            echo "</table>";       
          } else {         
            echo "<p>No attendance records found for the selected date range.</p>";       
          }     
        } else {       
          echo "<p>Select a date range to view attendance records.</p>";     
        }     
        ?>     
        </div>   
      </div> 
    </div>      

    <!-- Summary Report -->     
    <div id="summary" class="report-section">       
      <div class="report-card">         
        <h3>Attendance Summary</h3>         
        <ul>           
          <li>Total Registered Users: <b><?=esc($totalUsers)?></b></li>           
          <li>Total Recognized Logs: <b><?=esc($totalRecognized)?></b></li>           
          <li>Total Unrecognized Attempts: <b><?=esc($totalUnrecognized)?></b></li>         
        </ul>       
      </div>     
    </div>      

    <div id="exceptions" class="report-section">   
      <div class="report-card">     
        <h3>Exception Reports</h3>     
        <p>Unrecognized attempts and suspicious activities.</p>     
        <?php     
        $exceptions = $conn->query("         
          SELECT a.date, u.id AS user_id, u.name, u.role, u.dept, a.status          
          FROM attendance a         
          JOIN users u ON a.user_id=u.id         
          WHERE a.status='unrecognized'         
          ORDER BY a.date DESC, u.name ASC     
        ");      

        if($exceptions->num_rows > 0){       
          echo "<table>               
            <tr>                 
              <th>Date</th>                 
              <th>User ID</th>                 
              <th>Name</th>                 
              <th>Role</th>                 
              <th>Dept</th>                 
              <th>Status</th>               
            </tr>";       
          while($row = $exceptions->fetch_assoc()){         
            echo "<tr>                 
              <td>".esc($row['date'])."</td>                 
              <td>".esc($row['user_id'])."</td>                 
              <td>".esc($row['name'])."</td>                 
              <td>".esc($row['role'])."</td>                 
              <td>".esc($row['dept'])."</td>                 
              <td>".esc($row['status'])."</td>               
            </tr>";       
          }       
          echo "</table>";     
        } else {       
          echo "<p>No exceptions found.</p>";     
        }     
        ?>   
      </div> 
    </div>       

    <!-- Individual User Report -->     
    <div id="individual" class="report-section">       
      <div class="report-card">         
        <h3>Individual User Report</h3>         
        <form method="GET">           
          <label>Enter User ID: <input type="text" name="userId" placeholder="e.g. 2023001" required></label>           
          <button type="submit" class="btn">Search</button>         
        </form>         
        <div id="userReport">         
        <?php         
        if(isset($_GET['userId'])){             
          $uid = $conn->real_escape_string($_GET['userId']);                          
          // Check if user exists             
          $userCheck = $conn->query("SELECT * FROM users WHERE id='$uid'");                          
          if($userCheck->num_rows == 0){                 
            echo "<p>User ID <b>".esc($uid)."</b> does not exist.</p>";             
          } else {                 
            $userData = $userCheck->fetch_assoc();                                  
            // Check attendance                 
            $attendanceQuery = $conn->query("SELECT date, status FROM attendance WHERE user_id='$uid' ORDER BY date DESC");                                  
            if($attendanceQuery->num_rows == 0){                     
              echo "<p>User <b>".esc($userData['name'])."</b> exists but has no attendance records yet.</p>";                 
            } else {                     
              echo "<p>Attendance for <b>".esc($userData['name'])." ($uid)</b> - Role: ".esc($userData['role'])." / Dept: ".esc($userData['dept'])."</p>";                     
              echo "<table><tr><th>Date</th><th>Status</th></tr>";                     
              while($row = $attendanceQuery->fetch_assoc()){                         
                echo "<tr><td>".esc($row['date'])."</td><td>".esc($row['status'])."</td></tr>";                     
              }                     
              echo "</table>";                 
            }             
          }         
        }         
        ?>         
        </div>       
      </div>     
    </div>      

    <!-- Export -->     
    <div class="report-card">       
      <h3>Export Reports</h3>       
      <p>You can export attendance logs as CSV for offline use.</p>       
      <a href="export_csv.php" class="btn">Export CSV</a>     
    </div>    

  </div> 
</div>  

<script> 
function showReport(id) {   
  document.querySelectorAll(".report-section").forEach(div=>div.classList.remove("active"));   
  document.getElementById(id).classList.add("active");   
  window.scrollTo(0,0); 
}  

// Show default section based on GET params 
<?php 
if(isset($_GET['userId'])){   
  echo "showReport('individual');"; 
}elseif(isset($_GET['from'], $_GET['to'])){   
  echo "showReport('custom');"; 
} 
?> 

// Live Date & Time (with "at" and 2-digit hours)
function updateDateTime() {
  const now = new Date();
  const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
  const date = now.toLocaleDateString('en-US', options);
  const time = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
  document.getElementById("datetime").innerHTML = date + " at " + time;
}
setInterval(updateDateTime, 1000);
updateDateTime();
</script> 
</body> 
</html>
