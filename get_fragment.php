<?php
ini_set('date.timezone', 'Europe/Moscow');
define("WRITE_LOG", 0); // уровень логирования (пока про запас)
require_once 'login_log.php';
// функция возвращаетт в JS результирующий массив данных, сохраненный в БД, как если бы был стандартным образом обсчитан алгоритмы 1+2
// параметры:
// InstrumentName - наименования инструмента (name_id в таблице chart_names)
// numBars - количество баров до указанного (по умолчанию 1000)
//  lastBarTime - время последнего бара YYYY-MM-DD HH:mm:SS
// modelId - если указано, то берем кусок нужного чарта, чтобы эта модель была недалеко от правой границы
$pointList = [ // массив-список всех точек, которые могут быть у моделей (по ним ставим относительное позиционирование по базовой точке)
    "_2",
    "_3",
    "t1",
    "t2",
    "t2'",
    "t3",
    "t3-",
    "t3'",
    "t3'мп",
    "t3'мп5'",
    "t4",
    "t5",
    "t5'",
    "t5\"",
    "t3\"",
    "A2Prev",
    "calcP6t",
    "calcP6\"t",
    "auxP6t",
    "auxP6't",
    "t1p",
    "t2p",
    "t3p",
    "t4p",
    "confirmT1p",
    "max_bar",
    "fixed_at",
    "conf_t4"
];
$mainPointsList = [ // массив-список всех точек, которые должны ВСЕ быть внутри отобранного фрагмента графика - используется для проверки - показывать модель или нет (если не все точки видны)
    "_2",
    "_3",
    "t1",
    "t2",
    "t2'",
    "t3",
    "t3-",
    "t3'",
    "t3'мп",
    "t3'мп5'",
    "t4",
    "t5",
    "t5'",
    "t5\"",
    "t3\"",
    "A2Prev"
];

$main_list = [ // набор ключей которые непосредственно у модели (а не в Param или Presupp)
    "v",
    "_2",
    "_3",
    "t1",
    "t2",
    "t2'",
    "t3",
    "t3-",
    "t3'",
    "t3'мп",
    "t3'мп5'",
    "t4",
    "t5",
    "t5'",
    "t5\"",
    "t3\"",
    "A2Prev",
    "conf_t4"
];
$Presupp_list = ["t1p", "t2p", "t3p", "t4p", "confirmT1p"];
$service_list = ["id", "name_id", "bar_id", "Alg"];

ob_start();

$err_cnt = 0;
$ems = [];
$res = []; // возворащаемый результат json
$res['Error'] = 'Error_01';
$res['Errors'] = [];
$res['Chart'] = 0; // нужный фрагмент свечного графика
$res['Models'] = []; // суда поместим модели первого алгоритма
$res['Models2'] = []; // суда поместим модели второго алгоритма
//$sqlTimeCheck=[]; // если этот массив определен, то сюда помещается инфа по количеству вызовов и времени выполнения sql запросов по каждой строке кода
$res['info']['type'] = '_GET';
$PARAM = $_GET;
if (isset($_POST['InstrumentName'])) {
    $res['info']['type'] = '_POST';
    $PARAM = $_POST;
}

$url = ((!empty($_SERVER['HTTPS'])) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$url = explode('?', $url);
$url = $url[0];
$pos = strrpos($url, "/");
$url_path = $res['info']['url'] = substr($url, 0, $pos + 1);

if (isset($PARAM['InstrumentName'])) $instrumentName = SanitizeString($PARAM['InstrumentName']);
else $instrumentName = "EURJPY240";

$lastBarTime = isset($PARAM['lastBarTime']) ? $PARAM['lastBarTime'] : "20030329000000";

$lastBarTime = preg_replace("/[^,.0-9]/", '', $lastBarTime);
if (strlen($lastBarTime) > 3) $lastBarTime = substr($lastBarTime . "000000000000", 0, 14); // необходимо ввести хотя бы 4 цифры года, иначе берем правый кусок графика
else $lastBarTime = "20290101000000";

$numBars = isset($PARAM['numBars']) ? intval($PARAM['numBars']) : 1000;
if ($numBars == 0) $numBars = 1000;
$res['info']['numBars'] = $numBars;
if ($numBars < 300 || $numBars > 10000) {
    $res['Error'] = $res['Errors'][] = "Некорректный параметр numBars: $numBars (должен быть от 300 до 10000";
    die();
}
$modelId = "";
if (isset($PARAM['modelId'])) {
    $modelId = preg_replace("/[^,.0-9]/", '', $PARAM['modelId']);
}
if ($modelId !== "") {
    $query_ = "select n.name instrumentName,bar_id,name_id,timeframe,m.Alg from models m left join chart_names n on m.name_id=n.id where m.id=$modelId;";
    //  $res['info']['TMP1 '.__LINE__]=$query_;
    $result = queryMysql($query_);
    if ($result->num_rows == 1) {
        $tmpRec = $result->fetch_assoc();
        $instrumentName = $tmpRec['instrumentName'];
        $interval_sec = intval($tmpRec['timeframe']);
        $res['info']['AlgNum'] = intval($tmpRec['Alg']);
        $barId = $tmpRec['bar_id'];
        $name_id = $tmpRec['name_id'];
        $query_ = "select dandt from charts where name_id=$name_id and id>=$barId order by id limit 223;";
        //            $res['info']['TMP_query_133']=$query_;
        $result = queryMysql($query_);
        $n_rec = $result->num_rows;
        $result->data_seek($n_rec - 1);
        $tmpRec = $result->fetch_assoc();
        $lastBarTime = preg_replace("/[^,.0-9]/", '', $tmpRec['dandt']);
        $res['info']['modelId'] = $modelId;

        $res['info']['fragment_by_modelId'] = "Запрос modelId=$modelId barId=$barId instrumentName=$instrumentName (name_id=$name_id)";
    } else {
        $res['Error'] = $res['Errors'][] = "Model c id='" . $PARAM['modelId'] . "' в БД не найдена!";
        die();
    }
} else {
    //    $res['info']['startBarTime'] = $lastBarTime;
    $result = queryMysql("select count(*) cnt,ifnull(max(id),0) id,ifnull(max(timeframe),900) timeframe from chart_names where name='$instrumentName'");
    $fa = $result->fetch_assoc();
    $name_id = $fa['id'];
    $interval_sec = intval($fa['timeframe']);
    if ($fa['cnt'] == 0) {
        $res['Error'] = $res['Errors'][] = "Инструмент '$instrumentName' в БД отсутсвует";
        die();
    }
}
$res['info']['lastBarTime'] = $lastBarTime;
$res['info']['Instrument'] = $instrumentName;
$res['info']['Interval_sec'] = $interval_sec;
$chartOut_tmp = [];
$chartOut = [];
$res['info']['TMP_query'] = "select * from charts where name_id=$name_id and dandt<='$lastBarTime' order by dandt desc limit $numBars;";
$result = queryMysql("select * from charts where name_id=$name_id and dandt<='$lastBarTime' order by dandt desc limit $numBars;");
$n_rec = $result->num_rows;
$result->data_seek(0);
$i = 1;
$barById = []; // номер бара в массиве по его ID
while ($chartRec = $result->fetch_assoc()) {
    //$res['info']['TMP__rec']=$chartRec;
    $barById[$chartRec['id']] = $n_rec - $i;
    $ot_date = date_create($chartRec['dandt']);
    $ot_UNIXSTAMP = date_format($ot_date, 'U');

    // $res['info']['TMP__ot_UNIXSTAMP']=$ot_UNIXSTAMP;
    // $res['info']['TMP__ot_']=date_format($ot_date,"Y:m:d H:i:s");
    //$chartOut[]=["open_time"=>$chartRec['dandt'],"open"=>$chartRec['o'],"high"=>$chartRec['h'],"low"=>$chartRec['l'],"close"=>$chartRec['c'],"volume"=>$chartRec['o'],"close_time"=>date_format(date_create($ot_UNIXSTAMP+$interval_sec),"Y:m:d H:i:s")];
    $chartOut_tmp[$n_rec - $i] = ["open_time" => $ot_UNIXSTAMP * 1000, "open" => floatval($chartRec['o']), "high" => floatval($chartRec['h']), "low" => floatval($chartRec['l']), "close" => floatval($chartRec['c']), "volume" => floatval($chartRec['v']), "close_time" => ($ot_UNIXSTAMP + $interval_sec) * 1000];
    $i++;
}
for ($i = 0; $i < count($chartOut_tmp); $i++) $chartOut[] = $chartOut_tmp[$i]; // получаем нормализованный массив, что-бы при выгрузке json_encode получадся индексированный массив а не объект
$res['Chart'] = $chartOut;
$result = queryMysql("truncate table temp");
$result = queryMysql("insert into temp (select id from charts where name_id=$name_id and dandt<='$lastBarTime' order by dandt desc limit $numBars)");
$tmp_ar = [];

//$result=queryMysql("select c.dandt,m.* from models m left join charts c on m.bar_id=c.id where bar_id in (select id from temp);");
//$result=queryMysql("select m.* from models m  where bar_id in (select id from temp);");
$result = queryMysql("select m.* from temp t left join models m on t.id=m.bar_id;"); // заменил для ускорения выборки (было самым медленным участком) - но теперь нужно фильтровать на isnull

$result->data_seek(0);
while ($modelRec = $result->fetch_assoc()) if ($modelRec['Alg']) $tmp_ar[] = $modelRec;
$n_rec = count($tmp_ar);
//$res['tmp_ar']=$tmp_ar;

$index_length = strlen($numBars - 1); // размерность индекса - 3 для 1000 баров (000-999)
// перебор моделей первого алгоритма
for ($i = 0; $i < $n_rec; $i++) if ($tmp_ar[$i]['Alg'] == 1) {
    $baseBarNum = $barById[$tmp_ar[$i]['bar_id']];
    $model_ind_ = "p" . substr(("00000" . $baseBarNum), $index_length * (-1));
    $Alg_ = $tmp_ar[$i]['Alg'];
    //отбираем из массива только те, у которых все важные точки в пределах фрагмента чарта
    $isModelInside = true;
    foreach ($mainPointsList as $pk => $pv) { // перебор всех основных точек - смотрим, находятся ли они все внутри нашего фрагмента
        if (is_null($tmp_ar[$i][$pv])) continue;
        if (($tmp_ar[$i][$pv] + $baseBarNum) < 0 || ($tmp_ar[$i][$pv] + $baseBarNum) >= $numBars) {
            $isModelInside = false;
            break;
        }
    }
    if ($isModelInside) {
        $model = []; // начинаем формировать модель для выдачи
        foreach ($main_list as $pk => $pv) { // заполненние основных полей
            $key = $pv;
            if ($key == '_2' || $key == '_3') $key = substr($key, 1); // отсекаем "_"
            if (!is_null($tmp_ar[$i][$pv])) {
                if (in_array($pv, $pointList)) $model[$key] = $tmp_ar[$i][$pv] + $baseBarNum;
                else $model[$key] = $tmp_ar[$i][$pv];
            }
        }
        $model['id'] = $model['split'] = intval($tmp_ar[$i]['id']);
        foreach ($tmp_ar[$i] as $pk => $pv) if (!in_array($pk, $main_list) && !in_array($pk, $Presupp_list) && !in_array($pk, $service_list)) { // перебое всех полей, относящихся к Param
            if (!is_null($pv)) {
                $val_ = $pv;
                if ($pk == "alt") $val_ = intval($val_);
                if (in_array($pk, $pointList)) $val_ = $val_ + $baseBarNum;
                if ($pk == 'auxP6' || $pk == 'auxP6\'' || $pk == 'calcP6') $val_ = floatval($val_);
                if ($pk == '_CT' || $pk == '_cross_point' || $pk == '_cross_point2') $val_ = CP_translate($baseBarNum, $val_);
                if ($pk == '_points') $val_ = points_translate($Alg_, $val_, $baseBarNum);
                $model['param'][$pk] = $val_;
            }
        }
        // добавляем к параметрам расчетные параметры из отдельной таблицы controls
        $result = queryMysql("select * from controls c where c.model_id=" . $model['id']);
        if ($result->num_rows == 1) {
            $controlsRec = $result->fetch_assoc();
            foreach ($controlsRec as $pk => $pv) if (!is_null($pv)) $model['param'][$pk] = $pv;
        }
        // добавляем ветку "levels"  из отдельной таблицы size_and_levels
        $result = queryMysql("select * from size_and_levels  where model_id=" . $model['id']);
        while ($levelsRec = $result->fetch_assoc()) {
            $aim_field = $levelsRec['aim_field'];
            foreach ($levelsRec as $pk => $pv) if ($pk !== "aim_field" && $pk !== "model_id" && !is_null($pv)) $model['levels'][$aim_field][$pk] = $pv;
            $model['levels'][$aim_field]['bar_0'] += $baseBarNum;
        }

        ksort($model['param']); // сортировка параметров по алфавиту для идинообразия при выводе
        $result = queryMysql("select s.status from status_map m left join statuses s on m.status_id=s.id where m.model_id=" . $model['id']);
        while ($statusRec = $result->fetch_assoc()) $model['status'][$statusRec['status']] = 0;

        $Models1[$model_ind_][] = $model;
    }
}
// перебор моделей второго алгоритма
for ($i = 0; $i < $n_rec; $i++)
    if ($tmp_ar[$i]['Alg'] == 2) {
        $baseBarNum = $barById[$tmp_ar[$i]['bar_id']];
        $model_ind_ = "p" . substr(("00000" . $baseBarNum), $index_length * (-1));
        $Alg_ = $tmp_ar[$i]['Alg'];
        //отбираем из массива только те, у которых все важные точки в пределах фрагмента чарта
        $isModelInside = true;
        foreach ($mainPointsList as $pk => $pv) { // перебор всех основных точек - смотрим, находятся ли они все внутри нашего фрагмента
            if (is_null($tmp_ar[$i][$pv])) continue;
            if (($tmp_ar[$i][$pv] + $baseBarNum) < 0 || ($tmp_ar[$i][$pv] + $baseBarNum) >= $numBars) {
                $isModelInside = false;
                break;
            }
        }
        if ($isModelInside) {
            $model = []; // начинаем формировать модель для выдачи
            foreach ($main_list as $pk => $pv) { // заполненние основных полей
                $key = $pv;
                if ($key == '_2' || $key == '_3') $key = substr($key, 1); // отсекаем "_"
                if (!is_null($tmp_ar[$i][$pv])) {
                    if (in_array($pv, $pointList)) $model[$key] = $tmp_ar[$i][$pv] + $baseBarNum;
                    else $model[$key] = $tmp_ar[$i][$pv];
                }
            }
            $model['id'] = $model['split'] = intval($tmp_ar[$i]['id']);
            foreach ($tmp_ar[$i] as $pk => $pv) if (!in_array($pk, $main_list) && !in_array($pk, $Presupp_list) && !in_array($pk, $service_list)) { // перебое всех полей, относящихся к Param
                if (!is_null($pv)) {
                    $val_ = $pv;
                    if ($pk == "alt") $val_ = intval($val_);
                    if (in_array($pk, $pointList)) $val_ = $val_ + $baseBarNum;
                    if ($pk == 'auxP6' || $pk == 'auxP6\'' || $pk == 'calcP6') $val_ = floatval($val_);
                    if ($pk == '_CT' || $pk == '_cross_point' || $pk == '_cross_point2') $val_ = CP_translate($baseBarNum, $val_);
                    if ($pk == '_points') $val_ = points_translate($Alg_, $val_, $baseBarNum);
                    $model['param'][$pk] = $val_;
                }
            }
            // добавляем к параметрам контрольные параметры из отдельной таблицы controls
            $result = queryMysql("select * from controls c where c.model_id=" . $model['id']);
            if ($result->num_rows == 1) {
                $controlsRec = $result->fetch_assoc();
                foreach ($controlsRec as $pk => $pv) if (!is_null($pv)) $model['param'][$pk] = $pv;
            }
            ksort($model['param']); // сортировка параметров по алфавиту для единообразия при выводе
            $result = queryMysql("select s.status from status_map m left join statuses s on m.status_id=s.id where m.model_id=" . $model['id']);
            while ($statusRec = $result->fetch_assoc()) $model['status'][$statusRec['status']] = 0;
            // добавляем ветку-массив из одного элемента Presupp (если таковой имеется)
            if (!is_null($tmp_ar[$i]['t1p'])) {
                $t1p_ = ($tmp_ar[$i]['t1p'] ?? 0) + $baseBarNum;
                $t2p_ = ($tmp_ar[$i]['t2p'] ?? 0) + $baseBarNum;
                $t3p_ = ($tmp_ar[$i]['t3p'] ?? 0) + $baseBarNum;
                $t4p_ = ($tmp_ar[$i]['t4p'] ?? 0) + $baseBarNum;
                $confirmT1p_ = ($tmp_ar[$i]['confirmT1p'] ?? 0) + $baseBarNum;
                $model['Presupp'][] = ['t1p' => $t1p_, 't3p' => $t3p_, 't2p' => $t2p_, 't4p' => $t4p_, 'confirmT1p' => $confirmT1p_];
            }
            $result = queryMysql("select prev_model_id,P6,type,m.bar_id from idprevs_map map left join models m on map.prev_model_id=m.id where map.model_id=" . $model['id']);
            while ($idprevsRec = $result->fetch_assoc()) {
                if (isset($barById[$idprevsRec['bar_id']])) {
                    $bar_ = $barById[$idprevsRec['bar_id']];
                    $ind_ = "p" . substr(("00000" . $bar_), $index_length * (-1));
                    $ind2 = 99;
                    if (isset($Models1[$ind_])) foreach ($Models1[$ind_] as $pk => $pv) if ($pv['id'] == $idprevsRec['prev_model_id']) {
                        $ind2 = $pk;
                        break;
                    }
                    if ($ind2 == 99) $res['Errors'][] = "ERROR IDPrevs : ind_=99 model_id=" . $model['id'];
                    else $model['IDprevs'][] = "m1-" . $ind_ . "-" . $ind2 . "-p" . ($idprevsRec['P6'] + $bar_) . "-" . $idprevsRec['type'];
                } else $res['Errors'][] = "ERROR IDPrevs model_id=" . $model['id'] . " idprevsRec['bar_id']=" . $idprevsRec['bar_id'];
            }
            $result = queryMysql("select inner_model_id,m.bar_id from idinners_map map left join models m on map.inner_model_id=m.id where map.model_id=" . $model['id']);
            while ($idinnersRec = $result->fetch_assoc()) {
                if (isset($barById[$idinnersRec['bar_id']])) {
                    $bar_ = $barById[$idinnersRec['bar_id']];
                    $ind_ = "p" . substr(("00000" . $bar_), $index_length * (-1));
                    $ind2 = 99;
                    if (isset($Models1[$ind_])) foreach ($Models1[$ind_] as $pk => $pv) if ($pv['id'] == $idinnersRec['inner_model_id']) {
                        $ind2 = $pk;
                        break;
                    }
                    if ($ind2 == 99) $res['Errors'][] = "ERROR IDinners : ind_=99 model_id=" . $model['id'];
                    else $model['IDinners'][] = "m1-" . $ind_ . "-" . $ind2;
                } else $res['Errors'][] = "ERROR IDinners : model_id=" . $model['id'] . " idinnersRec['bar_id']=" . $idinnersRec['bar_id'];
            }
            // добавляем ветку "levels"  из отдельной таблицы size_and_levels
            $result = queryMysql("select * from size_and_levels  where model_id=" . $model['id']);
            while ($levelsRec = $result->fetch_assoc()) {
                $aim_field = $levelsRec['aim_field'];
                foreach ($levelsRec as $pk => $pv) if ($pk !== "aim_field" && $pk !== "model_id" && !is_null($pv)) $model['levels'][$aim_field][$pk] = $pv;
                $model['levels'][$aim_field]['bar_0'] += $baseBarNum;
            }


            //select prev_model_id,P6,type,m.bar_id from idprevs_map map
            //left join models m on map.prev_model_id=m.id
            //where map.model_id=66350;

            $Models2[$model_ind_][] = $model;
        }
    }


$res['Models'] = $Models1;
// $res['Models2']=$Models2;
$res['Models2'] = isset($Models2) ? $Models2 : [];
unset($res['Error']);
die();
function CP_translate($baseBarNum, $CP)
{ // переводит (транслирует) строку для Кросс-поинт и пр. (формат "<дробный номер бара> (<Уровень пересечения >)" относительно опорного баоа - меняется только число до скобок
    $pos = strpos($CP, "(");
    $bar = substr($CP, 0, $pos - 1);
    //file_put_contents("TMP_err1__.txt","bar: ($bar) baseBarNum: ($baseBarNum) $CP: ($CP)".PHP_EOL,FILE_APPEND);
    return (($bar + $baseBarNum) . substr($CP, $pos - 1));
}
function points_translate($algNum, $_points, $baseBarNum)
{ // переводит (транслирует) строку для _points  в относительные координаты
    $pArr = explode(" ", $_points);
    $arP = [];
    $arV = [];
    $out = "";
    for ($i = 0; $i < count($pArr); $i++) {
        $ar = explode(":", $pArr[$i]);
        $arP[] = $ar[0];
        $arV[] = $ar[1];
        $out .= " " . $ar[0] . ":" . ($arV[$i] + $baseBarNum);
    }
    return (substr($out, 1));
}
