<?php
ini_set('date.timezone', 'Europe/Moscow');
set_time_limit(0);
// filePath - путь к файлу с историей (.CSV)
// startBar - индекс первого бара (номер строки, начиная с 0)
// numBars - количество баров, начиная со startBar
// truncate - 1 либо 0 (по умолчнию) - нужно ли стирать, если по этому файлу в БД уже что-то есть (актуально для первой порции
//

$filePath = "big_saves\\USDJPY60.csv";
$startTime = microtime(true);

$url = ((!empty($_SERVER['HTTPS'])) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$url = explode('?', $url);
$url = $url[0];
$pos = strrpos($url, "/");
$url_path = substr($url, 0, $pos + 1);
echo $filePath . "<br>";

$startBar = 0; //  индекс первого бара (номер строки, начиная с 0)
$numBars = 1000;
$cont = true;
while ($cont) {
    $result = file_get_contents($url_path . "fill_db4chart.php?filePath=" . $filePath . "&startBar=$startBar&numBars=$numBars&truncate=" . ($startBar ? 0 : 1));
    $res_JSON = json_decode($result, true);
    file_put_contents("CURRENT_load_chart.json", $result);
    if (count($res_JSON['Errors']) > 0 || $res_JSON['info']['numBars'] !== $res_JSON['info']['barCnt']) $cont = false;
    //$cont=false;
    $startBar += 800;
}
if (count($res_JSON['Errors']) == 0)
    if ($res_JSON['info']['barCnt'] !== $res_JSON['info']['numBars'] && $res_JSON['info']['barCnt'] < 500) { // если последний кусок очень маленький, повторяем еще раз
        $result = file_get_contents($url_path . "fill_db4chart.php?filePath=" . $filePath . "&startBar=" . ($res_JSON['info']['count_bars'] - $numBars) . "&numBars=$numBars&truncate=0");
        file_put_contents("CURRENT_load_chart.json", $result);
    }
$endTime = microtime(true);
echo "calcTime=" . ($endTime - $startTime) . "<br>";
echo "count_bar = " . $res_JSON['info']['count_bars'] . " calcTime per 1000=" . ($endTime - $startTime) / ($res_JSON['info']['count_bars'] / 1000) . "<br>";
