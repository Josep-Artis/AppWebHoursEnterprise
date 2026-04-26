<?php
/**
 * fichaje.php
 * Página de fichaje (entrada/salida) de Vestigia CheckIn
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requireLogin();

$userId    = userId();
$usuario   = getUsuario($userId);
$jornadaEfectiva = getJornadaEfectiva($userId);
$sinJornada = ($jornadaEfectiva === 'sin_asignar');
$horario   = obtenerHorario($jornadaEfectiva);
$proyectos = getProyectosUsuario($userId);
$fichajeAbierto = getFichajeAbierto($userId);

// Fichajes de los últimos 7 días
$pdo  = getDB();
$stmt = $pdo->prepare(
    "SELECT f.*, p.nombre AS proyecto_nombre
     FROM fichajes f LEFT JOIN proyectos p ON p.id = f.proyecto_id
     WHERE f.user_id = ? AND f.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
     ORDER BY f.fecha DESC, f.hora_entrada DESC"
);
$stmt->execute([$userId]);
$fichajesRecientes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fichar — Vestigia CheckIn</title>
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
                <h2>🕐 Registro de Jornada</h2>
                <p>Registra tu entrada y salida diaria.</p>
            </div>

            <!-- Hero del fichaje con reloj grande -->
            <div class="fichaje-hero">
                <div class="fecha-hoy"><?= fechaEspanol(date('Y-m-d')) ?></div>
                <div class="reloj-grande">--:--:--</div>
                <div id="estado-fichaje" class="estado-fichaje <?= $fichajeAbierto ? 'entrado' : 'sin-fichar' ?>">
                    <?= $fichajeAbierto ? '● En oficina' : '○ Sin fichar' ?>
                </div>
                <div id="info-fichaje" style="font-size:0.9rem;opacity:0.85;margin-top:0.4rem;">
                    <?php if ($fichajeAbierto): ?>
                        <strong>Entrada:</strong> <?= substr($fichajeAbierto['hora_entrada'],0,5) ?>
                        <?php if ($fichajeAbierto['tarde'] && $fichajeAbierto['minutos_retraso'] > 0): ?>
                            — <span style="color:#ffd97a;">⚠ <?= $fichajeAbierto['minutos_retraso'] ?> min de retraso</span>
                        <?php endif; ?>
                        <div style="margin-top:0.4rem;">
                            Tiempo trabajado: <span id="contador-tiempo" style="font-weight:700;">00:00:00</span>
                        </div>
                    <?php else: ?>
                        No has fichado hoy todavía.
                    <?php endif; ?>
                </div>
                <div style="margin-top:0.5rem;font-size:0.82rem;opacity:0.65;">
                    <?php if ($sinJornada): ?>
                        Sin jornada asignada
                    <?php else: ?>
                        Horario: <?= $horario['entrada'] ?> – <?= $horario['salida'] ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($sinJornada): ?>
            <div class="alerta alerta-error" style="margin-bottom:1.5rem;">
                <span>⚠</span>
                <div>
                    <strong>Jornada no asignada.</strong>
                    Tu horario laboral aún no ha sido configurado.
                    Contacta con RRHH o tu responsable para que te asignen un turno.
                </div>
            </div>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.5rem;">
                <!-- Panel de entrada -->
                <div class="card">
                    <div class="card-titulo">🟢 Registrar Entrada</div>

                    <div class="form-grupo">
                        <label for="proyecto-id">Proyecto <span style="color:var(--acento);">*</span></label>
                        <select id="proyecto-id" class="form-control" <?= $fichajeAbierto ? 'disabled' : '' ?>>
                            <option value="">— Selecciona un proyecto —</option>
                            <?php foreach ($proyectos as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= e($p['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($proyectos)): ?>
                            <p class="form-ayuda" style="color:var(--acento);">
                                No tienes proyectos asignados. Contacta con tu responsable.
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="form-grupo">
                        <div class="form-check">
                            <input type="checkbox" id="teletrabajo" name="teletrabajo"
                                   <?= ($fichajeAbierto && $fichajeAbierto['teletrabajo']) ? 'checked' : '' ?>
                                   <?= $fichajeAbierto ? 'disabled' : '' ?>>
                            <label for="teletrabajo">🏠 Trabajando en remoto (teletrabajo)</label>
                        </div>
                    </div>

                    <button
                        id="btn-entrada"
                        class="btn btn-exito btn-bloque btn-lg"
                        <?= ($fichajeAbierto || $sinJornada) ? 'disabled' : '' ?>
                    >
                        🕐 Registrar entrada
                    </button>
                </div>

                <!-- Panel de salida -->
                <div class="card">
                    <div class="card-titulo">🔴 Registrar Salida</div>
                    <p style="color:var(--texto-suave);font-size:0.9rem;margin-bottom:1.25rem;">
                        Al registrar tu salida se cerrará el fichaje activo del día.
                        El sistema calculará las horas extras o posibles incidencias.
                    </p>
                    <?php if ($fichajeAbierto): ?>
                        <div style="margin-bottom:1rem;">
                            <strong>Proyecto activo:</strong>
                            <?= e($fichajeAbierto['proyecto_nombre'] ?? '—') ?><br>
                            <strong>Entrada registrada:</strong>
                            <?= substr($fichajeAbierto['hora_entrada'],0,5) ?><br>
                            <strong>Hora estimada de salida:</strong>
                            <?= $horario['salida'] ?>
                        </div>
                    <?php endif; ?>
                    <button
                        id="btn-salida"
                        class="btn btn-acento btn-bloque btn-lg"
                        <?= !$fichajeAbierto ? 'disabled' : '' ?>
                    >
                        🏁 Registrar salida
                    </button>
                </div>
            </div>

            <!-- Historial reciente -->
            <div class="card">
                <div class="card-titulo">📋 Fichajes recientes (últimos 7 días)</div>
                <?php if ($fichajesRecientes): ?>
                <div class="tabla-contenedor">
                    <table class="tabla">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Proyecto</th>
                                <th>Entrada</th>
                                <th>Salida</th>
                                <th>Horas</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody id="historial-hoy">
                            <?php foreach ($fichajesRecientes as $f): ?>
                            <?php
                                $minTrabajados = 0;
                                if ($f['hora_entrada'] && $f['hora_salida']) {
                                    $en = strtotime($f['fecha'] . ' ' . $f['hora_entrada']);
                                    $sa = strtotime($f['fecha'] . ' ' . $f['hora_salida']);
                                    $minTrabajados = (int)round(($sa - $en) / 60);
                                }
                            ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($f['fecha'])) ?></td>
                                <td><?= e($f['proyecto_nombre'] ?? '—') ?></td>
                                <td><?= substr($f['hora_entrada'],0,5) ?></td>
                                <td>
                                    <?php if ($f['hora_salida']): ?>
                                        <?= substr($f['hora_salida'],0,5) ?>
                                    <?php else: ?>
                                        <span class="badge badge-amarillo">Abierto</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $minTrabajados ? minutosAHoras($minTrabajados) : '—' ?></td>
                                <td>
                                    <?= $f['tarde'] ? '<span class="badge badge-amarillo">⚠ Tarde</span>' : '<span class="badge badge-verde">✓ Puntual</span>' ?>
                                    <?= $f['teletrabajo'] ? '<span class="badge badge-azul">🏠</span>' : '' ?>
                                    <?php if ($f['horas_extra'] > 0): ?>
                                        <span class="badge badge-primario">+<?= minutosAHoras($f['horas_extra']) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <p style="color:var(--texto-suave);font-size:0.9rem;">No hay fichajes en los últimos 7 días.</p>
                <?php endif; ?>
                <div style="margin-top:1rem;">
                    <a href="informes.php" class="btn btn-secundario btn-sm">Ver todos mis informes →</a>
                </div>
            </div>

        </main>
    </div>
</div>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script src="<?= APP_URL ?>/assets/js/fichaje.js"></script>
<?php if ($fichajeAbierto): ?>
<script>
    // Pasar estado actual de fichaje al JS
    document.addEventListener('DOMContentLoaded', () => {
        iniciarContador('<?= $fichajeAbierto['hora_entrada'] ?>');
    });
</script>
<?php endif; ?>
</body>
</html>