<?php

// Handle player check-in and check-out
function hockeysignin_handle_form_submission() {
    // Add cache control headers
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if (!isset($_POST['player_name'])) {
            return;
        }

        // Generate a unique submission ID
        if (!session_id()) {
            session_start();
        }
        
        $submission_id = $_POST['submission_id'] ?? '';
        if (empty($submission_id) || isset($_SESSION['processed_submissions'][$submission_id])) {
            return;
        }

        $player_name = sanitize_text_field($_POST['player_name']);
        $action = sanitize_text_field($_POST['action']);
        
        hockey_log("Form submission: action={$action}, player={$player_name}", 'debug');
        
        $handler = \HockeySignin\Form_Handler::getInstance();
        $response = '';
        
        if ($action === 'checkin') {
            hockey_log("Starting check-in process for player: {$player_name}", 'debug');
            $response = $handler->handleCheckIn($player_name);
            hockey_log("Check-in response: {$response}", 'debug');
            
            // Mark this submission as processed
            $_SESSION['processed_submissions'][$submission_id] = true;
            
            if ($response === 'already_checked_in') {
                $nonce = wp_create_nonce('hockeysignin_action');
                echo '<div class="notice"><p>' . esc_html($player_name) . ' is already checked in. 
                      <form method="post" action="" style="display:inline;">
                      <input type="hidden" name="player_name" value="' . esc_attr($player_name) . '">
                      <input type="hidden" name="action" value="checkout">
                      <input type="hidden" name="hockeysignin_nonce" value="' . $nonce . '">
                      <input type="hidden" name="submission_id" value="' . uniqid('checkout_', true) . '">
                      <button type="submit" onclick="return confirm(\'Do you want to check out ' . esc_js($player_name) . '?\');">
                          Check Out Instead?
                      </button>
                      </form></p></div>';
                return;
            }
        } elseif ($action === 'checkout') {
            hockey_log("Processing checkout for player: {$player_name}", 'debug');
            $response = $handler->handleCheckOut($player_name);
            $_SESSION['processed_submissions'][$submission_id] = true;
        }
        
        if ($response) {
            echo '<div class="updated"><p>' . esc_html($response) . '</p></div>';
        }
    }
}
add_action('init', 'hockeysignin_handle_form_submission');

function display_next_game_date() {
    if (get_option('hockeysignin_hide_next_game', '0') === '1') {
        return;
    }
    $next_game_date = get_next_game_date();
    echo "The next scheduled skate date is " . esc_html(date('l, F jS', strtotime($next_game_date))) . ".";
}

// Enqueue necessary scripts and styles for the public-facing parts
function hockeysignin_enqueue_public_scripts() {
    wp_enqueue_style('hockeysignin-styles', plugin_dir_url(__FILE__) . 'styles.css');
    wp_enqueue_script('hockeysignin-scripts', plugin_dir_url(__FILE__) . 'scripts.js', array('jquery'), null, true);
}
add_action('wp_enqueue_scripts', 'hockeysignin_enqueue_public_scripts');

// Shortcode for displaying the sign-in form and handling submissions
function hockeysignin_shortcode() {
    // Replace class with direct time check
    $current_hour = current_time('G'); // 24-hour format
    $current_day = current_time('l'); // Day of week
    
    // Check if check-in should be disabled
    if (get_option('hockeysignin_off_state')) {
        $custom_text = get_option('hockeysignin_custom_text', 'Sign-in is currently disabled.');
        return '<div class="hockeysignin-message">' . esc_html($custom_text) . '</div>';
    }
    
    // Check if within allowed hours (8am to 6pm)
    if ($current_hour < 8 || $current_hour >= 18) {
        return '<div class="hockeysignin-message" style="text-align: center;">Check-in is available from 8am to 6pm on game days.</div>';
    }
    
    ob_start();
    ?>
    <div class="hockeysignin-container">
        <form method="post" action="" id="hockey-signin-form">
            <?php wp_nonce_field('hockeysignin_action', 'hockeysignin_nonce'); ?>
            <input type="hidden" name="submission_id" value="<?php echo uniqid('signin_', true); ?>">
            
            <label for="player_name">Player Name:</label>
            <input type="text" id="player_name" name="player_name" required>
            
            <?php
            // Add skate preference dropdown for Fridays
            $day_of_week = date('l');
            if ($day_of_week === 'Friday') {
                echo '<label for="skate_preference">Skate Preference:</label>';
                echo '<select id="skate_preference" name="skate_preference" required>';
                echo '<option value="fast">Fast</option>';
                echo '<option value="beginner">Beginner/Rusty</option>';
                echo '<option value="either">Either</option>';
                echo '</select>';
            }
            ?>
            
            <div id="new_player_info" style="display: none;">
                <p>Not in our database? Please provide:</p>
                <input type="text" 
                       name="additional_info" 
                       id="additional_info" 
                       placeholder="Contact info (phone/email) or +1 of [player name] (optional)">
            </div>
            
            <button type="submit" name="action" value="checkin">Check In</button>
            <button type="submit" name="action" value="checkout">Check Out</button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('hockeysignin', 'hockeysignin_shortcode');

// Shortcode for displaying the roster
add_shortcode('hockeysignin_roster', 'display_roster');

// Add function to check roster existence during page loads
function check_and_create_roster_on_page_load() {
    // Only run this check once per page load
    static $checked = false;
    if ($checked) return;
    $checked = true;
    
    $current_date = current_time('Y-m-d');
    
    // Only proceed if it's a game day
    if (!\hockeysignin\Core\GameSchedule::getInstance()->isGameDay($current_date)) {
        return;
    }
    
    $day_of_week = date('l', strtotime($current_date));
    $day_directory_map = get_day_directory_map($current_date);
    $day_directory = $day_directory_map[$day_of_week] ?? null;
    
    if (!$day_directory) {
        return;
    }
    
    $formatted_date = date('D_M_j', strtotime($current_date));
    $season = get_current_season($current_date);
    $file_path = plugin_dir_path(dirname(__FILE__)) . "../rosters/{$season}/{$day_directory}/Pickup_Roster-{$formatted_date}.txt";
    
    // If roster doesn't exist, create it
    if (!file_exists($file_path)) {
        hockey_log("Roster file missing during page load, attempting to create: {$file_path}", 'debug');
        $result = create_next_game_roster_files($current_date);
        hockey_log("Page load roster creation result: " . ($result ? "Success" : "Failed"), 'debug');
    }
}

// Hook into WordPress to check roster on every page load
add_action('wp', 'check_and_create_roster_on_page_load');
