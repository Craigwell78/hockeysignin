<?php

namespace hockeysignin\filters;

class ProfanityFilter {
    private static $instance = null;
    private $blacklist = [];
    
    private function __construct() {
        $this->blacklist = [
            // Profanity
            'fuck', 'fucking', 'fucker', 'fck', 'fuk',
            'shit', 'sh1t', 'sh!t', 'shame',
            'ass', 'asshole', 'ass hole',
            'bitch', 'b1tch',
            'dick', 'd1ck',
            'cock', 'cunt',
            
            // Racial slurs
            'nigger', 'nigga', 'n1gger', 'n1gga',
            'kike', 'k1ke',
            'chink', 'ch1nk',
            'spic', 'sp1c',
            'wetback',
            'gook',
            
            // Homophobic slurs
            'fag', 'faggot', 'f4gg0t', 'f4g',
            'dyke', 'queer',
            
            // Misogynistic terms
            'whore', 'slut', 'wh0re',
            
            // Common variations
            'stfu', 'gtfo', 'kys',
            'kys', 'kys',
        ];
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function containsProfanity($text) {
        $text = strtolower(trim($text));
        
        // Remove spaces and special characters
        $textNoSpaces = preg_replace('/[\s\-\_\.\,\!\@\#\$]+/', '', $text);
        
        foreach ($this->blacklist as $word) {
            // Check original text with spaces
            if (strpos($text, $word) !== false) {
                hockey_log("Profanity detected (direct match): {$word}", 'warning');
                return true;
            }
            
            // Check without spaces and special characters
            $wordNoSpaces = preg_replace('/[\s\-\_\.\,\!\@\#\$]+/', '', $word);
            if (strpos($textNoSpaces, $wordNoSpaces) !== false) {
                hockey_log("Profanity detected (normalized): {$word}", 'warning');
                return true;
            }
            
            // Check for l33t speak variations
            $l33t = str_replace(
                ['a', 'e', 'i', 'o', 's'],
                ['@', '3', '1', '0', '$'],
                $wordNoSpaces
            );
            if (strpos($textNoSpaces, $l33t) !== false) {
                hockey_log("Profanity detected (l33t speak): {$word}", 'warning');
                return true;
            }
        }
        
        return false;
    }
}