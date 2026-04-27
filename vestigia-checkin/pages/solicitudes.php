<?php
/**
 * solicitudes.php
 * Página de solicitudes (vacaciones, bajas, cambios de horario, teletrabajo)
 * Vestigia CheckIn
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requireLogin();

$userId     = userId();
$pdo        = getDB();
$vista      = $_GET['vista'] ?? 'mias';
$tipoFiltro = $_GET['tipo'] ?? '';
$mensaje    = '';
$error      = '';

// ── Procesar acciones POST ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if (!validarCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido.';
    } else {
        $accion = $_POST['accion'];

        // ── Nueva solicitud (empleado → responsable) ──────────────────────────
        if ($accion === 'nueva_solicitud') {
            $tipo             = $_POST['tipo'] ?? '';
            $descripcion      = trim($_POST['descripcion'] ?? '');
            $fechaInicio      = $_POST['fecha_inicio'] ?? '';
            $fechaFin         = $_POST['fecha_fin'] ?? '';
            $tipoJornadaNueva = $_POST['tipo_jornada_nueva'] ?? '';

            $tiposValidos = ['vacaciones','baja','cambio_horario','teletrabajo'];
            if (!in_array($tipo, $tiposValidos)) {
                $error = 'Tipo de solicitud inválido.';
            } elseif (!$descripcion) {
                $error = 'La descripción es obligatoria.';
            } elseif ($tipo === 'cambio_horario' && !$tipoJornadaNueva) {
                $error = 'Debes seleccionar el turno destino para un cambio de horario.';
            } else {
                if (crearSolicitud($userId, $tipo, $descripcion, $fechaInicio, $fechaFin, $tipoJornadaNueva)) {
                    $mensaje = 'Solicitud enviada correctamente. Recibirás una respuesta pronto.';
                } else {
                    $error = 'Error al enviar la solicitud. Inténtalo de nuevo.';
                }
            }
        }

        // ── Nueva propuesta (responsable → empleado) ──────────────────────────
        elseif ($accion === 'nueva_propuesta' && tieneRol([ROL_SUPERADMIN, ROL_ADMIN_RRHH, ROL_SUBADMIN])) {
            $destinatarioId   = (int)($_POST['destinatario_id'] ?? 0);
            $tipo             = $_POST['tipo'] ?? '';
            $descripcion      = trim($_POST['descripcion_propuesta'] ?? '');
            $fechaInicio      = $_POST['fecha_inicio_propuesta'] ?? '';
            $fechaFin         = $_POST['fecha_fin_propuesta'] ?? '';
            $tipoJornadaNueva = $_POST['tipo_jornada_nueva'] ?? '';

            $tiposValidos = ['vacaciones','baja','cambio_horario','teletrabajo'];
            if (!$destinatarioId) {
                $error = 'Debes seleccionar un empleado destinatario.';
            } elseif (!in_array($tipo, $tiposValidos)) {
                $error = 'Tipo de propuesta inválido.';
            } elseif (!$descripcion) {
                $error = 'La descripción es obligatoria.';
            } elseif ($tipo === 'cambio_horario' && !$tipoJornadaNueva) {
                $error = 'Debes seleccionar el turno destino para un cambio de horario.';
            } else {
                if (crearPropuesta($userId, $destinatarioId, $tipo, $descripcion, $fechaInicio, $fechaFin, $tipoJornadaNueva)) {
                    $mensaje = 'Propuesta enviada correctamente al empleado.';
                } else {
                    $error = 'Error al enviar la propuesta. Inténtalo de nuevo.';
                }
            }
        }

        // ── Responder propuesta recibida (empleado acepta/rechaza) ────────────
        elseif ($accion === 'responder_propuesta') {
            $solicitudId = (int)($_POST['solicitud_id'] ?? 0);
            $respuesta   = $_POST['respuesta'] ?? '';
            $estado      = $respuesta === 'aceptar' ? 'aprobado' : 'rechazado';

            // Verificar que la propuesta va dirigida a este usuario
            $stmtV = $pdo->prepare("SELECT * FROM solicitudes WHERE id = ? AND destinatario_id = ? AND estado = 'pendiente'");
            $stmtV->execute([$solicitudId, $userId]);
            $propuesta = $stmtV->fetch();
            if ($propuesta) {
                $pdo->prepare(
                    "UPDATE solicitudes SET estado = ?, aprobado_por = ?, fecha_resolucion = NOW() WHERE id = ?"
                )->execute([$estado, $userId, $solicitudId]);

                // Si acepta un cambio de horario → aplicar jornada nueva
                if ($estado === 'aprobado' && $propuesta['tipo'] === 'cambio_horario' && !empty($propuesta['tipo_jornada_nueva'])) {
                    if ($propuesta['fecha_inicio'] && $propuesta['fecha_fin']) {
                        // Cambio TEMPORAL — el destinatario es el propio empleado
                        $pdo->prepare(
                            "INSERT INTO cambios_horario_temporales (user_id, tipo_jornada_temporal, fecha_inicio, fecha_fin, solicitud_id)
                             VALUES (?, ?, ?, ?, ?)"
                        )->execute([$userId, $propuesta['tipo_jornada_nueva'], $propuesta['fecha_inicio'], $propuesta['fecha_fin'], $solicitudId]);
                    } else {
                        // Cambio PERMANENTE
                        $pdo->prepare("UPDATE users SET tipo_jornada = ? WHERE id = ?")
                            ->execute([$propuesta['tipo_jornada_nueva'], $userId]);
                    }
                }

                $mensaje = $estado === 'aprobado' ? 'Has aceptado la propuesta.' : 'Has rechazado la propuesta.';
            } else {
                $error = 'Propuesta no válida o ya respondida.';
            }
        }

        // ── Aprobar/rechazar solicitud normal (solo admins) ───────────────────
        elseif (in_array($accion, ['aprobar', 'rechazar']) && tieneRol([ROL_SUPERADMIN, ROL_ADMIN_RRHH, ROL_SUBADMIN])) {
            $solicitudId = (int)($_POST['solicitud_id'] ?? 0);
            $estado      = $accion === 'aprobar' ? 'aprobado' : 'rechazado';
            $stmt = $pdo->prepare(
                "UPDATE solicitudes SET estado = ?, aprobado_por = ?, fecha_resolucion = NOW()
                 WHERE id = ? AND destinatario_id IS NULL"
            );
            if ($stmt->execute([$estado, $userId, $solicitudId])) {
                $mensaje = 'Solicitud ' . ($estado === 'aprobado' ? 'aprobada' : 'rechazada') . ' correctamente.';

                if ($estado === 'aprobado') {
                    $s = $pdo->prepare("SELECT * FROM solicitudes WHERE id = ?");
                    $s->execute([$solicitudId]);
                    $sol = $s->fetch();

                    // Vacaciones aprobadas → crear entrada en tabla vacaciones
                    if ($sol && $sol['tipo'] === 'vacaciones' && $sol['fecha_inicio'] && $sol['fecha_fin']) {
                        $pdo->prepare(
                            "INSERT INTO vacaciones (user_id, fecha_inicio, fecha_fin, estado, aprobado_por)
                             VALUES (?, ?, ?, 'aprobado', ?)"
                        )->execute([$sol['user_id'], $sol['fecha_inicio'], $sol['fecha_fin'], $userId]);
                    }

                    // Cambio de horario aprobado → aplicar jornada nueva
                    if ($sol && $sol['tipo'] === 'cambio_horario' && !empty($sol['tipo_jornada_nueva'])) {
                        if ($sol['fecha_inicio'] && $sol['fecha_fin']) {
                            // Cambio TEMPORAL
                            $pdo->prepare(
                                "INSERT INTO cambios_horario_temporales (user_id, tipo_jornada_temporal, fecha_inicio, fecha_fin, solicitud_id)
                                 VALUES (?, ?, ?, ?, ?)"
                            )->execute([$sol['user_id'], $sol['tipo_jornada_nueva'], $sol['fecha_inicio'], $sol['fecha_fin'], $solicitudId]);
                        } else {
                            // Cambio PERMANENTE
                            $pdo->prepare("UPDATE users SET tipo_jornada = ? WHERE id = ?")
                                ->execute([$sol['tipo_jornada_nueva'], $sol['user_id']]);
                        }
                    }
                }
            } else {
                $error = 'Error al procesar la solicitud.';
            }
        }
    }
}

// ── Obtener datos para cada tab ───────────────────────────────────────────────

// Solicitudes normales propias (empleado → responsable, sin destinatario)
$sql = "SELECT s.* FROM solicitudes s WHERE s.user_id = ? AND s.destinatario_id IS NULL";
$params = [$userId];
if ($tipoFiltro) {
    $sql .= " AND s.tipo = ?";
    $params[] = $tipoFiltro;
}
$sql .= " ORDER BY s.fecha DESC";
$stmtMias = $pdo->prepare($sql);
$stmtMias->execute($params);
$solicitudes = $stmtMias->fetchAll();

// Solicitudes pendientes de aprobación (vista admin, solo las normales sin destinatario)
$solicitudesPendientes = [];
if (tieneRol([ROL_SUPERADMIN, ROL_ADMIN_RRHH, ROL_SUBADMIN])) {
    if (tieneRol([ROL_SUPERADMIN, ROL_ADMIN_RRHH])) {
        $stmtA = $pdo->query(
            "SELECT s.*, u.nombre AS usuario_nombre, u.email AS usuario_email
             FROM solicitudes s INNER JOIN users u ON u.id = s.user_id
             WHERE s.estado = 'pendiente' AND s.destinatario_id IS NULL
             ORDER BY s.fecha DESC"
        );
    } else {
        $stmtA = $pdo->prepare(
            "SELECT s.*, u.nombre AS usuario_nombre, u.email AS usuario_email
             FROM solicitudes s INNER JOIN users u ON u.id = s.user_id
             WHERE s.estado = 'pendiente' AND s.destinatario_id IS NULL AND u.departamento_id = ?
             ORDER BY s.fecha DESC"
        );
        $stmtA->execute([userDepto()]);
    }
    $solicitudesPendientes = $stmtA->fetchAll();
}

// Propuestas recibidas por el usuario actual
$propuestasRecibidas = getPropuestasRecibidas($userId);

// Propuestas enviadas por el usuario actual (solo responsables)
$propuestasEnviadas = [];
if (tieneRol([ROL_SUPERADMIN, ROL_ADMIN_RRHH, ROL_SUBADMIN])) {
    $propuestasEnviadas = getPropuestasEnviadas($userId);
}

// Empleados disponibles para enviar propuestas (filtrado por depto si subadmin)
$empleadosParaPropuesta = [];
if (tieneRol([ROL_SUPERADMIN, ROL_ADMIN_RRHH, ROL_SUBADMIN])) {
    $deptoFiltro = tieneRol([ROL_SUPERADMIN, ROL_ADMIN_RRHH]) ? 0 : userDepto();
    $empleadosParaPropuesta = getUsuarios($deptoFiltro);
    // Excluir al propio responsable
    $empleadosParaPropuesta = array_filter($empleadosParaPropuesta, fn($u) => $u['id'] !== $userId);
}

// Determinar qué tab activar por defecto
$tabActiva = $vista;

$etiquetasTipo = [
    'vacaciones'     => '🌴 Vacaciones',
    'baja'           => '🤒 Baja médica',
    'cambio_horario' => '📅 Cambio de horario',
    'teletrabajo'    => '🏠 Teletrabajo',
];
$etiquetasEstado = [
    'pendiente' => ['label' => 'Pendiente', 'clase' => 'badge-amarillo'],
    'aprobado'  => ['label' => 'Aprobado',  'clase' => 'badge-verde'],
    'rechazado' => ['label' => 'Rechazado', 'clase' => 'badge-rojo'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitudes — Vestigia CheckIn</title>
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
                <h2>📨 Solicitudes</h2>
                <p>Gestiona tus solicitudes de vacaciones, bajas y cambios de horario.</p>
            </div>

            <?php if ($mensaje): ?>
                <div class="alerta alerta-exito"><span>✓</span> <?= e($mensaje) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alerta alerta-error"><span>✕</span> <?= e($error) ?></div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="tabs-wrapper">
                <div class="tabs">
                    <button class="tab-btn <?= !in_array($tabActiva, ['admin','recibidas','enviadas']) ? 'activo' : '' ?>" data-tab="mias">
                        Mis solicitudes
                    </button>
                    <?php if (tieneRol([ROL_SUPERADMIN, ROL_ADMIN_RRHH, ROL_SUBADMIN])): ?>
                    <button class="tab-btn <?= $tabActiva === 'admin' ? 'activo' : '' ?>" data-tab="admin">
                        Pendientes de aprobación
                        <?php if (count($solicitudesPendientes) > 0): ?>
                            <span class="badge badge-acento" style="margin-left:0.4rem;"><?= count($solicitudesPendientes) ?></span>
                        <?php endif; ?>
                    </button>
                    <?php endif; ?>
                    <?php $numRecibidas = count(array_filter($propuestasRecibidas, fn($p) => $p['estado'] === 'pendiente')); ?>
                    <button class="tab-btn <?= $tabActiva === 'recibidas' ? 'activo' : '' ?>" data-tab="recibidas">
                        📬 Recibidas
                        <?php if ($numRecibidas > 0): ?>
                            <span class="badge badge-acento" style="margin-left:0.4rem;"><?= $numRecibidas ?></span>
                        <?php endif; ?>
                    </button>
                    <?php if (tieneRol([ROL_SUPERADMIN, ROL_ADMIN_RRHH, ROL_SUBADMIN])): ?>
                    <button class="tab-btn <?= $tabActiva === 'enviadas' ? 'activo' : '' ?>" data-tab="enviadas">
                        📤 Enviadas
                    </button>
                    <button class="tab-btn" data-tab="nueva-propuesta">+ Nueva propuesta</button>
                    <?php endif; ?>
                    <button class="tab-btn" data-tab="nueva">+ Nueva solicitud</button>
                </div>

                <!-- Tab: Mis solicitudes -->
                <div id="tab-mias" class="tab-panel <?= !in_array($tabActiva, ['admin','recibidas','enviadas']) ? 'activo' : '' ?>">
                    <div class="card">
                        <div class="card-titulo">📋 Mis solicitudes</div>
                        <?php if ($solicitudes): ?>
                        <div class="tabla-contenedor">
                            <table class="tabla">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Descripción</th>
                                        <th>Fechas</th>
                                        <th>Enviada</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($solicitudes as $s): ?>
                                    <tr>
                                        <td><?= $etiquetasTipo[$s['tipo']] ?? e($s['tipo']) ?></td>
                                        <td style="max-width:280px;"><?= e($s['descripcion']) ?></td>
                                        <td>
                                            <?php if ($s['fecha_inicio']): ?>
                                                <?= date('d/m/Y', strtotime($s['fecha_inicio'])) ?>
                                                <?php if ($s['fecha_fin']): ?> – <?= date('d/m/Y', strtotime($s['fecha_fin'])) ?><?php endif; ?>
                                            <?php else: ?>—<?php endif; ?>
                                        </td>
                                        <td><?= date('d/m/Y', strtotime($s['fecha'])) ?></td>
                                        <td>
                                            <?php $est = $etiquetasEstado[$s['estado']] ?? ['label'=>$s['estado'],'clase'=>'badge-gris']; ?>
                                            <span class="badge <?= $est['clase'] ?>"><?= $est['label'] ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                            <p style="color:var(--texto-suave);font-size:0.9rem;">No tienes solicitudes enviadas.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tab: Pendientes de aprobación (admin) -->
                <?php if (tieneRol([ROL_SUPERADMIN, ROL_ADMIN_RRHH, ROL_SUBADMIN])): ?>
                <div id="tab-admin" class="tab-panel <?= $tabActiva === 'admin' ? 'activo' : '' ?>">
                    <div class="card">
                        <div class="card-titulo">✅ Solicitudes pendientes de aprobación</div>
                        <?php if ($solicitudesPendientes): ?>
                        <div class="tabla-contenedor">
                            <table class="tabla">
                                <thead>
                                    <tr>
                                        <th>Empleado</th>
                                        <th>Tipo</th>
                                        <th>Descripción</th>
                                        <th>Fechas</th>
                                        <th>Fecha solicitud</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($solicitudesPendientes as $s): ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($s['usuario_nombre']) ?></strong><br>
                                            <span style="font-size:0.78rem;color:var(--texto-suave);"><?= e($s['usuario_email']) ?></span>
                                        </td>
                                        <td><?= $etiquetasTipo[$s['tipo']] ?? e($s['tipo']) ?></td>
                                        <td style="max-width:240px;"><?= e($s['descripcion']) ?></td>
                                        <td>
                                            <?php if ($s['fecha_inicio']): ?>
                                                <?= date('d/m/Y', strtotime($s['fecha_inicio'])) ?>
                                                <?= $s['fecha_fin'] ? '– ' . date('d/m/Y', strtotime($s['fecha_fin'])) : '' ?>
                                            <?php else: ?>—<?php endif; ?>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($s['fecha'])) ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                <input type="hidden" name="accion" value="aprobar">
                                                <input type="hidden" name="solicitud_id" value="<?= $s['id'] ?>">
                                                <button type="submit" class="btn btn-exito btn-sm">✓ Aprobar</button>
                                            </form>
                                            <form method="POST" style="display:inline;margin-left:0.25rem;">
                                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                <input type="hidden" name="accion" value="rechazar">
                                                <input type="hidden" name="solicitud_id" value="<?= $s['id'] ?>">
                                                <button type="submit" class="btn btn-peligro btn-sm"
                                                        data-confirmar="¿Rechazar esta solicitud?">✕ Rechazar</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                            <p style="color:var(--texto-suave);font-size:0.9rem;">No hay solicitudes pendientes. ¡Todo al día!</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Tab: Propuestas recibidas -->
                <div id="tab-recibidas" class="tab-panel <?= $tabActiva === 'recibidas' ? 'activo' : '' ?>">
                    <div class="card">
                        <div class="card-titulo">📬 Propuestas recibidas</div>
                        <?php if ($propuestasRecibidas): ?>
                        <div class="tabla-contenedor">
                            <table class="tabla">
                                <thead>
                                    <tr>
                                        <th>De</th>
                                        <th>Tipo</th>
                                        <th>Descripción</th>
                                        <th>Fechas</th>
                                        <th>Recibida</th>
                                        <th>Estado / Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($propuestasRecibidas as $p): ?>
                                    <tr>
                                        <td><strong><?= e($p['remitente_nombre']) ?></strong></td>
                                        <td><?= $etiquetasTipo[$p['tipo']] ?? e($p['tipo']) ?></td>
                                        <td style="max-width:240px;"><?= e($p['descripcion']) ?></td>
                                        <td>
                                            <?php if ($p['fecha_inicio']): ?>
                                                <?= date('d/m/Y', strtotime($p['fecha_inicio'])) ?>
                                                <?= $p['fecha_fin'] ? '– ' . date('d/m/Y', strtotime($p['fecha_fin'])) : '' ?>
                                            <?php else: ?>—<?php endif; ?>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($p['fecha'])) ?></td>
                                        <td>
                                            <?php if ($p['estado'] === 'pendiente'): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                    <input type="hidden" name="accion" value="responder_propuesta">
                                                    <input type="hidden" name="solicitud_id" value="<?= $p['id'] ?>">
                                                    <input type="hidden" name="respuesta" value="aceptar">
                                                    <button type="submit" class="btn btn-exito btn-sm">✓ Aceptar</button>
                                                </form>
                                                <form method="POST" style="display:inline;margin-left:0.25rem;">
                                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                    <input type="hidden" name="accion" value="responder_propuesta">
                                                    <input type="hidden" name="solicitud_id" value="<?= $p['id'] ?>">
                                                    <input type="hidden" name="respuesta" value="rechazar">
                                                    <button type="submit" class="btn btn-peligro btn-sm"
                                                            data-confirmar="¿Rechazar esta propuesta?">✕ Rechazar</button>
                                                </form>
                                            <?php else: ?>
                                                <?php $est = $etiquetasEstado[$p['estado']] ?? ['label'=>$p['estado'],'clase'=>'badge-gris']; ?>
                                                <span class="badge <?= $est['clase'] ?>"><?= $est['label'] ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                            <p style="color:var(--texto-suave);font-size:0.9rem;">No tienes propuestas recibidas.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tab: Propuestas enviadas (solo responsables) -->
                <?php if (tieneRol([ROL_SUPERADMIN, ROL_ADMIN_RRHH, ROL_SUBADMIN])): ?>
                <div id="tab-enviadas" class="tab-panel <?= $tabActiva === 'enviadas' ? 'activo' : '' ?>">
                    <div class="card">
                        <div class="card-titulo">📤 Propuestas enviadas a empleados</div>
                        <?php if ($propuestasEnviadas): ?>
                        <div class="tabla-contenedor">
                            <table class="tabla">
                                <thead>
                                    <tr>
                                        <th>Empleado</th>
                                        <th>Tipo</th>
                                        <th>Descripción</th>
                                        <th>Fechas</th>
                                        <th>Enviada</th>
                                        <th>Respuesta</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($propuestasEnviadas as $p): ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($p['destinatario_nombre']) ?></strong><br>
                                            <span style="font-size:0.78rem;color:var(--texto-suave);"><?= e($p['destinatario_email']) ?></span>
                                        </td>
                                        <td><?= $etiquetasTipo[$p['tipo']] ?? e($p['tipo']) ?></td>
                                        <td style="max-width:240px;"><?= e($p['descripcion']) ?></td>
                                        <td>
                                            <?php if ($p['fecha_inicio']): ?>
                                                <?= date('d/m/Y', strtotime($p['fecha_inicio'])) ?>
                                                <?= $p['fecha_fin'] ? '– ' . date('d/m/Y', strtotime($p['fecha_fin'])) : '' ?>
                                            <?php else: ?>—<?php endif; ?>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($p['fecha'])) ?></td>
                                        <td>
                                            <?php $est = $etiquetasEstado[$p['estado']] ?? ['label'=>$p['estado'],'clase'=>'badge-gris']; ?>
                                            <span class="badge <?= $est['clase'] ?>"><?= $est['label'] ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                            <p style="color:var(--texto-suave);font-size:0.9rem;">No has enviado ninguna propuesta todavía.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tab: Nueva propuesta (responsable → empleado) -->
                <div id="tab-nueva-propuesta" class="tab-panel">
                    <div class="card" style="max-width:600px;">
                        <div class="card-titulo">📝 Nueva propuesta a empleado</div>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="accion" value="nueva_propuesta">

                            <div class="form-grupo">
                                <label for="destinatario_id">Empleado destinatario *</label>
                                <select name="destinatario_id" id="destinatario_id" class="form-control" required>
                                    <option value="">— Selecciona un empleado —</option>
                                    <?php foreach ($empleadosParaPropuesta as $emp): ?>
                                        <option value="<?= $emp['id'] ?>"><?= e($emp['nombre']) ?> — <?= e($emp['departamento_nombre'] ?? '—') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-grupo">
                                <label for="tipo_propuesta">Tipo de propuesta *</label>
                                <select name="tipo" id="tipo_propuesta" class="form-control" required>
                                    <option value="">— Selecciona —</option>
                                    <?php foreach ($etiquetasTipo as $val => $etiq): ?>
                                        <option value="<?= $val ?>"><?= $etiq ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-grupo" id="grupo-jornada-propuesta" style="display:none;">
                                <label for="tipo_jornada_nueva_propuesta">Turno destino *</label>
                                <select name="tipo_jornada_nueva" id="tipo_jornada_nueva_propuesta" class="form-control">
                                    <option value="">— Selecciona turno —</option>
                                    <option value="completa_manana">Jornada completa — Mañana (08:00-16:00)</option>
                                    <option value="completa_tarde">Jornada completa — Tarde (11:00-19:00)</option>
                                    <option value="parcial_manana">Jornada parcial — Mañana (08:00-13:00)</option>
                                    <option value="parcial_tarde">Jornada parcial — Tarde (14:00-19:00)</option>
                                </select>
                                <span class="form-ayuda">Sin fechas = cambio permanente. Con fechas = cambio temporal.</span>
                            </div>

                            <div class="form-grupo">
                                <label for="descripcion_propuesta">Descripción *</label>
                                <textarea name="descripcion_propuesta" id="descripcion_propuesta" class="form-control"
                                          rows="4" required
                                          placeholder="Ej: Esta semana necesitamos que vengas en turno de tarde..."></textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-grupo">
                                    <label for="fecha_inicio_propuesta">Fecha inicio</label>
                                    <input type="date" name="fecha_inicio_propuesta" id="fecha_inicio_propuesta" class="form-control">
                                </div>
                                <div class="form-grupo">
                                    <label for="fecha_fin_propuesta">Fecha fin</label>
                                    <input type="date" name="fecha_fin_propuesta" id="fecha_fin_propuesta" class="form-control">
                                </div>
                            </div>

                            <div class="alerta alerta-info" style="margin-bottom:1rem;">
                                <span>ℹ</span>
                                <div>El empleado recibirá la propuesta en su pestaña <strong>Recibidas</strong> y podrá aceptarla o rechazarla.</div>
                            </div>

                            <button type="submit" class="btn btn-primario">📨 Enviar propuesta</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Tab: Nueva solicitud (empleado → responsable) -->
                <div id="tab-nueva" class="tab-panel">
                    <div class="card" style="max-width:600px;">
                        <div class="card-titulo">📝 Nueva solicitud</div>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="accion" value="nueva_solicitud">

                            <div class="form-grupo">
                                <label for="tipo">Tipo de solicitud *</label>
                                <select name="tipo" id="tipo" class="form-control" required>
                                    <option value="">— Selecciona —</option>
                                    <?php foreach ($etiquetasTipo as $val => $etiq): ?>
                                        <option value="<?= $val ?>" <?= ($tipoFiltro === $val) ? 'selected' : '' ?>>
                                            <?= $etiq ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-grupo" id="grupo-jornada-solicitud" style="display:none;">
                                <label for="tipo_jornada_nueva_solicitud">Turno destino *</label>
                                <select name="tipo_jornada_nueva" id="tipo_jornada_nueva_solicitud" class="form-control">
                                    <option value="">— Selecciona turno —</option>
                                    <option value="completa_manana">Jornada completa — Mañana (08:00-16:00)</option>
                                    <option value="completa_tarde">Jornada completa — Tarde (11:00-19:00)</option>
                                    <option value="parcial_manana">Jornada parcial — Mañana (08:00-13:00)</option>
                                    <option value="parcial_tarde">Jornada parcial — Tarde (14:00-19:00)</option>
                                </select>
                                <span class="form-ayuda">Sin fechas = cambio permanente. Con fechas = cambio temporal.</span>
                            </div>

                            <div class="form-grupo">
                                <label for="descripcion">Descripción *</label>
                                <textarea name="descripcion" id="descripcion" class="form-control"
                                          rows="4" required
                                          placeholder="Describe los detalles de tu solicitud..."></textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-grupo">
                                    <label for="fecha_inicio">Fecha inicio</label>
                                    <input type="date" name="fecha_inicio" id="fecha_inicio"
                                           class="form-control" min="<?= date('Y-m-d') ?>">
                                    <span class="form-ayuda">Opcional según el tipo de solicitud</span>
                                </div>
                                <div class="form-grupo">
                                    <label for="fecha_fin">Fecha fin</label>
                                    <input type="date" name="fecha_fin" id="fecha_fin" class="form-control">
                                </div>
                            </div>

                            <div class="alerta alerta-info" style="margin-bottom:1rem;">
                                <span>ℹ</span>
                                <div>
                                    <strong>Recuerda:</strong> Las vacaciones se solicitan en enero para todo el año.
                                    Los cambios de horario se comunican trimestralmente.
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primario">📨 Enviar solicitud</button>
                        </form>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        <?php if ($tabActiva === 'admin'): ?>
        document.querySelector('[data-tab="admin"]')?.click();
        <?php elseif ($tabActiva === 'recibidas'): ?>
        document.querySelector('[data-tab="recibidas"]')?.click();
        <?php elseif ($tabActiva === 'enviadas'): ?>
        document.querySelector('[data-tab="enviadas"]')?.click();
        <?php elseif ($tipoFiltro): ?>
        document.querySelector('[data-tab="nueva"]')?.click();
        <?php endif; ?>

        // Mostrar/ocultar turno destino en nueva solicitud
        const tipoSol = document.getElementById('tipo');
        const grupoJornadaSol = document.getElementById('grupo-jornada-solicitud');
        const selectJornadaSol = document.getElementById('tipo_jornada_nueva_solicitud');
        if (tipoSol && grupoJornadaSol) {
            const toggleJornadaSol = () => {
                const esCambio = tipoSol.value === 'cambio_horario';
                grupoJornadaSol.style.display = esCambio ? 'block' : 'none';
                selectJornadaSol.required = esCambio;
            };
            tipoSol.addEventListener('change', toggleJornadaSol);
            toggleJornadaSol(); // por si hay valor preseleccionado
        }

        // Mostrar/ocultar turno destino en nueva propuesta
        const tipoProp = document.getElementById('tipo_propuesta');
        const grupoJornadaProp = document.getElementById('grupo-jornada-propuesta');
        const selectJornadaProp = document.getElementById('tipo_jornada_nueva_propuesta');
        if (tipoProp && grupoJornadaProp) {
            const toggleJornadaProp = () => {
                const esCambio = tipoProp.value === 'cambio_horario';
                grupoJornadaProp.style.display = esCambio ? 'block' : 'none';
                selectJornadaProp.required = esCambio;
            };
            tipoProp.addEventListener('change', toggleJornadaProp);
            toggleJornadaProp();
        }
    });
</script>
</body>
</html>