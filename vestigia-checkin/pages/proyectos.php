<?php
/**
 * pages/proyectos.php
 * Gestión de proyectos — Vestigia CheckIn
 * Acceso: superadmin y subadmin
 */
 
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
 
requireLogin();
 
if (!tieneRol([ROL_SUPERADMIN, ROL_SUBADMIN])) {
    header('Location: ' . APP_URL . '/pages/main.php');
    exit;
}
 
$pdo     = getDB();
$mensaje = '';
$error   = '';
 
// ── Procesar formularios POST ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido.';
    } else {
        $accion = $_POST['accion'] ?? '';
 
        // ── Crear proyecto ────────────────────────────────────────────────────
        if ($accion === 'crear') {
            $nombre   = trim($_POST['nombre'] ?? '');
            $deptoId  = (int)($_POST['departamento_id'] ?? 0) ?: null;
            $fechaIni = $_POST['fecha_inicio'] ?? date('Y-m-d');
            $fechaFin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
            $uids     = $_POST['usuarios'] ?? [];
 
            if (!$nombre) {
                $error = 'El nombre del proyecto es obligatorio.';
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO proyectos (nombre, departamento_id, activo, fecha_inicio, fecha_fin)
                     VALUES (?, ?, 1, ?, ?)"
                );
                $stmt->execute([$nombre, $deptoId, $fechaIni, $fechaFin]);
                $proyId = $pdo->lastInsertId();
 
                foreach ($uids as $uid) {
                    $pdo->prepare("INSERT IGNORE INTO proyecto_usuario (proyecto_id, user_id) VALUES (?, ?)")
                        ->execute([$proyId, (int)$uid]);
                }
                $mensaje = "Proyecto «{$nombre}» creado correctamente.";
            }
        }
 
        // ── Editar proyecto ───────────────────────────────────────────────────
        if ($accion === 'editar') {
            $id       = (int)($_POST['proyecto_id'] ?? 0);
            $nombre   = trim($_POST['nombre'] ?? '');
            $deptoId  = (int)($_POST['departamento_id'] ?? 0) ?: null;
            $fechaIni = $_POST['fecha_inicio'] ?? date('Y-m-d');
            $fechaFin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
            $uids     = $_POST['usuarios'] ?? [];
 
            if (!$nombre) {
                $error = 'El nombre del proyecto es obligatorio.';
            } else {
                $pdo->prepare(
                    "UPDATE proyectos SET nombre=?, departamento_id=?, fecha_inicio=?, fecha_fin=? WHERE id=?"
                )->execute([$nombre, $deptoId, $fechaIni, $fechaFin, $id]);
 
                $pdo->prepare("DELETE FROM proyecto_usuario WHERE proyecto_id = ?")->execute([$id]);
                foreach ($uids as $uid) {
                    $pdo->prepare("INSERT IGNORE INTO proyecto_usuario (proyecto_id, user_id) VALUES (?, ?)")
                        ->execute([$id, (int)$uid]);
                }
                $mensaje = 'Proyecto actualizado correctamente.';
            }
        }
 
        // ── Archivar / reactivar ──────────────────────────────────────────────
        if ($accion === 'archivar') {
            $id     = (int)($_POST['proyecto_id'] ?? 0);
            $activo = (int)($_POST['activo_actual'] ?? 1);
            $pdo->prepare("UPDATE proyectos SET activo = ? WHERE id = ?")
                ->execute([$activo ? 0 : 1, $id]);
            $mensaje = $activo ? 'Proyecto archivado.' : 'Proyecto reactivado.';
        }
    }
}
 
// ── Cargar proyectos ──────────────────────────────────────────────────────────
if (tieneRol([ROL_SUPERADMIN])) {
    $proyectos = $pdo->query(
        "SELECT p.*, d.nombre AS departamento_nombre
         FROM proyectos p
         LEFT JOIN departamentos d ON d.id = p.departamento_id
         ORDER BY p.activo DESC, p.nombre"
    )->fetchAll();
} else {
    $stmt = $pdo->prepare(
        "SELECT p.*, d.nombre AS departamento_nombre
         FROM proyectos p
         LEFT JOIN departamentos d ON d.id = p.departamento_id
         WHERE p.departamento_id = ? OR p.departamento_id IS NULL
         ORDER BY p.activo DESC, p.nombre"
    );
    $stmt->execute([userDepto()]);
    $proyectos = $stmt->fetchAll();
}
 
// Usuarios asignados a cada proyecto
$asignaciones = [];
$rows = $pdo->query("SELECT proyecto_id, user_id FROM proyecto_usuario")->fetchAll();
foreach ($rows as $r) {
    $asignaciones[$r['proyecto_id']][] = $r['user_id'];
}
 
// Departamentos
$departamentos = $pdo->query("SELECT id, nombre FROM departamentos ORDER BY nombre")->fetchAll();
 
// Usuarios disponibles
if (tieneRol([ROL_SUPERADMIN])) {
    $usuarios = $pdo->query(
        "SELECT u.id, u.nombre, d.nombre AS departamento_nombre
         FROM users u LEFT JOIN departamentos d ON d.id = u.departamento_id
         WHERE u.activo=1 AND u.archivado=0
         ORDER BY u.nombre"
    )->fetchAll();
} else {
    $stmt = $pdo->prepare(
        "SELECT u.id, u.nombre, d.nombre AS departamento_nombre
         FROM users u LEFT JOIN departamentos d ON d.id = u.departamento_id
         WHERE u.activo=1 AND u.archivado=0 AND u.departamento_id=?
         ORDER BY u.nombre"
    );
    $stmt->execute([userDepto()]);
    $usuarios = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proyectos — Vestigia CheckIn</title>
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
                <div>
                    <h2>📁 Gestión de Proyectos</h2>
                    <p>Crea, edita y asigna empleados a los proyectos de la empresa.</p>
                </div>
                <button class="btn btn-primario" onclick="Vestigia.abrirModal('modal-nuevo')">
                    + Nuevo proyecto
                </button>
            </div>
 
            <?php if ($mensaje): ?>
                <div class="alerta alerta-exito"><span>✓</span> <?= e($mensaje) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alerta alerta-error"><span>✕</span> <?= e($error) ?></div>
            <?php endif; ?>
 
            <!-- Tabs activos / archivados -->
            <div class="tabs-wrapper">
                <div class="tabs">
                    <button class="tab-btn activo" data-tab="activos">Activos</button>
                    <button class="tab-btn" data-tab="archivados">Archivados</button>
                </div>
 
                <!-- Tab: Proyectos activos -->
                <div id="tab-activos" class="tab-panel activo">
                    <?php $activos = array_filter($proyectos, fn($p) => $p['activo']); ?>
                    <?php if ($activos): ?>
                    <div class="tabla-contenedor">
                        <table class="tabla">
                            <thead>
                                <tr>
                                    <th>Proyecto</th>
                                    <th>Departamento</th>
                                    <th>Inicio</th>
                                    <th>Fin</th>
                                    <th>Empleados</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activos as $p): ?>
                                <?php $uidsProyecto = $asignaciones[$p['id']] ?? []; ?>
                                <tr>
                                    <td><strong><?= e($p['nombre']) ?></strong></td>
                                    <td><?= e($p['departamento_nombre'] ?? 'Interdepartamental') ?></td>
                                    <td><?= $p['fecha_inicio'] ? date('d/m/Y', strtotime($p['fecha_inicio'])) : '—' ?></td>
                                    <td><?= $p['fecha_fin'] ? date('d/m/Y', strtotime($p['fecha_fin'])) : '—' ?></td>
                                    <td>
                                        <span class="badge-count"><?= count($uidsProyecto) ?> empleado<?= count($uidsProyecto) !== 1 ? 's' : '' ?></span>
                                    </td>
                                    <td style="display:flex;gap:0.4rem;flex-wrap:wrap;">
                                        <button class="btn btn-secundario btn-sm"
                                            onclick="abrirEditar(<?= htmlspecialchars(json_encode([
                                                'id'               => $p['id'],
                                                'nombre'           => $p['nombre'],
                                                'departamento_id'  => $p['departamento_id'],
                                                'fecha_inicio'     => $p['fecha_inicio'],
                                                'fecha_fin'        => $p['fecha_fin'],
                                                'usuarios'         => $uidsProyecto,
                                            ]), ENT_QUOTES) ?>)">
                                            ✏️ Editar
                                        </button>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                            <input type="hidden" name="accion" value="archivar">
                                            <input type="hidden" name="proyecto_id" value="<?= $p['id'] ?>">
                                            <input type="hidden" name="activo_actual" value="1">
                                            <button type="submit" class="btn btn-peligro btn-sm"
                                                    data-confirmar="¿Archivar el proyecto «<?= e($p['nombre']) ?>»?">
                                                📦 Archivar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <div class="card" style="text-align:center;padding:2.5rem;">
                            <p style="font-size:2rem;margin-bottom:0.5rem;">📁</p>
                            <p style="color:var(--texto-suave);">No hay proyectos activos todavía.</p>
                            <button class="btn btn-primario" style="margin-top:1rem;"
                                    onclick="Vestigia.abrirModal('modal-nuevo')">
                                + Crear el primero
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
 
                <!-- Tab: Proyectos archivados -->
                <div id="tab-archivados" class="tab-panel">
                    <?php $archivados = array_filter($proyectos, fn($p) => !$p['activo']); ?>
                    <?php if ($archivados): ?>
                    <div class="tabla-contenedor">
                        <table class="tabla">
                            <thead>
                                <tr>
                                    <th>Proyecto</th>
                                    <th>Departamento</th>
                                    <th>Inicio</th>
                                    <th>Fin</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($archivados as $p): ?>
                                <tr style="opacity:0.6;">
                                    <td><?= e($p['nombre']) ?></td>
                                    <td><?= e($p['departamento_nombre'] ?? 'Interdepartamental') ?></td>
                                    <td><?= $p['fecha_inicio'] ? date('d/m/Y', strtotime($p['fecha_inicio'])) : '—' ?></td>
                                    <td><?= $p['fecha_fin'] ? date('d/m/Y', strtotime($p['fecha_fin'])) : '—' ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                            <input type="hidden" name="accion" value="archivar">
                                            <input type="hidden" name="proyecto_id" value="<?= $p['id'] ?>">
                                            <input type="hidden" name="activo_actual" value="0">
                                            <button type="submit" class="btn btn-exito btn-sm">
                                                ♻️ Reactivar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <p style="color:var(--texto-suave);padding:1.5rem 0;">No hay proyectos archivados.</p>
                    <?php endif; ?>
                </div>
            </div><!-- /tabs-wrapper -->
 
        </main>
    </div>
</div>
 
<!-- ── Modal: Nuevo proyecto ────────────────────────────────────────────────── -->
<div class="modal-overlay" id="modal-nuevo">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-titulo">📁 Nuevo proyecto</div>
            <button class="modal-cerrar" onclick="Vestigia.cerrarModal('modal-nuevo')">✕</button>
        </div>
        <div class="modal-cuerpo">
            <form method="POST" id="form-nuevo">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="accion" value="crear">
 
                <div class="form-grupo">
                    <label>Nombre del proyecto <span style="color:var(--acento);">*</span></label>
                    <input type="text" name="nombre" class="form-control" placeholder="Ej: Edición Especial Roma" required>
                </div>
 
                <div class="form-row">
                    <div class="form-grupo">
                        <label>Fecha de inicio</label>
                        <input type="date" name="fecha_inicio" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-grupo">
                        <label>Fecha de fin <span style="color:var(--texto-suave);font-size:0.8rem;">(opcional)</span></label>
                        <input type="date" name="fecha_fin" class="form-control">
                    </div>
                </div>
 
                <div class="form-grupo">
                    <label>Departamento <span style="color:var(--texto-suave);font-size:0.8rem;">(vacío = interdepartamental)</span></label>
                    <select name="departamento_id" class="form-control">
                        <option value="">— Interdepartamental —</option>
                        <?php foreach ($departamentos as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= e($d['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
 
                <div class="form-grupo">
                    <label>Asignar empleados</label>
                    <div style="max-height:200px;overflow-y:auto;border:1px solid var(--borde);border-radius:var(--radio);padding:0.5rem;">
                        <?php if ($usuarios): ?>
                            <?php foreach ($usuarios as $u): ?>
                            <label style="display:flex;align-items:center;gap:0.5rem;padding:0.3rem 0.25rem;cursor:pointer;font-weight:normal;">
                                <input type="checkbox" name="usuarios[]" value="<?= $u['id'] ?>">
                                <?= e($u['nombre']) ?>
                                <?php if (!empty($u['departamento_nombre'])): ?>
                                    <span style="color:var(--texto-suave);font-size:0.78rem;">(<?= e($u['departamento_nombre']) ?>)</span>
                                <?php endif; ?>
                            </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color:var(--texto-suave);font-size:0.85rem;padding:0.5rem;">No hay empleados disponibles.</p>
                        <?php endif; ?>
                    </div>
                </div>
 
                <div style="display:flex;justify-content:flex-end;gap:0.75rem;margin-top:1rem;">
                    <button type="button" class="btn btn-secundario" onclick="Vestigia.cerrarModal('modal-nuevo')">Cancelar</button>
                    <button type="submit" class="btn btn-primario">💾 Crear proyecto</button>
                </div>
            </form>
        </div>
    </div>
</div>
 
<!-- ── Modal: Editar proyecto ───────────────────────────────────────────────── -->
<div class="modal-overlay" id="modal-editar">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-titulo">✏️ Editar proyecto</div>
            <button class="modal-cerrar" onclick="Vestigia.cerrarModal('modal-editar')">✕</button>
        </div>
        <div class="modal-cuerpo">
            <form method="POST" id="form-editar">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="proyecto_id" id="edit-proyecto-id">
 
                <div class="form-grupo">
                    <label>Nombre del proyecto <span style="color:var(--acento);">*</span></label>
                    <input type="text" name="nombre" id="edit-nombre" class="form-control" required>
                </div>
 
                <div class="form-row">
                    <div class="form-grupo">
                        <label>Fecha de inicio</label>
                        <input type="date" name="fecha_inicio" id="edit-fecha-inicio" class="form-control">
                    </div>
                    <div class="form-grupo">
                        <label>Fecha de fin <span style="color:var(--texto-suave);font-size:0.8rem;">(opcional)</span></label>
                        <input type="date" name="fecha_fin" id="edit-fecha-fin" class="form-control">
                    </div>
                </div>
 
                <div class="form-grupo">
                    <label>Departamento</label>
                    <select name="departamento_id" id="edit-departamento" class="form-control">
                        <option value="">— Interdepartamental —</option>
                        <?php foreach ($departamentos as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= e($d['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
 
                <div class="form-grupo">
                    <label>Empleados asignados</label>
                    <div id="edit-usuarios-lista" style="max-height:200px;overflow-y:auto;border:1px solid var(--borde);border-radius:var(--radio);padding:0.5rem;">
                        <?php foreach ($usuarios as $u): ?>
                        <label style="display:flex;align-items:center;gap:0.5rem;padding:0.3rem 0.25rem;cursor:pointer;font-weight:normal;" data-uid="<?= $u['id'] ?>">
                            <input type="checkbox" class="edit-usuario-check" name="usuarios[]" value="<?= $u['id'] ?>">
                            <?= e($u['nombre']) ?>
                            <?php if (!empty($u['departamento_nombre'])): ?>
                                <span style="color:var(--texto-suave);font-size:0.78rem;">(<?= e($u['departamento_nombre']) ?>)</span>
                            <?php endif; ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
 
                <div style="display:flex;justify-content:flex-end;gap:0.75rem;margin-top:1rem;">
                    <button type="button" class="btn btn-secundario" onclick="Vestigia.cerrarModal('modal-editar')">Cancelar</button>
                    <button type="submit" class="btn btn-primario">💾 Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>
 
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script>
function abrirEditar(datos) {
    document.getElementById('edit-proyecto-id').value    = datos.id;
    document.getElementById('edit-nombre').value         = datos.nombre;
    document.getElementById('edit-fecha-inicio').value   = datos.fecha_inicio || '';
    document.getElementById('edit-fecha-fin').value      = datos.fecha_fin || '';
    document.getElementById('edit-departamento').value   = datos.departamento_id || '';
 
    // Marcar los checkboxes de usuarios asignados
    document.querySelectorAll('.edit-usuario-check').forEach(cb => {
        cb.checked = datos.usuarios.includes(parseInt(cb.value));
    });
 
    Vestigia.abrirModal('modal-editar');
}
</script>
<style>
.badge-count {
    display: inline-block;
    background: var(--fondo);
    border: 1px solid var(--borde);
    border-radius: 999px;
    padding: 0.15rem 0.65rem;
    font-size: 0.8rem;
    color: var(--texto-suave);
}
 
/* Modal con más respiración para que no parezca tan apretado */
#modal-nuevo .modal,
#modal-editar .modal {
    padding: 0;
}
#modal-nuevo .modal-header,
#modal-editar .modal-header {
    padding: 1.4rem 1.75rem 1.1rem;
}
#modal-nuevo .modal-cuerpo,
#modal-editar .modal-cuerpo {
    padding: 0.25rem 1.75rem 1.75rem;
}
#modal-nuevo .form-grupo,
#modal-editar .form-grupo {
    margin-bottom: 1.25rem;
}
#modal-nuevo .form-row,
#modal-editar .form-row {
    gap: 1rem;
    margin-bottom: 0;
}
#modal-nuevo label,
#modal-editar label {
    margin-bottom: 0.45rem;
    display: block;
}
/* Lista de empleados con más padding interno */
#modal-nuevo .form-grupo > div,
#modal-editar #edit-usuarios-lista {
    padding: 0.6rem 0.75rem;
}
#modal-nuevo .form-grupo > div label,
#modal-editar #edit-usuarios-lista label {
    padding: 0.4rem 0.25rem;
    margin-bottom: 0;
}
/* Botones del pie del modal */
#modal-nuevo form > div:last-child,
#modal-editar form > div:last-child {
    padding-top: 0.5rem;
    border-top: 1px solid var(--borde);
    margin-top: 1.5rem;
}
</style>
</body>
</html>