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

// Camera control
async function startCamera() {
  if (stream) return;

  try {
    stream = await navigator.mediaDevices.getUserMedia({ video: true });
    document.getElementById('cameraFeed').srcObject = stream;

    // Add communication with python server for this
    

  } catch (err) {
    alert('Error accessing camera: ' + err);
  }
}

function stopCamera() {
  if (!stream) return;

  let tracks = stream.getTracks();
  tracks.forEach((track) => track.stop());
  document.getElementById('cameraFeed').srcObject = null;

  stream = undefined;
}

$('#start-camera').onclick = startCamera;
$('#stop-camera').onclick = stopCamera;
