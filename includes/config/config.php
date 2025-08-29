<?php
return [
    'times' => [
        'check_in_start' => '08:00',
        'waitlist_process' => '18:00',
        'game_end' => '23:59'
    ],
    'paths' => [
        'roster_template' => realpath(__DIR__ . '/../../rosters/roster_template.txt'),
        'roster_base' => realpath(__DIR__ . '/../../rosters/'),
        'log_file' => realpath(__DIR__ . '/../../logs/debug.log')
    ]
];
