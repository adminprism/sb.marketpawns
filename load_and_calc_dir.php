<?php
// filePath - путь к файлу с историей (.CSV)
// startBar - индекс первого бара (номер строки, начиная с 0)
// numBars - количество баров, начиная со startBar
// truncate - 1 либо 0 (по умолчнию) - нужно ли стирать, если по этому файлу в БД уже что-то есть (актуально для первой порции
//
ini_set('date.timezone', 'Europe/Moscow');
ini_set('memory_limit', '256M');

echo "<pre>" . date("Y-m-d H:i:s") . " START<br><br>";



ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

DEFINE("BARS_PER_STEP", 3000); // сколько баров за раз читаем и рассчитываем
DEFINE("SHIFT_FOR_NEXT", 2700); // на сколько баров двигаемся (нахлест)

set_time_limit(0);


//$filePath="big_saves\\GBPUSD240.csv";
$startTime = microtime(true);
$url = ((!empty($_SERVER['HTTPS'])) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$url = explode('?', $url);
$url = $url[0];
$pos = strrpos($url, "/");
$url_path = substr($url, 0, $pos + 1);


$files = listdir('big_saves');
// $files = listdir('big_saves_4');
// $files = listdir('big_saves_'); // for testing
// $files = listdir('test_charts'); // for testing
sort($files, SORT_LOCALE_STRING);
$files_cnt = 0;
$bar_total_cnt = 0;
$file_cnt = 0;
foreach ($files as $f) {
    $file_cnt++;
    $startTime1 = microtime(true);
    $l = explode("/", $f);
    $filename = $l[count($l) - 1];
    $path = substr($f, 0, strlen($f) - strlen($filename));
    // echo "path: ($path) filename: ($filename) <br>";
    //echo  $f."  ".filesize($f). "   ".$filename. "<br>";

    $startBar = 0; //  индекс первого бара (номер строки, начиная с 0)
    $numBars = BARS_PER_STEP;
    $files_cnt++;
    $cont = true;
    $call_cnt = 0;
    while ($cont) {
        $call_cnt++;
        $last_url = $url_path . "fill_db4chart.php?filePath=" . urlencode($path . $filename) . "&startBar=$startBar&numBars=$numBars&truncate=" . ($startBar ? 0 : 1);
        $result = file_get_contents($last_url);
        // echo $url_path."fill_db4chart.php?filePath=".$path.$filename."&startBar=$startBar&numBars=$numBars&truncate=".($startBar?0:1)."<br>";
        $res_JSON = json_decode($result, true);
        $res_JSON['file_cnt'] = $file_cnt;
        $res_JSON['call_cnt'] = $call_cnt;
        $res_JSON['last_call_url'] = $last_url;
        file_put_contents("CURRENT_load_chart.json", json_encode($res_JSON));
        //die();/////////////////////////!!!!!!!!!!!!
        if (count($res_JSON['Errors']) > 0 || $res_JSON['info']['numBars'] !== $res_JSON['info']['barCnt']) $cont = false;
        if (count($res_JSON['Errors']) > 0) {
            echo "<br>Вышли по ошибке";
            die();
        }
        //$cont=false;
        $startBar += SHIFT_FOR_NEXT;
    }
    if (count($res_JSON['Errors']) == 0) // походу, лишний кусок, не выполняется по логике при данных настройках ...
        if ($res_JSON['info']['barCnt'] !== $res_JSON['info']['numBars'] && $res_JSON['info']['barCnt'] < 200) { // если последний кусок очень маленький, повторяем еще раз
            $call_cnt++;
            $last_url = $url_path . "fill_db4chart.php?filePath=" . urlencode($path . $filename) . "&startBar=" . ($res_JSON['info']['count_bars'] - $numBars) . "&numBars=$numBars&truncate=0";
            $result = file_get_contents($last_url);
            $res_JSON = json_decode($result, true);
            $res_JSON['file_cnt'] = $file_cnt;
            $res_JSON['call_cnt'] = $call_cnt;
            $res_JSON['last_call_url'] = $last_url;
            file_put_contents("CURRENT_load_chart.json", json_encode($res_JSON));
            if (count($res_JSON['Errors']) > 0) {
                echo "<br>Вышли по ошибке";
                die();
            }
        }
    $endTime = microtime(true);
    echo "$filename : calcTime=" . round($endTime - $startTime1, 3) . "<br>";
    echo "count_bar = " . $res_JSON['info']['count_bars'] . " calcTime per 1000=" . round(($endTime - $startTime1) / ($res_JSON['info']['count_bars'] / 1000), 3) . "<br><br>";
    $bar_total_cnt += $res_JSON['info']['count_bars'];
}
echo "<br>files_act=$files_cnt bar_total_cnt=$bar_total_cnt calcTime=" . round(($endTime - $startTime), 3) . "<br>";

//echo $filePath."<br>";



function listdir($dir = '.')
{
    if (!is_dir($dir)) {
        return false;
    }

    $files = array();
    listdiraux($dir, $files);

    return $files;
}

function listdiraux($dir, &$files)
{
    $handle = opendir($dir);
    while (($file = readdir($handle)) !== false) {
        if ($file == '.' || $file == '..') {
            continue;
        }
        $filepath = $dir == '.' ? $file : $dir . '/' . $file;
        if (is_link($filepath))
            continue;
        if (is_file($filepath))
            $files[] = $filepath;
        else if (is_dir($filepath))
            listdiraux($filepath, $files);
    }
    closedir($handle);
}
