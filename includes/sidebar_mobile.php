<style>
#mobileMenu {
  background: linear-gradient(180deg, var(--side-base-1) 0%, var(--side-base-2) 60%, var(--side-base-1) 100%);
  color: var(--side-fg);
  box-shadow: var(--side-shadow);
  border: 1px solid var(--side-sep);
}

#mobileMenu .offcanvas-header {
  border-bottom: 1px solid var(--side-sep);
  background: rgba(7,22,26,.85);
  backdrop-filter: blur(6px);
}

#mobileMenu .offcanvas-body {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 0.5rem 1rem;
}

#mobileMenu .nav-link {
  color: var(--side-fg);
  border-radius: var(--radius);
  padding: 0.5rem 1rem;
  margin: 0.25rem 0;
  border: 1px solid transparent;
  transition: background 0.15s ease, transform 0.08s ease, border-color 0.2s ease;
  display: flex;
  align-items: center;
}

#mobileMenu .nav-link:hover {
  background: rgba(36,163,193,.12);
  border-color: rgba(36,163,193,.25);
  transform: translateX(1px);
}

#mobileMenu .nav-link.active-link {
  background: rgba(36,163,193,.18);
  border-color: rgba(36,163,193,.45);
  box-shadow: inset 0 0 0 1px rgba(36,163,193,.25);
}

#mobileMenu .badge {
  min-width: 22px;
  height: 22px;
  line-height: 22px;
  padding: 0 6px;
  background: #d9534f;
  color: #fff;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  text-align: center;
  box-shadow: 0 0 0 2px rgba(0,0,0,.12);
}

/* Botón de menú móvil */
.btn.d-lg-none {
  background: var(--brand);
  border: 1px solid var(--sb-border);
  box-shadow: 0 6px 18px rgba(0,0,0,.18);
  color: var(--side-fg);
  transition: all 0.2s ease;
}

.btn.d-lg-none:hover {
  background: var(--brand-2);
  box-shadow: 0 10px 26px rgba(0,0,0,.28);
  transform: translateY(-2px);
}

.btn.d-lg-none i {
  color: var(--side-fg);
}

.logout-link {
  background: rgba(36,163,193,.08);
  border: 1px solid rgba(36,163,193,.15);
  transition: background .15s ease, border-color .2s ease, transform .08s ease;
}

.logout-link:hover {
  background: rgba(36,163,193,.15);
  border-color: rgba(36,163,193,.35);
  transform: translateY(-1px);
}

:root {
  --brand:#3C92A6;
  --brand-2:#24a3c1;
}
@media (max-width: 991.98px) {
  .sidebar { display: none !important; }

  html, body { 
    padding-left: 10px !important; 
    padding-right: 10px !important; 
  }
  .content-wrapper,
  .page-content,
  .main,
  main {
    margin-left: 0 !important;
    width: 100% !important;
  }
}
</style>
<!-- Menú Offcanvas -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileMenu" aria-labelledby="mobileMenuLabel" style="width: 280px;">

  <!-- Cabecera -->
  <div class="offcanvas-header border-bottom brand" style="padding: 1rem 1.5rem;">
    <img src="/sisec-ui/public/img/QRCESISS.png" alt="Logo SISEC" style="max-width: 120px; align: center;">
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
  </div>

  <!-- Cuerpo del menú -->
  <div class="offcanvas-body d-flex flex-column justify-content-between p-3">

    <div class="nav flex-column" style="gap: 0.5rem; font-size: 1.1rem;">
      <!-- Inicio -->
      <?php if (in_array($_SESSION['usuario_rol'] ?? '', ['Superadmin', 'Administrador', 'Mantenimientos', 'Técnico', 'Capturista','Distrital'])): ?>
        <a href="/sisec-ui/views/inicio/index.php" class="nav-link <?= ($activePage ?? '') === 'inicio' ? 'active-link' : '' ?>">
          <i class="fas fa-home me-2"></i> Inicio
        </a>
      <?php endif; ?>

      <?php
        $rol = $_SESSION['usuario_rol'] ?? null;
        $rolesConListados = ['Superadmin', 'Administrador', 'Mantenimientos', 'Técnico', 'Capturista'];
      ?>

      <?php if (in_array($rol, $rolesConListados)): ?>
        <!-- Submenú Listados -->
        <a class="nav-link d-flex align-items-center" data-bs-toggle="collapse" href="#collapseListados" role="button" aria-expanded="false" aria-controls="collapseListados">
          <i class="fas fa-list me-2"></i>Listados
          <i class="fas fa-chevron-down ms-auto"></i>
        </a>
        <div class="collapse <?= ($activePage === 'dispositivos' || $activePage === 'listado_qr') ? 'show' : '' ?>" id="collapseListados">
          <a href="/sisec-ui/views/dispositivos/listar.php" class="nav-link ps-4 <?= ($activePage ?? '') === 'dispositivos' ? 'active-link' : '' ?>">
            <i class="fas fa-desktop me-2"></i>Dispositivos
          </a>
          <a href="/sisec-ui/views/dispositivos/listado_qr.php" class="nav-link ps-4 <?= ($activePage ?? '') === 'listado_qr' ? 'active-link' : '' ?>">
            <i class="fas fa-list-alt me-2"></i>Listado QR
          </a>
          <?php if (in_array($_SESSION['usuario_rol'] ?? '', ['Superadmin', 'Mantenimientos','Técnico', 'Capturista'])): ?>
              <a href="/sisec-ui/views/dispositivos/qr_virgenes_generar.php" class="nav-link ps-4 <?= ($activePage ?? '') === 'listado_qr' ? 'active-link' : '' ?>">
                <i class="fas fa-plus-square me-2"></i>Generar QR virgen
              </a>
            <?php endif; ?>
        </div>
      <?php else: ?>
        <!-- Solo Dispositivos -->
        <a href="/sisec-ui/views/dispositivos/listar.php" class="nav-link <?= ($activePage ?? '') === 'dispositivos' ? 'active-link' : '' ?>">
          <i class="fas fa-camera me-2"></i> Dispositivos
        </a>
      <?php endif; ?>

      <!-- Registrar dispositivo -->
      <?php if (in_array($rol, ['Superadmin', 'Capturista','Técnico','Monitorista'])): ?>
        <a href="/sisec-ui/views/dispositivos/registro.php" class="nav-link <?= ($activePage ?? '') === 'registro' ? 'active-link' : '' ?>">
          <i class="fas fa-plus-circle me-2"></i> Registrar dispositivo
        </a>
      <?php endif; ?>

      <!-- Usuarios -->
      <?php if (in_array($rol, ['Superadmin'])): ?>
        <a href="/sisec-ui/views/usuarios/index.php" class="nav-link">
          <i class="fa-solid fa-users me-2"></i>Usuarios
        </a>
        <a href="/sisec-ui/views/usuarios/registrar.php" class="nav-link">
          <i class="fa-solid fa-user-plus me-2"></i>Registrar usuario
        </a>
        <a href="/sisec-ui/views/usuarios/pendientes.php" class="nav-link d-flex justify-content-between <?= ($pendCount ?? 0) > 0 ? 'pending-alert' : '' ?>">
          <span><i class="fas fa-user-clock me-2"></i>Usuarios pendientes</span>
          <span class="badge"><?= $pendCount ?? 0 ?></span>
        </a>
      <?php endif; ?>

      <!-- Soporte -->
      <!--a href="/sisec-ui/views/inicio/soporte.php" class="nav-link <?= ($activePage ?? '') === 'soporte' ? 'active-link' : '' ?>">
        <i class="fas fa-tools me-2"></i> Soporte
      </a-->
    </div>

    <!-- Cerrar sesión -->
    <?php if (isset($_SESSION['usuario_id'])): ?>
      <div class="mt-3">
        <hr/>
        <a href="/sisec-ui/logout.php" class="d-block text-danger text-decoration-none px-3 py-2 rounded logout-link" style="font-size:1.1rem;">
          <i class="fas fa-sign-out-alt me-2"></i> Cerrar sesión
        </a>
      </div>
    <?php endif; ?>
  </div>
</div>