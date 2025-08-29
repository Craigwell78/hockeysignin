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
        $today_date = $today->format('Y-m-d');
        
        // Check for date overrides first
        $date_override = \hockeysignin\Core\DateOverride::getInstance();
        $overrides = $date_override->getAllOverrides();
        
        // Find the next override date (if any)
        $next_override_date = null;
        foreach ($overrides as $override_date => $override) {
            if ($override_date >= $today_date) {
                if (!$next_override_date || $override_date < $next_override_date) {
                    $next_override_date = $override_date;
                }
            }
        }
        
        // If we have an override today and it's before game time, return today
        if ($next_override_date === $today_date) {
            $override = $overrides[$today_date];
            $game_time = $this->getGameTimeFromOverride($override);
            $current_time = $today->format('Hi');
            
            if ($current_time < $game_time) {
                return $today_date;
            }
        }
        
        // If we have an override in the future, return it
        if ($next_override_date && $next_override_date > $today_date) {
            return $next_override_date;
        }
        
        // Fall back to regular season configuration
        $today_day = $today->format('l');
        $game_days = array_keys($directory_map);
        
        // If today is a game day and it's before the game time
        if (in_array($today_day, $game_days)) {
            $game_time = $this->getGameTime($directory_map[$today_day]);
            $current_time = $today->format('Hi');
            
            if ($current_time < $game_time) {
                return $today_date;
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
    
    private function getGameTimeFromOverride($override) {
        // Convert the actual time to HHMM format for comparison
        $time = $override['actual_time'];
        $timestamp = strtotime($time);
        return date('Hi', $timestamp);
    }
    
    public function isGameDay($date) {
        // Check for date override first
        $date_override = \hockeysignin\Core\DateOverride::getInstance();
        
        if ($date_override->hasOverride($date)) {
            // If there's an override, it's a game day
            return true;
        }
        
        // Fall back to regular season configuration
        $directory_map = get_option('hockey_directory_map', []);
        $day = date('l', strtotime($date));
        return isset($directory_map[$day]);
    }
    
    public function getCheckInTimeRange($date = null) {
        // Start time is fixed at 8am
        $start = '8:00';
        
        // If no date provided, use current date
        if (!$date) {
            $date = current_time('Y-m-d');
        }
        
        // Check for date override first
        $date_override = \hockeysignin\Core\DateOverride::getInstance();
        
        if ($date_override->hasOverride($date)) {
            $override = $date_override->getOverride($date);
            
            // Check if override has a custom waitlist time
            if (!empty($override['custom_waitlist_time'])) {
                $waitlist_time = $override['custom_waitlist_time'];
                hockey_log("Using custom waitlist time for override date {$date}: {$waitlist_time}", 'debug');
            } else {
                // For overrides without custom time, use the replacing day's waitlist time
                $replacing_day = $date_override->getDayOfWeek($date);
                $waitlist_times = get_option('hockey_waitlist_processing_times', []);
                $waitlist_time = $waitlist_times[$replacing_day] ?? get_option('hockey_waitlist_processing_time', '18:00');
            }
        } else {
            // Get day-specific waitlist time for regular dates
            $day = date('l', strtotime($date));
            $waitlist_times = get_option('hockey_waitlist_processing_times', []);
            $waitlist_time = $waitlist_times[$day] ?? get_option('hockey_waitlist_processing_time', '18:00');
        }
        
        return [
            'start' => $start,
            'end' => $waitlist_time
        ];
    }
} 