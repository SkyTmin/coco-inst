<?php
/**
 * Main API Router for Coco Instruments
 * Handles all API requests and routes them to appropriate endpoints
 */

// Error handling and reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Set timezone
date_default_timezone_set('Europe/Moscow');

// CORS Headers - MUST be sent before any output
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 86400");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include required files
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/auth.php';

// Autoload endpoint classes
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/endpoints/' . strtolower(str_replace('\\', '/', $class)) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/api/v1';

// Remove query string and normalize path
$path = parse_url($requestUri, PHP_URL_PATH);
$path = str_replace('/api/v1', '', $path);
$path = rtrim($path, '/') ?: '/';

// Parse request body for JSON
$requestData = [];
if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
    $rawData = file_get_contents('php://input');
    if (!empty($rawData)) {
        $requestData = json_decode($rawData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            sendResponse(['error' => 'Invalid JSON in request body'], 400);
        }
    }
}

// Initialize database connection
try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    sendResponse(['error' => 'Database connection failed'], 500);
}

// Route definitions
$routes = [
    // Auth routes (no auth required)
    'POST /auth/register' => ['handler' => 'Auth::register', 'auth' => false],
    'POST /auth/login' => ['handler' => 'Auth::login', 'auth' => false],
    'POST /auth/refresh' => ['handler' => 'Auth::refresh', 'auth' => false],
    'POST /auth/logout' => ['handler' => 'Auth::logout', 'auth' => true],
    'GET /auth/profile' => ['handler' => 'Auth::profile', 'auth' => true],
    
    // Coco Money routes
    'GET /coco-money/sheets' => ['handler' => 'CocoMoney::getSheets', 'auth' => true],
    'POST /coco-money/sheets' => ['handler' => 'CocoMoney::saveSheets', 'auth' => true],
    'GET /coco-money/categories' => ['handler' => 'CocoMoney::getCategories', 'auth' => true],
    'POST /coco-money/categories' => ['handler' => 'CocoMoney::saveCategories', 'auth' => true],
    
    // Debts routes
    'GET /debts' => ['handler' => 'Debts::getDebts', 'auth' => true],
    'POST /debts' => ['handler' => 'Debts::saveDebts', 'auth' => true],
    'GET /debts/categories' => ['handler' => 'Debts::getCategories', 'auth' => true],
    'POST /debts/categories' => ['handler' => 'Debts::saveCategories', 'auth' => true],
    
    // Clothing Size routes
    'GET /clothing-size' => ['handler' => 'ClothingSize::getData', 'auth' => true],
    'POST /clothing-size' => ['handler' => 'ClothingSize::saveData', 'auth' => true],
    
    // Scale Calculator routes  
    'GET /geodesy/scale-calculator/history' => ['handler' => 'ScaleCalculator::getHistory', 'auth' => true],
    'POST /geodesy/scale-calculator/history' => ['handler' => 'ScaleCalculator::saveHistory', 'auth' => true],
];

// Find matching route
$routeKey = "$method $path";
$route = null;

foreach ($routes as $pattern => $config) {
    if (preg_match('#^' . str_replace('/', '\/', $pattern) . '$#', $routeKey, $matches)) {
        $route = $config;
        break;
    }
}

// 404 if no route found
if (!$route) {
    sendResponse(['error' => 'Endpoint not found'], 404);
}

// Check authentication if required
$userId = null;
if ($route['auth']) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    if (empty($authHeader)) {
        sendResponse(['error' => 'Authorization header missing'], 401);
    }
    
    if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        sendResponse(['error' => 'Invalid authorization format'], 401);
    }
    
    $token = $matches[1];
    $middleware = new Middleware();
    $authResult = $middleware->authenticate($token);
    
    if (!$authResult['valid']) {
        sendResponse(['error' => $authResult['error'] ?? 'Invalid or expired token'], 401);
    }
    
    $userId = $authResult['userId'];
}

// Execute handler
try {
    list($class, $method) = explode('::', $route['handler']);
    
    // Include the appropriate endpoint file
    $endpointFile = __DIR__ . '/endpoints/' . strtolower($class) . '.php';
    if (!file_exists($endpointFile)) {
        throw new Exception("Endpoint file not found: $endpointFile");
    }
    require_once $endpointFile;
    
    // Create instance and call method
    $instance = new $class($db, $userId);
    
    if (!method_exists($instance, $method)) {
        throw new Exception("Method $method not found in class $class");
    }
    
    $response = $instance->$method($requestData);
    sendResponse($response);
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    sendResponse(['error' => 'Internal server error', 'message' => $e->getMessage()], 500);
}

/**
 * Send JSON response and exit
 */
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    
    // Ensure we're sending JSON
    header('Content-Type: application/json; charset=UTF-8');
    
    // Format response
    if ($statusCode >= 200 && $statusCode < 300) {
        $response = [
            'success' => true,
            'data' => $data
        ];
    } else {
        $response = [
            'success' => false,
            'error' => $data['error'] ?? 'Unknown error',
            'message' => $data['message'] ?? null
        ];
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}