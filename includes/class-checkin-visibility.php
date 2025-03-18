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
        hockey_log("Testing mode check - Value: " . $testing_mode, 'debug');
        
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
        $hour = (int)date('G', $current_time);
        $day = date('D', $current_time);
        
        // Check if it's a game day
        $game_days = $this->getGameDays();
        if (!in_array($day, $game_days)) {
            return false;
        }
        
        // Get check-in time range from GameSchedule
        $schedule = \hockeysignin\Core\GameSchedule::getInstance();
        $time_range = $schedule->getCheckInTimeRange();
        
        // Convert times to hours for comparison
        $start_hour = (int)date('G', strtotime($time_range['start']));
        $end_hour = (int)date('G', strtotime($time_range['end']));
        
        // Check if within check-in hours
        if ($hour >= $start_hour && $hour < $end_hour) {
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