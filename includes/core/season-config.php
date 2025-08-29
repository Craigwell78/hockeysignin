<?php

namespace hockeysignin\Core;

class SeasonConfig {
    private static $instance = null;
    private $directory_map;
    
    private function __construct() {
        $this->directory_map = get_option('hockey_directory_map', []);
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getDayDirectory($date) {
        // Check for date override first
        $date_override = \hockeysignin\Core\DateOverride::getInstance();
        
        if ($date_override->hasOverride($date)) {
            return $date_override->getDirectoryForDate($date);
        }
        
        // Fall back to regular season configuration
        $day_of_week = date('l', strtotime($date));
        return isset($this->directory_map[$day_of_week]) ? $this->directory_map[$day_of_week] : null;
    }
    
    public function getGameDays() {
        return array_keys($this->directory_map);
    }
} 