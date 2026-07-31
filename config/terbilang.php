<?php

// config for Riskihajar/Terbilang
return [
    /*
    |--------------------------------------------------------------------------
    | Date Output Format
    |--------------------------------------------------------------------------
    */
    'output' => [
        'date' => '{DAY} {MONTH} {YEAR}',
        'time' => '{HOUR} {SEPARATOR} {MINUTE} {MINUTE_LABEL} {SECOND} {SECOND_LABEL}',
    ],

    /**
     * Locale for Terbilang output (Indonesian)
     */
    'locale' => 'id',

    /**
     * Prefer package dictionary over intl NumberFormatter
     * so terbilang stays in Bahasa Indonesia consistently.
     */
    'use_intl' => false,

    /*
    |--------------------------------------------------------------------------
    | Distance Between Date Output Format
    |--------------------------------------------------------------------------
    */
    'distance' => [
        'type' => \Riskihajar\Terbilang\Enums\DistanceDate::Day,
        'template' => '{YEAR} {MONTH} {DAY} {HOUR} {MINUTE} {SECOND}',
        'hide_zero_value' => true,
        'separator' => ' ',
        'terbilang' => false,
        'show' => [
            'year' => true,
            'month' => true,
            'day' => true,
            'hour' => true,
            'minute' => true,
            'second' => true,
        ],
    ],
];
