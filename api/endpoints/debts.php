<?php
/**
 * Debts endpoints
 * Handles debt management and debt categories
 */

class Debts {
    private $db;
    private $userId;
    
    public function __construct($db, $userId) {
        $this->db = $db;
        $this->userId = $userId;
    }
    
    /**
     * Get debts
     */
    public function getDebts($data) {
        $result = $this->db->fetchOne(
            "SELECT debts, updated_at FROM debts WHERE user_id = ?",
            [$this->userId]
        );
        
        if (!$result) {
            return [];
        }
        
        $debts = json_decode($result['debts'], true);
        return is_array($debts) ? $debts : [];
    }
    
    /**
     * Save debts
     */
    public function saveDebts($data) {
        if (!isset($data['debts'])) {
            throw new Exception('Debts data is required');
        }
        
        $debts = $data['debts'];
        
        // Validate debts
        if (!is_array($debts)) {
            throw new Exception('Debts must be an array');
        }
        
        foreach ($debts as $debt) {
            $this->validateDebt($debt);
        }
        
        $jsonData = json_encode($debts, JSON_UNESCAPED_UNICODE);
        
        // Check if data already exists
        $exists = $this->db->fetchOne(
            "SELECT id FROM debts WHERE user_id = ?",
            [$this->userId]
        );
        
        if ($exists) {
            // Update existing
            $this->db->execute(
                "UPDATE debts 
                 SET debts = ?, updated_at = CURRENT_TIMESTAMP 
                 WHERE user_id = ?",
                [$jsonData, $this->userId]
            );
        } else {
            // Insert new
            $this->db->execute(
                "INSERT INTO debts (user_id, debts) VALUES (?, ?)",
                [$this->userId, $jsonData]
            );
        }
        
        return ['message' => 'Debts saved successfully'];
    }
    
    /**
     * Get debt categories
     */
    public function getCategories($data) {
        $result = $this->db->fetchOne(
            "SELECT categories FROM debt_categories WHERE user_id = ?",
            [$this->userId]
        );
        
        if (!$result) {
            return [];
        }
        
        $categories = json_decode($result['categories'], true);
        return is_array($categories) ? $categories : [];
    }
    
    /**
     * Save debt categories
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
            "SELECT id FROM debt_categories WHERE user_id = ?",
            [$this->userId]
        );
        
        if ($exists) {
            // Update existing
            $this->db->execute(
                "UPDATE debt_categories 
                 SET categories = ?, updated_at = CURRENT_TIMESTAMP 
                 WHERE user_id = ?",
                [$jsonData, $this->userId]
            );
        } else {
            // Insert new
            $this->db->execute(
                "INSERT INTO debt_categories (user_id, categories) VALUES (?, ?)",
                [$this->userId, $jsonData]
            );
        }
        
        return ['message' => 'Categories saved successfully'];
    }
    
    /**
     * Validate debt data
     */
    private function validateDebt($debt) {
        if (!is_array($debt)) {
            throw new Exception('Each debt must be an object');
        }
        
        // Required fields
        if (!isset($debt['id']) || !isset($debt['amount'])) {
            throw new Exception('Each debt must have id and amount');
        }
        
        // Validate amount
        if (!is_numeric($debt['amount']) || $debt['amount'] < 0) {
            throw new Exception('Debt amount must be a positive number');
        }
        
        // Validate status if present
        if (isset($debt['status'])) {
            $validStatuses = ['active', 'partial', 'closed'];
            if (!in_array($debt['status'], $validStatuses)) {
                throw new Exception('Invalid debt status');
            }
        }
        
        // Validate payments if present
        if (isset($debt['payments']) && is_array($debt['payments'])) {
            foreach ($debt['payments'] as $payment) {
                if (!isset($payment['amount']) || !is_numeric($payment['amount']) || $payment['amount'] < 0) {
                    throw new Exception('Payment amount must be a positive number');
                }
            }
        }
    }
}