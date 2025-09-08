<?php
/**
 * Nova Adult Hockey Unified Configuration
 * Supports multiple regions and venues
 */

function get_nova_hockey_config() {
    return [
        'regions' => [
            'halifax' => [
                'name' => 'Halifax Pickup Hockey',
                'domain' => 'halifaxpickuphockey.com',
                'venues' => [
                    'Forum' => [
                        'name' => 'Halifax Forum',
                        'address' => '2901 Windsor St, Halifax, NS',
                        'time_slots' => ['10:30 PM']
                    ],
                    'Civic' => [
                        'name' => 'Halifax Civic Centre',
                        'address' => '5800 Sackville St, Halifax, NS',
                        'time_slots' => ['10:30 PM']
                    ]
                ],
                'schedules' => [
                    'Monday' => ['Forum'],
                    'Tuesday' => ['Forum'],
                    'Thursday' => ['Civic'],
                    'Friday' => ['Forum'],
                    'Saturday' => ['Forum']
                ],
                'seasons' => [
                    'regular' => [
                        'start' => '10-01',
                        'end' => '03-31',
                        'directory_map' => [
                            'Monday' => 'Mon1030Forum',
                            'Tuesday' => 'Tues1030Forum',
                            'Thursday' => 'Thur1030Civic',
                            'Friday' => 'Fri1030Forum',
                            'Saturday' => 'Sat1030Forum'
                        ],
                        'folder_format' => 'RegularSeason{year}-{next_year}'
                    ],
                    'spring' => [
                        'start' => '04-01',
                        'end' => '05-31',
                        'directory_map' => [
                            'Monday' => 'Mon1030Civic',
                            'Tuesday' => 'Tues1030Civic',
                            'Thursday' => 'Thur1030Civic',
                            'Friday' => 'Fri1030Civic',
                            'Saturday' => 'Sat1030Civic'
                        ],
                        'folder_format' => 'Spring{year}'
                    ],
                    'summer' => [
                        'start' => '06-01',
                        'end' => '09-30',
                        'directory_map' => [
                            'Monday' => 'Mon1030Forum',
                            'Tuesday' => 'Tues1030Forum',
                            'Thursday' => 'Thur1030Forum',
                            'Friday' => 'Fri1030Forum',
                            'Saturday' => 'Sat1000Forum'
                        ],
                        'folder_format' => 'Summer{year}'
                    ]
                ]
            ],
            'south_shore' => [
                'name' => 'South Shore Pickup Hockey',
                'domain' => 'southshorepickuphockey.com',
                'venues' => [
                    'LCLC' => [
                        'name' => 'Lunenburg County Lifestyle Centre',
                        'address' => '135 North Park St, Bridgewater, NS',
                        'time_slots' => ['9:15 PM', '9:30 PM']
                    ]
                ],
                'schedules' => [
                    'Sunday' => ['LCLC'],
                    'Wednesday' => ['LCLC']
                ],
                'seasons' => [
                    'regular' => [
                        'start' => '10-01',
                        'end' => '03-31',
                        'directory_map' => [
                            'Sunday' => 'Sunday0915PMLCLC',
                            'Wednesday' => 'Wednesday0930PMLCLC'
                        ],
                        'folder_format' => 'RegularSeason{year}-{next_year}'
                    ],
                    'spring' => [
                        'start' => '04-01',
                        'end' => '05-31',
                        'directory_map' => [
                            'Sunday' => 'Sunday0915PMLCLC',
                            'Wednesday' => 'Wednesday0930PMLCLC'
                        ],
                        'folder_format' => 'Spring{year}'
                    ],
                    'summer' => [
                        'start' => '06-01',
                        'end' => '09-30',
                        'directory_map' => [
                            'Sunday' => 'Sunday0915PMLCLC',
                            'Wednesday' => 'Wednesday0930PMLCLC'
                        ],
                        'folder_format' => 'Summer{year}'
                    ]
                ]
            ]
        ],
        'global_settings' => [
            'checkin_hours' => [
                'start' => 8,  // 8 AM
                'end' => 18    // 6 PM
            ],
            'default_game_fee' => 25.00,
            'late_fee' => 5.00,
            'no_show_fee' => 10.00,
            'max_players_per_game' => 20,
            'waitlist_limit' => 10,
            'timezone' => 'America/Halifax'
        ],
        'features' => [
            'date_overrides' => true,
            'checkin_visibility' => true,
            'skill_menus' => true,
            'payment_integration' => false, // To be enabled in Phase 3
            'newsletter_integration' => false, // To be enabled in Phase 4
            'podcast_integration' => false // To be enabled in Phase 4
        ]
    ];
}

/**
 * Get configuration for a specific region
 */
function get_region_config($region_key) {
    $config = get_nova_hockey_config();
    return $config['regions'][$region_key] ?? null;
}

/**
 * Get all available regions
 */
function get_available_regions() {
    $config = get_nova_hockey_config();
    return array_keys($config['regions']);
}

/**
 * Get current region based on domain or WordPress option
 */
function get_current_region() {
    // Check if region is set in WordPress options
    $stored_region = get_option('hockey_current_region');
    if ($stored_region) {
        return $stored_region;
    }
    
    // Fallback to domain detection
    $domain = $_SERVER['HTTP_HOST'] ?? '';
    
    if (strpos($domain, 'halifax') !== false) {
        return 'halifax';
    } elseif (strpos($domain, 'southshore') !== false) {
        return 'south_shore';
    } elseif (strpos($domain, 'novaadulthockey') !== false) {
        return 'nova'; // Main landing page
    }
    
    // Default to halifax if no match
    return 'halifax';
}

/**
 * Get season configuration for a region and date
 */
function get_season_config($region_key, $date = null) {
    $region_config = get_region_config($region_key);
    if (!$region_config) {
        return null;
    }
    
    $date = $date ?: current_time('Y-m-d');
    $month_day = date('m-d', strtotime($date));
    
    foreach ($region_config['seasons'] as $season_key => $season_config) {
        $start = $season_config['start'];
        $end = $season_config['end'];
        
        // Handle year boundary (e.g., regular season Oct-Mar)
        if ($start > $end) {
            if ($month_day >= $start || $month_day <= $end) {
                return $season_config;
            }
        } else {
            if ($month_day >= $start && $month_day <= $end) {
                return $season_config;
            }
        }
    }
    
    // Default to regular season if no match
    return $region_config['seasons']['regular'] ?? null;
}

return get_nova_hockey_config();
