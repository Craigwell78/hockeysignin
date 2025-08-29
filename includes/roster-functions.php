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
    hockey_log("Creating roster files for {$day_of_week} {$date}", 'debug');
    
    // Get the season first to determine which template to use
    $season = get_current_season($date);
    
    // Choose template based on day and season
    if ($day_of_week === 'Friday' && strpos($season, 'Summer') === false) {
        // Use Friday template only for non-Summer seasons
        $template_file = 'roster_template_friday.txt';
    } else {
        // Use regular template for all Summer skates and non-Friday skates
        $template_file = 'roster_template.txt';
    }
    
    // Get the plugin directory path
    $plugin_dir = plugin_dir_path(dirname(__FILE__));
    $template_path = $plugin_dir . "rosters/{$template_file}";
    hockey_log("Template path: {$template_path}", 'debug');
    
    if (!file_exists($template_path)) {
        hockey_log("Template file does not exist: {$template_path}", 'error');
        return;
    }
    
    $day_directory_map = get_day_directory_map($date);
    $day_directory = $day_directory_map[$day_of_week] ?? null;

    if (!$day_directory) {
        hockey_log("No directory mapping found for date: {$date}", 'error');
        return;
    }

    $formatted_date = date('D_M_j', strtotime($date));
    $file_path = $plugin_dir . "rosters/{$season}/{$day_directory}/Pickup_Roster-{$formatted_date}.txt";
    hockey_log("Target roster file path: {$file_path}", 'debug');

    if (!file_exists($file_path)) {
        // Create directory if it doesn't exist
        $dir = dirname($file_path);
        hockey_log("Creating directory: {$dir}", 'debug');
        
        if (!file_exists($dir)) {
            if (!wp_mkdir_p($dir)) {
                hockey_log("Failed to create directory: {$dir}", 'error');
                return;
            }
            // Set directory permissions
            if (!chmod($dir, 0775)) {
                hockey_log("Failed to set directory permissions: {$dir}", 'error');
                return;
            }
        }
        
        // For Friday rosters in non-Summer seasons, update the rink labels
        if ($day_of_week === 'Friday' && strpos($season, 'Summer') === false) {
            $template_content = file_get_contents($template_path);
            $fast_rink = get_fast_skate_rink($date);
            hockey_log("Friday skate - Fast rink: {$fast_rink}", 'debug');
            
            // Replace literal \n with actual newlines
            $template_content = str_replace('\\n', "\n", $template_content);
            
            // Update Civic rink label - set to 11pm for Spring session
            if (strpos($season, 'Spring') !== false) {
                if ($fast_rink === 'CIVIC') {
                    $template_content = preg_replace(
                        '/CIVIC 10:30PM\n\[FAST\/BEGINNER-RUSTY\] SKATE/',
                        'CIVIC 11:00PM\nFAST SKATE',
                        $template_content
                    );
                    $template_content = preg_replace(
                        '/FORUM 10:30PM\n\[FAST\/BEGINNER-RUSTY\] SKATE/',
                        'FORUM 10:30PM\nBEGINNER/RUSTY SKATE',
                        $template_content
                    );
                } else {
                    $template_content = preg_replace(
                        '/CIVIC 10:30PM\n\[FAST\/BEGINNER-RUSTY\] SKATE/',
                        'CIVIC 11:00PM\nBEGINNER/RUSTY SKATE',
                        $template_content
                    );
                    $template_content = preg_replace(
                        '/FORUM 10:30PM\n\[FAST\/BEGINNER-RUSTY\] SKATE/',
                        'FORUM 10:30PM\nFAST SKATE',
                        $template_content
                    );
                }
            } else {
                // Regular season handling (10:30pm)
                if ($fast_rink === 'CIVIC') {
                    $template_content = preg_replace(
                        '/CIVIC 10:30PM\n\[FAST\/BEGINNER-RUSTY\] SKATE/',
                        'CIVIC 10:30PM\nFAST SKATE',
                        $template_content
                    );
                    $template_content = preg_replace(
                        '/FORUM 10:30PM\n\[FAST\/BEGINNER-RUSTY\] SKATE/',
                        'FORUM 10:30PM\nBEGINNER/RUSTY SKATE',
                        $template_content
                    );
                } else {
                    $template_content = preg_replace(
                        '/CIVIC 10:30PM\n\[FAST\/BEGINNER-RUSTY\] SKATE/',
                        'CIVIC 10:30PM\nBEGINNER/RUSTY SKATE',
                        $template_content
                    );
                    $template_content = preg_replace(
                        '/FORUM 10:30PM\n\[FAST\/BEGINNER-RUSTY\] SKATE/',
                        'FORUM 10:30PM\nFAST SKATE',
                        $template_content
                    );
                }
            }
            
            // Write the updated content to the new file
            if (file_put_contents($file_path, $template_content) === false) {
                hockey_log("Failed to write Friday roster file: {$file_path}", 'error');
                return;
            }
        } else {
            // For Summer Fridays and non-Friday rosters
            $template_content = file_get_contents($template_path);
            
            // Add FORUM 10:30PM header for summer Friday rosters
            if ($day_of_week === 'Friday' && strpos($season, 'Summer') !== false) {
                $template_content = "FORUM 10:30PM\n\n" . $template_content;
            }
            
            // Ensure the file ends with a newline
            if (substr($template_content, -1) !== "\n") {
                $template_content .= "\n";
            }
            
            if (file_put_contents($file_path, $template_content) === false) {
                hockey_log("Failed to write roster file: {$file_path}", 'error');
                return;
            }
        }
        
        // Set file permissions
        if (!chmod($file_path, 0664)) {
            hockey_log("Failed to set permissions on roster file: {$file_path}", 'error');
            return;
        }
        
        hockey_log("Roster file created successfully: {$file_path}", 'debug');
    } else {
        hockey_log("Roster file already exists: {$file_path}", 'debug');
    }
}

function check_in_player($date, $player_name, $skate_preference = null) {
    hockey_log("Starting check-in process for player: {$player_name}", 'debug');
    
    // Capitalize the player name
    $player_name = capitalize_player_name($player_name);
    
    // Check for profanity first
    $filter = \hockeysignin\filters\ProfanityFilter::getInstance();
    if ($filter->containsProfanity($player_name)) {
        hockey_log("Check-in rejected - inappropriate content: {$player_name}", 'warning');
        return "Check-in failed: Please use appropriate language.";
    }
    
    if (!$date) {
        $date = current_time('Y-m-d');
    }
    
    $day_of_week = date('l', strtotime($date));
    $season = get_current_season($date);
    hockey_log("Processing check-in for {$day_of_week} during {$season}", 'debug');
    
    // For Friday skates, validate skate preference only for non-Summer seasons
    if ($day_of_week === 'Friday' && strpos($season, 'Summer') === false && !$skate_preference) {
        hockey_log("Check-in rejected - missing skate preference for Friday skate during {$season}", 'warning');
        return "Check-in failed: Please select a skate preference for Friday skates.";
    }
    
    // Get roster file path
    $day_directory_map = get_day_directory_map($date);
    $day_directory = $day_directory_map[$day_of_week] ?? null;
    $formatted_date = date('D_M_j', strtotime($date));
    $file_path = plugin_dir_path(dirname(__FILE__)) . "rosters/{$season}/{$day_directory}/Pickup_Roster-{$formatted_date}.txt";
    
    hockey_log("Checking roster file: {$file_path}", 'debug');
    
    // Ensure roster file exists
    if (!file_exists($file_path)) {
        hockey_log("Roster file does not exist, creating it now", 'debug');
        create_next_game_roster_files($date);
        
        // Verify the file was created
        if (!file_exists($file_path)) {
            hockey_log("Failed to create roster file: {$file_path}", 'error');
            return "Error: Could not create roster file.";
        }
    }
    
    // Check if player is already checked in
    $roster = file_get_contents($file_path);
    if (strpos($roster, $player_name) !== false) {
        hockey_log("Player {$player_name} is already checked in", 'debug');
        return "already_checked_in";
    }
    
    global $wpdb;
    
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
            return update_roster($date, $player_name, false, null, true, $skate_preference);  // Force to waitlist
        }
        
        // If player is a goalie, only allow them in goalie spots
        if (strtolower($player->position) === 'goalie') {
            hockey_log("Player {$player_name} is a goalie", 'debug');
            return update_roster($date, $player_name, true, 'Goalie', false, $skate_preference);
        }
        
        return update_roster($date, $player_name, $prepaid, $player->position, false, $skate_preference);
    } else {
        hockey_log("Player not found in database: {$player_name}", 'debug');
        return update_roster($date, $player_name, false, null, true, $skate_preference);
    }
}

function check_out_player($player_name) {
    hockey_log("Processing checkout for player: {$player_name}", 'debug');
    
    $current_date = current_time('Y-m-d');
    $day_directory_map = get_day_directory_map($current_date);
    $day_of_week = date('l', strtotime($current_date));
    $day_directory = $day_directory_map[$day_of_week] ?? null;
    $formatted_date = date('D_M_j', strtotime($current_date));
    $season = get_current_season($current_date);
    $file_path = plugin_dir_path(dirname(__FILE__)) . "rosters/{$season}/{$day_directory}/Pickup_Roster-{$formatted_date}.txt";
    
    if (!file_exists($file_path)) {
        return false;
    }
    
    $lines = file($file_path, FILE_IGNORE_NEW_LINES);
    $sections = get_roster_sections($lines, $day_of_week);
    $player_found = false;
    
    // First check regular roster spots
    foreach ($lines as $i => $line) {
        if (stripos($line, $player_name) !== false && !preg_match('/^WL:|^\d+\./', $line)) {
            // Clean up the line, removing player name and any extra hyphens
            $lines[$i] = preg_replace('/([FDG](?:oal)?(?:-|:))\s*[^-]*/', '$1', $line);
            // Remove any double hyphens that might have been created
            $lines[$i] = preg_replace('/-+/', '-', $lines[$i]);
            $player_found = true;
            break;
        }
    }
    
    // If not found in regular spots, check waitlist
    if (!$player_found && isset($sections['waitlist']['start'])) {
        $waitlist_start = $sections['waitlist']['start'];
        
        // Find player in waitlist
        for ($i = $waitlist_start + 1; $i < count($lines); $i++) {
            if (stripos($lines[$i], $player_name) !== false) {
                // Remove this line
                array_splice($lines, $i, 1);
                $player_found = true;
                
                // Renumber remaining waitlist entries
                $count = 1;
                for ($j = $waitlist_start + 1; $j < count($lines); $j++) {
                    if (preg_match('/^\d+\./', $lines[$j])) {
                        $player = preg_replace('/^\d+\.\s*/', '', $lines[$j]);
                        $lines[$j] = $count . ". " . $player;
                        $count++;
                    }
                }
                break;
            }
        }
    }
    
    if ($player_found) {
        file_put_contents($file_path, implode("\n", $lines));
        hockey_log("Player checked out successfully: {$player_name}", 'debug');
        return true;
    }
    
    hockey_log("Player not found for checkout: {$player_name}", 'debug');
    return false;
}

function update_roster($date, $player_name, $prepaid, $preferred_position = null, $forceWaitlist = false, $skate_preference = null) {
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
    $file_path = plugin_dir_path(dirname(__FILE__)) . "rosters/{$season}/{$day_directory}/Pickup_Roster-{$formatted_date}.txt";
    
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
        if (!isset($sections['waitlist']['start'])) {
            hockey_log("Error: Waitlist section not found in roster", 'error');
            return "Error: Could not process roster.";
        }
        
        $waitlist_start = $sections['waitlist']['start'];
        $waitlist_end = $sections['waitlist']['end'] ?? count($lines);
        $waitlist_count = 0;
        
        // Get current waitlist entries
        $waitlist_lines = array_slice($lines, $waitlist_start + 1, $waitlist_end - $waitlist_start - 1);
        foreach ($waitlist_lines as $line) {
            if (preg_match('/^\d+\./', $line)) {
                $waitlist_count++;
            }
        }
        
        // Add preference to player name for waitlist
        $preference_suffix = '';
        if ($day_of_week === 'Friday' && $skate_preference) {
            $preference_suffix = " ({$skate_preference})";
        }
        
        // Add new entry after existing entries
        array_splice($lines, $waitlist_start + $waitlist_count + 1, 0, [($waitlist_count + 1) . ". " . $player_name . $preference_suffix]);
        hockey_log("Non-database player {$player_name} added to waitlist at position " . ($waitlist_count + 1), 'debug');
        
        if (@file_put_contents($file_path, implode("\n", $lines)) === false) {
            hockey_log("Failed to write to roster file", 'error');
            return "Error: Could not update roster file.";
        }
        
        return "Thank you! You've been added to our waitlist for tonight. Please check back at 6pm to see if you have made the roster! You can reach us at halifaxpickuphockey@gmail.com to ask about Regular subscriber spots!";
    }

    // For Friday skates, determine which rink to place the player on
    $target_rink = null;
    if ($day_of_week === 'Friday' && $skate_preference) {
        $fast_rink = get_fast_skate_rink($date);
        if ($skate_preference === 'fast') {
            $target_rink = $fast_rink;
        } elseif ($skate_preference === 'beginner') {
            $target_rink = ($fast_rink === 'FORUM') ? 'CIVIC' : 'FORUM';
        }
        hockey_log("Friday skate - Target rink determined: {$target_rink} for {$skate_preference} skate", 'debug');
    }
    
    // Find an available spot
    $spot = find_available_spot($lines, $preferred_position, $target_rink);
    
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
        
        // Log the assignment
        if ($day_of_week === 'Friday') {
            hockey_log("Player {$player_name} assigned to {$spot['rink']} rink at position {$position}", 'debug');
        } else {
            hockey_log("Player {$player_name} assigned to position {$position}", 'debug');
        }
        
        if (@file_put_contents($file_path, implode("\n", $lines)) === false) {
            hockey_log("Failed to write to roster file", 'error');
            return "Error: Could not update roster file.";
        }
        
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
        
        // Add preference to player name for waitlist
        $preference_suffix = '';
        if ($day_of_week === 'Friday' && $skate_preference) {
            $preference_suffix = " ({$skate_preference})";
        }
        
        // Add player to waitlist
        $lines[] = ($waitlist_count + 1) . ". " . $player_name . $preference_suffix;
        hockey_log("Player {$player_name} added to waitlist at position " . ($waitlist_count + 1), 'debug');
        
        if (@file_put_contents($file_path, implode("\n", $lines)) === false) {
            hockey_log("Failed to write to roster file", 'error');
            return "Error: Could not update roster file.";
        }
        
        return "Thank you! You've been added to our waitlist for tonight. Please check back at 6pm to see if you have made the roster! You can reach us at halifaxpickuphockey@gmail.com to ask about Regular subscriber spots!";
    }
}

function move_waitlist_to_roster($lines, $day_of_week) {
    $sections = get_roster_sections($lines, $day_of_week);
    if (!$sections) {
        hockey_log("Error: Could not determine roster sections", 'error');
        return null;
    }
    
    // Debug sections
    hockey_log("Sections found: " . print_r($sections, true), 'debug');
    
    // Extract waitlist section
    $waitlist_section = array_slice($lines, 
        $sections['waitlist']['start'] + 1,
        $sections['waitlist']['end'] - $sections['waitlist']['start']);
    
    // Debug waitlist section
    hockey_log("Waitlist section: " . print_r($waitlist_section, true), 'debug');
    
    $waitlisted = [];
    foreach ($waitlist_section as $line) {
        if (preg_match('/^\d+\.\s*(.*)$/', $line, $matches)) {
            $player = trim($matches[1]);
            if (!empty($player)) {
                $waitlisted[] = $player;
                hockey_log("Added player to waitlist array: {$player}", 'debug');
            }
        }
    }
    
    hockey_log("Processing waitlist with " . count($waitlisted) . " players", 'debug');
    
    // Process moving players to roster
    $remaining_waitlist = [];
    foreach ($waitlisted as $player) {
        hockey_log("Looking for spot for player: {$player}", 'debug');
        $spot = find_available_spot($lines);
        hockey_log("Spot found: " . ($spot ? "Yes at line " . $spot['line_number'] : "No"), 'debug');
        
        if ($spot) {
            $position = $spot['position'];
            $player_with_mark = $player . "* *confirming*";
            
            if ($position === 'Goal') {
                $replacement = "Goal: " . $player_with_mark;
            } else {
                $replacement = "{$position}- " . $player_with_mark;
            }
            
            hockey_log("Before replacement at line {$spot['line_number']}: {$lines[$spot['line_number']]}", 'debug');
            $lines[$spot['line_number']] = $replacement;
            hockey_log("After replacement at line {$spot['line_number']}: {$lines[$spot['line_number']]}", 'debug');
            
            hockey_log("Waitlist movement: {$player} moved to position {$position}", 'debug');
        } else {
            $remaining_waitlist[] = $player;
            hockey_log("No spot found for {$player}, adding to remaining waitlist", 'debug');
        }
    }
    
    // Debug final lines before waitlist rebuild
    hockey_log("Lines before waitlist rebuild: " . print_r($lines, true), 'debug');
    
    // Rebuild waitlist section
    $lines = array_slice($lines, 0, $sections['waitlist']['start'] + 1);
    foreach ($remaining_waitlist as $i => $player) {
        $lines[] = ($i + 1) . ". " . $player;
    }
    
    hockey_log("Waitlist processing complete. " . (count($waitlisted) - count($remaining_waitlist)) . " players moved, " . count($remaining_waitlist) . " remaining", 'debug');
    
    // Debug final lines
    hockey_log("Final lines: " . print_r($lines, true), 'debug');
    
    return $lines;
}

function finalize_roster_at_930pm($date) {
    $day_directory_map = get_day_directory_map($date);
    $day_of_week = date('l', strtotime($date));
    $day_directory = $day_directory_map[$day_of_week] ?? null;

    if (!$day_directory) {
        hockey_log("No directory mapping found for date: {$date}", 'error');
        return;
    }

    $formatted_date = date('D_M_j', strtotime($date));
    $season = get_current_season($date);
    $file_path = plugin_dir_path(dirname(__FILE__)) . "rosters/{$season}/{$day_directory}/Pickup_Roster-{$formatted_date}.txt";

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
    
    $file_path = plugin_dir_path(dirname(__FILE__)) . "rosters/{$season}/{$day_directory}/Pickup_Roster-{$formatted_date}.txt";
    
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
        return '<div class="hockey-roster-display">' . esc_html($roster_content) . '</div>';
    }
    
    // Show next game date when no roster is available
    if (get_option('hockeysignin_hide_next_game', '0') !== '1') {
        $next_game_day = calculate_next_game_day();
        $next_game_day_formatted = date_i18n('l, F jS', strtotime($next_game_day));
        return '<div class="hockey-roster-display">The next scheduled skate date is ' . $next_game_day_formatted . '.</div>';
    }
    
    return '';
}

function check_in_player_after_6pm($date, $player_name) {
global $wpdb;

$day_of_week = date('l', strtotime($date));
$day_directory_map = get_day_directory_map($date);
$day_directory = $day_directory_map[$day_of_week] ?? null;
$formatted_date = date('D_M_j', strtotime($date));
$season = get_current_season($date);
$file_path = plugin_dir_path(dirname(__FILE__)) . "rosters/{$season}/{$day_directory}/Pickup_Roster-{$formatted_date}.txt";

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
return "Roster file not found for today.<br><br>Our skates are Tuesday 10:30pm Forum, Thursday 10:30pm Civic, and Friday & Saturday 10:30pm Forum.<br>We now offer Beginner/Rusty *and* Quicker pace skates Friday nights.<br>Check in begins at 8:00am for each skate.";
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
        '/\bshame\b/i',
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

function find_available_spot($lines, $preferred_position = null, $target_rink = null) {
    $sections = get_roster_sections($lines, date('l'));
    $end_line = isset($sections['waitlist']) ? $sections['waitlist']['start'] : count($lines);
    
    hockey_log("Searching for spot between lines 0 and {$end_line}" . 
        ($preferred_position ? " (preferred position: {$preferred_position})" : "") .
        ($target_rink ? " (target rink: {$target_rink})" : ""), 'debug');
    
    // Log the first few lines of the roster for debugging
    hockey_log("First 10 lines of roster:", 'debug');
    for ($i = 0; $i < min(10, count($lines)); $i++) {
        hockey_log("Line {$i}: '{$lines[$i]}'", 'debug');
    }
    
    $forward_spots = [];
    $defense_spots = [];
    $goalie_spots = [];
    
    // Get the start lines for each rink section
    $civic_start = null;
    $forum_start = null;
    $season = get_current_season(current_time('Y-m-d'));
    $is_summer = strpos($season, 'Summer') !== false;
    
    for ($i = 0; $i < $end_line; $i++) {
        if (!$is_summer && preg_match('/^CIVIC (10:30|11:00)PM/', $lines[$i])) {
            $civic_start = $i;
        } elseif (strpos($lines[$i], 'FORUM 10:30PM') === 0) {
            $forum_start = $i;
        }
    }
    
    // Collect all available spots
    for ($i = 0; $i < $end_line; $i++) {
        $line = trim($lines[$i]);
        
        // Skip lines that don't contain position markers
        if (!preg_match('/^(F-|D-|Goal:)/', $line)) {
            continue;
        }
        
        // Determine which rink this spot is in
        $current_rink = null;
        if (!$is_summer && $civic_start !== null && $i > $civic_start && ($forum_start === null || $i < $forum_start)) {
            $current_rink = 'CIVIC';
        } elseif ($forum_start !== null && $i > $forum_start) {
            $current_rink = 'FORUM';
        }
        
        // For Friday skates, check if the spot is in the target rink
        if ($target_rink && $current_rink !== $target_rink) {
            hockey_log("Skipping spot at line {$i} - wrong rink: {$current_rink}", 'debug');
            continue;
        }
        
        // Check if the spot is empty (no player name after the position marker)
        if (preg_match('/^(F-|D-|Goal:)\s*$/', $line)) {
            hockey_log("Found empty spot at line {$i}: {$line} in rink {$current_rink}", 'debug');
            if ($line === 'F-') {
                $forward_spots[] = ['line_number' => $i, 'position' => 'F', 'rink' => $current_rink];
            } elseif ($line === 'D-') {
                $defense_spots[] = ['line_number' => $i, 'position' => 'D', 'rink' => $current_rink];
            } elseif ($line === 'Goal:') {
                $goalie_spots[] = ['line_number' => $i, 'position' => 'Goal', 'rink' => $current_rink];
            }
        } else {
            hockey_log("Spot at line {$i} is not empty: {$line}", 'debug');
        }
    }
    
    hockey_log("Available spots - Forward: " . count($forward_spots) . 
               ", Defense: " . count($defense_spots) . 
               ", Goalie: " . count($goalie_spots), 'debug');
    
    // Special handling for goalies
    if ($preferred_position !== null && strtolower($preferred_position) === 'goalie') {
        if (!empty($goalie_spots)) {
            hockey_log("Found available goalie spot for goalie player", 'debug');
            $spot = $goalie_spots[array_rand($goalie_spots)];
            hockey_log("Selected goalie spot at line {$spot['line_number']} in rink {$spot['rink']}", 'debug');
            return $spot;
        }
        hockey_log("No goalie spots available for goalie player, must go to waitlist", 'debug');
        return null;
    }
    
    // Non-goalie players can only be assigned to forward or defense positions
    if ($preferred_position !== null) {
        $position_lower = strtolower($preferred_position);
        switch ($position_lower) {
            case 'forward':
                if (!empty($forward_spots)) {
                    hockey_log("Found available forward spots for preferred position", 'debug');
                    $spot = $forward_spots[array_rand($forward_spots)];
                    hockey_log("Selected forward spot at line {$spot['line_number']} in rink {$spot['rink']}", 'debug');
                    return $spot;
                }
                hockey_log("No forward spots available despite preference", 'debug');
                break;
            case 'defence':
            case 'defense':
                if (!empty($defense_spots)) {
                    hockey_log("Found available defense spots for preferred position", 'debug');
                    $spot = $defense_spots[array_rand($defense_spots)];
                    hockey_log("Selected defense spot at line {$spot['line_number']} in rink {$spot['rink']}", 'debug');
                    return $spot;
                }
                hockey_log("No defense spots available despite preference", 'debug');
                break;
        }
    }
    
    // If no preferred position available, randomly select from any non-goalie spot
    $all_spots = array_merge($forward_spots, $defense_spots);
    if (!empty($all_spots)) {
        hockey_log("Falling back to random position selection (excluding goalie spots)", 'debug');
        $spot = $all_spots[array_rand($all_spots)];
        hockey_log("Selected random spot: {$spot['position']} at line {$spot['line_number']} in rink {$spot['rink']}", 'debug');
        return $spot;
    }
    
    hockey_log("No available spots found", 'debug');
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
    if ($day_of_week === 'Friday') {
        $civic_start = false;
        $forum_start = false;
        $waitlist_start = false;
        $season = get_current_season(current_time('Y-m-d'));
        $is_summer = strpos($season, 'Summer') !== false;
        
        foreach ($lines as $i => $line) {
            $line = trim($line);
            if (preg_match('/^CIVIC (10:30|11:00)PM/', $line)) {
                $civic_start = $i;
            }
            if (strpos($line, 'FORUM 10:30PM') === 0) {
                $forum_start = $i;
            }
            if ($line === 'WL:') {
                $waitlist_start = $i;
            }
        }
        
        if ($is_summer) {
            // For summer, we only need Forum and waitlist sections
            if ($forum_start !== false && $waitlist_start !== false) {
                return [
                    'forum' => ['start' => $forum_start],
                    'waitlist' => [
                        'start' => $waitlist_start,
                        'end' => count($lines)
                    ]
                ];
            }
            hockey_log("Error: Could not find required sections in summer Friday roster. Forum: {$forum_start}, Waitlist: {$waitlist_start}", 'error');
        } else {
            // For non-summer, we need all three sections
            if ($civic_start !== false && $forum_start !== false && $waitlist_start !== false) {
                return [
                    'civic' => ['start' => $civic_start],
                    'forum' => ['start' => $forum_start],
                    'waitlist' => [
                        'start' => $waitlist_start,
                        'end' => count($lines)
                    ]
                ];
            }
            hockey_log("Error: Could not find all sections in winter Friday roster. Civic: {$civic_start}, Forum: {$forum_start}, Waitlist: {$waitlist_start}", 'error');
        }
        return null;
    }
    
    // Keep existing non-Friday logic
    $waitlist_start = array_search('WL:', $lines);
    if ($waitlist_start === false) {
        hockey_log("Error: Could not find waitlist section in non-Friday roster", 'error');
        return null;
    }
    
    return [
        'main' => [
            'start' => 0,
            'end' => $waitlist_start
        ],
        'waitlist' => [
            'start' => $waitlist_start,
            'end' => count($lines)
        ]
    ];
}

function get_waitlisted_players($lines, $sections) {
    $waitlisted = [];
    
    if (!isset($sections['waitlist']['start'])) {
        hockey_log("No waitlist section found", 'debug');
        return $waitlisted;
    }
    
    // Debug the waitlist section
    hockey_log("Reading waitlist from line {$sections['waitlist']['start']}", 'debug');
    
    // Start after the "WL:" line
    $start_line = $sections['waitlist']['start'] + 1;
    
    // Read until the end of the file
    for ($i = $start_line; $i < count($lines); $i++) {
        $line = trim($lines[$i]);
        hockey_log("Processing waitlist line: '{$line}'", 'debug');
        
        if (preg_match('/^\d+\.\s*(.*)$/', $line, $matches)) {
            $player = trim($matches[1]);
            if (!empty($player)) {
                $waitlisted[] = $player;
                hockey_log("Found waitlisted player: {$player}", 'debug');
            }
        }
    }
    
    hockey_log("Found " . count($waitlisted) . " players on waitlist", 'debug');
    return $waitlisted;
}

// For moving waitlist players to roster during 6pm event
function move_waitlist_player_to_roster($player_name, $spot, $is_6pm_event = false) {
    $position = $spot['position'];
    $player_with_mark = $player_name . " *";
    
    // Only add "confirming" text during 6pm event
    if ($is_6pm_event) {
        $player_with_mark .= " *confirming*";
    }
    
    $lines[$spot['line_number']] = $player_with_mark;
    return $lines;
}

function assign_player_to_spot($player_name, $spot, $is_waitlist_move = false) {
    $position = $spot['position'];
    
    // Only add confirming text if it's a waitlist move during 6pm event
    if ($is_waitlist_move) {
        $player_with_mark = $player_name . " * *confirming*";
    } else {
        $player_with_mark = $player_name;
    }
    
    if ($position === 'Goal') {
        return "Goal: " . $player_with_mark;
    } else {
        return "{$position}- " . $player_with_mark;
    }
}

function capitalize_player_name($name) {
    // Split the name into parts
    $name_parts = explode(' ', trim($name));
    
    // Capitalize first letter of each part
    $capitalized_parts = array_map(function($part) {
        return ucfirst(strtolower($part));
    }, $name_parts);
    
    // Join the parts back together
    return implode(' ', $capitalized_parts);
}

function get_fast_skate_rink($date) {
    // Reference date: April 18, 2024 - Fast skate at Forum
    $reference_date = strtotime('2024-04-18');
    $current_date = strtotime($date);
    
    // Calculate weeks difference
    $weeks_diff = floor(($current_date - $reference_date) / (7 * 24 * 60 * 60));
    
    // If even number of weeks, Fast is at Forum, otherwise at Civic
    return ($weeks_diff % 2 == 0) ? 'FORUM' : 'CIVIC';
}