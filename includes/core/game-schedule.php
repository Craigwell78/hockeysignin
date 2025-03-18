<?php

namespace hockeysignin\Core;

class GameSchedule {
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getNextGameDate() {
        $directory_map = get_option('hockey_directory_map', []);
        if (empty($directory_map)) {
            return null;
        }
        
        $today = new \DateTime('now', new \DateTimeZone(wp_timezone_string()));
        $today_day = $today->format('l');
        $game_days = array_keys($directory_map);
        
        // If today is a game day and it's before the game time
        if (in_array($today_day, $game_days)) {
            $game_time = $this->getGameTime($directory_map[$today_day]);
            $current_time = $today->format('Hi');
            
            if ($current_time < $game_time) {
                return $today->format('Y-m-d');
            }
        }
        
        // Find the next game day
        $next_date = clone $today;
        do {
            $next_date->modify('+1 day');
            $next_day = $next_date->format('l');
        } while (!in_array($next_day, $game_days));
        
        return $next_date->format('Y-m-d');
    }
    
    private function getGameTime($directory) {
        // Extract time from directory name (format: DayHHmmAMPMVenue)
        preg_match('/\d{4}(?:AM|PM)/', $directory, $matches);
        return $matches[0] ?? '0000';
    }
    
    public function isGameDay($date) {
        $directory_map = get_option('hockey_directory_map', []);
        $day = date('l', strtotime($date));
        return isset($directory_map[$day]);
    }
    
    public function getCheckInTimeRange() {
        // Start time is fixed at 8am
        $start = '8:00';
        
        // End time is the waitlist processing time
        $waitlist_time = get_option('hockey_waitlist_processing_time', '17:00');
        
        return [
            'start' => $start,
            'end' => $waitlist_time
        ];
    }
} 