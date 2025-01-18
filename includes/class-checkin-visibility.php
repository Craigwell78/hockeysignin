<?php

namespace hockeysignin\Core;

class CheckInVisibility {
    private $instance;
    
    public function __construct($instance = 'HPH') {
        $this->instance = $instance;
    }
    
    public function shouldShowCheckIn() {
        // Check for testing mode first
        if (get_option('hockeysignin_testing_mode', '0') === '1') {
            return true;
        }
        
        // Skip check if admin has manually disabled sign-in
        if (get_option('hockeysignin_off_state')) {
            return false;
        }
        
        // Get current time in proper timezone
        $current_time = current_time('timestamp');
        $hour = (int)date('G', $current_time);
        $day = date('D', $current_time);
        
        // Check if it's a game day
        $game_days = $this->getGameDays();
        if (!in_array($day, $game_days)) {
            return false;
        }
        
        // Check if within check-in hours (8am to 6pm)
        if ($hour >= 8 && $hour < 18) {
            return true;
        }
        
        return false;
    }
    
    private function getGameDays() {
        $game_days = [
            'HPH' => ['Tue', 'Thu', 'Fri', 'Sat'],
            'SSPH' => ['Sun', 'Thu']
        ];
        
        return $game_days[$this->instance] ?? [];
    }
} 