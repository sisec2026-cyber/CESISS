<!-- Sidebar móvil (offcanvas) -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileMenu" aria-labelledby="mobileMenuLabel" style="background-color: #fff;">
  <div class="offcanvas-header border-bottom">
      <h5 class="offcanvas-title">
        <img src="/sisec-ui/public/img/logo.png" alt="Logo SISEC" style="max-height: 50px;">
      </h5>

    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
  </div>

  <div class="offcanvas-body d-flex flex-column justify-content-between">
    <div>
      <?php if (in_array($_SESSION['usuario_rol'], ['Administrador', 'Mantenimientos'])): ?>
        <a href="/sisec-ui/views/inicio/index.php" 
          class="d-block mb-2 text-dark text-decoration-none <?= ($activePage ?? '') === 'inicio' ? 'active' : '' ?>">
          <i class="fas fa-home me-2"></i>Inicio
        </a>
      <?php endif; ?>

      <a href="/sisec-ui/views/dispositivos/listar.php" 
        class="d-block mb-2 text-dark text-decoration-none <?= ($activePage ?? '') === 'dispositivos' ? 'active' : '' ?>">
        <i class="fas fa-camera me-2"></i>Dispositivos
      </a>

      <?php if (in_array($_SESSION['usuario_rol'], ['Administrador', 'Mantenimientos'])): ?>
        <a href="/sisec-ui/views/dispositivos/registro.php" 
          class="d-block mb-2 text-dark text-decoration-none <?= ($activePage ?? '') === 'registro' ? 'active' : '' ?>">
          <i class="fas fa-plus-circle me-2"></i>Registrar dispositivo
        </a>
      <?php endif; ?>

      <?php if ($_SESSION['usuario_rol'] === 'Administrador'): ?>
        <a href="/sisec-ui/views/usuarios/index.php" class="d-block mb-2 text-dark text-decoration-none">
          <i class="fa-solid fa-users me-2"></i>Usuarios
        </a>
        <a href="/sisec-ui/views/usuarios/registrar.php" class="d-block mb-2 text-dark text-decoration-none">
          <i class="fa-solid fa-user-plus me-2"></i>Registrar usuario
        </a>
      <?php endif; ?>
    </div>

    <?php if (isset($_SESSION['usuario_id'])): ?>
      <div class="mt-3">
        <hr />
        <a href="/sisec-ui/logout.php" class="d-block text-danger text-decoration-none">
          <i class="fas fa-sign-out-alt me-2"></i> Cerrar sesión
        </a>
      </div>
    <?php endif; ?>
  </div>
</div>
