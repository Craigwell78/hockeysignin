<?php

namespace hockeysignin\Core;

class SeasonConfig {
    private static $instance = null;
    
    private $regular_season_map = [
        'Tuesday' => 'Tues1030Forum',
        'Thursday' => 'Thur1030Civic',
        'Friday' => 'Fri1030Forum',
        'Saturday' => 'Sat1030Forum',
    ];
    
    private $spring_summer_map = [
        'Tuesday' => 'Tues1030Civic',
        'Thursday' => 'Thur1030Civic',
        'Friday' => 'Fri1030Civic',
        'Saturday' => 'Sat1030Civic',
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
        $config = get_option('hockey_directory_map', []);
        return $config;
    }
} 