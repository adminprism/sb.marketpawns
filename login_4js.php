<?php
$dbhost = 'localhost';    // Unlikely to require changing
// $dbname = 'market_pawns';   // БД сайта (чарты, модели)
$dbname = 'webfinance_';   // Modify these...
// $dbname = 'webfinance_2';   // Modify these...
$dbuser = 'root';   // ...variables according
// $dbpass = 'prsms111!';
$dbpass = 'Timer11!';   // ...to your installation
$startTime = $php_start = microtime(true);
$sql_time = $fgc_time = 0;

error_reporting(E_USER_NOTICE); //error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 'On');
$curDir = getcwd();
$Last_Error = "";

function queryMysql($query)
{
  global $connection, $sql_time, $res;
  $tmp_time__ = microtime(true);
  $result = $connection->query($query);
  if (isset($sql_time)) $sql_time += (microtime(true) - $tmp_time__);
  if (!$result) {
    $res['Errors'][] = "mySQL : " . $connection->error; //die($connection->error);
    $res['Errors'][] = "query: " . $query; //die($connection->error);
  }
  return $result;
}
$connection = new mysqli($dbhost,$dbuser,$dbpass,$dbname,3306);
// if ($connection->connect_error) $Last_Error = "ERROR! Ошибка подключения к базе данных $dbname.";
// echo "LOGIN FILE LOADED SUCCESSFULLY<br>";
