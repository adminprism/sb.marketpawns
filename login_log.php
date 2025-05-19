<?php
$dbhost = 'localhost';    // Unlikely to require changing
$dbname = 'webfinance_';   // Modify these...
// $dbname = 'webfinance_2';   // Modify these...
// $dbname = 'market_pawns';   // Modify these...
$dbuser = 'root';   // ...variables according
$dbpass = 'Timer11!';   // ...to your installation
// $dbpass = 'prsms111!';
$startTime = $php_start = microtime(true);
$sql_time = $fgc_time = 0;

error_reporting(E_ALL); //error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 'On');
$curDir = getcwd();
$Last_Error = "";
$All_Errors = "";
// наш обработчик ошибок
// function myHandler($level, $message, $file, $line, $context)
function myHandler($level, $message, $file, $line, $context = 0)
{
  global $Last_Error, $All_Errors, $res;
  // в зависимости от типа ошибки формируем заголовок сообщения

  switch ($level) {
    case E_WARNING:
      $type = 'Warning';
      break;
    case E_NOTICE:
      $type = 'Notice';
      break;
    default;
      // это не E_WARNING и не E_NOTICE
      // значит мы прекращаем обработку ошибки
      // далее обработка ложится на сам PHP
      return false;
  }
  // выводим текст ошибки
  //  echo "<h2>$type: $message</h2>";
  //  echo "<p><strong>File</strong>: $file:$line</p>";
  //  echo "<p><strong>Context</strong>: $". join(', $', array_keys($context))."</p>";
  $Last_Error = "ERROR! $type $message in file $file line: $line";
  $res['Errors']['line_' . $line] = $Last_Error . "<br> Trace: <br> " . generateCallTrace();
  $res['Error'] = $Last_Error;
  //$res['TMP____ERROR']="ЗАГАДОЧНАЯ ОШИБКА";
  die();
  //  $All_Errors.=($Last_Error."<br>");
  //      $res['AllErrors']=$All_Errors;
  // сообщаем, что мы обработали ошибку, и дальнейшая обработка не требуется
  return true;
}
// регистрируем наш обработчик, он будет срабатывать на для всех типов ошибок
function myException($exception)
{
  global $res;
  static $cnt = 0;
  $cnt++;
  $res['Errors']['Exception_' . $cnt] = $exception->getMessage() . ' (line ' . $exception->getLine() . ')';
  return (true);
}
set_error_handler('myHandler', E_ALL);
set_exception_handler('myException');
register_shutdown_function('shutdown');

$connection = new mysqli($dbhost,$dbuser,$dbpass,$dbname,3306);
if ($connection->connect_error) die($connection->connect_error);

function queryMysql($query, $noLog = false, $useOrStore = MYSQLI_STORE_RESULT)
{
  global $connection, $sql_time, $res, $sqlTimeCheck;
  $tmp_time_1 = microtime(true);
  $result = $connection->query($query, $useOrStore);
  $time__ = microtime(true) - $tmp_time_1;
  if (isset($sql_time)) $sql_time += $time__;
  if (isset($sqlTimeCheck)) { // есди есть такой глобалный массив, значит ведем контроль за выполнением запросов отдельно по каждой строке (откуда вызывается данная функция
    $wf = whereFromLast();
    if (!isset($sqlTimeCheck[$wf]['calcTime'])) $sqlTimeCheck[$wf]['calcTime'] = $time__;
    else $sqlTimeCheck[$wf]['calcTime'] += $time__;
    if (!isset($sqlTimeCheck[$wf]['calcCnt'])) $sqlTimeCheck[$wf]['calcCnt'] = 1;
    else $sqlTimeCheck[$wf]['calcCnt']++;
  }
  if (!$noLog) write_log($query . "<br>", 3);
  //$res['info']['TMP__whereFrom']=whereFromLast();

  if (!$noLog)    write_log(" ($time__)<br>", 3);
  if ($connection->error && (!isset($res['Errors']) || count($res['Errors']) < 1000)) {
    $res['Errors'][] = "mySQL : " . $connection->error; //die($connection->error);
    $res['Errors'][] = "query: " . $query; //die($connection->error);
  }
  return $result;
}
function queryMysql_($query)
{
  global $connection;
  // echo $query."<br>";
  $result = $connection->query($query);
  if (!$result) die($connection->error);

  return $result;
}

function sanitizeString($var)
{
  global $connection;
  $var = strip_tags($var);
  $var = htmlentities($var);
  $var = stripslashes($var);
  return $connection->real_escape_string($var);
}

function write_log($msg, $level)
{
  global $f_header;
  //$fh = fopen("temp\\eev_get_deals_exmo.log", 'a');
  if (WRITE_LOG >= $level) {
    fwrite($f_header, str_replace("<br>", PHP_EOL, $msg));
    //echo $msg;
  };
}
function is_ajax()
{
  if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    return (true);
  } else return (false);
}
function shutdown()
{
  global $Last_Error, $res, $startTime, $sql_time, $sqlTimeCheck; //, $All_Errors;
  //$trace=generateCallTrace();
  //$res['shutDownTrace']=$trace;
  $error = error_get_last();
  if (is_array($error)) {
    $res['Errors']['Errors_on_shutdown'] = $error;
    while (ob_get_level()) ob_end_clean(); // стандартный прием для очистки буфера от мусора, см. в интернете
    //$res['Errors']['Last_Error'] = $Last_Error;
  }
  $tt = (microtime(true) - $startTime);
  $tt = substr($tt, 0, 5) . " сек.";
  $res['info']['calcTime'] = $tt;
  $res['info']['calcTime_sql'] = substr($sql_time, 0, 5) . " сек.";
  if (isset($sqlTimeCheck)) $res['info']['sqlTimeCheck'] = $sqlTimeCheck;


  header('Content-type: application/json');
  //    header('Access-Control-Allow-Origin: *');
  //    header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
  //    header('Access-Control-Allow-Headers: X-Requested-With, content-type');
  while (ob_get_level()) ob_end_clean();  // стандартный прием для очистки буфера от мусора, см. в интернете

  //        $start_memory = memory_get_usage();
  //        file_put_contents("TMP_mgu1.txt"," mgu=".memory_get_usage());
  //        $tmp = unserialize(serialize($res));
  //        file_put_contents("TMP_mgu2.txt"," mgu_of_res=".(memory_get_usage() - $start_memory));

  $res_ = json_encode($res);
  echo $res_; // json_encode($res, true);
}
function generateCallTrace()
{
  $e = new Exception();
  $trace = explode("\n", $e->getTraceAsString());
  // reverse array to make steps line up chronologically
  $trace = array_reverse($trace);
  array_shift($trace); // remove {main}
  array_pop($trace); // remove call to this method
  array_pop($trace); // remove call to myHandler function
  $length = count($trace);
  $result = array();

  for ($i = 0; $i < $length; $i++) {
    $result[] = ($i + 1)  . ')' . substr($trace[$i], strpos($trace[$i], ' ')); // replace '#someNum' with '$i)', set the right ordering
  }

  return "\t" . implode("\n\t", $result) . "\n";
}
function whereFromLast()
{
  $e = new Exception();
  $trace = explode("\n", $e->getTraceAsString());
  $str = $trace[1];
  $pos2 = strpos($str, "):");
  $pos1 = strpos($str, "(");
  if ($pos1 === false || $pos2 === false || $pos1 > $pos2) return (0);
  $res = substr($str, $pos1 + 1, ($pos2 - $pos1 - 1));
  return ($res);
}
