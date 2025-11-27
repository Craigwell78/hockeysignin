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
    
    // Implement log rotation at 2MB instead of 5MB
    $log_size = filesize(HOCKEY_LOG_FILE);
    if ($log_size > 2 * 1024 * 1024) { // 2MB
        // Rotate log file
        $backup_file = HOCKEY_LOG_FILE . '.old';
        if (file_exists($backup_file)) {
            unlink($backup_file);
        }
        rename(HOCKEY_LOG_FILE, $backup_file);
        touch(HOCKEY_LOG_FILE);
        chmod(HOCKEY_LOG_FILE, 0664);
        
        // Log the rotation
        $timestamp = date('d-M-Y H:i:s T');
        $log_message = sprintf(
            "[%s] [HockeySignin][info] Log file rotated (was %s)\n",
            $timestamp,
            size_format($log_size)
        );
        error_log($log_message, 3, HOCKEY_LOG_FILE);
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
    
    // Skip verbose debug messages for routine operations
    $verbose_patterns = [
        'Scheduled waitlist processing for',
        'Scheduled daily cron jobs',
        'Scheduled daily roster creation',
        'Within check-in hours',
        'Today is a skate day',
        'Looking for roster file',
        'Roster file found',
        'Roster file found, displaying content'
    ];
    
    $is_verbose = false;
    foreach ($verbose_patterns as $pattern) {
        if (strpos($message, $pattern) !== false) {
            $is_verbose = true;
            break;
        }
    }
    
    // Only log if it's important, an error/warning, or debug is enabled and not verbose
    $should_log = in_array($caller, $important_events) || 
                  $level === 'error' || 
                  $level === 'warning' || 
                  (defined('WP_DEBUG') && WP_DEBUG && !$is_verbose);
    
    if ($should_log) {
        $log_message = sprintf(
            "[%s] [HockeySignin][%s] %s\n",
            $timestamp,
            $level,
            $message
        );
        
        error_log($log_message, 3, HOCKEY_LOG_FILE);
    }
}
