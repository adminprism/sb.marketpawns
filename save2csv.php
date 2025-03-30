<?php
//ini_set('memory_limit', '2048Mb');
set_time_limit(600);
define("WRITE_LOG",0); //
$tableName="mfs";

require_once "login_log.php";

$result=queryMysql("SELECT * FROM $tableName order by id limit 0,10000000",true,MYSQLI_USE_RESULT);

if (!$result) die('Couldn\'t fetch records');
$headers = $result->fetch_fields();
foreach($headers as $header) {
    $head[] = $header->name;
}
$fp = fopen("$tableName".".csv", 'w');

if ($fp && $result) {
//    header('Content-Type: text/csv');
//    header('Content-Disposition: attachment; filename="export.csv"');
//    header('Pragma: no-cache');
//    header('Expires: 0');
    fputcsv($fp, array_values($head),",");
    while ($row = $result->fetch_array(MYSQLI_NUM)) {
        fputcsv($fp, array_values($row),",");
    }
    die;
}
