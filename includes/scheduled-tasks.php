<?php
// Only register the action handlers
add_action('create_daily_roster_files_event', 'create_daily_roster_files');
add_action('move_waitlist_to_roster_event', 'process_waitlist_at_6pm');

function create_daily_roster_files() {
    $current_date = current_time('Y-m-d');
    $day_of_week = date('l', strtotime($current_date));
    $local_time = current_time('H:i');
    
    hockey_log("Starting daily roster file creation at {$local_time} for {$current_date} ({$day_of_week})", 'debug');
    
    // Call the existing function to create the roster
    create_next_game_roster_files($current_date);
}

function process_waitlist_at_6pm() {
    $current_date = current_time('Y-m-d');
    $day_of_week = date('l', strtotime($current_date));
    $day_directory_map = get_day_directory_map($current_date);
    $day_directory = $day_directory_map[$day_of_week] ?? null;
    $formatted_date = date('D_M_j', strtotime($current_date));
    $season = get_current_season($current_date);
    $file_path = realpath(__DIR__ . "/../rosters/") . "/{$season}/{$day_directory}/Pickup_Roster-{$formatted_date}.txt";
    
    $local_time = current_time('H:i');
    
    hockey_log("Current season: " . $season, 'debug');
    hockey_log("Day directory map: " . print_r($day_directory_map, true), 'debug');
    hockey_log("Processing waitlist at {$local_time} for {$current_date}", 'debug');

    if (file_exists($file_path)) {
        move_waitlist_to_roster($current_date);
        hockey_log("Waitlist processed successfully for {$current_date}", 'debug');
    } else {
        hockey_log("Roster file not found: {$file_path}", 'error');
    }
}