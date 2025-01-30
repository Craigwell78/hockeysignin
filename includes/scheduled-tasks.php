<?php
// Only register the action handlers
add_action('create_daily_roster_files_event', 'create_daily_roster_files');
add_action('move_waitlist_to_roster_event', 'process_waitlist_at_6pm');

// Add manual trigger capability
add_action('admin_post_trigger_waitlist_processing', function() {
    // Check if user has permission
    if (!current_user_can('manage_options')) {
        wp_die('Sorry, you are not allowed to access this page.');
    }
    
    hockey_log("Manual trigger received", 'debug');
    do_action('move_waitlist_to_roster_event');
    wp_redirect(admin_url('admin.php?page=hockey-settings&processed=1'));
    exit;
});

function create_daily_roster_files() {
    $current_date = current_time('Y-m-d');
    $day_of_week = date('l', strtotime($current_date));
    $local_time = current_time('H:i');
    
    hockey_log("Starting daily roster file creation at {$local_time} for {$current_date} ({$day_of_week})", 'debug');
    
    // Call the existing function to create the roster
    create_next_game_roster_files($current_date);
}

function process_waitlist_at_6pm() {
    hockey_log("Starting waitlist processing job", 'debug');
    
    try {
        $current_date = current_time('Y-m-d');
        $day_of_week = date('l', strtotime($current_date));
        hockey_log("Processing for date: {$current_date} ({$day_of_week})", 'debug');
        
        $day_directory_map = get_day_directory_map($current_date);
        hockey_log("Directory map: " . print_r($day_directory_map, true), 'debug');
        
        $day_directory = $day_directory_map[$day_of_week] ?? null;
        if (!$day_directory) {
            hockey_log("No directory found for {$day_of_week}", 'error');
            return;
        }
        
        $formatted_date = date('D_M_j', strtotime($current_date));
        $season = get_current_season($current_date);
        $file_path = realpath(__DIR__ . "/../rosters/") . "/{$season}/{$day_directory}/Pickup_Roster-{$formatted_date}.txt";
        
        hockey_log("Attempting to process file: {$file_path}", 'debug');
        
        if (file_exists($file_path)) {
            hockey_log("Found roster file, reading contents", 'debug');
            $roster = file_get_contents($file_path);
            $lines = explode("\n", $roster);
            
            $updated_lines = move_waitlist_to_roster($lines, $day_of_week);
            if ($updated_lines) {
                file_put_contents($file_path, implode("\n", $updated_lines));
                hockey_log("Waitlist processing complete and file updated", 'debug');
            }
        } else {
            hockey_log("Roster file not found: {$file_path}", 'error');
        }
    } catch (Exception $e) {
        hockey_log("Error in process_waitlist_at_6pm: " . $e->getMessage(), 'error');
    }
}