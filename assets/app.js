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
  const tbody = tableWrap.querySelector("tbody");
  if (!tbody) return;
  const rows = Array.from(tbody.querySelectorAll("tr"));
  let visibleDataRows = 0;
  let currentGroupRow = null;
  let groupHasVisibleRows = false;

  rows.forEach((row, index) => {
    const isGroupRow = row.hasAttribute("data-group-row");
    const isDataRow = row.getAttribute("data-search-row") === "1";
    if (isGroupRow) {
      if (currentGroupRow) currentGroupRow.style.display = groupHasVisibleRows ? "" : "none";
      currentGroupRow = row;
      groupHasVisibleRows = false;
      if (index === rows.length - 1 && currentGroupRow) currentGroupRow.style.display = "none";
      return;
    }
    if (isDataRow && row.style.display !== "none") {
      visibleDataRows += 1;
      groupHasVisibleRows = true;
    }
    const nextRow = rows[index + 1];
    const nextIsGroupRow = !nextRow || nextRow.hasAttribute("data-group-row");
    if (nextIsGroupRow && currentGroupRow) {
      currentGroupRow.style.display = groupHasVisibleRows ? "" : "none";
      currentGroupRow = null;
      groupHasVisibleRows = false;
    }
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
    row.style.display = query === "" || text.includes(query) ? "" : "none";
  });
  refreshGroupedTable(tableId, counterId);
}

function initializeTableCounts() {
  refreshGroupedTable("regularTable", "regularVisibleCount");
  refreshGroupedTable("outsourcedTable", "outsourcedVisibleCount");
}

function parseJsonAttribute(node, attributeName) {
  try {
    return JSON.parse(node.getAttribute(attributeName) || "[]");
  } catch (error) {
    return [];
  }
}

function drawAxes(ctx, width, height, margin) {
  ctx.strokeStyle = "#d9e4ef";
  ctx.lineWidth = 1;
  ctx.beginPath();
  ctx.moveTo(margin, margin);
  ctx.lineTo(margin, height - margin);
  ctx.lineTo(width - margin, height - margin);
  ctx.stroke();
}

function drawBarChart(canvas) {
  const labels = parseJsonAttribute(canvas, "data-labels");
  const values = parseJsonAttribute(canvas, "data-values");
  const color = canvas.getAttribute("data-color") || "#1a73e8";
  const ctx = canvas.getContext("2d");
  const ratio = window.devicePixelRatio || 1;
  const width = canvas.clientWidth || 480;
  const height = 240;
  canvas.width = width * ratio;
  canvas.height = height * ratio;
  canvas.style.height = `${height}px`;
  ctx.scale(ratio, ratio);
  ctx.clearRect(0, 0, width, height);

  const margin = 28;
  drawAxes(ctx, width, height, margin);

  const max = Math.max(...values, 1);
  const barWidth = Math.max(18, (width - margin * 2) / Math.max(values.length, 1) - 10);
  values.forEach((value, index) => {
    const x = margin + index * ((width - margin * 2) / Math.max(values.length, 1)) + 6;
    const barHeight = ((height - margin * 2) * value) / max;
    const y = height - margin - barHeight;
    ctx.fillStyle = color;
    ctx.fillRect(x, y, barWidth, barHeight);
    ctx.fillStyle = "#55657b";
    ctx.font = "11px Inter, Arial, sans-serif";
    ctx.save();
    ctx.translate(x + barWidth / 2, height - margin + 12);
    ctx.rotate(-0.35);
    ctx.textAlign = "right";
    ctx.fillText(labels[index] || "", 0, 0);
    ctx.restore();
  });
}

function drawMultiLineChart(canvas) {
  const labels = parseJsonAttribute(canvas, "data-labels");
  const series = parseJsonAttribute(canvas, "data-series");
  const ctx = canvas.getContext("2d");
  const ratio = window.devicePixelRatio || 1;
  const width = canvas.clientWidth || 480;
  const height = 240;
  canvas.width = width * ratio;
  canvas.height = height * ratio;
  canvas.style.height = `${height}px`;
  ctx.scale(ratio, ratio);
  ctx.clearRect(0, 0, width, height);

  const margin = 28;
  drawAxes(ctx, width, height, margin);
  const flattened = series.flatMap((item) => item.data || []);
  const max = Math.max(...flattened, 1);
  const points = Math.max(labels.length - 1, 1);

  series.forEach((item) => {
    ctx.strokeStyle = item.color || "#1a73e8";
    ctx.lineWidth = 2.5;
    ctx.beginPath();
    (item.data || []).forEach((value, index) => {
      const x = margin + ((width - margin * 2) / points) * index;
      const y = height - margin - ((height - margin * 2) * value) / max;
      if (index === 0) ctx.moveTo(x, y);
      else ctx.lineTo(x, y);
    });
    ctx.stroke();
  });

  ctx.fillStyle = "#55657b";
  ctx.font = "11px Inter, Arial, sans-serif";
  labels.forEach((label, index) => {
    const x = margin + ((width - margin * 2) / points) * index;
    ctx.save();
    ctx.translate(x, height - margin + 12);
    ctx.rotate(-0.35);
    ctx.textAlign = "right";
    ctx.fillText(label, 0, 0);
    ctx.restore();
  });
}

function initializeCharts() {
  document.querySelectorAll(".chart-canvas").forEach((canvas) => {
    const chartType = canvas.getAttribute("data-chart");
    if (chartType === "multi-line") {
      drawMultiLineChart(canvas);
    } else {
      drawBarChart(canvas);
    }
  });
}

window.addEventListener("DOMContentLoaded", () => {
  updateClock("liveDateTime");
  initializeTableCounts();
  initializeCharts();
  window.addEventListener("resize", initializeCharts);
});
