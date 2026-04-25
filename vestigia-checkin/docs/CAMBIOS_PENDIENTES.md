# 📋 Cambios Pendientes — Vestigia CheckIn

Documento de planificación de mejoras y correcciones pendientes de implementar.
Última actualización: abril 2026

---

## ✅ Cambios ya aplicados (rama `testing`)

### Fix — Horas extra desmedidas
**Problema:** `calcularHorasExtra()` comparaba solo la hora de salida real contra la esperada, ignorando a qué hora había entrado el empleado. Un empleado que entraba tarde y salía a su hora generaba extras falsos.

**Solución:** La función ahora calcula el tiempo real trabajado (entrada→salida) y lo compara contra la jornada esperada completa.

**Archivos modificados:**
- `includes/funciones.php` — nueva firma de `calcularHorasExtra()`
- `api/fichar.php` — usa la nueva firma al registrar salida
- `api/informes.php` — idem al editar fichaje

---

### Fix — Bug SQL en `notificarRetraso()`
**Problema:** Fallo de precedencia de operadores en el `WHERE`. El filtro `activo = 1 AND archivado = 0` solo aplicaba al rol `admin_rrhh`, no al `subadmin`, por lo que subadmins inactivos o archivados podían recibir emails de retraso.

**Solución:** Añadidos paréntesis correctos para que el filtro aplique a ambos roles.

**Archivos modificados:**
- `includes/funciones.php` — fix en la query de `notificarRetraso()`

---

### Feature — Propuestas de responsable a empleado
**Descripción:** Hasta ahora el flujo de solicitudes era únicamente empleado → responsable. Se ha añadido el flujo inverso: un responsable (subadmin, admin_rrhh, superadmin) puede enviar propuestas a un empleado concreto, y el empleado las acepta o rechaza.

**Cambios en BD:**
```sql
ALTER TABLE solicitudes
  ADD COLUMN destinatario_id INT(11) UNSIGNED DEFAULT NULL AFTER user_id,
  ADD CONSTRAINT fk_solicitudes_destinatario
    FOREIGN KEY (destinatario_id) REFERENCES users(id) ON DELETE SET NULL;
```
- `destinatario_id = NULL` → solicitud normal (empleado → responsable)
- `destinatario_id = valor` → propuesta inversa (responsable → empleado)

**Nuevas funciones en `includes/funciones.php`:**
- `crearPropuesta()` — crea una propuesta con destinatario
- `getPropuestasRecibidas()` — propuestas recibidas por un usuario
- `getPropuestasEnviadas()` — propuestas enviadas por un responsable
- `contarPropuestasPendientes()` — badge de notificaciones

**Nuevas tabs en `pages/solicitudes.php`:**
- **Recibidas** — visible para todos, muestra propuestas recibidas con botones Aceptar/Rechazar
- **Enviadas** — solo responsables, muestra el estado de sus propuestas
- **Nueva propuesta** — solo responsables, formulario para enviar propuesta a empleado

**Archivos modificados:**
- `database/schema.sql`
- `includes/funciones.php`
- `pages/solicitudes.php`

---

## 🔄 Cambios pendientes de implementar

### Feature — Refactorización de jornadas laborales

#### Contexto y decisiones tomadas
El sistema actual tiene los siguientes problemas:
- Jornada completa definida como 8:00-19:00 (11h) — **incorrecto**
- No distingue entre turno de mañana y turno de tarde
- Existe lógica de "jornada intensiva de verano" que **se elimina** para simplificar
- Al crear un empleado se le asigna jornada por defecto — **incorrecto**, debe quedar `sin_asignar` hasta que RRHH lo gestione
- Un empleado `sin_asignar` **no puede fichar** — debe ver un mensaje informativo

Los cambios de turno pueden ser:
- **Permanentes** — RRHH/admin cambia `users.tipo_jornada` directamente
- **Temporales** — via solicitud/propuesta de `cambio_horario` con `fecha_inicio` y `fecha_fin`, se guarda en `cambios_horario_temporales` y se restaura automáticamente al terminar

El turno lo asigna siempre **admin o admin_rrhh**. El empleado puede solicitar un cambio puntual via solicitudes (tipo `cambio_horario`), y el responsable puede proponer un cambio al empleado via propuestas (sistema ya implementado). En ambos casos, si la solicitud/propuesta tiene fechas → cambio temporal. Si no tiene fechas → cambio permanente.

---

#### Nuevos tipos de jornada

| Tipo (`tipo_jornada`) | Turno | Entrada | Salida | Horas/día |
|-----------------------|-------|---------|--------|-----------|
| `completa_manana` | Mañana | 08:00 | 16:00 | 8h |
| `completa_tarde` | Tarde | 11:00 | 19:00 | 8h |
| `parcial_manana` | Mañana | 08:00 | 13:00 | 5h |
| `parcial_tarde` | Tarde | 14:00 | 19:00 | 5h |
| `sin_asignar` | — | — | — | — |

> ⚠️ `sin_asignar` = el empleado **no puede fichar**. Debe mostrar: *"Tu jornada laboral aún no ha sido asignada. Contacta con RRHH."*

---

#### Paso 1 — `database/schema.sql` + ejecutar en MySQL

Añadir al final del schema y ejecutar manualmente en la BD:

```sql
-- Migración v1.2 — Refactorización jornadas laborales

-- 1. Actualizar ENUM tipo_jornada en users
ALTER TABLE `users` MODIFY `tipo_jornada`
  ENUM('completa_manana','completa_tarde','parcial_manana','parcial_tarde','sin_asignar')
  NOT NULL DEFAULT 'sin_asignar';

-- 2. Nueva tabla para cambios temporales de horario
--    Se crea un registro aquí cuando se aprueba/acepta una solicitud
--    o propuesta de tipo cambio_horario con fechas definidas.
--    Al fichar, se consulta esta tabla primero.
CREATE TABLE IF NOT EXISTS `cambios_horario_temporales` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`               INT UNSIGNED NOT NULL,
  `tipo_jornada_temporal` ENUM('completa_manana','completa_tarde','parcial_manana','parcial_tarde') NOT NULL,
  `fecha_inicio`          DATE NOT NULL,
  `fecha_fin`             DATE NOT NULL,
  `solicitud_id`          INT UNSIGNED DEFAULT NULL COMMENT 'Solicitud o propuesta que originó el cambio',
  `created_at`            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_fecha` (`user_id`, `fecha_inicio`, `fecha_fin`),
  CONSTRAINT `fk_cht_user`      FOREIGN KEY (`user_id`)      REFERENCES `users`(`id`)      ON DELETE CASCADE,
  CONSTRAINT `fk_cht_solicitud` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

#### Paso 2 — `config.php`

**Eliminar** el bloque de horarios laborales actual (líneas ~34-45):
```php
// ELIMINAR todo esto:
define('HORA_ENTRADA_NORMAL',    '08:00');
define('HORA_SALIDA_NORMAL',     '19:00');
define('HORA_ENTRADA_VERANO',    '08:00');
define('HORA_SALIDA_VERANO',     '16:00');
define('HORA_ENTRADA_MANANA',    '08:00');
define('HORA_SALIDA_MANANA',     '13:00');
define('HORA_ENTRADA_TARDE',     '13:00');
define('HORA_SALIDA_TARDE',      '19:00');
define('INICIO_JORNADA_VERANO',  '06-01');
define('FIN_JORNADA_VERANO',     '09-01');
```

**Reemplazar por:**
```php
// ── Horarios laborales ───────────────────────────────────────────────────────
define('HORA_ENTRADA_COMPLETA_MANANA', '08:00');
define('HORA_SALIDA_COMPLETA_MANANA',  '16:00');
define('HORA_ENTRADA_COMPLETA_TARDE',  '11:00');
define('HORA_SALIDA_COMPLETA_TARDE',   '19:00');
define('HORA_ENTRADA_PARCIAL_MANANA',  '08:00');
define('HORA_SALIDA_PARCIAL_MANANA',   '13:00');
define('HORA_ENTRADA_PARCIAL_TARDE',   '14:00');
define('HORA_SALIDA_PARCIAL_TARDE',    '19:00');
define('HORAS_SEMANALES_MAX',          40);
```

---

#### Paso 3 — `includes/funciones.php`

**A) Reescribir `obtenerHorario()`** — eliminar toda la lógica de verano:

```php
/**
 * Devuelve el horario de entrada/salida según tipo de jornada.
 * Ya no hay lógica de verano — se eliminó.
 *
 * @param string $tipoJornada completa_manana|completa_tarde|parcial_manana|parcial_tarde
 * @return array ['entrada' => 'HH:MM', 'salida' => 'HH:MM']
 */
function obtenerHorario(string $tipoJornada): array {
    switch ($tipoJornada) {
        case 'completa_manana':
            return ['entrada' => HORA_ENTRADA_COMPLETA_MANANA, 'salida' => HORA_SALIDA_COMPLETA_MANANA];
        case 'completa_tarde':
            return ['entrada' => HORA_ENTRADA_COMPLETA_TARDE,  'salida' => HORA_SALIDA_COMPLETA_TARDE];
        case 'parcial_manana':
            return ['entrada' => HORA_ENTRADA_PARCIAL_MANANA,  'salida' => HORA_SALIDA_PARCIAL_MANANA];
        case 'parcial_tarde':
            return ['entrada' => HORA_ENTRADA_PARCIAL_TARDE,   'salida' => HORA_SALIDA_PARCIAL_TARDE];
        default:
            // sin_asignar u otro valor desconocido — no debería llegar aquí
            return ['entrada' => '00:00', 'salida' => '00:00'];
    }
}
```

**B) Eliminar `esJornadaVerano()`** — borrar la función entera.

**C) Añadir `getJornadaEfectiva()`** — nueva función que consulta cambios temporales:

```php
/**
 * Devuelve el tipo de jornada efectiva para un usuario en una fecha dada.
 * Primero comprueba si hay un cambio temporal activo en cambios_horario_temporales.
 * Si no hay ninguno, devuelve el tipo_jornada base del usuario.
 *
 * @param int    $userId ID del usuario
 * @param string $fecha  YYYY-MM-DD (por defecto hoy)
 * @return string  Tipo de jornada: completa_manana|completa_tarde|parcial_manana|parcial_tarde|sin_asignar
 */
function getJornadaEfectiva(int $userId, string $fecha = ''): string {
    if (!$fecha) $fecha = date('Y-m-d');

    $pdo  = getDB();

    // Buscar cambio temporal activo para esta fecha
    $stmt = $pdo->prepare(
        "SELECT tipo_jornada_temporal FROM cambios_horario_temporales
         WHERE user_id = ? AND fecha_inicio <= ? AND fecha_fin >= ?
         ORDER BY created_at DESC LIMIT 1"
    );
    $stmt->execute([$userId, $fecha, $fecha]);
    $cambio = $stmt->fetchColumn();

    if ($cambio) {
        return $cambio;
    }

    // Sin cambio temporal → devolver jornada base del usuario
    $stmt2 = $pdo->prepare("SELECT tipo_jornada FROM users WHERE id = ? LIMIT 1");
    $stmt2->execute([$userId]);
    return $stmt2->fetchColumn() ?: 'sin_asignar';
}
```

---

#### Paso 4 — `api/fichar.php`

En la acción `entrada`, reemplazar la lectura de `$usuario['tipo_jornada']` por `getJornadaEfectiva()` y añadir el bloqueo:

```php
// ANTES (eliminar):
$horario = obtenerHorario($usuario['tipo_jornada'] ?? 'completa');

// DESPUÉS (sustituir por):
$jornadaEfectiva = getJornadaEfectiva($userId);
if ($jornadaEfectiva === 'sin_asignar') {
    echo json_encode(['error' => 'Tu jornada laboral no está asignada. Contacta con RRHH.']);
    exit;
}
$horario = obtenerHorario($jornadaEfectiva);
```

Hacer lo mismo en la acción `salida` (también usa `$usuario['tipo_jornada']`).

---

#### Paso 5 — `pages/solicitudes.php` — lógica al aceptar `cambio_horario`

Dentro del bloque que procesa `accion = aprobar` (solicitud normal) y `accion = responder_propuesta` con respuesta `aceptar`, añadir después del UPDATE:

```php
// Si es cambio_horario aprobado/aceptado → aplicar cambio de jornada
if ($tipo === 'cambio_horario') {
    // Necesita contener el nuevo tipo de jornada en la descripción o en un campo extra
    // Leer la solicitud para obtener fechas y tipo nuevo
    $solData = $pdo->prepare("SELECT * FROM solicitudes WHERE id = ?");
    $solData->execute([$solicitudId]);
    $sol = $solData->fetch();

    if ($sol['fecha_inicio'] && $sol['fecha_fin']) {
        // Cambio TEMPORAL → insertar en cambios_horario_temporales
        // El tipo de jornada nuevo debe venir en la descripción o en un campo dedicado
        // Por ahora se parsea de la descripción — en el futuro añadir campo tipo_jornada_nueva
        $pdo->prepare(
            "INSERT INTO cambios_horario_temporales (user_id, tipo_jornada_temporal, fecha_inicio, fecha_fin, solicitud_id)
             VALUES (?, ?, ?, ?, ?)"
        )->execute([$sol['user_id'], $tipoJornadaNueva, $sol['fecha_inicio'], $sol['fecha_fin'], $solicitudId]);
    } else {
        // Cambio PERMANENTE → actualizar users.tipo_jornada
        $pdo->prepare("UPDATE users SET tipo_jornada = ? WHERE id = ?")
            ->execute([$tipoJornadaNueva, $sol['user_id']]);
    }
}
```

> ⚠️ **Pendiente de decisión:** el formulario de nueva solicitud/propuesta de tipo `cambio_horario` necesita un campo adicional `tipo_jornada_nueva` (select con los 4 tipos) para que el sistema sepa a qué turno cambiar. Añadir este campo en el formulario de `pages/solicitudes.php` cuando el tipo seleccionado sea `cambio_horario`.

---

#### Paso 6 — `api/usuarios.php`

**Al crear usuario**, cambiar el valor por defecto de `tipo_jornada`:
```php
// El campo tipo_jornada en el INSERT debe ser 'sin_asignar' por defecto
// Verificar que el formulario de creación incluye el select con los nuevos valores
```

**El select de tipo_jornada** en el formulario HTML de crear/editar empleado debe tener:
```html
<select name="tipo_jornada">
    <option value="sin_asignar">— Sin asignar —</option>
    <option value="completa_manana">Jornada completa — Mañana (08:00-16:00)</option>
    <option value="completa_tarde">Jornada completa — Tarde (11:00-19:00)</option>
    <option value="parcial_manana">Jornada parcial — Mañana (08:00-13:00)</option>
    <option value="parcial_tarde">Jornada parcial — Tarde (14:00-19:00)</option>
</select>
```

---

#### Paso 7 — `pages/fichaje.php`

**Eliminar** la línea que muestra "Jornada intensiva":
```php
// ELIMINAR esta línea:
<?= esJornadaVerano() ? '(Jornada intensiva)' : '' ?>
```

**Añadir** bloqueo visual si el empleado no tiene jornada asignada. Justo después de cargar `$usuario`, añadir:
```php
$jornadaEfectiva = getJornadaEfectiva($userId);
$sinJornada = ($jornadaEfectiva === 'sin_asignar');
```

Y en el HTML, antes de los botones de entrada/salida:
```php
<?php if ($sinJornada): ?>
    <div class="alerta alerta-error">
        <span>⚠</span>
        <div>
            <strong>Jornada no asignada.</strong>
            Tu horario laboral aún no ha sido configurado.
            Contacta con RRHH o tu responsable para que te asignen un turno.
        </div>
    </div>
<?php endif; ?>
```

Y deshabilitar los botones de entrada/salida si `$sinJornada`:
```php
<button id="btn-entrada" <?= ($fichajeAbierto || $sinJornada) ? 'disabled' : '' ?>>
    🕐 Registrar entrada
</button>
```

---

#### Orden de ejecución

| Paso | Archivo | Acción |
|------|---------|--------|
| 1 | `database/schema.sql` + MySQL | ALTER ENUM users + CREATE cambios_horario_temporales |
| 2 | `config.php` | Nuevas constantes, eliminar verano |
| 3 | `includes/funciones.php` | Reescribir `obtenerHorario()`, eliminar `esJornadaVerano()`, añadir `getJornadaEfectiva()` |
| 4 | `api/fichar.php` | Usar `getJornadaEfectiva()` en entrada y salida + bloqueo `sin_asignar` |
| 5 | `pages/solicitudes.php` | Lógica al aprobar/aceptar `cambio_horario` + campo `tipo_jornada_nueva` en formulario |
| 6 | `api/usuarios.php` | Default `sin_asignar` + nuevos valores en select |
| 7 | `pages/fichaje.php` | Quitar jornada intensiva + mensaje + botones deshabilitados si `sin_asignar` |

---

## 💡 Ideas a valorar en el futuro

- **Notificación interna** al empleado cuando se le asigna o cambia la jornada
- **Historial de jornadas** por empleado para trazabilidad
- **Vista de calendario** con los cambios temporales activos por departamento
- **Campo `tipo_jornada_nueva`** en solicitudes para especificar el turno de destino en cambios de horario (actualmente se parsea de la descripción — mejor tenerlo como campo propio)

---

*Este documento se actualiza conforme se van planificando e implementando cambios.*
