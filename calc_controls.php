<?php
ini_set('date.timezone', 'Europe/Moscow');
set_time_limit(0);
ini_set('memory_limit', '2048M');
define("READ_MODEL_NUM", 3000); // по сколько моделей читаем из БД за раз
define("READ_BAR_NUM", 25000); // по сколько баров читаем из БД за раз
define("READ_BAR_BEFORE", 6000); // по сколько уходим влево при чтении очередной порции баров (зависит по скольку читали при загрузке базы)
define("WRITE_LOG", 1); // уровень логирования 0-нет 9 - максимальный
define("LOG_FILE", "calc_controls.log"); //
define("NEXT_NEEDED", false); // 2022-09-08 - добавлен флажок, показываеющий, что считаем "историю" и параметры "NEXT" считать не нужно (фактически, флаг - RT или "История")
define("REC_LIMIT_DEBUG", 10000000); // количество записей (ограничение для отладки)
define("PORTION_OF_READING", 50000); // по сколько записей моделей читаем за раз (на каждое формирование порции у меня уходит около 17 сек, поэтому очень маленькие порции нежелательны
define("NUM_OF_SECTION", 5); // число секций, на которые распределяем name_id

define("MIN_PIPS", 0.00001); // минимальный пипс (для Альпари минимальное изменение цены для большинства инструментов = 0.00001)

require_once 'login_log.php';
require_once 'inc_calc_controls.php'; // общая (для calc_controls.php, rt_calcModels.php, rt_mt4_interface.php) функция расчета размеров моделей для 4 возможных целей и контрольных параметров
// функция для расчета контрольных параметров по моделям первого и второго алгоритма
// параметры:
// modelId - id модели, если он указан, то считаются контрольные параметры только по ней и сохраняется лог
// nameId - id инструмента, если он указан, то считаем только по данному инструменту
//  если указано "ALL", то считает по всем
//
ob_start();

$err_cnt = 0;
$ems = [];
$res = []; // возворащаемый результат json
$res['Error'] = 'Error_01';
$res['Errors'] = [];
$Chart = []; // кусок графика (подкачивается по мере надобности)
$pipsByNameId = []; // храним размер пипса для каждого NameId что-бы каждый раз не пересчитывать (добавлено 2022-09-08)
$baseBarNum = 0; // номер опорного бара для текущей модели
$chartNumById = []; // ассоциативный массив, возвращает номер (индекс) в массиве chart по bar_id
$lastBarPortion = 0; // сколько баров загрузили в крайний раз (типа, дошли до конца или что-то осталось еще)
$res['info']['type'] = '_GET';
$res['info']['debugParams'] = [];
$PARAM = $_GET;
if (WRITE_LOG > 0) $f_header = fopen(LOG_FILE, 'w');
if (isset($_POST['nameId']) || isset($_POST['modelId'])) {
    $res['info']['type'] = '_POST';
    $PARAM = $_POST;
}

//$url = ((!empty($_SERVER['HTTPS'])) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
//$url = explode('?', $url);
//$url = $url[0];
//$pos=strrpos($url,"/");
//$url_path=$res['info']['url']=substr($url,0,$pos+1);

$add_where = "";
$cur_id = 0;
$isALL = false;
if (isset($PARAM['nameId'])) {
    $nameId = SanitizeString($PARAM['nameId']);
    $add_where = " where name_id=$nameId ";
    if (strtoupper($nameId) == "ALL") {
        $add_where = " where true ";
        $isALL = true;
    }
}
if (isset($PARAM['modelId'])) {
    $modelId = SanitizeString($PARAM['modelId']);
    $add_where = " where id=$modelId ";
    $isALL = false;
}
if ($add_where == "") {
    $res['Errors'][] = "Не заданы параметры modelId или nameID";
    die();
}
write_log(date("Y-m-d H:i:s") . " start" . PHP_EOL, 1);
if ($isALL) {
    $result = queryMysql("truncate table controls;");
    $result = queryMysql("truncate table size_and_levels;");
}
$columnsInModels = []; // список всех полей в таблице
$result = queryMysql("SHOW COLUMNS FROM models", true);
while ($rec = $result->fetch_assoc()) {
    //    $res['test fields'][]=$rec['Field'];
    $columnsInModels[$rec['Field']] = $rec;
}
$res['columns_in_model'] = $columnsInModels;
$result = queryMysql("select * from models" . $add_where . " and id>$cur_id order by id limit " . READ_MODEL_NUM . ";");
$n_rec = $result->num_rows;
$res['reading_models'][] = "id=$cur_id ; n_rec=$n_rec";
$result->data_seek(0);
$i = 0;
$barById = []; // номер бара в массиве по его ID

while ($n_rec > 0) {
    while ($cur_model = $result->fetch_assoc()) {
        $i++;
        $cur_id = $cur_model['id'];
        calcModel($cur_model);
        //$res['out'][]="$i) $cur_id";
    }
    if ($n_rec == READ_MODEL_NUM) {
        $result = queryMysql("select * from models" . $add_where . " and id>$cur_id order by id limit " . READ_MODEL_NUM . ";");
        $n_rec = $result->num_rows;
        $result->data_seek(0);
        $res['reading_models'][] = "id=$cur_id ; n_rec=$n_rec";
    } else $n_rec = 0;
}
$res['info']['CNT'] = $i;
write_log(PHP_EOL . "end", 1);


// ! Перенесено из calc_statistics (который больше не используется)
// если не заполнены section в chart_names, делим примерно поровну на 3 части
$result = queryMysql("select n.*,c.cnt from chart_names n left join (select name_id,count(*) cnt from charts group by name_id) c on n.id=c.name_id  where section is null order by cnt desc,id;", false, MYSQLI_STORE_RESULT);
$num = 1;
$chart_names = [];
while ($rec = $result->fetch_assoc()) {
    $chart_names[$rec['id']] = $rec;
}
foreach ($chart_names as $id => $chart_name) {
    $result0 = queryMysql("update chart_names set section=$num where id=$id;");
    $res['info']['setting section numbers'] = "Распределили чарты по участкам";
    write_log(" Установлено section=$num для графика" . $chart_name['name'] . PHP_EOL, 3);
    if (++$num == (NUM_OF_SECTION + 1)) $num = 1;
}
$result->close();
// ! конец заполнения section

if ($isALL || isset($nameId)) { // проставляем только если полный пересчет или по name_id (в противном случае, считаем, что уже было проставлено
    // ! Расчет параметра bad, перенесено из calc_statistics, т.к. данный файл больше не используется
    $res['info']['cnt_tmp'] = 0;
    $row_cnt = 0;
    $add_where = $isALL ? "" : "and name_id=$nameId";
    $result = queryMysql("update models set bad=1 where G1!='WEDGE' $add_where and AUX='AM' and (`lvl34to46aux`='NoAM_INF' or `lvl34to46aux`>11 or `ll2'5to56aux`='NoAM_INF' or `ll2'5to56aux`>11);");
    $result = queryMysql("update models set bad=2 where G1 in ('EAM','AM','AM/DBM') $add_where and (`lvl34to46`='NoAM_INF' or `lvl34to46`>11 or `ll2'5to56`='NoAM_INF' or `ll2'5to56`>11);");
    // ! Конец расчета параметра bad
}

unset($res['Error']);
die();

// * Расчет контрольных параметров и параметров характеризующих участок 5-6 для заданной модели
function calcModel($model)
{
    global $res, $baseBarNum, $isALL, $pipsByNameId, $Chart, $columnsInModels;
    static $cnt = 0;
    $cnt++;
    setNewModel($model);
    $G1 = $model['G1'];
    $v = $model['v'];

    $pips = $pipsByNameId[$model['name_id']] ?? 0; // пытаемся получить ранее посчитанный $pips из справочника
    if ($pips == 0) { // если такого еще нет, то считаем и добавляем в справочник $pipsByNameId
        $pips = $pipsByNameId[$model['name_id']] = calcPips($Chart);
    }

    if (($cnt % 1000) == 0) $res['out'][] = "$cnt) " . $model['id'];
    if (is_null($G1) && is_null($model['auxP6'])) {
        write_log("Игнорируем модель т.к. G1=null, auxP6=null " . PHP_EOL, 1);
        return (false);
    }
    if ($G1 == "WEDGE") {
        write_log("Игнорируем модель т.к. G1=WEDGE" . PHP_EOL, 1);
        return (false);
    }
    $params = [];
    $sizeAndLevels = [];
    // _llappr<aim>, _ll4appr<aim>, _lvlappr<aim> /////////////////////
    if (!is_null($model["_cross_point"])) {
        $result1 = calcParams($model, "ищем P6dev", $model["_cross_point"], $pips); // функция calcParams возвращает массив с контрольными парметрами
        if ($result1 && $G1 !== "AM" && $G1 !== "EAM") write_log("TMP check!!! WARNING-1" . PHP_EOL, 8); // проверяем, что расчитали параметры только для AM и EAM
        if (isset($result1['P6dev'])) $params['P6dev'] = $result1['P6dev'];
        if (isset($result1['P6aims'])) $params['P6aims'] = $result1['P6aims'];
        if (isset($result1['bar_0'])) $sizeAndLevels['P6aims'] = $result1;
        // ! Параметр _ll25apprP6
        // if (isset($result1['ApprReachedAt'])) $params['_ll25apprP6'] = abs(round(($result1['ApprReachedAt'] - $model['t5']) / ($model['t5'] - $model['t2']),2));
        if (isset($result1['ApprReachedAt'])) $params['_ll25apprP6'] = abs(round(($result1['ApprReachedAt'] - $model['t5']) / ($model['t5'] - $model['t2']), 2));
        if ($result1)
            foreach ($result1 as $pk => $pv)
                if (strpos($pk, "@")) $params[str_replace("@", "P6", $pk)] = $pv;
        //
        //        // Параметры _llapprP6, _ll4apprP6, _lvlapprP6
        //        if (isset($result1['_llappr'])) $params['_llapprP6'] = $result1['_llappr'];
        //        if (isset($result1['_ll4appr'])) $params['_ll4apprP6'] = $result1['_ll4appr'];
        //        if (isset($result1['_lvlappr'])) $params['_lvlapprP6'] = $result1['_lvlappr'];
        //        // Параметры _llapprP6ba, _lvlapprP6half, _lvlapprP6halfba
        //        if (isset($result1['_llappr_ba'])) $params['_llapprP6ba'] = $result1['_llappr_ba'];
        //        if (isset($result1['_lvlappr_half'])) $params['_lvlapprP6half'] = $result1['_lvlappr_half'];
        //        if (isset($result1['_lvlappr_halfba'])) $params['_lvlapprP6halfba'] = $result1['_lvlappr_halfba'];
        //        // параметры pst
        //        if (isset($result1['pst_apprP6bmp'])) $params['pst_apprP6bmp'] = $result1['pst_apprP6bmp'];
        //        if (isset($result1['pst_P6bmp'])) $params['pst_P6bmp'] = $result1['pst_P6bmp'];
        //        if (isset($result1['pst_P4_P6bmp'])) $params['pst_P4_P6bmp'] = $result1['pst_P4_P6bmp'];
        //        if (isset($result1['pstex_conf_RP6'])) $params['pstex_conf_RP6'] = $result1['pstex_conf_RP6'];
        //        if (isset($result1['pstex_RP6bmp'])) $params['pstex_RP6bmp'] = $result1['pstex_RP6bmp'];
        //        // добавлено 2023-06-15
        //        if (isset($result1['pst_conf_RP6'])) $params['pst_conf_RP6'] = $result1['pst_conf_RP6'];
        //        if (isset($result1['pst_lead_RP6'])) $params['pst_lead_RP6'] = $result1['pst_lead_RP6'];
        //        if (isset($result1['pst_leadsize_RP6'])) $params['pst_leadsize_RP6'] = $result1['pst_leadsize_RP6'];
        //        if (isset($result1['pst_RP6fall'])) $params['pst_RP6fall'] = $result1['pst_RP6fall'];
        //        if (isset($result1['pst_RP6fallsize'])) $params['pst_RP6fallsize'] = $result1['pst_RP6fallsize'];
    }

    if (!is_null($model['calcP6"'])) { // Для ВМП при наличии ЛТ через т.5” вычисляются параметры  P6aims" (полный аналог P6aims) и P6dev"
        $result2 = calcParams($model, "ищем P6dev\"", $model["calcP6\"t"] . " (" . $model['calcP6"'] . ")", $pips);
        if (isset($result2['P6dev'])) $params['P6dev"'] = $result2['P6dev'];
        if (isset($result2['P6aims'])) $params['P6aims"'] = $result2['P6aims'];
        if (isset($result2['bar_0'])) $sizeAndLevels['P6aims"'] = $result2;
        // ! Параметр _ll25apprP6"
        if (isset($result2['ApprReachedAt'])) $params["_ll25apprP6\""] = abs(round(($result2['ApprReachedAt'] - $model['t5"']) / ($model['t5"'] - $model['t2']), 2));
        if ($result2)
            foreach ($result2 as $pk => $pv)
                if (strpos($pk, "@")) $params[str_replace("@", "P6\"", $pk)] = $pv;
        //        // Параметры _llapprP6", _ll4apprP6", _lvlapprP6"
        //        if (isset($result2['_llappr'])) $params['_llapprP6"'] = $result2['_llappr'];
        //        if (isset($result2['_ll4appr'])) $params['_ll4apprP6"'] = $result2['_ll4appr'];
        //        if (isset($result2['_lvlappr'])) $params['_lvlapprP6"'] = $result2['_lvlappr'];
        //        // Параметры _llappr"P6ba, _lvlapprP6"half, _lvlapprP6"halfba
        //        if (isset($result2['_llappr_ba'])) $params['_llapprP6"ba'] = $result2['_llappr_ba'];
        //        if (isset($result2['_lvlappr_half'])) $params['_lvlapprP6"half'] = $result2['_lvlappr_half'];
        //        if (isset($result2['_lvlappr_halfba'])) $params['_lvlapprP6"halfba'] = $result2['_lvlappr_halfba'];
        //        // параметры pst
        //        if (isset($result2['pst_apprP6bmp'])) $params['pst_apprP6"bmp'] = $result2['pst_apprP6bmp'];
        //        if (isset($result2['pst_P6bmp'])) $params['pst_P6"bmp'] = $result2['pst_P6bmp'];
        //        if (isset($result2['pst_P4_P6bmp'])) $params['pst_P4_P6"bmp'] = $result2['pst_P4_P6bmp'];
        //        if (isset($result2['pstex_conf_RP6'])) $params['pstex_conf_RP6"'] = $result2['pstex_conf_RP6'];
        //        if (isset($result2['pstex_RP6bmp'])) $params['pstex_RP6"bmp'] = $result2['pstex_RP6bmp'];
        //        // добавлено 2023-06-15
        //        if (isset($result2['pst_conf_RP6'])) $params['pst_conf_RP6"'] = $result2['pst_conf_RP6'];
        //        if (isset($result2['pst_lead_RP6'])) $params['pst_lead_RP6"'] = $result2['pst_lead_RP6'];
        //        if (isset($result2['pst_leadsize_RP6'])) $params['pst_leadsize_RP6"'] = $result2['pst_leadsize_RP6'];
        //        if (isset($result2['pst_RP6fall'])) $params['pst_RP6"fall'] = $result2['pst_RP6fall'];
        //        if (isset($result2['pst_RP6fallsize'])) $params['pst_RP6"fallsize'] = $result2['pst_RP6fallsize'];
    }

    if (!is_null($model['auxP6'])) { // Для вспомогательных МП (т.е. для моделей AMEM, AMDBM, AMAM, AMforAM/DBM, AMforEM/DBM) рассчитываются следующие параметры- аналоги: auxP6aims (аналог P6aims) auxP6dev аналогичный P6dev
        $result3 = calcParams($model, "ищем auxP6dev", $model["auxP6t"] . " (" . $model['auxP6'] . ")", $pips);
        if (isset($result3['P6dev'])) $params['auxP6dev'] = $result3['P6dev'];
        if (isset($result3['P6aims'])) $params['auxP6aims'] = $result3['P6aims'];
        if (isset($result3['bar_0'])) $sizeAndLevels['auxP6aims'] = $result3;
        // ! Параметр _ll25apprAuxP6
        if (isset($result3['ApprReachedAt'])) $params['_ll25apprAuxP6'] = abs(round(($result3['ApprReachedAt'] - $model['t5']) / ($model['t5'] - $model['t2']), 2));
        if ($result3)
            foreach ($result3 as $pk => $pv)
                if (strpos($pk, "@")) $params[str_replace("@", "AuxP6", $pk)] = $pv;
        //        // Параметры _llapprAuxP6, _ll4apprAuxP6, _lvlapprAuxP6
        //        if (isset($result3['_llappr'])) $params['_llapprAuxP6'] = $result3['_llappr'];
        //        if (isset($result3['_ll4appr'])) $params['_ll4apprAuxP6'] = $result3['_ll4appr'];
        //        if (isset($result3['_lvlappr'])) $params['_lvlapprAuxP6'] = $result3['_lvlappr'];
        //        // Параметры _llapprAuxP6ba, _lvlapprAuxP6half, _lvlapprAuxP6halfba
        //        if (isset($result3['_llappr_ba'])) $params['_llapprAuxP6ba'] = $result3['_llappr_ba'];
        //        if (isset($result3['_lvlappr_half'])) $params['_lvlapprAuxP6half'] = $result3['_lvlappr_half'];
        //        if (isset($result3['_lvlappr_halfba'])) $params['_lvlapprAuxP6halfba'] = $result3['_lvlappr_halfba'];
        //        // параметры pst
        //        if (isset($result3['pst_apprP6bmp'])) $params['pst_apprAuxP6bmp'] = $result3['pst_apprP6bmp'];
        //        if (isset($result3['pst_P6bmp'])) $params['pst_AuxP6bmp'] = $result3['pst_P6bmp'];
        //        if (isset($result3['pst_P4_P6bmp'])) $params['pst_P4_AuxP6bmp'] = $result3['pst_P4_P6bmp'];
        //        if (isset($result3['pstex_conf_RP6'])) $params['pstex_conf_RAuxP6'] = $result3['pstex_conf_RP6'];
        //        if (isset($result3['pstex_RP6bmp'])) $params['pstex_RAuxP6bmp'] = $result3['pstex_RP6bmp'];
        //        // добавлено 2023-06-15
        //        if (isset($result3['pst_conf_RP6'])) $params['pst_conf_RAuxP6'] = $result3['pst_conf_RP6'];
        //        if (isset($result3['pst_lead_RP6'])) $params['pst_lead_RAuxP6'] = $result3['pst_lead_RP6'];
        //        if (isset($result3['pst_leadsize_RP6'])) $params['pst_leadsize_RAuxP6'] = $result3['pst_leadsize_RP6'];
        //        if (isset($result3['pst_RP6fall'])) $params['pst_RAuxP6fall'] = $result3['pst_RP6fall'];
        //        if (isset($result3['pst_RP6fallsize'])) $params['pst_RAuxP6fallsize'] = $result3['pst_RP6fallsize'];
    }

    if (!is_null($model['auxP6\''])) { // Для вспомогательных моделей, построенных через т.5’ рассчитываются параметры auxP6aims’ – аналогичный P6bumper auxP6dev’ аналогичный P6dev )
        $result4 = calcParams($model, "ищем auxP6dev'", $model["auxP6't"] . " (" . $model["auxP6'"] . ")", $pips);
        if (isset($result4['P6dev'])) $params['auxP6dev\''] = $result4['P6dev'];
        if (isset($result4['P6aims'])) $params['auxP6aims\''] = $result4['P6aims'];
        if (isset($result4['bar_0'])) $sizeAndLevels['auxP6aims\''] = $result4;
        // ! Параметр _ll25apprAuxP6'
        if (isset($result4['ApprReachedAt'])) $params['_ll25apprAuxP6\''] = abs(round(($result4['ApprReachedAt'] - $model["t5'"]) / ($model["t5'"] - $model['t2']), 2));
        if ($result4)
            foreach ($result4 as $pk => $pv)
                if (strpos($pk, "@")) $params[str_replace("@", "AuxP6'", $pk)] = $pv;
        //        // Параметры _llapprAuxP6', _ll4apprAuxP6', _lvlapprAuxP6'
        //        if (isset($result4['_llappr'])) $params['_llapprAuxP6\''] = $result4['_llappr'];
        //        if (isset($result4['_ll4appr'])) $params['_ll4apprAuxP6\''] = $result4['_ll4appr'];
        //        if (isset($result4['_lvlappr'])) $params['_lvlapprAuxP6\''] = $result4['_lvlappr'];
        //        // Параметры _llapprAuxP6'ba, _lvlapprAuxP6'half, _lvlapprAuxP6'halfba
        //        if (isset($result4['_llappr_ba'])) $params['_llapprAuxP6\'ba'] = $result4['_llappr_ba'];
        //        if (isset($result4['_lvlappr_half'])) $params['_lvlapprAuxP6\'half'] = $result4['_lvlappr_half'];
        //        if (isset($result4['_lvlappr_halfba'])) $params['_lvlapprAuxP6\'halfba'] = $result4['_lvlappr_halfba'];
        //        // параметры pst
        //        if (isset($result4['pst_apprP6bmp'])) $params['pst_apprAuxP6\'bmp'] = $result4['pst_apprP6bmp'];
        //        if (isset($result4['pst_P6bmp'])) $params['pst_AuxP6\'bmp'] = $result4['pst_P6bmp'];
        //        if (isset($result4['pst_P4_P6bmp'])) $params['pst_P4_AuxP6\'bmp'] = $result4['pst_P4_P6bmp'];
        //        if (isset($result4['pstex_conf_RP6'])) $params['pstex_conf_RAuxP6\''] = $result4['pstex_conf_RP6'];
        //        if (isset($result4['pstex_RP6bmp'])) $params['pstex_RAuxP6\'bmp'] = $result4['pstex_RP6bmp'];
        //        // добавлено 2023-06-15
        //        if (isset($result4['pst_conf_RP6'])) $params['pst_conf_RAuxP6\''] = $result4['pst_conf_RP6'];
        //        if (isset($result4['pst_lead_RP6'])) $params['pst_lead_RAuxP6\''] = $result4['pst_lead_RP6'];
        //        if (isset($result4['pst_leadsize_RP6'])) $params['pst_leadsize_RAuxP6\''] = $result4['pst_leadsize_RP6'];
        //        if (isset($result4['pst_RP6fall'])) $params['pst_RAuxP6\'fall'] = $result4['pst_RP6fall'];
        //        if (isset($result4['pst_RP6fallsize'])) $params['pst_RAuxP6\'fallsize'] = $result4['pst_RP6fallsize'];
    }

    if (!$isALL) $result5 = queryMysql("delete from controls where model_id=" . $model['id'], true);
    $fields_str = "model_id";
    $values_str = $model['id'];
    $cnt_fld = 0;
    foreach ($params as $pk => $pv) if (substr($pk, 0, 2) == "P6" || substr($pk, 0, 2) == "au") {  // только для "старых стандартных" параметров
        $fields_str .= ",`" . $pk . "`";
        $values_str .= ",'" . $pv . "'";
        $cnt_fld++; // подсчет кол-ва параметров, добавленных в fields_str (=кол-во значений, добавленных в $values_str)
    }

    // * Filling "size_and_levels" DB table
    if ($cnt_fld) $result6 = queryMysql("insert into controls ($fields_str) VALUES ($values_str);");
    if (!$isALL) $result7 = queryMysql("delete from size_and_levels where model_id=" . $model['id'], true);
    foreach ($sizeAndLevels as $aim_field => $p) {
        $fields_str = "model_id,aim_field";
        $values_str = $model['id'] . ",'" . str_replace("'", "\'", $aim_field) . "'";
        foreach ($p as $pk => $pv) if (in_array(substr($pk, 0, 3), ['siz', 'lvl', 'bar', 'aim'])) {
            $fields_str .= ",`$pk`";
            $values_str .= ",'" . $pv . "'";
        }
        //        write_log("TMP___ insert into size_and_levels ($fields_str) VALUES ($values_str);".PHP_EOL,9);
        $result8 = queryMysql("insert into size_and_levels ($fields_str) VALUES ($values_str);");
    }

    // * Вносим прочие параметры, для которых в models есть одноименные поля
    $update_str = "";
    foreach ($params as $pk => $pv) if (isset($columnsInModels[$pk])) {  // есть такое поле в таблице -> апдейтим//было: if (substr($pk, 0, 2) == "_l" || substr($pk, 0, 3) == "pst") {
        $res['info']['debugParams'][$pk] = ($res['info']['debugParams'][$pk] ?? 0) + 1;
        if (strlen($update_str) > 0) $update_str .= ",";
        $update_str .= "`" . $pk . "`=" . prepareForMySQL($pv);
    }

    if (strlen($update_str) > 0) $result9 = queryMysql("UPDATE models SET $update_str WHERE id = " . $model['id']);


    //  $result1=calcParams($model,"ищем P6\"dev",p6_bar." (".p6_level.")");
    //$lvl_approachP6=$CP_level;

}

function setNewModel($model)
{ // подготовка чарта для анализа новой модели - установка начального бара и подкачка порции баров, если необходимо
    global $res, $chartNumById, $lastBarPortion, $baseBarNum, $Chart;
    static $npp = 1;
    $nameId = $model['name_id'];
    $barId = $model['bar_id'];

    //    $res['reading_chart'][]="0> barID=$barId";
    if (!isset($chartNumById[$model['bar_id']]) || $chartNumById[$model['bar_id']] > (READ_BAR_NUM - 500) && $lastBarPortion == READ_BAR_NUM) { // если такого (опорного) бара в нашем массиве (фрагменте) нет либо он менее чем 500 баров от правого края (считаем, что модель не может быть больше 500 баров)
        // -> скачиваем новый фрагмент свечного графика - берем 1200 баров до опорного и READ_BAR_NUM (10000) далее
        // select * from charts where name_id=1 and id<200 order by id desc limit 100;
        $query = "select * from charts where name_id=$nameId and id<$barId order by id desc limit " . READ_BAR_BEFORE . ";";
        $res['reading_chart'][] = "1> " . $query;
        $result = queryMysql($query);
        $n_rec = $result->num_rows;
        if ($n_rec > 0) {
            $result->data_seek($n_rec - 1);
            $firstBar = $result->fetch_assoc();
            $barFrom = $firstBar['id'];
        } else {
            $barFrom = 0;
            $res['reading_chart'][] = "barFrom=0";
        }
        $query = "select * from charts where name_id=$nameId and id>=$barFrom order by id limit " . READ_BAR_NUM . ";";
        $result = queryMysql($query);
        $lastBarPortion = $n_rec = $result->num_rows;
        $res['reading_chart'][] = "2> " . $query . "   n_rec=$n_rec";
        $result->data_seek(0);
        $Chart = [];
        $chartNumById = [];
        $i = 0;
        while ($curBar = $result->fetch_assoc()) {
            $Chart[] = ["dandt" => $curBar['dandt'], "o" => $curBar['o'], "c" => $curBar['c'], "h" => $curBar['h'], "l" => $curBar['l']];
            $chartNumById[$curBar['id']] = $i;
            if ($barId == $curBar['id']) $baseBarNum = $i;
            $i++;
        }
        //   $res['out_tmp_bar']=$Chart;
    } else $baseBarNum = $chartNumById[$model['bar_id']];
    $msg_ =
        write_log(PHP_EOL . $npp++ . ") " . date("Y-m-d H:i:s") . " Model-" . $model['Alg'] . "-" . ($model['v'] == 'low' ? "low" : "high") . " G1='" . $model['G1'] . "' [" . $model['id'] . "] bar=" . $baseBarNum . " bar_id=" . $model['bar_id'] . " (" . substr($Chart[$baseBarNum]['dandt'], 0, 16) . ")" . PHP_EOL, 1);
}

function lineLevel($line, $bar) // копия вспомогательной функции из первого алгоритма
{ // параметр $line - асс. массив $bar=опорные бар $level=значение (цены) на опорном баре, $angle - изменение значения (цены) за 1 бар
    return ($line['level'] + ($bar - $line['bar']) * $line['angle']);
}

function LT($State)
{ // определение линии тренда
    global $baseBarNum;
    $v = $State['v'];
    $t3_ = (isset($State['t3\''])) ? 't3\'' : 't3';
    return (['bar' => $State['t1'] + $baseBarNum, 'level' => low($State['t1'] + $baseBarNum, $v), 'angle' => (low($State[$t3_] + $baseBarNum, $v) - low($State['t1'] + $baseBarNum, $v)) / ($State[$t3_] - $State['t1'])]);
}

function not_v($v)
{ // из high->low и наоборот
    return (($v == 'low') ? 'high' : 'low');
}

// набор функций, возвращающие open, close, high и low бара с номером $i (J для модели, где точка 1=low либо с отр. знаком, если high (чтобы не делать 2 копии алгоритма)
function high($i, $v, $line = "0")
{ // возвращает high либо -low
    global $Chart;
    //    if(!isset($maxBar4Split[$curSplit])||$maxBar4Split[$curSplit]<$i)$maxBar4Split[$curSplit]=$i;
    return (($v == 'low') ? $Chart[$i]['h'] : $Chart[$i]['l'] * (-1));
}

function low($i, $v, $line = "0")
{ // возвращает high либо -low
    global $Chart;
    //if(!isset($i))$res['tmp_info_low_func']="!!! low: i: ".$i." v: ".$v." line: ".$line;
    //    if(!isset($maxBar4Split[$curSplit])||$maxBar4Split[$curSplit]<$i)$maxBar4Split[$curSplit]=$i;
    return (($v == 'low') ? $Chart[$i]['l'] * 1 : $Chart[$i]['h'] * (-1));
}

function open($i, $v)
{ // возвращает high либо -low
    global $Chart;
    //    if(!isset($maxBar4Split[$curSplit])||$maxBar4Split[$curSplit]<$i)$maxBar4Split[$curSplit]=$i;
    return (($v == 'low') ? $Chart[$i]['o'] : $Chart[$i]['o'] * (-1));
}

function close($i, $v)
{ // возвращает high либо -low
    global $Chart;
    //    if(!isset($maxBar4Split[$curSplit])||$maxBar4Split[$curSplit]<$i)$maxBar4Split[$curSplit]=$i;
    return (($v == 'low') ? $Chart[$i]['c'] : $Chart[$i]['c'] * (-1));
}

function calcPips($Chart)
{
    global $res;
    $pips = 11111111111111; // определяем размер пипса для текущего инструмента тупо анализируя наш чарт
    $tmp_pipsCalcCnt = 0;
    for ($i = 0; $i < count($Chart) - 1; $i++) {
        $tmp_pipsCalcCnt++;
        $diff = ($Chart[$i]['h'] - $Chart[$i]['o']);
        if ($diff >= MIN_PIPS && $diff < $pips) $pips = $diff;
        $diff = ($Chart[$i]['h'] - $Chart[$i]['c']);
        if ($diff >= MIN_PIPS && $diff < $pips) $pips = $diff;
        $diff = ($Chart[$i]['o'] - $Chart[$i]['l']);
        if ($diff >= MIN_PIPS && $diff < $pips) $pips = $diff;
        $diff = ($Chart[$i]['c'] - $Chart[$i]['l']);
        if ($diff >= MIN_PIPS && $diff < $pips) $pips = $diff;
        if ($pips <= (MIN_PIPS + 0.0000001)) break; // достигли минимально возможного (на большинстве инструментов = 0.00001)
    }
    $pips = round($pips, 6);
    $tmp = str_replace("0", "", "" . ($pips * 1000000000)); // получаем последнюю цифру/цифры
    return round($pips / $tmp, 6);
}

// ! Функция перенесена из caolc_statistics
function getNextRec()
{ // функция обеспечивает порционное чтение моделей
    static $limit_from = 0;
    static $limit_cnt = PORTION_OF_READING; //
    static $totalRecCnt = 0;
    static $nRows = 0;
    static $nRecCur = 0;
    static $Recs = [];
    static $cnt = 0;
    $cnt++;
    if ($cnt > REC_LIMIT_DEBUG) {
        write_log("Вышли по ограничению DEBUG_LIMIT=" . REC_LIMIT_DEBUG . PHP_EOL, 1);
        return (false);
    }

    if ($nRecCur == $nRows || count($Recs) == 0) { // читаем очередную порцию
        if ($nRows !== 0 && $nRows < $limit_cnt) {
            write_log("Чтение моделей завершено" . PHP_EOL, 1);
            return (false);
        } // если предыдущая порция была последней

        $result = queryMysql("select m.*,c.*,n.section from models m left join controls c on m.id=c.model_id left join chart_names n on m.name_id=n.id order by n.id,m.id limit $limit_from,$limit_cnt;", false, MYSQLI_STORE_RESULT);
        if (!$result) {
            write_log("ERROR! ошибка чтения моделей, останов." . PHP_EOL, 1);
            return (false);
        }
        $nRows = $result->num_rows;
        write_log("Прочитали очередную порцию моделей - $nRows записей." . PHP_EOL, 1);
        if ($nRows == 0) {
            write_log("Очередная порция содерджит 0 записей. Дошли до конца." . PHP_EOL, 1);
            return (false);
        }
        $Recs = [];
        while ($rec = $result->fetch_assoc()) {
            $Recs[] = $rec;
        }
        $result->close();
        $limit_from += $limit_cnt;
        $totalRecCnt += $nRows;
        $nRecCur = 0;
    }
    $out = $Recs[$nRecCur++];
    return ($out);
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

function prepareForMySQL($s)
{ // заменяетт симвлы апострофа и обратного слэша - для использоваения в команде MySQL
    return (str_replace("'", "\'", str_replace("\\", "\\\\", $s)));
}
