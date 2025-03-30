<?php
ini_set('date.timezone', 'Europe/Moscow');
define("FGC_TIMEOUT", 10);
define("MAX_LIMIT", 10000); // максимально допустимое кол-во баров на чарте
define("MIN_LIMIT", 100); // минимально допустимое кол-во баров на чарте
ob_start();
$php_start = $time_start1 = microtime(true);
$sql_time = 0;
$fgc_time = 0;
$err_cnt = 0;
$fgc_timeout = FGC_TIMEOUT / 2;
$ems = [];
$ems["EURUSD"] = 83;
$ems["PLNUSD"] = 181423;
$ems["USDRUB"] = 901;
$ems["USDUAH"] = 176169;
$ems["USDTRY"] = 497242;
$ems["JPYUSD"] = 181450;
$ems["CADUSD"] = 181455;
$ems["GBPUSD"] = 86;
$ems["CHFUSD"] = 181454;
$ems["EURJPY"] = 84;
//usleep(1000000);

$p = [];
$p['1m'] = 2;
$p['5m'] = 3;
$p['10m'] = 4;
$p['15m'] = 5;
$p['30m'] = 6;
$p['1h'] = 7;
$p['1D'] = 8;
$p['1W'] = 9;
$p['1M'] = 10; // список доступных на сайте интервалов
$sec = [];
$sec['1m'] = 60;
$sec['5m'] = 60 * 5;
$sec['10m'] = 60 * 10;
$sec['15m'] = 60 * 15;
$sec['30m'] = 60 * 30;
$sec['1h'] = 60 * 60;
$sec['1D'] = 24 * 60 * 60;
$sec['1W'] = 7 * 24 * 60 * 60;
$sec['1M'] = 30 * 7 * 24 * 60 * 60; // сколько сенунд в интервале

$pair = "EURUSD";
if (isset($_POST['pair'])) $pair = SanitizeString($_POST['pair']);

$interval = "15m";
if (isset($_POST['interval'])) $interval = SanitizeString($_POST['interval']);
if (!isset($p[$interval])) $interval = "5m";

if (isset($_POST['type'])) $type = SanitizeString($_POST['type']);
if (!isset($type)) $type = "saves";

if (isset($_POST['filename'])) $filename = SanitizeString($_POST['filename']);
if (!isset($filename)) $filename = "2019-07-24 12_09 EURUSD1.csv";

$limit = 1000;
if (isset($_POST['limit'])) $limit = SanitizeString($_POST['limit']);
if ($limit < MIN_LIMIT) $limit = MIN_LIMIT;
if ($limit > MAX_LIMIT) $limit = MAX_LIMIT;

// $lastBarTime = "9999999999"; // максимальный бар не определен - берем конец файла
// if (isset($_POST['lastBar'])) $lastBarTime = SanitizeString($_POST['lastBar']);
// //if(("_".$lastBarTime)<"_1990"||("_".$lastBarTime)>"_2025")$lastBarTime="20200821";
// $lastBarTime_digit = $lastBarTime = preg_replace("/[^,.0-9]/", '', $lastBarTime);
// if (strlen($lastBarTime_digit) > 3) $lastBarTime = substr($lastBarTime_digit . "0000000000", 0, 14); // необходимо ввести хотя бы 4 цифры года, иначе берем правый кусок графика
// else $lastBarTime = "999999999999";
// $lastBarTime = substr($lastBarTime, 0, 4) . '.' . substr($lastBarTime, 4, 2) . '.' . substr($lastBarTime, 6, 2) . ',' . substr($lastBarTime, 8, 2) . ':' . substr($lastBarTime, 10, 2); //YYYY.MM.DD,hh:mm

if (isset($_POST['lastBar'])) {
    $lastBarTime = SanitizeString($_POST['lastBar']);
    // Конвертируем timestamp из миллисекунд в секунды и форматируем дату
    if (is_numeric($lastBarTime) && strlen($lastBarTime) >= 13) {
        $timestamp = floor($lastBarTime / 1000);
        $lastBarTime = date('Y.m.d,H:i', $timestamp);
    } else {
        // Если lastBar не передан или некорректный, берем конец файла
        $lastBarTime = "9999999999";
    }
} else {
    $lastBarTime = "9999999999";
}


if ($type == 'forex') {
    //$ResultInfo['tmp1']="$type interval: $interval filename: $filename limit: $limit";
    $td = []; // на сколько сдвигаем date_from от date_to (в сек) для интервалов (накидывем 3 дня за счет выходных, когда Forex не работает)
    $td['1m'] = 24 * 3600 + 86400 * 2;
    $td['5m'] = 5 * $limit * 60 + 86400 * 2;
    $td['10m'] = 10 * $limit * 60 + 86400 * 2;
    $td['15m'] = 15 * $limit * 60 + 86400 * 2;
    $td['30m'] = 30 * $limit * 60 + 86400 * 3;
    $td['1h'] = 60 * $limit * 60 + 86400 * 3;
    $td['1D'] = 60 * 24 * $limit * 60 + 86400 * 3;
    $td['1W'] = 7 * 60 * 24 * $limit * 60 + 86400 * 3;
    $td['1M'] = 31 * 60 * 24 * $limit * 60 + 86400 * 3;

    $now = Date("Y-m-d h:i:s"); /// параметр?
    $now_uts = strtotime($now);
    echo "now: " . $now . "<br>";
    echo "now_uts: " . $now_uts . "<br>";

    $date_to = Date("Y-m-d h:i:00", $now_uts);
    $date_from = Date("Y-m-d H:i:00", $now_uts - $td[$interval]);
    $from_uts = $now_uts - $td[$interval];

    echo "date_to: " . $date_to . "<br>";
    echo "date_from: " . $date_from . "<br>";

    $date_last_wos = preg_replace("/[^0-9]/", "", $date_from);
    //echo $pair . " " . $date_last_wos . " " . " date_from:".$date_from."<br>";

    $yf = substr($date_from, 0, 4);
    $mf = (int)substr($date_from, 5, 2) - 1;
    $df = (int)substr($date_from, 8, 2);
    $yt = substr($date_to, 0, 4);
    $mt = (int)substr($date_to, 5, 2) - 1;
    $dt = (int)substr($date_to, 8, 2);
    $from = Date("d.m.Y", $from_uts);
    $to = Date("d.m.Y", $now_uts);
    $em = $ems[$pair];
    //    echo "yf: $yf mf: $mf df: $df     yt: $yt mt: $mt dt: $dt  <br>";
    $p_ = $p[$interval];
    $url = "http://export.finam.ru/$pair.txt?market=5&em=$em&code=$pair&apply=0&df=$df&mf=$mf&yf=$yf&from=$from&dt=$dt&mt=$mt&yt=$yt&to=$to&p=$p_&f=$pair&e=.txt&cn=$pair&dtf=1&tmf=1&MSOR=0&mstime=on&mstimever=1&sep=1&sep2=1&datf=5&at=1&fsp=1";
    //    echo $url . "<br>";
    $fgc_time = microtime(true);
    $txt = file_get_contents($url);
    $fgc_time = microtime(true) - $fgc_time;
    if (!$txt) {
        $ResultInfo['status'] = "error - ошибка получения данных с FOREX";
    } else {
        $arTxt = explode("\n", $txt);
        //   echo "count: " . count($arTxt) . "<br>";
        $first_rec = count($arTxt) - $limit - 1;
        if ($first_rec < 1) $first_rec = 1;
        //   echo "first_rec: " . $first_rec . "<br>";
        $ret = [];
        for ($i = $first_rec; $i < count($arTxt); $i++) {
            if (strlen($arTxt[$i]) > 20) {

                $arRec = explode(",", $arTxt[$i]);
                $rec_dandt = $arRec[0] . $arRec[1];
                $rec_dandt_str = substr($rec_dandt, 0, 4) . '-' . substr($rec_dandt, 4, 2) . '-' . substr($rec_dandt, 6, 2);
                $rec_dandt_str .= ' ' . substr($rec_dandt, 8, 2) . ':' . substr($rec_dandt, 10, 2) . ':00';
                //     echo $i . ")" . $arTxt[$i] . " dandt: " . $rec_dandt_str . "<br>";
                $open = $arRec[2];
                $high = $arRec[3];
                $low = $arRec[4];
                $close = $arRec[5];
                $vol = $arRec[6];
                $ret[] = [
                    'open_time' => strtotime($rec_dandt_str) * 1000,
                    'open' => floatval($open),
                    'high' => floatval($high),
                    'low' => floatval($low),
                    'close' => floatval($close),
                    'volume' => floatval($vol),
                    'close_time' => (strtotime($rec_dandt_str) + $sec[$interval]) * 1000,
                ];
            }
        }
        $ResultInfo['result'] = $ret;
        if (count($ret) > 10) $ResultInfo['status'] = "ok";
        else  $ResultInfo['status'] = "error - мало записей: " + count($ret);
    }
} // forex
else {
    // $ResultInfo['tmp1']="$type interval: $interval filename: $filename limit: $limit";
    if (isset($_POST['filename'])) {
        $txt = file_get_contents("saved_charts/" . $_POST['filename']);
        $arTxt = explode("\n", $txt);
        if (strlen($arTxt[count($arTxt) - 1]) < 20) unset($arTxt[count($arTxt) - 1]);
        //file_put_contents("TMP___.txt","lastBarTime=$lastBarTime lastBar=".$_POST['lastBar']);
        $isFound = false;
        $cnt = count($arTxt);
        //        file_put_contents("TMP3___.txt"," lastBarTime=$lastBarTime");
        for ($pk = 0; $pk < $cnt; $pk++) {

            if (strcmp(substr($arTxt[$pk], 0, 16), $lastBarTime) >= 0) {
                //file_put_contents("TMP3___found.txt"," lastBarTime=$lastBarTime substr(arTxt[$pk]".substr($arTxt[$pk],0,16));
                $isFound = $pk;
                break;
            }
        }
        
        // if ($isFound !== false) $first_rec = $isFound - $limit; // нашли строку, дата которой превысила запрашиваемое значение
        // else         $first_rec = $cnt - $limit - 1;

        // if ($first_rec < 1) $first_rec = 1;
        // $ret = [];

        // Определяем начальную позицию для чтения данных
        if ($isFound !== false) {
            $first_rec = max(0, $isFound - $limit); // Берем данные ДО найденной позиции
        } else {
            $first_rec = max(0, $cnt - $limit - 1);
        }
        
        $ret = [];
        $end_rec = min($first_rec + $limit, count($arTxt) - 1);


        for ($i = $first_rec; $i < count($arTxt) - 1 && $i < ($first_rec + $limit); $i++) {
            if (strlen($arTxt[$i]) > 20) {
                $arRec = explode(",", $arTxt[$i]);
                // $arRec_next = explode(",", $arTxt[$i + 1]);
                $arRec_next = explode(",", isset($arTxt[$i + 1]) ? $arTxt[$i + 1] : $arTxt[$i]);
                $rec_dandt = $arRec[0] . $arRec[1];
                $rec_dandt_next = $arRec_next[0] . $arRec_next[1];
                $rec_dandt_str = substr($rec_dandt, 0, 4) . '-' . substr($rec_dandt, 5, 2) . '-' . substr($rec_dandt, 8, 2);
                $rec_dandt_str .= ' ' . substr($rec_dandt, 10, 2) . ':' . substr($rec_dandt, 13, 2) . ':00';
                $rec_dandt_str_next = substr($rec_dandt_next, 0, 4) . '-' . substr($rec_dandt_next, 5, 2) . '-' . substr($rec_dandt_next, 8, 2);
                $rec_dandt_str_next .= ' ' . substr($rec_dandt_next, 10, 2) . ':' . substr($rec_dandt_next, 13, 2) . ':00';

                $open = $arRec[2];
                $high = $arRec[3];
                $low = $arRec[4];
                $close = $arRec[5];
                $vol = $arRec[6];
                $ret[] = [
                    'open_time' => strtotime($rec_dandt_str) * 1000,
                    //                    'rec_dandt_str'=>$rec_dandt_str,
                    //                    'rec_dandt_str_next'=>$rec_dandt_str_next,
                    'open' => floatval($open),
                    'high' => floatval($high),
                    'low' => floatval($low),
                    'close' => floatval($close),
                    'volume' => floatval($vol),
                    'close_time' => strtotime($rec_dandt_str_next)  * 1000,
                ];
            }
        }
        $ResultInfo['result'] = $ret;
        if (count($ret) > 10) $ResultInfo['status'] = "ok";
        else  $ResultInfo['status'] = "error - мало записей: " + count($ret);
    } else {
    }
}


header('Content-type: application/json');
while (ob_get_level()) ob_end_clean();
$ResultInfo['calcTime'] = microtime(true) - $time_start1;
$ResultInfo['fgcTime'] = $fgc_time;
// $r=file_put_contents("TMP_res_Candles__.json",json_encode( $ResultInfo ));
echo json_encode($ResultInfo);


function sanitizeString($var)
{

    $var = strip_tags($var);
    $var = htmlentities($var);
    $var = stripslashes($var);
    return $var;
}
