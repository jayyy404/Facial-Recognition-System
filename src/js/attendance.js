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
  canvas.toBlob(blob => {
    const formdata = new FormData();
    formdata.append('image', blob);
  
    // Add communication with python server for this
    fetch('http://localhost:5001/recognize', {
      method: 'POST',
      body: formdata,
    })
      .then((res) => res.json())
      .then((data) => {
        console.warn(data);
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
