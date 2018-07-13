<?php
/**
 * Docker环境
 */
//$config['mysql']['master']['host']            = 'rm-2ze510v31171wl09zo.mysql.rds.aliyuncs.com';
$config['mysql']['master']['host']            = 'rm-2ze35uq9nsn48il9tuo.mysql.rds.aliyuncs.com';
$config['mysql']['master']['port']            = 3306;
$config['mysql']['master']['user']            = 'dgames';
$config['mysql']['master']['password']        = 'SLQhSfkZeEBCP0Hy';
$config['mysql']['master']['charset']         = 'utf8';
$config['mysql']['master']['database']        = 'dgamesdev';

//$config['mysql']['slave1']['host']           = 'rm-2ze510v31171wl09zo.mysql.rds.aliyuncs.com';
$config['mysql']['slave1']['host']           = 'rm-2ze35uq9nsn48il9tuo.mysql.rds.aliyuncs.com';
$config['mysql']['slave1']['port']           = 3306;
$config['mysql']['slave1']['user']           = 'dgames';
$config['mysql']['slave1']['password']       = 'SLQhSfkZeEBCP0Hy';
$config['mysql']['slave1']['charset']        = 'utf8';
$config['mysql']['slave1']['database']       = 'dgamesdev';

//$config['mysql']['slave2']['host']           = 'rm-2ze510v31171wl09zo.mysql.rds.aliyuncs.com';
$config['mysql']['slave2']['host']           = 'rm-2ze35uq9nsn48il9tuo.mysql.rds.aliyuncs.com';
$config['mysql']['slave2']['port']           = 3306;
$config['mysql']['slave2']['user']           = 'dgames';
$config['mysql']['slave2']['password']       = 'SLQhSfkZeEBCP0Hy';
$config['mysql']['slave2']['charset']        = 'utf8';
$config['mysql']['slave2']['database']       = 'dgamesdev';

$config['mysql_proxy']['master_slave'] = [
    'pools' => [
        'master' => 'master',
        'slaves' => ['slave1', 'slave2'],
    ],
    'mode' => \PG\MSF\Macro::MASTER_SLAVE,
];

return $config;
