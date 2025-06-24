<?php
/**
 * JWT Authentication Middleware
 * Handles token generation, validation, and user authentication
 */

class Middleware {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Generate JWT token
     */
    public function generateToken($payload, $expiresIn = JWT_ACCESS_TOKEN_EXPIRY) {
        $header = json_encode(['typ' => 'JWT', 'alg' => JWT_ALGORITHM]);
        
        $payload['iat'] = time();
        $payload['exp'] = time() + $expiresIn;
        $payload = json_encode($payload);
        
        $base64Header = $this->base64UrlEncode($header);
        $base64Payload = $this->base64UrlEncode($payload);
        
        $signature = hash_hmac(
            'sha256',
            $base64Header . '.' . $base64Payload,
            JWT_SECRET,
            true
        );
        
        $base64Signature = $this->base64UrlEncode($signature);
        
        return $base64Header . '.' . $base64Payload . '.' . $base64Signature;
    }
    
    /**
     * Validate JWT token
     */
    public function validateToken($token) {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return ['valid' => false, 'error' => 'Invalid token format'];
        }
        
        list($header, $payload, $signature) = $parts;
        
        // Verify signature
        $validSignature = hash_hmac(
            'sha256',
            $header . '.' . $payload,
            JWT_SECRET,
            true
        );
        
        if (!hash_equals($this->base64UrlEncode($validSignature), $signature)) {
            return ['valid' => false, 'error' => 'Invalid signature'];
        }
        
        // Decode payload
        $payloadData = json_decode($this->base64UrlDecode($payload), true);
        
        if (!$payloadData) {
            return ['valid' => false, 'error' => 'Invalid payload'];
        }
        
        // Check expiration
        if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
            return ['valid' => false, 'error' => 'Token expired'];
        }
        
        return ['valid' => true, 'payload' => $payloadData];
    }
    
    /**
     * Authenticate request
     */
    public function authenticate($token) {
        $result = $this->validateToken($token);
        
        if (!$result['valid']) {
            return $result;
        }
        
        $payload = $result['payload'];
        
        if (!isset($payload['uid'])) {
            return ['valid' => false, 'error' => 'User ID not found in token'];
        }
        
        // Verify user exists and is active
        $user = $this->db->fetchOne(
            "SELECT id, is_active FROM users WHERE id = ? AND is_active = TRUE",
            [$payload['uid']]
        );
        
        if (!$user) {
            return ['valid' => false, 'error' => 'User not found or inactive'];
        }
        
        return [
            'valid' => true,
            'userId' => $user['id'],
            'payload' => $payload
        ];
    }
    
    /**
     * Generate refresh token
     */
    public function generateRefreshToken() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Save refresh token
     */
    public function saveRefreshToken($userId, $token) {
        // Delete old tokens for this user (keep only last 5)
        $this->db->execute(
            "DELETE FROM refresh_tokens 
             WHERE user_id = ? 
             AND id NOT IN (
                SELECT id FROM (
                    SELECT id FROM refresh_tokens 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 4
                ) AS keep_tokens
             )",
            [$userId, $userId]
        );
        
        // Save new token
        $expiresAt = date('Y-m-d H:i:s', time() + JWT_REFRESH_TOKEN_EXPIRY);
        
        $this->db->execute(
            "INSERT INTO refresh_tokens (user_id, token, expires_at) VALUES (?, ?, ?)",
            [$userId, $token, $expiresAt]
        );
    }
    
    /**
     * Validate refresh token
     */
    public function validateRefreshToken($token) {
        $tokenData = $this->db->fetchOne(
            "SELECT rt.*, u.is_active 
             FROM refresh_tokens rt
             JOIN users u ON rt.user_id = u.id
             WHERE rt.token = ? 
             AND rt.expires_at > NOW()
             AND u.is_active = TRUE",
            [$token]
        );
        
        if (!$tokenData) {
            return ['valid' => false, 'error' => 'Invalid or expired refresh token'];
        }
        
        return [
            'valid' => true,
            'userId' => $tokenData['user_id']
        ];
    }
    
    /**
     * Revoke refresh token
     */
    public function revokeRefreshToken($token) {
        $this->db->execute(
            "DELETE FROM refresh_tokens WHERE token = ?",
            [$token]
        );
    }
    
    /**
     * Revoke all user tokens
     */
    public function revokeAllUserTokens($userId) {
        $this->db->execute(
            "DELETE FROM refresh_tokens WHERE user_id = ?",
            [$userId]
        );
    }
    
    /**
     * Clean expired tokens
     */
    public function cleanExpiredTokens() {
        $this->db->execute(
            "DELETE FROM refresh_tokens WHERE expires_at < NOW()"
        );
    }
    
    /**
     * Base64 URL encode
     */
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64 URL decode
     */
    private function base64UrlDecode($data) {
        $padding = strlen($data) % 4;
        if ($padding) {
            $data .= str_repeat('=', 4 - $padding);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
    
    /**
     * Rate limiting check
     */
    public function checkRateLimit($identifier, $limit = API_RATE_LIMIT, $window = 60) {
        // Simple file-based rate limiting
        // In production, use Redis or similar
        $cacheDir = dirname(__DIR__) . '/cache/rate_limit';
        if (!file_exists($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $file = $cacheDir . '/' . md5($identifier) . '.json';
        $now = time();
        
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            
            // Clean old entries
            $data = array_filter($data, function($timestamp) use ($now, $window) {
                return $timestamp > ($now - $window);
            });
            
            if (count($data) >= $limit) {
                return false;
            }
        } else {
            $data = [];
        }
        
        $data[] = $now;
        file_put_contents($file, json_encode($data));
        
        return true;
    }
}