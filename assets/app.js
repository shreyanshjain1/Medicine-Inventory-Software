function updateClock(id) {
  const el = document.getElementById(id);
  if (!el) return;
  const render = () => {
    const now = new Date();
    el.textContent = now.toLocaleString();
  };
  render();
  setInterval(render, 1000);
}
function updateVisibleCount(counterId, visibleCount) {
  const counter = document.getElementById(counterId);
  if (counter) counter.textContent = String(visibleCount);
}
function refreshGroupedTable(tableId, counterId) {
  const tableWrap = document.getElementById(tableId);
  if (!tableWrap) return;
  const tbody = tableWrap.querySelector('tbody');
  if (!tbody) return;
  const rows = Array.from(tbody.querySelectorAll('tr'));
  let visibleDataRows = 0;
  let currentGroupRow = null;
  let groupHasVisibleRows = false;
  rows.forEach((row, index) => {
    const isGroupRow = row.hasAttribute('data-group-row');
    const isDataRow = row.getAttribute('data-search-row') === '1';
    if (isGroupRow) {
      if (currentGroupRow) currentGroupRow.style.display = groupHasVisibleRows ? '' : 'none';
      currentGroupRow = row; groupHasVisibleRows = false;
      if (index === rows.length - 1 && currentGroupRow) currentGroupRow.style.display = 'none';
      return;
    }
    if (isDataRow && row.style.display !== 'none') { visibleDataRows += 1; groupHasVisibleRows = true; }
    const nextRow = rows[index + 1];
    const nextIsGroupRow = !nextRow || nextRow.hasAttribute('data-group-row');
    if (nextIsGroupRow && currentGroupRow) { currentGroupRow.style.display = groupHasVisibleRows ? '' : 'none'; currentGroupRow = null; groupHasVisibleRows = false; }
  });
  updateVisibleCount(counterId, visibleDataRows);
}
function liveSearch(inputId, tableId, counterId) {
  const input = document.getElementById(inputId);
  const tableWrap = document.getElementById(tableId);
  if (!input || !tableWrap) return;
  const query = input.value.trim().toLowerCase();
  const rows = tableWrap.querySelectorAll('tbody tr[data-search-row="1"]');
  rows.forEach((row) => {
    const text = row.textContent.toLowerCase();
    row.style.display = query === '' || text.includes(query) ? '' : 'none';
  });
  refreshGroupedTable(tableId, counterId);
}
function initializeTableCounts() {
  refreshGroupedTable('regularTable', 'regularVisibleCount');
  refreshGroupedTable('outsourcedTable', 'outsourcedVisibleCount');
}
window.addEventListener('DOMContentLoaded', () => {
  updateClock('liveDateTime');
  initializeTableCounts();
});
