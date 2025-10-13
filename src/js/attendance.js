import { $ } from './libs/element';
import updateClock from './libs/clock';

fetch('/api/get-state')
  .then((res) => res.json())
  .then(({ logs }) => {
    $('#recognitionLog').replaceChildren(
      ...logs.map((log) => {
        const p = $.create('p');
        p.innerHTML = `<strong>${log.time}</strong> - ${log.name} ${
          log.user_id
        } - ${log.recognized ? '✅ Recognized' : '❌ Unrecognized'}`;

        return p;
      })
    );
  });

updateClock();

let stream;
let interval;

/** @type {HTMLVideoElement} */
const video = $('#cameraFeed');

// Camera control
async function startCamera() {
  if (stream) return;

  $('#recognitionLog').innerHTML = '';

  try {
    stream = await navigator.mediaDevices.getUserMedia({ video: true });
    video.srcObject = stream;
    interval = setInterval(capture, 1000);
  } catch (err) {
    alert('Error accessing camera: ' + err);
  }
}

function capture() {
  const canvas = $.create('canvas');
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;

  canvas.getContext('2d').drawImage(video, 0, 0);
  canvas.toBlob((blob) => {
    const formdata = new FormData();
    formdata.append('image', blob);

    // Add communication with python server for this
    fetch('http://localhost:5001/recognize', {
      method: 'POST',
      body: formdata,
    })
      .then((res) => res.json())
      .then((data) => {
        stopCamera();
        // console.warn(data);

        if (data.status === 'success') {
          const name = $.create('h2');
          const id = $.create('p');
          const role = $.create('p');

          name.innerHTML = `Name: ${data.name}`;
          id.innerHTML = `ID: ${data.id}`;
          role.innerHTML = `Role: ${data.role}`;

          const message = $.create('p');
          message.style.marginTop = 'auto';
          message.innerHTML = `Successfully registered!`;

          $('#recognitionLog').replaceChildren(name, id, role, message);

          const formdata = new FormData();
          formdata.append('user_id', data.id);
          formdata.append('status', 'present');

          fetch('/api/add-to-attendance', {
            method: 'POST',
            body: formdata,
          }).then((res) => {
            if (!res.ok) {
              console.error(`Error logging user attendance: ${res.statusText}`);
              return;
            }
          });
        }

        else if (data.status === 'forbidden') {
          const name = $.create('h2');
          name.innerHTML = 'Unrecognized User Found';

          const message = $.create('p');
          message.style.marginTop = 'auto';
          message.innerHTML = `Double-check if you are in the class list.`;

          $('#recognitionLog').replaceChildren(name, message);

          const formdata = new FormData();
          formdata.append('user_id', -1);
          formdata.append('status', 'unrecognized');


          fetch('/api/add-to-attendance', {
            method: 'POST',
            body: formdata,
          }).then((res) => {
            if (!res.ok) {
              console.error(`Error logging user attendance: ${res.statusText}`);
              return;
            }
          });
        }
      });
  });
}

function stopCamera() {
  if (!stream) return;

  let tracks = stream.getTracks();
  tracks.forEach((track) => track.stop());
  video.srcObject = null;

  stream = undefined;

  clearInterval(interval);
  interval = undefined;
}

$('#start-camera').onclick = startCamera;
$('#stop-camera').onclick = stopCamera;
