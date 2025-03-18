<?php
use HockeySignin\Form_Handler;

function hockeysignin_add_admin_menu() {
    add_menu_page('Hockey Sign-in', 'Hockey Sign-in', 'manage_options', 'hockeysignin', 'hockeysignin_admin_page', 'dashicons-admin-users', 25);
    add_submenu_page('hockeysignin', 'Settings', 'Settings', 'manage_options', 'hockeysignin_settings', 'hockeysignin_settings_page');
}

function hockeysignin_settings_page() {
    ?>
    <div class="wrap">
        <h1>Hockey Sign-in Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('hockeysignin_settings_group');
            do_settings_sections('hockeysignin_settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function hockeysignin_admin_page() {
    echo '<div class="wrap"><h1>Hockey Player Check-In</h1>';
    
    $handler = Form_Handler::getInstance();
    $current_date = current_time('Y-m-d');
    $current_time = current_time('H:i');
    
    if (isset($_POST['manual_start'])) {
        if (!$handler->verify_nonce()) {
            die('Security check failed');
        }
        
        $manually_started_next_game_date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : get_next_game_date();
        create_next_game_roster_files($manually_started_next_game_date);
        echo '<div class="updated"><p>Roster file created for ' . esc_html($manually_started_next_game_date) . '.</p></div>';
    }
    
    // Check if it's past waitlist processing time
    $waitlist_time = get_option('hockey_waitlist_processing_time', '18:00');
    if ($current_time >= $waitlist_time) {
        $day_of_week = date('l', strtotime($current_date));
        $day_directory_map = get_day_directory_map($current_date);
        $day_directory = $day_directory_map[$day_of_week] ?? null;
        
        if ($day_directory) {
            $formatted_date = date('D_M_j', strtotime($current_date));
            $season = get_current_season($current_date);
            $file_path = realpath(__DIR__ . "/../rosters/") . "/{$season}/{$day_directory}/Pickup_Roster-{$formatted_date}.txt";
            
            if (file_exists($file_path)) {
                $roster = file_get_contents($file_path);
                $lines = explode("\n", $roster);
                move_waitlist_to_roster($lines, $day_of_week);
                echo '<div class="updated"><p>Roster has been automatically updated at ' . esc_html($waitlist_time) . '.</p></div>';
            }
        }
    }
    
    if (isset($_POST['check_in_player'])) {
        $player_name = sanitize_text_field($_POST['player_name']);
        $date = isset($manually_started_next_game_date) ? $manually_started_next_game_date : 
               (isset($_POST['date']) ? sanitize_text_field($_POST['date']) : null);
               
        $response = $handler->handleCheckIn($player_name, $date);
        echo '<div class="updated"><p>' . esc_html($response) . '</p></div>';
    }
    
    if (isset($_POST['confirm_checkout'])) {
        $player_name = sanitize_text_field($_POST['player_name']);
        $response = $handler->handleCheckOut($player_name);
        echo '<div class="updated"><p>' . esc_html($response) . '</p></div>';
    }
    
    // Create a container for the forms and the roster
    echo '<div class="hockeysignin-container" style="display: flex;">';

    // Create a container for the forms
    echo '<div class="hockeysignin-forms" style="flex: 1; margin-right: 20px;">';
    echo '<form method="post" action="" onsubmit="return confirm(\'Would you like to create the next scheduled game day roster?\');">';
    wp_nonce_field('hockeysignin_action', 'hockeysignin_nonce');
    echo '<input type="hidden" name="manual_start" value="1">';
    echo '<input type="submit" class="button-primary" value="Start Next Game">';
    echo '</form>';

    // Add manual waitlist processing button
    echo '<form method="post" action="' . admin_url('admin.php?page=hockeysignin') . '" style="margin-top: 10px;">';
    echo '<input type="hidden" name="action" value="trigger_waitlist_processing">';
    wp_nonce_field('hockeysignin_action', 'hockeysignin_nonce');
    echo '<input type="submit" class="button-secondary" value="Process Waitlist Now" onclick="return confirm(\'Process the waitlist now?\');">';
    echo '</form>';

    // Add undo button after waitlist processing button
    echo '<form method="post" action="' . admin_url('admin.php?page=hockeysignin') . '" style="margin-top: 10px;">';
    echo '<input type="hidden" name="action" value="undo_waitlist_processing">';
    wp_nonce_field('hockeysignin_action', 'hockeysignin_nonce');
    echo '<input type="submit" class="button-secondary" value="Undo Last Waitlist Processing" onclick="return confirm(\'Undo the last waitlist processing?\');">';
    echo '</form>';

    echo '<h2>Manual Player Check-In</h2>';
    echo '<form method="post" action="">';
    wp_nonce_field('hockeysignin_action', 'hockeysignin_nonce');
    echo '<label for="player_name">Player Name:</label>';
    echo '<input type="text" name="player_name" required>';
    echo '<label for="date">Date (optional):</label>';
    echo '<input type="date" name="date">';
    echo '<input type="hidden" name="check_in_player" value="1">';
    echo '<input type="submit" class="button-primary" value="Check In Player">';
    echo '</form>';
    echo '</div>'; // Close the forms container

    // Create a container for the roster
    echo '<div class="hockeysignin-roster" style="flex: 1;">';
    echo '<h2>Current Roster</h2>';
    echo display_roster(isset($manually_started_next_game_date) ? $manually_started_next_game_date : null);
    echo '</div>'; // Close the roster container

    echo '</div>'; // Close the main container

    echo '</div>';
}

// Function to get the next game date
function get_next_game_date() {
    return \hockeysignin\Core\GameSchedule::getInstance()->getNextGameDate();
}

add_action('admin_menu', 'hockeysignin_add_admin_menu');

function hockeysignin_settings_init() {
    // Register settings
    register_setting('hockeysignin_settings_group', 'hockeysignin_off_state');
    register_setting('hockeysignin_settings_group', 'hockeysignin_custom_text');
    register_setting('hockeysignin_settings_group', 'hockeysignin_hide_next_game');
    register_setting('hockeysignin_settings_group', 'hockeysignin_testing_mode');

    // Main settings section
    add_settings_section(
        'hockeysignin_main_section',
        'Main Settings',
        null,
        'hockeysignin_settings'
    );

    // Add fields
    add_settings_field(
        'hockeysignin_off_state',
        'Turn Off Sign-in',
        'hockeysignin_off_state_callback',
        'hockeysignin_settings',
        'hockeysignin_main_section'
    );

    add_settings_field(
        'hockeysignin_testing_mode',
        'Enable Testing Mode',
        'hockeysignin_testing_mode_callback',
        'hockeysignin_settings',
        'hockeysignin_main_section'
    );
}

// Add the missing callback functions
function hockeysignin_off_state_callback() {
    $off_state = get_option('hockeysignin_off_state', '0');
    echo '<input type="checkbox" id="hockeysignin_off_state" 
          name="hockeysignin_off_state" 
          value="1" ' . checked('1', $off_state, false) . '/>
          <label for="hockeysignin_off_state"> Disable player check-in</label>';
}

function hockeysignin_testing_mode_callback() {
    $testing_mode = get_option('hockeysignin_testing_mode', '0');
    hockey_log("Current testing mode value: " . $testing_mode, 'debug');
    ?>
    <input type="checkbox" 
           id="hockeysignin_testing_mode" 
           name="hockeysignin_testing_mode" 
           value="1" 
           <?php checked('1', $testing_mode); ?>>
    <label for="hockeysignin_testing_mode">Enable testing mode (bypasses time restrictions)</label>
    <?php
}

add_action('admin_init', 'hockeysignin_settings_init');

add_action('update_option_hockeysignin_testing_mode', function($old_value, $new_value) {
    hockey_log("Testing mode option updated - Old: {$old_value}, New: {$new_value}", 'debug');
}, 10, 2);

function hockeysignin_add_season_wizard() {
    add_submenu_page(
        'hockeysignin',
        'Season Setup',
        'Season Setup',
        'manage_options',
        'hockeysignin_season_setup',
        'render_season_setup'
    );
}
add_action('admin_menu', 'hockeysignin_add_season_wizard');

function render_season_setup() {
    if (isset($_POST['create_season'])) {
        if (isset($_POST['confirm_configuration_change']) && $_POST['confirm_configuration_change'] === 'yes') {
            handle_season_setup();
        } else {
            // Show confirmation dialog
            ?>
            <div class="wrap">
                <h2>Confirm Season Configuration Change</h2>
                <p>Are you sure you want to change the current season configuration? This will affect:</p>
                <ul style="list-style-type: disc; margin-left: 20px;">
                    <li>Daily roster creation</li>
                    <li>Waitlist processing</li>
                    <li>Check-in/out system</li>
                    <li>All scheduled tasks</li>
                </ul>
                
                <form method="post">
                    <?php wp_nonce_field('season_setup_nonce'); ?>
                    
                    <!-- Preserve all previous form data -->
                    <?php
                    foreach ($_POST as $key => $value) {
                        if (is_array($value)) {
                            foreach ($value as $k => $v) {
                                echo '<input type="hidden" name="' . esc_attr($key) . '[' . esc_attr($k) . ']" value="' . esc_attr($v) . '">';
                            }
                        } else {
                            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
                        }
                    }
                    ?>
                    <input type="hidden" name="confirm_configuration_change" value="yes">
                    
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="Yes, Change Configuration">
                        <a href="<?php echo admin_url('admin.php?page=hockeysignin_season_setup'); ?>" class="button">Cancel</a>
                    </p>
                </form>
            </div>
            <?php
            return;
        }
    }

    ?>
    <div class="wrap">
        <h1>Season Setup Wizard</h1>
        
        <div class="notice notice-info">
            <p>Note: After creating the season structure, you can edit roster templates using the File Manager plugin in: <code>/wp-content/plugins/hockeysignin/rosters/</code></p>
        </div>

        <?php
        // Add current season info
        $current_date = current_time('Y-m-d');
        $current_season = get_current_season($current_date);
        $next_game_date = get_next_game_date();
        $next_game_day = date('l', strtotime($next_game_date));
        $day_directory_map = get_day_directory_map($next_game_date);
        $next_day_directory = $day_directory_map[$next_game_day] ?? null;
        ?>
        
        <div class="card" style="max-width: 800px; margin-bottom: 20px; padding: 10px;">
            <h3>Current Configuration</h3>
            <p><strong>Active Season:</strong> <?php echo esc_html($current_season); ?></p>
            <p><strong>Base Path:</strong> <code>/wp-content/plugins/hockeysignin/rosters/<?php echo esc_html($current_season); ?>/</code></p>
            <?php if ($next_day_directory): ?>
                <p><strong>Next Active Directory (<?php echo esc_html($next_game_day); ?>):</strong> <code><?php echo esc_html($next_day_directory); ?></code></p>
                <p><strong>Next Game Date:</strong> <?php echo esc_html($next_game_date); ?></p>
            <?php endif; ?>
        </div>

        <form method="post" class="season-setup-form">
            <?php wp_nonce_field('season_setup_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th><label for="season_name">Season Name</label></th>
                    <td>
                        <input type="text" name="season_name" id="season_name" class="regular-text" 
                               placeholder="RegularSeason2024-2025 or Spring2025" required>
                        <p class="description">Enter the season name exactly as it should appear in the folder structure</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="waitlist_time">Waitlist Processing Time</label></th>
                    <td>
                        <input type="time" name="waitlist_time" id="waitlist_time" value="18:00" required>
                        <p class="description">Time when waitlist will be processed daily (24-hour format)</p>
                    </td>
                </tr>
            </table>

            <h3>Game Schedule</h3>
            <table class="widefat" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Venue</th>
                        <th>Start Time (24h)</th>
                        <th>Enable</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                    foreach ($days as $day) {
                        ?>
                        <tr>
                            <td><?php echo $day; ?></td>
                            <td>
                                <input type="text" name="venue[<?php echo $day; ?>]" 
                                       placeholder="Forum or Civic" class="regular-text">
                            </td>
                            <td>
                                <input type="time" name="time[<?php echo $day; ?>]" 
                                       value="22:30" class="regular-text">
                            </td>
                            <td>
                                <input type="checkbox" name="enabled[<?php echo $day; ?>]" value="1">
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>

            <p class="submit">
                <input type="submit" name="create_season" class="button button-primary" value="Create Season Structure">
            </p>
        </form>
    </div>
    <?php

    // Add this temporary code to admin/admin-functions.php for testing
    function check_cron_schedules() {
        $crons = _get_cron_array();
        echo '<div class="card" style="max-width: 800px; margin: 20px 0; padding: 10px;">';
        echo '<h3>Scheduled Tasks</h3>';
        echo '<table class="widefat">';
        echo '<thead><tr><th>Task</th><th>Next Run (Your Time)</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($crons as $timestamp => $cron) {
            foreach ($cron as $hook => $events) {
                if (in_array($hook, ['create_daily_roster_files_event', 'move_waitlist_to_roster_event'])) {
                    $local_time = get_date_from_gmt(date('Y-m-d H:i:s', $timestamp));
                    echo '<tr>';
                    echo '<td>' . esc_html($hook) . '</td>';
                    echo '<td>' . esc_html($local_time) . '</td>';
                    echo '</tr>';
                }
            }
        }
        
        echo '</tbody></table></div>';
    }
    // Add to render_season_setup() temporarily
    check_cron_schedules();
}

function handle_season_setup() {
    if (!wp_verify_nonce($_POST['_wpnonce'], 'season_setup_nonce')) {
        wp_die('Security check failed');
    }

    $season_name = sanitize_text_field($_POST['season_name']);
    $venues = $_POST['venue'];
    $times = $_POST['time'];
    $enabled = $_POST['enabled'] ?? [];
    $waitlist_time = sanitize_text_field($_POST['waitlist_time']);
    
    // Create directory map
    $directory_map = [];
    foreach ($venues as $day => $venue) {
        if (empty($venue) || !isset($enabled[$day])) continue;
        
        // Convert 24hr time to 12hr format with AM/PM
        $time_24 = $times[$day];
        $timestamp = strtotime($time_24);
        $time_12 = date('hi', $timestamp) . (date('a', $timestamp) === 'am' ? 'AM' : 'PM');
        
        // Preserve venue case as entered
        $venue = trim($venue);
        $directory_map[$day] = $day . $time_12 . $venue;
    }

    // Update directory map in WordPress options
    update_option('hockey_directory_map', $directory_map);
    
    // Update active season
    update_option('hockey_active_season', $season_name);
    
    // Create season folders
    $base_path = WP_PLUGIN_DIR . '/hockeysignin/rosters/';
    
    // Create folders and copy templates
    foreach ($directory_map as $day => $dir) {
        $full_path = $base_path . $season_name . '/' . $dir;
        wp_mkdir_p($full_path);
        
        // Copy appropriate template
        $template = $day === 'Friday' ? 'roster_template_friday.txt' : 'roster_template.txt';
        copy($base_path . $template, $full_path . '/template.txt');
    }

    // Update waitlist processing time in wp_options
    update_option('hockey_waitlist_processing_time', $waitlist_time);

    // Clear and reschedule cron jobs
    wp_clear_scheduled_hook('create_daily_roster_files_event');
    wp_clear_scheduled_hook('move_waitlist_to_roster_event');
    
    // Reschedule with new configuration
    $site_timezone = new DateTimeZone(wp_timezone_string());
    $current_time = new DateTime('now', $site_timezone);
    
    // Schedule roster creation for next 8am
    $roster_time = new DateTime('today 8:00:00', $site_timezone);
    if ($current_time > $roster_time) {
        $roster_time->modify('+1 day');
    }
    
    // Schedule waitlist processing for configured time
    $waitlist_time_obj = new DateTime('today ' . $waitlist_time, $site_timezone);
    if ($current_time > $waitlist_time_obj) {
        $waitlist_time_obj->modify('+1 day');
    }

    wp_schedule_event(
        $roster_time->setTimezone(new DateTimeZone('UTC'))->getTimestamp(),
        'daily',
        'create_daily_roster_files_event'
    );
    
    wp_schedule_event(
        $waitlist_time_obj->setTimezone(new DateTimeZone('UTC'))->getTimestamp(),
        'daily',
        'move_waitlist_to_roster_event'
    );

    // Force refresh of next game date calculation
    wp_cache_delete('next_game_date', 'hockeysignin');
    
    // Clear any transients that might cache the game schedule
    delete_transient('hockey_game_schedule');

    add_settings_error(
        'season_setup',
        'season_created',
        'Season structure created and configuration updated successfully!',
        'updated'
    );
}