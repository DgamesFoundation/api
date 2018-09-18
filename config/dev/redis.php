<?php

$config['redis']['active'] = 'tw';
/**
 *  local environment
 */
$config['redis']['tw']['ip'] = '127.0.0.1';
$config['redis']['tw']['port'] = 22012;
$config['redis']['asyn_max_count'] = 10;

return $config;
