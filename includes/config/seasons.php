<?php
function get_season_config() {
    // Get the dynamic directory map first
    $directory_map = get_option('hockey_directory_map', []);
    
    // Allow seasons to have individual directory maps if needed
    $regular_map = get_option('hockey_regular_directory_map', $directory_map);
    $spring_map = get_option('hockey_spring_directory_map', $directory_map);
    $summer_map = get_option('hockey_summer_directory_map', $directory_map);
    
    $config = get_option('hockey_season_config', [
        'regular' => [
            'start' => '10-01',
            'end' => '03-31',
            'directory_map' => $regular_map,
            'folder_format' => 'RegularSeason{year}-{next_year}'
        ],
        'spring' => [
            'start' => '04-01',
            'end' => '05-31',
            'directory_map' => $spring_map,
            'folder_format' => 'Spring{year}'
        ],
        'summer' => [
            'start' => '06-01',
            'end' => '09-30',
            'directory_map' => $summer_map,
            'folder_format' => 'Summer{year}'
        ]
    ]);
    
    return $config;
}

return get_season_config();
