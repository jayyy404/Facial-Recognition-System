import { checkIfAdminLoggedIn, confirmIfAdmin } from './admin';
import updateClock from './libs/clock';
import { $ } from './libs/element';

fetch('/api/get-state')
  .then((res) => res.json())
  .then(({ logs }) => {
    renderLogs(logs);
  });

function renderLogs(logs) {
  $('#logsTable').replaceChildren(
    ...logs.map((log) => {
      /** This is a shortened version of the next three lines, called in one line */
      // if (log.recognized == 1) statusText = 'Recognized';
      // else if (log.recognized == 0) statusText = 'Unrecognized';
      // else if (log.recognized == 2) statusText = 'New User Added';
      // Map recognized flag to user-friendly status text
      // In DB: recognized === 1 => recognized/present, 0 => unrecognized
  const recognizedFlag = Number(log.recognized);
  let statusText = recognizedFlag === 1 ? 'Present' : 'Unrecognized';

      /**
       * This is an alias for `document.createElement`.
       * Check @file src/js/libs/element.js for the implementation
       */
      const tr = $.create('tr');

      tr.innerHTML = `
        <td>${log.time}</td>
        <td>${log.name ?? ''}</td>
        <td>${log.user_id ?? ''}</td>
        <td>${statusText}</td>
      `;

      return tr;
    })
  );
}

checkIfAdminLoggedIn($('#adminModal'), () => {
  $('#logsContent').style.removeProperty('display');
});

/** Cancel button */
$('#admin-form .btn-cancel').onclick = () => {
  alert('Admin login attempt canceled. Redirecting to attendance.');
  window.location.href = '/attendance';
};
