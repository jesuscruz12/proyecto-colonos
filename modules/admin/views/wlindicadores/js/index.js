/* ===========================================================================
   WLINDICADORES - index.js
   - Controla KPIs, gráficas y tops con rango de fechas (mes actual por defecto)
   - Endpoints (GET):
       admin/wlindicadores/kpis
       admin/wlindicadores/activaciones_por_dia
       admin/wlindicadores/recargas_por_dia
       admin/wlindicadores/top_socios
       admin/wlindicadores/top_planes
       admin/wlindicadores/consumo_mix
   =========================================================================== */

/* globals ApexCharts */
(function () {
  'use strict';

  // ===== Helpers =====
  const qs  = (s) => document.querySelector(s);
  const qsa = (s) => Array.from(document.querySelectorAll(s));

  function showNoData(chartRef, elSelector, text = 'Sin datos en el periodo') {
    const el = document.querySelector(elSelector);
    if (!chartRef) {
      const c = new ApexCharts(el, {
        chart: { type: 'line', height: 320, toolbar: { show: false } },
        noData: { text }
      });
      c.render();
      return c;
    } else {
      chartRef.updateOptions({ noData: { text } }, false, true);
      chartRef.updateSeries([]);
      return chartRef;
    }
  }

  // Asegura BASE_URL
  if (!window.BASE_URL) {
    const baseEl = document.querySelector('base[href]');
    window.BASE_URL = baseEl ? baseEl.getAttribute('href') : '/';
  }

  const fmtMXNIntl = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
  const fmtMXN = (n) => fmtMXNIntl.format(Number(n ?? 0));

  const getMonthRange = () => {
    const now = new Date();
    const y = now.getFullYear();
    const m = now.getMonth();
    const first = new Date(y, m, 1);
    const last  = new Date(y, m + 1, 0);
    const p = (n) => (n < 10 ? '0' + n : '' + n);
    return {
      desde: `${first.getFullYear()}-${p(first.getMonth() + 1)}-${p(first.getDate())}`,
      hasta: `${last.getFullYear()}-${p(last.getMonth() + 1)}-${p(last.getDate())}`,
    };
  };

  const setDefaultMonth = () => {
    const r = getMonthRange();
    const d = qs('#kpi_desde'), h = qs('#kpi_hasta');
    if (d) d.value = r.desde;
    if (h) h.value = r.hasta;
  };

  const getRange = () => ({
    desde: qs('#kpi_desde')?.value || '',
    hasta: qs('#kpi_hasta')?.value || ''
  });

  const fetchJSON = async (path, params = {}) => {
    const u = new URL(BASE_URL + path, window.location.origin);
    Object.entries(params).forEach(([k, v]) => u.searchParams.set(k, v));
    const res = await fetch(u.toString(), { headers: { 'Accept': 'application/json' } });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
  };

  // ===== KPIs =====
  async function loadKpis() {
    const { desde, hasta } = getRange();
    const data = await fetchJSON('admin/wlindicadores/kpis', { desde, hasta });
    if (!data.success) throw new Error(data.error || 'Error KPIs');

    qs('#kpi_activaciones').textContent   = (data.data.activaciones ?? 0).toLocaleString('es-MX');
    qs('#kpi_recargas_monto').textContent = fmtMXN(data.data.recargasMonto ?? 0);
    qs('#kpi_recargas_count').textContent = (data.data.recargasCount ?? 0).toLocaleString('es-MX');
    qs('#kpi_ticket_promedio').textContent= fmtMXN(data.data.ticketPromedio ?? 0);

    const label = `${desde} a ${hasta}`;
    const lrA = qs('#lbl_rango_a'), lrR = qs('#lbl_rango_r');
    if (lrA) lrA.textContent = label;
    if (lrR) lrR.textContent = label;
  }

  // ===== Charts =====
  // Usar SIEMPRE window.* y chart.id para poder exportar
  // window.chartActivaciones, window.chartRecargas, window.chartConsumoMix
  // Se crean UNA sola vez, y luego solo se actualizan.

  // === (1) Chart activaciones: multi-serie (Nuevas / Portadas / Total)
  async function loadChartActivaciones() {
    const { desde, hasta } = getRange();
    const resp = await fetchJSON('admin/wlindicadores/activaciones_por_dia', { desde, hasta });
    if (!resp.success) throw new Error(resp.error || 'Error chart activaciones');

    const labels = resp.labels ?? [];
    const series = resp.series ?? [];
    const hasData =
      labels.length > 0 &&
      series.length > 0 &&
      series.some(s => Array.isArray(s.data) && s.data.some(v => Number(v) > 0));

    if (!window.chartActivaciones) {
      window.chartActivaciones = new ApexCharts(
        document.querySelector('#chart_activaciones'),
        {
          chart: { id: 'chart-activaciones', type: 'line', height: 320, toolbar: { show: false } },
          stroke: { width: 3, curve: 'smooth' },
          dataLabels: { enabled: false },
          tooltip: { x: { format: 'yyyy-MM-dd' } },
          xaxis: { categories: [], labels: { rotate: -45 } },
          yaxis: { labels: { formatter: (v) => Number(v).toLocaleString('es-MX') } },
          series: [],
          noData: { text: 'Sin datos en el periodo' }
        }
      );
      await window.chartActivaciones.render();
    }

    if (!hasData) {
      await window.chartActivaciones.updateOptions({ xaxis: { categories: [] }, noData: { text: 'Sin datos en el periodo' } });
      await window.chartActivaciones.updateSeries([]);
      return;
    }

    await window.chartActivaciones.updateOptions({ xaxis: { categories: labels }, noData: { text: '' } });
    await window.chartActivaciones.updateSeries(series);
  }

  // === (2) Chart recargas: eje MXN y conteos
  async function loadChartRecargas() {
    const { desde, hasta } = getRange();
    const resp = await fetchJSON('admin/wlindicadores/recargas_por_dia', { desde, hasta });
    if (!resp.success) throw new Error(resp.error || 'Error chart recargas');

    const labels = resp.labels ?? [];
    const s = resp.series ?? []; // [ {name:'Recargas',data:[...]}, {name:'Monto',data:[...]} ]
    const hasData =
      labels.length > 0 &&
      s.length > 0 &&
      s.some(x => Array.isArray(x.data) && x.data.some(v => Number(v) > 0));

    if (!window.chartRecargas) {
      window.chartRecargas = new ApexCharts(document.querySelector('#chart_recargas'), {
        chart: { id: 'chart-recargas', type: 'line', height: 320, toolbar: { show: false } },
        stroke: { width: 3, curve: 'smooth' },
        dataLabels: { enabled: false },
        tooltip: {
          x: { format: 'yyyy-MM-dd' },
          y: { formatter: (val, { seriesIndex }) => seriesIndex === 1 ? fmtMXN(val) : Number(val).toLocaleString('es-MX') }
        },
        xaxis: { categories: [], labels: { rotate: -45 } },
        yaxis: [
          { title: { text: 'Recargas' }, labels: { formatter: (v) => Number(v).toLocaleString('es-MX') } },
          { opposite: true, title: { text: 'Monto' }, labels: { formatter: (v) => fmtMXN(v) } }
        ],
        series: [],
        noData: { text: 'Sin datos en el periodo' }
      });
      await window.chartRecargas.render();
    }

    if (!hasData) {
      await window.chartRecargas.updateOptions({ xaxis: { categories: [] }, noData: { text: 'Sin datos en el periodo' } });
      await window.chartRecargas.updateSeries([]);
      return;
    }

    await window.chartRecargas.updateOptions({ xaxis: { categories: labels }, noData: { text: '' } });
    await window.chartRecargas.updateSeries([
      { name: s[0]?.name || 'Recargas', data: s[0]?.data || [], yAxisIndex: 0 },
      { name: s[1]?.name || 'Monto',    data: s[1]?.data || [], yAxisIndex: 1 },
    ]);
  }

  // === (3) Donut Consumo (Recargas vs Activaciones)
  async function loadChartConsumo() {
    const { desde, hasta } = getRange();
    const resp = await fetchJSON('admin/wlindicadores/consumo_mix', { desde, hasta });
    if (!resp.success) throw new Error(resp.error || 'Error consumo_mix');

    const seriesRaw = resp.series ?? [0,0];
    const counts = resp.counts ?? { recargas: 0, activaciones: 0 };
    const labels = [
      `Recargas (${counts.recargas.toLocaleString('es-MX')})`,
      `Activaciones (${counts.activaciones.toLocaleString('es-MX')})`
    ];
    const hasData = seriesRaw.some(v => Number(v) > 0);

    if (!window.chartConsumoMix) {
      window.chartConsumoMix = new ApexCharts(document.querySelector('#chart_consumo_mix'), {
        chart: { id: 'chart-consumo', type: 'donut', height: 320 },
        labels: [],
        series: [],
        dataLabels: { enabled: true, formatter: (val) => `${val.toFixed(1)}%` },
        tooltip: { y: { formatter: (v) => fmtMXN(v) } },
        legend: { position: 'bottom' },
        plotOptions: {
          pie: {
            donut: {
              size: '70%',
              labels: {
                show: true,
                total: {
                  show: true,
                  label: 'Total',
                  formatter: (w) => fmtMXN(w.globals.seriesTotals.reduce((a,b)=>a+b,0))
                },
                value: { formatter: (val) => fmtMXN(val) }
              }
            }
          }
        },
        noData: { text: 'Sin datos en el periodo' }
      });
      await window.chartConsumoMix.render();
    }

    if (!hasData) {
      await window.chartConsumoMix.updateOptions({ labels: [], noData: { text: 'Sin datos en el periodo' } });
      await window.chartConsumoMix.updateSeries([]);
    } else {
      await window.chartConsumoMix.updateOptions({ labels, noData: { text: '' } });
      await window.chartConsumoMix.updateSeries(seriesRaw);
    }
    const tag = document.getElementById('tag_rango_consumo');
    if (tag) tag.textContent = `${desde} a ${hasta}`;
  }

  // === (4) Top planes
  async function loadTopPlanes() {
    const { desde, hasta } = getRange();
    const data = await fetchJSON('admin/wlindicadores/top_planes', { desde, hasta });
    if (!data.success) throw new Error(data.error || 'Error top planes');

    const body = qs('#tbl_top_planes');
    const hint = qs('#lbl_top_planes_hint');
    body.innerHTML = '';

    const items = data.items ?? [];
    const by = data.by ?? 'unknown';

    const badgePS = (p) => {
      if (p === 1) return '<span class="badge bg-success">Activación</span>';
      if (p === 2) return '<span class="badge bg-warning text-dark">Recarga</span>';
      return '<span class="badge bg-secondary">—</span>';
    };

    if (by === 'recargas') {
      if (hint) hint.textContent = 'Ordenado por número total de recargas.';
      if (items.length === 0) {
        body.innerHTML = `<tr><td colspan="5" class="text-center p-3 text-muted">Sin datos</td></tr>`;
        return;
      }
      items.forEach((it, idx) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${idx + 1}</td>
          <td>
            <div class="d-flex flex-column">
              <strong>${it.plan}</strong>
              <small class="text-muted">ID: ${it.plan_id}</small>
            </div>
          </td>
          <td class="text-end">${(it.recargas ?? 0).toLocaleString('es-MX')}</td>
          <td class="text-end">${(it.numeros_unicos ?? 0).toLocaleString('es-MX')}</td>
          <td class="text-end">${fmtMXN(it.monto ?? 0)}</td>
        `;
        body.appendChild(tr);
      });
      return;
    }

    if (by === 'activaciones') {
      if (hint) hint.textContent = 'Ordenado por número de activaciones.';
      if (items.length === 0) {
        body.innerHTML = `<tr><td colspan="5" class="text-center p-3 text-muted">Sin datos</td></tr>`;
        return;
      }
      items.forEach((it, idx) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${idx + 1}</td>
          <td>
            <div class="d-flex flex-column">
              <strong>${it.plan}</strong>
              <small class="text-muted">ID: ${it.plan_id}</small>
              <div class="mt-1 d-flex gap-1">${badgePS(it.prim_sec)}</div>
            </div>
          </td>
          <td class="text-end">${(it.activaciones ?? 0).toLocaleString('es-MX')}</td>
          <td class="text-end">—</td>
          <td class="text-end">—</td>
        `;
        body.appendChild(tr);
      });
      return;
    }

    if (hint) hint.textContent = 'No se pudo determinar la fuente para el top.';
    body.innerHTML = `<tr><td colspan="5" class="text-center p-3 text-muted">Sin datos</td></tr>`;
  }

  // ===== Exportación PNG/PDF (ApexCharts.exec) =====
  const idsParaExportar = [
    { id: 'chart-activaciones', nombre: 'activaciones' },
    { id: 'chart-recargas',     nombre: 'recargas'     },
    { id: 'chart-consumo',      nombre: 'consumo'      },
  ];

  function download(uri, filename) {
    const a = document.createElement('a');
    a.href = uri; a.download = filename; document.body.appendChild(a);
    a.click(); a.remove();
  }

  async function getPngFromApex(id) {
    try {
      const res = await ApexCharts.exec(id, 'dataURI'); // { imgURI, blob }
      return res?.imgURI || null;
    } catch (e) {
      console.error('dataURI error', id, e);
      return null;
    }
  }

  async function exportChartsAsPNGs() {
    const { desde, hasta } = getRange();
    for (const c of idsParaExportar) {
      const uri = await getPngFromApex(c.id);
      if (uri) download(uri, `ind_${c.nombre}_${desde}_a_${hasta}.png`);
    }
  }

  async function exportChartsAsPDF() {
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF('l', 'pt', 'a4');
    const pageW = pdf.internal.pageSize.getWidth();
    const pageH = pdf.internal.pageSize.getHeight();
    const margin = 24;

    let first = true;
    for (const c of idsParaExportar) {
      const uri = await getPngFromApex(c.id);
      if (!uri) continue;

      if (!first) pdf.addPage(); else first = false;
      pdf.setFontSize(14);
      pdf.text(c.nombre.toUpperCase(), margin, margin + 6);
      pdf.addImage(uri, 'PNG', margin, margin * 2, pageW - margin * 2, pageH - margin * 3, undefined, 'FAST');
    }

    const { desde, hasta } = getRange();
    pdf.save(`indicadores_${desde}_a_${hasta}.pdf`);
  }

  // ===== Carga y eventos =====
  async function loadAll() {
    await Promise.allSettled([
      loadKpis(),
      loadChartActivaciones(),
      loadChartRecargas(),
      loadChartConsumo(),
      loadTopPlanes()
    ]);
  }

  function bindEvents() {
    // Tooltips Bootstrap
    qsa('[data-bs-toggle="tooltip"]').forEach(el => {
      try { new bootstrap.Tooltip(el); } catch (e) {}
    });

    // Aplicar rango
    qs('#btn_kpi_aplicar')?.addEventListener('click', async () => {
      await loadAll();
    });

    // Exportar
    document.getElementById('btn_export_png')?.addEventListener('click', (e) => {
      e.preventDefault(); exportChartsAsPNGs();
    });
    document.getElementById('btn_export_pdf')?.addEventListener('click', (e) => {
      e.preventDefault(); exportChartsAsPDF();
    });
  }

  // ===== Boot =====
  window.addEventListener('DOMContentLoaded', async () => {
    setDefaultMonth();
    bindEvents();
    await loadAll();
  });
})();
