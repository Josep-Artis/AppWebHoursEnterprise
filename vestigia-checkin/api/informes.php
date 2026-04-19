<?php
/**
 * api/informes.php
 * Exportación de informes en PDF y Excel — Vestigia CheckIn
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requireLogin();

$userId  = userId();
$pdo     = getDB();
$formato = $_GET['formato'] ?? 'excel';
$filtro  = $_GET['filtro']  ?? 'mes';
$desde   = $_GET['desde']   ?? date('Y-m-01');
$hasta   = $_GET['hasta']   ?? date('Y-m-t');
$filtroUsuario = (int)($_GET['user_id'] ?? 0);
$accion  = $_GET['accion']  ?? '';

// ── Editar fichaje (solo superadmin/admin_rrhh) ───────────────────────────────
if ($accion === 'editar_fichaje') {
    if (!tieneRol([ROL_SUPERADMIN, ROL_ADMIN_RRHH])) {
        http_response_code(403);
        die('Sin permisos.');
    }
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare(
        "SELECT f.*, u.nombre AS usuario_nombre, p.nombre AS proyecto_nombre
         FROM fichajes f
         LEFT JOIN users u ON u.id = f.user_id
         LEFT JOIN proyectos p ON p.id = f.proyecto_id
         WHERE f.id = ?"
    );
    $stmt->execute([$id]);
    $fichaje = $stmt->fetch();

    if (!$fichaje) { die('Fichaje no encontrado.'); }

    $mensaje = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && validarCsrf($_POST['csrf_token'] ?? '')) {
        $horaEntrada = $_POST['hora_entrada'] ?? '';
        $horaSalida  = $_POST['hora_salida']  ?? '';
        $proyectoId  = (int)($_POST['proyecto_id'] ?? 0);
        $teletrabajo = (int)isset($_POST['teletrabajo']);

        // Recalcular tarde y minutos retraso
        $usuarioFich = getUsuario($fichaje['user_id']);
        $horario     = obtenerHorario($usuarioFich['tipo_jornada'] ?? 'completa');
        $minRetraso  = calcularRetraso($horaEntrada . ':00', $horario['entrada']);
        $esTarde     = ($minRetraso > 0) ? 1 : 0;

        // Recalcular horas extra
        $minExtra = $horaSalida ? calcularHorasExtra($horaSalida . ':00', $horario['salida']) : 0;

        $pdo->prepare(
            "UPDATE fichajes SET hora_entrada=?, hora_salida=?, proyecto_id=?, teletrabajo=?,
             tarde=?, minutos_retraso=?, horas_extra=? WHERE id=?"
        )->execute([
            $horaEntrada . ':00',
            $horaSalida ? $horaSalida . ':00' : null,
            $proyectoId ?: null,
            $teletrabajo,
            $esTarde, $minRetraso, $minExtra,
            $id
        ]);
        $mensaje = 'Fichaje actualizado correctamente.';
        $stmt->execute([$id]);
        $fichaje = $stmt->fetch();
    }

    $proyectos = $pdo->query("SELECT id, nombre FROM proyectos WHERE activo=1 ORDER BY nombre")->fetchAll();
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Editar fichaje — Vestigia CheckIn</title>
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
                    <h2>✏️ Editar Fichaje</h2>
                    <p>Modificar registro de <?= e($fichaje['usuario_nombre']) ?> del <?= date('d/m/Y', strtotime($fichaje['fecha'])) ?></p>
                </div>
                <?php if ($mensaje): ?>
                    <div class="alerta alerta-exito"><span>✓</span> <?= e($mensaje) ?></div>
                <?php endif; ?>
                <div class="card" style="max-width:520px;">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <div class="form-row">
                            <div class="form-grupo">
                                <label>Hora entrada *</label>
                                <input type="time" name="hora_entrada" class="form-control"
                                       value="<?= substr($fichaje['hora_entrada'],0,5) ?>" required>
                            </div>
                            <div class="form-grupo">
                                <label>Hora salida</label>
                                <input type="time" name="hora_salida" class="form-control"
                                       value="<?= $fichaje['hora_salida'] ? substr($fichaje['hora_salida'],0,5) : '' ?>">
                            </div>
                        </div>
                        <div class="form-grupo">
                            <label>Proyecto</label>
                            <select name="proyecto_id" class="form-control">
                                <option value="">— Sin proyecto —</option>
                                <?php foreach ($proyectos as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= $fichaje['proyecto_id']==$p['id']?'selected':'' ?>>
                                        <?= e($p['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-grupo">
                            <div class="form-check">
                                <input type="checkbox" name="teletrabajo" id="teletrabajo"
                                       <?= $fichaje['teletrabajo'] ? 'checked' : '' ?>>
                                <label for="teletrabajo">🏠 Teletrabajo</label>
                            </div>
                        </div>
                        <div style="display:flex;gap:0.75rem;">
                            <button type="submit" class="btn btn-primario">💾 Guardar cambios</button>
                            <a href="<?= APP_URL ?>/pages/informes.php" class="btn btn-secundario">← Volver</a>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>
    <script src="<?= APP_URL ?>/assets/js/main.js"></script>
    </body>
    </html>
    <?php
    exit;
}

// ── Obtener datos para exportar ───────────────────────────────────────────────
if (!tieneRol([ROL_SUPERADMIN, ROL_ADMIN_RRHH, ROL_SUBADMIN])) {
    $filtroUsuario = $userId;
}

if ($filtroUsuario) {
    $stmt = $pdo->prepare(
        "SELECT f.fecha, f.hora_entrada, f.hora_salida, f.tarde, f.minutos_retraso,
                f.horas_extra, f.teletrabajo,
                u.nombre AS empleado, p.nombre AS proyecto
         FROM fichajes f
         LEFT JOIN users u ON u.id = f.user_id
         LEFT JOIN proyectos p ON p.id = f.proyecto_id
         WHERE f.user_id = ? AND f.fecha BETWEEN ? AND ?
         ORDER BY f.fecha, f.hora_entrada"
    );
    $stmt->execute([$filtroUsuario, $desde, $hasta]);
} else {
    if (tieneRol([ROL_SUPERADMIN, ROL_ADMIN_RRHH])) {
        $stmt = $pdo->prepare(
            "SELECT f.fecha, f.hora_entrada, f.hora_salida, f.tarde, f.minutos_retraso,
                    f.horas_extra, f.teletrabajo,
                    u.nombre AS empleado, p.nombre AS proyecto
             FROM fichajes f
             LEFT JOIN users u ON u.id = f.user_id
             LEFT JOIN proyectos p ON p.id = f.proyecto_id
             WHERE f.fecha BETWEEN ? AND ?
             ORDER BY u.nombre, f.fecha, f.hora_entrada"
        );
        $stmt->execute([$desde, $hasta]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT f.fecha, f.hora_entrada, f.hora_salida, f.tarde, f.minutos_retraso,
                    f.horas_extra, f.teletrabajo,
                    u.nombre AS empleado, p.nombre AS proyecto
             FROM fichajes f
             INNER JOIN users u ON u.id = f.user_id
             LEFT JOIN proyectos p ON p.id = f.proyecto_id
             WHERE f.fecha BETWEEN ? AND ? AND u.departamento_id = ?
             ORDER BY u.nombre, f.fecha, f.hora_entrada"
        );
        $stmt->execute([$desde, $hasta, userDepto()]);
    }
}
$filas = $stmt->fetchAll();

$nombreArchivo = 'vestigia_fichajes_' . str_replace(['-','/'], '', $desde) . '_' . str_replace(['-','/'], '', $hasta);

// ── EXPORTAR EXCEL (CSV con UTF-8 BOM para compatibilidad con Excel) ──────────
if ($formato === 'excel') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.csv"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    // BOM UTF-8
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($out, ['Empleado','Fecha','Proyecto','Hora Entrada','Hora Salida','Horas Trabajadas','Retraso (min)','Horas Extra','Teletrabajo','Estado'], ';');

    foreach ($filas as $f) {
        $minTrab = 0;
        if ($f['hora_entrada'] && $f['hora_salida']) {
            $en = strtotime($f['fecha'] . ' ' . $f['hora_entrada']);
            $sa = strtotime($f['fecha'] . ' ' . $f['hora_salida']);
            $minTrab = (int)round(($sa - $en) / 60);
        }
        fputcsv($out, [
            $f['empleado'] ?? '—',
            date('d/m/Y', strtotime($f['fecha'])),
            $f['proyecto']  ?? '—',
            substr($f['hora_entrada'], 0, 5),
            $f['hora_salida'] ? substr($f['hora_salida'], 0, 5) : '—',
            $minTrab ? minutosAHoras($minTrab) : '—',
            $f['minutos_retraso'] ?? 0,
            $f['horas_extra'] ? minutosAHoras($f['horas_extra']) : '0h 0min',
            $f['teletrabajo'] ? 'Sí' : 'No',
            $f['tarde'] ? 'Tarde' : 'Puntual',
        ], ';');
    }
    fclose($out);
    exit;
}

// ── EXPORTAR PDF (HTML imprimible) ────────────────────────────────────────────
if ($formato === 'pdf') {
    header('Content-Type: text/html; charset=UTF-8');

    $totalFichajes = count($filas);
    $totalTarde    = array_sum(array_column($filas, 'tarde'));
    $totalExtra    = array_sum(array_column($filas, 'horas_extra'));
    $totalTeletr   = array_sum(array_column($filas, 'teletrabajo'));

    echo '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Informe de fichajes — Vestigia CheckIn</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: Georgia, serif; font-size: 11pt; color: #1a1a1a; padding: 2cm; }
  h1  { font-size: 20pt; color: #184332; margin-bottom: 0.2cm; }
  h2  { font-size: 13pt; color: #660708; margin: 0.6cm 0 0.3cm; }
  .empresa { font-size: 9pt; color: #666; margin-bottom: 1cm; }
  .periodo { font-size: 10pt; margin-bottom: 0.5cm; }
  .stats   { display:flex; gap:1cm; margin-bottom:0.8cm; }
  .stat    { background:#f5f0e8; padding:0.3cm 0.5cm; border-radius:4px; text-align:center; min-width:3cm; }
  .stat strong { display:block; font-size:16pt; color:#184332; }
  .stat span   { font-size:8pt; color:#666; text-transform:uppercase; }
  table  { width:100%; border-collapse:collapse; font-size:9pt; }
  thead  { background:#184332; color:#FFFCF2; }
  th, td { border:1px solid #ddd; padding:0.2cm 0.3cm; }
  tr:nth-child(even) { background:#faf8f3; }
  .tarde { color:#660708; font-weight:bold; }
  .puntual { color:#184332; }
  .footer { margin-top:1cm; font-size:8pt; color:#999; text-align:center; }
  @media print { body { padding: 1cm; } }
</style>
</head>
<body>
<h1>📜 Vestigia CheckIn</h1>
<div class="empresa">Vestigia — Revista Internacional de Historia</div>
<h2>Informe de Fichajes</h2>
<div class="periodo">Período: <strong>' . e($desde) . '</strong> al <strong>' . e($hasta) . '</strong></div>
<div class="stats">
  <div class="stat"><strong>' . $totalFichajes . '</strong><span>Registros</span></div>
  <div class="stat"><strong>' . $totalTarde . '</strong><span>Retrasos</span></div>
  <div class="stat"><strong>' . minutosAHoras($totalExtra) . '</strong><span>H. Extra</span></div>
  <div class="stat"><strong>' . $totalTeletr . '</strong><span>Teletrabajo</span></div>
</div>
<table>
<thead>
  <tr>
    <th>Empleado</th>
    <th>Fecha</th>
    <th>Proyecto</th>
    <th>Entrada</th>
    <th>Salida</th>
    <th>Horas</th>
    <th>H. Extra</th>
    <th>Teletrabajo</th>
    <th>Estado</th>
  </tr>
</thead>
<tbody>';

    foreach ($filas as $f) {
        $minTrab = 0;
        if ($f['hora_entrada'] && $f['hora_salida']) {
            $en = strtotime($f['fecha'] . ' ' . $f['hora_entrada']);
            $sa = strtotime($f['fecha'] . ' ' . $f['hora_salida']);
            $minTrab = (int)round(($sa - $en) / 60);
        }
        $claseEstado = $f['tarde'] ? 'tarde' : 'puntual';
        $txtEstado   = $f['tarde'] ? 'Tarde' : 'Puntual';
        echo '<tr>
            <td>' . e($f['empleado'] ?? '—') . '</td>
            <td>' . date('d/m/Y', strtotime($f['fecha'])) . '</td>
            <td>' . e($f['proyecto'] ?? '—') . '</td>
            <td>' . substr($f['hora_entrada'], 0, 5) . '</td>
            <td>' . ($f['hora_salida'] ? substr($f['hora_salida'], 0, 5) : '—') . '</td>
            <td>' . ($minTrab ? minutosAHoras($minTrab) : '—') . '</td>
            <td>' . ($f['horas_extra'] ? minutosAHoras($f['horas_extra']) : '—') . '</td>
            <td>' . ($f['teletrabajo'] ? '🏠 Sí' : 'No') . '</td>
            <td class="' . $claseEstado . '">' . $txtEstado . '</td>
        </tr>';
    }

    echo '</tbody></table>
<div class="footer">Generado por Vestigia CheckIn — ' . date('d/m/Y H:i') . '</div>
<script>window.onload = function(){ window.print(); }</script>
</body></html>';
    exit;
}

echo json_encode(['error' => 'Formato no válido.']);