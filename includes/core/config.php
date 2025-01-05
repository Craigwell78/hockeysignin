<?php
namespace hockeysignin\core;

class Config {
    private static $instance = null;
    private $config = [];
    private $seasons = [];

    private function __construct() {
        $this->config = require __DIR__ . '/../config/config.php';
        $this->seasons = require __DIR__ . '/../config/seasons.php';
    }

    public static function getInstance() {
        if (self::$instance === null) {
            hockey_log("Creating new Config instance", 'debug');
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get($key, $default = null) {
        $value = $this->config[$key] ?? $default;
        hockey_log("Config get: {$key} = " . print_r($value, true), 'debug');
        return $value;
    }

    public function getSeason($date) {
        hockey_log("Getting season for date: {$date}", 'debug');
        $month_day = date('m-d', strtotime($date));
        
        foreach ($this->seasons as $season => $details) {
            $start = $details['start'];
            $end = $details['end'];
            
            if ($this->isDateInRange($month_day, $start, $end)) {
                hockey_log("Found season: {$season}", 'debug');
                return $details;
            }
        }
        
        hockey_log("No season found for date: {$date}", 'warning');
        return null;
    }

    private function isDateInRange($date, $start, $end) {
        if ($start <= $end) {
            return $date >= $start && $date <= $end;
        }
        // Handles wrapping around year end (e.g., Oct-Mar season)
        return $date >= $start || $date <= $end;
    }

    public function getSeasonFolder($date) {
        hockey_log("Getting season folder for date: {$date}", 'debug');
        $month_day = date('m-d', strtotime($date));
        $year = date('Y', strtotime($date));
        
        // If date is between January 1st and March 31st, use previous year
        if ($month_day < '04-01') {
            $year = $year - 1;
        }
        
        $next_year = $year + 1;
        $season_details = $this->getSeason($date);
        
        if (!$season_details) {
            hockey_log("No season details found for date: {$date}", 'warning');
            return null;
        }
        
        $folder_format = $season_details['folder_format'];
        $folder_format = str_replace('{year}', $year, $folder_format);
        $folder_format = str_replace('{next_year}', $next_year, $folder_format);
        
        hockey_log("Season folder: {$folder_format}", 'debug');
        return $folder_format;
    }
}