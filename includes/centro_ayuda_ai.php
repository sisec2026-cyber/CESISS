<?php
// includes/centro_ayuda_ai.php
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet"/>

<style>
  #btnAyudaAI { position:fixed; right:18px; bottom:88px; width:56px; height:56px; z-index:1050; }
  .ai-chat { max-height: 52vh; overflow:auto; background:#0b1720; color:#e8f1f3; border-radius:.5rem; padding:1rem; }
  .ai-msg { margin-bottom: .75rem; }
  .ai-msg.user    { text-align: right; }
  .ai-msg.user .bubble { display:inline-block; background:#1e3a8a; color:#fff; padding:.5rem .75rem; border-radius: .75rem; }
  .ai-msg.bot     { text-align: left; }
  .ai-msg.bot .bubble  { display:inline-block; background:#0f2a33; color:#d6eef4; padding:.5rem .75rem; border-radius: .75rem; }
  .ai-tools { gap:.5rem; flex-wrap: wrap; }
  .ai-tools .btn { padding: .25rem .5rem; }
</style>

<button id="btnAyudaAI" class="btn btn-primary rounded-circle shadow" title="Ayuda con IA (F1)">
  <i class="fa-solid fa-robot"></i>
</button>

<div class="modal fade" id="modalAyudaAI" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-robot me-2"></i>Ayuda con IA · Registro</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="small text-muted mb-2">
          Pregunta cómo capturar, valida formatos, o pídele que te ayude a identificar el tipo de dispositivo.
        </div>

        <!-- Sugerencias rápidas -->
        <div class="d-flex ai-tools mb-3">
          <button class="btn btn-outline-secondary btn-sm" data-suggest="No ubico el tipo de dispositivo; ayúdame a clasificarlo con 2 opciones y por qué.">Clasificar dispositivo</button>
          <button class="btn btn-outline-secondary btn-sm" data-suggest="¿Cómo debo capturar IP y MAC para este equipo? Dame ejemplos.">Captura IP/MAC</button>
          <button class="btn btn-outline-secondary btn-sm" data-suggest="Estoy registrando una alarma; ¿qué campos mínimos debo llenar?">Checklist Alarma</button>
          <button class="btn btn-outline-secondary btn-sm" data-suggest="Estoy registrando un switch; ¿qué me recomiendas llenar y qué no aplica?">Checklist Switch</button>
        </div>

        <!-- Chat -->
        <div id="aiChat" class="ai-chat mb-3">
          <div class="ai-msg bot"><div class="bubble">Hola 👋, soy tu ayuda de captura. Cuéntame qué necesitas y te guío paso a paso.</div></div>
        </div>

        <div class="input-group">
          <input id="aiInput" type="text" class="form-control" placeholder="Escribe tu duda… (Enter para enviar)">
          <button id="aiSend" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i></button>
        </div>

        <div class="form-text mt-2">Tip: Usa <kbd>F1</kbd> para abrir la ayuda, y selecciona campo del formulario para recibir ayuda contextual.</div>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const modalEl = document.getElementById('modalAyudaAI');
  const modal   = new bootstrap.Modal(modalEl);
  const btn     = document.getElementById('btnAyudaAI');
  const chat    = document.getElementById('aiChat');
  const input   = document.getElementById('aiInput');
  const sendBtn = document.getElementById('aiSend');

  function open() { modal.show(); setTimeout(()=>input.focus(), 150); }
  btn.addEventListener('click', open);
  document.addEventListener('keydown', e => { if (e.key === 'F1') { e.preventDefault(); open(); } });

  // Sugerencias
  document.querySelectorAll('[data-suggest]').forEach(b=>{
    b.addEventListener('click', ()=> {
      input.value = b.dataset.suggest || '';
      input.focus();
    });
  });

  function addMsg(role, text){
    const div = document.createElement('div');
    div.className = 'ai-msg ' + (role === 'user' ? 'user' : 'bot');
    div.innerHTML = `<div class="bubble">${escapeHtml(text)}</div>`;
    chat.appendChild(div);
    chat.scrollTop = chat.scrollHeight;
  }
  function escapeHtml(s){
    return (s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  }

  // Obtiene estado relevante del formulario (ajusta IDs si cambian)
  function getFormState(){
    const $ = sel => document.querySelector(sel);
    const val = sel => { const el = $(sel); return el ? el.value.trim() : null; };
    return {
      equipo:       val('#equipo'),
      marca:        val('#marca') || val('#marcaManual'),
      modelo:       val('#modelo'),
      ip:           val('#ipInput') || val('input[name="ip"]'),
      mac:          val('#macInput') || val('input[name="mac"]'),
      zona_alarma:  val('input[name="zona_alarma"]'),
      tipo_switch:  val('#tipo_switch'),
      tipo_cctv:    val('#tipo_cctv'),
      tipo_alarma:  val('#tipo_alarma'),
    };
  }

  async function askAI(message){
    addMsg('user', message);
    // “Escribiendo…”
    const typing = document.createElement('div');
    typing.className = 'ai-msg bot';
    typing.innerHTML = `<div class="bubble"><em>Escribiendo…</em></div>`;
    chat.appendChild(typing);
    chat.scrollTop = chat.scrollHeight;

    try {
      const res = await fetch('<?= htmlspecialchars(dirname($_SERVER["PHP_SELF"])) ?>/../../api/ai_help.php', {
        method: 'POST',
        headers: { 'Content-Type':'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          message,
          form: getFormState()
        })
      });

      const data = await res.json();
      typing.remove();
      if (!res.ok) {
        addMsg('bot', 'Ocurrió un error al consultar la ayuda. Intenta de nuevo.');
        console.error('AI error', data);
        return;
        }
      addMsg('bot', data.reply || 'No recibí respuesta.');
    } catch (e) {
      typing.remove();
      addMsg('bot', 'No pude conectarme a la ayuda. ¿Hay internet o permisos de servidor?');
      console.error(e);
    }
  }

  function send(){
    const msg = input.value.trim();
    if (!msg) return;
    input.value = '';
    askAI(msg);
  }

  input.addEventListener('keydown', e => { if (e.key === 'Enter') send(); });
  sendBtn.addEventListener('click', send);

  // Ayuda contextual: si el usuario enfoca un campo sensible, abrimos el modal
  document.addEventListener('focusin', (e) => {
    const t = e.target;
    if (!t || !t.name) return;
    if (['ip','mac','zona_alarma','modelo','equipo'].some(n => (t.name || '').includes(n))) {
      if (!document.body.classList.contains('modal-open')) {
        // No forzamos abrir para no ser invasivos; descomenta si quieres auto-abrir:
        // open();
      }
    }
  });
})();
</script>
