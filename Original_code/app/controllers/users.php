<?php include "db.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>FaceAttend - Manage Users</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .form-row {
      display: flex;
      gap: 15px;
      margin-bottom: 10px;
    }
    .form-row div {
      flex: 1;
    }
    .camera-box, .preview-box {
      border: 1px solid #444;
      padding: 10px;
      border-radius: 10px;
      background: #0d1117;
    }
    .camera-box video, .preview-box img {
      width: 100%;
      border-radius: 10px;
    }
    .btn-group {
      margin-top: 10px;
      display: flex;
      gap: 10px;
    }
    .search-bar {
      margin: 10px 0;
    }
    .search-bar input {
      width: 250px;
      padding: 8px;
      border-radius: 8px;
      border: 1px solid #eae3e3ff;
    }
    .modal h2 span {
      font-size: 16px;
      color: #e1ddddff;
      font-weight: normal;
    }

    /* Table */
    table {
      width: 100%;
      border-collapse: collapse;
    }
    table thead {
      background: #083667ff;
    }
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
    table tr:hover {
      background: #0d3874ff;
    }

    /* Modal */
    .modal-backdrop {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.6);
      align-items: center;
      justify-content: center;
      z-index: 1000;
    }
    .modal {
      background: #161b22;
      padding: 20px;
      border-radius: 12px;
      width: 800px;
      max-height: 90vh;
      overflow-y: auto;
    }

    /* Scrollbar styling */
    .modal::-webkit-scrollbar {
      width: 8px;
    }
    .modal::-webkit-scrollbar-track {
      background: #0d1117;
      border-radius: 10px;
    }
    .modal::-webkit-scrollbar-thumb {
      background: #444;
      border-radius: 10px;
    }
    .modal::-webkit-scrollbar-thumb:hover {
      background: #666;
    }

    .btn {
      background-color: #007bff;
      color: white;
      border: none;
      padding: 5px 12px;
      border-radius: 5px;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    .btn:hover {
      background-color: #0056b3;
      transform: scale(1.05);
    }
    .btn.secondary {
      background-color: #dc3545;
      color: white;
    }
    .btn.secondary:hover {
      background-color: #a71d2a;
      transform: scale(1.05);
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
        <a href="users.php" class="active"><i class="fa fa-users"></i> Manage Users</a>
        <a href="attendance.php"><i class="fa fa-calendar-check"></i> Attendance (Live)</a>
        <a href="reports.php"><i class="fa fa-chart-bar"></i> Reports</a>
        <a href="logs.php"><i class="fa fa-book"></i> Logs</a>
        <a href="settings.php"><i class="fa fa-cog"></i> Settings</a>
      </div>
      <div class="footer">Logged in as <b>Admin</b></div>
    </div>

    <!-- Main -->
    <div class="main">
      <div class="topbar">
        <h2>Manage Users</h2>
        <div class="clock" id="liveClock"></div> <!-- ✅ Clock -->
        <div class="top-actions">
          <button class="btn" onclick="openUserModal()">+ Add User</button>
        </div>
      </div>

      <!-- Search -->
      <div class="search-bar">
        <input type="text" id="searchInput" placeholder="Search by name, ID, or role..." onkeyup="filterUsers()">
      </div>

      <!-- Users Table -->
      <table>
        <thead><tr><th>Name</th><th>ID</th><th>Role</th><th>Action</th></tr></thead>
        <tbody id="userTable"></tbody>
      </table>
    </div>
  </div>

  <!-- Modal -->
  <div class="modal-backdrop" id="userModal">
    <div class="modal">
      <h2 id="modalTitle">Add New User</h2>

      <div class="form-row">
        <div><label>Full Name</label><input id="uname"></div>
        <div><label>Role</label>
          <select id="urole">
            <option>Student</option>
            <option>Employee</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div><label>Student/Employee ID</label><input id="uid"></div>
        <div><label>Course/Dept</label><input id="udept"></div>
      </div>

      <div class="form-row">
        <div><label>Username</label><input id="uusername"></div>
        <div><label>Password</label><input id="upassword" type="password"></div>
      </div>

      <!-- Camera & Preview -->
      <div class="form-row">
        <div class="camera-box" style="flex:2;">
          <label>Camera</label>
          <video id="camera" autoplay></video>
          <div class="btn-group">
            <button class="btn" onclick="capturePhoto()">Capture</button>
            <button class="btn secondary" onclick="clearPhotos()">Clear</button>
          </div>
        </div>
        <div class="preview-box" style="flex:1;">
          <label>Preview</label>
          <div id="preview">No photo</div>
        </div>
      </div>

      <div class="btn-group">
        <button class="btn" onclick="saveUser()">Save User</button>
        <button class="btn secondary" onclick="closeUserModal()">Cancel</button>
      </div>
    </div>
  </div>

  <script>
    let USERS = [];
    let capturedPhotos = [];
    let editingUserId = null;

    async function loadUsers() {
      const res = await fetch("index.php?action=get_state");
      const data = await res.json();
      USERS = data.users;
      refreshTable();
    }

    function refreshTable(filteredUsers = USERS) {
      const tbody = document.getElementById("userTable");
      tbody.innerHTML = "";
      filteredUsers.forEach(u=>{
        let tr = document.createElement("tr");
        tr.innerHTML = `
          <td>${u.name}</td>
          <td>${u.id}</td>
          <td>${u.role}</td>
          <td>
            <button class="btn" onclick="editUser('${u.id}')">Edit</button>
            <button class="btn secondary" onclick="deleteUser('${u.id}')">Delete</button>
          </td>`;
        tbody.appendChild(tr);
      });
    }

    function filterUsers() {
      const searchValue = document.getElementById("searchInput").value.toLowerCase();
      const filtered = USERS.filter(u =>
        u.name.toLowerCase().includes(searchValue) ||
        u.id.toLowerCase().includes(searchValue) ||
        u.role.toLowerCase().includes(searchValue)
      );
      refreshTable(filtered);
    }

    async function saveUser() {
      let user = {
        id: document.getElementById("uid").value,
        name: document.getElementById("uname").value,
        role: document.getElementById("urole").value,
        dept: document.getElementById("udept").value,
        username: document.getElementById("uusername").value,
        password: document.getElementById("upassword").value,
        photos: capturedPhotos
      };

      await fetch("index.php?action=save_user", {
        method:"POST",
        body: JSON.stringify(user)
      });

      closeUserModal();
      loadUsers();
    }

    async function deleteUser(id) {
      if (!confirm("Delete this user?")) return;
      await fetch("index.php?action=delete_user&id=" + encodeURIComponent(id));
      loadUsers();
    }

    function openUserModal() { 
      editingUserId = null;
      document.getElementById("modalTitle").innerText = "Add New User";
      clearForm();
      document.getElementById("userModal").style.display="flex"; 
      startCamera();
    }

    function closeUserModal() { 
      document.getElementById("userModal").style.display="none"; 
      stopCamera();
      clearPhotos();
    }

    function clearForm() {
      document.getElementById("uid").value = "";
      document.getElementById("uname").value = "";
      document.getElementById("urole").value = "Student";
      document.getElementById("udept").value = "";
      document.getElementById("uusername").value = "";
      document.getElementById("upassword").value = "";
    }

    function editUser(id) {
      const user = USERS.find(u => u.id === id);
      if (!user) return;

      editingUserId = id;
      document.getElementById("modalTitle").innerText = "Edit User";
      document.getElementById("uid").value = user.id;
      document.getElementById("uname").value = user.name;
      document.getElementById("urole").value = user.role;
      document.getElementById("udept").value = user.dept;
      document.getElementById("uusername").value = user.username;
      document.getElementById("upassword").value = user.password;

      capturedPhotos = user.photos || [];
      updatePreview();

      document.getElementById("userModal").style.display="flex";
      startCamera();
    }

    // Camera
    let stream;
    async function startCamera() {
      stream = await navigator.mediaDevices.getUserMedia({ video: true });
      document.getElementById("camera").srcObject = stream;
    }
    function stopCamera() {
      if (stream) {
        stream.getTracks().forEach(track => track.stop());
      }
    }

    function capturePhoto() {
      const video = document.getElementById("camera");
      const canvas = document.createElement("canvas");
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      canvas.getContext("2d").drawImage(video, 0, 0);
      let photo = canvas.toDataURL("image/png");
      capturedPhotos.push(photo);
      updatePreview();
    }

    function clearPhotos() {
      capturedPhotos = [];
      updatePreview();
    }

    function updatePreview() {
      const preview = document.getElementById("preview");
      preview.innerHTML = "";
      if (capturedPhotos.length === 0) {
        preview.innerHTML = "No photo";
      } else {
        capturedPhotos.forEach(p => {
          let img = document.createElement("img");
          img.src = p;
          preview.appendChild(img);
        });
      }
    }

    // ✅ Live Clock
    function updateClock() {
      const now = new Date();
      const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
                        hour: '2-digit', minute: '2-digit', second: '2-digit' };
      document.getElementById("liveClock").innerText = now.toLocaleString('en-US', options);
    }
    setInterval(updateClock, 1000);
    updateClock();

    loadUsers();
  </script>
</body>
</html>
