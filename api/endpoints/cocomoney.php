<?php
/**
 * Coco Money endpoints
 * Handles income sheets, preliminary income, and expense categories
 */

class CocoMoney {
    private $db;
    private $userId;
    
    public function __construct($db, $userId) {
        $this->db = $db;
        $this->userId = $userId;
    }
    
    /**
     * Get sheets (income and preliminary)
     */
    public function getSheets($data) {
        $result = $this->db->fetchOne(
            "SELECT data, updated_at FROM coco_money_sheets WHERE user_id = ?",
            [$this->userId]
        );
        
        if (!$result) {
            // Return default structure if no data exists
            return [
                'income' => [],
                'preliminary' => []
            ];
        }
        
        $sheets = json_decode($result['data'], true);
        
        // Ensure structure is correct
        if (!isset($sheets['income'])) {
            $sheets['income'] = [];
        }
        if (!isset($sheets['preliminary'])) {
            $sheets['preliminary'] = [];
        }
        
        return $sheets;
    }
    
    /**
     * Save sheets (income and preliminary)
     */
    public function saveSheets($data) {
        if (!isset($data['sheets'])) {
            throw new Exception('Sheets data is required');
        }
        
        $sheets = $data['sheets'];
        
        // Validate structure
        if (!is_array($sheets) || !isset($sheets['income']) || !isset($sheets['preliminary'])) {
            throw new Exception('Invalid sheets structure');
        }
        
        // Validate each sheet
        $this->validateSheets($sheets['income']);
        $this->validateSheets($sheets['preliminary']);
        
        // Convert to JSON
        $jsonData = json_encode($sheets, JSON_UNESCAPED_UNICODE);
        
        // Check if data already exists
        $exists = $this->db->fetchOne(
            "SELECT id FROM coco_money_sheets WHERE user_id = ?",
            [$this->userId]
        );
        
        if ($exists) {
            // Update existing
            $this->db->execute(
                "UPDATE coco_money_sheets 
                 SET data = ?, updated_at = CURRENT_TIMESTAMP 
                 WHERE user_id = ?",
                [$jsonData, $this->userId]
            );
        } else {
            // Insert new
            $this->db->execute(
                "INSERT INTO coco_money_sheets (user_id, data) VALUES (?, ?)",
                [$this->userId, $jsonData]
            );
        }
        
        return ['message' => 'Sheets saved successfully'];
    }
    
    /**
     * Get categories
     */
    public function getCategories($data) {
        $result = $this->db->fetchOne(
            "SELECT categories FROM coco_money_categories WHERE user_id = ?",
            [$this->userId]
        );
        
        if (!$result) {
            return [];
        }
        
        $categories = json_decode($result['categories'], true);
        return is_array($categories) ? $categories : [];
    }
    
    /**
     * Save categories
     */
    public function saveCategories($data) {
        if (!isset($data['categories'])) {
            throw new Exception('Categories data is required');
        }
        
        $categories = $data['categories'];
        
        // Validate categories
        if (!is_array($categories)) {
            throw new Exception('Categories must be an array');
        }
        
        foreach ($categories as $category) {
            if (!isset($category['id']) || !isset($category['name'])) {
                throw new Exception('Each category must have id and name');
            }
        }
        
        $jsonData = json_encode($categories, JSON_UNESCAPED_UNICODE);
        
        // Check if data already exists
        $exists = $this->db->fetchOne(
            "SELECT id FROM coco_money_categories WHERE user_id = ?",
            [$this->userId]
        );
        
        if ($exists) {
            // Update existing
            $this->db->execute(
                "UPDATE coco_money_categories 
                 SET categories = ?, updated_at = CURRENT_TIMESTAMP 
                 WHERE user_id = ?",
                [$jsonData, $this->userId]
            );
        } else {
            // Insert new
            $this->db->execute(
                "INSERT INTO coco_money_categories (user_id, categories) VALUES (?, ?)",
                [$this->userId, $jsonData]
            );
        }
        
        return ['message' => 'Categories saved successfully'];
    }
    
    /**
     * Validate sheets array
     */
    private function validateSheets($sheets) {
        if (!is_array($sheets)) {
            throw new Exception('Sheets must be an array');
        }
        
        foreach ($sheets as $sheet) {
            if (!is_array($sheet)) {
                throw new Exception('Each sheet must be an object');
            }
            
            // Required fields
            if (!isset($sheet['id']) || !isset($sheet['amount'])) {
                throw new Exception('Each sheet must have id and amount');
            }
            
            // Validate amount
            if (!is_numeric($sheet['amount']) || $sheet['amount'] < 0) {
                throw new Exception('Amount must be a positive number');
            }
            
            // Validate expenses if present
            if (isset($sheet['expenses']) && is_array($sheet['expenses'])) {
                foreach ($sheet['expenses'] as $expense) {
                    if (!isset($expense['amount']) || !is_numeric($expense['amount']) || $expense['amount'] < 0) {
                        throw new Exception('Expense amount must be a positive number');
                    }
                }
            }
        }
    }
}