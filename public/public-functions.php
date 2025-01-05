<?php

// Handle player check-in and check-out
function hockeysignin_handle_form_submission() {
    if (isset($_POST['player_name']) && isset($_POST['action'])) {
        $player_name = sanitize_text_field($_POST['player_name']);
        $action = sanitize_text_field($_POST['action']);
        
        $handler = \hockeysignin\Core\FormHandler::getInstance();
        $response = '';
        
        if ($action === 'checkin') {
            $response = $handler->handleCheckIn($player_name);
        } elseif ($action === 'checkout') {
            $response = $handler->handleCheckOut($player_name);
        }
        
        if ($response) {
            echo '<div class="updated"><p>' . esc_html($response) . '</p></div>';
        }
    }
}

function display_next_game_date() {
    if (get_option('hockeysignin_hide_next_game', '0') === '1') {
        return;
    }
    $next_game_date = get_next_game_date();
    echo "The next scheduled skate date is " . esc_html(date('l, F jS', strtotime($next_game_date))) . ".";
}
add_action('init', 'hockeysignin_handle_form_submission');

// Enqueue necessary scripts and styles for the public-facing parts
function hockeysignin_enqueue_public_scripts() {
    wp_enqueue_style('hockeysignin-styles', plugin_dir_url(__FILE__) . 'styles.css');
    wp_enqueue_script('hockeysignin-scripts', plugin_dir_url(__FILE__) . 'scripts.js', array('jquery'), null, true);
}
add_action('wp_enqueue_scripts', 'hockeysignin_enqueue_public_scripts');

// Shortcode for displaying the sign-in form and handling submissions
function hockeysignin_shortcode() {
    if (get_option('hockeysignin_off_state')) {
        $custom_text = get_option('hockeysignin_custom_text', 'Sign-in is currently disabled.');
        return '<div class="hockeysignin-message">' . esc_html($custom_text) . '</div>';
    }

    ob_start();
    ?>
    <div class="hockeysignin-container">
        <form method="post" action="">
            <?php wp_nonce_field('hockeysignin_action', 'hockeysignin_nonce'); ?>
            <label for="player_name">Player Name:</label>
            <input type="text" id="player_name" name="player_name" required>
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
