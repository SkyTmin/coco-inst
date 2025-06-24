<?php
/**
 * Authentication endpoints
 * Handles user registration, login, refresh tokens, and profile
 */

class Auth {
    private $db;
    private $userId;
    private $middleware;
    
    public function __construct($db, $userId = null) {
        $this->db = $db;
        $this->userId = $userId;
        $this->middleware = new Middleware();
    }
    
    /**
     * Register new user
     */
    public function register($data) {
        // Validate input
        if (!isset($data['email']) || !isset($data['password']) || !isset($data['name'])) {
            throw new Exception('Email, name, and password are required');
        }
        
        $email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
        if (!$email) {
            throw new Exception('Invalid email format');
        }
        
        $name = trim($data['name']);
        if (strlen($name) < 2) {
            throw new Exception('Name must be at least 2 characters');
        }
        
        if (strlen($data['password']) < 8) {
            throw new Exception('Password must be at least 8 characters');
        }
        
        // Check if email already exists
        $existing = $this->db->fetchOne(
            "SELECT id FROM users WHERE email = ?",
            [$email]
        );
        
        if ($existing) {
            throw new Exception('Email already registered');
        }
        
        // Hash password
        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        
        // Create user
        $this->db->beginTransaction();
        
        try {
            $this->db->execute(
                "INSERT INTO users (email, name, password) VALUES (?, ?, ?)",
                [$email, $name, $hashedPassword]
            );
            
            $userId = $this->db->lastInsertId();
            
            // Generate tokens
            $accessToken = $this->middleware->generateToken(['uid' => $userId]);
            $refreshToken = $this->middleware->generateRefreshToken();
            
            // Save refresh token
            $this->middleware->saveRefreshToken($userId, $refreshToken);
            
            // Create empty data for all modules
            $this->initializeUserData($userId);
            
            $this->db->commit();
            
            return [
                'user' => [
                    'id' => $userId,
                    'email' => $email,
                    'name' => $name
                ],
                'accessToken' => $accessToken,
                'refreshToken' => $refreshToken
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Login user
     */
    public function login($data) {
        // Validate input
        if (!isset($data['email']) || !isset($data['password'])) {
            throw new Exception('Email and password are required');
        }
        
        $email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
        if (!$email) {
            throw new Exception('Invalid email format');
        }
        
        // Check rate limiting
        if (!$this->middleware->checkRateLimit('login_' . $email, 5, 300)) {
            throw new Exception('Too many login attempts. Please try again later.');
        }
        
        // Find user
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE email = ?",
            [$email]
        );
        
        if (!$user) {
            throw new Exception('Invalid email or password');
        }
        
        // Check if account is locked
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            throw new Exception('Account is locked. Please try again later.');
        }
        
        // Verify password
        if (!password_verify($data['password'], $user['password'])) {
            // Increment failed attempts
            $this->db->execute(
                "UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE id = ?",
                [$user['id']]
            );
            
            // Lock account if too many attempts
            if ($user['failed_login_attempts'] >= MAX_LOGIN_ATTEMPTS - 1) {
                $lockedUntil = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_TIME);
                $this->db->execute(
                    "UPDATE users SET locked_until = ? WHERE id = ?",
                    [$lockedUntil, $user['id']]
                );
            }
            
            throw new Exception('Invalid email or password');
        }
        
        // Check if user is active
        if (!$user['is_active']) {
            throw new Exception('Account is deactivated');
        }
        
        // Reset failed attempts and update last login
        $this->db->execute(
            "UPDATE users SET 
                failed_login_attempts = 0, 
                locked_until = NULL,
                last_login = CURRENT_TIMESTAMP 
             WHERE id = ?",
            [$user['id']]
        );
        
        // Generate tokens
        $accessToken = $this->middleware->generateToken(['uid' => $user['id']]);
        $refreshToken = $this->middleware->generateRefreshToken();
        
        // Save refresh token
        $this->middleware->saveRefreshToken($user['id'], $refreshToken);
        
        return [
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name']
            ],
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken
        ];
    }
    
    /**
     * Refresh access token
     */
    public function refresh($data) {
        if (!isset($data['refreshToken'])) {
            throw new Exception('Refresh token is required');
        }
        
        $result = $this->middleware->validateRefreshToken($data['refreshToken']);
        
        if (!$result['valid']) {
            throw new Exception($result['error']);
        }
        
        // Get user
        $user = $this->db->fetchOne(
            "SELECT id, email, name FROM users WHERE id = ?",
            [$result['userId']]
        );
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // Generate new tokens
        $accessToken = $this->middleware->generateToken(['uid' => $user['id']]);
        $refreshToken = $this->middleware->generateRefreshToken();
        
        // Revoke old refresh token
        $this->middleware->revokeRefreshToken($data['refreshToken']);
        
        // Save new refresh token
        $this->middleware->saveRefreshToken($user['id'], $refreshToken);
        
        return [
            'user' => $user,
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken
        ];
    }
    
    /**
     * Logout user
     */
    public function logout($data) {
        if (isset($data['refreshToken'])) {
            $this->middleware->revokeRefreshToken($data['refreshToken']);
        }
        
        // Optionally revoke all tokens for this user
        if (isset($data['logoutAll']) && $data['logoutAll'] === true) {
            $this->middleware->revokeAllUserTokens($this->userId);
        }
        
        return ['message' => 'Logged out successfully'];
    }
    
    /**
     * Get user profile
     */
    public function profile($data) {
        $user = $this->db->fetchOne(
            "SELECT id, email, name, created_at, last_login FROM users WHERE id = ?",
            [$this->userId]
        );
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        return $user;
    }
    
    /**
     * Initialize empty user data for all modules
     */
    private function initializeUserData($userId) {
        // Initialize Coco Money data
        $this->db->execute(
            "INSERT INTO coco_money_sheets (user_id, data) VALUES (?, ?)",
            [$userId, json_encode(['income' => [], 'preliminary' => []])]
        );
        
        $this->db->execute(
            "INSERT INTO coco_money_categories (user_id, categories) VALUES (?, ?)",
            [$userId, json_encode([])]
        );
        
        // Initialize Debts data
        $this->db->execute(
            "INSERT INTO debts (user_id, debts) VALUES (?, ?)",
            [$userId, json_encode([])]
        );
        
        $this->db->execute(
            "INSERT INTO debt_categories (user_id, categories) VALUES (?, ?)",
            [$userId, json_encode([])]
        );
        
        // Initialize Clothing Size data
        $this->db->execute(
            "INSERT INTO clothing_size (user_id, data) VALUES (?, ?)",
            [$userId, json_encode([
                'parameters' => new stdClass(),
                'savedResults' => [],
                'currentGender' => 'male'
            ])]
        );
        
        // Initialize Scale Calculator data
        $this->db->execute(
            "INSERT INTO scale_calculator_history (user_id, history) VALUES (?, ?)",
            [$userId, json_encode([])]
        );
    }
}