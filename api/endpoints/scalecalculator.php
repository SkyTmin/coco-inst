class ScaleCalculator {
    private $db;
    private $userId;
    
    public function __construct($db, $userId) {
        $this->db = $db;
        $this->userId = $userId;
    }
    
    /**
     * Get scale calculator history
     */
    public function getHistory($data) {
        $result = $this->db->fetchOne(
            "SELECT history, updated_at FROM scale_calculator_history WHERE user_id = ?",
            [$this->userId]
        );
        
        if (!$result) {
            return [];
        }
        
        $history = json_decode($result['history'], true);
        
        // Transform to expected format
        if (is_array($history)) {
            // Ensure each entry has the required fields
            $formattedHistory = array_map(function($entry) {
                return [
                    'id' => $entry['id'] ?? null,
                    'scale' => $entry['scale'] ?? 0,
                    'textHeight' => $entry['textHeight'] ?? 0,
                    'timestamp' => $entry['timestamp'] ?? null,
                    'createdAt' => $entry['timestamp'] ?? null // for backward compatibility
                ];
            }, $history);
            
            return $formattedHistory;
        }
        
        return [];
    }
    
    /**
     * Save scale calculator history
     */
    public function saveHistory($data) {
        if (!isset($data['history'])) {
            throw new Exception('History data is required');
        }
        
        $history = $data['history'];
        
        // Validate history
        if (!is_array($history)) {
            throw new Exception('History must be an array');
        }
        
        foreach ($history as $entry) {
            $this->validateHistoryEntry($entry);
        }
        
        // Limit history size to prevent excessive data
        if (count($history) > 100) {
            $history = array_slice($history, 0, 100);
        }
        
        $jsonData = json_encode($history, JSON_UNESCAPED_UNICODE);
        
        // Check if data already exists
        $exists = $this->db->fetchOne(
            "SELECT id FROM scale_calculator_history WHERE user_id = ?",
            [$this->userId]
        );
        
        if ($exists) {
            // Update existing
            $this->db->execute(
                "UPDATE scale_calculator_history 
                 SET history = ?, updated_at = CURRENT_TIMESTAMP 
                 WHERE user_id = ?",
                [$jsonData, $this->userId]
            );
        } else {
            // Insert new
            $this->db->execute(
                "INSERT INTO scale_calculator_history (user_id, history) VALUES (?, ?)",
                [$this->userId, $jsonData]
            );
        }
        
        return ['message' => 'History saved successfully'];
    }
    
    /**
     * Validate history entry
     */
    private function validateHistoryEntry($entry) {
        if (!is_array($entry)) {
            throw new Exception('Each history entry must be an object');
        }
        
        // Required fields
        if (!isset($entry['scale']) || !isset($entry['textHeight'])) {
            throw new Exception('Each history entry must have scale and textHeight');
        }
        
        // Validate scale
        if (!is_numeric($entry['scale']) || $entry['scale'] <= 0) {
            throw new Exception('Scale must be a positive number');
        }
        
        // Validate text height
        if (!is_numeric($entry['textHeight']) || $entry['textHeight'] <= 0) {
            throw new Exception('Text height must be a positive number');
        }
    }
}<?php
/**
 * Scale Calculator endpoints
 * Handles scale calculation history
 */

class ScaleCalculator {
    private $db;
    private $userId;
    
    public function __construct($db, $userId) {
        $this->db = $db;
        $this->userId = $userId;
    }
    
    /**
     * Get scale calculator history
     */
    public function getHistory($data) {
        $result = $this->db->fetchOne(
            "SELECT history,