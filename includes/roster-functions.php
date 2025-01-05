<?php
// Functions to manage the hockey roster files

function get_current_season($date = null) {
    if ($date === null) {
        $date = current_time('Y-m-d');
    }
    
    try {
        if (!class_exists('hockeysignin\Core\Config')) {
            throw new Exception("Config class not found");
        }
        
        $config = hockeysignin\Core\Config::getInstance();
        
        $season_folder = $config->getSeasonFolder($date);
        
        if (!$season_folder) {
            throw new Exception("No season folder returned from config");
        }
        
        return $season_folder;
        
    } catch (Exception $e) {
        $year = date('Y', strtotime($date));
        $month_day = date('m-d', strtotime($date));
        
        if ($month_day >= '10-01' || $month_day < '04-01') {
            return "RegularSeason{$year}-" . ($year + 1);
        } elseif ($month_day >= '04-01' && $month_day < '06-01') {
            return "Spring{$year}";
        } else {
            return "Summer{$year}";
        }
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
$day_directory_map = get_day_directory_map($date);
$day_of_week = date('l', strtotime($date));
$day_directory = $day_directory_map[$day_of_week] ?? null;

if (!$day_directory) {
hockey_log("No directory mapping found for date: {$date}", 'error');
return;
}

$formatted_date = date('D_M_j', strtotime($date));
$season = get_current_season($date);
$file_path = realpath(__DIR__ . "/../rosters/") . "/{$season}/{$day_directory}/Pickup_Roster-{$formatted_date}.txt";

if (!file_exists($file_path)) {
$template_path = realpath(__DIR__ . "/../rosters/roster_template.txt");
if (file_exists($template_path)) {
copy($template_path, $file_path);
chmod($file_path, 0664); // Set file permissions to 664
hockey_log("Roster file created: {$file_path}", 'debug');
} else {
hockey_log("Roster template not found: {$template_path}", 'error');
}
}
}

function check_in_player($date, $player_name) {
    global $wpdb;
    
    if (!$date) {
        $date = current_time('Y-m-d');
    }
    
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
        
        hockey_log("Player found: {$player_name}, Active nights: " . print_r($active_nights, true) . ", Current day: {$day_of_week}, Prepaid: " . ($prepaid ? 'yes' : 'no'), 'debug');
        
        return update_roster($date, $player_name, $prepaid, $player->position);
    } else {
        hockey_log("Player not found in database: {$player_name}", 'debug');
        return update_roster($date, $player_name, false);
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

function update_roster($date, $player_name, $prepaid, $position = null) {
global $wpdb;

if (!$date) {
$date = current_time('Y-m-d');
}

$day_directory_map = get_day_directory_map($date);
$day_of_week = date('l', strtotime($date));
$day_directory = $day_directory_map[$day_of_week] ?? null;

if (!$day_directory) {
hockey_log("No directory mapping found for date: {$date}", 'error');
return "No directory mapping found for date: {$date}";
}

$formatted_date = date('D_M_j', strtotime($date));
$season = get_current_season($date);
$file_path = realpath(__DIR__ . "/../rosters/") . "/{$season}/{$day_directory}/Pickup_Roster-{$formatted_date}.txt";

if (!file_exists($file_path)) {
hockey_log("Roster file not found: {$file_path}", 'error');
return "Roster file not found: {$file_path}";
}
$roster = file_get_contents($file_path);

// Check if the player is already on the roster
if (strpos($roster, $player_name) !== false) {
hockey_log("Player already on the roster: {$player_name}", 'error');
return "{$player_name} is already checked in.";
}

$player = $wpdb->get_row($wpdb->prepare("SELECT * FROM wp_participants_database WHERE CONCAT(`first_name`, ' ', `last_name`) = %s", $player_name));

if ($prepaid && $player) {
hockey_log("Player found in database: {$player_name}, Position: {$player->position}", 'debug');

$positions = [];
if ($player->position == 'Goalie') {
$positions = ['Goal:'];
} elseif ($player->position == 'Forward') {
$positions = ['F-'];
} elseif ($player->position == 'Defence') {
$positions = ['D-'];
}

$open_spots = [];
foreach ($positions as $position) {
if (preg_match_all("/^{$position}\s*\n/m", $roster, $matches, PREG_OFFSET_CAPTURE)) {
foreach ($matches[0] as $match) {
$open_spots[] = $match[1];
}
}
}

if (!empty($open_spots)) {
$random_spot = $open_spots[array_rand($open_spots)];
$roster = substr_replace($roster, "{$positions[0]} {$player_name}\n", $random_spot, strlen("{$positions[0]}\n"));
} else {
hockey_log("No empty position found, adding to waitlist: {$player_name}", 'debug');
            
            // Extract current waitlist
            if (preg_match('/WL:\n(.*?)(?=\n\n|$)/s', $roster, $matches)) {
                $waitlist_section = $matches[1];
                $waitlist_entries = array_filter(explode("\n", $waitlist_section));
                
                // Filter out any "WL:" entries that might have been added by mistake
                $waitlist_entries = array_filter($waitlist_entries, function($entry) {
                    return !preg_match('/^WL:/', trim($entry));
                });
                
                // Add new entry
                $waitlist_entries[] = $player_name;
                
                // Renumber all entries
                $numbered_entries = array_map(function($index, $entry) {
                    // Remove any existing numbers and "WL:" if present
                    $entry = preg_replace('/^\d+\.\s*/', '', $entry);
                    $entry = preg_replace('/^WL:\s*/', '', $entry);
                    return ($index + 1) . ". " . trim($entry);
                }, array_keys($waitlist_entries), $waitlist_entries);
                
                // Replace waitlist section in roster
                $new_waitlist = "WL:\n" . implode("\n", $numbered_entries);
                $roster = preg_replace('/WL:.*?(?=\n\n|$)/s', $new_waitlist, $roster);
                
                file_put_contents($file_path, $roster);
                hockey_log("Added {$player_name} to waitlist", 'debug');
                return "{$player_name} has been added to the waitlist.";
            } else {
                // If no waitlist section exists, create one
                $roster .= "\nWL:\n1. {$player_name}";
                file_put_contents($file_path, $roster);
                hockey_log("Created new waitlist section with {$player_name}", 'debug');
                return "{$player_name} has been added to the waitlist.";
            }
        }
    } else {
        hockey_log("Player not prepaid or not found in database, adding to waitlist: {$player_name}", 'debug');
        
        // Extract current waitlist
        if (preg_match('/WL:\n(.*?)(?=\n\n|$)/s', $roster, $matches)) {
            $waitlist_section = $matches[1];
            $waitlist_entries = array_filter(explode("\n", $waitlist_section));
            
            // Filter out any "WL:" entries that might have been added by mistake
            $waitlist_entries = array_filter($waitlist_entries, function($entry) {
                return !preg_match('/^WL:/', trim($entry));
            });
            
            // Add new entry
            $waitlist_entries[] = $player_name;
            
            // Renumber all entries
            $numbered_entries = array_map(function($index, $entry) {
                // Remove any existing numbers and "WL:" if present
                $entry = preg_replace('/^\d+\.\s*/', '', $entry);
                $entry = preg_replace('/^WL:\s*/', '', $entry);
                return ($index + 1) . ". " . trim($entry);
            }, array_keys($waitlist_entries), $waitlist_entries);
            
            // Replace waitlist section in roster
            $new_waitlist = "WL:\n" . implode("\n", $numbered_entries);
            $roster = preg_replace('/WL:.*?(?=\n\n|$)/s', $new_waitlist, $roster);
        } else {
            // If no waitlist section exists, create one
            $roster .= "\nWL:\n1. {$player_name}";
        }
        
        file_put_contents($file_path, $roster);
        hockey_log("Added {$player_name} to waitlist", 'debug');
        return "{$player_name} has been added to the waitlist.";
    }
    
    file_put_contents($file_path, $roster);
    return "{$player_name} has been successfully checked in.";
}

function move_waitlist_to_roster($date) {
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
    
    // Get all empty spots with their positions
    $empty_spots = [];
    if (preg_match_all('/^(F-|D-|Goal:)\s*$/m', $roster, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $index => $match) {
            $empty_spots[] = [
                'position' => $matches[1][$index][0],
                'offset' => $match[1],
                'length' => strlen($match[0])
            ];
        }
    }
    
    hockey_log("Found " . count($empty_spots) . " empty spots", 'debug');
    
    // Extract waitlisted players
    $waitlisted = [];
    if (preg_match('/WL:\n(.*?)(?=\n\n|$)/s', $roster, $matches)) {
        $waitlist_section = $matches[1];
        $waitlist_entries = array_filter(explode("\n", $waitlist_section));
        
        foreach ($waitlist_entries as $entry) {
            if (preg_match('/^\d+\.\s*(.*)$/', $entry, $matches)) {
                $waitlisted[] = trim($matches[1]);
            }
        }
    }
    
    hockey_log("Found " . count($waitlisted) . " waitlisted players", 'debug');
    
    // Process moving players to roster
    $remaining_waitlist = [];
    foreach ($waitlisted as $player) {
        if (!empty($empty_spots)) {
            $spot = array_shift($empty_spots);
            $position = $spot['position'];
            hockey_log("Moving player {$player} to {$position} position", 'debug');
            
            // Replace the empty spot with the player
            $roster = substr_replace(
                $roster, 
                "{$position} {$player}\n", 
                $spot['offset'], 
                $spot['length'] + 1  // +1 for the newline
            );
        } else {
            $remaining_waitlist[] = $player;
        }
    }
    
    // Replace old waitlist with remaining players (numbered)
    $new_waitlist = "WL:\n";
    foreach ($remaining_waitlist as $index => $player) {
        $new_waitlist .= ($index + 1) . ". " . $player . "\n";
    }
    
    // Replace entire waitlist section
    $roster = preg_replace('/WL:.*?(?=\n\n|$)/s', rtrim($new_waitlist), $roster);
    
    file_put_contents($file_path, $roster);
    hockey_log("Waitlist processed. Moved " . (count($waitlisted) - count($remaining_waitlist)) . " players to roster", 'debug');
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