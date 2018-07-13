<?php
/**
 * Docker环境
 */
$config['mysql']['master']['host']            = '127.0.0.1';
$config['mysql']['master']['port']            = 3306;
$config['mysql']['master']['user']            = 'root';
$config['mysql']['master']['password']        = '123456';
$config['mysql']['master']['charset']         = 'utf8';
$config['mysql']['master']['database']        = 'demo';

$config['mysql']['slave1']['host']           = '127.0.0.1';
$config['mysql']['slave1']['port']           = 3306;
$config['mysql']['slave1']['user']           = 'root';
$config['mysql']['slave1']['password']       = '123456';
$config['mysql']['slave1']['charset']        = 'utf8';
$config['mysql']['slave1']['database']       = 'demo';

$config['mysql']['slave2']['host']           = '127.0.0.1';
$config['mysql']['slave2']['port']           = 3306;
$config['mysql']['slave2']['user']           = 'root';
$config['mysql']['slave2']['password']       = '123456';
$config['mysql']['slave2']['charset']        = 'utf8';
$config['mysql']['slave2']['database']       = 'demo';

$config['mysql_proxy']['master_slave'] = [
    'pools' => [
        'master' => 'master',
        'slaves' => ['slave1', 'slave2'],
    ],
    'mode' => \PG\MSF\Macro::MASTER_SLAVE,
];

return $config;