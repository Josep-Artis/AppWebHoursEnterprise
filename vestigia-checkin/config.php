<?php
/**
 * config.php
 * Configuración global de la aplicación Vestigia CheckIn
 */

// Zona horaria de España
date_default_timezone_set('Europe/Madrid');

// ── Configuración de base de datos ──────────────────────────────────────────
define('DB_HOST',     'localhost');
define('DB_NAME',     'vestigia_checkin');
define('DB_USER',     'vestigia');          // Cambiar en producción
define('DB_PASS',     'vestigia123');             // Cambiar en producción
define('DB_CHARSET',  'utf8mb4');

// ── Configuración de la aplicación ──────────────────────────────────────────
define('APP_NAME',    'Vestigia CheckIn');
define('APP_URL',     'http://localhost/AppWebHoursEnterprise/vestigia-checkin'); // Cambiar en producción
define('APP_VERSION', '1.0.0');

// ── Configuración de sesión ─────────────────────────────────────────────────
define('SESSION_NAME',    'vestigia_session');
define('SESSION_TIMEOUT', 28800); // 8 horas en segundos

// ── Configuración de correo (PHPMailer) ─────────────────────────────────────
define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USERNAME', 'noreply@vestigia.com'); // Cambiar en producción
define('MAIL_PASSWORD', 'password_aqui');         // Cambiar en producción
define('MAIL_FROM',     'noreply@vestigia.com');
define('MAIL_FROM_NAME','Vestigia CheckIn');

// ── Horarios laborales ───────────────────────────────────────────────────────
define('HORA_ENTRADA_COMPLETA_MANANA', '08:00'); // Jornada completa turno mañana
define('HORA_SALIDA_COMPLETA_MANANA',  '16:00');
define('HORA_ENTRADA_COMPLETA_TARDE',  '11:00'); // Jornada completa turno tarde
define('HORA_SALIDA_COMPLETA_TARDE',   '19:00');
define('HORA_ENTRADA_PARCIAL_MANANA',  '08:00'); // Jornada parcial turno mañana
define('HORA_SALIDA_PARCIAL_MANANA',   '13:00');
define('HORA_ENTRADA_PARCIAL_TARDE',   '14:00'); // Jornada parcial turno tarde
define('HORA_SALIDA_PARCIAL_TARDE',    '19:00');
define('HORAS_SEMANALES_MAX',          40);      // Máximo de horas semanales antes de extras

// ── Rutas de archivos ────────────────────────────────────────────────────────
define('ROOT_PATH',   __DIR__);
define('UPLOAD_PATH', ROOT_PATH . '/assets/img/uploads/');
define('UPLOAD_URL',  APP_URL . '/assets/img/uploads/');

// ── Niveles de rol ───────────────────────────────────────────────────────────
define('ROL_SUPERADMIN', 'superadmin');
define('ROL_ADMIN_RRHH', 'admin_rrhh');
define('ROL_SUBADMIN',   'subadmin');
define('ROL_USER',       'user');

// ── Inicio de sesión seguro ──────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path'     => '/',
        'secure'   => false, // Cambiar a true en producción con HTTPS
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}
