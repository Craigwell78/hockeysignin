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
    
    // Add new testing mode setting
    register_setting('hockeysignin_options', 'hockeysignin_testing_mode');
    
    add_settings_section(
        'hockeysignin_testing_section',
        'Testing Mode Settings',
        'hockeysignin_testing_section_callback',
        'hockeysignin'
    );
    
    add_settings_field(
        'hockeysignin_testing_mode',
        'Enable Testing Mode',
        'hockeysignin_testing_mode_callback',
        'hockeysignin',
        'hockeysignin_testing_section'
    );
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
        
        hockey_log("Manual start button clicked", 'debug');
        $manually_started_next_game_date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : get_next_game_date();
        hockey_log("Next game date determined: " . $manually_started_next_game_date, 'debug');
        
        create_next_game_roster_files($manually_started_next_game_date);
        echo '<div class="updated"><p>Roster file created for ' . esc_html($manually_started_next_game_date) . '.</p></div>';
    }
    
    // Check if it's past 6 PM and add a helpful message about waitlist processing
    $processed_key = 'waitlist_processed_' . $current_date;
    
    // Add this near the top of the function, after checking nonces
    if (isset($_POST['force_waitlist_processing'])) {
        if (!$handler->verify_nonce()) {
            die('Security check failed');
        }
        
        hockey_log("Manual waitlist processing triggered by admin", 'debug');
        process_waitlist_at_6pm();
        echo '<div class="updated"><p>Waitlist processing completed.</p></div>';
    }
    
    // Check if it's past 6 PM
    if ($current_time >= '18:00') {
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
                echo '<div class="updated"><p>Roster has been automatically updated at 6 PM.</p></div>';
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

    // Add this button near your other waitlist controls (around line 110-120)
    echo '<h2>Waitlist Processing</h2>';
    echo '<form method="post" style="margin-bottom: 20px;">';
    wp_nonce_field('hockeysignin_action', 'hockeysignin_nonce');
    echo '<input type="hidden" name="force_waitlist_processing" value="1">';
    echo '<input type="submit" class="button-primary" value="Process Waitlist Now">';
    echo '</form>';
    
    // Display status of today's waitlist processing
    if (get_transient($processed_key)) {
        echo '<div class="notice notice-success inline"><p>Waitlist has been processed for today (' . esc_html($current_date) . ').</p></div>';
    } else if ($current_time >= '18:00') {
        echo '<div class="notice notice-warning inline"><p>It\'s after 6pm, but waitlist processing has not yet run for today. You can process it manually using the button above.</p></div>';
    } else {
        echo '<div class="notice notice-info inline"><p>Waitlist will be automatically processed at 6pm today.</p></div>';
    }

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
    hockey_log("get_next_game_date() called", 'debug');
    $next_date = \hockeysignin\Core\GameSchedule::getInstance()->getNextGameDate();
    hockey_log("get_next_game_date() returning: " . $next_date, 'debug');
    return $next_date;
}

add_action('admin_menu', 'hockeysignin_add_admin_menu');

function hockeysignin_settings_init() {
    // Register all settings
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
    echo '<input type="checkbox" id="hockeysignin_testing_mode" 
          name="hockeysignin_testing_mode" 
          value="1" ' . checked('1', $testing_mode, false) . '/>
          <label for="hockeysignin_testing_mode"> Allow check-in form at any time (for testing)</label>';
}

add_action('admin_init', 'hockeysignin_settings_init');