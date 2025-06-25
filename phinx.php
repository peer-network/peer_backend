<?php

return
[
    'paths' => [
        'migrations' => 'sql_files_for_import/migrations',
        'seeds' => 'sql_files_for_import/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'production' => [
            'adapter' => 'pgsql',
            'host' => 'localhost',
            'name' => 'peer',
            'user' => 'postgres',
            'pass' => 'test',
            'port' => '5432',
            'charset' => 'utf8',
        ],
        'development' => [
            'adapter' => 'pgsql',
            'host' => 'localhost',
            'name' => 'peer',
            'user' => 'postgres',
            'pass' => 'test',
            'port' => '5432',
            'charset' => 'utf8',
        ],
        'testing' => [
            'adapter' => 'pgsql',
            'host' => 'localhost',
            'name' => 'peer',
            'user' => 'postgres',
            'pass' => 'test',
            'port' => '5432',
            'charset' => 'utf8',
        ]
    ],
    'version_order' => 'creation'
];
