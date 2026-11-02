<?php
// ==================== CONFIGURACIÓN ====================
define('DB_HOST', 'localhost');
define('DB_NAME', 'recibos_db');
define('DB_USER', 'cambia_por_tu_usuario');
define('DB_PASS', 'cambia_por_tu_password');
define('APP_URL', 'https://tu-dominio.com/api');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('JWT_SECRET', 'cambia_esta_clave_secreta_muy_larga');
define('JWT_EXPIRE', 7 * 24 * 60 * 60); // 7 días

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
?>