<?php
// эмуляция торгов (вариант от 2022-06-10 - обрабытваем все заданные сетапы согласно ТЗ и выводим отчет в json - запускается из JS (trade_emulador.php) через AJAX
// setupIDs - массив номеров сетапов, которые нужно посчитать. Если не задано, то считаем все, что указаны
// если указано mode=list, то вместо расчета возвращаем массив из текстов сетапов
ini_set('date.timezone', 'Europe/Moscow');
ini_set('memory_limit', '24480M');
set_time_limit(0);
//set_exception_handler('\myException');
//set_error_handler('myHandler', E_ALL);
$memory_limit = ini_get('memory_limit');
echo "<pre>";
echo "memory_limit: ". $memory_limit.PHP_EOL;

$arr=[];
for($i=0;$i<10000000;$i++){
    try{
        $arr[]=str_pad(generateRandomString(32),1024*1024);
        }
    catch (\Exception $exception){
        echo "*** Exception: ".$exception->getMessage() . ' in '.shortFileName($exception->getFile()).'(' . $exception->getLine() .')'.PHP_EOL;
        //echo "ERROR LIMIT MEMORY".PHP_EOL;
    }
    echo "$i) ".round(memory_get_usage(true)/1024/1024,0)."Mb".PHP_EOL;
}
//file_put_contents("tmp_log/_______debug_ " . shortFileName(__FILE__) . "_(" . __LINE__ . ").json", json_encode(get_defined_vars(), JSON_PARTIAL_OUTPUT_ON_ERROR)); // for debug only
die();
function shortFileName($f){ // отрезает путь к файлу слева
    $pos=strrpos($f,"\\");
    if($pos===false)$pos=strrpos($f,"/");
    if($pos===false)$out=$f;
    else $out=substr($f,$pos+1);
    return($out);
}
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}

