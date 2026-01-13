/* ============================================================
   INDICADORES - Dashboard (App Tránsito)
   - KPIs
   - Serie diaria (ApexCharts)
   - Mapa Leaflet (solo México) con puntos por accidente
   - Exportar PNG/PDF
   ============================================================ */

/* globals BASE_URL, alertify, ApexCharts, html2canvas, jspdf, L */

// ----- helpers generales -----
const fmtInt = (n) => Number(n || 0).toLocaleString();
let chart = null;   // ApexCharts
let lmap = null;    // Leaflet map
let lpoints = null; // Capa de puntos

// Carga dinámica de recursos (por si no están en el header)
function loadScript(src) {
  return new Promise((resolve, reject) => {
    if ([...document.scripts].some(s => s.src === src)) return resolve();
    const s = document.createElement('script');
    s.src = src; s.async = true;
    s.onload = resolve; s.onerror = () => reject(new Error('No se pudo cargar: ' + src));
    document.head.appendChild(s);
  });
}
function loadCss(href) {
  if ([...document.styleSheets].some(ss => ss.href === href)) return;
  const l = document.createElement('link');
  l.rel = 'stylesheet'; l.href = href;
  document.head.appendChild(l);
}

/* =========================== KPIs =========================== */
function setKPI(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = fmtInt(value);
}

function cargarKPIs(desde = null, hasta = null) {
  const qs = [];
  if (desde) qs.push('desde=' + encodeURIComponent(desde));
  if (hasta) qs.push('hasta=' + encodeURIComponent(hasta));
  const url = BASE_URL + 'admin/indicadores/kpis' + (qs.length ? '?' + qs.join('&') : '');

  return fetch(url, { credentials: 'same-origin' })
    .then(r => r.json())
    .then(d => {
      // DEBUG: si viene error desde PHP, no pintes 0 en silencio
      if (!d || d.error) {
        console.error('[KPIs] payload con error:', d);
        alertify.error(d && d.message ? d.message : 'Error al calcular KPIs');
        return;
      }

      const {
        total = 0,
        abiertos = 0,
        asignado = 0,
        enRuta = 0,
        enSitio = 0,
        enProceso = 0,
        cerrados = 0,
        cancelados = 0,
        con_fuga = 0, // por si después se usa
        menor = 0,
        moderado = 0,
        grave = 0,
        hoy = 0,
        ultimos7 = 0
      } = d;

      setKPI('kpi_total', total);
      setKPI('kpi_abiertos', abiertos);
      setKPI('kpi_asignado', asignado);
      setKPI('kpi_enruta', enRuta);
      setKPI('kpi_ensitio', enSitio);
      setKPI('kpi_enproceso', enProceso);
      setKPI('kpi_cerrados', cerrados);
      setKPI('kpi_cancelados', cancelados);
      // setKPI('kpi_fuga', con_fuga);
      setKPI('kpi_menor', menor);
      setKPI('kpi_moderado', moderado);
      setKPI('kpi_grave', grave);
      setKPI('kpi_hoy', hoy);
      setKPI('kpi_ult7', ultimos7);
    })
    .catch(() => alertify.error('Error al cargar los KPIs'));
}

/* ======================= Serie diaria ======================= */
function crearChart(labels, series) {
  const el = document.querySelector('#chart_accidentes');
  if (!el) return;

  const options = {
    chart: { type: 'area', height: 340, toolbar: { show: false } },
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2 },
    series,
    xaxis: { categories: labels, labels: { rotate: -45 } },
    yaxis: {
      min: 0,
      forceNiceScale: true,
      labels: { formatter: (v) => fmtInt(v) }
    },
    fill: {
      type: 'gradient',
      gradient: { shadeIntensity: 0.3, opacityFrom: 0.6, opacityTo: 0.1 }
    },
    tooltip: {
      shared: true,
      intersect: false,
      y: { formatter: (v) => fmtInt(v) + ' registros' }
    },
    legend: { position: 'top', horizontalAlign: 'left' },
    grid: { strokeDashArray: 4 }
  };

  if (chart) {
    chart.updateOptions(options, false, true);
  } else {
    chart = new ApexCharts(el, options);
    chart.render();
  }
}

function normalizarSerie(d) {
  const labels = d.labels || [];
  const series = [];

  if (Array.isArray(d.total))    series.push({ name: 'Total',    data: d.total });
  if (Array.isArray(d.menor))    series.push({ name: 'MENOR',    data: d.menor });
  if (Array.isArray(d.moderado)) series.push({ name: 'MODERADO', data: d.moderado });
  if (Array.isArray(d.grave))    series.push({ name: 'GRAVE',    data: d.grave });

  if (!series.length && Array.isArray(d.values)) {
    series.push({ name: 'Total', data: d.values });
  }

  return { labels, series };
}

function cargarSerie(dias = 14, desde = null, hasta = null) {
  const qs = ['dias=' + encodeURIComponent(dias)];
  if (desde) qs.push('desde=' + encodeURIComponent(desde));
  if (hasta) qs.push('hasta=' + encodeURIComponent(hasta));
  const url = BASE_URL + 'admin/indicadores/serie_diaria?' + qs.join('&');

  return fetch(url, { credentials: 'same-origin' })
    .then(r => r.json())
    .then(d => {
      const { labels, series } = normalizarSerie(d || {});
      crearChart(labels, series);
    })
    .catch(() => alertify.error('Error al cargar la serie diaria'));
}

/* ==================== Mapa (Leaflet) ==================== */
function ensureLeaflet() {
  const lfCss = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
  const lfJs  = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
  loadCss(lfCss);
  return (typeof L !== 'undefined') ? Promise.resolve() : loadScript(lfJs);
}

// Bounds aprox. de México (Suroeste / Noreste)
function mexicoBounds() {
  return L.latLngBounds([14.3, -118.6], [32.9, -86.5]);
}

function initMapIfNeeded() {
  const cont = document.getElementById('world-map');
  if (!cont) return;

  // Si ya existe, solo limpia capa de puntos
  if (lmap) {
    if (lpoints) lpoints.clearLayers();
    return;
  }

  // Crear mapa una sola vez
  const center = [23.6345, -102.5528];
  lmap = L.map('world-map', {
    zoomControl: true,
    scrollWheelZoom: false,
    minZoom: 4,
    maxZoom: 12
  }).setView(center, 5);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap'
  }).addTo(lmap);

  // Restringir desplazamiento a México
  lmap.setMaxBounds(mexicoBounds());

  // Capa reutilizable de puntos
  lpoints = L.layerGroup().addTo(lmap);
}

/* >>>>>>>>>> Normaliza puntos para el mapa (array plano o {points: [...]}) <<<<<<<<<< */
function extractPoints(payload) {
  // 1) Origen flexible
  let pts;
  if (Array.isArray(payload)) {
    pts = payload; // backend podría devolver un array plano
  } else {
    pts = payload?.points || payload?.puntos || payload?.data || [];
  }
  if (!Array.isArray(pts)) pts = [];

  // 2) Normaliza + limita a México (si lng viene positivo, invierte signo)
  const mb = mexicoBounds();
  const out = [];
  for (const p of pts) {
    let lat = Number(p.lat), lng = Number(p.lng);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) continue;

    let ll = L.latLng(lat, lng);
    if (!mb.contains(ll) && lng > 0) {
      // muchos datos vienen con longitudes positivas: cámbialas a poniente
      lng = -lng;
      ll = L.latLng(lat, lng);
    }
    if (mb.contains(ll)) {
      out.push({
        lat: ll.lat,
        lng: ll.lng,
        // campos extra por si existen
        direccion: p.direccion || p.address || '',
        severidad: p.severidad || '',
        estatus:   p.estatus || p.status || ''
      });
    }
  }
  return out;
}

function renderPuntos(payload) {
  if (!lmap || !lpoints) return;

  const points = extractPoints(payload);
  lpoints.clearLayers();

  const bounds = [];
  points.forEach(p => {
    const marker = L.circleMarker([p.lat, p.lng], {
      radius: 5,
      weight: 1,
      color: '#0D6EFD',
      fillColor: '#0D6EFD',
      fillOpacity: 0.85
    });
    const sev = (p.severidad || '').toString().toUpperCase();
    const est = (p.estatus || '').toString().toUpperCase();
    const dir = (p.direccion || '').toString();
    const tip = [
      sev ? `<b>${sev}</b>` : '',
      est ? `<span>${est}</span>` : '',
      dir ? `<div style="max-width:240px">${dir}</div>` : ''
    ].filter(Boolean).join('<br>');
    if (tip) marker.bindTooltip(tip, { direction: 'top', sticky: true });
    marker.addTo(lpoints);
    bounds.push([p.lat, p.lng]);
  });

  if (bounds.length) {
    lmap.fitBounds(bounds, { padding: [20, 20] });
  } else {
    lmap.fitBounds(mexicoBounds());
  }
}

/**
 * Top estados por número de accidentes, a partir de markers del backend:
 * backend -> indicadoresModel::mapa() => { markers:[{code,name,lat,lng,count}], total }
 */
function renderTopEstados(markers = [], total = 0) {
  const ol = document.getElementById('map-top');
  if (ol) {
    ol.innerHTML = '';
    const top = [...markers]
      .sort((a, b) => (b.count || 0) - (a.count || 0))
      .slice(0, 5)
      .map(m => ({
        name: m.name || m.code || '',
        count: Number(m.count || 0),
        perc: total > 0 ? Math.round((Number(m.count || 0) * 100) / total) : 0
      }));

    top.forEach(t => {
      const li = document.createElement('li');
      li.textContent = `${t.name}: ${fmtInt(t.count)} (${t.perc}%)`;
      ol.appendChild(li);
    });
  }

  const legend = document.getElementById('map-legend');
  if (legend) {
    legend.innerHTML = `Total: <b>${fmtInt(total)}</b>`;
  }
}

function cargarMapa(desde = null, hasta = null) {
  const qs = [];
  if (desde) qs.push('desde=' + encodeURIComponent(desde));
  if (hasta) qs.push('hasta=' + encodeURIComponent(hasta));
  const url = BASE_URL + 'admin/indicadores/mapa' + (qs.length ? '?' + qs.join('&') : '');

  return ensureLeaflet()
    .then(() => fetch(url, { credentials: 'same-origin' }))
    .then(r => r.json())
    .then(payload => {
      initMapIfNeeded();
      const markers = payload.markers || [];
      const total   = payload.total   || 0;
      renderTopEstados(markers, total);
      renderPuntos(payload);
    })
    .catch(() => {
      const c = document.querySelector('#world-map');
      if (c) c.textContent = 'Mapa no disponible';
      alertify.error('Error al cargar el mapa de incidentes');
    });
}

/* ===================== Exportar PNG/PDF ===================== */
function exportarPNG() {
  const target = document.querySelector('.app-content');
  if (!target) return;
  html2canvas(target).then(canvas => {
    const link = document.createElement('a');
    link.download = 'dashboard.png';
    link.href = canvas.toDataURL('image/png');
    link.click();
  });
}

function exportarPDF() {
  const target = document.querySelector('.app-content');
  if (!target) return;
  html2canvas(target).then(canvas => {
    const imgData = canvas.toDataURL('image/png');
    const pdf = new jspdf.jsPDF('l', 'pt', 'a4');
    const pageWidth = pdf.internal.pageSize.getWidth() - 40;
    const pageHeight = (canvas.height * pageWidth) / canvas.width;
    pdf.addImage(imgData, 'PNG', 20, 20, pageWidth, pageHeight);
    pdf.save('dashboard.pdf');
  });
}

/* ============================== Init ============================== */
document.addEventListener('DOMContentLoaded', () => {
  const sel          = document.getElementById('sel_dias');
  const btnRefrescar = document.getElementById('btn_refrescar');
  const btnAplicar   = document.getElementById('btn_kpi_aplicar');
  const desde        = document.getElementById('kpi_desde');
  const hasta        = document.getElementById('kpi_hasta');
  const btnExportPng = document.getElementById('btn_export_png');
  const btnExportPdf = document.getElementById('btn_export_pdf');

  const refresh = () => {
    const dias = parseInt(sel?.value, 10) || 14;
    const d = (desde?.value || '').trim() || null;
    const h = (hasta?.value || '').trim() || null;
    Promise.all([
      cargarKPIs(d, h),
      cargarSerie(dias, d, h),
      cargarMapa(d, h)
    ]).then(() => alertify.message('Indicadores actualizados'));
  };

  // Inicial (mes actual por defecto en backend)
  cargarKPIs();
  cargarSerie(14);
  cargarMapa();

  // Eventos
  sel?.addEventListener('change', refresh);
  btnRefrescar?.addEventListener('click', refresh);
  btnAplicar?.addEventListener('click', refresh);
  btnExportPng?.addEventListener('click', (e)=>{ e.preventDefault(); exportarPNG(); });
  btnExportPdf?.addEventListener('click', (e)=>{ e.preventDefault(); exportarPDF(); });
});
