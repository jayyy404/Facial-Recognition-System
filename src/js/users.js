import { checkIfAdminLoggedIn, confirmIfAdmin } from './admin';
import updateClock from './libs/clock';
import { $ } from './libs/element';

let users = [];
let capturedPhotos = [];

function loadUsers() {
  fetch('/api/get-state')
    .then((res) => res.json())
    .then((data) => {
      users = [...data.users];
      updateTable();
    });
}

function updateTable() {
  $('#userTable').replaceChildren(
    ...filterUsers().map((user) => {
      const tr = $.create('tr');

      tr.innerHTML = `
        <td>${user.name}</td>
        <td>${user.id}</td>
        <td>${user.role}</td>
      `;

      const editUserBtn = $.create('button');
      const deleteUserBtn = $.create('button');

      editUserBtn.onclick = () => editUser(user.id);
      deleteUserBtn.onclick = () => deleteUser(user.id);

      editUserBtn.innerHTML = 'Edit';
      deleteUserBtn.innerHTML = 'Delete';

      editUserBtn.className = 'btn';
      deleteUserBtn.className = 'btn secondary';

      const td = $.create('td');

      td.append(editUserBtn, deleteUserBtn);
      tr.append(td);

      return tr;
    })
  );
}

function filterUsers() {
  const searchValue = $('#searchInput').value.toLowerCase();

  return users.filter(
    (u) =>
      u.name.toLowerCase().includes(searchValue) ||
      u.id.toLowerCase().includes(searchValue) ||
      u.role.toLowerCase().includes(searchValue)
  );
}

async function saveUserData(e) {
  e.preventDefault();

  const userData = new FormData($('#user-form'));
  
  // Add the images here
  await Promise.all(capturedPhotos.map(async (uri, index) => {
    const mimetypeMatch = uri.match(/^data:(.*?);base64,/);
    const mimeType = mimetypeMatch ? mimetypeMatch[1] : 'application/octet-stream';

    const blob = await fetch(uri).then(res => res.blob());

    const file = new File(
      [blob], 
      `${userData.get('name')}-image-${index}`,
      {
        type: mimeType,
        lastModified: Date.now()
      }
    );

    userData.append('file[]', file, `${userData.get('name')}-image-${index}.png`);
  }));

  fetch('/api/update-user', {
    method: 'POST',
    body: userData,
  }).then((res) => {
    if (res.ok) {
      closeUserModal();
      loadUsers();
    }
  });
}

function addNewUser() {
  clearPhotos();
  clearForm();

  $('#modalTitle').innerText = `Add New User`;
  $('#userModal').style.removeProperty('display');

  startCamera();
}

function editUser(id) {
  clearPhotos();
  clearForm();
  startCamera();

  const user = users.find((user) => user.id === id);

  if (!user) return;

  $('#modalTitle').innerText = `Edit User`;
  $('#userModal').style.removeProperty('display');

  $('#uid').value = user.id;
  $('#uname').value = user.name;
  $('#urole').value = user.role;
  $('#udept').value = user.dept;
  $('#uusername').value = user.username;

  capturedPhotos = JSON.parse(user.photo || "[]");
  updatePreview();
}

function deleteUser(id) {
  if (!confirm('Delete this user?')) return;

  const formData = new FormData();
  formData.set('id', id);

  fetch('/api/delete-user', {
    method: 'POST',
    body: formData,
  }).then((res) => {
    if (res.ok) {
      loadUsers();
    }
  });
}

function closeUserModal() {
  stopCamera();
  $('#userModal').style.display = 'none';
}

function clearForm() {
  $('#user-form').reset();
}

// ------------------------------------------------------------------------------------
// Preview and camera
// ------------------------------------------------------------------------------------
function updatePreview() {
  const preview = $('#preview');

  if (capturedPhotos.length === 0) {
    preview.innerHTML = 'No photo';
  } else {

    preview.replaceChildren(
      ...capturedPhotos.map((p) => {
        const img = $.create('img');
        img.src = p;
        return img;
      })
    );
  }
}

let cameraStream;

async function startCamera() {
  if (cameraStream) return;

  cameraStream = await navigator.mediaDevices.getUserMedia({ video: true });
  $('#camera').srcObject = cameraStream;
}

function stopCamera() {
  if (!cameraStream) return;

  cameraStream.getTracks().forEach((track) => track.stop());
  cameraStream = undefined;
}

function capture() {
  const video = $('#camera');
  const canvas = $.create('canvas');
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;
  canvas.getContext('2d').drawImage(video, 0, 0);
  let photo = canvas.toDataURL('image/png');

  capturedPhotos.push(photo);
  updatePreview();
}

function clearPhotos() {
  capturedPhotos = [];
  updatePreview();
}

checkIfAdminLoggedIn($('#adminModal'), () => {
  loadUsers();
  updateClock();

  $('#open-user-modal').onclick = addNewUser;
  $('#searchInput').oninput = updateTable;

  $('#capture-photo').onclick = capture;
  $('#clear-photos').onclick = clearPhotos;

  $('#user-form').onsubmit = saveUserData;
  $('#close-modal').onclick = closeUserModal;
});
