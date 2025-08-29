<?php
function hockey_log($message, $level = 'debug') {
    // Ensure log directory exists
    $logs_dir = dirname(HOCKEY_LOG_FILE);
    if (!file_exists($logs_dir)) {
        wp_mkdir_p($logs_dir);
    }
    
    // Create log file if it doesn't exist
    if (!file_exists(HOCKEY_LOG_FILE)) {
        touch(HOCKEY_LOG_FILE);
        chmod(HOCKEY_LOG_FILE, 0664);
    }
    
    // Monitor log file size (5MB threshold)
    $log_size = filesize(HOCKEY_LOG_FILE);
    if ($log_size > 5 * 1024 * 1024) { // 5MB
        $size_mb = round($log_size / 1024 / 1024, 2);
        error_log("[" . date('d-M-Y H:i:s T') . "] [HockeySignin][warning] Log file size: {$size_mb}MB\n", 3, HOCKEY_LOG_FILE);
    }
    
    static $page_loads = 0;
    
    // Format timestamp
    $timestamp = date('d-M-Y H:i:s T');
    
    // Determine if this is a page load
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $caller = $backtrace[1]['function'] ?? '';
    
    if ($caller === 'require' || $caller === 'require_once') {
        $page_loads++;
        $message = "Page load #{$page_loads}: " . $message;
    }
    
    // Always log these events regardless of debug setting
    $important_events = [
        'handleCheckIn',
        'handleCheckOut',
        'check_prepaid_status',
        'process_waitlist_at_6pm',
        'create_daily_roster_files',
        'move_waitlist_to_roster'
    ];
    
    if (in_array($caller, $important_events) || $level === 'error' || $level === 'warning') {
        $log_message = sprintf(
            "[%s] [HockeySignin][%s] %s\n",
            $timestamp,
            $level,
            $message
        );
        
        error_log($log_message, 3, HOCKEY_LOG_FILE);
    } elseif (defined('WP_DEBUG') && WP_DEBUG) {
        // Only log debug messages when WP_DEBUG is enabled
        $log_message = sprintf(
            "[%s] [HockeySignin][debug] %s\n",
            $timestamp,
            $message
        );
        
        error_log($log_message, 3, HOCKEY_LOG_FILE);
    }
}
