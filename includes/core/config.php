<?php
namespace hockeysignin\Core;

class Config {
    private static $instance = null;
    private $config = [];
    private $seasons = [];

    private function __construct() {
        $this->config = require __DIR__ . '/../config/config.php';
        $this->seasons = require __DIR__ . '/../config/seasons.php';
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get($key, $default = null) {
        return $this->config[$key] ?? $default;
    }

    public function getSeason($date) {
        $month_day = date('m-d', strtotime($date));
        
        if ($month_day >= '10-01' || $month_day < '04-01') {
            return 'regular';
        }
        return 'summer';
    }

    public function getSeasonFolder($date) {
        $season = $this->getSeason($date);
        $folder = $season === 'regular' ? 'RegularSeason2024-2025' : 'Summer2025';
        return $folder;
    }
}