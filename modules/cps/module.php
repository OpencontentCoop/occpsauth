<?php

$Module = array('name' => 'CPS Auth');

$ViewList = array();
$ViewList['auth'] = array(
    'functions' => array('auth'),
    'script' => 'auth.php',
    'params' => array(),
    'unordered_params' => array()
);
$ViewList['logout'] = array(
    'functions' => array('logout'),
    'script' => 'logout.php',
    'params' => array(),
    'unordered_params' => array()
);

$FunctionList = array();
$FunctionList['auth'] = array();
$FunctionList['logout'] = array();


