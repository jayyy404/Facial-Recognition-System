import { $ } from './libs/element';
import updateClock from './libs/clock';
import { checkIfAdminLoggedIn } from './admin';

function showReport(id, fn) {
  $.all('.report-section').forEach((div) => div.classList.remove('active'));
  $(`#${id}`).classList.add('active');
  window.scrollTo(0, 0);

  fn();
}

$('#show-report-monthly').onclick = () => showReport('monthly', fetchMonthly);
$('#show-report-custom').onclick = () => showReport('custom', customReportGeneration);
$('#show-report-summary').onclick = () => showReport('summary', fetchSummary);
$('#show-report-exceptions').onclick = () => showReport('exceptions', fetchExceptions);
$('#show-report-individual').onclick = () => showReport('individual', fetchIndividualUser);

updateClock();

// ------------------------------------------------------------------------------------
// Equivalent to the GET param load by default thingy
// ------------------------------------------------------------------------------------
const params = new URLSearchParams(location.search);

if (params.has('userId')) {
  showReport('individual', fetchIndividualUser);
} else if (params.has('from') || params.has('to')) {
  showReport('custom', customReportGeneration);
} else {
  showReport('monthly', fetchMonthly);
}

// ------------------------------------------------------------------------------------
// PHP things moved to the frontend call side. This is way cleaner than doing
// it inline in PHP files.
// ------------------------------------------------------------------------------------

function renderData(reportData, columns, displayColumns, container) {
  // Check if columns and displayColumns check out
  if (columns.length !== displayColumns.length) {
    throw new Error(
      `Data column length is not equal to display column length! \nAt data: ${columns.length}\nAt display: ${displayColumns.length}`
    );
  }

  const table = $.create('table');
  const thead = $.create('thead');
  const tbody = $.create('tbody');

  table.append(thead, tbody);

  thead.innerHTML = `
    <tr>
      ${displayColumns.map(col => `<th>${col}</th>`).join('\n')}
    </tr>
  `

  tbody.replaceChildren(
    ...reportData.map(row => {
      const el = $.create('tr');

      el.innerHTML = 
        columns.map(col => `<td>${row[col]}</td>`).join('\n');

      return el;
    })
  );

  container.replaceChild(table);
}

async function fetchMonthly() {
  const monthlyReport = await fetch('/api/reports/monthly-report').then(res => res.json());

  if (monthlyReport.length === 0) {
    $('#monthly .data').innerHTML = `<p>No attendance records found.</p>`;
    return;
  }

  renderData(
    monthlyReport, 
    ['user_id', 'name', 'role', 'dept', 'status'],
    ['User ID', 'Name', 'Role', 'Dept', 'Status'],
    $('#monthly .data')
  );  
}

async function customReportGeneration() {
  const data = await fetch('/api/reports/data').then(res => res.json());

  /** 
   * There are a couple things I changed here:
   * - Removed the Generate button. Changes are now applied automatically once date changes.
   * - Fetch the data on load instead of every change, and then applied the filter client-side.
   * 
   * This way, the data is fetched only once, and then filters are applied after without 
   * refreshing the page.
   */
  const form = $('#custom form');
  
  updateCustomReportUI();
  form.onchange = updateCustomReportUI;

  function updateCustomReportUI() {
    const { from, to } = Object.fromEntries(new FormData(form).entries());

    const filteredData = data.filter(row => {
      const date = new Date(row.date);
      return date >= new Date(from) && date <= new Date(to);
    });

    if (filteredData.length === 0) {
      $('#custom .data').innerHTML = `<p>No attendance records found for the selected date range.</p>`;
      return;
    }

    renderData(
      filteredData, 
      ['date', 'user_id', 'name', 'role', 'dept', 'status'],
      ['Date', 'User ID', 'Name', 'Role', 'Dept', 'Status'],
      $('#custom .data')
    );  
  }
}

async function fetchSummary() {
  const { totalUsers, totalRecognized, totalUnrecognized }
    = await fetch("/api/reports/summary").then(res => res.json());

  $('#summary .total-registered').innerHTML = `Total Registered Users: <b>${totalUsers}</b>`;
  $('#summary .total-recognized').innerHTML = `Total Recognized Logs: <b>${totalRecognized}</b>`;
  $('#summary .total-unrecognized').innerHTML = `Total Unrecognized Attempts: <b>${totalUnrecognized}</b>`;
}

async function fetchExceptions() {
  const exceptions = (await fetch('/api/reports/data').then(res => res.json()))
    .filter(row => row.status === "unrecognized");

  if (exceptions.length === 0) {
    $('#exceptions .data').innerHTML = `<p>No exceptions found.</p>`;
    return;
  }

  renderData(
    exceptions, 
    ['date', 'user_id', 'name', 'role', 'dept', 'status'],
    ['Date', 'User ID', 'Name', 'Role', 'Dept', 'Status'],
    $('#exceptions .data')
  ); 
}

async function fetchIndividualUser() {
  const users = await fetch('/api/reports/get-user-list').then(res => res.json());
  const input = $('#user-id-search');
  
  updateExceptionReport();
  input.oninput = updateExceptionReport;

  async function updateExceptionReport() {
    const id = input.value;

    // If there is no input, indicate that user ID has to be entered.
    if (id.length === 0) {
      $('#individual .data').innerHTML = `<p>Input a user ID above to continue.</p>`;
      return;
    }
    // console.log

    const user = users.find(user => user.id === id);

    if (!user) {
      $('#individual .data').innerHTML = `<p>User ID <b>${id}</b> does not exist.</p>`;
      return;
    }

    // Get user attendance list
    const userAttendanceList = await fetch(`/api/reports/get-user-attendance?id=${user.id}`).then(res => res.json());

    if (userAttendanceList.length === 0) {
      $('#individual .data').innerHTML = `<p>User <b>${user.name}</b> (ID ${id}) exists but has no attendance records yet.</p>`;
      return;
    }

    renderData(
      userAttendanceList, 
      ['date', 'status'],
      ['Date', 'Status'],
      $('#individual .data')
    ); 

    const userInfo = $.create('p');
    userInfo.innerHTML = `Attendance for <b>${user.name} (${user.id}) - Role: ${user.role} / Dept: ${user.dept}</b>`

    $('#individual .data').prepend(userInfo);
  }
}

checkIfAdminLoggedIn($('#adminModal'), () => {
  $('#reportsContent').style.removeProperty('display');
});