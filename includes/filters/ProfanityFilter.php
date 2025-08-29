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
        
        foreach ($this->blacklist as $word) {
            // Check for word boundaries using regex - this prevents false positives
            // like "David Chiasson" being blocked because it contains "ass"
            $pattern = '/\b' . preg_quote($word, '/') . '\b/i';
            if (preg_match($pattern, $text)) {
                hockey_log("Profanity detected (word boundary match): {$word}", 'warning');
                return true;
            }
            
            // Check for l33t speak variations with word boundaries
            // This prevents false positives while still catching intentional l33t speak
            $l33t_word = str_replace(
                ['a', 'e', 'i', 'o', 's'],
                ['@', '3', '1', '0', '$'],
                $word
            );
            $l33t_pattern = '/\b' . preg_quote($l33t_word, '/') . '\b/i';
            if (preg_match($l33t_pattern, $text)) {
                hockey_log("Profanity detected (l33t speak with boundaries): {$word}", 'warning');
                return true;
            }
            
            // Check for common misspellings and variations with word boundaries
            $variations = [
                str_replace('s', '$', $word),  // 'ass' -> 'a$$'
                str_replace('a', '@', $word),  // 'ass' -> '@ss'
                str_replace('i', '1', $word),  // 'shit' -> 'sh1t'
                str_replace('e', '3', $word),  // 'fuck' -> 'fu3k'
            ];
            
            foreach ($variations as $variation) {
                if ($variation !== $word) {  // Skip if no change was made
                    $variation_pattern = '/\b' . preg_quote($variation, '/') . '\b/i';
                    if (preg_match($variation_pattern, $text)) {
                        hockey_log("Profanity detected (variation with boundaries): {$word} -> {$variation}", 'warning');
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
}