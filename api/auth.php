<?php
require_once 'config.php';
require_once 'db.php';

class Auth {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function register($name, $email, $phone, $password) {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            return ['success' => false, 'message' => 'El correo ya está registrado'];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("INSERT INTO users (name, email, phone, password) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$name, $email, $phone, $hash])) {
            return ['success' => true, 'message' => 'Usuario creado'];
        }
        return ['success' => false, 'message' => 'Error al crear usuario'];
    }

    public function login($email, $password) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Credenciales inválidas'];
        }
        $token = $this->generateToken($user['id'], $user['email']);
        return [
            'success' => true,
            'token' => $token,
            'user' => ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'phone' => $user['phone']]
        ];
    }

    public function verifyToken($token) {
        if (!$token) return false;
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) return false;
            list($headerB64, $payloadB64, $sigB64) = $parts;
            $header = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $headerB64)), true);
            $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payloadB64)), true);
            $expectedSig = base64url_encode(hash_hmac('sha256', $headerB64 . '.' . $payloadB64, JWT_SECRET, true));
            if (!hash_equals($expectedSig, $sigB64)) return false;
            if (!isset($payload['exp']) || $payload['exp'] < time()) return false;
            return $payload;
        } catch (Exception $e) {
            return false;
        }
    }

    private function generateToken($userId, $email) {
        $header = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64url_encode(json_encode([
            'user_id' => $userId,
            'email' => $email,
            'iat' => time(),
            'exp' => time() + JWT_EXPIRE
        ]));
        $sig = base64url_encode(hash_hmac('sha256', $header . '.' . $payload, JWT_SECRET, true));
        return $header . '.' . $payload . '.' . $sig;
    }
}

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
?>