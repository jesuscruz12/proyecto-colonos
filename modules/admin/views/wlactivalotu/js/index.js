/* ========================================================================
   WL ACTÍVALO TÚ — index.js (flujo: 1 Cobertura → 2 IMEI → 3 Plan → 4 SIM → 5 Tipo de línea → 6 Confirmación)
   - UX: feedback claro, spinners, deshabilitados consistentes, prevención de errores
   - Accesibilidad: aria-live en mensajes, foco inicial por paso, teclado en tarjetas de plan
   - Responsividad: carrusel con wheel horizontal y botones prev/next
   - eSIM: exige plan seleccionado antes de generar el QR
   - Contrato QR: usa siempre `qr_img_url` (+ opcional `qr_text`)
   ======================================================================== */
(function () {
  "use strict";

  /* ------------------------------ Utils ------------------------------ */
  const qs  = (s, r = document) => r.querySelector(s);
  const qsa = (s, r = document) => Array.from(r.querySelectorAll(s));

  function getAdminBase() {
    const path = location.pathname;
    const i = path.indexOf("/admin/");
    if (i >= 0) return path.substring(0, i + 7);
    if (typeof BASE_URL !== "undefined") return BASE_URL.replace(/\/?$/, "/") + "admin/";
    return "/admin/";
  }
  const ADMIN_BASE = getAdminBase();

  const urlParams   = new URLSearchParams(location.search);
  const mockEnabled = urlParams.get("mock") === "1";
  const mockCase    = urlParams.get("case") || "";

  function buildURL(endpoint) {
    let u = ADMIN_BASE.replace(/\/$/, "") + "/" + endpoint.replace(/^\//, "");
    if (mockEnabled) {
      u += (u.includes("?") ? "&" : "?") + "mock=1";
      if (mockCase) u += "&case=" + encodeURIComponent(mockCase);
    }
    return u;
  }

  async function postJSON(endpoint, body = {}) {
    const url = buildURL(endpoint);
    let r, text;
    try {
      r = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: new URLSearchParams(body),
        credentials: "same-origin",
      });
      text = await r.text();
    } catch {
      return { ok: false, error: "Sin conexión. Verifica tu red." };
    }
    let json = null;
    try { json = JSON.parse(text); } catch { /* respuesta no-JSON */ }
    if (!r.ok) return { ok: false, error: json?.error || text || `HTTP ${r.status}` };
    if (!json || typeof json.ok === "undefined") return { ok: false, error: "Respuesta inválida del servidor." };
    return json;
  }

  function showMsg(sel, type, text) {
    const el = qs(sel); if (!el) return;
    const map = { success: "alert-success", error: "alert-danger", warning: "alert-warning", info: "alert-info" };
    el.className = `alert ${map[type] || "alert-secondary"} mt-2`;
    el.setAttribute("role", "status");
    el.setAttribute("aria-live", type === "error" ? "assertive" : "polite");
    el.textContent = text;
    el.classList.remove("d-none");
  }
  function clearMsg(sel) {
    const el = qs(sel); if (!el) return;
    el.className = "d-none";
    el.textContent = "";
    el.removeAttribute("role");
    el.removeAttribute("aria-live");
  }

  function setLoading(btn, isLoading, textWhenLoading = "Procesando...") {
    if (!btn) return;
    if (isLoading) {
      if (!btn.dataset._orig) btn.dataset._orig = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>${textWhenLoading}`;
    } else {
      btn.disabled = false;
      if (btn.dataset._orig) btn.innerHTML = btn.dataset._orig;
      delete btn.dataset._orig;
    }
  }

  const debounce = (fn, ms = 300) => {
    let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
  };

  /* ----------------------------- Wizard ----------------------------- */
  const STEPS = 6;
  // Importante: el paso 3 es Plan y el 4 es SIM (nuevo orden)
  const stepNames = { 1: "Cobertura", 2: "IMEI", 3: "Plan", 4: "Tipo de SIM", 5: "Tipo de línea", 6: "Confirmación" };
  const PROGRESS = [0, 16, 33, 50, 66, 100];
  let currentStep = 1;

  function focusFirstInput(step) {
    const cont = qsa(".wiz-step").find(c => Number(c.dataset.step) === step);
    if (!cont) return;
    const first = cont.querySelector("input,select,button,textarea,[tabindex]");
    if (first) first.focus({ preventScroll: true });
  }

  function animateProgress(toPct) {
    const bar = qs("#wizProgress");
    if (!bar) return;
    bar.style.transition = "width .25s ease";
    bar.style.width = `${toPct}%`;
  }

  function showStep(n) {
    qsa(".wiz-step").forEach(c => c.classList.toggle("d-none", Number(c.dataset.step) !== n));
    qs("#wizStepNum").textContent = n;
    qs("#wizStepName").textContent = stepNames[n];
    animateProgress(PROGRESS[n - 1] ?? (n * (100 / STEPS)));
    currentStep = n;
    focusFirstInput(n);
    document.body.classList.toggle("has-wizard-footer", n >= 1 && n <= 6);
  }

  qsa(".btn-prev").forEach(b => b.addEventListener("click", () => showStep(Math.max(1, currentStep - 1))));

  const state = {
    cp: "", cobertura_ok: false,
    imei: "", banda28_ok: false, acepta_esim: false,
    planes: [], cv_plan: null,
    tipo_sim: "fisica", icc: "", esim_qr_id: null, esim_qr_img: null, esim_expira_min: null, esim_qr_text: "",
    resultados: { preactiva: null, porta: null },
    msisdn: ""
  };

  /* ------------------------- Paso 1: Cobertura ------------------------- */
  const inpCP = qs("#inp_cp");
  const btnCob = qs("#btn_validar_cp");
  const btnNextCP = qs("#next_from_cp");

  inpCP?.addEventListener("input", debounce(() => {
    const cp = inpCP.value.replace(/\D/g, "").slice(0, 5);
    inpCP.value = cp;
    clearMsg("#msg_cp");
    btnNextCP && (btnNextCP.disabled = true);
  }, 250));

  btnCob?.addEventListener("click", async () => {
    const cp = inpCP.value.replace(/\D/g, "").slice(0, 5);
    inpCP.value = cp;
    if (!/^\d{5}$/.test(cp)) return showMsg("#msg_cp", "warning", "Ingresa un código postal válido (5 dígitos).");
    showMsg("#msg_cp", "info", "Validando cobertura...");
    setLoading(btnCob, true, "Validando...");
    const r = await postJSON("wlactivalotu/validarCobertura", { cp });
    setLoading(btnCob, false);
    if (!r.ok) return showMsg("#msg_cp", "error", r.error);
    if (r.data.cobertura) {
      state.cp = cp; state.cobertura_ok = true;
      showMsg("#msg_cp", "success", "Cobertura disponible.");
      btnNextCP && (btnNextCP.disabled = false);
    } else {
      showMsg("#msg_cp", "warning", "No hay cobertura en tu zona.");
    }
  });

  btnNextCP?.addEventListener("click", () => showStep(2));

  /* --------------------------- Paso 2: IMEI --------------------------- */
  const inpIMEI = qs("#inp_imei");
  const btnIMEI = qs("#btn_validar_imei");
  const btnNextIMEI = qs("#next_from_imei");

  inpIMEI?.addEventListener("input", debounce(() => {
    const v = inpIMEI.value.replace(/\D/g, "").slice(0, 16);
    inpIMEI.value = v;
    clearMsg("#msg_imei");
    btnNextIMEI && (btnNextIMEI.disabled = true);
  }, 250));

  btnIMEI?.addEventListener("click", async () => {
    const imei = inpIMEI.value.replace(/\D/g, "");
    inpIMEI.value = imei;
    if (!/^\d{14,16}$/.test(imei)) return showMsg("#msg_imei", "warning", "IMEI inválido (14–16 dígitos).");
    showMsg("#msg_imei", "info", "Validando IMEI...");
    setLoading(btnIMEI, true, "Validando...");
    const r = await postJSON("wlactivalotu/validarImei", { imei });
    setLoading(btnIMEI, false);

    if (!r.ok) return showMsg("#msg_imei", "error", r.error || "Error al validar IMEI.");

    state.imei = imei;
    state.banda28_ok = !!r.data?.compatible_banda28;
    state.acepta_esim = !!r.data?.acepta_esim;

    if (!state.banda28_ok) {
      btnNextIMEI && (btnNextIMEI.disabled = true);
      return showMsg("#msg_imei", "error", "Tu dispositivo no es compatible con Banda 28.");
    }

    showMsg("#msg_imei", "success",
      state.acepta_esim ? "Compatible con Banda 28 y eSIM." : "Compatible con Banda 28 (sin eSIM)."
    );
    btnNextIMEI && (btnNextIMEI.disabled = false);

    await cargarPlanes();
    showStep(3); // ahora vamos a Plan
  });

  btnNextIMEI?.addEventListener("click", async () => { await cargarPlanes(); showStep(3); });

  /* --------------------------- Paso 3: Plan --------------------------- */
  const scroller = qs("#planes_scroller");
  const btnPrev  = qs("#planes_prev");
  const btnNext  = qs("#planes_next");
  const btnNextPlan = qs("#next_from_plan");

  async function cargarPlanes() {
    if (!scroller) return;
    scroller.innerHTML = `
      <div class="d-flex align-items-center gap-2 text-muted">
        <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
        <span>Cargando planes...</span>
      </div>`;

    const r = await postJSON("wlactivalotu/listarPlanes");
    if (!r.ok) { scroller.innerHTML = `<div class="text-danger">${r.error}</div>`; return; }
    const planes = (r.data.planes || []).filter(p => Number(p.primar_secundaria) === 1);
    if (!planes.length) { scroller.innerHTML = `<div class="text-warning">No hay planes de activación.</div>`; return; }

    state.planes = planes;
    scroller.innerHTML = "";

    planes.forEach(p => {
      const el = document.createElement("div");
      el.className = "card shadow-sm flex-shrink-0 text-center plan-card";
      el.style.cssText = "width:240px;scroll-snap-align:center;cursor:pointer;";
      el.setAttribute("data-cvplan", p.cv_plan);
      el.setAttribute("tabindex", "0");
      el.innerHTML = `
        ${p.imagen ? `<img src="${p.imagen}" class="card-img-top" alt="${p.nombre}" style="height:120px;object-fit:cover;border-bottom:1px solid #eee;">` : ""}
        <div class="card-body p-2">
          <strong>${p.nombre}</strong><br>
          <span class="text-success">$${Number(p.precio).toFixed(2)}</span>
        </div>`;

      const select = () => {
        qsa(".plan-card").forEach(x => x.classList.remove("border", "border-primary", "shadow"));
        el.classList.add("border", "border-primary", "shadow");
        scroller.dataset.selected = String(p.cv_plan);
        state.cv_plan = p.cv_plan;
        btnNextPlan && (btnNextPlan.disabled = false);
      };

      el.addEventListener("click", select);
      el.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === " ") { e.preventDefault(); select(); }
      });

      scroller.appendChild(el);
    });

    // navegación del carrusel
    const gap = 12;
    const cardWidth = () => {
      const card = qs("#planes_scroller .card");
      return card ? card.getBoundingClientRect().width + gap : 252;
    };
    btnPrev.onclick = () => scroller.scrollBy({ left: -cardWidth(), behavior: "smooth" });
    btnNext.onclick = () => scroller.scrollBy({ left:  cardWidth(), behavior: "smooth" });

    // rueda vertical -> scroll horizontal
    scroller.addEventListener("wheel", (e) => {
      if (Math.abs(e.deltaX) < Math.abs(e.deltaY)) {
        scroller.scrollLeft += e.deltaY;
        e.preventDefault();
      }
    }, { passive: false });

    btnNextPlan && (btnNextPlan.disabled = !scroller.dataset.selected);
  }

  btnNextPlan?.addEventListener("click", async () => {
    // Configura visibilidad de eSIM en base al equipo
    const esimOption = qs("#esim_option");
    if (esimOption) state.acepta_esim ? esimOption.classList.remove("d-none") : esimOption.classList.add("d-none");

    await prepararPaso4();
    showStep(4);
  });

  /* ---------------------- Paso 4: Tipo de SIM / QR --------------------- */
  function activarModoSim(tipo) {
    state.tipo_sim = tipo;
    const pf = qs("#panel_sim_fisica");
    const pe = qs("#panel_sim_esim");
    if (tipo === "fisica") {
      pf?.classList.remove("d-none");
      pe?.classList.add("d-none");
      qs("#next_from_sim").disabled = !state.icc;
    } else {
      pf?.classList.add("d-none");
      pe?.classList.remove("d-none");
      qs("#next_from_sim").disabled = !(state.icc && state.esim_qr_id);
    }
  }

  async function prepararPaso4() {
    // reset de selección SIM/esim
    state.icc = "";
    state.esim_qr_id = null;
    state.esim_qr_img = null;
    state.esim_qr_text = "";
    qs("#esim_qr_box")?.classList.add("d-none");

    const selected = qs('input[name="tipo_sim"]:checked')?.value || "fisica";
    activarModoSim(selected);
    if (state.tipo_sim === "fisica") await cargarICCs(1);
    else await cargarICCs(2);
  }

  qsa('input[name="tipo_sim"]').forEach(r => r.addEventListener("change", async () => {
    activarModoSim(qs('input[name="tipo_sim"]:checked').value);
    state.icc = ""; state.esim_qr_id = null; state.esim_qr_img = null; state.esim_qr_text = "";
    qs("#esim_qr_box")?.classList.add("d-none");
    if (state.tipo_sim === "fisica") await cargarICCs(1); else await cargarICCs(2);
    const iccPort = qs("#inp_icc_porta"); if (iccPort) iccPort.value = "";
  }));

  async function cargarICCs(tipo) {
    const isEsim = tipo === 2;
    const sel = isEsim ? qs("#sel_icc_esim") : qs("#sel_icc");
    const msgSel = isEsim ? "#msg_icc_esim" : "#msg_icc";
    if (!sel) return;

    sel.innerHTML = `<option value="" selected disabled>Cargando...</option>`;
    state.icc = "";
    qs("#next_from_sim").disabled = true;
    if (isEsim) { qs("#btn_generar_esim").disabled = true; qs("#esim_qr_box").classList.add("d-none"); }

    const r = await postJSON("wlactivalotu/listarIccs", { tipo_sim: tipo });
    if (!r.ok) { showMsg(msgSel, "error", r.error); sel.innerHTML = `<option>Error</option>`; return; }

    const list = r.data.iccs || [];
    if (!list.length) {
      sel.innerHTML = `<option value="" selected disabled>${isEsim ? "Sin eSIM" : "Sin ICCs"}</option>`;
      return showMsg(msgSel, "warning", isEsim ? "Sin inventario eSIM." : "Sin inventario de SIM física.");
    }

    clearMsg(msgSel);
    sel.innerHTML = [`<option value="" selected disabled>Selecciona...</option>`]
      .concat(list.map(i => `<option value="${i.icc}" data-msisdn="${i.msisdn}">${i.icc} - ${i.msisdn} — ${i.almacen} (${i.status})</option>`))
      .join("");
  }

  qs("#sel_icc")?.addEventListener("change", (ev) => {

    const sel = ev.target;
    const option = sel.options[sel.selectedIndex];

    state.icc = option.value || "";
    state.msisdn = option.dataset.msisdn || ""; 

    qs("#next_from_sim").disabled = !state.icc;
    const iccPort = qs("#inp_icc_porta"); if (iccPort && state.icc) iccPort.value = state.icc;
  });

  qs("#sel_icc_esim")?.addEventListener("change", (ev) => {
    state.icc = ev.target.value || "";
    qs("#btn_generar_esim").disabled = !state.icc;
    const iccPort = qs("#inp_icc_porta"); if (iccPort && state.icc) iccPort.value = state.icc;
  });

  // Generar y mostrar QR eSIM — requiere PLAN seleccionado
  qs("#btn_generar_esim")?.addEventListener("click", async (ev) => {
    if (!state.icc) return;
    if (!state.cv_plan) return showMsg("#msg_icc_esim", "warning", "Selecciona primero un plan.");

    const btn = ev.currentTarget;
    clearMsg("#msg_icc_esim");
    qs("#esim_qr_box").classList.add("d-none");
    qs("#next_from_sim").disabled = true;

    setLoading(btn, true, "Generando...");
    const r = await postJSON("wlactivalotu/generarEsim", { icc: state.icc, cv_plan: state.cv_plan });
    setLoading(btn, false);

    if (!r.ok) return showMsg("#msg_icc_esim", "error", r.error);

    state.esim_qr_id      = r.data.qr_id || null;
    state.esim_qr_img     = r.data.qr_img_url || "";
    state.esim_expira_min = r.data.expira_en_min || null;
    state.esim_qr_text    = r.data.qr_text || "";

    const img  = qs("#esim_qr_img");
    const info = qs("#esim_qr_info");
    if (img)  img.src = state.esim_qr_img;
    if (info) info.textContent = `QR de ${state.icc}${state.esim_expira_min ? ` • expira en ${state.esim_expira_min} min` : ""}.`;

    // Bloque opcional para copiar texto del perfil (si viene)
    let wrap = qs("#esim_qr_text_wrap"), txt = qs("#esim_qr_text");
    if (!wrap) {
      const box = qs("#esim_qr_box");
      wrap = document.createElement("div");
      wrap.id = "esim_qr_text_wrap";
      wrap.className = "mt-2";
      wrap.innerHTML = `
        <div class="input-group">
          <input id="esim_qr_text" class="form-control form-control-sm" readonly>
          <button id="esim_qr_copy" class="btn btn-outline-secondary btn-sm" type="button">Copiar</button>
        </div>
        <small class="text-muted">Código del perfil eSIM (si tu equipo lo requiere).</small>`;
      box?.appendChild(wrap);
      txt = qs("#esim_qr_text");
    }
    if (state.esim_qr_text) {
      wrap.classList.remove("d-none");
      txt.value = state.esim_qr_text;
    } else {
      wrap.classList.add("d-none");
      if (txt) txt.value = "";
    }

    qs("#esim_qr_copy")?.addEventListener("click", () => {
      if (!txt?.value) return;
      navigator.clipboard.writeText(txt.value)
        .then(() => showMsg("#msg_icc_esim", "success", "Código copiado al portapapeles."))
        .catch(() => showMsg("#msg_icc_esim", "error", "No se pudo copiar."));
    }, { once: true });

    qs("#esim_qr_box").classList.remove("d-none");
    qs("#next_from_sim").disabled = false;
  });

  qs("#next_from_sim")?.addEventListener("click", () => showStep(5));

  /* --------------------- Paso 5: Nueva o Portabilidad --------------------- */
  function togglePorta() {
    const isPorta = qs('input[name="tipo_linea"]:checked').value === "portabilidad";
    qs("#panel_porta")?.classList.toggle("d-none", !isPorta);
    qs("#btn_preactivar_nueva")?.classList.toggle("d-none", isPorta);
    qs("#btn_solicitar_porta")?.classList.toggle("d-none", !isPorta);
  }
  qsa('input[name="tipo_linea"]').forEach(r => r.addEventListener("change", togglePorta));
  togglePorta();

  const numPorta = qs("#inp_numero_porta");
  const nipPorta = qs("#inp_nip_porta");
  const iccPorta = qs("#inp_icc_porta");

  numPorta?.addEventListener("input", () => { numPorta.value = numPorta.value.replace(/\D/g, "").slice(0, 10); });
  nipPorta?.addEventListener("input", () => { nipPorta.value = nipPorta.value.replace(/\D/g, "").slice(0, 6); });
  iccPorta?.addEventListener("input", () => { iccPorta.value = iccPorta.value.replace(/\D/g, ""); });

  qs("#btn_preactivar_nueva")?.addEventListener("click", async (ev) => {
    const btn = ev.currentTarget;
    const tipoSim = qs('input[name="tipo_sim"]:checked').value;
    const icc = state.icc || "";
    const cv_plan = state.cv_plan || "";
    const msisdn = state.msisdn || "";

    if (!cv_plan) return alert("Selecciona un plan.");
    if (!icc)     return alert("Selecciona una SIM.");

    showMsg("#panel_result_new", "info", "Revisando...");
    setLoading(btn, true);
    const r = await postJSON("wlactivalotu/preactivarNueva", { tipo_sim: tipoSim, icc, cv_plan, msisdn });
    setLoading(btn, false);
    if (!r.ok) return showMsg("#panel_result_new", "error", r.error);

    state.resultados.preactiva = r.data;
    showMsg("#panel_result_new", "success", r.data.instrucciones || "Revision exitosa.");
    prepararConfirmacion();
    showStep(6);
  });

  qs("#btn_solicitar_porta")?.addEventListener("click", async (ev) => {
    const btn = ev.currentTarget;
    const tipoSim = qs('input[name="tipo_sim"]:checked').value;
    const iccIn   = (iccPorta.value.trim() || state.icc || "");
    const cv_plan = state.cv_plan || "";
    const numero  = (numPorta.value || "").trim();
    const nip     = (nipPorta.value || "").trim();
    const nombre  = (qs("#inp_nombre_cliente").value || "").trim();
    const correo  = (qs("#inp_correo_cliente").value || "").trim();

    if (!/^\d{10}$/.test(numero)) return showMsg("#msg_porta", "error", "Número debe tener 10 dígitos.");
    if (!/^\d{4,6}$/.test(nip))   return showMsg("#msg_porta", "error", "NIP inválido (4–6 dígitos).");
    if (nombre.length < 3)        return showMsg("#msg_porta", "error", "Nombre del cliente demasiado corto.");
    if (iccIn.replace(/\D/g, "").length < 18) return showMsg("#msg_porta", "error", "ICCID parece inválido (18+).");

    showMsg("#msg_porta", "info", "Enviando solicitud...");
    setLoading(btn, true, "Enviando...");
    const r = await postJSON("wlactivalotu/solicitarPortabilidad", {
      tipo_sim: tipoSim, icc: iccIn, cv_plan, numero, nip,
      nombre_cliente: nombre, correo_cliente: correo
    });
    setLoading(btn, false);
    if (!r.ok) return showMsg("#msg_porta", "error", r.error);

    state.resultados.porta = r.data;
    showMsg("#panel_result_porta", "success", r.data.mensaje || "Solicitud enviada.");
    prepararConfirmacion();
    showStep(6);
  });

  function prepararConfirmacion() {
    const simTxt = state.tipo_sim === "fisica" ? `SIM Física (ICC: ${state.icc || "-"})` : `eSIM (ICC: ${state.icc || "-"})`;
    const tipoLineaTxt = qs('input[name="tipo_linea"]:checked').value === "portabilidad" ? "Portabilidad" : "Nueva línea";
    const planId = state.cv_plan || "-";
    const paso_dn = state.msisdn || "-";
    qs("#resumen_confirm").innerHTML = `
      <ul class="list-group">
        <li class="list-group-item"><strong>CP:</strong> ${qs("#inp_cp").value}</li>
        <li class="list-group-item"><strong>IMEI:</strong> ${qs("#inp_imei").value}</li>
        <li class="list-group-item"><strong>Plan ID:</strong> ${planId}</li>
        <li class="list-group-item"><strong>SIM:</strong> ${simTxt}</li>
        <li class="list-group-item"><strong>Tipo de línea:</strong> ${tipoLineaTxt}</li>
        <li class="list-group-item"><strong>MSISDN:</strong> ${paso_dn}</li>
      </ul>`;
  }

  /* ------------------------- Paso 6: Confirmar ------------------------- */
  qs("#btn_confirmar")?.addEventListener("click", async (ev) => {
    const btn = ev.currentTarget;
    showMsg("#msg_confirm", "info", "Registrando...");
    setLoading(btn, true, "Registrando...");
    const r = await postJSON("wlactivalotu/confirmarActivacion", {
      tipo_linea: qs('input[name="tipo_linea"]:checked').value,
      tipo_sim: state.tipo_sim,
      icc: state.icc || "",
      cv_plan: state.cv_plan || "",
      cp: qs("#inp_cp").value,
      imei: qs("#inp_imei").value,
      meta: state.resultados,
      msisdn: state.msisdn || ""
    });
    setLoading(btn, false);
    if (!r.ok) return showMsg("#msg_confirm", "error", r.error);
    showMsg("#msg_confirm", "success", r.data.mensaje || "Confirmado.");
  });

  /* ----------------------------- Init ----------------------------- */
  window.addEventListener("DOMContentLoaded", () => { showStep(1); });
})();
