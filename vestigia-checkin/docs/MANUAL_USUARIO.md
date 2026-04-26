# 📖 Manual de Usuario — Vestigia CheckIn
### Sistema de Control de Horarios Empresarial

**Versión:** 1.2  
**Última actualización:** Abril 2026

---

## 📌 ¿Qué es Vestigia CheckIn?

Vestigia CheckIn es una aplicación web de control de presencia y gestión de jornada laboral diseñada para empresas. Permite registrar entradas y salidas, gestionar proyectos, solicitar vacaciones y cambios de horario, consultar informes y administrar el equipo desde cualquier navegador.

La aplicación está pensada para ser usada por toda la empresa: desde el empleado que ficha cada día hasta dirección, que tiene visión global de todos los equipos.

---

## 👥 Roles de usuario

Vestigia CheckIn tiene cuatro niveles de acceso. Cada usuario tiene asignado uno al darse de alta.

### 🔑 Superadmin — Dirección
Acceso total al sistema. Puede hacer todo lo que hacen los demás roles además de:
- Crear, editar, archivar y eliminar empleados
- Acceder a informes de toda la empresa
- Modificar fichajes de cualquier empleado
- Crear proyectos de cualquier departamento

### 👔 Admin RRHH — Recursos Humanos
- Gestión completa de empleados (alta, edición, archivo)
- Modificación de fichajes incorrectos
- Aprobación de solicitudes de vacaciones y bajas
- Acceso a informes globales de toda la empresa
- Envío de propuestas de cambio de horario a empleados

### 📊 Subadmin — Jefe de Departamento
- Gestión de los empleados de su departamento
- Creación y gestión de proyectos de su departamento
- Aprobación de solicitudes de su equipo
- Informes de su departamento
- Envío de propuestas de cambio de horario a empleados de su depto

### 👤 Empleado — Usuario estándar
- Registro de entrada y salida
- Consulta de su horario y calendario
- Consulta de sus propios informes
- Envío y seguimiento de solicitudes
- Respuesta a propuestas recibidas de su responsable

---

## 🖥️ Pantallas de la aplicación

### 🔐 Login
Pantalla de acceso con email y contraseña. Cada usuario tiene sus propias credenciales asignadas por RRHH o Dirección al darse de alta. Si es el primer acceso, se recomienda cambiar la contraseña desde el perfil.

---

### 🏠 Dashboard — Pantalla principal
Al entrar en la aplicación se muestra el dashboard con un resumen del día:
- Horario de hoy (entrada y salida según turno asignado)
- Estado del fichaje actual
- Próximos eventos del departamento o empresa
- Accesos rápidos a las secciones más usadas

---

### 🕐 Fichaje
La pantalla más usada del día a día. Desde aquí el empleado registra su entrada y salida.

**Para registrar entrada:**
1. Seleccionar el proyecto en el que se va a trabajar (obligatorio)
2. Marcar la casilla de teletrabajo si se trabaja desde casa (opcional)
3. Pulsar **Registrar entrada**

**Para registrar salida:**
1. Pulsar **Registrar salida**
2. El sistema calcula automáticamente las horas trabajadas y las posibles horas extra

**Consideraciones importantes:**
- Solo se puede tener un fichaje abierto por día
- Si se olvida fichar la salida, el sistema cierra automáticamente el fichaje a las 00:00
- Si el empleado llega tarde, se envía automáticamente un email de notificación al responsable y a RRHH
- Si no se ficha en todo el día, también se envía un aviso por email
- Si el empleado no tiene jornada asignada, no puede fichar — debe contactar con RRHH

**Historial reciente:** Debajo de los botones de fichaje se muestran los últimos 7 días con las horas de entrada, salida, proyecto y posibles retrasos o extras.

---

### 📅 Mi Horario
Muestra el horario semanal del empleado y un calendario mensual con el historial de asistencia.

**Información visible:**
- Horario de lunes a viernes con hora de entrada y salida
- Tipo de jornada asignada (ver apartado de jornadas)
- Calendario del mes con codificación por colores:
  - 🟢 Verde: fichado a tiempo
  - 🟡 Amarillo: llegada con retraso
  - 🔴 Rojo/sin color: día laboral sin fichar
- Vacaciones aprobadas del año en curso

Desde esta pantalla también se puede acceder directamente a solicitar un cambio de horario o pedir vacaciones.

---

### 📝 Solicitudes
Centro de gestión de peticiones entre empleados y responsables. Tiene varias pestañas según el rol.

**Pestaña: Mis solicitudes**
Listado de todas las solicitudes enviadas por el empleado con su estado actual (pendiente, aprobada, rechazada).

**Pestaña: Pendientes de aprobación** *(solo responsables)*
Solicitudes de los empleados esperando respuesta. El responsable puede aprobarlas o rechazarlas desde aquí.

**Pestaña: Recibidas**
Propuestas que un responsable ha enviado directamente al empleado. El empleado puede aceptarlas o rechazarlas. Aparece un badge con el número de propuestas pendientes de respuesta.

**Pestaña: Enviadas** *(solo responsables)*
Propuestas que el responsable ha mandado a empleados, con el estado de respuesta de cada una.

**Pestaña: Nueva solicitud**
Formulario para que el empleado envíe una petición a su responsable. Tipos disponibles:
- **Vacaciones** — indicar fecha de inicio y fin
- **Baja** — indicar fechas y descripción
- **Cambio de horario** — indicar el turno deseado y las fechas (si son días concretos) o sin fechas si el cambio es permanente
- **Teletrabajo** — indicar el período solicitado

**Pestaña: Nueva propuesta** *(solo responsables)*
Formulario para que un responsable envíe una propuesta directamente a un empleado. Mismos tipos disponibles. El empleado la recibirá en su pestaña "Recibidas" y podrá aceptarla o rechazarla.

---

### 📊 Informes
Permite consultar y exportar los registros de fichajes con distintos filtros.

**Filtros disponibles:**
- Hoy / Esta semana / Este mes / Este año / Semana anterior / Rango personalizado
- Por empleado (solo responsables)

**Información mostrada:**
- Listado de fichajes con hora de entrada, salida, proyecto, retraso y horas extra
- Resumen estadístico: total de días fichados, días con retraso, días en teletrabajo, horas extra acumuladas

**Exportación:**
- **PDF** — informe formateado para imprimir o archivar
- **Excel** — datos en hoja de cálculo para análisis

**Visibilidad por rol:**
- Empleado: solo sus propios datos
- Subadmin: los datos de su departamento
- Superadmin / Admin RRHH: datos de toda la empresa, con filtro por empleado

---

### 👤 Perfil
Datos personales del usuario y configuración de la cuenta.

**Información visible:**
- Nombre, email, foto de perfil
- Departamento y tipo de jornada asignada
- Fecha del primer fichaje registrado
- Estadísticas generales de actividad

**Acciones disponibles:**
- Cambiar foto de perfil
- Cambiar contraseña

---

### 🗃️ Gestión de Empleados *(solo Superadmin y Admin RRHH)*
Accessible desde el menú lateral. Permite:
- Ver el listado completo de empleados con su departamento, rol, jornada y estado
- Crear nuevos empleados (se les asigna jornada `sin asignar` por defecto)
- Editar datos de un empleado: nombre, email, rol, departamento, tipo de jornada
- Archivar empleados (en caso de baja o despido — los datos se conservan por respaldo legal)
- Restaurar empleados archivados
- Eliminar empleados permanentemente *(solo Superadmin)*
- Filtrar por departamento, nombre/email y estado (activos/archivados)

---

### 📁 Proyectos *(Superadmin, Admin RRHH y Subadmin)*
- Crear proyectos con nombre, departamento, fechas y descripción
- Asignar empleados a proyectos (un empleado puede estar en varios a la vez)
- Los proyectos pueden ser interdepartamentales
- Al fichar, el empleado selecciona el proyecto activo del día

---

## ⏰ Jornadas laborales

Cada empleado tiene asignado un tipo de jornada que determina su horario de entrada y salida. La asignación la hace RRHH o Dirección.

| Tipo | Turno | Entrada | Salida | Horas/día |
|------|-------|---------|--------|-----------|
| Jornada completa mañana | Mañana | 08:00 | 16:00 | 8h |
| Jornada completa tarde | Tarde | 11:00 | 19:00 | 8h |
| Jornada parcial mañana | Mañana | 08:00 | 13:00 | 5h |
| Jornada parcial tarde | Tarde | 14:00 | 19:00 | 5h |
| Sin asignar | — | — | — | — |

> ⚠️ Un empleado con jornada **sin asignar** no puede fichar hasta que RRHH le asigne un turno.

### Cambios de jornada
Los cambios de turno pueden ser **permanentes** o **temporales**:

- **Permanentes:** RRHH o Dirección cambia directamente el turno base del empleado desde la gestión de usuarios, o aprueba una solicitud de cambio sin fechas.
- **Temporales:** Si la solicitud o propuesta de cambio incluye fechas de inicio y fin, el sistema aplica el nuevo turno solo durante ese período y lo revierte automáticamente al terminar.

El flujo puede ser en ambas direcciones:
- **Empleado → Responsable:** el empleado solicita un cambio de turno mediante una solicitud de tipo "Cambio de horario"
- **Responsable → Empleado:** el responsable propone un cambio de turno al empleado, que puede aceptarlo o rechazarlo

---

## 🔔 Notificaciones automáticas

El sistema envía emails automáticamente en los siguientes casos:

| Evento | Destinatario |
|--------|-------------|
| Empleado llega tarde | Subadmin del departamento + Admin RRHH |
| Empleado no ficha en todo el día | Subadmin del departamento + Admin RRHH |

Además, hay notificaciones internas en la app:
- Badge en el menú de solicitudes cuando hay solicitudes pendientes de aprobar (responsables)
- Badge en la pestaña "Recibidas" cuando hay propuestas pendientes de responder (empleados)

---

## 🔒 Seguridad

- Las contraseñas se almacenan cifradas (bcrypt)
- Todos los formularios están protegidos contra ataques CSRF
- Las consultas a la base de datos usan prepared statements para prevenir SQL injection
- La salida de datos está escapada para prevenir XSS
- Las sesiones tienen un tiempo de expiración de 8 horas

---

## ❓ Preguntas frecuentes

**¿Qué hago si olvidé fichar la salida?**
El sistema cierra el fichaje automáticamente a las 00:00. Si necesitas corregir la hora de salida, contacta con RRHH o Dirección, que pueden editar el fichaje.

**¿Puedo fichar desde casa?**
Sí. Al registrar la entrada, marca la casilla de teletrabajo. Esto quedará reflejado en los informes.

**¿Cómo solicito vacaciones?**
Ve a Solicitudes → Nueva solicitud → Vacaciones. Indica las fechas y una descripción. Tu responsable o RRHH recibirá la petición y la aprobará o rechazará.

**¿Qué pasa si mi responsable me propone un cambio de horario?**
Recibirás la propuesta en la pestaña "Recibidas" de Solicitudes. Puedes aceptarla o rechazarla. Si la aceptas y tiene fechas, el cambio se aplica automáticamente durante ese período.

**¿Cómo sé si llego tarde?**
En la pantalla de fichaje puedes ver tu horario de entrada. Si fichas después de esa hora, el sistema lo registra como retraso y avisa a tu responsable.

**No puedo fichar, ¿qué hago?**
Puede que tu jornada no esté asignada todavía. Contacta con RRHH para que te asignen un turno.

---

## 📞 Soporte

Para cualquier incidencia técnica o duda sobre el funcionamiento de la aplicación, contacta con el departamento de RRHH o con el administrador del sistema.

---

*Vestigia CheckIn © 2026 — Todos los derechos reservados.*
