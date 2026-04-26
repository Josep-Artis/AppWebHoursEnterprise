<?php
/**
 * api/fichar.php
 * API REST de fichaje (entrada/salida/estado)
 * Vestigia CheckIn
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

// Solo acepta peticiones autenticadas
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$userId  = userId();
$usuario = getUsuario($userId);
$pdo     = getDB();

// ── Determinar acción ─────────────────────────────────────────────────────────
$metodo = $_SERVER['REQUEST_METHOD'];
$accion = $_GET['accion'] ?? '';

// GET: consultar estado actual
if ($metodo === 'GET' && $accion === 'estado') {
    $fichaje = getFichajeAbierto($userId);
    if ($fichaje) {
        echo json_encode([
            'activo'           => true,
            'hora_entrada'     => $fichaje['hora_entrada'],
            'tarde'            => (bool)$fichaje['tarde'],
            'minutos_retraso'  => (int)$fichaje['minutos_retraso'],
            'teletrabajo'      => (bool)$fichaje['teletrabajo'],
            'proyecto_id'      => $fichaje['proyecto_id'],
        ]);
    } else {
        echo json_encode(['activo' => false]);
    }
    exit;
}

// POST: registrar entrada o salida
if ($metodo === 'POST') {
    $datos = json_decode(file_get_contents('php://input'), true);
    if (!$datos) $datos = $_POST;

    $accion = $datos['accion'] ?? '';

    // ── Registrar ENTRADA ─────────────────────────────────────────────────────
    if ($accion === 'entrada') {
        $proyectoId  = (int)($datos['proyecto_id'] ?? 0);
        $teletrabajo = (int)($datos['teletrabajo'] ?? 0);

        // Validar que no hay ya un fichaje abierto hoy
        if (getFichajeAbierto($userId)) {
            echo json_encode(['error' => 'Ya tienes un fichaje abierto hoy.']);
            exit;
        }

        // Validar proyecto
        if (!$proyectoId) {
            echo json_encode(['error' => 'Debes seleccionar un proyecto.']);
            exit;
        }

        // Verificar que el proyecto pertenece al usuario
        $stmtP = $pdo->prepare(
            "SELECT COUNT(*) FROM proyecto_usuario WHERE proyecto_id = ? AND user_id = ?"
        );
        $stmtP->execute([$proyectoId, $userId]);
        if (!(int)$stmtP->fetchColumn()) {
            echo json_encode(['error' => 'No tienes acceso a ese proyecto.']);
            exit;
        }

        // Validar jornada asignada
        $jornadaEfectiva = getJornadaEfectiva($userId);
        if ($jornadaEfectiva === 'sin_asignar') {
            echo json_encode(['error' => 'Tu jornada laboral no está asignada. Contacta con RRHH.']);
            exit;
        }

        // Calcular retraso
        $horaAhora   = date('H:i:s');
        $horario     = obtenerHorario($jornadaEfectiva);
        $minRetraso  = calcularRetraso($horaAhora, $horario['entrada']);
        $esTarde     = ($minRetraso > 0);

        // Insertar fichaje
        $stmt = $pdo->prepare(
            "INSERT INTO fichajes (user_id, proyecto_id, hora_entrada, fecha, tarde, minutos_retraso, teletrabajo, horas_extra)
             VALUES (?, ?, ?, CURDATE(), ?, ?, ?, 0)"
        );
        $ok = $stmt->execute([$userId, $proyectoId, $horaAhora, $esTarde ? 1 : 0, $minRetraso, $teletrabajo]);

        if (!$ok) {
            echo json_encode(['error' => 'Error al registrar la entrada.']);
            exit;
        }

        // Notificar retraso por email
        if ($esTarde) {
            notificarRetraso($userId, $minRetraso);
        }

        echo json_encode([
            'ok'              => true,
            'hora_entrada'    => $horaAhora,
            'tarde'           => $esTarde,
            'minutos_retraso' => $minRetraso,
            'teletrabajo'     => (bool)$teletrabajo,
        ]);
        exit;
    }

    // ── Registrar SALIDA ──────────────────────────────────────────────────────
    if ($accion === 'salida') {
        $fichaje = getFichajeAbierto($userId);

        if (!$fichaje) {
            echo json_encode(['error' => 'No tienes ningún fichaje abierto hoy.']);
            exit;
        }

        $horaAhora = date('H:i:s');
        $horario   = obtenerHorario(getJornadaEfectiva($userId));
        $minExtra  = calcularHorasExtra($fichaje['hora_entrada'], $horaAhora, $horario['entrada'], $horario['salida']);

        $stmt = $pdo->prepare(
            "UPDATE fichajes SET hora_salida = ?, horas_extra = ? WHERE id = ?"
        );
        $ok = $stmt->execute([$horaAhora, $minExtra, $fichaje['id']]);

        if (!$ok) {
            echo json_encode(['error' => 'Error al registrar la salida.']);
            exit;
        }

        echo json_encode([
            'ok'          => true,
            'hora_salida' => $horaAhora,
            'horas_extra' => $minExtra,
        ]);
        exit;
    }
}

echo json_encode(['error' => 'Acción no válida.']);