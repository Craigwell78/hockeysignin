<?php
// Only register the action handlers
add_action('create_daily_roster_files_event', 'create_daily_roster_files');
add_action('move_waitlist_to_roster_event', 'process_waitlist');

// Add manual trigger capability
add_action('admin_init', function() {
    if (!isset($_POST['action'])) return;
    
    if (!current_user_can('manage_options')) {
        wp_die('Sorry, you are not allowed to access this page.');
    }
    
    if ($_POST['action'] === 'trigger_waitlist_processing') {
        hockey_log("Manual trigger received", 'debug');
        process_waitlist();
    }
    
    if ($_POST['action'] === 'undo_waitlist_processing') {
        $current_date = current_time('Y-m-d');
        $day_of_week = date('l', strtotime($current_date));
        $day_directory_map = get_day_directory_map($current_date);
        $day_directory = $day_directory_map[$day_of_week] ?? null;
        
        if (!$day_directory) return;
        
        $formatted_date = date('D_M_j', strtotime($current_date));
        $season = get_current_season($current_date);
        $file_path = realpath(__DIR__ . "/../rosters/") . "/{$season}/{$day_directory}/Pickup_Roster-{$formatted_date}.txt";
        $backup_path = $file_path . '.backup';
        
        if (file_exists($backup_path)) {
            copy($backup_path, $file_path);
            unlink($backup_path);
            hockey_log("Restored roster from backup", 'debug');
        }
    }
});

function create_daily_roster_files() {
    $current_day = current_time('l');
    
    // Get the directory map from WordPress options
    $directory_map = get_option('hockey_directory_map', []);
    
    // Only run on configured game days
    if (!array_key_exists($current_day, $directory_map)) {
        hockey_log("Not a game day ({$current_day}), skipping roster creation", 'debug');
        return;
    }
    
    // Get the next game date
    $next_game_date = get_next_game_date();
    if (!$next_game_date) {
        hockey_log("No next game date found", 'error');
        return;
    }
    
    // Create roster files for the next game
    create_next_game_roster_files($next_game_date);
}

function process_waitlist() {
    $current_day = current_time('l');
    
    // Get the directory map from WordPress options
    $directory_map = get_option('hockey_directory_map', []);
    
    // Only run on configured game days
    if (!array_key_exists($current_day, $directory_map)) {
        hockey_log("Not a game day ({$current_day}), skipping waitlist processing", 'debug');
        return;
    }
    
    // Get the next game date
    $next_game_date = get_next_game_date();
    if (!$next_game_date) {
        hockey_log("No next game date found", 'error');
        return;
    }
    
    // Process waitlist for the next game
    move_waitlist_to_roster($next_game_date);
}