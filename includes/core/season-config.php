<?php

namespace hockeysignin\Core;

class SeasonConfig {
    private static $instance = null;
    
    private $regular_season_map = [
        'Monday' => 'Mon1030Forum',
        'Tuesday' => 'Tues1030Forum',
        'Thursday' => 'Thur1030Civic',
        'Friday' => 'Fri1030Forum',
        'Saturday' => 'Sat1030Forum',
    ];
    
    private $spring_summer_map = [
        'Monday' => 'Mon1030Forum',
        'Tuesday' => 'Tues1030Forum',
        'Thursday' => 'Thur1030Forum',
        'Friday' => 'Fri1030Forum',
        'Saturday' => 'Sat1000Forum',
    ];
    
    private function __construct() {}
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getDayDirectory($date) {
        $month_day = date('m-d', strtotime($date));
        $day_of_week = date('l', strtotime($date));
        
        $map = $this->getSeasonMap($month_day);
        return $map[$day_of_week] ?? null;
    }
    
    private function getSeasonMap($month_day) {
        if ($month_day >= '10-01' || $month_day < '04-01') {
            return $this->regular_season_map;
        }
        return $this->spring_summer_map;
    }
} 