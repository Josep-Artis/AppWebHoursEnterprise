-- ============================================================================
-- Vestigia CheckIn - Esquema de Base de Datos
-- Sistema de Control de Horarios Empresarial
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- ============================================================================
-- TABLA: departamentos
-- Departamentos de la empresa
-- ============================================================================
CREATE TABLE IF NOT EXISTS `departamentos` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos iniciales de departamentos
INSERT INTO `departamentos` (`id`, `nombre`) VALUES
(1, 'Dirección'),
(2, 'RRHH'),
(3, 'Contabilidad'),
(4, 'Desarrollo'),
(5, 'Diseño');

-- ============================================================================
-- TABLA: users
-- Usuarios del sistema (empleados)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `foto` VARCHAR(255) DEFAULT NULL,
  `rol` ENUM('superadmin','admin_rrhh','subadmin','user') NOT NULL DEFAULT 'user',
  `departamento_id` INT(11) UNSIGNED DEFAULT NULL,
  `tipo_jornada` ENUM('completa','media_manana','media_tarde') NOT NULL DEFAULT 'completa',
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `archivado` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `departamento_id` (`departamento_id`),
  KEY `activo` (`activo`),
  KEY `archivado` (`archivado`),
  CONSTRAINT `fk_users_departamento` FOREIGN KEY (`departamento_id`) REFERENCES `departamentos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuario administrador por defecto (password: admin123)
INSERT INTO `users` (`nombre`, `email`, `password`, `rol`, `departamento_id`, `tipo_jornada`, `activo`) VALUES
('Administrador', 'admin@vestigia.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin', 1, 'completa', 1);

-- ============================================================================
-- TABLA: proyectos
-- Proyectos de la empresa
-- ============================================================================
CREATE TABLE IF NOT EXISTS `proyectos` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(200) NOT NULL,
  `departamento_id` INT(11) UNSIGNED DEFAULT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `fecha_inicio` DATE DEFAULT NULL,
  `fecha_fin` DATE DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `departamento_id` (`departamento_id`),
  KEY `activo` (`activo`),
  CONSTRAINT `fk_proyectos_departamento` FOREIGN KEY (`departamento_id`) REFERENCES `departamentos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: proyecto_usuario
-- Relación muchos a muchos entre proyectos y usuarios
-- ============================================================================
CREATE TABLE IF NOT EXISTS `proyecto_usuario` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `proyecto_id` INT(11) UNSIGNED NOT NULL,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `proyecto_user` (`proyecto_id`, `user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_proyecto_usuario_proyecto` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_proyecto_usuario_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: fichajes
-- Registro de fichajes diarios
-- ============================================================================
CREATE TABLE IF NOT EXISTS `fichajes` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `proyecto_id` INT(11) UNSIGNED DEFAULT NULL,
  `hora_entrada` DATETIME DEFAULT NULL,
  `hora_salida` DATETIME DEFAULT NULL,
  `fecha` DATE NOT NULL,
  `tarde` TINYINT(1) NOT NULL DEFAULT 0,
  `minutos_retraso` INT(11) DEFAULT 0,
  `horas_extra` DECIMAL(5,2) DEFAULT 0.00,
  `teletrabajo` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `proyecto_id` (`proyecto_id`),
  KEY `fecha` (`fecha`),
  KEY `tarde` (`tarde`),
  CONSTRAINT `fk_fichajes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fichajes_proyecto` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: horarios
-- Horarios personalizados por usuario y trimestre
-- ============================================================================
CREATE TABLE IF NOT EXISTS `horarios` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `dia_semana` TINYINT(1) NOT NULL COMMENT '1=Lunes, 7=Domingo',
  `hora_inicio` TIME NOT NULL,
  `hora_fin` TIME NOT NULL,
  `trimestre` TINYINT(1) NOT NULL COMMENT '1-4',
  `año` YEAR NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `trimestre_año` (`trimestre`, `año`),
  CONSTRAINT `fk_horarios_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: vacaciones
-- Registro de vacaciones aprobadas
-- ============================================================================
CREATE TABLE IF NOT EXISTS `vacaciones` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `fecha_inicio` DATE NOT NULL,
  `fecha_fin` DATE NOT NULL,
  `estado` ENUM('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
  `aprobado_por` INT(11) UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `aprobado_por` (`aprobado_por`),
  KEY `fecha_inicio` (`fecha_inicio`),
  KEY `estado` (`estado`),
  CONSTRAINT `fk_vacaciones_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vacaciones_aprobador` FOREIGN KEY (`aprobado_por`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: solicitudes
-- Solicitudes de vacaciones, bajas, cambios de horario y teletrabajo
-- ============================================================================
CREATE TABLE IF NOT EXISTS `solicitudes` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `tipo` ENUM('vacaciones','baja','cambio_horario','teletrabajo') NOT NULL,
  `descripcion` TEXT NOT NULL,
  `fecha_inicio` DATE DEFAULT NULL,
  `fecha_fin` DATE DEFAULT NULL,
  `estado` ENUM('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
  `aprobado_por` INT(11) UNSIGNED DEFAULT NULL,
  `fecha` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `fecha_resolucion` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `tipo` (`tipo`),
  KEY `estado` (`estado`),
  KEY `aprobado_por` (`aprobado_por`),
  CONSTRAINT `fk_solicitudes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_solicitudes_aprobador` FOREIGN KEY (`aprobado_por`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: eventos
-- Eventos del calendario empresarial
-- ============================================================================
CREATE TABLE IF NOT EXISTS `eventos` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `titulo` VARCHAR(200) NOT NULL,
  `descripcion` TEXT DEFAULT NULL,
  `fecha` DATE NOT NULL,
  `departamento_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'NULL = evento general',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fecha` (`fecha`),
  KEY `departamento_id` (`departamento_id`),
  CONSTRAINT `fk_eventos_departamento` FOREIGN KEY (`departamento_id`) REFERENCES `departamentos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- FIN DEL ESQUEMA
-- ============================================================================

-- ============================================================================
-- MIGRACIÓN v1.2 — Refactorización jornadas laborales
-- Ejecutar sobre bases de datos existentes
-- ============================================================================

-- 1. Actualizar ENUM tipo_jornada en users
ALTER TABLE `users` MODIFY `tipo_jornada`
  ENUM('completa_manana','completa_tarde','parcial_manana','parcial_tarde','sin_asignar')
  NOT NULL DEFAULT 'sin_asignar';

-- 2. Nueva tabla para cambios temporales de horario
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
-- Ejecutar sobre bases de datos existentes (instalaciones nuevas ya lo tienen)
-- ============================================================================

-- Añade destinatario_id a solicitudes.
-- NULL  → flujo normal (empleado → responsable)
-- valor → propuesta inversa (responsable → empleado concreto)
ALTER TABLE `solicitudes`
  ADD COLUMN IF NOT EXISTS `destinatario_id` INT(11) UNSIGNED DEFAULT NULL
    COMMENT 'NULL=solicitud normal, valor=propuesta de responsable a empleado'
    AFTER `user_id`,
  ADD CONSTRAINT IF NOT EXISTS `fk_solicitudes_destinatario`
    FOREIGN KEY (`destinatario_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
