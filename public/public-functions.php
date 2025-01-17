<?php

// Handle player check-in and check-out
function hockeysignin_handle_form_submission() {
    if (isset($_POST['player_name']) && isset($_POST['action'])) {
        $player_name = sanitize_text_field($_POST['player_name']);
        $action = sanitize_text_field($_POST['action']);
        
        hockey_log("Form submission: action={$action}, player={$player_name}", 'debug');
        
        $handler = \hockeysignin\Core\FormHandler::getInstance();
        $response = '';
        
        if ($action === 'checkin') {
            $response = $handler->handleCheckIn($player_name);
            hockey_log("Check-in response: {$response}", 'debug');
            
            if ($response === 'already_checked_in') {
                $nonce = wp_create_nonce('hockeysignin_action');
                echo '<div class="notice"><p>' . esc_html($player_name) . ' is already checked in. 
                      <form method="post" action="" style="display:inline;">
                      <input type="hidden" name="player_name" value="' . esc_attr($player_name) . '">
                      <input type="hidden" name="action" value="checkout">
                      <input type="hidden" name="hockeysignin_nonce" value="' . $nonce . '">
                      <button type="submit" onclick="return confirm(\'Do you want to check out ' . esc_js($player_name) . '?\');">
                          Check Out Instead?
                      </button>
                      </form></p></div>';
                return;
            }
        } elseif ($action === 'checkout') {
            hockey_log("Processing checkout for player: {$player_name}", 'debug');
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
    
    <script>
    jQuery(document).ready(function($) {
        $('#player_name').on('change', function() {
            var playerName = $(this).val();
            $.ajax({
                url: ajaxurl,
                data: {
                    action: 'check_player_exists',
                    player_name: playerName
                },
                success: function(response) {
                    if (!response.exists) {
                        $('#new_player_info').show();
                    } else {
                        $('#new_player_info').hide();
                    }
                }
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('hockeysignin', 'hockeysignin_shortcode');

// Shortcode for displaying the roster
add_shortcode('hockeysignin_roster', 'display_roster');
