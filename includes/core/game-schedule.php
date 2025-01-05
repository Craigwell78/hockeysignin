<?php

namespace hockeysignin\Core;

class GameSchedule {
    private static $instance = null;
    private $game_days = [2, 4, 5, 6]; // Tuesday, Thursday, Friday, Saturday
    private $game_schedule = ['Tuesday', 'Thursday', 'Friday', 'Saturday'];
    
    private function __construct() {}
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getNextGameDate() {
        $today = current_time('Y-m-d');
        $day_of_week = date('N', strtotime($today));
        $current_time = current_time('H:i');
        
        // Check if today is a game day and it's before 8 AM
        if (in_array($day_of_week, $this->game_days) && $current_time < '08:00') {
            return $today;
        }
        
        // Special cases
        if ($this->isBetweenSaturdayAndTuesday($day_of_week, $current_time)) {
            return date('Y-m-d', strtotime('next Tuesday', strtotime($today)));
        }
        
        if ($this->isBetweenTuesdayAndThursday($day_of_week, $current_time)) {
            return date('Y-m-d', strtotime('next Thursday', strtotime($today)));
        }
        
        if ($this->isBetweenThursdayAndFriday($day_of_week, $current_time)) {
            return date('Y-m-d', strtotime('next Friday', strtotime($today)));
        }
        
        if ($this->isBetweenFridayAndSaturday($day_of_week, $current_time)) {
            return date('Y-m-d', strtotime('next Saturday', strtotime($today)));
        }
        
        // Default case: find next game day
        return $this->findNextGameDay($day_of_week);
    }
    
    private function isBetweenSaturdayAndTuesday($day_of_week, $current_time) {
        return ($day_of_week == 6 && $current_time >= '23:00') || 
               $day_of_week == 7 || 
               $day_of_week == 1 || 
               ($day_of_week == 2 && $current_time < '08:00');
    }
    
    private function isBetweenTuesdayAndThursday($day_of_week, $current_time) {
        return ($day_of_week == 2 && $current_time >= '23:00') || 
               $day_of_week == 3 || 
               ($day_of_week == 4 && $current_time < '08:00');
    }
    
    private function isBetweenThursdayAndFriday($day_of_week, $current_time) {
        return ($day_of_week == 4 && $current_time >= '23:00') || 
               ($day_of_week == 5 && $current_time < '08:00');
    }
    
    private function isBetweenFridayAndSaturday($day_of_week, $current_time) {
        return ($day_of_week == 5 && $current_time >= '23:00') || 
               ($day_of_week == 6 && $current_time < '08:00');
    }
    
    private function findNextGameDay($current_day_of_week) {
        foreach ($this->game_days as $day) {
            if ($day > $current_day_of_week) {
                return date('Y-m-d', strtotime('next ' . $this->game_schedule[$day - 2]));
            }
        }
        // If no game day found in current week, return first game day of next week
        return date('Y-m-d', strtotime('next ' . $this->game_schedule[0]));
    }
    
    public function isGameDay($date) {
        return in_array(date('l', strtotime($date)), $this->game_schedule);
    }
} 