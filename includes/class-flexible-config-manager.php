<?php
/**
 * Flexible Configuration Manager
 * Allows dynamic configuration of seasons, nights, and features
 */

namespace hockeysignin\Admin;

class FlexibleConfigManager {
    
    private static $instance = null;
    
    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Add admin menu for configuration
     */
    public function add_admin_menu() {
        add_submenu_page(
            'hockeysignin',
            'Season Configuration',
            'Season Config',
            'manage_options',
            'hockey-season-config',
            [$this, 'admin_page']
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('hockey_season_config', 'hockey_active_nights');
        register_setting('hockey_season_config', 'hockey_friday_skill_menu_enabled');
        register_setting('hockey_season_config', 'hockey_venue_assignments');
        register_setting('hockey_season_config', 'hockey_season_dates');
    }
    
    /**
     * Admin configuration page
     */
    public function admin_page() {
        if (isset($_POST['submit'])) {
            $this->save_configuration();
        }
        
        $active_nights = get_option('hockey_active_nights', [
            'Monday' => true,
            'Tuesday' => true,
            'Thursday' => true,
            'Friday' => true,
            'Saturday' => true
        ]);
        
        $friday_skill_menu = get_option('hockey_friday_skill_menu_enabled', false);
        $venue_assignments = get_option('hockey_venue_assignments', [
            'Monday' => 'Forum',
            'Tuesday' => 'Forum',
            'Thursday' => 'Civic',
            'Friday' => 'Forum',
            'Saturday' => 'Forum'
        ]);
        
        $season_dates = get_option('hockey_season_dates', [
            'regular_start' => '10-01',
            'regular_end' => '03-31',
            'spring_start' => '04-01',
            'spring_end' => '05-31',
            'summer_start' => '06-01',
            'summer_end' => '09-30'
        ]);
        
        ?>
        <div class="wrap">
            <h1>Nova Adult Hockey - Season Configuration</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('hockey_season_config_nonce'); ?>
                
                <h2>Active Game Nights</h2>
                <p>Select which nights of the week have games:</p>
                <table class="form-table">
                    <?php
                    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                    foreach ($days as $day) {
                        $checked = isset($active_nights[$day]) && $active_nights[$day] ? 'checked' : '';
                        echo "<tr>";
                        echo "<th scope='row'>{$day}</th>";
                        echo "<td><input type='checkbox' name='active_nights[{$day}]' value='1' {$checked} /></td>";
                        echo "</tr>";
                    }
                    ?>
                </table>
                
                <h2>Venue Assignments</h2>
                <p>Assign venues to each active night:</p>
                <table class="form-table">
                    <?php
                    $venues = ['Forum', 'Civic'];
                    foreach ($days as $day) {
                        if (isset($active_nights[$day]) && $active_nights[$day]) {
                            echo "<tr>";
                            echo "<th scope='row'>{$day}</th>";
                            echo "<td>";
                            echo "<select name='venue_assignments[{$day}]'>";
                            foreach ($venues as $venue) {
                                $selected = (isset($venue_assignments[$day]) && $venue_assignments[$day] === $venue) ? 'selected' : '';
                                echo "<option value='{$venue}' {$selected}>{$venue}</option>";
                            }
                            echo "</select>";
                            echo "</td>";
                            echo "</tr>";
                        }
                    }
                    ?>
                </table>
                
                <h2>Friday Skill Menu</h2>
                <p>Enable/disable the Friday skill menu system (Civic & Forum selection):</p>
                <table class="form-table">
                    <tr>
                        <th scope="row">Friday Skill Menu</th>
                        <td>
                            <input type="checkbox" name="friday_skill_menu_enabled" value="1" <?php echo $friday_skill_menu ? 'checked' : ''; ?> />
                            <label>Enable Friday skill menu (Civic & Forum selection)</label>
                            <p class="description">When enabled, players can choose between Civic and Forum on Fridays. When disabled, all Friday games are at Forum only.</p>
                        </td>
                    </tr>
                </table>
                
                <h2>Season Dates</h2>
                <p>Configure season start and end dates:</p>
                <table class="form-table">
                    <tr>
                        <th scope="row">Regular Season</th>
                        <td>
                            <input type="text" name="season_dates[regular_start]" value="<?php echo esc_attr($season_dates['regular_start']); ?>" placeholder="MM-DD" />
                            to
                            <input type="text" name="season_dates[regular_end]" value="<?php echo esc_attr($season_dates['regular_end']); ?>" placeholder="MM-DD" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Spring Season</th>
                        <td>
                            <input type="text" name="season_dates[spring_start]" value="<?php echo esc_attr($season_dates['spring_start']); ?>" placeholder="MM-DD" />
                            to
                            <input type="text" name="season_dates[spring_end]" value="<?php echo esc_attr($season_dates['spring_end']); ?>" placeholder="MM-DD" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Summer Season</th>
                        <td>
                            <input type="text" name="season_dates[summer_start]" value="<?php echo esc_attr($season_dates['summer_start']); ?>" placeholder="MM-DD" />
                            to
                            <input type="text" name="season_dates[summer_end]" value="<?php echo esc_attr($season_dates['summer_end']); ?>" placeholder="MM-DD" />
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Save Configuration'); ?>
            </form>
            
            <h2>Current Configuration Preview</h2>
            <div style="background: #f1f1f1; padding: 15px; border-radius: 5px;">
                <h3>Active Nights:</h3>
                <ul>
                    <?php
                    foreach ($active_nights as $day => $active) {
                        if ($active) {
                            $venue = isset($venue_assignments[$day]) ? $venue_assignments[$day] : 'Not set';
                            echo "<li><strong>{$day}</strong> - {$venue}</li>";
                        }
                    }
                    ?>
                </ul>
                
                <h3>Friday Configuration:</h3>
                <p>Skill Menu: <?php echo $friday_skill_menu ? '<span style="color: green;">Enabled</span>' : '<span style="color: red;">Disabled</span>'; ?></p>
                
                <h3>Season Dates:</h3>
                <ul>
                    <li>Regular: <?php echo $season_dates['regular_start']; ?> to <?php echo $season_dates['regular_end']; ?></li>
                    <li>Spring: <?php echo $season_dates['spring_start']; ?> to <?php echo $season_dates['spring_end']; ?></li>
                    <li>Summer: <?php echo $season_dates['summer_start']; ?> to <?php echo $season_dates['summer_end']; ?></li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Save configuration
     */
    private function save_configuration() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'hockey_season_config_nonce')) {
            wp_die('Security check failed');
        }
        
        // Save active nights
        $active_nights = [];
        if (isset($_POST['active_nights'])) {
            foreach ($_POST['active_nights'] as $day => $value) {
                $active_nights[$day] = true;
            }
        }
        update_option('hockey_active_nights', $active_nights);
        
        // Save venue assignments
        if (isset($_POST['venue_assignments'])) {
            update_option('hockey_venue_assignments', $_POST['venue_assignments']);
        }
        
        // Save Friday skill menu setting
        $friday_skill_menu = isset($_POST['friday_skill_menu_enabled']) ? true : false;
        update_option('hockey_friday_skill_menu_enabled', $friday_skill_menu);
        
        // Save season dates
        if (isset($_POST['season_dates'])) {
            update_option('hockey_season_dates', $_POST['season_dates']);
        }
        
        // Regenerate configuration files
        $this->regenerate_config_files();
        
        echo '<div class="notice notice-success"><p>Configuration saved successfully!</p></div>';
    }
    
    /**
     * Regenerate configuration files based on current settings
     */
    private function regenerate_config_files() {
        $active_nights = get_option('hockey_active_nights', []);
        $venue_assignments = get_option('hockey_venue_assignments', []);
        $season_dates = get_option('hockey_season_dates', []);
        
        // This would regenerate the seasons.php and nova-config.php files
        // For now, we'll just log the changes
        hockey_log("Configuration updated - Active nights: " . implode(', ', array_keys($active_nights)), 'info');
        hockey_log("Venue assignments: " . print_r($venue_assignments, true), 'info');
    }
    
    /**
     * Get current active nights
     */
    public static function getActiveNights() {
        return get_option('hockey_active_nights', [
            'Monday' => true,
            'Tuesday' => true,
            'Thursday' => true,
            'Friday' => true,
            'Saturday' => true
        ]);
    }
    
    /**
     * Check if Friday skill menu is enabled
     */
    public static function isFridaySkillMenuEnabled() {
        return get_option('hockey_friday_skill_menu_enabled', false);
    }
    
    /**
     * Get venue assignments
     */
    public static function getVenueAssignments() {
        return get_option('hockey_venue_assignments', [
            'Monday' => 'Forum',
            'Tuesday' => 'Forum',
            'Thursday' => 'Civic',
            'Friday' => 'Forum',
            'Saturday' => 'Forum'
        ]);
    }
}
