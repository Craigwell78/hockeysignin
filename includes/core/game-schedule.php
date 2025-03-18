<?php

namespace hockeysignin\Core;

class GameSchedule {
    private static $instance = null;
    private $game_schedule = ['Tuesday', 'Thursday', 'Friday', 'Saturday'];
    private $game_days = [2, 4, 5, 6]; // 1 = Monday, 2 = Tuesday, etc.
    
    private function __construct() {}
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getNextGameDate() {
        $today = current_time('Y-m-d');
        $current_time = current_time('H:i');
        $day_of_week = date('N', strtotime($today)); // 1 (Monday) through 7 (Sunday)
        
        hockey_log("GameSchedule::getNextGameDate() - Today: {$today}, Time: {$current_time}, Day of week: {$day_of_week}", 'debug');
        hockey_log("Game days array: " . print_r($this->game_days, true), 'debug');
        
        // If it's a game day and before or exactly 8am, return today
        if (in_array($day_of_week, $this->game_days) && $current_time <= '08:00') {
            hockey_log("Current day is game day at or before 8am", 'debug');
            return $today;
        }
        
        // If it's a game day after 8am, we still want to use today until midnight
        if (in_array($day_of_week, $this->game_days)) {
            hockey_log("Current day is game day after 8am", 'debug');
            return $today;
        }
        
        // Find the next game day
        foreach ($this->game_days as $index => $game_day) {
            hockey_log("Checking game day: {$game_day}", 'debug');
            if ($game_day > $day_of_week) {
                // Calculate days until next game
                $days_until = $game_day - $day_of_week;
                $next_date = date('Y-m-d', strtotime("+{$days_until} days", strtotime($today)));
                hockey_log("Found next game day: {$next_date} (day {$game_day})", 'debug');
                return $next_date;
            }
        }
        
        // If we're past Saturday or no later games this week, get next Tuesday
        // Calculate days until next Tuesday (day 2)
        $days_until = 7 - $day_of_week + 2;
        $next_date = date('Y-m-d', strtotime("+{$days_until} days", strtotime($today)));
        hockey_log("No more games this week, returning next Tuesday: {$next_date}", 'debug');
        return $next_date;
    }
    
    public function isGameDay($date) {
        return in_array(date('l', strtotime($date)), $this->game_schedule);
    }
} 