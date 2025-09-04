<?php
/**
 * Centro de Ayuda (drop-in)
 * Requisitos:
 * - Bootstrap 5 (CSS + JS) ya cargado en tu layout (o deja las 2 líneas CDN de abajo)
 * - (Opcional) Font Awesome para el ícono del botón
 *
 * Uso:
 * include __DIR__ . '/centro_ayuda.php';
 * Luego, en tus inputs agrega data-help="id" (ver ejemplos al final).
 */
?>
<!-- Bootstrap (si YA lo cargas en tu layout, puedes quitar estas 2 líneas) -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- (Opcional) Font Awesome para icono de ayuda -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet"/>

<style>
  /* Estilos mínimos del Centro de Ayuda */
  #btnAyudaFlotante {
    position: fixed; right: 18px; bottom: 18px; width:56px; height:56px; z-index: 1050;
  }
  .help-highlight { background: yellow; padding: 0 .15rem; }
  #helpSearch:focus { box-shadow: 0 0 0 .2rem rgba(13,110,253,.25); }
</style>

<!-- Botón flotante de ayuda -->
<button id="btnAyudaFlotante" type="button" class="btn btn-primary rounded-circle shadow"
        aria-label="Centro de ayuda" title="Centro de ayuda (F1)">
  <i class="fa-solid fa-circle-question"></i>
</button>

<!-- Modal Centro de Ayuda -->
<div class="modal fade" id="modalAyuda" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Centro de ayuda · Registro de dispositivos</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <!-- Buscador -->
        <div class="input-group mb-3">
          <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
          <input id="helpSearch" type="search" class="form-control" placeholder="Buscar: cámara, switch, MAC, alarma, etc.">
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs" id="helpTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-guias" data-bs-toggle="tab" data-bs-target="#pane-guias" type="button" role="tab">Guías rápidas</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-dispositivos" data-bs-toggle="tab" data-bs-target="#pane-dispositivos" type="button" role="tab">Dispositivos</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-faq" data-bs-toggle="tab" data-bs-target="#pane-faq" type="button" role="tab">FAQ</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-atajos" data-bs-toggle="tab" data-bs-target="#pane-atajos" type="button" role="tab">Atajos</button>
          </li>
        </ul>

        <div class="tab-content pt-3">
          <!-- Guías -->
          <div class="tab-pane fade show active" id="pane-guias" role="tabpanel" aria-labelledby="tab-guias">
            <div class="accordion" id="accGuias">
              <div class="accordion-item help-item">
                <h2 class="accordion-header">
                  <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#g1">
                    Completar un registro correctamente (checklist)
                  </button>
                </h2>
                <div id="g1" class="accordion-collapse collapse show" data-bs-parent="#accGuias">
                  <div class="accordion-body">
                    <ol class="mb-2">
                      <li>Selecciona <strong>Equipo</strong> correcto (cámara, switch, alarma, monitor).</li>
                      <li>Rellena campos obligatorios (<span class="text-danger">*</span>).</li>
                      <li>Para <strong>cámaras/switch</strong>, valida <code>IP</code> y <code>MAC</code> con formato correcto.</li>
                      <li>Adjunta evidencia (foto/serie) si aplica.</li>
                      <li>Guarda y verifica la notificación en el panel.</li>
                    </ol>
                    <div class="small text-muted">Tip: pulsa <kbd>F1</kbd> para abrir esta ayuda desde cualquier campo.</div>
                  </div>
                </div>
              </div>

              <div class="accordion-item help-item">
                <h2 class="accordion-header">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#g2">
                    Campos que se ocultan/mostrar según el equipo
                  </button>
                </h2>
                <div id="g2" class="accordion-collapse collapse" data-bs-parent="#accGuias">
                  <div class="accordion-body">
                    <ul class="mb-0">
                      <li><strong>Switch:</strong> ocultar <em>MAC</em> y <em>IP</em> si así lo definiste; mostrar <em>modelo, puertos</em>.</li>
                      <li><strong>Alarma:</strong> <em>no</em> mostrar <em>MAC</em>/<em>IP</em>; sí <em>zona</em>, <em>partición</em>, <em>panel</em>.</li>
                      <li><strong>Monitor:</strong> ocultar <em>Switch, IP, MAC, No. Puerto, IDE, IDE Password</em>.</li>
                    </ul>
                  </div>
                </div>
              </div>

            </div>
          </div>

          <!-- Dispositivos -->
          <div class="tab-pane fade" id="pane-dispositivos" role="tabpanel" aria-labelledby="tab-dispositivos">
            <div class="accordion" id="accDispositivos">
              <div class="accordion-item help-item" data-tags="camara ip onvif poe nvr dvr resolucion">
                <h2 class="accordion-header">
                  <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#d-camara">
                    Cámara
                  </button>
                </h2>
                <div id="d-camara" class="accordion-collapse collapse show" data-bs-parent="#accDispositivos">
                  <div class="accordion-body">
                    <ul>
                      <li>IP válida: <code>0-255.0-255.0-255.0-255</code> (ej. <code>192.168.1.25</code>).</li>
                      <li>MAC: <code>XX:XX:XX:XX:XX:XX</code> (hexadecimal).</li>
                      <li>Indica <strong>resolución</strong>, <strong>lente</strong> y si es <strong>PoE</strong>.</li>
                      <li>Relaciona con <strong>NVR/DVR</strong> y puerto/CH si aplica.</li>
                    </ul>
                  </div>
                </div>
              </div>

              <div class="accordion-item help-item" data-tags="switch poe capa2 capa3 puertos vlan mac">
                <h2 class="accordion-header">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#d-switch">
                    Switch
                  </button>
                </h2>
                <div id="d-switch" class="accordion-collapse collapse" data-bs-parent="#accDispositivos">
                  <div class="accordion-body">
                    <ul>
                      <li>Especifica <strong>modelo</strong>, <strong>nº de puertos</strong> y si es <strong>PoE</strong>.</li>
                      <li>Si tu flujo lo requiere, <em>oculta</em> campos IP/MAC para este equipo.</li>
                      <li>Documenta <strong>VLAN</strong> y puerto hacia <em>uplink</em>.</li>
                    </ul>
                  </div>
                </div>
              </div>

              <div class="accordion-item help-item" data-tags="alarma zona particion panel teclado sensor cm dh drc em">
                <h2 class="accordion-header">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#d-alarma">
                    Sistema de alarma
                  </button>
                </h2>
                <div id="d-alarma" class="accordion-collapse collapse" data-bs-parent="#accDispositivos">
                  <div class="accordion-body">
                    <ul>
                      <li>Campos clave: <strong>Zona</strong>, <strong>Partición</strong>, <strong>Tipo de sensor</strong> (CM, DH, DRC, EM...).</li>
                      <li>No se requieren <strong>IP/MAC</strong> en este tipo de equipo.</li>
                      <li>Adjunta foto/plano si ayuda al mantenimiento.</li>
                    </ul>
                  </div>
                </div>
              </div>

              <div class="accordion-item help-item" data-tags="monitor pantalla hdmi vga energia">
                <h2 class="accordion-header">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#d-monitor">
                    Monitor
                  </button>
                </h2>
                <div id="d-monitor" class="accordion-collapse collapse" data-bs-parent="#accDispositivos">
                  <div class="accordion-body">
                    <ul>
                      <li>Especifica <strong>tamaño</strong>, <strong>conexión</strong> (HDMI/VGA/DP) y <strong>ubicación</strong>.</li>
                      <li>Oculta <em>Switch, IP, MAC, No. Puerto, IDE, IDE Password</em> en este caso.</li>
                    </ul>
                  </div>
                </div>
              </div>

            </div>
          </div>

          <!-- FAQ -->
          <div class="tab-pane fade" id="pane-faq" role="tabpanel" aria-labelledby="tab-faq">
            <div class="accordion" id="accFAQ">
              <div class="accordion-item help-item" data-tags="errores validacion campos obligatorios">
                <h2 class="accordion-header">
                  <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#f1">
                    Me marca error al guardar, ¿qué reviso?
                  </button>
                </h2>
                <div id="f1" class="accordion-collapse collapse show" data-bs-parent="#accFAQ">
                  <div class="accordion-body">
                    <ul>
                      <li>Campos obligatorios vacíos.</li>
                      <li>Formato incorrecto (IP, MAC, sólo números/letras).</li>
                      <li>Duplicidad de serie o determinante/sucursal.</li>
                    </ul>
                  </div>
                </div>
              </div>

              <div class="accordion-item help-item" data-tags="imagenes tamaño peso dompdf memoria pdf">
                <h2 class="accordion-header">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#f2">
                    El PDF se rompe o usa mucha memoria
                  </button>
                </h2>
                <div id="f2" class="accordion-collapse collapse" data-bs-parent="#accFAQ">
                  <div class="accordion-body">
                    <div>Reduce resolución y peso de imágenes; usa paginación si exportas listados grandes.</div>
                  </div>
                </div>
              </div>

            </div>
          </div>

          <!-- Atajos -->
          <div class="tab-pane fade" id="pane-atajos" role="tabpanel" aria-labelledby="tab-atajos">
            <ul class="mb-0">
              <li><kbd>F1</kbd>: Abrir ayuda.</li>
              <li><kbd>Ctrl</kbd> + <kbd>/</kbd>: enfocarse al buscador de ayuda.</li>
              <li><kbd>Esc</kbd>: cerrar ayuda.</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <small class="text-muted me-auto">¿Falta algo? Puedes ampliar este contenido desde tu código.</small>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const ayudaModalEl  = document.getElementById('modalAyuda');
  const ayudaModal    = new bootstrap.Modal(ayudaModalEl);
  const btnAyuda      = document.getElementById('btnAyudaFlotante');
  const inputSearch   = document.getElementById('helpSearch');
  const helpItems     = Array.from(document.querySelectorAll('.help-item'));

  function normalize(s){ return (s||'').toLowerCase(); }
  function clearHighlights(container){
    container.querySelectorAll('.help-highlight').forEach(span=>{
      const text = document.createTextNode(span.textContent);
      span.replaceWith(text);
    });
  }
  function highlightMatches(container, query){
    if (!query) return;
    const nodes = container.querySelectorAll('p,li,div,button,h2');
    nodes.forEach(node=>{
      // limpiar previas
      clearHighlights(node);
      const txt = node.textContent;
      const idx = txt.toLowerCase().indexOf(query.toLowerCase());
      if (idx >= 0) {
        const before = txt.slice(0, idx);
        const match  = txt.slice(idx, idx + query.length);
        const after  = txt.slice(idx + query.length);
        node.innerHTML = `${before}<span class="help-highlight">${match}</span>${after}`;
      }
    });
  }

  // Abrir con botón
  btnAyuda.addEventListener('click', () => {
    ayudaModal.show();
    setTimeout(() => inputSearch?.focus(), 200);
  });

  // F1 abre ayuda (evita abrir ayuda al escribir en inputs si el navegador intercepta)
  document.addEventListener('keydown', (e) => {
    if (e.key === 'F1') {
      e.preventDefault();
      ayudaModal.show();
      setTimeout(() => inputSearch?.focus(), 200);
    }
  });

  // Ctrl + / para ir al buscador
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === '/') {
      if (!document.body.classList.contains('modal-open')) ayudaModal.show();
      setTimeout(() => inputSearch?.focus(), 200);
    }
  });

  // Filtrado en vivo
  inputSearch.addEventListener('input', () => {
    const q = normalize(inputSearch.value.trim());
    helpItems.forEach(item => {
      clearHighlights(item);
      if (!q) { item.classList.remove('d-none'); return; }
      const text = normalize(item.innerText);
      const tags = normalize(item.getAttribute('data-tags'));
      const match = text.includes(q) || (tags && tags.includes(q));
      item.classList.toggle('d-none', !match);
      if (match) highlightMatches(item, q);
    });
  });

  // Mostrar una única vez tip inicial
  (function showOnce(){
    const KEY = 'ayuda_tip_inicial_mostrado_v1';
    try {
      if (!localStorage.getItem(KEY)) {
        setTimeout(()=>ayudaModal.show(), 600);
        localStorage.setItem(KEY, '1');
      }
    } catch(_) {}
  })();

  // Ayuda contextual por campo (usa data-help="id-seccion")
  document.addEventListener('focusin', (e) => {
    const t = e.target;
    const helpAnchor = t?.dataset?.help;
    if (!helpAnchor) return;

    if (!document.body.classList.contains('modal-open')) {
      ayudaModal.show();
    }

    if (helpAnchor.startsWith('d-')) {
      document.querySelector('#tab-dispositivos')?.click();
    }

    setTimeout(() => {
      const section = document.getElementById(helpAnchor);
      if (section && section.classList.contains('accordion-collapse')) {
        const collapse = new bootstrap.Collapse(section, { toggle: false });
        collapse.show();
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
      } else if (section) {
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }, 180);
  });
})();
</script>

<!-- ===== Ejemplos de cómo “enganchar” campos (puedes borrar esta sección si ya entendiste) ===== -->
<!--
<input type="text" name="ip" class="form-control" placeholder="192.168.1.25" data-help="d-camara">
<input type="text" name="mac" class="form-control" placeholder="00:11:22:33:44:55" data-help="d-camara">
<input type="number" name="zona_alarma" class="form-control" placeholder="Ej: 1" data-help="d-alarma">
<input type="text" name="modelo_switch" class="form-control" placeholder="Modelo" data-help="d-switch">
-->
