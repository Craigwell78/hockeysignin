<?php
namespace HockeySignin;

class CheckInVisibility {
    private $instance;
    
    public function __construct($instance = 'HPH') {
        $this->instance = $instance;
    }
    
    public function shouldShowCheckIn() {
        // Check for testing mode first
        $testing_mode = get_option('hockeysignin_testing_mode', '0');
        
        if ($testing_mode === '1') {
            hockey_log("Testing mode is enabled, allowing check-in", 'debug');
            return true;
        }
        
        // Skip check if admin has manually disabled sign-in
        if (get_option('hockeysignin_off_state')) {
            return false;
        }
        
        // Get current time in proper timezone
        $current_time = current_time('timestamp');
        $current_date = current_time('Y-m-d');
        $hour = (int)date('G', $current_time);
        $day = date('D', $current_time);
        
        // Check if there's a date override for today
        $date_override = \hockeysignin\Core\DateOverride::getInstance();
        $has_override = $date_override->hasOverride($current_date);
        
        // Check if it's a game day (either regular or via override)
        $game_days = $this->getGameDays();
        $is_game_day = in_array($day, $game_days);
        
        if (!$is_game_day && !$has_override) {
            hockey_log("Not a game day and no date override for {$current_date}", 'debug');
            return false;
        }
        
        // If there's a date override, check if we're within check-in hours
        if ($has_override) {
            hockey_log("Date override detected for {$current_date}, checking check-in hours", 'debug');
        }
        
        // Check if within check-in hours (8am to 6pm)
        if ($hour >= 8 && $hour < 18) {
            hockey_log("Within check-in hours ({$hour}:00), allowing check-in", 'debug');
            return true;
        }
        
        hockey_log("Outside check-in hours ({$hour}:00), check-in disabled", 'debug');
        return false;
    }
    
    private function getGameDays() {
        // Get the directory map from WordPress options
        $directory_map = get_option('hockey_directory_map', []);
        
        // Extract enabled days from the directory map
        $enabled_days = array_keys($directory_map);
        
        // Convert full day names to three-letter abbreviations
        $game_days = array_map(function($day) {
            return substr($day, 0, 3);
        }, $enabled_days);
        
        return $game_days;
    }
} 