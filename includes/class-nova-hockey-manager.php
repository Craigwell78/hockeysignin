<?php
/**
 * Nova Adult Hockey Manager
 * Unified management system for multiple regions
 */

namespace hockeysignin\Core;

class NovaHockeyManager {
    private static $instance = null;
    private $config;
    private $current_region;
    
    private function __construct() {
        $this->config = require __DIR__ . '/../config/nova-config.php';
        $this->current_region = get_current_region();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get current region configuration
     */
    public function getCurrentRegionConfig() {
        return $this->getRegionConfig($this->current_region);
    }
    
    /**
     * Get configuration for a specific region
     */
    public function getRegionConfig($region_key) {
        return $this->config['regions'][$region_key] ?? null;
    }
    
    /**
     * Get all available regions
     */
    public function getAvailableRegions() {
        return array_keys($this->config['regions']);
    }
    
    /**
     * Get venues for a region
     */
    public function getVenuesForRegion($region_key = null) {
        $region_key = $region_key ?: $this->current_region;
        $region_config = $this->getRegionConfig($region_key);
        return $region_config['venues'] ?? [];
    }
    
    /**
     * Get schedule for a region
     */
    public function getScheduleForRegion($region_key = null) {
        $region_key = $region_key ?: $this->current_region;
        $region_config = $this->getRegionConfig($region_key);
        return $region_config['schedules'] ?? [];
    }
    
    /**
     * Get season configuration for a region and date
     */
    public function getSeasonConfig($region_key = null, $date = null) {
        $region_key = $region_key ?: $this->current_region;
        $date = $date ?: current_time('Y-m-d');
        
        $region_config = $this->getRegionConfig($region_key);
        if (!$region_config) {
            return null;
        }
        
        $month_day = date('m-d', strtotime($date));
        
        foreach ($region_config['seasons'] as $season_key => $season_config) {
            $start = $season_config['start'];
            $end = $season_config['end'];
            
            // Handle year boundary (e.g., regular season Oct-Mar)
            if ($start > $end) {
                if ($month_day >= $start || $month_day <= $end) {
                    return $season_config;
                }
            } else {
                if ($month_day >= $start && $month_day <= $end) {
                    return $season_config;
                }
            }
        }
        
        // Default to regular season if no match
        return $region_config['seasons']['regular'] ?? null;
    }
    
    /**
     * Get directory path for a game
     */
    public function getGameDirectory($region_key, $date, $venue, $time) {
        $season_config = $this->getSeasonConfig($region_key, $date);
        if (!$season_config) {
            return null;
        }
        
        $day_of_week = date('l', strtotime($date));
        $directory_map = $season_config['directory_map'];
        
        // Check for date overrides first
        if (class_exists('\hockeysignin\Core\DateOverride')) {
            $date_override = \hockeysignin\Core\DateOverride::getInstance();
            if ($date_override->hasOverride($date)) {
                $day_of_week = $date_override->getDayOfWeek($date);
            }
        }
        
        $base_directory = $directory_map[$day_of_week] ?? null;
        if (!$base_directory) {
            return null;
        }
        
        // Build full directory path
        $year = date('Y', strtotime($date));
        $folder_format = $season_config['folder_format'];
        
        // Replace placeholders in folder format
        $folder_name = str_replace(
            ['{year}', '{next_year}'],
            [$year, $year + 1],
            $folder_format
        );
        
        return "rosters/{$folder_name}/{$base_directory}";
    }
    
    /**
     * Get global settings
     */
    public function getGlobalSettings() {
        return $this->config['global_settings'];
    }
    
    /**
     * Get feature flags
     */
    public function getFeatures() {
        return $this->config['features'];
    }
    
    /**
     * Check if a feature is enabled
     */
    public function isFeatureEnabled($feature_name) {
        $features = $this->getFeatures();
        return $features[$feature_name] ?? false;
    }
    
    /**
     * Get check-in visibility for current region
     */
    public function shouldShowCheckIn($region_key = null) {
        $region_key = $region_key ?: $this->current_region;
        
        // Use checkin visibility class if available
        if (class_exists('\HockeySignin\CheckInVisibility')) {
            $checkin_visibility = new \HockeySignin\CheckInVisibility($region_key);
            return $checkin_visibility->shouldShowCheckIn();
        }
        
        // Fallback to basic time-based check
        $global_settings = $this->getGlobalSettings();
        $current_hour = (int)date('G');
        $checkin_hours = $global_settings['checkin_hours'];
        
        return $current_hour >= $checkin_hours['start'] && $current_hour < $checkin_hours['end'];
    }
    
    /**
     * Get region-specific game fee
     */
    public function getGameFee($region_key = null, $venue = null) {
        $region_key = $region_key ?: $this->current_region;
        $global_settings = $this->getGlobalSettings();
        
        $base_fee = $global_settings['default_game_fee'];
        
        // Add venue-specific surcharges if needed
        $venues = $this->getVenuesForRegion($region_key);
        if ($venue && isset($venues[$venue]['surcharge'])) {
            $base_fee += $venues[$venue]['surcharge'];
        }
        
        return $base_fee;
    }
    
    /**
     * Get all regions with their basic info
     */
    public function getAllRegionsInfo() {
        $regions_info = [];
        
        foreach ($this->config['regions'] as $key => $region) {
            $regions_info[$key] = [
                'name' => $region['name'],
                'domain' => $region['domain'],
                'venue_count' => count($region['venues']),
                'schedule_days' => array_keys($region['schedules'])
            ];
        }
        
        return $regions_info;
    }
    
    /**
     * Switch current region (for admin purposes)
     */
    public function setCurrentRegion($region_key) {
        if (array_key_exists($region_key, $this->config['regions'])) {
            $this->current_region = $region_key;
            update_option('hockey_current_region', $region_key);
            return true;
        }
        return false;
    }
    
    /**
     * Get current region key
     */
    public function getCurrentRegion() {
        return $this->current_region;
    }
}
