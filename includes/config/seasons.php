<?php
function get_season_config() {
    // Get the dynamic directory map first
    $directory_map = get_option('hockey_directory_map', [
        'Sunday' => 'Sunday0915LCLC'  // Default for SSPH
    ]);
    
    $config = get_option('hockey_season_config', [
        'regular' => [
            'start' => '10-01',
            'end' => '03-31',
            'directory_map' => $directory_map,
            'folder_format' => 'RegularSeason{year}-{next_year}'
        ],
        'spring' => [
            'start' => '04-01',
            'end' => '05-31',
            'directory_map' => $directory_map,
            'folder_format' => 'Spring{year}'
        ],
        'summer' => [
            'start' => '06-01',
            'end' => '09-30',
            'directory_map' => $directory_map,
            'folder_format' => 'Summer{year}'
        ]
    ]);
    
    return $config;
}

return get_season_config();
