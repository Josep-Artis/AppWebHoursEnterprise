<?php
/**
 * perfil.php
 * Página de perfil del usuario — Vestigia CheckIn
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requireLogin();

$userId  = userId();
$usuario = getUsuario($userId);
$pdo     = getDB();
$mensaje = '';
$error   = '';

// ── Procesar formulario ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido.';
    } else {
        $accion = $_POST['accion'] ?? '';

        // Cambiar datos personales
        if ($accion === 'actualizar_perfil') {
            $nombre = trim($_POST['nombre'] ?? '');
            if (!$nombre) {
                $error = 'El nombre es obligatorio.';
            } else {
                // Gestionar subida de foto
                $fotoNueva = $usuario['foto'];
                if (!empty($_FILES['foto']['name'])) {
                    $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                        $error = 'Formato de imagen no válido.';
                    } elseif ($_FILES['foto']['size'] > 2 * 1024 * 1024) {
                        $error = 'La imagen no puede superar 2 MB.';
                    } else {
                        $uploadDir = dirname(__DIR__) . '/assets/img/uploads/';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                        $fotoNueva = 'avatar_' . $userId . '_' . time() . '.' . $ext;
                        if (!move_uploaded_file($_FILES['foto']['tmp_name'], $uploadDir . $fotoNueva)) {
                            $error = 'Error al subir la imagen.';
                            $fotoNueva = $usuario['foto'];
                        }
                    }
                }
                if (!$error) {
                    $stmt = $pdo->prepare("UPDATE users SET nombre = ?, foto = ? WHERE id = ?");
                    if ($stmt->execute([$nombre, $fotoNueva, $userId])) {
                        $_SESSION['user_nombre'] = $nombre;
                        $_SESSION['user_foto']   = $fotoNueva;
                        $usuario['nombre'] = $nombre;
                        $usuario['foto']   = $fotoNueva;
                        $mensaje = 'Perfil actualizado correctamente.';
                    } else {
                        $error = 'Error al guardar los cambios.';
                    }
                }
            }
        }

        // Cambiar contraseña
        elseif ($accion === 'cambiar_password') {
            $actual  = $_POST['password_actual'] ?? '';
            $nueva   = $_POST['password_nueva'] ?? '';
            $confirm = $_POST['password_confirmar'] ?? '';

            if (!$actual || !$nueva || !$confirm) {
                $error = 'Todos los campos de contraseña son obligatorios.';
            } elseif (strlen($nueva) < 8) {
                $error = 'La nueva contraseña debe tener al menos 8 caracteres.';
            } elseif ($nueva !== $confirm) {
                $error = 'Las contraseñas nuevas no coinciden.';
            } else {
                // Verificar contraseña actual
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $hash = $stmt->fetchColumn();
                if (!password_verify($actual, $hash)) {
                    $error = 'La contraseña actual no es correcta.';
                } else {
                    $nuevoHash = password_hash($nueva, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$nuevoHash, $userId]);
                    $mensaje = 'Contraseña cambiada correctamente.';
                }
            }
        }
    }
}

// Estadísticas del usuario
$stmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS total_fichajes,
        SUM(tarde) AS total_tarde,
        SUM(teletrabajo) AS total_teletrabajo,
        SUM(horas_extra) AS total_extra,
        MIN(fecha) AS primer_fichaje
     FROM fichajes WHERE user_id = ?"
);
$stmt->execute([$userId]);
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil — Vestigia CheckIn</title>
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
                <h2>👤 Mi Perfil</h2>
                <p>Gestiona tu información personal y preferencias de cuenta.</p>
            </div>

            <?php if ($mensaje): ?>
                <div class="alerta alerta-exito"><span>✓</span> <?= e($mensaje) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alerta alerta-error"><span>✕</span> <?= e($error) ?></div>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:300px 1fr;gap:1.25rem;align-items:start;">

                <!-- Tarjeta de perfil -->
                <div class="card" style="text-align:center;">
                    <div class="avatar avatar-lg" style="margin:0 auto 1rem;">
                        <?php if ($usuario['foto']): ?>
                            <img src="<?= APP_URL ?>/assets/img/uploads/<?= e($usuario['foto']) ?>" alt="Avatar">
                        <?php else: ?>
                            <?= strtoupper(mb_substr($usuario['nombre'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <h3 style="margin-bottom:0.25rem;"><?= e($usuario['nombre']) ?></h3>
                    <p style="color:var(--texto-suave);font-size:0.88rem;margin-bottom:0.5rem;"><?= e($usuario['email']) ?></p>
                    <span class="badge badge-primario"><?= e(str_replace('_',' ', $usuario['rol'])) ?></span>
                    <hr class="separador">
                    <div style="font-size:0.85rem;text-align:left;">
                        <p><strong>Departamento:</strong> <?= e($usuario['departamento_nombre'] ?? '—') ?></p>
                        <p><strong>Tipo de jornada:</strong>
                            <?php
                            $tipos = [
                                'completa_manana' => 'Completa — Mañana (08:00-16:00)',
                                'completa_tarde'  => 'Completa — Tarde (11:00-19:00)',
                                'parcial_manana'  => 'Parcial — Mañana (08:00-13:00)',
                                'parcial_tarde'   => 'Parcial — Tarde (14:00-19:00)',
                                'sin_asignar'     => '⚠ Sin asignar',
                            ];
                            echo $tipos[$usuario['tipo_jornada']] ?? e($usuario['tipo_jornada']);
                            ?>
                        </p>
                        <?php if ($stats['primer_fichaje']): ?>
                        <p><strong>Primer fichaje:</strong> <?= date('d/m/Y', strtotime($stats['primer_fichaje'])) ?></p>
                        <?php endif; ?>
                    </div>
                    <hr class="separador">
                    <!-- Estadísticas totales -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;text-align:center;">
                        <div>
                            <div style="font-size:1.4rem;font-weight:700;color:var(--primario);"><?= (int)$stats['total_fichajes'] ?></div>
                            <div style="font-size:0.7rem;color:var(--texto-suave);text-transform:uppercase;">Fichajes</div>
                        </div>
                        <div>
                            <div style="font-size:1.4rem;font-weight:700;color:var(--acento);"><?= (int)$stats['total_tarde'] ?></div>
                            <div style="font-size:0.7rem;color:var(--texto-suave);text-transform:uppercase;">Retrasos</div>
                        </div>
                        <div>
                            <div style="font-size:1.4rem;font-weight:700;color:#2a7a50;"><?= (int)$stats['total_teletrabajo'] ?></div>
                            <div style="font-size:0.7rem;color:var(--texto-suave);text-transform:uppercase;">Teletrabajo</div>
                        </div>
                        <div>
                            <div style="font-size:1.1rem;font-weight:700;color:var(--primario);"><?= minutosAHoras((int)$stats['total_extra']) ?></div>
                            <div style="font-size:0.7rem;color:var(--texto-suave);text-transform:uppercase;">H. Extra</div>
                        </div>
                    </div>
                </div>

                <!-- Formularios -->
                <div style="display:flex;flex-direction:column;gap:1.25rem;">

                    <!-- Actualizar datos personales -->
                    <div class="card">
                        <div class="card-titulo">✏️ Editar datos personales</div>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="accion" value="actualizar_perfil">

                            <div class="form-grupo">
                                <label for="nombre">Nombre completo *</label>
                                <input type="text" name="nombre" id="nombre" class="form-control"
                                       value="<?= e($usuario['nombre']) ?>" required>
                            </div>

                            <div class="form-grupo">
                                <label for="email_ro">Correo electrónico</label>
                                <input type="email" id="email_ro" class="form-control"
                                       value="<?= e($usuario['email']) ?>" readonly
                                       style="background:var(--fondo);color:var(--texto-suave);">
                                <span class="form-ayuda">El email solo puede ser modificado por RRHH.</span>
                            </div>

                            <div class="form-grupo">
                                <label for="foto">Foto de perfil</label>
                                <input type="file" name="foto" id="foto" class="form-control"
                                       accept="image/*" style="padding:0.4rem;">
                                <span class="form-ayuda">Formatos: JPG, PNG, WEBP. Máximo 2 MB.</span>
                            </div>

                            <button type="submit" class="btn btn-primario">💾 Guardar cambios</button>
                        </form>
                    </div>

                    <!-- Cambiar contraseña -->
                    <div class="card">
                        <div class="card-titulo">🔒 Cambiar contraseña</div>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="accion" value="cambiar_password">

                            <div class="form-grupo">
                                <label for="password_actual">Contraseña actual *</label>
                                <input type="password" name="password_actual" id="password_actual"
                                       class="form-control" autocomplete="current-password" required>
                            </div>
                            <div class="form-row">
                                <div class="form-grupo">
                                    <label for="password_nueva">Nueva contraseña *</label>
                                    <input type="password" name="password_nueva" id="password_nueva"
                                           class="form-control" autocomplete="new-password"
                                           minlength="8" required>
                                    <span class="form-ayuda">Mínimo 8 caracteres.</span>
                                </div>
                                <div class="form-grupo">
                                    <label for="password_confirmar">Confirmar nueva contraseña *</label>
                                    <input type="password" name="password_confirmar" id="password_confirmar"
                                           class="form-control" autocomplete="new-password" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primario">🔒 Cambiar contraseña</button>
                        </form>
                    </div>

                </div>
            </div>

        </main>
    </div>
</div>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>