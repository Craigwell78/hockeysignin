<?php

namespace hockeysignin\Core;

class DateOverride {
    private static $instance = null;
    private $overrides;
    
    private function __construct() {
        $this->overrides = get_option('hockey_date_overrides', []);
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get override for a specific date
     */
    public function getOverride($date) {
        $date_key = date('Y-m-d', strtotime($date));
        return isset($this->overrides[$date_key]) ? $this->overrides[$date_key] : null;
    }
    
    /**
     * Check if a date has an override
     */
    public function hasOverride($date) {
        return $this->getOverride($date) !== null;
    }
    
    /**
     * Get the directory structure for a date (considering overrides)
     */
    public function getDirectoryForDate($date) {
        $override = $this->getOverride($date);
        
        if ($override) {
            // For overrides, use the REPLACING day's directory, not the actual day
            // This ensures the roster file goes in the existing directory structure
            $replacing_day = $override['replacing_day'];
            $directory_map = get_option('hockey_directory_map', []);
            
            if (isset($directory_map[$replacing_day])) {
                return $directory_map[$replacing_day];
            }
        }
        
        // Fall back to regular season configuration
        $day_of_week = date('l', strtotime($date));
        $directory_map = get_option('hockey_directory_map', []);
        return isset($directory_map[$day_of_week]) ? $directory_map[$day_of_week] : null;
    }
    
    /**
     * Get the day of week for a date (considering overrides)
     * For overrides, returns the REPLACING day to maintain directory structure
     */
    public function getDayOfWeek($date) {
        $override = $this->getOverride($date);
        
        if ($override) {
            // Return the replacing day to maintain the existing directory structure
            return $override['replacing_day'];
        }
        
        return date('l', strtotime($date));
    }
    
    /**
     * Get the actual day of week for a date (for template selection, etc.)
     */
    public function getActualDayOfWeek($date) {
        $override = $this->getOverride($date);
        
        if ($override) {
            return $override['actual_day'];
        }
        
        return date('l', strtotime($date));
    }
    
    /**
     * Get all active overrides
     */
    public function getAllOverrides() {
        return $this->overrides;
    }
    
    /**
     * Add a new override
     */
    public function addOverride($original_date, $replacing_day, $actual_date, $actual_day, $actual_time, $actual_venue, $custom_waitlist_time = null) {
        $override = [
            'original_date' => $original_date,
            'replacing_day' => $replacing_day,
            'actual_date' => $actual_date,
            'actual_day' => $actual_day,
            'actual_time' => $actual_time,
            'actual_venue' => $actual_venue,
            'custom_waitlist_time' => $custom_waitlist_time,
            'created_at' => current_time('mysql')
        ];
        
        $this->overrides[$actual_date] = $override;
        update_option('hockey_date_overrides', $this->overrides);
        
        $waitlist_info = $custom_waitlist_time ? " with custom waitlist time {$custom_waitlist_time}" : "";
        hockey_log("Date override added: {$replacing_day} {$original_date} → {$actual_day} {$actual_date} at {$actual_time} at {$actual_venue}{$waitlist_info}", 'info');
        
        return true;
    }
    
    /**
     * Remove an override
     */
    public function removeOverride($date) {
        if (isset($this->overrides[$date])) {
            $override = $this->overrides[$date];
            unset($this->overrides[$date]);
            update_option('hockey_date_overrides', $this->overrides);
            
            hockey_log("Date override removed: {$override['replacing_day']} {$override['original_date']} → {$override['actual_day']} {$override['actual_date']}", 'info');
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Clean up expired overrides (older than 30 days)
     */
    public function cleanupExpiredOverrides() {
        $cutoff_date = date('Y-m-d', strtotime('-30 days'));
        $cleaned = 0;
        
        foreach ($this->overrides as $date => $override) {
            if ($date < $cutoff_date) {
                unset($this->overrides[$date]);
                $cleaned++;
            }
        }
        
        if ($cleaned > 0) {
            update_option('hockey_date_overrides', $this->overrides);
            hockey_log("Cleaned up {$cleaned} expired date overrides", 'debug');
        }
        
        return $cleaned;
    }
}
