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
        
        $manually_started_next_game_date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : get_next_game_date();
        create_next_game_roster_files($manually_started_next_game_date);
        echo '<div class="updated"><p>Roster file created for ' . esc_html($manually_started_next_game_date) . '.</p></div>';
    }
    
    // Check if it's past 6 PM
    if ($current_time >= '18:00') {
        move_waitlist_to_roster($current_date);
        echo '<div class="updated"><p>Roster has been automatically updated at 6 PM.</p></div>';
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
    echo '<form method="post" action="' . admin_url('admin-post.php') . '" style="margin-top: 10px;">';
    echo '<input type="hidden" name="action" value="trigger_waitlist_processing">';
    wp_nonce_field('hockeysignin_action', 'hockeysignin_nonce');
    echo '<input type="submit" class="button-secondary" value="Process Waitlist Now" onclick="return confirm(\'Process the waitlist now?\');">';
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