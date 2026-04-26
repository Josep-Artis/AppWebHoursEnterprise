# 📅 Jornadas Laborales — Vestigia CheckIn

Documento que recoge el diseño, motivación y funcionamiento del sistema de jornadas laborales.
Última actualización: abril 2026

---

## 🧩 Contexto — ¿Por qué se refactorizó?

El sistema original tenía varios problemas que afectaban tanto a la **corrección de los datos** como a la **experiencia del empleado**:

### ❌ Problemas del sistema anterior

**Jornada completa incorrecta**
La jornada completa estaba definida como 8:00-19:00 (11 horas), lo cual es incorrecto. El horario de oficina es de 8:00 a 19:00, pero eso es el rango total de la jornada de empresa — no la jornada de ningún empleado concreto. Una jornada completa real es de 8 horas.

**Sin distinción de turno**
El sistema no distinguía entre turno de mañana y turno de tarde. Esto impedía saber si un empleado hacía el mismo horario que otro, y hacía imposible gestionar cambios de turno de forma estructurada.

**Jornada intensiva de verano**
Existía una lógica automática que en junio-agosto cambiaba el horario de jornada completa a 8:00-16:00. Esta lógica se eliminó por dos motivos:
- Generaba complejidad innecesaria y casos difíciles de depurar
- El horario de verano, si se aplica, debería ser una decisión explícita de RRHH, no automática

**Empleados sin jornada podían fichar**
Al crear un empleado, se le asignaba `completa` como jornada por defecto. Esto significaba que un empleado recién creado podía fichar con un horario incorrecto antes de que RRHH le asignara su turno real.

---

## ✅ Solución — Nuevos tipos de jornada

Se definen 4 tipos de jornada reales más un estado especial:

| Tipo (`tipo_jornada`) | Turno | Entrada | Salida | Horas/día |
|-----------------------|-------|---------|--------|-----------|
| `completa_manana` | Mañana | 08:00 | 16:00 | 8h |
| `completa_tarde` | Tarde | 11:00 | 19:00 | 8h |
| `parcial_manana` | Mañana | 08:00 | 13:00 | 5h |
| `parcial_tarde` | Tarde | 14:00 | 19:00 | 5h |
| `sin_asignar` | — | — | — | — |

### El estado `sin_asignar`
Cuando se crea un empleado, su jornada queda como `sin_asignar` hasta que RRHH o un admin le asigne un turno. Un empleado en este estado **no puede fichar** — verá un mensaje informativo en la página de fichaje:

> *"Tu jornada laboral aún no ha sido configurada. Contacta con RRHH o tu responsable para que te asignen un turno."*

Esto garantiza que **ningún fichaje se registra con un horario incorrecto**.

---

## 🔄 Cambios temporales de turno

### Motivación
Un empleado puede necesitar cambiar de turno puntualmente (un día, una semana). Del mismo modo, un responsable puede proponer a un empleado que cambie de turno durante un período. En ambos casos el cambio debe:
- Aplicarse automáticamente durante las fechas indicadas
- Revertirse solo al terminar, sin intervención manual
- Quedar trazado (saber qué solicitud lo originó)

### Tabla `cambios_horario_temporales`

```sql
CREATE TABLE cambios_horario_temporales (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id               INT UNSIGNED NOT NULL,
  tipo_jornada_temporal ENUM('completa_manana','completa_tarde','parcial_manana','parcial_tarde') NOT NULL,
  fecha_inicio          DATE NOT NULL,
  fecha_fin             DATE NOT NULL,
  solicitud_id          INT UNSIGNED DEFAULT NULL,
  created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)      REFERENCES users(id)      ON DELETE CASCADE,
  FOREIGN KEY (solicitud_id) REFERENCES solicitudes(id) ON DELETE SET NULL
);
```

### Función `getJornadaEfectiva()`

Al fichar, el sistema no lee directamente `users.tipo_jornada`. En su lugar llama a `getJornadaEfectiva()`, que:

1. Busca en `cambios_horario_temporales` si hay un cambio activo **para hoy**
2. Si lo hay → usa ese turno temporal
3. Si no → usa `users.tipo_jornada` (el turno base del empleado)

Esto hace que los cambios temporales sean **transparentes** — el empleado ficha normalmente y el sistema aplica el turno correcto sin que nadie tenga que acordarse de revertir nada.

---

## 👥 Quién gestiona los turnos

| Acción | Quién puede hacerla |
|--------|---------------------|
| Asignar turno base a un empleado | Admin, Admin RRHH |
| Cambiar turno base permanentemente | Admin, Admin RRHH |
| Solicitar cambio puntual de turno | Empleado → via solicitud tipo `cambio_horario` |
| Proponer cambio puntual a un empleado | Responsable → via propuesta tipo `cambio_horario` |
| Aceptar/rechazar propuesta de cambio | El propio empleado |

### Cambio permanente vs temporal
- **Con fechas** en la solicitud/propuesta → cambio temporal (se guarda en `cambios_horario_temporales`)
- **Sin fechas** → cambio permanente (se actualiza `users.tipo_jornada` directamente)

---

## ⚙️ Constantes de configuración

Definidas en `config.php`:

```php
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

Si en el futuro cambian los horarios, solo hay que tocar este archivo.

---

## 📁 Archivos afectados

| Archivo | Cambio |
|---------|--------|
| `config.php` | Nuevas constantes, eliminadas las de verano |
| `database/schema.sql` | ALTER ENUM users + CREATE cambios_horario_temporales |
| `includes/funciones.php` | Reescrita `obtenerHorario()`, eliminada `esJornadaVerano()`, nueva `getJornadaEfectiva()` |
| `api/fichar.php` | Usa `getJornadaEfectiva()` + bloqueo `sin_asignar` |
| `api/informes.php` | Usa `getJornadaEfectiva()` al editar fichajes |
| `api/usuarios.php` | Default `sin_asignar`, nuevos valores en select |
| `pages/fichaje.php` | Mensaje y bloqueo visual si `sin_asignar` |
| `pages/horario.php` | Etiquetas nuevas, sin badge de verano |
| `pages/perfil.php` | Etiquetas nuevas |
| `pages/main.php` | Usa `getJornadaEfectiva()`, sin badge de verano |

---

*Este documento forma parte de la documentación técnica de Vestigia CheckIn.*
