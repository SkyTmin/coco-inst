<?php
/**
 * Clothing Size endpoints
 * Handles clothing size parameters and saved results
 */

class ClothingSize {
    private $db;
    private $userId;
    
    public function __construct($db, $userId) {
        $this->db = $db;
        $this->userId = $userId;
    }
    
    /**
     * Get clothing size data
     */
    public function getData($data) {
        $result = $this->db->fetchOne(
            "SELECT data, updated_at FROM clothing_size WHERE user_id = ?",
            [$this->userId]
        );
        
        if (!$result) {
            // Return default structure
            return [
                'parameters' => new stdClass(),
                'savedResults' => [],
                'currentGender' => 'male'
            ];
        }
        
        $clothingData = json_decode($result['data'], true);
        
        // Ensure proper structure
        if (!isset($clothingData['parameters'])) {
            $clothingData['parameters'] = new stdClass();
        }
        if (!isset($clothingData['savedResults'])) {
            $clothingData['savedResults'] = [];
        }
        if (!isset($clothingData['currentGender'])) {
            $clothingData['currentGender'] = 'male';
        }
        
        return $clothingData;
    }
    
    /**
     * Save clothing size data
     */
    public function saveData($data) {
        // Validate structure
        if (!is_array($data)) {
            throw new Exception('Invalid data format');
        }
        
        // Extract and validate components
        $parameters = $data['parameters'] ?? new stdClass();
        $savedResults = $data['savedResults'] ?? [];
        $currentGender = $data['currentGender'] ?? 'male';
        
        // Validate parameters
        if (!is_object($parameters) && !is_array($parameters)) {
            throw new Exception('Parameters must be an object');
        }
        
        // Validate saved results
        if (!is_array($savedResults)) {
            throw new Exception('Saved results must be an array');
        }
        
        // Validate gender
        $validGenders = ['male', 'female', 'child'];
        if (!in_array($currentGender, $validGenders)) {
            throw new Exception('Invalid gender value');
        }
        
        // Validate parameter values if present
        if (!empty($parameters)) {
            foreach ($parameters as $key => $value) {
                if (!is_numeric($value) || $value < 0) {
                    throw new Exception("Parameter '$key' must be a positive number");
                }
            }
        }
        
        // Prepare data for storage
        $clothingData = [
            'parameters' => $parameters ?: new stdClass(),
            'savedResults' => $savedResults,
            'currentGender' => $currentGender
        ];
        
        $jsonData = json_encode($clothingData, JSON_UNESCAPED_UNICODE);
        
        // Check if data already exists
        $exists = $this->db->fetchOne(
            "SELECT id FROM clothing_size WHERE user_id = ?",
            [$this->userId]
        );
        
        if ($exists) {
            // Update existing
            $this->db->execute(
                "UPDATE clothing_size 
                 SET data = ?, updated_at = CURRENT_TIMESTAMP 
                 WHERE user_id = ?",
                [$jsonData, $this->userId]
            );
        } else {
            // Insert new
            $this->db->execute(
                "INSERT INTO clothing_size (user_id, data) VALUES (?, ?)",
                [$this->userId, $jsonData]
            );
        }
        
        return ['message' => 'Clothing size data saved successfully'];
    }
}