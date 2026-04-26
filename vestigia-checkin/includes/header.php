<?php
/**
 * header.php
 * Cabecera principal de la aplicación — partial compartido
 * Vestigia CheckIn
 */
?><header class="main-header">
    <!-- Botón toggle sidebar (móvil) -->
    <button class="header-toggle" id="sidebar-toggle" aria-label="Menú">☰</button>

    <!-- Título de la página actual -->
    <div class="header-titulo" id="header-titulo">Vestigia CheckIn</div>

    <!-- Fecha de hoy -->
    <div class="header-fecha"><?= fechaEspanol(date('Y-m-d')) ?></div>

    <!-- Reloj en tiempo real -->
    <div class="header-reloj">--:--:--</div>

    <!-- Notificaciones de solicitudes pendientes (solo admins) -->
    <?php
    $notifCount = 0;
    if (tieneRol([ROL_SUPERADMIN, ROL_ADMIN_RRHH, ROL_SUBADMIN])) {
        $pdo_ = getDB();
        if (tieneRol([ROL_SUPERADMIN, ROL_ADMIN_RRHH])) {
            $notifCount = (int)$pdo_->query("SELECT COUNT(*) FROM solicitudes WHERE estado='pendiente' AND destinatario_id IS NULL")->fetchColumn();
        } else {
            $st_ = $pdo_->prepare("SELECT COUNT(*) FROM solicitudes s INNER JOIN users u ON u.id=s.user_id WHERE s.estado='pendiente' AND s.destinatario_id IS NULL AND u.departamento_id=?");
            $st_->execute([userDepto()]);
            $notifCount = (int)$st_->fetchColumn();
        }
    }
    ?>
    <?php if ($notifCount > 0): ?>
    <a href="<?= APP_URL ?>/pages/solicitudes.php?vista=admin" class="header-notif" title="Solicitudes pendientes">
        🔔<span class="badge"><?= $notifCount ?></span>
    </a>
    <?php endif; ?>
</header>