<?php
require_once __DIR__ . '/filters/ProfanityFilter.php';

// Functions to manage the hockey roster files

function get_current_season($date = null) {
    if ($date === null) {
        $date = current_time('Y-m-d');
    }
    
    try {
        $year = date('Y', strtotime($date));
        $month_day = date('m-d', strtotime($date));
        
        if ($month_day >= '10-01') {
            return "RegularSeason{$year}-" . ($year + 1);
        } elseif ($month_day < '04-01') {
            return "RegularSeason" . ($year - 1) . "-{$year}";
        } elseif ($month_day >= '04-01' && $month_day < '06-01') {
            return "Spring{$year}";
        } else {
            return "Summer{$year}";
        }
    } catch (Exception $e) {
        hockey_log("Error determining season: " . $e->getMessage(), 'error');
        return null;
    }
}

function get_day_directory_map($date) {
    $day_of_week = date('l', strtotime($date));
    $directory = \hockeysignin\Core\SeasonConfig::getInstance()->getDayDirectory($date);
    
    if (!$directory) {
        hockey_log("No directory mapping found for {$day_of_week} on {$date}", 'error');
        return [];
    }
    
    return [
        $day_of_week => $directory
    ];
}

function calculate_next_game_day() {
    return \hockeysignin\Core\GameSchedule::getInstance()->getNextGameDate();
}

function is_game_day($date = null) {
    if (!$date) {
        $date = current_time('Y-m-d');
    }
    return \hockeysignin\Core\GameSchedule::getInstance()->isGameDay($date);
}

function create_next_game_roster_files($date) {
    $day_of_week = date('l', strtotime($date));
    
    // Choose template based on day
    $template_file = ($day_of_week === 'Friday') 
        ? 'roster_template_friday.txt' 
        : 'roster_template.txt';
    
    $template_path = realpath(__DIR__ . "/../rosters/") . "/{$template_file}";
    
    $day_directory_map = get_day_directory_map($date);
    $day_directory = $day_directory_map[$day_of_week] ?? null;

    if (!$day_directory) {
        hockey_log("No directory mapping found for date: {$date}", 'error');
        return;
    }

    $formatted_date = date('D_M_j', strtotime($date));
    $season = get_current_season($date);
    $file_path = realpath(__DIR__ . "/../rosters/") . "/{$season}/{$day_directory}/Pickup_Roster-{$formatted_date}.txt";

    if (!file_exists($file_path)) {
        if (file_exists($template_path)) {
            // Create directory if it doesn't exist
            $dir = dirname($file_path);
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }
            
            // Copy template to new file
            copy($template_path, $file_path);
            chmod($file_path, 0664); // Set file permissions to 664
            hockey_log("Roster file created: {$file_path}", 'debug');
        } else {
            hockey_log("Roster template not found: {$template_path}", 'error');
        }
    }
}

function check_in_player($date, $player_name) {
    hockey_log("Starting check-in process for player: {$player_name}", 'debug');
    
    // Check for profanity first
    $filter = \hockeysignin\filters\ProfanityFilter::getInstance();
    if ($filter->containsProfanity($player_name)) {
        hockey_log("Check-in rejected - inappropriate content: {$player_name}", 'warning');
        return "Check-in failed: Please use appropriate language.";
    }
    
    if (!$date) {
        $date = current_time('Y-m-d');
    }
    
    // Check if player is already checked in
    $day_directory_map = get_day_directory_map($date);
    $day_of_week = date('l', strtotime($date));
    $day_directory = $day_directory_map[$day_of_week] ?? null;
    $formatted_date = date('D_M_j', strtotime($date));
    $season = get_current_season($date);
    $file_path = realpath(__DIR__ . "/../rosters/") . "/{$season}/{$day_directory}/Pickup_Roster-{$formatted_date}.txt";
    
    hockey_log("Checking roster file: {$file_path}", 'debug');
    
    if (file_exists($file_path)) {
        $roster = file_get_contents($file_path);
        if (strpos($roster, $player_name) !== false) {
            hockey_log("Player {$player_name} is already checked in", 'debug');
            return "already_checked_in";
        }
    }
    
    global $wpdb;
    
    $day_of_week = date('l', strtotime($date));
    
    // Look up the player in the database
    $player = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM wp_participants_database WHERE CONCAT(`first_name`, ' ', `last_name`) = %s",
        $player_name
    ));
    
    // If player exists in database
    if ($player) {
        // Unserialize the active_nights array
        $active_nights = maybe_unserialize($player->active_nights);
        
        // Check if they're registered for this day
        $prepaid = is_array($active_nights) && in_array($day_of_week, $active_nights);
        
        hockey_log("Player {$player_name} prepaid status: " . ($prepaid ? 'yes' : 'no'), 'debug');
        
        // If player is not registered for this day, treat like non-database player
        if (!$prepaid) {
            hockey_log("Player {$player_name} is not registered for {$day_of_week}", 'debug');
            return update_roster($date, $player_name, false, null, true);  // Force to waitlist
        }
        
        // If player is a goalie, only allow them in goalie spots
        if (strtolower($player->position) === 'goalie') {
            hockey_log("Player {$player_name} is a goalie", 'debug');
            return update_roster($date, $player_name, true, 'Goalie', false);
        }
        
        return update_roster($date, $player_name, $prepaid, $player->position);
    } else {
        hockey_log("Player not found in database: {$player_name}", 'debug');
        return update_roster($date, $player_name, false, null, true);
    }
}

function check_out_player($player_name) {
$date = current_time('Y-m-d');
$day_directory_map = get_day_directory_map($date);
$day_of_week = date('l', strtotime($date));
$day_directory = $day_directory_map[$day_of_week] ?? null;
$formatted_date = date('D_M_j', strtotime($date));
$season = get_current_season($date);
$file_path = realpath(__DIR__ . "/../rosters/") . "/{$season}/{$day_directory}/Pickup_Roster-{$formatted_date}.txt";

if (file_exists($file_path)) {
$roster = file_get_contents($file_path);
$roster_lines = explode("\n", $roster);
$updated_roster = [];

foreach ($roster_lines as $line) {
// Check if the line contains the player's name
if (strpos($line, $player_name) !== false) {
// Remove only the player's name, keep the position marker
$line = preg_replace('/\b' . preg_quote($player_name, '/') . '\b/', '', $line);
$line = trim($line); // Remove any extra spaces
}
$updated_roster[] = $line;
}

file_put_contents($file_path, implode("\n", $updated_roster));
hockey_log("Player checked out: {$player_name}", 'debug');
} else {
hockey_log("Roster file not found: {$file_path}", 'error');
}
}

function update_roster($date, $player_name, $prepaid, $preferred_position = null, $forceWaitlist = false) {
    // Check for profanity first, before any roster operations
    $filter = \hockeysignin\Filters\ProfanityFilter::getInstance();
    if ($filter->containsProfanity($player_name)) {
        hockey_log("Roster update rejected - inappropriate content: {$player_name}", 'warning');
        return "Check-in failed: Please use appropriate language.";
    }
    
    // Get roster file path and contents
    $day_of_week = date('l', strtotime($date));
    $day_directory_map = get_day_directory_map($date);
    $day_directory = $day_directory_map[$day_of_week] ?? null;
    $formatted_date = date('D_M_j', strtotime($date));
    $season = get_current_season($date);
    $file_path = realpath(__DIR__ . "/../rosters/") . "/{$season}/{$day_directory}/Pickup_Roster-{$formatted_date}.txt";
    
    if (!file_exists($file_path)) {
        hockey_log("Roster file not found: {$file_path}", 'error');
        return "Error: Roster file not found.";
    }
    
    $roster = file_get_contents($file_path);
    $lines = explode("\n", $roster);
    
    // Get roster sections
    $sections = get_roster_sections($lines, $day_of_week);
    
    if (!$sections) {
        hockey_log("Error: Could not determine roster sections", 'error');
        return "Error: Could not process roster.";
    }

    // Handle non-database players (forceWaitlist) first
    if ($forceWaitlist) {
        $waitlist_start = $sections['waitlist']['start'];
        $waitlist_count = 0;
        for ($i = $waitlist_start; $i < count($lines); $i++) {
            if (preg_match('/^\d+\./', $lines[$i])) {
                $waitlist_count++;
            }
        }
        
        $lines[] = ($waitlist_count + 1) . ". " . $player_name;
        hockey_log("Non-database player {$player_name} added to waitlist at position " . ($waitlist_count + 1), 'debug');
        
        file_put_contents($file_path, implode("\n", $lines));
        return "Thank you! You've been added to our waitlist for tonight. Please check back at 6pm to see if you have made the roster! You can reach us at halifaxpickuphockey@gmail.com to ask about Regular subscriber spots!";
    }

    // Find an available spot
    $spot = find_available_spot($lines, $preferred_position);
    
    if ($spot) {
        // Player can be added to roster
        $position = $spot['position'];
        $player_with_mark = $prepaid ? $player_name : $player_name . "*";
        
        if ($position === 'Goal') {
            // For goalies, append to the Goal: line with a space
            $replacement = "Goal: " . $player_with_mark;
        } else {
            $replacement = "{$position}- {$player_with_mark}";
        }
        
        $lines[$spot['line_number']] = $replacement;
        
        // Log the assignment (keep detailed logging but simplify user message)
        if ($day_of_week === 'Friday') {
            hockey_log("Player {$player_name} assigned to {$spot['rink']} rink at position {$position}", 'debug');
        } else {
            hockey_log("Player {$player_name} assigned to position {$position}", 'debug');
        }
        
        file_put_contents($file_path, implode("\n", $lines));
        return "You have been added to the roster. Please check back after 6pm for finalized teams.";
    } else {
        // Add to waitlist
        $waitlist_start = $sections['waitlist']['start'];
        
        // Get current waitlist numbers
        $waitlist_count = 0;
        for ($i = $waitlist_start; $i < count($lines); $i++) {
            if (preg_match('/^\d+\./', $lines[$i])) {
                $waitlist_count++;
            }
        }
        
        // Add player to waitlist
        $lines[] = ($waitlist_count + 1) . ". " . $player_name;
        hockey_log("Player {$player_name} added to waitlist at position " . ($waitlist_count + 1), 'debug');
        
        file_put_contents($file_path, implode("\n", $lines));
        return "Thank you! You've been added to our waitlist for tonight. Please check back at 6pm to see if you have made the roster! You can reach us at halifaxpickuphockey@gmail.com to ask about Regular subscriber spots!";
    }
}

function move_waitlist_to_roster($date) {
    $day_directory_map = get_day_directory_map($date);
    $day_of_week = date('l', strtotime($date));
    $day_directory = $day_directory_map[$day_of_week] ?? null;
    
    if (!$day_directory) {
        hockey_log("No directory mapping found for {$day_of_week} on {$date}", 'error');
        return;
    }
    
    $formatted_date = date('D_M_j', strtotime($date));
    $season = get_current_season($date);
    $file_path = realpath(__DIR__ . "/../rosters/") . "/{$season}/{$day_directory}/Pickup_Roster-{$formatted_date}.txt";
    
    if (!file_exists($file_path)) {
        hockey_log("Roster file not found: {$file_path}", 'error');
        return;
    }
    
    $roster = file_get_contents($file_path);
    $lines = explode("\n", $roster);
    
    // Get roster sections
    $sections = get_roster_sections($lines, $day_of_week);
    
    if (!$sections) {
        hockey_log("Error: Could not determine roster sections", 'error');
        return;
    }
    
    // Extract waitlisted players
    $waitlisted = [];
    $waitlist_section = array_slice($lines, $sections['waitlist']['start'], 
        $sections['waitlist']['end'] - $sections['waitlist']['start']);
    
    foreach ($waitlist_section as $line) {
        if (preg_match('/^\d+\.\s*(.*)$/', $line, $matches)) {
            $player = trim($matches[1]);
            if (!empty($player)) {
                $waitlisted[] = $player;
            }
        }
    }
    
    hockey_log("Processing waitlist with " . count($waitlisted) . " players", 'debug');
    
    // Process moving players to roster
    $remaining_waitlist = [];
    foreach ($waitlisted as $player) {
        $spot = find_available_spot($lines);
        if ($spot) {
            $position = $spot['position'];
            $player_with_asterisk = $player . "*";
            $replacement = "{$position}- {$player_with_asterisk}";
            $lines[$spot['line_number']] = $replacement;
            
            // Log assignment with rink information for Fridays
            if ($day_of_week === 'Friday') {
                hockey_log("Waitlist movement: {$player} moved to {$spot['rink']} rink at position {$position}", 'debug');
            } else {
                hockey_log("Waitlist movement: {$player} moved to position {$position}", 'debug');
            }
        } else {
            if (!empty($player)) {
                $remaining_waitlist[] = $player;
                hockey_log("Waitlist movement: {$player} remains on waitlist", 'debug');
            }
        }
    }
    
    // Rebuild waitlist section
    $lines = array_slice($lines, 0, $sections['waitlist']['start'] + 1); // Keep everything up to WL: line
    
    // Add remaining waitlisted players with new numbers
    foreach ($remaining_waitlist as $index => $player) {
        $lines[] = ($index + 1) . ". " . $player;
    }
    
    // Add final newline
    $lines[] = "";
    
    // Save updated roster
    file_put_contents($file_path, implode("\n", $lines));
    
    hockey_log("Waitlist processing complete. " . 
        (count($waitlisted) - count($remaining_waitlist)) . " players moved, " . 
        count($remaining_waitlist) . " remaining", 'debug');
}

function finalize_roster_at_930pm($date) {
$day_directory_map = get_day_directory_map($date);
$day_of_week = date_i18n('l', strtotime($date));
$day_directory = $day_directory_map[$day_of_week] ?? null;

if (!$day_directory) {
hockey_log("No directory mapping found for date: {$date}", 'error');
return;
}

$formatted_date = date_i18n('D_M_j', strtotime($date));
$season = get_current_season($date);
$file_path = realpath(__DIR__ . "/../rosters/") . "/{$season}/{$day_directory}/Pickup_Roster-{$formatted_date}.txt";

if (!file_exists($file_path)) {
hockey_log("Roster file not found: {$file_path}", 'error');
return;
}
$roster = file_get_contents($file_path);
$roster .= "\n-- Roster Finalized at 9:30 PM --";

if (file_put_contents($file_path, $roster) === false) {
hockey_log("Unable to finalize roster file: {$file_path}", 'error');
}
}

// Fetch the current roster from the appropriate file
function get_current_roster() {
    $date = current_time('Y-m-d');
    $day_of_week = date('l', strtotime($date));
    $day_directory_map = get_day_directory_map($date);
    $day_directory = $day_directory_map[$day_of_week] ?? null;
    $formatted_date = date('D_M_j', strtotime($date));
    $season = get_current_season($date);
    
    $file_path = realpath(__DIR__ . "/../rosters/") . "/{$season}/{$day_directory}/Pickup_Roster-{$formatted_date}.txt";
    
    if (file_exists($file_path)) {
        return file_get_contents($file_path);
    }
    return false;
}

// Display the roster or the next scheduled skate date
function display_roster($date = null) {
    // If sign-in is off, return empty string since admin handles the custom message
    if (get_option('hockeysignin_off_state')) {
        return '';
    }
    
    // Get the roster content
    $roster_content = get_current_roster();
    
    if ($roster_content !== false) {
        return nl2br($roster_content); // Convert newlines to <br> tags for HTML display
    }
    
    // Only show schedule info when no roster is available
    $base_message = "Our skates are Tuesday 10:30pm Forum, Thursday 10:30pm Civic, and Friday & Saturday 10:30pm Forum.<br><br>Check in begins at 8:00am for each skate.";
    
    if (get_option('hockeysignin_hide_next_game', '0') !== '1') {
        $next_game_day = calculate_next_game_day();
        $next_game_day_formatted = date_i18n('l, F jS', strtotime($next_game_day));
        $base_message .= "<br><br>The next scheduled skate date is " . $next_game_day_formatted . ".";
    }
    
    return $base_message;
}

function check_in_player_after_6pm($date, $player_name) {
global $wpdb;

$day_of_week = date('l', strtotime($date));
$day_directory_map = get_day_directory_map($date);
$day_directory = $day_directory_map[$day_of_week] ?? null;
$formatted_date = date('D_M_j', strtotime($date));
$season = get_current_season($date);
$file_path = realpath(__DIR__ . "/../rosters/") . "/{$season}/{$day_directory}/Pickup_Roster-{$formatted_date}.txt";

if (file_exists($file_path)) {
$roster = file_get_contents($file_path);

// Check for vacant spots
$positions = ['F -', 'D -', 'Goal:'];
$vacant_spot_found = false;
foreach ($positions as $position) {
if (strpos($roster, "{$position} \n") !== false) {
$roster = preg_replace("/{$position} \n/", "{$position} {$player_name}\n", $roster, 1);
$vacant_spot_found = true;
break;
}
}

if ($vacant_spot_found) {
file_put_contents($file_path, $roster);
return "You have been added to the roster. Please contact HPH admin on FB / Messenger, at payforhockey@hotmail.com or at 902-488-5590 to confirm your spot.";
} else {
return "No vacant spots available. You have been added to the waitlist.";
}
} else {
hockey_log("Roster file not found: {$file_path}", 'error');
return "Roster file not found for today.<br><br>Our skates are Tuesday 10:30pm Forum, Thursday 10:30pm Civic, and Friday & Saturday 10:30pm Forum.<br><br>Check in begins at 8:00am for each skate.";
}
}

function contains_profanity($text) {
    $banned_patterns = [
        '/\bfuck\b/i', '/fuck\w*/i',
        '/\bshit\b/i', '/shit\w*/i',
        '/\bbitch\b/i', '/bitch\w*/i',
        '/\basshole\b/i', '/ass\w*/i',
        '/\bdick\b/i', '/dick\w*/i',
        '/\bcunt\b/i', '/cunt\w*/i',
        '/\bslut\b/i', '/slut\w*/i',
        '/\bwhore\b/i', '/whore\w*/i',
        '/\bfag\b/i', '/fag\w*/i',
        '/\bnigger\b/i', '/nigg\w*/i',
        '/\bchink\b/i', '/chink\w*/i',
        '/\bspic\b/i', '/spic\w*/i',
        '/\bkike\b/i', '/kike\w*/i',
        '/\bgook\b/i', '/gook\w*/i',
        '/\bwop\b/i', '/wop\w*/i',
        '/\bdago\b/i', '/dago\w*/i',
        '/\bwetback\b/i', '/wetback\w*/i',
        // Add more patterns as needed
    ];

    foreach ($banned_patterns as $pattern) {
        if (preg_match($pattern, $text)) {
            return true;
        }
    }
    return false;
}

function log_new_player_info($player_name, $additional_info = '') {
    $timestamp = date('Y-m-d H:i:s');
    
    $log_message = sprintf(
        "[%s] NEW PLAYER: %s%s\n",
        $timestamp,
        $player_name,
        $additional_info ? " | Info: " . $additional_info : ""
    );
    
    $new_players_log = plugin_dir_path(__DIR__) . 'logs/new_players.log';
    error_log($log_message, 3, $new_players_log);
}

function is_empty_position($line, $preferred_position = null) {
    $line = trim($line);
    
    if ($preferred_position) {
        $pattern = '/^' . preg_quote($preferred_position, '/') . '-$/';
        $result = preg_match($pattern, $line);
        if ($result) {
            hockey_log("Found empty position matching preference: {$preferred_position}", 'debug');
        }
        return $result;
    }
    
    $pattern = '/^[FD]-$/';
    $result = preg_match($pattern, $line);
    return $result;
}

function find_available_spot($lines, $preferred_position = null) {
    $day_of_week = date('l');
    $sections = get_roster_sections($lines, $day_of_week);
    
    if (!$sections) {
        hockey_log("Error: Could not determine roster sections", 'error');
        return null;
    }
    
    hockey_log("Searching for spot between lines 0 and " . 
        ($sections['waitlist']['start'] ?? count($lines)) . 
        ($preferred_position ? " (preferred position: {$preferred_position})" : ""), 'debug');
    
    // Special handling for goalies
    if ($preferred_position === 'Goalie' || $preferred_position === 'G') {
        foreach ($lines as $i => $line) {
            if ($sections['waitlist']['start'] && $i >= $sections['waitlist']['start']) {
                break;
            }
            
            $line = rtrim($line);
            
            // Find the Goal: line
            if ($line === 'Goal:') {
                hockey_log("Found goalie position at line {$i}", 'debug');
                return [
                    'line_number' => $i,
                    'position' => 'Goal',
                    'original_line' => $line
                ];
            }
        }
        
        hockey_log("No goalie positions available", 'debug');
        return null;
    }
    
    // For skaters (after goalie check)
    $available_spots = [];
    $any_spots = [];
    
    foreach ($lines as $i => $line) {
        if ($sections['waitlist']['start'] && $i >= $sections['waitlist']['start']) {
            break;
        }
        
        $line = rtrim($line);
        hockey_log("Checking line {$i}: '{$line}'", 'debug');
        
        if (preg_match('/^[FD]-$/', $line)) {
            $position = get_position_from_line($line);
            $spot = [
                'line_number' => $i,
                'position' => $position,
                'original_line' => $line
            ];
            
            hockey_log("Found empty position: {$position} at line {$i}", 'debug');
            
            if ($preferred_position && substr($preferred_position, 0, 1) === $position) {
                $available_spots[] = $spot;
            }
            $any_spots[] = $spot;
        }
    }
    
    // If we found preferred spots, randomly select one
    if (!empty($available_spots)) {
        $spot = $available_spots[array_rand($available_spots)];
        hockey_log("Selected preferred position {$spot['position']} at line {$spot['line_number']}", 'debug');
        return $spot;
    }
    
    // If we found any spots, randomly select one
    if (!empty($any_spots)) {
        $spot = $any_spots[array_rand($any_spots)];
        hockey_log("Selected available position {$spot['position']} at line {$spot['line_number']}", 'debug');
        return $spot;
    }
    
    hockey_log("No available spots found in roster", 'debug');
    return null;
}

// Helper function to count filled positions in a section
function count_filled_positions($lines, $start, $end) {
    $count = 0;
    for ($i = $start; $i < $end; $i++) {
        $line = rtrim($lines[$i]);
        if (preg_match('/^[FD]-\s+\S/', $line)) { // Matches positions that have a player
            $count++;
        }
    }
    return $count;
}

// Helper function to get position from line
function get_position_from_line($line) {
    if (preg_match('/^([FD])-/', $line, $matches)) {
        return $matches[1];
    }
    return null;
}

function get_roster_sections($lines, $day_of_week) {
    if ($day_of_week !== 'Friday') {
        return [
            'main' => [
                'start' => 0,
                'end' => array_search('WL:', $lines)
            ],
            'waitlist' => [
                'start' => array_search('WL:', $lines),
                'end' => count($lines)
            ]
        ];
    }
    
    // For Friday games, find the sections
    $civic_start = 0; // CIVIC section starts at beginning
    $forum_start = array_search('FORUM 10:30PM', $lines);
    $waitlist_start = array_search('WL:', $lines);
    
    if ($forum_start === false || $waitlist_start === false) {
        hockey_log("Error: Could not find all sections in Friday roster", 'error');
        return null;
    }
    
    return [
        'civic' => [
            'start' => $civic_start,
            'end' => $forum_start
        ],
        'forum' => [
            'start' => $forum_start,
            'end' => $waitlist_start
        ],
        'waitlist' => [
            'start' => $waitlist_start,
            'end' => count($lines)
        ]
    ];
}