<?php
/**
 * api/usuarios.php
 * API de gestión de usuarios — Vestigia CheckIn
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requireLogin();

$pdo    = getDB();
$metodo = $_SERVER['REQUEST_METHOD'];
$vista  = $_GET['vista'] ?? '';

// ── Vista HTML: lista de empleados ────────────────────────────────────────────
if ($vista === 'lista') {
    if (!tieneRol([ROL_SUPERADMIN, ROL_ADMIN_RRHH, ROL_SUBADMIN])) {
        header('Location: ' . APP_URL . '/pages/main.php');
        exit;
    }
    mostrarListaEmpleados($pdo);
    exit;
}

// ── API JSON ──────────────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');

if ($metodo === 'GET') {
    $accion = $_GET['accion'] ?? '';

    if ($accion === 'listar' && tieneRol([ROL_SUPERADMIN, ROL_ADMIN_RRHH, ROL_SUBADMIN])) {
        $usuarios = tieneRol([ROL_SUPERADMIN, ROL_ADMIN_RRHH])
            ? getUsuarios()
            : getUsuarios(userDepto());
        echo json_encode($usuarios);
        exit;
    }
}

if ($metodo === 'POST' && tieneRol([ROL_SUPERADMIN, ROL_ADMIN_RRHH])) {
    $datos  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $accion = $datos['accion'] ?? '';

    // Crear usuario
    if ($accion === 'crear') {
        $nombre     = trim($datos['nombre'] ?? '');
        $email      = trim($datos['email'] ?? '');
        $password   = $datos['password'] ?? '';
        $rol        = $datos['rol'] ?? ROL_USER;
        $deptoId    = (int)($datos['departamento_id'] ?? 0) ?: null;
        $tipoJornada = $datos['tipo_jornada'] ?? 'sin_asignar';

        if (!$nombre || !$email || !$password) {
            echo json_encode(['error' => 'Nombre, email y contraseña son obligatorios.']);
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['error' => 'Email inválido.']);
            exit;
        }
        // Comprobar email único
        $existe = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $existe->execute([$email]);
        if ((int)$existe->fetchColumn() > 0) {
            echo json_encode(['error' => 'Ya existe un usuario con ese email.']);
            exit;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO users (nombre, email, password, rol, departamento_id, tipo_jornada, activo, archivado)
             VALUES (?, ?, ?, ?, ?, ?, 1, 0)"
        );
        $ok = $stmt->execute([
            $nombre, $email,
            password_hash($password, PASSWORD_DEFAULT),
            $rol, $deptoId, $tipoJornada
        ]);
        echo json_encode($ok ? ['ok' => true, 'id' => $pdo->lastInsertId()] : ['error' => 'Error al crear el usuario.']);
        exit;
    }

    // Editar usuario
    if ($accion === 'editar') {
        $id          = (int)($datos['id'] ?? 0);
        $nombre      = trim($datos['nombre'] ?? '');
        $email       = trim($datos['email'] ?? '');
        $rol         = $datos['rol'] ?? '';
        $deptoId     = (int)($datos['departamento_id'] ?? 0) ?: null;
        $tipoJornada = $datos['tipo_jornada'] ?? 'sin_asignar';

        if (!$id || !$nombre || !$email) {
            echo json_encode(['error' => 'Datos incompletos.']);
            exit;
        }

        $stmt = $pdo->prepare(
            "UPDATE users SET nombre=?, email=?, rol=?, departamento_id=?, tipo_jornada=? WHERE id=?"
        );
        $ok = $stmt->execute([$nombre, $email, $rol, $deptoId, $tipoJornada, $id]);

        // Cambiar contraseña si se proporciona
        if ($ok && !empty($datos['password'])) {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")
                ->execute([password_hash($datos['password'], PASSWORD_DEFAULT), $id]);
        }

        echo json_encode($ok ? ['ok' => true] : ['error' => 'Error al editar.']);
        exit;
    }

    // Archivar usuario
    if ($accion === 'archivar') {
        $id = (int)($datos['id'] ?? 0);
        $pdo->prepare("UPDATE users SET archivado=1, activo=0 WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    // Eliminar usuario (solo superadmin)
    if ($accion === 'eliminar' && tieneRol([ROL_SUPERADMIN])) {
        $id = (int)($datos['id'] ?? 0);
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    // Restaurar usuario archivado
    if ($accion === 'restaurar') {
        $id = (int)($datos['id'] ?? 0);
        $pdo->prepare("UPDATE users SET archivado=0, activo=1 WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]);
        exit;
    }
}

echo json_encode(['error' => 'Acción no válida o sin permisos.']);

// ── Función para renderizar la página HTML de lista de empleados ──────────────
function mostrarListaEmpleados(PDO $pdo): void
{
    $buscar   = trim($_GET['q'] ?? '');
    $deptoFil = (int)($_GET['departamento_id'] ?? 0);
    $archivado = (int)($_GET['archivado'] ?? 0);

    $sql = "SELECT u.*, d.nombre AS departamento_nombre
            FROM users u LEFT JOIN departamentos d ON d.id = u.departamento_id
            WHERE u.archivado = ?";
    $params = [$archivado];

    if ($buscar) {
        $sql .= " AND (u.nombre LIKE ? OR u.email LIKE ?)";
        $params[] = "%$buscar%";
        $params[] = "%$buscar%";
    }
    if ($deptoFil) {
        $sql .= " AND u.departamento_id = ?";
        $params[] = $deptoFil;
    }
    if (!tieneRol([ROL_SUPERADMIN, ROL_ADMIN_RRHH])) {
        $sql .= " AND u.departamento_id = ?";
        $params[] = userDepto();
    }
    $sql .= " ORDER BY u.nombre";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $usuarios = $stmt->fetchAll();

    $departamentos = $pdo->query("SELECT * FROM departamentos ORDER BY nombre")->fetchAll();

    $roles = [
        ROL_SUPERADMIN => 'Superadmin',
        ROL_ADMIN_RRHH => 'Admin RRHH',
        ROL_SUBADMIN   => 'Jefe de dpto',
        ROL_USER       => 'Empleado',
    ];

    require_once dirname(__DIR__) . '/includes/funciones.php';
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empleados — Vestigia CheckIn</title>
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
                <h2>🗃️ Gestión de Empleados</h2>
                <p>Administra el equipo de Vestigia.</p>
                <?php if (tieneRol([ROL_SUPERADMIN, ROL_ADMIN_RRHH])): ?>
                <button class="btn btn-primario" id="btn-nuevo-usuario">+ Nuevo empleado</button>
                <?php endif; ?>
            </div>

            <!-- Filtros -->
            <form method="GET" style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-bottom:1.25rem;align-items:flex-end;">
                <input type="hidden" name="vista" value="lista">
                <div class="form-grupo" style="margin:0;">
                    <label>Buscar</label>
                    <input type="text" name="q" class="form-control" placeholder="Nombre o email..." value="<?= e($buscar) ?>">
                </div>
                <?php if (tieneRol([ROL_SUPERADMIN, ROL_ADMIN_RRHH])): ?>
                <div class="form-grupo" style="margin:0;">
                    <label>Departamento</label>
                    <select name="departamento_id" class="form-control" data-autosubmit>
                        <option value="0">— Todos —</option>
                        <?php foreach ($departamentos as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $deptoFil===$d['id']?'selected':'' ?>><?= e($d['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="form-grupo" style="margin:0;">
                    <label>Estado</label>
                    <select name="archivado" class="form-control" data-autosubmit>
                        <option value="0" <?= !$archivado?'selected':'' ?>>Activos</option>
                        <option value="1" <?= $archivado?'selected':'' ?>>Archivados</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-secundario">🔍 Filtrar</button>
            </form>

            <div class="card">
                <div class="card-titulo">👥 Empleados (<?= count($usuarios) ?>)</div>
                <?php if ($usuarios): ?>
                <div class="tabla-contenedor">
                    <table class="tabla">
                        <thead>
                            <tr>
                                <th>Empleado</th>
                                <th>Departamento</th>
                                <th>Rol</th>
                                <th>Jornada</th>
                                <th>Estado</th>
                                <?php if (tieneRol([ROL_SUPERADMIN, ROL_ADMIN_RRHH])): ?>
                                <th>Acciones</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $u): ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:0.75rem;">
                                        <div class="avatar" style="width:36px;height:36px;font-size:1rem;">
                                            <?php if ($u['foto']): ?>
                                                <img src="<?= APP_URL ?>/assets/img/uploads/<?= e($u['foto']) ?>" alt="">
                                            <?php else: ?>
                                                <?= strtoupper(mb_substr($u['nombre'],0,1)) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <strong><?= e($u['nombre']) ?></strong><br>
                                            <span style="font-size:0.78rem;color:var(--texto-suave);"><?= e($u['email']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><?= e($u['departamento_nombre'] ?? '—') ?></td>
                                <td><span class="badge badge-primario"><?= $roles[$u['rol']] ?? e($u['rol']) ?></span></td>
                                <td>
                                    <?php
                                    $jornadas = [
                                        'completa_manana' => 'Completa Mañana',
                                        'completa_tarde'  => 'Completa Tarde',
                                        'parcial_manana'  => 'Parcial Mañana',
                                        'parcial_tarde'   => 'Parcial Tarde',
                                        'sin_asignar'     => '⚠ Sin asignar',
                                    ];
                                    echo $jornadas[$u['tipo_jornada']] ?? e($u['tipo_jornada']);
                                    ?>
                                </td>
                                <td>
                                    <?php if ($u['archivado']): ?>
                                        <span class="badge badge-rojo">Archivado</span>
                                    <?php elseif ($u['activo']): ?>
                                        <span class="badge badge-verde">Activo</span>
                                    <?php else: ?>
                                        <span class="badge badge-amarillo">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <?php if (tieneRol([ROL_SUPERADMIN, ROL_ADMIN_RRHH])): ?>
                                <td>
                                    <button class="btn btn-secundario btn-sm btn-editar-usuario"
                                            data-id="<?= $u['id'] ?>"
                                            data-nombre="<?= e($u['nombre']) ?>"
                                            data-email="<?= e($u['email']) ?>"
                                            data-rol="<?= e($u['rol']) ?>"
                                            data-depto="<?= $u['departamento_id'] ?>"
                                            data-jornada="<?= e($u['tipo_jornada']) ?>">✏️</button>
                                    <?php if (!$u['archivado']): ?>
                                    <button class="btn btn-amarillo btn-sm"
                                            data-accion="archivar" data-id="<?= $u['id'] ?>"
                                            data-confirmar="¿Archivar a <?= e($u['nombre']) ?>?">📦</button>
                                    <?php else: ?>
                                    <button class="btn btn-exito btn-sm"
                                            data-accion="restaurar" data-id="<?= $u['id'] ?>"
                                            data-confirmar="¿Restaurar a <?= e($u['nombre']) ?>?">↩️</button>
                                    <?php endif; ?>
                                    <?php if (tieneRol([ROL_SUPERADMIN])): ?>
                                    <button class="btn btn-peligro btn-sm"
                                            data-accion="eliminar" data-id="<?= $u['id'] ?>"
                                            data-confirmar="¿ELIMINAR a <?= e($u['nombre']) ?>? Esta acción no se puede deshacer.">🗑️</button>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <p style="color:var(--texto-suave);font-size:0.9rem;">No se encontraron empleados.</p>
                <?php endif; ?>
            </div>

        </main>
    </div>
</div>

<!-- Modal nuevo/editar usuario -->
<div id="modal-usuario" class="modal-empleados-overlay">
    <div class="modal-contenido" style="max-width:520px;">
        <div class="modal-cabecera">
            <h3 id="modal-titulo">Nuevo empleado</h3>
            <button class="modal-cerrar" id="modal-cerrar">✕</button>
        </div>
        <form id="form-usuario" method="POST" action="">
            <input type="hidden" id="u-accion" name="accion" value="crear">
            <input type="hidden" id="u-id" name="id" value="">
            <div class="form-row">
                <div class="form-grupo">
                    <label>Nombre *</label>
                    <input type="text" id="u-nombre" name="nombre" class="form-control" required>
                </div>
                <div class="form-grupo">
                    <label>Email *</label>
                    <input type="email" id="u-email" name="email" class="form-control" required>
                </div>
            </div>
            <div class="form-grupo">
                <label>Contraseña <span id="u-pass-hint" style="font-weight:400;color:var(--texto-suave);font-size:0.8rem;">(obligatoria)</span></label>
                <input type="password" id="u-password" name="password" class="form-control" minlength="8">
            </div>
            <div class="form-row">
                <div class="form-grupo">
                    <label>Rol</label>
                    <select id="u-rol" name="rol" class="form-control">
                        <option value="user">Empleado</option>
                        <option value="subadmin">Jefe de dpto</option>
                        <option value="admin_rrhh">Admin RRHH</option>
                        <?php if (tieneRol([ROL_SUPERADMIN])): ?>
                        <option value="superadmin">Superadmin</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-grupo">
                    <label>Tipo de jornada</label>
                    <select id="u-jornada" name="tipo_jornada" class="form-control">
                        <option value="sin_asignar">— Sin asignar —</option>
                        <option value="completa_manana">Jornada completa — Mañana (08:00-16:00)</option>
                        <option value="completa_tarde">Jornada completa — Tarde (11:00-19:00)</option>
                        <option value="parcial_manana">Jornada parcial — Mañana (08:00-13:00)</option>
                        <option value="parcial_tarde">Jornada parcial — Tarde (14:00-19:00)</option>
                    </select>
                </div>
            </div>
            <div class="form-grupo">
                <label>Departamento</label>
                <select id="u-depto" name="departamento_id" class="form-control">
                    <option value="">— Sin departamento —</option>
                    <?php foreach ($departamentos as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= e($d['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
                <button type="button" class="btn btn-secundario" id="modal-cancelar">Cancelar</button>
                <button type="submit" class="btn btn-primario">💾 Guardar</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script>
// Gestión del modal de usuario
const modal        = document.getElementById('modal-usuario');
const formUsuario  = document.getElementById('form-usuario');
const modalTitulo  = document.getElementById('modal-titulo');
const passHint     = document.getElementById('u-pass-hint');

document.getElementById('btn-nuevo-usuario')?.addEventListener('click', () => {
    formUsuario.reset();
    document.getElementById('u-accion').value = 'crear';
    document.getElementById('u-id').value = '';
    modalTitulo.textContent = 'Nuevo empleado';
    passHint.textContent = '(obligatoria)';
    modal.style.display = 'flex';
});

document.querySelectorAll('.btn-editar-usuario').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('u-accion').value  = 'editar';
        document.getElementById('u-id').value      = btn.dataset.id;
        document.getElementById('u-nombre').value  = btn.dataset.nombre;
        document.getElementById('u-email').value   = btn.dataset.email;
        document.getElementById('u-rol').value     = btn.dataset.rol;
        document.getElementById('u-depto').value   = btn.dataset.depto;
        document.getElementById('u-jornada').value = btn.dataset.jornada;
        document.getElementById('u-password').value = '';
        modalTitulo.textContent = 'Editar empleado';
        passHint.textContent = '(dejar vacío para no cambiar)';
        modal.style.display = 'flex';
    });
});

['modal-cerrar','modal-cancelar'].forEach(id => {
    document.getElementById(id)?.addEventListener('click', () => { modal.style.display = 'none'; });
});

// Enviar formulario vía AJAX
formUsuario.addEventListener('submit', async e => {
    e.preventDefault();
    const datos = Object.fromEntries(new FormData(formUsuario));
    const resp  = await fetch('<?= APP_URL ?>/api/usuarios.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(datos)
    });
    const json = await resp.json();
    if (json.ok) { location.reload(); }
    else { alert(json.error || 'Error al guardar.'); }
});

// Acciones de archivar/eliminar/restaurar
document.querySelectorAll('[data-accion]').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm(btn.dataset.confirmar)) return;
        const resp = await fetch('<?= APP_URL ?>/api/usuarios.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ accion: btn.dataset.accion, id: btn.dataset.id })
        });
        const json = await resp.json();
        if (json.ok) location.reload();
        else alert(json.error || 'Error.');
    });
});
</script>
</body>
</html>
    <?php
}