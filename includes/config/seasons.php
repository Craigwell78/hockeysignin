<?php
return [
    'regular' => [
        'start' => '10-01',
        'end' => '03-31',
        'directory_map' => [
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
            'Tuesday' => 'Tues1030Civic',
            'Thursday' => 'Thur1030Civic',
            'Friday' => 'Fri1030Civic',
            'Saturday' => 'Sat1030Civic'
        ],
        'folder_format' => 'Summer{year}'
    ]
];
