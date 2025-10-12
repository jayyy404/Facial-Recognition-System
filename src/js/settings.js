import { checkIfAdminLoggedIn } from './admin';
import { $ } from './libs/element';
import updateClock from './libs/clock';

updateClock();
checkIfAdminLoggedIn($('#adminModal'), () => {
  $('#settingsContent').style.removeProperty('display');
  updateSettings();
  updateEventListeners();
});

// Notification system (#alert-contents)

/**
 * All system updates have been moved from the PHP side to the Javascript side.
 */
async function updateSettings() {
  const settings = await fetch('/api/settings/get-settings').then((res) =>
    res.json()
  );
  const roles = await fetch('/api/settings/get-roles').then((res) =>
    res.json()
  );

  function updateSystemSettings() {
    $('#system-settings [name="system_name"]').value = settings.system_name;
    $('#system-settings [name="institution"]').value = settings.institution;
    $.all(`#system-settings [name="timezone"] option`).forEach(
      (el) => (el.selected = el.value === settings.timezone)
    );
    $.all(`#system-settings [name="datetime_format"] option`).forEach(
      (el) => (el.selected = el.value === settings.datetime_format)
    );
  }

  function updateAccountSettings() {
    $('#account-settings [name="admin_username"]').value =
      settings.admin_username;

    // Roles
    $('#account-settings [name="default_role"]').replaceChildren(
      roles.map(({ role_name }) => {
        const opt = $.create('option');

        opt.value = role_name;
        opt.innerHTML = role_name;
        opt.selected = settings.default_role === role_name;

        return opt;
      })
    );

    // Password policy
    $.all(`#account-settings [name="password_policy"] option`).forEach(
      (el) => (el.selected = el.value === settings.password_policy)
    );
  }

  function updateAttendanceSettings() {
    // Default camera
    $.all(`#attendance-settings [name="default_camera"] option`).forEach(
      (el) => (el.selected = el.value === settings.default_camera)
    );

    // Recognition sensitivity
    $.all(
      `#attendance-settings [name="recognition_sensitivity"] option`
    ).forEach(
      (el) => (el.selected = el.value === settings.recognition_sensitivity)
    );

    // Samples per user
    $('#attendance-settings [name="samples_per_user"]').value =
      settings.samples_per_user;
    $('#attendance-settings [name="cutoff_time"]').value = settings.cutoff_time;
  }

  function updateDatabaseAndBackupSettings() {
    $('#db-and-backup-settings [name="last_backup"]').value =
      settings.last_backup ?? 'Not yet';
  }

  function updateSecuritySettings() {
    $('#security-settings [name="session_timeout"]').value =
      settings.session_timeout;
    $.all(`#security-settings [name="access_level"] option`).forEach(
      (el) => (el.selected = el.value === settings.access_level)
    );
  }

  updateSystemSettings();
  updateAccountSettings();
  updateAttendanceSettings();
  updateDatabaseAndBackupSettings();
  updateSecuritySettings();
}

function updateEventListeners() {
  $('#system-settings').onsubmit = (e) => {
    e.preventDefault();

    const formData = new FormData(e.currentTarget);

    fetch('/api/settings/updates/system', {
      method: 'POST',
      body: formData,
    }).then(async (res) => {
      if (res.ok) {
        updateSettings();
        $('.alert').style.removeProperty('display');
        $('#alert-contents').innerHTML = await res.text();
      }
    });
  };

  $('#account-settings').onsubmit = (e) => {
    e.preventDefault();

    const formData = new FormData(e.currentTarget);

    fetch('/api/settings/updates/account', {
      method: 'POST',
      body: formData,
    }).then(async (res) => {
      if (res.ok) {
        updateSettings();
        $('.alert').style.removeProperty('display');
        $('#alert-contents').innerHTML = await res.text();
      }
    });
  };

  $('#attendance-settings').onsubmit = (e) => {
    e.preventDefault();

    const formData = new FormData(e.currentTarget);

    fetch('/api/settings/updates/attendance', {
      method: 'POST',
      body: formData,
    }).then(async (res) => {
      if (res.ok) {
        updateSettings();
        $('.alert').style.removeProperty('display');
        $('#alert-contents').innerHTML = await res.text();
      }
    });
  };

  $('#db-and-backup-settings').onsubmit = (e) => {
    e.preventDefault();

    fetch('/api/settings/updates/backup', { method: 'POST' }).then(
      async (res) => {
        if (res.ok) {
          updateSettings();
          $('.alert').style.removeProperty('display');
          $('#alert-contents').innerHTML = await res.text();
        }
      }
    );
  };

  $('#security-settings').onsubmit = (e) => {
    e.preventDefault();

    const formData = new FormData(e.currentTarget);

    fetch('/api/settings/updates/security', {
      method: 'POST',
      body: formData,
    }).then(async (res) => {
      if (res.ok) {
        updateSettings();
        $('.alert').style.removeProperty('display');
        $('#alert-contents').innerHTML = await res.text();
      }
    });
  };
}
