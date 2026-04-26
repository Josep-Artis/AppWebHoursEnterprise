<?php
/**
 * horario.php
 * Página de visualización y gestión de horarios
 * Vestigia CheckIn
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requireLogin();

$userId  = userId();
$usuario = getUsuario($userId);
$pdo     = getDB();

$mesActual = (int)date('m');
$anioActual = (int)date('Y');
$trimestre = ceil($mesActual / 3);

// Horario base según tipo de jornada efectiva
$jornadaEfectiva = getJornadaEfectiva($userId);
$horarioBase = obtenerHorario($jornadaEfectiva);

// Horarios personalizados por día si existen
$stmt = $pdo->prepare(
    "SELECT * FROM horarios
     WHERE user_id = ? AND trimestre = ? AND año = ?
     ORDER BY dia_semana"
);
$stmt->execute([$userId, $trimestre, $anioActual]);
$horariosPersonalizados = $stmt->fetchAll();

// Indexar por día de la semana
$horariosPorDia = [];
foreach ($horariosPersonalizados as $h) {
    $horariosPorDia[$h['dia_semana']] = $h;
}

// Vacaciones aprobadas del año actual
$stmt = $pdo->prepare(
    "SELECT * FROM vacaciones
     WHERE user_id = ? AND YEAR(fecha_inicio) = ? AND estado = 'aprobado'
     ORDER BY fecha_inicio"
);
$stmt->execute([$userId, $anioActual]);
$vacaciones = $stmt->fetchAll();

// Calcular calendario del mes actual
$primerDia   = mktime(0, 0, 0, $mesActual, 1, $anioActual);
$diasEnMes   = (int)date('t', $primerDia);
$inicioDia   = (int)date('N', $primerDia); // 1=lunes

// Fichajes del mes para colorear el calendario
$stmt = $pdo->prepare(
    "SELECT fecha, tarde FROM fichajes
     WHERE user_id = ? AND DATE_FORMAT(fecha,'%Y-%m') = ?
     ORDER BY fecha"
);
$stmt->execute([$userId, date('Y-m')]);
$fichajesMes = [];
foreach ($stmt->fetchAll() as $f) {
    $fichajesMes[$f['fecha']] = $f['tarde'];
}

$diasSemana = ['Lun','Mar','Mié','Jue','Vie'];
$diasSemanaCompleto = ['Lunes','Martes','Miércoles','Jueves','Viernes'];
$nombresMes = ['','enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Horario — Vestigia CheckIn</title>
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
                <h2>📅 Mi Horario</h2>
                <p>Consulta tu horario laboral y visualiza tu calendario de asistencia.</p>
            </div>

            <!-- Horario semanal -->
            <div class="card" style="margin-bottom:1.25rem;">
                <div class="card-titulo">
                    🕐 Horario semanal — T<?= $trimestre ?> <?= $anioActual ?>
                </div>
                <div class="horario-semana">
                    <?php for ($dia = 1; $dia <= 5; $dia++): ?>
                    <?php
                        if (isset($horariosPorDia[$dia])) {
                            $entrada = $horariosPorDia[$dia]['hora_inicio'];
                            $salida  = $horariosPorDia[$dia]['hora_fin'];
                        } else {
                            $entrada = $horarioBase['entrada'];
                            $salida  = $horarioBase['salida'];
                        }
                        $esHoy = ((int)date('N') === $dia);
                    ?>
                    <div class="horario-dia-card" style="<?= $esHoy ? 'border-color:var(--primario);background:rgba(24,67,50,0.04);' : '' ?>">
                        <div class="horario-dia-nombre"><?= $diasSemanaCompleto[$dia-1] ?></div>
                        <div class="horario-entrada"><?= substr($entrada, 0, 5) ?></div>
                        <div class="horario-salida"><?= substr($salida, 0, 5) ?></div>
                        <?php if ($esHoy): ?>
                            <div class="badge badge-primario" style="margin-top:0.5rem;font-size:0.65rem;">HOY</div>
                        <?php endif; ?>
                    </div>
                    <?php endfor; ?>
                </div>

                <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--borde);display:flex;gap:1rem;flex-wrap:wrap;font-size:0.85rem;">
                    <span><strong>Tipo de jornada:</strong>
                        <?php
                        $tipos = [
                            'completa_manana' => 'Completa — Mañana (08:00-16:00)',
                            'completa_tarde'  => 'Completa — Tarde (11:00-19:00)',
                            'parcial_manana'  => 'Parcial — Mañana (08:00-13:00)',
                            'parcial_tarde'   => 'Parcial — Tarde (14:00-19:00)',
                            'sin_asignar'     => '⚠ Sin asignar',
                        ];
                        echo $tipos[$jornadaEfectiva] ?? e($jornadaEfectiva);
                        ?>
                    </span>
                    <span><strong>Departamento:</strong> <?= e($usuario['departamento_nombre'] ?? '—') ?></span>
                </div>
            </div>

            <!-- Calendario del mes -->
            <div class="card" style="margin-bottom:1.25rem;">
                <div class="card-titulo">📆 Calendario — <?= $nombresMes[$mesActual] ?> <?= $anioActual ?></div>

                <!-- Leyenda -->
                <div style="display:flex;gap:1rem;flex-wrap:wrap;font-size:0.78rem;margin-bottom:1rem;">
                    <span><span class="badge badge-verde" style="font-size:0.7rem;">●</span> Fichado a tiempo</span>
                    <span><span class="badge badge-amarillo" style="font-size:0.7rem;">●</span> Llegada tarde</span>
                    <span style="background:var(--primario);color:white;padding:0.15rem 0.5rem;border-radius:4px;font-size:0.7rem;">Hoy</span>
                    <span style="background:#f8d7da;color:#721c24;padding:0.15rem 0.5rem;border-radius:4px;font-size:0.7rem;">Sin fichar</span>
                </div>

                <div class="calendario-grid">
                    <!-- Nombres de días -->
                    <?php foreach (['L','M','X','J','V','S','D'] as $d): ?>
                        <div class="cal-dia-nombre"><?= $d ?></div>
                    <?php endforeach; ?>

                    <!-- Días vacíos iniciales -->
                    <?php for ($i = 1; $i < $inicioDia; $i++): ?>
                        <div class="cal-dia otro-mes"></div>
                    <?php endfor; ?>

                    <!-- Días del mes -->
                    <?php for ($dia = 1; $dia <= $diasEnMes; $dia++):
                        $fechaDia = sprintf('%04d-%02d-%02d', $anioActual, $mesActual, $dia);
                        $diaSemana = (int)date('N', strtotime($fechaDia));
                        $esHoy     = ($fechaDia === date('Y-m-d'));
                        $esFinde   = ($diaSemana >= 6);
                        $tieneFichaje = isset($fichajesMes[$fechaDia]);
                        $esTarde   = $tieneFichaje && $fichajesMes[$fechaDia];
                        $esPasado  = ($fechaDia < date('Y-m-d'));

                        $clases = 'cal-dia';
                        if ($esHoy) $clases .= ' hoy';
                        elseif ($esTarde) $clases .= ' tarde';
                        elseif ($tieneFichaje) $clases .= ' fichado';
                        elseif ($esPasado && !$esFinde) $clases .= ' ausente';
                        if ($esFinde) $clases .= ' festivo';
                    ?>
                        <div class="<?= $clases ?>" title="<?= $fechaDia ?>"><?= $dia ?></div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Vacaciones aprobadas -->
            <?php if ($vacaciones): ?>
            <div class="card">
                <div class="card-titulo">🌴 Vacaciones aprobadas <?= $anioActual ?></div>
                <div class="tabla-contenedor">
                    <table class="tabla">
                        <thead>
                            <tr>
                                <th>Desde</th>
                                <th>Hasta</th>
                                <th>Días</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vacaciones as $v):
                                $desde = strtotime($v['fecha_inicio']);
                                $hasta = strtotime($v['fecha_fin']);
                                $dias  = (int)(($hasta - $desde) / 86400) + 1;
                            ?>
                            <tr>
                                <td><?= date('d/m/Y', $desde) ?></td>
                                <td><?= date('d/m/Y', $hasta) ?></td>
                                <td><?= $dias ?> días</td>
                                <td><span class="badge badge-verde">✓ Aprobado</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Solicitar cambio de horario -->
            <div style="margin-top:1.25rem;text-align:right;">
                <a href="solicitudes.php?tipo=cambio_horario" class="btn btn-secundario">
                    📨 Solicitar cambio de horario
                </a>
                <a href="solicitudes.php?tipo=vacaciones" class="btn btn-primario" style="margin-left:0.5rem;">
                    🌴 Solicitar vacaciones
                </a>
            </div>

        </main>
    </div>
</div>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>