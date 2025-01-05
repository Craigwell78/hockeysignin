<?php

namespace hockeysignin\Core;

class FormHandler {
    private static $instance = null;
    
    private function __construct() {}
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function handleCheckIn($player_name, $date = null) {
        if (!$this->verifyNonce()) {
            return 'Security check failed';
        }
        
        $date = $date ?? current_time('Y-m-d');
        return check_in_player($date, $player_name);
    }
    
    public function handleCheckOut($player_name) {
        if (!$this->verifyNonce()) {
            return 'Security check failed';
        }
        
        check_out_player($player_name);
        return "{$player_name} has been checked out.";
    }
    
    private function verifyNonce() {
        return isset($_POST['hockeysignin_nonce']) && 
               wp_verify_nonce($_POST['hockeysignin_nonce'], 'hockeysignin_action');
    }
} 