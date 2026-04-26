<?php
/**
 * main.php
 * Dashboard principal de Vestigia CheckIn
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requireLogin();

$userId  = userId();
$usuario = getUsuario($userId);
$pdo     = getDB();

// ── Estadísticas del mes actual ───────────────────────────────────────────────
$mesActual = date('Y-m');
$stmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS total_dias,
        SUM(tarde) AS dias_tarde,
        SUM(teletrabajo) AS dias_teletrabajo,
        SUM(horas_extra) AS minutos_extra
     FROM fichajes
     WHERE user_id = ? AND DATE_FORMAT(fecha,'%Y-%m') = ?"
);
$stmt->execute([$userId, $mesActual]);
$stats = $stmt->fetch();

// ── Solicitudes pendientes (para admins) ──────────────────────────────────────
$solicitudesPendientes = 0;
if (tieneRol([ROL_SUPERADMIN, ROL_ADMIN_RRHH, ROL_SUBADMIN])) {
    if (tieneRol([ROL_SUPERADMIN, ROL_ADMIN_RRHH])) {
        $solicitudesPendientes = (int)$pdo->query("SELECT COUNT(*) FROM solicitudes WHERE estado='pendiente'")->fetchColumn();
    } else {
        $st = $pdo->prepare("SELECT COUNT(*) FROM solicitudes s INNER JOIN users u ON u.id = s.user_id WHERE s.estado='pendiente' AND u.departamento_id = ?");
        $st->execute([userDepto()]);
        $solicitudesPendientes = (int)$st->fetchColumn();
    }
}

// ── Fichaje de hoy ────────────────────────────────────────────────────────────
$fichajeHoy = getFichajeAbierto($userId);

// ── Últimos 5 fichajes ────────────────────────────────────────────────────────
$stmt = $pdo->prepare(
    "SELECT f.*, p.nombre AS proyecto_nombre
     FROM fichajes f LEFT JOIN proyectos p ON p.id = f.proyecto_id
     WHERE f.user_id = ? ORDER BY f.fecha DESC, f.hora_entrada DESC LIMIT 5"
);
$stmt->execute([$userId]);
$ultFichajes = $stmt->fetchAll();

// ── Eventos próximos ──────────────────────────────────────────────────────────
$stmt = $pdo->prepare(
    "SELECT * FROM eventos
     WHERE fecha >= CURDATE()
       AND (departamento_id IS NULL OR departamento_id = ?)
     ORDER BY fecha ASC LIMIT 4"
);
$stmt->execute([$usuario['departamento_id']]);
$eventos = $stmt->fetchAll();

// ── Horario de hoy ────────────────────────────────────────────────────────────
$horarioHoy  = obtenerHorario(getJornadaEfectiva($userId));
$diaSemana   = (int)date('N');
$esLaborable = ($diaSemana >= 1 && $diaSemana <= 5);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Vestigia CheckIn</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
<div class="app-layout">
    <div class="sidebar-overlay" id="sidebar-overlay"></div>
    <?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include dirname(__DIR__) . '/includes/header.php'; ?>
        <main class="page-area">
            <div class="page-header">
                <h2>🏛️ Panel de Control</h2>
                <p>Bienvenido/a, <?= e($usuario['nombre']) ?>. Hoy es <?= fechaEspanol(date('Y-m-d')) ?>.</p>
            </div>

            <?php if ($esLaborable && !$fichajeHoy): ?>
                <div class="alerta alerta-aviso">
                    <span>⚠</span>
                    Todavía no has fichado hoy. Tu hora de entrada es <strong><?= $horarioHoy['entrada'] ?></strong>.
                    <a href="fichaje.php" class="btn btn-primario btn-sm" style="margin-left:auto;">Fichar ahora</a>
                </div>
            <?php elseif ($fichajeHoy): ?>
                <div class="alerta alerta-exito">
                    <span>✓</span>
                    Registrado/a desde las <strong><?= substr($fichajeHoy['hora_entrada'],0,5) ?></strong>.
                    <a href="fichaje.php" class="btn btn-acento btn-sm" style="margin-left:auto;">Registrar salida</a>
                </div>
            <?php endif; ?>

            <!-- Estadísticas del mes -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-valor"><?= (int)($stats['total_dias'] ?? 0) ?></div>
                    <div class="stat-etiqueta">Días fichados (mes)</div>
                </div>
                <div class="stat-card acento">
                    <div class="stat-valor"><?= (int)($stats['dias_tarde'] ?? 0) ?></div>
                    <div class="stat-etiqueta">Retrasos este mes</div>
                </div>
                <div class="stat-card verde">
                    <div class="stat-valor"><?= (int)($stats['dias_teletrabajo'] ?? 0) ?></div>
                    <div class="stat-etiqueta">Días teletrabajo</div>
                </div>
                <div class="stat-card">
                    <div class="stat-valor"><?= minutosAHoras((int)($stats['minutos_extra'] ?? 0)) ?></div>
                    <div class="stat-etiqueta">Horas extra</div>
                </div>
                <?php if ($solicitudesPendientes > 0): ?>
                <div class="stat-card acento">
                    <div class="stat-valor"><?= $solicitudesPendientes ?></div>
                    <div class="stat-etiqueta">Solicitudes pendientes</div>
                </div>
                <?php endif; ?>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.25rem;">
                <!-- Últimos fichajes -->
                <div class="card">
                    <div class="card-titulo">📋 Últimos fichajes</div>
                    <?php if ($ultFichajes): ?>
                    <div class="tabla-contenedor">
                        <table class="tabla">
                            <thead><tr><th>Fecha</th><th>Entrada</th><th>Salida</th><th>Estado</th></tr></thead>
                            <tbody>
                                <?php foreach ($ultFichajes as $f): ?>
                                <tr>
                                    <td><?= date('d/m', strtotime($f['fecha'])) ?></td>
                                    <td><?= substr($f['hora_entrada'],0,5) ?></td>
                                    <td><?= $f['hora_salida'] ? substr($f['hora_salida'],0,5) : '<span class="badge badge-amarillo">Abierto</span>' ?></td>
                                    <td>
                                        <?= $f['tarde'] ? '<span class="badge badge-amarillo">⚠ Tarde</span>' : '<span class="badge badge-verde">✓ Puntual</span>' ?>
                                        <?= $f['teletrabajo'] ? '<span class="badge badge-azul">🏠</span>' : '' ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <p style="color:var(--texto-suave);font-size:0.9rem;">Sin fichajes registrados.</p>
                    <?php endif; ?>
                    <div style="margin-top:1rem;"><a href="informes.php" class="btn btn-secundario btn-sm">Ver todos →</a></div>
                </div>

                <!-- Horario + eventos -->
                <div style="display:flex;flex-direction:column;gap:1.25rem;">
                    <div class="card">
                        <div class="card-titulo">🕐 Tu horario hoy</div>
                        <div style="display:flex;gap:1.5rem;align-items:center;padding:0.5rem 0;">
                            <div style="text-align:center;">
                                <div style="font-size:1.6rem;font-weight:700;color:var(--primario);"><?= $horarioHoy['entrada'] ?></div>
                                <div style="font-size:0.75rem;color:var(--texto-suave);text-transform:uppercase;">Entrada</div>
                            </div>
                            <div style="font-size:1.5rem;color:var(--borde);">→</div>
                            <div style="text-align:center;">
                                <div style="font-size:1.6rem;font-weight:700;color:var(--acento);"><?= $horarioHoy['salida'] ?></div>
                                <div style="font-size:0.75rem;color:var(--texto-suave);text-transform:uppercase;">Salida</div>
                            </div>
                        </div>
                        <div style="margin-top:1rem;"><a href="horario.php" class="btn btn-secundario btn-sm">Ver horario completo →</a></div>
                    </div>

                    <?php if ($eventos): ?>
                    <div class="card">
                        <div class="card-titulo">📅 Próximos eventos</div>
                        <?php foreach ($eventos as $ev): ?>
                            <div style="display:flex;gap:0.75rem;margin-bottom:0.75rem;align-items:flex-start;">
                                <div style="background:var(--primario);color:var(--secundario);border-radius:6px;padding:0.3rem 0.5rem;text-align:center;min-width:40px;font-size:0.8rem;font-weight:700;">
                                    <?= date('d', strtotime($ev['fecha'])) ?><br>
                                    <span style="font-size:0.65rem;font-weight:400;"><?= strtoupper(date('M', strtotime($ev['fecha']))) ?></span>
                                </div>
                                <div>
                                    <strong style="font-size:0.9rem;"><?= e($ev['titulo']) ?></strong>
                                    <?php if ($ev['descripcion']): ?>
                                        <div style="font-size:0.78rem;color:var(--texto-suave);"><?= e($ev['descripcion']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Accesos rápidos -->
            <div class="card">
                <div class="card-titulo">⚡ Accesos rápidos</div>
                <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
                    <a href="fichaje.php"     class="btn btn-primario">🕐 Fichar</a>
                    <a href="horario.php"     class="btn btn-secundario">📅 Mi horario</a>
                    <a href="solicitudes.php" class="btn btn-secundario">📨 Solicitudes</a>
                    <a href="informes.php"    class="btn btn-secundario">📊 Mis informes</a>
                    <a href="perfil.php"      class="btn btn-secundario">👤 Mi perfil</a>
                    <?php if (tieneRol([ROL_SUPERADMIN, ROL_ADMIN_RRHH, ROL_SUBADMIN])): ?>
                        <a href="informes.php?vista=equipo" class="btn btn-secundario">👥 Equipo</a>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>