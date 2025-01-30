<?php

namespace HockeySignin;

class Form_Handler {
    private static $instance = null;
    private $nonce_action = 'hockeysignin_action';
    private $nonce_field = 'hockeysignin_nonce';
    
    private function __construct() {}
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function verify_nonce($nonce = null, $action = null) {
        $nonce = $nonce ?? $_POST[$this->nonce_field] ?? null;
        $action = $action ?? $this->nonce_action;
        
        if (!$nonce || !wp_verify_nonce($nonce, $action)) {
            hockey_log("Security check failed", 'debug');
            return false;
        }
        return true;
    }
    
    public function handleCheckIn($player_name, $date = null) {
        if (!$this->verify_nonce()) {
            return 'Security check failed';
        }
        
        $date = $date ?? current_time('Y-m-d');
        return check_in_player($date, $player_name);
    }
    
    public function handleCheckOut($player_name) {
        if (!$this->verify_nonce()) {
            return 'Security check failed';
        }
        
        check_out_player($player_name);
        return "{$player_name} has been checked out.";
    }
} 