<?php
// функция для расчета совпадений по уровням (Модель 1 -- Модель 2) согласно ТЗ
// tool - наименование инструмента (напр. EURUSD), если он указан, то считаем только по данному инструменту (все таймфремы)
//  если указано "ALL", то считает по всей базе, предварительно очищая таблицу matches
// 25.02.2022 добавлен дополнительный функционал для расчета параметров моделей и сводных параметров с учетом предсказаний нейронных сетей
//
ini_set('date.timezone', 'Europe/Moscow');
ini_set('memory_limit', '4096M');
set_time_limit(0);
define("WRITE_LOG", 3); // уровень логирования 0-нет 9 - максимальный
define("LOG_FILE", "calc_matches.log"); //
// 25.02.2002 - добавлено: определение вес1,вес2,вес3,вес4 для расчета "результирующего параметра" коэфф.X*вес1  +  Slvl*вес2 + St*вес3 + Range*вес4) / (знак деления) 4 (кол-во параметров)
define("WEIGHT_1", 1); // нужно установить значение!!!!!!!!!!!!!!!!!!!!!!!!
define("WEIGHT_2", 1); // нужно установить значение!!!!!!!!!!!!!!!!!!!!!!!!
define("WEIGHT_3", 1); // нужно установить значение!!!!!!!!!!!!!!!!!!!!!!!!
define("WEIGHT_4", 1); // нужно установить значение!!!!!!!!!!!!!!!!!!!!!!!!

// определение настроек для уровней - в % от рамера модели
define("LVL_APPROACH", 6); // подход к уровню МП
// define("LVL_LOST", 70); // потеря уровня МП
define("LVL_LOST", 85); // потеря уровня МП
define("TIME_DEPTH", 100); // один размер по времени, отложенный от времени расчётной т.6
define("YI_PROC", 3); // максимальное расстояние между уровнями для варианта 1 (в %)
define("YII_PROC", 3); // максимальное расстояние между уровнями для варианта 2 (в %)

require_once 'login_log.php';
ob_start();

$pointsList = [
    "2",
    "3",
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
]; // служебный константа-массив - список всех возможных точкек модели - используется для того, чтобы отклонять модели, которые отличаются только точкой t5
$err_cnt = 0;
$ems = [];
$res = []; // возворащаемый результат json
$res['Error'] = 'Error_01';
$res['Errors'] = [];
$baseBarNum = 0; // номер опорного бара для текущей модели
$chartNumById = []; // ассоциативный массив, возвращает номер (индекс) в массиве chart по bar_id
$lastBarPortion = 0; // сколько баров загрузили в крайний раз (типа, дошли до конца или что-то осталось еще)
$Chart = []; // чарт - массив баров для текущего инструмента и таймфрейма (name_id)
$AimsAndSizes = []; // массив целей и размеров для моделей (P6,P6",auxP6,auxP6' - для текущего инструмента и таймфрейма (name_id)
$aimList = ["P6", 'P6"', "auxP6", "auxP6'"]; // перечень наименований целей

$res['info']['type'] = '_GET';
$PARAM = $_GET;
if (WRITE_LOG > 0) $f_header = fopen(LOG_FILE, 'w');
if (isset($_POST['tool'])) {
    $res['info']['type'] = '_POST';
    $PARAM = $_POST;
}

write_log(date("Y-m-d H:i:s") . " start" . PHP_EOL, 1);

// формируем асс.массив по всем интсрументам и таймфремам (ТФ), по которым в БД есть модели
$ourTools = []; // по каждому названию инструмента массив таймфремов по возрастанию и соответвующий (такой же длины) массив name_id (id в таблице chart_names)
$toolBy_name_id = []; // по name_id хранится tool (напр. 'EURUSD')
$TfBy_name_id = []; // по name_id хранится ТФ (напр. 60 для часовика)
$nameBy_name_id = []; // по name_id хранится name (напр. EURUSD60 для часовика)
$result = queryMysql("select tool,name,timeframe*1 tf,id name_id from chart_names where id in (select distinct name_id from models) order by tool,timeframe*1;");
$nameIdlistStr = "";
while ($rec = $result->fetch_assoc()) {
    if (!isset($ourTools[$rec['tool']])) $ourTools[$rec['tool']] = ["name_id" => [], "tf" => [], "name_id_list" => ""];
    $ourTools[$rec['tool']]["name_id"][] = intval($rec['name_id']);
    $ourTools[$rec['tool']]["tf"][] = intval($rec['tf']);
    if ($ourTools[$rec['tool']]["name_id_list"]) $ourTools[$rec['tool']]["name_id_list"] .= ",";
    $ourTools[$rec['tool']]["name_id_list"] .= intval($rec['name_id']); // добавляем в список новый таймфрейм по данному инструменту
    $toolBy_name_id[intval($rec['name_id'])] = $rec['tool'];
    $nameBy_name_id[intval($rec['name_id'])] = $rec['name'];
    $TfBy_name_id[intval($rec['name_id'])] = intval($rec['tf'] / 60); // приводим к минутам (в БД почему то в секундах хранится)
}


//$add_where = "";
$cur_id = 0;
$isALL = false;
if (isset($PARAM['tool']) && (isset($ourTools[$PARAM['tool']]) || $PARAM['tool'] == "ALL")) {

    if ($PARAM['tool'] == "ALL") $isALL = true;
    else $tool = SanitizeString($PARAM['tool']);
} else {
    $res['Errors'][] = "Не задан параметр tool (инструмент) либо по заданному инструменту нет моделей в базе";
    die();
}

// * Creating Matches table if it doesn't exists yet
$query = <<<END
CREATE TABLE IF NOT EXISTS `matches` (
  `model_id` int(11) NOT NULL,
  `m1_G1` varchar(32) NOT NULL,
  `type` enum('clstI','clstII','clstA','clstI_E','clstII_E','clstA_E') COLLATE utf8_unicode_ci NOT NULL,
  `aim_name` enum('P6','P6"','auxP6','auxP6''') COLLATE utf8_unicode_ci NOT NULL,
  `link_id` int(11) NOT NULL,
  `link_aim_name` enum('P6','P6"','auxP6','auxP6''') COLLATE utf8_unicode_ci NOT NULL,
  `tf1` int(11) NOT NULL,
  `tf2` int(11) NOT NULL,
  `X` float NOT NULL DEFAULT 1,
  `Slvl` float NOT NULL,
  `St` float NOT NULL,
  `Range` float NOT NULL,
  `Koef` float NOT NULL,
  KEY `model` (`model_id`),
  KEY `link` (`link_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
END;
$result = queryMysql($query);

$aim_name2dop = ["P6" => "", "P6\"" => "\"", "auxP6" => "_AuxP6", "auxP6'" => "_AuxP6'"];
$setNullStr = "";
foreach ($aim_name2dop as $pk => $pv) {
    $setNullStr .= ",`Clst_I$pv`=null,`Clst_II$pv`=null,`Clst_A$pv`=null,`ContextClstI$pv`=null,`ContextClstII$pv`=null";
    $setNullStr .= ",`Clst_I_E$pv`=null,`Clst_II_E$pv`=null,`Clst_A_E$pv`=null,`ContextClstI_E$pv`=null,`ContextClstII_E$pv`=null";
}
$setNullStr = substr($setNullStr, 1); // отсекаем символ "запятая" слева
if ($isALL) {
    $result = queryMysql("truncate table matches;");
    $result = queryMysql("update models set $setNullStr;");
} else {
    $result = queryMysql("delete from matches where model_id in (select id from models where name_id in (" . $ourTools[$tool]['name_id_list'] . "));");
    $result = queryMysql("update models set $setNullStr where name_id in (" . $ourTools[$tool]['name_id_list'] . ");");
}
write_log("Start MGU=" . memory_get_usage() . PHP_EOL, 1);
$ContextClst = []; // массив, где функция calcMatches сохранит значения ContextClst (I,II,I_E,II_E) для каждой модели
$TFs = []; // тут храним всю инфу (чарты, модели, размеры, уровни для текущего инструмента - для каждого ТФ отдельный ассоциативный массив, т.е. TF - "список из асс.массивов"
foreach ($ourTools as $cur_tool => $toolInfo) if ($isALL || $cur_tool == $tool) { // перебор всех инструментов (выполнится один раз, если задан конкретный)
    write_log(PHP_EOL . date("Y-m-d H:i:s") . " ***** Начинаем спаривать модели по $cur_tool  MGU=" . memory_get_usage() . PHP_EOL . PHP_EOL, 1);
    unset($TFs); // освобождаем память от предыдущего инструмента
    unset($ContextClst); // освобождаем память от всех ContextClst
    $ContextClst = [];
    $TFs = [];
    foreach ($toolInfo['name_id'] as $name_id) { // перебор всех ТФ по текущему инструменту
        $Chart = [];
        $barIdByDandt = []; // получение id бара по dandt
        $AimsAndSizes = [];
        write_log(PHP_EOL . date("Y-m-d H:i:s") . " ***** Начинаем спаривать модели для name_id=$name_id " . $nameBy_name_id[$name_id] . "  MGU=" . memory_get_usage() . PHP_EOL . PHP_EOL, 1);
        $result = queryMysql("select id,dandt,o,c,h,l from charts where name_id=$name_id order by id;");
        $bar_cnt = 0;
        while ($rec = $result->fetch_assoc()) {
            $id = intval($rec['id']);
            $Chart[$id] = [];
            $Chart[$id]['o'] = floatval($rec['o']);
            $Chart[$id]['c'] = floatval($rec['c']);
            $Chart[$id]['h'] = floatval($rec['h']);
            $Chart[$id]['l'] = floatval($rec['l']);
            $Chart[$id]['dandt'] = $rec['dandt']; //strtotime($rec['dandt']);
            $barIdByDandt[$rec['dandt']] = $id;
            $bar_cnt++;
        }
        $result->close();
        write_log("Прочитали чарт для name_id=$name_id " . $nameBy_name_id[$name_id] . " количество баров: $bar_cnt  MGU=" . memory_get_usage() . PHP_EOL, 1);
        $result = queryMysql("select * from models where name_id=$name_id;", FALSE, MYSQLI_USE_RESULT);
        $model_cnt = 0;
        while ($model = $result->fetch_assoc()) {
            $id = intval($model['id']);
            if (is_null($model['G1']) && is_null($model['auxP6'])) {
                write_log("Игнорируем модель [$id] т.к. G1=null, auxP6=null " . PHP_EOL, 3);
            } else if ($AAS = formAimsAndSizes($Chart, $model, $nameBy_name_id[$name_id])) {
                $AimsAndSizes[] = $AAS;
                $model_cnt++;
            }
        }
        $result->close();
        write_log("Сформировали массив Целей_И_Размеров моделей для name_id=$name_id " . $nameBy_name_id[$name_id] . " количество: $model_cnt  MGU=" . memory_get_usage() . PHP_EOL, 1);

        // сортируем массив по располажению самого раннего узла и (предварительно его определяем)
        foreach ($AimsAndSizes as $ind => $model) {
            $left_node = 1000000;
            for ($i = 0; $i < count($aimList); $i++) {
                if (isset($model[$aimList[$i]]) && $model[$aimList[$i]]['node_bar'] < $left_node) $left_node = $model[$aimList[$i]]['node_bar'];
            }
            $AimsAndSizes[$ind]['left_node'] = $left_node;
        }
        usort($AimsAndSizes, "sort_by_t4");
        $TFs[] = ["name_id" => $name_id, "Chart" => $Chart, "AimsAndSizes" => $AimsAndSizes, "barIdByDandt" => $barIdByDandt];  // добравляем в список структур полную инфу по очередному name_id текущего инструмента
    } // перебор всех name_id по текущему инструменту
    $TF_cnt = count($TFs);
    write_log(PHP_EOL . "Вызов функции расчета кластеров (calcMatches) для инструмента $cur_tool -  количество таймфреймов: $TF_cnt  MGU=" . memory_get_usage() . PHP_EOL, 1);

    calcMatches($TFs); // поиск совпадений по первому и второму вариантам и заполнение БД - табл. matches

    // заполняем поля Clst в таблице models
    // но вначале удаляем записи из matches где model1 имеет признак bad 1 или 2
    $result = queryMysql("delete from matches where type in ('clstA','clstA_E') and model_id in (select id from models where name_id in (" . $ourTools[$cur_tool]['name_id_list'] . ") and bad in (1,2));");
    $result = queryMysql("delete from matches where type not in ('clstA','clstA_E') and link_id in (select id from models where name_id in (" . $ourTools[$cur_tool]['name_id_list'] . ") and bad in (1,2));");


    //    // 2023-02-28 (временно) вначале старый запрос (без расщепления на типы P6 (из него пока берем Clst_A и Context расчет которых не поменялся)
    //    $query="select model_id,sum(if(`type`='clstI',1,0)) Clst_I,sum(if(`type`='clstII',1,0)) Clst_II,sum(if(`type`='clstA',1,0)) Clst_A,sum(if(`type`='clstI_E',1,0)) Clst_I_E,sum(if(`type`='clstII_E',1,0)) Clst_II_E,sum(if(`type`='clstA_E',1,0)) Clst_A_E ".
    //    "from matches mt left join models m on m.id=mt.model_id where m.name_id in (".$ourTools[$cur_tool]['name_id_list'].") group by model_id;";
    //    $result=queryMysql($query);
    //    $ClstTable=[];
    //    while ($rec = $result->fetch_assoc()){
    //        $model_id=intval($rec['model_id']);
    //        foreach($rec as $pk=>$pv)if($pk!=='model_id')$ClstTable[$model_id][$pk]=$pv; // проставляем 6 значений (число Clst) для каждой модели текущего инструмента
    //    }
    //    $result->close();
    //    foreach($ClstTable as $model_id=>$clstArr){
    //        // НЕ проставляем счетчики кластеров (раньше было свернуто, теперь в разбивке- в след.блоке
    //        $setList="";
    //        //foreach($clstArr as $clstName=>$clstCnt)$setList.=",$clstName=$clstCnt";
    //
    //        // проставляем параметры ContextClst
    //        foreach($clstArr as $clstName=>$clstCnt)
    //        if(strcmp(substr($clstName,0,6),'Clst_A')!==0){
    //            $val_=round(($ContextClst[$model_id][substr($clstName,5)]??0)*100);
    //            if($val_>32000)$val_=32000;
    //            $setList.=",ContextClst".substr($clstName,5)."=$val_";
    //        }
    //        $setList=substr($setList,1);
    //        write_log("setList_old : ",$setList.PHP_EOL,9);
    //        $result=queryMysql("update models set $setList where id=$model_id;");
    //    }

    // 2023-03-01 новый блок №1 - заполняем новый блок параметров типа Clst_I и Clst_II (кроме Clst_A...) и ContextClst в разбивке на типы P6 согласно ТЗ
    // группировка по цели модели 2 (прямые кластеры, т.е. для модели 2 ищем соответвуеющие модели 1)

    $query = "select model_id,aim_name,sum(if(`type`='clstI',1,0)) Clst_I,sum(if(`type`='clstII',1,0)) Clst_II,sum(if(`type`='clstI_E',1,0)) Clst_I_E,sum(if(`type`='clstII_E',1,0)) Clst_II_E," .
        "sum(if(`type`='clstA',1,0)) Clst_A,sum(if(`type`='clstA_E',1,0)) Clst_A_E " .
        "from matches mt left join models m on m.id=mt.model_id where m.name_id in (" . $ourTools[$cur_tool]['name_id_list'] . ") group by model_id,aim_name;";
    $result = queryMysql($query);
    $ClstTable = [];
    while ($rec = $result->fetch_assoc()) {
        $model_id = intval($rec['model_id']);
        $aim_name = $rec['aim_name'];
        foreach ($rec as $pk => $pv) if ($pk !== 'model_id' && $pk !== 'aim_name') $ClstTable[$model_id][$aim_name][$pk] = $pv; // проставляем 6 значений (число Clst) для каждой модели текущего инструмента
    }
    $result->close();
    // проставляем счетчики кластеров
    foreach ($ClstTable as $model_id => $clstArrAllP6) {
        $setList = "";
        foreach ($clstArrAllP6 as $aim_name => $clstArr) {
            foreach ($clstArr as $clstName => $clstCnt) {
                $clstName_DB = $clstName . $aim_name2dop[$aim_name];
                if ($setList) $setList .= ",";
                $setList .= "`$clstName_DB`=$clstCnt";
                if (substr($clstName, 0, 6) === "Clst_I") {
                    $val_ = round(($ContextClst[$model_id][substr($clstName, 5)][$aim_name] ?? 0) * 100);
                    if ($val_ > 32000) $val_ = 32000;
                    $setList .= ",`ContextClst" . substr($clstName, 5) . $aim_name2dop[$aim_name] . "`=$val_";
                }
            }
            write_log("setList1 : ", $setList . PHP_EOL, 9);
            $result = queryMysql("update models set $setList where id=$model_id;");
        }
    }

    //    // 2023-03-01 новый блок №2 - заполняем новый блок параметров типа Clst_A в разбивке на типы P6 (модели 1)согласно ТЗ
    //    // группировка по цели модели 2 (прямые кластеры, т.е. для модели 2 ищем соответвуеющие модели 1)
    //
    //    $query="select link_id,link_aim_name,sum(if(`type`='clstA',1,0)) Clst_A,sum(if(`type`='clstA_E',1,0)) Clst_A_E ".
    //        "from matches mt left join models m on m.id=mt.link_id where m.name_id in (".$ourTools[$cur_tool]['name_id_list'].") group by link_id,link_aim_name;";
    //    $result=queryMysql($query);
    //    $ClstTable=[];
    //    while ($rec = $result->fetch_assoc()){
    //        $link_id=intval($rec['link_id']);
    //        $link_aim_name=$rec['link_aim_name'];
    //        foreach($rec as $pk=>$pv)if($pk!=='link_id')$ClstTable[$link_id][$link_aim_name][$pk]=$pv; // проставляем 6 значений (число Clst) для каждой модели текущего инструмента
    //    }
    //    $result->close();
    //    // проставляем счетчики кластеров Clst_A...
    //    foreach($ClstTable as $link_id=>$clstArrAllP6) {
    //        $setList = "";
    //        foreach ($clstArrAllP6 as $link_aim_name => $clstArr) {
    //            foreach ($clstArr as $clstName => $clstCnt) {
    //                if (substr($clstName,0,6)==="Clst_A") {
    //                    $clstName_DB = $clstName.$aim_name2dop[$aim_name];
    //                    if($setList)$setList.=",";
    //                    $setList .= "`$clstName_DB`=$clstCnt";
    //                }
    //            }
    //            write_log("setList2 : ", $setList . PHP_EOL, 9);
    //            $result = queryMysql("update models set $setList where id=$link_id;");
    //        }
    //    }


} // перебор всех инструментов (либо 1 раз, если задан конкретный
write_log(PHP_EOL . date("Y-m-d H:i:s") . " end" . PHP_EOL, 1);
unset($res['Error']);
die();
//function prepareForMySQL($s){ // заменяет символы апострофа и обратного слэша - для использоваения в команде MySQL
//    return(str_replace("'","\'",str_replace("\\","\\\\",$s)));
//}
function calcMatches($TFs)
// получаем список структур по каждому ТФ в порядке возрастания
{
    // идея простая - проходим  циклом по индексам TFs (все ТФ, начиная с младшего $ind2 - от 0 до последнего(старшего) ТФ в массиве
    // после поиска кластеров в текущем (как было реализовано первоначально), перебираем все старшие таймфремы $ind1 (на предмет поиска Модели1)
    global $aimList, $nameBy_name_id, $TfBy_name_id, $ContextClst, $toolBy_name_id, $res;
    $res['debug']['mgu'] = number_format(memory_get_usage());
    for ($ind2 = 0; $ind2 < count($TFs); $ind2++) { // перебираем все ТФ для Модели2
        $TF2 = $TFs[$ind2]; // Асс.массив Модели2
        $name_id2 = $TF2['name_id'];
        write_log(" Расчет кластеров для Модели2  - name_id = $name_id2" . PHP_EOL, 5);
        for ($ind1 = $ind2; $ind1 < count($TFs); $ind1++) { //  вложенным циклом перебираем возможные ТФ для модели1 (такой-же, как у модели2 или старше
            $TF1 = $TFs[$ind1]; // Асс.массив для ТФ Модели1
            $name_id1 = $TF1['name_id'];
            write_log(" Ищем Модели1 - name_id = $name_id1" . PHP_EOL, 5);
            // рассчитываем коэффициент X (отношение ТФ модели2  (ТФ2) к ТФ модели1 (ТФ1) )
            $koefX = $TfBy_name_id[$name_id2] / $TfBy_name_id[$name_id1];
            if ($name_id1 == $name_id2) $koefX = 1; // для надежности, чтобы получить целочисленную 1, если на одном ТФ :)
            $AimsAndSizes2 = $TFs[$ind2]['AimsAndSizes']; // массив размеров и уровней кандидатов в модель2
            $AimsAndSizes1 = $TFs[$ind1]['AimsAndSizes']; // массив размеров и уровней кандидатов в модель1 // может быть тот же самый массив, просто с другим названием (если ищем на одном ТФ)
            $Chart2 = $TF2['Chart']; // чарт нужного ТФ для выбранных моделей2
            $Chart1 = $TF1['Chart']; // чарт нужного ТФ для выбранных моделей1
            $model2_cnt = count($AimsAndSizes2);  // сколько кандидатов в модели2
            $model1_cnt = count($AimsAndSizes1);  // сколько кандидатов в модели2
            $cnt = 0;
            for ($i = 0; $i < $model2_cnt - 1; $i++) { // перебор всех кандидатов в модели2
                //write_log("___ new i=$i ($model2_cnt) ".microtime(true)." cnt=($cnt) ".PHP_EOL,5);
                $cnt = 0;
                $id2 = $AimsAndSizes2[$i]['id'];
                if ($AimsAndSizes2[$i]['G1'] == "WEDGE") continue; // Модель 2 "клином" быть не должна
                foreach ($aimList as $pk2 => $aimName2) if (isset($AimsAndSizes2[$i][$aimName2])) { // перебираем все наименования цели Модели_2
                    // приводим номер бара узла модели 2 к номерам (индексам баров) модели 1 - все бары вначале переводим к нужному ТФ (получаем время округляя в прошлое до ТФ1) а потом по дате-времени бара определяем его индекс в Чарте1
                    if ($koefX == 1) $base2_bar = $AimsAndSizes2[$i]['bar_id'];
                    else {
                        $beginOfBar_ = beginOfBar($Chart2[$AimsAndSizes2[$i]['bar_id']]['dandt'], $TfBy_name_id[$name_id1]);
                        $base2_bar = $TF1['barIdByDandt'][$beginOfBar_];
                    } // получаем трансляцию опорного бара модели2 на ТФ1
                    $node2_bar = $base2_bar + $AimsAndSizes2[$i][$aimName2]['node_bar'] * $koefX; // получаем трансляцию бара узла на ТФ1 (дробное значение)
                    $node2_level = $AimsAndSizes2[$i][$aimName2]['node_level'];
                    $node2_size_time = $AimsAndSizes2[$i][$aimName2]['size_time'] * $koefX; // пересчитываем размер с учетом множителя
                    $node2_size_level = $AimsAndSizes2[$i][$aimName2]['size_level'];

                    // определение нчала интервала, где есть смысл искать кластеры
                    $j_l = $j_m = 0;
                    $j_r = $model1_cnt - 1;
                    $t4_comp = $base2_bar + $AimsAndSizes2[$i]['t4'] * $koefX; // t4  Модели2 "транспониранная" на Чарт Модели1
                    while (($j_r - $j_l) > 17) { // просто небольшое число - если диапозон достаточно сузился, то "уполовинечание" прекращаем
                        $j_m = round(($j_r + $j_l) / 2);
                        $t4_j = $AimsAndSizes1[$j_m]['bar_id'] + $AimsAndSizes1[$j_m]['t4'];
                        if (($t4_j + 1200) < $t4_comp) $j_l = $j_m;  // накидываем 1000 баров - то есть перебор будем делать начиная с модели t4 которой на 1000 баров левее
                        else $j_r = $j_m;
                    }
                    $j_m = max($j_m - 25, 0);
                    for ($j = $j_m; $j < $model1_cnt; $j++) { // перебор кандидатов в Модель1 (Для спаривания с текущим кандидатом в Модель2)

                        $id1 = $AimsAndSizes1[$j]['id'];
                        $base1_bar = $AimsAndSizes1[$j]['bar_id'];
                        if ($base1_bar < ($base2_bar - 1000)) continue; // т.к. модель ограничена 500 баров, то пропускаем модель1, если ее опорная точка более 1000 баров слева
                        // вначале первичный отсев
                        if (($base1_bar + $AimsAndSizes1[$j]['t4']) >= ($base2_bar + $AimsAndSizes2[$i]['t4'] * $koefX)) break; // прерываем цикл по Моделям1 если t4 модели1 правее t4 модели2 (т.к. массив отсортирован по t4)
                        $cnt++;
                        foreach ($aimList as $pk1 => $aimName1) if (isset($AimsAndSizes1[$j][$aimName1])) { // перебираем все наименования цели кондидата в Модель1

                            $node1_bar = $base1_bar + $AimsAndSizes1[$j][$aimName1]['node_bar']; // получаем трансляцию бара узла на ТФ1 (дробное значение)
                            $node1_level = $AimsAndSizes1[$j][$aimName1]['node_level'];
                            $node1_size_time = $AimsAndSizes1[$j][$aimName1]['size_time'];
                            $node1_size_level = $AimsAndSizes1[$j][$aimName1]['size_level'];
                            // * Сравнение положений узлов моделей 1 и 2
                            if (($node1_bar + TIME_DEPTH * $node1_size_time * $koefX / 100) < $node2_bar) continue; // узел Модели2 дальше глубины поиска - пропускаем
                            if ($koefX == 1) { // если работаем на одином таймфрейме, то проверяем, что модели не клоны по основным точкам
                                if (
                                    $AimsAndSizes2[$i][$aimName2]['bar_0'] == $AimsAndSizes1[$j][$aimName1]['bar_0']
                                    && abs(($AimsAndSizes2[$i][$aimName2]['lvl_0'] - $AimsAndSizes1[$j][$aimName1]['lvl_0']) / $AimsAndSizes2[$i][$aimName2]['lvl_0']) < 0.001
                                    && abs(($AimsAndSizes2[$i][$aimName2]['size_time'] - $AimsAndSizes1[$j][$aimName1]['size_time']) / $AimsAndSizes2[$i][$aimName2]['size_time']) < 0.001
                                ) {
                                    write_log("ВАРИАНТ  ($id1/$aimName1) <-> ($id2/$aimName2) отклонен так как модели имеют общие ключевые точки" . PHP_EOL, 7);
                                    continue;
                                }
                                //write_log("TMP  сравнение isModelsAreDifferent для ".$AimsAndSizes[$i]['id']." и ".$AimsAndSizes[$j]['id'].PHP_EOL,9);
                                if (!isModelsAreDifferent($AimsAndSizes2[$i], $AimsAndSizes1[$j])) {
                                    write_log("ВАРИАНТ  ($id1/$aimName1) <-> ($id2/$aimName2) отклонен так как модели отличаются только точкой t5" . PHP_EOL, 7);
                                    continue;
                                }
                            }
                            // 26.02.2022 - добавлен блок для расчета параметров кластера для сохранения в БД
                            $tf1 = $TfBy_name_id[$name_id1];
                            $tf2 = $TfBy_name_id[$name_id2];
                            $Slvl = $node1_size_level / $node2_size_level;
                            $St = ($AimsAndSizes1[$j]['G1'] == 'EAM' ? ($AimsAndSizes1[$j]['t4'] - $AimsAndSizes1[$j]['t2']) : ($AimsAndSizes1[$j]['t4'] - $AimsAndSizes1[$j]['t1'])) * $koefX /
                                ($AimsAndSizes2[$i]['G1'] == 'EAM' ? ($AimsAndSizes2[$i]['t4'] - $AimsAndSizes2[$i]['t2']) : ($AimsAndSizes2[$i]['t4'] - $AimsAndSizes2[$i]['t1']));
                            $Range = (($base2_bar + $AimsAndSizes2[$i]['t4'] * $koefX) - ($base1_bar + $AimsAndSizes1[$j]['t4'])) / $node2_size_time;
                            $Koef = (WEIGHT_1 / $koefX  +  $Slvl * WEIGHT_2 + $St * WEIGHT_3 + WEIGHT_4 / $Range) / 4;


                            // * проверяем ВАРИАНТ 1
                            if (abs($node1_level - $node2_level) < $node2_size_level * YI_PROC / 100) { //3. Расстояние между уровнями моделей 1 и 2 находится в пределах YI, где YI - величина в % от размера Модели 2 (по умолчанию = 3%).
                                write_log("ВАРИАНТ 1 ($id1/$aimName1) <-> ($id2/$aimName2) найден кластер! узел1: \"$node1_bar ($node1_level)\"  узел2: \"$node2_bar ($node2_level)\"" . PHP_EOL, 7);
                                $clstFieldName = ($koefX == 1) ? "clstI" : "clstI_E";
                                $clstInd = ($koefX == 1) ? "I" : "I_E"; // индекс для ContextClst

                                $result1 = queryMysql("insert into matches (model_id,m1_G1,type,aim_name,link_id,link_aim_name,tf1,tf2,X,Slvl,St,`Range`,Koef) VALUES($id2,'" . $AimsAndSizes1[$j]['G1'] . "','$clstFieldName','" . str_replace("'", "\'", $aimName2) . "',$id1,'" . str_replace("'", "\'", $aimName1) . "',$tf1,$tf2,$koefX,$Slvl,$St,$Range,$Koef)", true);
                                if (($ContextClst[$id2][$clstInd][$aimName2] ?? 0) < $node1_size_level / $node2_size_level) $ContextClst[$id2][$clstInd][$aimName2] = $node1_size_level / $node2_size_level;
                            } // по уровням ОК (вар.2)

                            // * проверяем ВАРИАНТ 2
                            if ($AimsAndSizes2[$i]['v'] !== $AimsAndSizes1[$j]['v']) continue; // для ВАРИАНТА 2 необходимо, чтобы Молель 1 и 2 были обе "low" либо обе "high"
                            if (abs($node1_level - $node2_level) < $node1_size_level * YII_PROC / 100) {
                                // определяем, что уровни подхода и уровни потери Модели_1 не были достигнуты до узла Модели_2
                                $v = $AimsAndSizes2[$i]['v'];
                                $K = ($v == "low") ? 1 : -1;
                                $CP_level = $node1_level * $K;
                                $approachLevel = $CP_level - $node1_size_level * LVL_APPROACH / 100;
                                $lostLevel = $CP_level - $node1_size_level * LVL_LOST / 100;
                                $isApproachOrLost = false;
                                for ($bar = $AimsAndSizes1[$j][$aimName1]['loop_from'] + $AimsAndSizes1[$j]['bar_id']; isset($Chart1[$bar]) && $bar <= $node2_bar; $bar++) {
                                    if (high($Chart1, $bar, $v) > $approachLevel) {
                                        write_log("ВАРИАНТ 2 ($id1/$aimName1) <-> ($id2/$aimName2) отклонен так как достигли уровня ПОДХОДА на баре $bar (" . $Chart1[$bar]['dandt'] . ")" . PHP_EOL, 7);
                                        $isApproachOrLost = "Approach";
                                        break;
                                    }
                                    if (low($Chart1, $bar, $v) < $lostLevel) {
                                        write_log("ВАРИАНТ 2 ($id1/$aimName1) <-> ($id2/$aimName2) отклонен так как достигли уровня ПОТЕРИ на баре $bar (" . $Chart1[$bar]['dandt'] . ")" . PHP_EOL, 7);
                                        $isApproachOrLost = "Lost";
                                        break;
                                    }
                                }
                                if ($isApproachOrLost === false) {
                                    write_log("ВАРИАНТ 2 ($id1/$aimName1) <-> ($id2/$aimName2) найден кластер! узел1: \"$node1_bar ($node1_level)\"  узел2: \"$node2_bar ($node2_level)\"" . PHP_EOL, 7);
                                    $clstFieldName = ($koefX == 1) ? "clstII" : "clstII_E";
                                    $clstInd = ($koefX == 1) ? "II" : "II_E";
                                    $result1 = queryMysql("insert into matches (model_id,m1_G1,type,aim_name,link_id,link_aim_name,tf1,tf2,X,Slvl,St,`Range`,Koef) VALUES($id2,'" . $AimsAndSizes1[$j]['G1'] . "','$clstFieldName','" . str_replace("'", "\'", $aimName2) . "',$id1,'" . str_replace("'", "\'", $aimName1) . "',$tf1,$tf2,$koefX,$Slvl,$St,$Range,$Koef)", true);
                                    if (($ContextClst[$id2][$clstInd][$aimName2] ?? 0) < $node1_size_level / $node2_size_level) $ContextClst[$id2][$clstInd][$aimName2] = $node1_size_level / $node2_size_level;
                                    $clstFieldName = ($koefX == 1) ? "clstA" : "clstA_E";
                                    $result1 = queryMysql("insert into matches (model_id,m1_G1,type,aim_name,link_id,link_aim_name,tf1,tf2,X,Slvl,St,`Range`,Koef) VALUES($id1,'" . $AimsAndSizes1[$j]['G1'] . "','$clstFieldName','" . str_replace("'", "\'", $aimName1) . "',$id2,'" . str_replace("'", "\'", $aimName2) . "',$tf1,$tf2,$koefX,$Slvl,$St,$Range,$Koef)", true);
                                }
                            } // по уровням ОК (вар.2)
                        } // перебираем все наименования цели кондидата в Модель1
                    } // перебор кандидатов в Модель_1 (Для спаривания с текущим кандидатом в Модель_2)
                } // перебираем все наименования цели кандидата Модели_2
            } // перебор всех кандидатов в Модели2
        } // перебор всех ТF не младше ТФ модели2 (для модели1) -  ТФ такой-же или старше
    } // перебор всех ТF (для модели2)
    return;
}
function isModelsAreDifferent($model1, $model2)
{
    global $pointsList;
    foreach ($pointsList as $pointName) if ($pointName !== 't5') {
        $point1 = isset($model1[$pointName]) ? $model1[$pointName] : "not";
        $point2 = isset($model2[$pointName]) ? $model2[$pointName] : "not";
        if ($point1 !== $point2) return (true);
    }
    return (false);
}
function sort_by_t4($model1, $model2) // сортировка по возрастанию т4
{
    if (($model1['bar_id'] + $model1['t4']) < ($model2['bar_id'] + $model2['t4'])) return (-1);
    if (($model1['bar_id'] + $model1['t4']) > ($model2['bar_id'] + $model2['t4'])) return (1);
    return (0);
}
function formAimsAndSizes($Chart, $model, $name)
{ // расчет узлов для модели
    global $res, $isALL, $Chart, $pointsList;
    static $cnt = 0;
    $cnt++;
    $result1 = $result2 = $result3 = $result4 = false;

    // копируем в модель для выдачи все необходимые поля исходной модели
    //    static $mainFieldsList=["id","name_id","bar_id","Alg","G1","v"];
    //    $outModel=[]; foreach($mainFieldsList as $pk=>$pv)if(isset($model[$pv]))$outModel[$pv]=$model[$pv];
    $outModel['id'] = intval($model['id']);
    $outModel['name_id'] = intval($model['name_id']);
    $outModel['bar_id'] = intval($model['bar_id']);
    $outModel['G1'] = $model['G1'];
    $outModel['v'] = $model['v'];
    $outModel['name'] = $name;
    foreach ($pointsList as $pointName) if (isset($model[$pointName])) $outModel[$pointName] = $model[$pointName];

    if (!isset($Chart[intval($model['bar_id'])])) {
        $min_ = 100000000000;
        $max_ = 0;
        foreach ($Chart as $id_ => $bar_) {
            if ($id_ > $max_) $max_ = $id_;
            if ($id_ < $min_) $min_ = $id_;
        }
        $res['debug']['____min_bar_id'] = $min_;
        $res['debug']['____max_bar_id'] = $max_;
        $get_defined_vars_ = get_defined_vars();
        unset($get_defined_vars_['Chart']);
        file_put_contents("tmp_log/_______debug_" . shortFileName(__FILE__) . "_(" . __LINE__ . ").json", json_encode($get_defined_vars_, JSON_PARTIAL_OUTPUT_ON_ERROR)); // for debug only
    }
    write_log(PHP_EOL . $cnt . ") " . date("Y-m-d H:i:s") . " formAimsAndSizes: $name Model-" . $model['Alg'] . "-" . ($model['v'] == 'low' ? "low" : "high") . " G1='" . $model['G1'] . "' [" . $model['id'] . "]  bar_id=" . $model['bar_id'] . " (" . substr($Chart[intval($model['bar_id'])]['dandt'], 0, 16) . ")" . PHP_EOL, 6);

    if (!is_null($model["_cross_point"])) {
        $result1 = calcAimAndSize($Chart, $model, "ищем P6dev", $model["_cross_point"]);
        if ($result1) $outModel['P6'] = $result1;
    }

    if (!is_null($model['calcP6"'])) { // Для ВМП при наличии ЛТ через т.5” вычисляются параметры  P6aims" (полный аналог P6aims) и P6dev"
        $result2 = calcAimAndSize($Chart, $model, "ищем P6dev\"", $model["calcP6\"t"] . " (" . $model['calcP6"'] . ")");
        if ($result2) $outModel['P6"'] = $result2;
    }

    if (!is_null($model['auxP6'])) { // Для вспомогательных МП (т.е. для моделей AMEM, AMDBM, AMAM, AMforAM/DBM, AMforEM/DBM) рассчитываются следующие параметры- аналоги: auxP6aims (аналог P6aims) auxP6dev аналогичный P6dev
        $result3 = calcAimAndSize($Chart, $model, "ищем auxP6dev", $model["auxP6t"] . " (" . $model['auxP6'] . ")");
        if ($result3) $outModel['auxP6'] = $result3;
    }

    if (!is_null($model['auxP6\''])) { // Для вспомогательных моделей, построенных через т.5’ рассчитываются параметры auxP6aims’ – аналогичный P6bumper auxP6dev’ аналогичный P6dev )
        $result4 = calcAimAndSize($Chart, $model, "ищем auxP6dev'", $model["auxP6't"] . " (" . $model["auxP6'"] . ")");
        if ($result4) $outModel["auxP6'"] = $result4;
    }
    if (!($result1 || $result2 || $result3 || $result4)) return (false);
    return ($outModel);
}
function calcAimAndSize($Chart, $model, $title, $CP)
{ // функция для расчета контрольных параметров по одному набору точек (одна P6 - передается в параметре
    global $baseBarNum, $res, $Chart, $aimLevels;
    $G1 = $model['G1'];
    $Alg = $model['Alg'];
    $v = $model['v'];
    $K = ($v == 'low') ? 1 : -1; // множитель для зеркального переворачивания моделей high - НАДО НЕ ЗАБЫВАТЬ ЕГО ИСПОЛЬЗОВАТЬ ПРИ ИСПОЛЬЗОВАНИИ ЛЮБЫХ УРОВНЕЙ
    $baseBarNum = $model['bar_id'];

    write_log("--- calcAimsAndSize($title): G1='$G1' Alg=$Alg  v=$v CP='$CP'" . PHP_EOL, 7);

    $pos = strpos($CP, "(");
    $CP_bar = floatval(substr($CP, 0, $pos - 1));
    $CP_level = round(floatval(substr($CP, $pos + 1)) * $K, 5);
    $modelParams = [];
    $sizeLevel = 0;
    if (!$CP || $CP_level == 0 || $CP_bar < $model['t4']) {
        write_log("CP не определен либо левее Т4" . PHP_EOL, 4);
        return (false);
        //$CP_ok=false;
    }
    if ($model['G1'] == 'DBM' && $title == "ищем P6dev") {
        write_log("В модели DBM P6dev не определяем, выходим." . PHP_EOL, 4);
        return (false);
    }

    write_log("CP: '$CP' - bar=$CP_bar level=$CP_level" . PHP_EOL, 5);
    // определяем размер модели по уровням и времени
    if ($G1 == 'WEDGE') {
        // write_log(' REDALERT id =' . $model['id'] . ' t2 =' . $model['t2']. ' basebarnum = '. $baseBarNum, 0);
        $sizeLevel = high($Chart, $model['t2'] + $baseBarNum, $v) - low($Chart, $model['t1'] + $baseBarNum, $v);
        $sizeTime = $CP_bar - $model['t1']; //t1==0 всегда, написал  просто для порядка
        write_log(" G1=$G1 => размер модели по уровню = t2-t1 =" . high($Chart, $model['t2'] + $baseBarNum, $v) . " - " . low($Chart, $model['t1'] + $baseBarNum, $v) . " =" . round($sizeLevel, 6) . " по времени t6-t1 =" . $CP_bar . " - " . $model['t1'] . " = " . round($sizeTime, 6) . PHP_EOL, 9);
        $modelParams['bar_0'] = intval($model['t1']);
        $modelParams['lvl_0'] = min(abs(high($Chart, $model['t2'] + $baseBarNum, $v)), abs(low($Chart, $model['t1'] + $baseBarNum, $v)));
        $modelParams['loop_from'] = $first_bar_num = $model["t4"] + 1;
    } else
        if ($G1 == 'AM/DBM' || $G1 == 'AM') {
        $sizeLevel = $CP_level - low($Chart, $model['t1'] + $baseBarNum, $v);
        $sizeTime = $CP_bar - $model['t1']; //t1==0 всегда, написал  просто для порядка
        write_log("G1=$G1 => размер модели по уровню = t6-t1 =" . $CP_level . " - " . low($Chart, $model['t1'] + $baseBarNum, $v) . " =" . round($sizeLevel, 6) . " по времени t6-t1 =" . $CP_bar . " - " . $model['t1'] . " = " . round($sizeTime, 6) . PHP_EOL, 9);
        $modelParams['bar_0'] = intval($model['t1']);
        $modelParams['lvl_0'] = min(abs($CP_level), abs(low($Chart, $model['t1'] + $baseBarNum, $v)));
        $modelParams['loop_from'] = $first_bar_num = $model["t4"] + 1;
    } else {
        $sizeLevel = $CP_level - low($Chart, $model['t3'] + $baseBarNum, $v);
        $t2_fieldName = is_null($model['t2\'']) ? 't2' : 't2\'';
        $sizeTime = $CP_bar - $model[$t2_fieldName];
        write_log("G1=$G1 => размер модели по уровню = t6-t3 =" . $CP_level . " - " . low($Chart, $model['t3'] + $baseBarNum, $v) . " =" . round($sizeLevel, 6) . " по времени t6-$t2_fieldName =" . $CP_bar . " - " . $model[$t2_fieldName] . " = " . round($sizeTime, 6) . PHP_EOL, 9);
        $modelParams['bar_0'] = intval($model[$t2_fieldName]);
        $modelParams['lvl_0'] = min(abs($CP_level), abs(low($Chart, $model['t3'] + $baseBarNum, $v)));
        $modelParams['loop_from'] = $first_bar_num = $model["t5"];
    }
    $last_bar_num = $CP_bar + $sizeTime * TIME_DEPTH / 100;
    if ($last_bar_num > ($first_bar_num + 500)) {
        write_log("Слишком большая модель, выходим." . PHP_EOL, 3);
        return (false);
    }
    $modelParams['size_time'] = round($sizeTime, 3);
    $modelParams['size_level'] = round($sizeLevel, 5);
    $modelParams['node_bar'] = round($CP_bar, 3);
    $modelParams['node_level'] = abs($CP_level);

    if ($sizeLevel <= 0) return (false); // такое случается на AM/DBM

    return ($modelParams);
}
function lineLevel($line, $bar) // копия вспомогательной функции из первого алгоритма
{ // параметр $line - асс. массив $bar=опорные бар $level=значение (цены) на опорном баре, $angle - изменение значения (цены) за 1 бар
    return ($line['level'] + ($bar - $line['bar']) * $line['angle']);
}
function not_v($v)
{ // из high->low и наоборот
    return (($v == 'low') ? 'high' : 'low');
}

// набор функций, возвращающие open, close, high и low бара с номером $i (J для модели, где точка 1=low либо с отр. знаком, если high (чтобы не делать 2 копии алгоритма)
function high($Chart, $i, $v, $line = "0")
{ // возвращает high либо -low
    return (($v == 'low') ? $Chart[$i]['h'] : $Chart[$i]['l'] * (-1));
}

function low($Chart, $i, $v, $line = "0")
{ // возвращает high либо -low
    return (($v == 'low') ? $Chart[$i]['l'] * 1 : $Chart[$i]['h'] * (-1));
}

function open($Chart, $i, $v)
{ // возвращает high либо -low
    return (($v == 'low') ? $Chart[$i]['o'] : $Chart[$i]['o'] * (-1));
}

function close($Chart, $i, $v)
{ // возвращает high либо -low
    return (($v == 'low') ? $Chart[$i]['c'] : $Chart[$i]['c'] * (-1));
}
function beginOfBar($min_dandt_str, $period)
{ // определяет строку фомата даты для начала бара нужного периода по минутному бару
    global $res;
    $hh = substr($min_dandt_str, 11, 2); // чаcы
    $mm = substr($min_dandt_str, 14, 2);  // минуты
    $begin = substr($min_dandt_str, 0, 11);
    switch ($period) {
        case 1:
            return ($begin . $hh . ":" . $mm . ":00");
        case 5:
        case 15:
        case 30:
            $mm = "00" . intdiv($mm * 1, $period) * $period;
            $mm = substr($mm, strlen($mm) - 2, 2);
            return ($begin . $hh . ":" . $mm . ":00");
        case 60:
            return ($begin . $hh . ":00:00");
        case 240:
            $hh = "00" . intdiv($hh * 1, 4) * 4;
            $hh = substr($hh, strlen($hh) - 2, 2);
            return ($begin . $hh . ":00:00");
    }
    return ($begin . "00:00:00");
}
function shortFileName($f)
{ // отрезает путь к файлу слева
    $pos1 = strrpos($f, "\\");
    if ($pos1 === false) $pos1 = -1;
    $pos2 = strrpos($f, "/");
    if ($pos2 === false) $pos2 = -1;
    $pos = max($pos1, $pos2);
    return (substr($f, $pos + 1));
}
