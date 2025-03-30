<?php
ini_set('date.timezone', 'Europe/Moscow');
define("EXS_APPROACH_IN_PIPS", 10); // размер в пипсах для определения подхода к УПБ при расчете TLexs13' , TLexs3'4 , ALexs12' , ALexs2'4 , auxTLexs , auxALexs
if (!defined("MIN_PIPS"))
    define("MIN_PIPS", 0.00001); // минимальный пипс (для Альпари минимальное изменение цены для большинства инструментов = 0.00001)
// (под пипсом тут понимается минимальное изменение цены на чарте (определяется по текущему чарту) рассчетно
$debug_info = [];
$php_start = $startTime = $debug_info['start'] = microtime(true);
$curDir = getcwd();
set_error_handler('myHandler', E_ALL);
set_exception_handler('myException');
register_shutdown_function('shutdown');
ob_start();
$curState = 0;
$curSplit = 0; // номер сплита текущего стейта
$Last_Error = "";
$All_Errors = ""; // вроде не используется...
$Flat_logs_CNT = 0; // просто для информации - суммарное число записаей во flat_log всех States
$maxBar4Split = []; // максимальный номер бара, к которому обращались в данном стейте
//$err_cnt = 0;
$splitCnt = 1; // счетчик отпочковавшихся веток при расщиплениях

function set_debug_info($s, $num = "default")
{
    global $debug_info;
    $debug_info[$num] = $s;
}

function not_v($v)
{ // из high->low и наоборот
    return (($v == 'low') ? 'high' : 'low');
}

// набор функций, возвращающие open, close, high и low бара с номером $i (J для модели, где точка 1=low либо с отрицательным знаком, если 1=high)
function high($i, $v, $line = "0")
{ // возвращает high либо -low
    global $Chart, $res, $maxBar4Split, $curSplit;
    if (!isset($maxBar4Split[$curSplit]) || $maxBar4Split[$curSplit] < $i) $maxBar4Split[$curSplit] = $i;
    return (($v == 'low') ? $Chart[$i]['high'] * 1 : $Chart[$i]['low'] * (-1));
}

function low($i, $v, $line = "0")
{ // возвращает high либо -low
    global $Chart, $res, $maxBar4Split, $curSplit;
    //if(!isset($i))$res['tmp_info_low_func']="!!! low: i: ".$i." v: ".$v." line: ".$line;
    if (!isset($maxBar4Split[$curSplit]) || $maxBar4Split[$curSplit] < $i) $maxBar4Split[$curSplit] = $i;
    return (($v == 'low') ? $Chart[$i]['low'] * 1 : $Chart[$i]['high'] * (-1));
}

function open($i, $v)
{ // возвращает high либо -low
    global $Chart, $maxBar4Split, $curSplit;
    if (!isset($maxBar4Split[$curSplit]) || $maxBar4Split[$curSplit] < $i) $maxBar4Split[$curSplit] = $i;
    return (($v == 'low') ? $Chart[$i]['open'] * 1 : $Chart[$i]['open'] * (-1));
}

function close($i, $v)
{ // возвращает high либо -low
    global $Chart, $maxBar4Split, $curSplit;
    if (!isset($maxBar4Split[$curSplit]) || $maxBar4Split[$curSplit] < $i) $maxBar4Split[$curSplit] = $i;
    return (($v == 'low') ? $Chart[$i]['close'] * 1 : $Chart[$i]['close'] * (-1));
}

function is_extremum($n, $type)
{ //проверка на экстремум по правилу N1
    // исправлено 2021-02-12 - в данной функции не проставлялся maxBar, может быть важно в дальнейшем при определении "когда сформировалась модель"
    global $Chart, $maxBar4Split, $curSplit;
    $max_bar = nBars - 1;
    if ($n >= $max_bar || $n < 1) return (false);
    if (!isset($maxBar4Split[$curSplit]) || $maxBar4Split[$curSplit] < $n) $maxBar4Split[$curSplit] = $n;
    if ($type == 'high') {  // проверка на экстремум high
        if ($Chart[$n - 1]['high'] >= $Chart[$n]['high']) return (false);
        for ($i = $n + 1; $i < $max_bar; $i++) {
            if ($maxBar4Split[$curSplit] < $i) $maxBar4Split[$curSplit] = $i;
            if ($Chart[$i]['high'] < $Chart[$n]['high']) return (true);
            if ($Chart[$i]['high'] > $Chart[$n]['high']) return (false);
        }
        return (false);
    }
    if ($type == 'low') { // проверка на экстремум low
        if ($Chart[$n - 1]['low'] <= $Chart[$n]['low']) return (false);
        for ($i = $n + 1; $i < $max_bar; $i++) {
            if ($maxBar4Split[$curSplit] < $i) $maxBar4Split[$curSplit] = $i;
            if ($Chart[$i]['low'] > $Chart[$n]['low']) return (true);
            if ($Chart[$i]['low'] < $Chart[$n]['low']) return (false);
        }
        return (false);
    }
}

function linesIntersection($L1, $L2)
{
    $price1 = $L1['level'];
    $price2 = lineLevel($L2, $L1['bar']);
    if ($price1 == $price2) return (['bar' => $L1['bar'], 'level' => $price1]);
    if ($L1['angle'] == $L2['angle']) return (false); // линии параллельны
    $dy = $L2['angle'] - $L1['angle']; // на сколько сближаются ЛЦ' и ЛТ'вмп за 1 бар
    $bar = $L1['bar'] + ($price1 - $price2) / $dy;
    return (['bar' => $bar, 'level' => lineLevel($L1, $bar)]);
}

function lineLevel($line, $bar)
{ // параметр $line - асс. массив $bar=опорные бар $level=значение (цены) на опорном баре, $angle - изменение значения (цены) за 1 бар
    return ($line['level'] + ($bar - $line['bar']) * $line['angle']);
}

function myLog_selected_bar($State, $txt)
{ // запись в лог $res['log_selected_bar'], в случае, если t1 (для Алгоритма 1) или t3 (для Алгоритма 2)== selectedBar
    global $res;
    if (ALGORITHM_NUM == 1 && selectedBar == $State['t1'] || ALGORITHM_NUM == 2 && selectedBar == $State['t3']) {
        $res['log_selected_bar'][] = '[' . $State['split'] . '] ' . $txt;
        return (1);
    }
    return (0);
}
function myLog($State, $txt)
{ // запись в лог $State + в лог $res['log_selected_bar'], в случаe, если t1 (для Алгоритма 1) или t3 (для Алгоритма 2)== selectedBar
    global $res, $paramArr, $Flat_logs_CNT;
    if (!isset($paramArr['log']) || $paramArr['log'] != 0) {
        $Flat_logs_CNT++;
        $State['flat_log'][] = '[' . $State['split'] . '] ' . $txt;
    }

    if (ALGORITHM_NUM == 1) if (!isset($State['t1']) ||  selectedBar == $State['t1']) $res['log_selected_bar'][] = '[' . $State['split'] . '] ' . $txt;
    if (ALGORITHM_NUM == 2) if (!isset($State['t3']) ||  selectedBar == $State['t3']) $res['log_selected_bar'][] = '[' . $State['split'] . '] ' . $txt;
    if (isset($res['FlatLog_Statistics'])) {
        $ind = whereFrom(true);
        if (isset($res['FlatLog_Statistics'][$ind])) $res['FlatLog_Statistics'][$ind]++;
        else $res['FlatLog_Statistics'][$ind] = 1;
    }

    //    if(count($State['flat_log'])>500){
    //        $res['log'][]="Превышен лимит лога 500 записей";
    //        $State['next_step']='stop';
    //    }
    return ($State);
}
function clearState($State, $list)
{ // unset всех полей кроме перечисленных и служебных
    $list .= "," . KEYS_LIST_STATIC; //",v,status,mode,curBar,next_step,cnt,flat_log,models";
    $list = explode(",", $list);
    $toUnset = [];
    foreach ($State as $pk => $pv) {
        if (!in_array($pk, $list)) {  // ключ на удаление J in_array — Проверяет наличие $pk в $list
            $toUnset[] = $pk; // если значение $pk не было найдено в листе, данное $pk удаляется
        }
    }

    for ($i = 0; $i < count($toUnset); $i++) unset($State[$toUnset[$i]]);
    if (!isset($State['status'])) $State['status'] = [];
    if (!isset($State['param'])) $State['param'] = [];
    return ($State);
}
// наш обработчик ошибок
function myHandler($level, $message, $file, $line, $context)
{
    global $Last_Error, $res, $curState, $pips; //, $All_Errors;
    // в зависимости от типа ошибки формируем заголовок сообщения
    switch ($level) {
        case E_WARNING:
            $type = 'Warning';
            break;
        case E_NOTICE:
            $type = 'Notice';
            break;
        default:
            $type = 'Error';
            break;
        // это не E_WARNING и не E_NOTICE
        // значит мы прекращаем обработку ошибки
        // далее обработка ложится на сам PHP
    }
    // выводим текст ошибки
    //  echo "<h2>$type: $message</h2>";
    //  echo "<p><strong>File</strong>: $file:$line</p>";
    //  echo "<p><strong>Context</strong>: $". join(', $', array_keys($context))."</p>";
    $Last_Error = "ERROR level: $level type: $type curState: $curState file: $file line: $line $message";
    $res['Errors']['line_' . $line] = $Last_Error . "<br> Trace: <br> " . generateCallTrace();
    //$res['Errors']['line_' . $line.'_trace'] = generateCallTrace(); //EEEEEEEEEEE
    //$All_Errors.=($Last_Error."<br>");
    // сообщаем, что мы обработали ошибку, и дальнейшая обработка не требуется
    return true;
}
// регистрируем наш обработчик, он будет срабатывать  для всех типов ошибок
function myException($exception)
{
    global $res;
    static $cnt = 0;
    $cnt++;
    $res['Errors']['Exception_' . $cnt] = $exception->getMessage() . ' (line ' . $exception->getLine() . ')';
    return (true);
}

function shutdown()
{
    global $Last_Error, $res, $startTime, $debug_info, $States, $paramArr, $Models_Alg1, $Flat_logs_CNT, $splitCnt, $start_proc_time, $curDir, $chUnique; //, $All_Errors
    $error = error_get_last();
    if (is_array($error) && count($error)) {
        $res['Errors'][] = $error;
    }
    while (ob_get_level()) ob_end_clean(); // стандартный прием для очистки буфера от мусора, см. в интернете


    $models_ = [];
    $tmp1 = strlen("" . (nBars - 1));
    //$res['tmp1']=$tmp1;
    foreach ($res['Models'] as $pk => $pv) {
        $models_['p' . substr("00000000" . $pk, $tmp1 * (-1))] = $pv; // всем моделям к номеру добавляется p, сделано не совсем понятно для чего, возможно чтобы номера визуально разделялись
    } // нумерацию моделей - "p"+номер опорного бара с добавлением нулей слева - количество разрядов определяется числом баров - 3 - для 1000, 4 если баров от 1001 до 10000
    krsort($models_); // сортировка по убыванию ключей = последние бары вначале
    $models_ = setAuxParams($models_); // определение расчетных параметров, которые не были рассчитаны по ходу алгоритма
    if (ALGORITHM_NUM == 2) { // после расчета второго алгоритма, возвращаем нетронутый массив $Models_Alg1 в ['Models'] и модели алгоритма_2 в ['Models2']
        $res['Models'] = $Models_Alg1;
        $res['Models2'] = $models_;
        // JSON_log($models_,"Models2_.json");
    } else { // после расчета первого алгоритма, возвращаем нетронутый массив $Models_Alg1 в ['Models'] и модели алгоритма_2 в ['Models2']
        $res['Models'] = $models_;
    }

    $res['info']['flat_log_CNT'] = $Flat_logs_CNT;
    $res['info']['split_CNT'] = $splitCnt;
    $res['info']['States_CNT'] = count($States);
    $res['debug_info'] = $debug_info;

    if (isset($res['info']['last_function']) && substr($res['info']['last_function'], -4) !== 'done') $res['info']['last_function'] .= " зависли на " . (microtime(true) - $start_proc_time) . " сек.";
    // Е: след. строка очищала flat_log, если галочка подробного была снята - без нее, в этом случае, там все равно оставалась инфа по очередности "step" из A2_mylog_start (теперь там пусто, смысла нет чистить)
    //  if (!isset($paramArr['log']) || $paramArr['log'] == 0) foreach ($States as $pk => $pv) if(isset($States[$pk]['flat_log']))$States[$pk]['flat_log'] = "deleted " . count($States[$pk]['flat_log']) . " records (log=0)";
    foreach ($res['info']['calcTimes'] as $pk => $pv) //if(substr($pk,strlen($pk)-5,4)=='time')
        $res['info']['calcTimes'][$pk] = round($res['info']['calcTimes'][$pk], 3);
    $res['States'] = $States;
    $branch_list = "";
    foreach ($res as $pk => $pv) {
        if ($branch_list !== "") $branch_list .= ",";
        $branch_list .= $pk;
    }
    $res['debug_info']['branch_list'] = $branch_list;
    calcStatistics();
    ksort($res['info']['models_total']);
    // ниже стираем все записи checkUnique равные 1 (не было ошибок уникальности)
    if (isset($res['info']['checkUnique'])) foreach ($res['info']['checkUnique'] as $pk => $pv)
        if ($res['info']['checkUnique'][$pk] == 1) unset($res['info']['checkUnique'][$pk]);
    if (isset($res['FlatLog_Statistics'])) {
        arsort($res['FlatLog_Statistics']);
        $total = 0;
        foreach ($res['FlatLog_Statistics'] as $pk => $pv) $total += $pv;
        $res['FlatLog_Statistics']["___TOTAL___"] = $total;
    }

    header('Content-type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: X-Requested-With, content-type');
    while (ob_get_level()) ob_end_clean();  // стандартный прием для очистки буфера от мусора, см. в интернете
    if (isset($chUnique)) {
        foreach ($chUnique as $pk => $pv)
            if (count($pv) == 1) unset($chUnique[$pk]);
        // file_put_contents($curDir . "\\TMP_chUnique___Alg" . ALGORITHM_NUM . ".json", json_encode($chUnique, true));
    }

    $tt = microtime(true) - $startTime;
    $tt = substr($tt, 0, 5) . " сек.";
    $res['info']['calcTime'] = $tt;
    $res['info']['mgu_on_finish'] = number_format(memory_get_usage());

    // если нужно, то сохраняем текстовую копию $res в файл
    if (defined("NEED_TEXT_RES") && NEED_TEXT_RES) {
        if (NEED_TEXT_RES === true) $outFile = $curDir . "/tmp_log/_res_" . str_replace(".php", "", shortFileName(__FILE__)) . "_" . date("Y-m-d H_i_s") . ".txt"; // автонаименование
        else $outFile = $curDir . "/" . NEED_TEXT_RES;
        $outFile = str_replace("{DANDT}", date("Y-m-d H_i_s"), $outFile);
        writeJSONtoTXT($outFile, $res);
    }


    $res_ = json_encode($res, JSON_PARTIAL_OUTPUT_ON_ERROR);
    //$r=file_put_contents($curDir."/tmp/TMP_res_ALG".ALGORITHM_NUM."__.json",$res_);
    if ($json_last_error_ = json_last_error()) {
        file_put_contents($curDir . "/tmp/TMP___shutdown.txt", "JSON_ERROR :" . $json_last_error_);
        file_put_contents($curDir . "/tmp_log/____json_error.json", $res_);
    }

    //if ($json_last_error_==0)
    echo $res_; // json_encode($res, true);
    //else echo "JSON_ERROR :" . json_last_error();
}
function writeJSONtoTXT($outFile, $json, $depth = 0, $fh = false)
{
    global $res;
    static $shift = "  "; // строка для отступа - повторяется по глубине некурсии
    if ($fh === false) { // первый уровень рекурсии
        $fh = fopen($outFile, "w");
        if ($fh === false) {
            $res['debug']['shutdownMsg'] = "Ошибка создания файла для записи результата. ($outFile)";
            return;
        } else $res['debug']['shutdownMsg'] = "Cоздан файл для записи результата. ($outFile)";
    }
    foreach ($json as $pk => $pv) {
        if (is_array($pv)) {
            fwrite($fh, str_repeat($shift, $depth) . $pk . ":" . PHP_EOL);
            if ($pv) writeJSONtoTXT($outFile, $pv, $depth + 1, $fh);
        } else {
            fwrite($fh, str_repeat($shift, $depth) . $pk . ": " . $pv . PHP_EOL);
        }
    }
    if ($depth == 0) {
        $res['debug']['shutdownMsg'] .= " Done.";
        fclose($fh);
    }
    return;
}
function compare4alt($model1, $model2)
{
    if (ALGORITHM_NUM == 2) {
        if ($model1['A2Prev'] < $model2['A2Prev']) return (-1);
        if ($model1['A2Prev'] > $model2['A2Prev']) return (1);
        if ($model1['t4'] < $model2['t4']) return (-1);
        if ($model1['t4'] > $model2['t4']) return (1);
        if ($model1['t5'] < $model2['t5']) return (-1);
        if ($model1['t5'] > $model2['t5']) return (1);
        return (0);
    } else {
        if ($model1['t2'] < $model2['t2']) return (-1);
        if ($model1['t2'] > $model2['t2']) return (1);
        if ($model1['t3'] < $model2['t3']) return (-1);
        if ($model1['t3'] > $model2['t3']) return (1);
        if ($model1['t4'] < $model2['t4']) return (-1);
        if ($model1['t4'] > $model2['t4']) return (1);
        if ($model1['t5'] ?? 0 < $model2['t5'] ?? 0) return (-1);
        if ($model1['t5'] ?? 0 > $model2['t5'] ?? 0) return (1);
        return (0);
    }
}

function setAuxParams($models)
{
    // 1. Определяем G1, если его пока нет
    global $pips, $res, $selectedBar, $curDir;
    //return($models);
    if (ALGORITHM_NUM == 1)  // выполняем только для первого алгоритма, во втором все ок
        foreach ($models as $bar => $models_at_bar)
            foreach ($models_at_bar as $pk => $pv) {
                if (!isset($pv['param']['G1'])) $models[$bar][$pk] = defineG1($pv);
            }

    // 2. рассчитываем для контроля Alt моделей (alt)
    foreach ($models as $bar => $models_at_bar) { // перебор всех множеств модедей на всех барах ($models)
        usort($models_at_bar, "compare4alt"); //сортировка по возрастанию alt
        $models[$bar] = $models_at_bar;
        $alt_ind = 0;
        $ind = 0;
        foreach ($models_at_bar as $pk => $pv) {
            if ($pv['param']['G1'] == 'WEDGE') { // если модель=КЛИН, то пропускаем ее, устанавливая ей alt=0
                $models[$bar][$pk]['param']['alt'] = 0;
                continue;
            }
            if ($ind == 0) $t4cur = $pv['t4']; // устанавливаем текущий т4 для начального бара
            $ind++;
            if ($pv['t4'] == $t4cur) $models[$bar][$pk]['param']['alt'] = $alt_ind;
            else {
                $models[$bar][$pk]['param']['alt'] = ++$alt_ind;
                $t4cur = $pv['t4'];
            }
        }
    }
    // 3. (п.4) рассчитываем: Сила модели по СТ
    //if(ALGORITHM_NUM==1)  // выполняем только для первого алгоритма, во втором _CT = null
    foreach ($models as $bar => $models_at_bar)
        foreach ($models_at_bar as $pk => $pv) if ($pv['param']['_CT'] ?? false) {
            $models[$bar][$pk]['param']['SPc'] = 'NA';
            $dist_t1_t3 = (isset($pv['t3\'']) ? $pv['t3\''] : $pv['t3']) - $pv['t1'];
            $dist_CT_t1 = $pv['t1'] - $pv['param']['calcP6t'];
            $param = $dist_CT_t1 / $dist_t1_t3;
            $models[$bar][$pk]['param']['SPc'] = ($param > 20) ? 0 : round($param, 1);
            // $models[$bar][$pk]['param']['SPctmp']=($param>20)?0:($param);
        }

    function compare_IDprevs_AI($model1, $model2)
    {
        if ($model1['t4'] > $model2['t4']) return (-1);
        if ($model1['t4'] < $model2['t4']) return (1);
        // if ($model1['t4'] == $model2['t4'])
        // {
        if ($model1['t1'] < $model2['t1']) return (-1);
        if ($model1['t1'] > $model2['t1']) return (1);
        if ($model1['param']['_points'] < $model2['param']['_points']) return (-1);
        // }
        return (1);
    }

    // 9. ПОИСК ПРЕДШЕСТВУЮЩИХ МОДЕЛЕЙ ДЛЯ МОДЕЛЕЙ АЛГОРИТМА 1.
    // global $Models_Alg1, $Chart;
    $Chart = json_decode($_POST['Chart'], true); // J полностью берет из джава скрипта график
    $Models_Alg1 = json_decode($_POST['Models1'], true); //  переданные модели Алгоритма_1 - потом будем использовать, пока просто проходит сквозняком и возвращается как модели первого алгоритма в 'Models' , результат данного расчета в 'Models2'
    // $State = myLog_start($State, "9");
    $cnt = 0;
    $npp = 0; // просто для лога - счетчик - сколько нашли IDprev для State

    // $Models_1 = [];
    if (ALGORITHM_NUM == 1)  // выполняем только для первого алгоритма
        foreach ($models as $bar => $models_at_bar)
            foreach ($models_at_bar as $pks => $State) {
                if (isset($Models_1)) unset($Models_1);
                $Models_1 = [];
                foreach ($models as $pkm => $Model_Alg1) if ($pkm < $bar)  // Для рассматриваемой модели перебираем в качестве кандидатов на предшествующие все модели Алгоритма I, у которой т.1 рнаьше т.1 рассматриваемой модели
                {
                    foreach ($Model_Alg1 as $pk => $Model_1) { //перебираем все модели из массива моделей первого алгоритма
                        // $State = myLog($State, "Проверка на старте - найдено IDprevs: " . count($Models_1));
                        $npp++;
                        $v1 = $Model_1['v'];
                        $v = $State['v'];
                        $cnt++;
                        if (!isset($State['2'])) $State = myLog($State, "Рассматриваем модель на pkm= " . $pkm . " в качестве предшествующей. State['2']= " . $State['2']);
                        // т.1 модели лежит перед т.1 рассматриваемой ВМП и т.4 лежит перед или совпадает с т.1 рассматриваемой модели
                        if (
                            $Model_1['t1'] >= $State['2']
                            // && $Model_1['t1'] < $State['t3']
                            // && $Model_1['t4'] <= $State['t3']
                            && $Model_1['t1'] < $State['t1']
                            && $Model_1['t4'] <= $State['t1']
                        ) {
                            $State = myLog($State, "проверяем модель m1-$pkm-$pk"); // $pkm - номер бара т.1,$pk - порядковый номер моделей на этой 1-ой точке
                            // ищем правую границу поиска t6/t6Supp
                            $LT = LT($Model_1); // обращаемся с Model а не State - там тоже есть нужные параметры (t1,t3,t3') // линия тренда прешествующей модели
                            $isLTbreakFound = false; // нашли ли пересечение/касание LT(1) или просто дошли до  STEP10LIMIT? т.е. ищем реальную Т6 или P6supp

                            $searchborder = $State['t4'];
                            if (isset($State['conf_t4'])) $searchborder = $State['conf_t4'];
                            // for ($i = $Model_1['t4'] + 1; $i < $Model_1['t1'] + STEP10LIMIT && $i < nBars; $i++) { //ищем пересечение ЛТ(потенциальной предшествующей модели - I) но не далее глубины поиска для step_10 или конца графика
                            // for ($i = $Model_1['t4'] + 1; $i < $searchborder && $i < nBars; $i++) { //ищем пересечение ЛТ(1) но не далее  подтверждения т.4 рассматриваемой модели или конца графика
                            for ($i = $Model_1['t4']; $i < $searchborder && $i < nBars; $i++) { //ищем пересечение ЛТ(потенциальной предшествующей модели - I) но не далее точки подтверждения т.4 рассматриваемой модели/бара подтверждения т.4 или конца графика
                                if (low($i, $v1) <= lineLevel($LT, $i)) { // есть пробой/касание ЛТ(I)
                                    $isLTbreakFound = true;
                                    // $LT_broken = $i;
                                    $LT_broken_searchborder = $i;
                                    break;
                                }
                            }

                            // if (!isset($LT_broken)) {
                            // $LT_broken = $i - 1; // если дошли до глубины поиска и не нашли касания LT, то просто устанавливаем правую границу на глубину поиска
                            // if (!isset($LT_broken_searchborder)) {
                            if ($isLTbreakFound == false) {
                                $LT_broken_searchborder = $searchborder; // если дошли до т.4/подтверждения т.4 рассматриваемой модели и не нашли касания LT, то просто устанавливаем правую границу на т.4/подтверждения т.4 рассматриваемой модели
                                // $State = myLog($State, " дошли до глубины поиска пробоя ЛТ $LT_broken");
                                // $State = myLog($State, " дошли до глубины поиска пробоя ЛТ $LT_broken_searchborder");
                                $State = myLog($State, " дошли до пробоя ЛТ/т.4 или подтверждения т.4 рассматриваемой модели $LT_broken_searchborder");
                            }
                            // $State = myLog($State, " прошли цикл поиска пробоя ЛТ с " . ($Model_1['t1'] + 1) . " по " . ($i - 1) . " isLTbreakFound=$LT_broken");
                            // $State = myLog($State, " прошли цикл поиска пробоя ЛТ с " . ($Model_1['t1'] + 1) . " по " . ($i - 1) . " isLTbreakFound=$LT_broken_searchborder");
                            $State = myLog($State, " прошли цикл поиска пробоя ЛТ с " . $Model_1['t4'] . " по " . ($i - 1) . " isLTbreakFound=$LT_broken_searchborder");
                            // далее мщем абсолютный экстремум (t6/t6Supp)
                            $t6_level = low($Model_1['t1'], $v1); // начальный - мин.уровень, он точно должен быть превышен
                            // $State = myLog($State, " ищем реальную t6 с " . $Model_1['t1'] . " до $LT_broken");
                            $State = myLog($State, " ищем реальную t6 с " . $Model_1['t1'] . " до $LT_broken_searchborder");
                            // for ($i = $Model_1['t1']; $i < $LT_broken; $i++) {
                            unset($t6);
                            for ($i = $Model_1['t1']; $i < $LT_broken_searchborder; $i++) {
                                if (is_extremum($i, not_v($v1)) && high($i, $v1) > $t6_level) {
                                    $t6_level = high($i, $v1);
                                    $t6 = $i; // новый кандидат в абс. экстремум (t6)
                                    // $State = myLog($State, " нашли кандидат в t6 $t6");
                                }
                            }
                            $State = myLog($State, " реальная т.6 для   m1-$pkm-$pk t6= $t6");

                            if (isset($t6)) {
                                if ($isLTbreakFound) $Model_1['t6'] = $t6; // если был пробой, значит найдена реальная t6 предшествующей модели
                                else  $Model_1['t6Supp'] = $t6; // иначе - предполагаемая t6
                                // определяемм "тип" модели - "непосредственно предшествующая" или "предшествующая коррекционная" или "none" - если нам не подходит
                                $Model_1['type'] = "none"; // первоначально присваиваемое значение
                                $Model_1['key'] = 'm1-' . $pkm . '-' . $pk; // $pkm - номер бара т.1, $pk - порядковый номер моделей на этой 1-ой точке
                            }

                            $t6_ind = isset($Model_1['t6']) ? 't6' : 't6Supp';
                            if (!isset($Model_1[$t6_ind])) {
                                $State = myLog($State, "TMP ERROR - не определена t6_ind");
                                $State['status']['ЗАГАДОЧНАЯ ОШИБКА не определена t6_ind max_bar=' . date("Y-m-d H:i:s", $Chart[nBars - 1]['close_time'])] = 0;
                            }

                            // if($Model_1[$t6_ind]==$State['t3']){ // t6(1) совпал с t3 ВМР
                            //     $Model_1['type']="непосредственно предшествующая";
                            //     $Model_1['key'].="-".$Model_1[$t6_ind]."-1"; // добавляем информацию к ключу (t6+ тип модели) (на случай, если потом не будем копировать сами модеди а только ключи)
                            // }
                            // else{
                            //     $Model_1['type']="предшествующая коррекционная";
                            //     $Model_1['key'].="-".$Model_1[$t6_ind]."-2"; // добавляем информацию к ключу  (тип=2 = предшествующая коррекционная)
                            // }
                            // $State=myLog($State,"Добавили модель ".$Model_1['key']);
                            // if(isset($Model_1[$t6_ind]))$Models_1[]=$Model_1; // добавляем в массив найденных моделей

                            // определяемм "тип" модели - "непосредственно предшествующая" или "предшествующая коррекционная" или "none" - если нам не подходит
                            if (isset($Model_1[$t6_ind])) {
                                // if ($t6_ind <= $State['t3']) { // Реальная/предполагаемая т.6 которых лежит перед или равна т.3 ВМП;
                                //     if ($Model_1[$t6_ind] == $State['t3']
                                // if ($t6_ind <= $State['t1']) { // Реальная/предполагаемая т.6 которых лежит перед или равна т.1 рассматриваемой модели;
                                // if ($t6_ind == $State['t1']) { // Реальная/предполагаемая т.6 которых равна т.1 рассматриваемой модели;
                                if (
                                    $Model_1[$t6_ind] == $State['t1'] // Реальная/предполагаемая т.6 которых равна т.1 рассматриваемой модели;
                                    && $v1 != $State['v'] // при этом модель противонаправленна рассматриваемой
                                ) { // t6(I) совпал с t3 ВМП
                                    $Model_1['type'] = "непосредственно предшествующая";
                                    $Model_1['key'] .= "-" . $Model_1[$t6_ind] . "-1"; // добавляем информацию к ключу (t6+ тип модели) (на случай, если потом не будем копировать сами модели а только ключи)
                                    $State = myLog($State, "Добавили модель " . $Model_1['key']);
                                    $Models_1[] = $Model_1; // добавляем в массив найденных моделей
                                }
                                // else
                                // ПРЕДШЕСТВУЮЩИЕ КОРРЕКЦИОННЫЕ ДЛЯ АЛГОРИТМА I НЕ НУЖНЫ,ПОЭТОМУ ЗАКОМЕНТИРОВАН БЛОК
                                // if ($Model_1[$t6_ind] <= $State['t3']
                                // && $v1 == $State['v']
                                // // ВНЕСЕНО 30.06.2021 (РАНЕЕ НЕ БЫЛО УЧТЕНО)
                                // && high($Model_1[$t6_ind], $v1) < high($State['t4'], $v)
                                // ) {
                                //     $Model_1['type'] = "предшествующая коррекционная"; // J определяем t4-тип
                                //     $Model_1['key'] .= "-" . $Model_1[$t6_ind] . "-2"; // добавляем информацию к ключу  (тип=2 = предшествующая коррекционная)
                                //     $State = myLog($State, "Добавили модель " . $Model_1['key']);
                                //     $Models_1[] = $Model_1; // добавляем в массив найденных моделей
                                // }
                                // $State = myLog($State, "Добавили модель " . $Model_1['key']);
                                // $Models_1[] = $Model_1; // добавляем в массив найденных моделей
                                // }
                            }
                        }
                    } //перебрали все модели (I) по каждой т1
                } // Закрываем формирование массива предшествующих моделей для рассматриваемой модели Алгоритма I

                // * Из моделей с одинаковой t4(I) оставляем по одной модели каждого типа "непосредственно предшествующая" и "предшествующая коррекционная"
                // оставляем по одной модели каждого типа (см.выше) - для каждой t4(I) - для этого сперва определяем массив t4(1)-тип
                $t4_1_keys = [];
                foreach ($Models_1 as $m) if (!in_array(($m['t4'] . '-' . $m['type']), $t4_1_keys)) $t4_1_keys[] = $m['t4'] . '-' . $m['type']; // добавляем новую t4-тип из массива отобранных моделей(1)
                // $State = myLog($State, " Рассматриваем $m c " . $m['t4'] . '-' . $m['type'] );
                usort($Models_1, "compare_IDprevs_AI");
                foreach ($t4_1_keys as $t4_key) { // для каждого т4(1) выбираем по одной модели каждого типа
                    $arTmp = explode('-', $t4_key);
                    $t4_ = $arTmp[0]; // номер бара t4
                    $type_ = $arTmp[1]; // тип
                    $lastT5 = -1; // последняя найденная t5 (устанавливаем начальное значение -1)
                    $lastPk = -1; // индекс модели  с последней найденной t5 (устанавливаем начальное значение -1)
                    foreach ($Models_1 as $pk => $m) { // перебор всех найденных моделей
                        if ($m['t4'] == $t4_ && $m['type'] == $type_) { // берем только нужные t4 и тип (из внешнего цикла
                            $t5_ = (isset($m['t5'])) ? $m['t5'] : (nBars + 1); // t5 либо 0, если t5 не определена
                            if ($lastT5 < $t5_) { // эта модель "лучше"?
                                if ($lastPk >= 0) unset($Models_1[$lastPk]); // стираем ранее найденную, если такая есть
                                $lastPk = $pk;
                                $lastT5 = $t5_;
                            }
                        }
                    }
                }

                // * Сортируем модели по времени т.4 и оставляем только последние 10
                // $State = myLog($State, "найдено IDprevs: " . count($Models_1));
                // $State['status']['TMP найдено IDprevs: ' . count($Models_1)] = 0;
                usort($Models_1, "compare_IDprevs_AI"); // Сортируем модели таким образом,чтобы первыми были модели с наиболее поздними т.4, а из равных  по т.4 - с наиболее ранними т.1)
                if (count($Models_1) > 10) { // редкий случай - осталось более 10 моделей
                    $State = myLog($State, "Осталось >10 моделей - оставляем 10 последних - у которых t4 позднее других");
                    $Models_1 = array_slice($Models_1, 0, 10); // оставляем первые 10 после сортировки
                }

                // ! added 13/01/22 Вносим в рассматриваемую модель параметр,отражающий тип значимой предшествующей модели
                if (isset($Models_1[0])) {
                    $State['param']['PrevType'] = $Models_1[0]['param']['G1'];
                    $State['param']['PrevG3'] = $Models_1[0]['param']['G3'];
                    if (isset($Models_1[0]['param']['SPc'])) {
                        $State['param']['PrevSPc'] = $Models_1[0]['param']['SPc'];
                    }
                    if (isset($Models_1[0]['param']['SP'])) {
                        $State['param']['PrevSP'] = $Models_1[0]['param']['SP'];
                    }
                    // $State['param']['PrevT1'] = $Models_1[0]['t1']; //  Временный параметр для тестирования,убрать
                    // $State['param']['PrevT4'] = $Models_1[0]['t4']; //  Временный параметр для тестирования,убрать
                }

                $IDprevs = [];
                foreach ($Models_1 as $pk => $pv) $IDprevs[] = $pv['key']; // создаём массив ключей
                //$State['IDprevs']=$Models_1;
                $State['IDprevs'] = $IDprevs; // заносим массив с ключами в State
                if (isset($State['IDprevs'][0]))
                    // $State['IDprevs'][0] = $State['IDprevs'][0] . "-" . "IDprev_1"; // ближайшую (непосредственно) предшествующую модель назначаем значимой (добавляем IDprev_1 к ключу)
                    $State['IDprevs'][0] = $State['IDprevs'][0] . "-" . "IDprev_0"; // ближайшую (непосредственно) предшествующую модель назначаем значимой (добавляем IDprev_1 к ключу)

                // foreach ($Models_1 as $pk => $pv) {
                // if($Models_1[$pk]['G1'] != 'WEDGE' {
                // }
                if ((count($Models_1) > 0)
                    // && (abs(low($State['t1'], $v)) - abs(low($Models_1[0]['t1'], $Models_1[0]['v']))) != 0
                ) {
                    if (
                        isset($State['param']['calcP6'])
                        && (abs($State['param']['calcP6']) - abs(low($State['t1'], $v)) != 0)
                        && $State['param']['G1'] != 'EM'
                        && $State['param']['G1'] != 'DBM'
                        && $State['param']['G1'] != 'EM/DBM' //! Добавлено 12.03.23
                    ) {
                        // $State = myLog($State, "calcP6= " . abs($State['param']['calcP6']));
                        // $State = myLog($State, "t1= " . abs(low($State['t1'],$v)));
                        // $State = myLog($State, "размер рассматриваемой модели= " . abs(abs($State['param']['calcP6']) - abs(low($State['t1'],$v))));
                        // $State = myLog($State, "6-ая предшествующей модели= " . abs(low($State['t1'], $v1))); // т.6 предшествующей модели совпадает с т.6 рассматриваемой модели
                        // $State = myLog($State, "t1 предшествующей модели= " .  abs(low($Models_1[0]['t1'],$v1)));
                        // $State = myLog($State, "размер предшествующей модели= " . abs( round(abs(low($State['t1'], $v)) - abs(low($Models_1[0]['t1'],$v1)),5) ));

                        // && (abs(low($State['t1'], $v)) - abs(low($Models_1[0]['t1'], $v1)) != 0)
                        $State['param']['P6Context'] =
                            abs(round(
                                (abs(low($State['t1'], $v)) - abs(low($Models_1[0]['t1'], $Models_1[0]['v']))) /
                                (abs($State['param']['calcP6']) - abs(low($State['t1'], $v))),
                                2
                            )) * 100 . "%";
                        //$State['param']['DEBUG_P6Context']="!!!(1)!!! ".abs(low($State['t1'], $v))."|".abs(low($Models_1[0]['t1'], $Models_1[0]['v']))."|".abs($State['param']['calcP6'])."|".abs(low($State['t1'], $v));
                        $State = myLog($State, "отношение предшествующей модели (по реальной т.6) к размеру рассматриваемой модели (для расчетной т.6)  = " . $State['param']['P6Context']);

                        if (
                            isset($Models_1[0]['param']['calcP6'])
                            && $Models_1[0]['param']['G1'] != 'DBM'
                            && $Models_1[0]['param']['G1'] != 'AM/DBM'
                        ) {
                            $State['param']['P6ContextOfP6'] =
                                abs(round(
                                    (abs($Models_1[0]['param']['calcP6']) - abs(low($Models_1[0]['t1'], $Models_1[0]['v']))) /
                                    (abs($State['param']['calcP6']) - abs(low($State['t1'], $v))),
                                    2
                                )) * 100 . "%";
                            $State = myLog($State, "отношение предшествующей модели (по расчётной т.6) к размеру рассматриваемой модели (для расчетной т.6)  = " . $State['param']['P6ContextOfP6']);
                        };

                        if (isset($Models_1[0]['param']['auxP6'])) {
                            $State['param']['P6ContextOfAuxP6'] =
                                abs(round(
                                    (abs($Models_1[0]['param']['auxP6']) - abs(low($Models_1[0]['t1'], $Models_1[0]['v']))) /
                                    (abs($State['param']['calcP6']) - abs(low($State['t1'], $v))),
                                    2
                                )) * 100 . "%";
                            $State = myLog($State, "отношение предшествующей модели (по расчётной т.6 вспомогательной МП) к размеру рассматриваемой модели (для расчётной т.6) = " . $State['param']['P6ContextOfAuxP6']);
                        }
                    }

                    if (
                        isset($State['param']['auxP6'])
                        && (abs($State['param']['auxP6']) - abs(low($State['t1'], $v)) != 0)
                    )
                        // && $State['param']['G1'] != 'EM')
                    {
                        // $State = myLog($State, "auxP6= " . abs($State['param']['auxP6']));
                        // $State = myLog($State, "t1= " . abs(low($State['t1'],$v)));
                        // $State = myLog($State, "размер рассматриваемой модели= " . abs(abs($State['param']['auxP6']) - abs(low($State['t1'],$v)) ) );
                        // $State = myLog($State, "6-ая предшествующей модели= " . abs(low($State['t1'], $v1))); // т.6 предшествующей модели совпадает с т.6 рассматриваемой модели
                        // $State = myLog($State, "t1 предшествующей модели= " .  abs(low($Models_1[0]['t1'],$v1)));
                        // $State = myLog($State, "размер предшествующей модели= " . abs( round(abs(low($State['t1'], $v)) - abs(low($Models_1[0]['t1'],$v1)),5)));
                        $State['param']['auxP6Context'] =
                            abs(round(
                                (abs(low($State['t1'], $v)) - abs(low($Models_1[0]['t1'], $Models_1[0]['v']))) /
                                (abs($State['param']['auxP6']) - abs(low($State['t1'], $v))),
                                2
                            )) * 100 . "%";
                        $State = myLog($State, "отношение предшествующей модели (по реальной т.6) к размеру рассматриваемой модели (для расчётной 6 вспомогательной МП) =" . $State['param']['auxP6Context']);

                        if (
                            isset($Models_1[0]['param']['calcP6'])
                            && $Models_1[0]['param']['G1'] != 'DBM'
                            && $Models_1[0]['param']['G1'] != 'AM/DBM'
                        ) {
                            $State['param']['auxP6ContextOfP6'] =
                                abs(round(
                                    (abs($Models_1[0]['param']['calcP6']) - abs(low($Models_1[0]['t1'], $Models_1[0]['v']))) /
                                    (abs($State['param']['auxP6']) - abs(low($State['t1'], $v))),
                                    2
                                )) * 100 . "%";
                            $State = myLog($State, "отношение предшествующей модели (по расчётной т.6) к размеру рассматриваемой модели (для расчетной т.6 вспомогательной МП)  = " . $State['param']['auxP6ContextOfP6']);
                        };

                        if (isset($Model_1[0]['param']['auxP6'])) {
                            $State['param']['auxP6ContextOfAuxP6'] =
                                abs(round(
                                    (abs($Models_1[0]['param']['auxP6']) - abs(low($Models_1[0]['t1'], $Models_1[0]['v']))) /
                                    (abs($State['param']['auxP6']) - abs(low($State['t1'], $v))),
                                    2
                                )) * 100 . "%";
                            $State = myLog($State, "отношение предшествующей модели (по расчётной т.6 вспомогательной МП) к размеру рассматриваемой модели (для расчётной т. 6 всопомогательной МП) =" . $State['param']['auxP6ContextOfAuxP6']);
                        }
                    }


                    if (
                        isset($State['param']['auxP6\''])
                        && (abs($State['param']['auxP6\'']) - abs(low($State['t1'], $v)) != 0)
                    )
                        // && $State['param']['G1'] != 'EM')
                    {
                        // $State = myLog($State, "auxP6'= " . abs($State['param']['auxP6\'']));
                        // $State = myLog($State, "t1= " . abs(low($State['t1'],$v)));
                        // $State = myLog($State, "размер рассматриваемой модели= " . abs(abs($State['param']['auxP6\'']) - abs(low($State['t1'],$v)) ) );
                        // $State = myLog($State, "6-ая предшествующей модели= " . abs(low($State['t1'], $v)));
                        // $State = myLog($State, "t1 предшествующей модели= " .  abs(low($Models_1[0]['t1'],$v1)));
                        // $State = myLog($State, "размер предшествующей модели= " . abs( round(abs(low($State['t1'], $v)) - abs(low($Models_1[0]['t1'],$v1)),5 )));
                        $State['param']['auxP6\'Context'] =
                            abs(round(
                                (abs(low($State['t1'], $v)) - abs(low($Models_1[0]['t1'], $Models_1[0]['v']))) /
                                (abs($State['param']['auxP6\'']) - abs(low($State['t1'], $v))),
                                2
                            )) * 100 . "%";
                        $State = myLog($State, "отношение предшествующей модели (по реальной т.6) к размеру рассматриваемой модели (для расчётной 6' всопомогательной МП) = " . $State['param']['auxP6\'Context']);

                        if (
                            isset($Models_1[0]['param']['calcP6'])
                            && $Models_1[0]['param']['G1'] != 'DBM'
                            && $Models_1[0]['param']['G1'] != 'AM/DBM'
                        ) {
                            $State['param']['auxP6\'ContextOfP6'] =
                                abs(round(
                                    (abs($Models_1[0]['param']['calcP6']) - abs(low($Models_1[0]['t1'], $Models_1[0]['v']))) /
                                    (abs($State['param']['auxP6\'']) - abs(low($State['t1'], $v))),
                                    2
                                )) * 100 . "%";
                            $State = myLog($State, "отношение предшествующей модели (по расчётной т.6 ) к размеру рассматриваемой модели (для расчетной т.6' вспомогательной МП)  = " . $State['param']['auxP6\'ContextOfP6']);
                        };

                        if (isset($Model_1[0]['param']['auxP6'])) {
                            $State['param']['auxP6\'ContextOfAuxP6'] =
                                abs(round(
                                    (abs($Models_1[0]['param']['auxP6']) - abs(low($Models_1[0]['t1'], $Models_1[0]['v']))) /
                                    (abs($State['param']['auxP6\'']) - abs(low($State['t1'], $v))),
                                    2
                                )) * 100 . "%";
                            $State = myLog($State, "отношение предшествующей модели (по расчётной т.6 вспомогательной МП) к размеру рассматриваемой модели (по расчётной т. 6' вспомогательной МП) =" . $State['param']['auxP6\'ContextOfAuxP6']);
                        }
                    }
                    // if (isset($State['param']['auxP6\'']))
                    // $State['param']["auxP6'Context"] = ($State['auxP6\''] - low($State['t1'], $v)) / ( $Models_1[0][$t6_level] - high($Models_1[0]['t1'], $v1)) * (-1); // расчётная 6-ая вспомогательной модели относительно предшествующей модели

                }
                // }

                // $IDprevs = [];
                // foreach ($Models_1 as $pk => $pv) {
                //     $IDprevs[] = $pv['key'];
                // }
                // //$State['IDprevs']=$Models_1;
                // $State['IDprevs'] = $IDprevs;
                // if(isset($State['IDprevs'][0]))
                // $State['IDprevs'][0] = $State['IDprevs'][0] . "-" . "IDprev_1";

                // $State['next_step'] = 'A2_step_11';
                // return ([$State]);

                // }
                // $State['param']['Prevs_v1'] = $v1;
                $State['param']['CountPrevs'] = count($Models_1);
                $State = myLog($State, "найдено IDprevs: " . count($Models_1));
                $State['status']['TMP найдено IDprevs: ' . count($Models_1)] = 0;
                // $models[$bar][$pk] = $State;
                $models[$bar][$pks] = $State;
            }


    // РАСЧЕТ ПАРАМЕТРОВ ПРЕДШЕСТВУЮЩИХ МОДЕЛЕЙ ДЛЯ АЛГОРИТМА II

    //     function compare_IDprevs_AII($model1, $model2)
    // {
    //     if ($model1['t4'] > $model2['t4']) return (-1);
    //     if ($model1['t4'] < $model2['t4']) return (1);
    //     // if ($model1['t4'] == $model2['t4'])
    //     // {
    //         if ($model1['t1'] < $model2['t1']) return (-1);
    //         if ($model1['t1'] > $model2['t1']) return (1);
    //     // }
    //     if ($model1['type'] == "непосредственно предшествующая") return (-1);
    //     if ($model1['type'] == "предшествующая коррекционная") return (1);
    //     return (0);
    // }
    if (ALGORITHM_NUM == 2)  // выполняем только для второго алгоритма
        foreach ($models as $bar => $models_at_bar)
            foreach ($models_at_bar as $pks => $State) { // перебираем все модели Алгоритма II
                if (isset($Models_1)) unset($Models_1);
                $Models_1 = [];
                // foreach ($Models_Alg1 as $pkm => $Model_Alg1)
                foreach ($Models_Alg1 as $pkm => $Model_Alg1)
                    if ($pkm < $State['t3'])
                        foreach ($Model_Alg1 as $pk => $Model_1) { //перебираем все модели из массива моделей Алгоритма I

                            $npp++;
                            $v1 = $Model_1['v'];
                            $v = $State['v'];
                            // $v1 = $Model_1['v'];
                            //$cnt++;

                            // т.1 модели лежит перед т.3 рассматриваемой ВМП и т.4 лежит перед или совпадает с т.3 рассматриваемой ВМП
                            if (
                                $Model_1['t1'] >= $State['A2Prev']
                                // && $Model_1['t1'] < $State['t3'] // перенесено в цикл моделей 1-го алгоритма
                                && $Model_1['t4'] <= $State['t3']
                            ) {
                                $State = myLog($State, "проверяем модель m1-$pkm-$pk"); // $pkm - номер бара т.1,$pk - порядковый номер моделей на этой 1-ой точке
                                // ищем правую границу поиска t6/t6Supp
                                $LT = LT($Model_1); // обращаемся с Model а не State - там тоже есть нужные параметры (t1,t3,t3') // линия тренда прешествующей модели
                                $isLTbreakFound = false; // нашли ли пересечение/касание LT(1) или просто дошли до  STEP10LIMIT? т.е. ищем реальную Т6 или P6supp

                                $searchborder = $State['t4'];
                                if (isset($State['conf_t4'])) $searchborder = $State['conf_t4'];
                                // for ($i = $Model_1['t4'] + 1; $i < $Model_1['t1'] + STEP10LIMIT && $i < nBars; $i++) { //ищем пересечение ЛТ(I) но не далее глубины поиска для step_10 или конца графика
                                // for ($i = $Model_1['t4'] + 1; $i < $searchborder && $i < nBars; $i++) { //ищем пересечение ЛТ(1) но не далее точки подтверждения т.4 рассматриваемой модели или конца графика
                                for ($i = $Model_1['t4']; $i < $searchborder && $i < nBars; $i++) { //ищем пересечение ЛТ(I) но не далее точки подтверждения т.4 рассматриваемой модел/бара подтверждения т.4 или конца графика
                                    if (low($i, $v1) <= lineLevel($LT, $i)) { // есть пробой/касание ЛТ(I)?
                                        $isLTbreakFound = true;
                                        // $LT_broken = $i;
                                        $LT_broken_searchborder = $i;
                                        break;
                                    }
                                }

                                // if (!isset($LT_broken)) {
                                // $LT_broken = $i - 1; // если дошли до глубины поиска и не нашли касания LT, то просто устанавливаем правую границу на глубину поиска
                                // if (!isset($LT_broken_searchborder)) {
                                if ($isLTbreakFound == false) {
                                    $LT_broken_searchborder = $searchborder; // если дошли до т.4/т.4 подтверждения т.4 рассматриваемой модели и не нашли касания LT, то просто устанавливаем правую границу на т.4/т.4 подтверждения рассматриваемой модели
                                    // $State = myLog($State, " дошли до глубины поиска пробоя ЛТ $LT_broken");
                                    // $State = myLog($State, " дошли до глубины поиска пробоя ЛТ $LT_broken_searchborder");
                                    $State = myLog($State, " дошли до пробоя ЛТ/т.4 или подтверждения т.4 рассматриваемой модели $LT_broken_searchborder");
                                }
                                // $State = myLog($State, " прошли цикл поиска пробоя ЛТ с " . ($Model_1['t1'] + 1) . " по " . ($i - 1) . " isLTbreakFound=$LT_broken");
                                // $State = myLog($State, " прошли цикл поиска пробоя ЛТ с " . ($Model_1['t1'] + 1) . " по " . ($i - 1) . " isLTbreakFound=$LT_broken_searchborder");
                                $State = myLog($State, " прошли цикл поиска пробоя ЛТ с " . $Model_1['t4'] . " по " . ($i - 1) . " isLTbreakFound=$LT_broken_searchborder");
                                // далее мщем абсолютный экстремум (t6/t6Supp)
                                $t6_level = low($Model_1['t1'], $v1); // начальный - мин.уровень, он точно должен быть превышен
                                // $State = myLog($State, " ищем реальную t6 с " . $Model_1['t1'] . " до $LT_broken");
                                $State = myLog($State, " ищем реальную t6 с " . $Model_1['t1'] . " до $LT_broken_searchborder");
                                // for ($i = $Model_1['t1']; $i < $LT_broken; $i++) {
                                unset($t6);
                                for ($i = $Model_1['t1']; $i < $LT_broken_searchborder; $i++) {
                                    if (is_extremum($i, not_v($v1)) && high($i, $v1) > $t6_level) {
                                        $t6_level = high($i, $v1);
                                        $t6 = $i; // новый кандидат в абс. экстремум (t6)
                                        // $State = myLog($State, " нашли кандидат в t6 $t6");
                                    }
                                }

                                if (isset($t6)) {
                                    if ($isLTbreakFound) $Model_1['t6'] = $t6; // если был пробой, значит найдена реальная t6 предшествующей модели
                                    else  $Model_1['t6Supp'] = $t6; // иначе - предполагаемая t6
                                    // определяемм "тип" модели - "непосредственно предшествующая" или "предшествующая коррекционная" или "none" - если нам не подходит
                                    $Model_1['type'] = "none"; // первоначально присваиваемое значение
                                    $Model_1['key'] = 'm1-' . $pkm . '-' . $pk; // $pkm - номер бара т.1,$pk - порядковый номер моделей на этой 1-ой точке
                                }
                                $t6_ind = isset($Model_1['t6']) ? 't6' : 't6Supp';
                                if (!isset($Model_1[$t6_ind])) {
                                    $State = myLog($State, "TMP ERROR - не определена t6_ind");
                                    $State['status']['ЗАГАДОЧНАЯ ОШИБКА не определена t6_ind max_bar=' . date("Y-m-d H:i:s", $Chart[nBars - 1]['close_time'])] = 0;
                                }

                                // if($Model_1[$t6_ind]==$State['t3']){ // t6(1) совпал с t3 ВМР
                                //     $Model_1['type']="непосредственно предшествующая";
                                //     $Model_1['key'].="-".$Model_1[$t6_ind]."-1"; // добавляем информацию к ключу (t6+ тип модели) (на случай, если потом не будем копировать сами модеди а только ключи)
                                // }
                                // else{
                                //     $Model_1['type']="предшествующая коррекционная";
                                //     $Model_1['key'].="-".$Model_1[$t6_ind]."-2"; // добавляем информацию к ключу  (тип=2 = предшествующая коррекционная)
                                // }
                                // $State=myLog($State,"Добавили модель ".$Model_1['key']);
                                // if(isset($Model_1[$t6_ind]))$Models_1[]=$Model_1; // добавляем в массив найденных моделей

                                if (isset($Model_1[$t6_ind])) {
                                    // if ($t6_ind <= $State['t3']) { // Реальная/предполагаемая т.6 которых лежит перед или равна т.3 ВМП;
                                    // if ($t6_ind == $State['t3']) { // Реальная/предполагаемая т.6 которых лежит перед или равна т.3 ВМП;
                                    if ($Model_1[$t6_ind] == $State['t3']) { // Реальная/предполагаемая т.6 которых лежит перед или равна т.3 ВМП;
                                        if (
                                            // $Model_1[$t6_ind] == $State['t3'] &&
                                            $v1 != $State['v'] // при этом модель противонаправленна рассматриваемой
                                        ) { // t6(I) совпал с t3 ВМП
                                            $Model_1['type'] = "непосредственно предшествующая";
                                            $Model_1['key'] .= "-" . $Model_1[$t6_ind] . "-1"; // добавляем информацию к ключу (t6+ тип модели) (на случай, если потом не будем копировать сами модели а только ключи)
                                            $State = myLog($State, "Добавили модель " . $Model_1['key']);
                                            $Models_1[] = $Model_1; // добавляем в массив найденных моделей
                                        }
                                        // else

                                        if (
                                            $Model_1[$t6_ind] <= $State['t3']
                                            && $v1 == $State['v']
                                            // ВНЕСЕНО 30.06.2021 (РАНЕЕ НЕ БЫЛО УЧТЕНО)
                                            && high($Model_1[$t6_ind], $v1) < high($State['t4'], $v)
                                        ) {
                                            $Model_1['type'] = "предшествующая коррекционная"; // J определяем t4-тип
                                            $Model_1['key'] .= "-" . $Model_1[$t6_ind] . "-2"; // добавляем информацию к ключу  (тип=2 = предшествующая коррекционная)
                                            $State = myLog($State, "Добавили модель " . $Model_1['key']);
                                            $Models_1[] = $Model_1; // добавляем в массив найденных моделей
                                        }
                                        // $State = myLog($State, "Добавили модель " . $Model_1['key']);
                                        // $Models_1[] = $Model_1; // добавляем в массив найденных моделей
                                    }
                                }
                            }
                        } //перебрали все модели (I) по каждой т1

                // Из моделей с одинаковой t4(I) оставляем по одной модели каждого типа "непосредственно предшествующая" и "предшествующая коррекционная"
                // оставляем по одной модели каждого типа (см.выше) - для каждой t4(I) - для этого сперва определяем массив t4(1)-тип
                $t4_1_keys = [];
                foreach ($Models_1 as $m) if (!in_array(($m['t4'] . '-' . $m['type']), $t4_1_keys)) $t4_1_keys[] = $m['t4'] . '-' . $m['type']; // добавляем новую t4-тип из массива отобранных моделей(1)
                usort($Models_1, "compare_IDprevs_AII");
                foreach ($t4_1_keys as $t4_key) { // для каждого т4(1) выбираем по одной модели каждого типа
                    $arTmp = explode('-', $t4_key);
                    $t4_ = $arTmp[0]; // номер бара t4
                    $type_ = $arTmp[1]; // тип
                    $lastT5 = -1; // последняя найденная t5
                    $lastPk = -1; // индекс модели  с последней найденной t5
                    foreach ($Models_1 as $pk => $m) { // перебор всех найденных моделей
                        if ($m['t4'] == $t4_ && $m['type'] == $type_) { // берем только нужные t4 и тип (из внешнего цикла
                            $t5_ = (isset($m['t5'])) ? $m['t5'] : (nBars + 1); // t5 либо 0, если t5 не определена
                            if ($lastT5 < $t5_) { // эта модель "лучше"?
                                if ($lastPk >= 0) unset($Models_1[$lastPk]); // стираем ранее найденную, если такая есть
                                $lastPk = $pk;
                                $lastT5 = $t5_;
                            }
                        }
                    }
                }

                // * Сортируем модели по времени т.4 и оставляем только последние 10
                $State['param']['CountPrevs'] = count($Models_1);
                $State = myLog($State, "найдено IDprevs: " . count($Models_1));
                $State['status']['TMP найдено IDprevs: ' . count($Models_1)] = 0;
                usort($Models_1, "compare_IDprevs_AII");
                if (count($Models_1) > 10) { // редкий случай - осталось более 10 моделей
                    $State = myLog($State, "Осталось >10 моделей - оставляем 10 последних - у которых t4 позднее других");
                    // usort($Models_1, "compare_IDprevs");
                    $Models_1 = array_slice($Models_1, 0, 10); // оставляем первые 10 после сортировки
                }

                // ! added 13/01/22 Вносим в рассматриваемую модель параметр,отражающий тип значимой предшествующей модели
                if (isset($Models_1[0])) {
                    $State['param']['PrevType'] = $Models_1[0]['param']['G1'];
                    $State['param']['PrevG3'] = $Models_1[0]['param']['G3'];
                    if (isset($Models_1[0]['param']['SPc'])) {
                        $State['param']['PrevSPc'] = $Models_1[0]['param']['SPc'];
                    }
                    if (isset($Models_1[0]['param']['SP'])) {
                        $State['param']['PrevSP'] = $Models_1[0]['param']['SP'];
                    }
                    // $State['param']['PrevT1'] = $Models_1[0]['t1']; //  Временный параметр для тестирования,убрать
                    // $State['param']['PrevT4'] = $Models_1[0]['t4']; //  Временный параметр для тестирования,убрать
                }

                if ((count($Models_1) > 0)
                    // && (( abs($t6_level) - abs(low($Models_1[0]['t1'],$v1))) != 0)
                    // && abs(low($State['t3'], $v1)) - abs(low($Models_1[0]['t1'], $v1))
                ) {
                    if (
                        isset($State['param']['calcP6'])
                        && (abs($State['param']['calcP6']) - abs(low($State['t3'], $v)) != 0)
                        // && $State['param']['G1'] != 'EM'
                    ) {
                        // $State = myLog($State, "calcP6= " . abs($State['param']['calcP6']));
                        // $State = myLog($State, "t3= " . abs(low($State['t3'],$v)));
                        // $State = myLog($State, "размер рассматриваемой модели= " . abs( abs($State['param']['calcP6']) - abs(low($State['t3'],$v))));
                        // $State = myLog($State, "6-ая предшествующей модели= " . abs(low($State['t3'], $v1)));
                        // $State = myLog($State, "t1 предшествующей модели= " .  abs(low($Models_1[0]['t1'],$v1)));
                        // $State = myLog($State, "размер предшествующей модели= " . abs( round(abs(low($State['t3'], $v)) - abs(low($Models_1[0]['t1'],$v1)),5)));
                        $State['param']['P6Context'] =
                            abs(round(
                                (abs(low($State['t3'], $v)) - abs(low($Models_1[0]['t1'], $Models_1[0]['v']))) /
                                (abs($State['param']['calcP6']) - abs(low($State['t3'], $v))),
                                2
                            )) * 100 . "%";
                        //$State['param']['DEBUG_P6Context']="!!!(2)!!! ".abs(low($State['t3'], $v))."|".abs(low($Models_1[0]['t1'], $Models_1[0]['v']))."|".abs($State['param']['calcP6'])."|".abs(low($State['t3'], $v));
                        $State = myLog($State, "отношение предшествующей модели (по реальной т.6) к размеру рассматриваемой модели (для расчетной т.6)  = " . $State['param']['P6Context']);

                        if (
                            isset($Models_1[0]['param']['calcP6'])
                            && $Models_1[0]['param']['G1'] != 'DBM'
                            && $Models_1[0]['param']['G1'] != 'AM/DBM'
                        ) {
                            $State['param']['P6ContextOfP6'] =
                                abs(round(
                                    (abs($Models_1[0]['param']['calcP6']) - abs(low($Models_1[0]['t1'], $Models_1[0]['t1']))) /
                                    (abs($State['param']['calcP6']) - abs(low($State['t3'], $v))),
                                    2
                                )) * 100 . "%";
                            $State = myLog($State, "отношение предшествующей модели (по расчётной т.6) к размеру рассматриваемой модели (для расчетной т.6)  = " . $State['param']['P6ContextOfP6']);
                        };

                        if (isset($Models_1[0]['param']['auxP6'])) {
                            $State['param']['P6ContextOfAuxP6'] =
                                abs(round(
                                    (abs($Models_1[0]['param']['auxP6']) - abs(low($Models_1[0]['t1'], $Models_1[0]['t1']))) /
                                    (abs($State['param']['calcP6']) - abs(low($State['t3'], $v))),
                                    2
                                )) * 100 . "%";
                            $State = myLog($State, "отношение предшествующей модели (по расчётной т.6 вспомогательной МП) к размеру рассматриваемой модели (для расчётной т.6) = " . $State['param']['P6ContextOfAuxP6']);
                        }
                    }

                    if (
                        isset($State['param']['calcP6"'])
                        // && $State['param']['G1'] != 'EM')
                        && (abs($State['param']['calcP6"']) - abs(low($State['t3'], $v)) != 0)
                    ) {
                        // $State = myLog($State, "calcP6\"= " . abs($State['param']['calcP6"']));
                        // $State = myLog($State, "t3= " . abs(low($State['t3'],$v)));
                        // $State = myLog($State, "размер рассматриваемой модели= " . abs(abs($State['param']['calcP6"']) - abs(low($State['t3'],$v))));
                        // $State = myLog($State, "6-ая предшествующей модели= " . abs(low($State['t3'], $v1)));
                        // $State = myLog($State, "t1 предшествующей модели= " . abs(low($Models_1[0]['t1'],$v1)));
                        // $State = myLog($State, "размер предшествующей модели= " . abs( round(abs(low($State['t3'], $v)) - abs(low($Models_1[0]['t1'],$v1)),5)));
                        $State['param']['P6"Context'] =
                            abs(round(
                                (abs(low($State['t3'], $v)) - abs(low($Models_1[0]['t1'], $Models_1[0]['t1']))) /
                                (abs($State['param']['calcP6"']) - abs(low($State['t3'], $v))),
                                2
                            )) * 100 . "%"; // расчётная 6" относительно предшествующей модели
                        $State = myLog($State, "отношение предшествующей модели (по реальной т.6) к размеру рассматриваемой модели (для расчётной 6\" ВМП) =" . $State['param']['calcP6"']);
                    }
                    // $State['param']['Prevs_v1'] = $v1;
                }
                $models[$bar][$pks] = $State;
            }
    // Закончили поиск предшествующих для моделей 2-го алгоритма



    // 4. (п.7)	Параметр, отражающий построена ли модель через т.3 или т.3'
    if (ALGORITHM_NUM == 2)
        foreach ($models as $bar => $models_at_bar) foreach ($models_at_bar as $pk => $pv) {
            $v = $pv['v'];
            if (isset($pv['t3\''])) {
                if (low($pv['t3\''], $v) < high($pv['t2'], $v)) $models[$bar][$pk]['param']['EAMP3'] = 'EAM3\'';
                else $models[$bar][$pk]['param']['EAMP3'] = 'EAM3\' out of Base';
            } else $models[$bar][$pk]['param']['EAMP3'] = 'EAM3';
        }
    // 5. (п.8) Расположение т.3(3') вспомогательной МП
    foreach ($models as $bar => $models_at_bar) foreach ($models_at_bar as $pk => $pv) {
        $v = $pv['v'];
        if (
            isset($models[$bar][$pk]['param']['AUX']) &&
            $models[$bar][$pk]['param']['AUX'] == 'AM'
        ) {
            if (isset($pv['t3\'мп'])) {
                if (low($pv['t3\'мп'], $v) > high($pv['t2'], $v)) {
                    $models[$bar][$pk]['param']['auxP3'] = '3\'outofb';
                } else {
                    $levelt2 = high($pv['t2'], $v);
                    $levelt2broken = false;
                    for ($i = $pv['t3']; $i < $pv['t3\'мп']; $i++) if (high($i, $v) > $levelt2) {
                        $levelt2broken = $i;
                        break;
                    }
                    if ($levelt2broken) $models[$bar][$pk]['param']['auxP3'] = '3\'aftrb';
                    else $models[$bar][$pk]['param']['auxP3'] = '3\'';
                }
            } else {
                $models[$bar][$pk]['param']['auxP3'] = '3';
            }
        }
        // else $models[$bar][$pk]['param']['auxP3'] = null;
        else unset($models[$bar][$pk]['param']['auxP3']);
    }
    // 6. (п.9) Параметр о построении через т. 3 или т. 3' для основных
    foreach ($models as $bar => $models_at_bar)
        foreach ($models_at_bar as $pk => $pv) {
            if (isset($pv['t3\''])) $models[$bar][$pk]['param']['P3'] = '3\'';
            else $models[$bar][$pk]['param']['P3'] = '3';
        }
    // 7. (п.10) Параметр, отражающий наличие и глубину пересечения баров 2 и 5 в процентах - ПРОВЕРИТЬ ЛОГИКУ - не совсем так, как в тексте - сделано по словесным комментариям!!!!
    foreach ($models as $bar => $models_at_bar) foreach ($models_at_bar as $pk => $pv) {
        $v = $pv['v'];
        if (!isset($pv['t5'])) {
            $models[$bar][$pk]['param']['Par25prcnt'] = "No6";
        } else
            if (high($pv['t2'], $v) < low($pv['t5'], $v)) { // Если бары точек 2 и 5 не пересекаются (бар 2 ниже бара 5), то отображается Par25prcnt=0%
                $models[$bar][$pk]['param']['Par25prcnt'] = "0%";
            } else {
                if (($dist_4_5 = abs(high($pv['t4'], $v) - low($pv['t5'], $v))) == 0) $intersectionProcent = 10000;
                else $intersectionProcent = (high($pv['t2'], $v) - low($pv['t5'], $v)) / $dist_4_5 * 100;
                $models[$bar][$pk]['param']['Par25prcnt'] = round($intersectionProcent, 0) . "%";
            }
    }
    // 8. (п.11) Параметр, отражающий наличие/отсутствие Пресуппозиции в ВМП
    //$tmpTime1=microtime(true);
    //if(ALGORITHM_NUM==2)
    foreach ($models as $bar => $models_at_bar) foreach ($models_at_bar as $pk => $pv) {
        if (isset($pv['Presupp']) && count($pv['Presupp']) > 0) $models[$bar][$pk]['param']['E6'] = "Pres";
        else $models[$bar][$pk]['param']['E6'] = "NoPres";
    }
    // 9.(п.12) Соотношение уровня между т.2 (2')-т.3 и т.2(2')-уровень расчетной т.6 для ВМП, ЧМП, МДР/ЧМП.
    foreach ($models as $bar => $models_at_bar) foreach ($models_at_bar as $pk => $pv) {
        $v = $pv['v'];
        if (in_array($pv['param']['G1'], ['EAM', 'AM', 'AM/DBM'])) { //Данный параметр рассчитывается для ВМП, ЧМП и МДР/ЧМП.
            // if(is_null($pv['param']['auxP6']??null)&&in_array($pv['param']['G1'],['AM','AM/DBM']))$models[$bar][$pk]['param']["lvl32'to2'6"]='NoAM';
            // убрано условие (не корректно в тексте, беседа 2020-10-27) т.к. параметр не касается вспомогательнйо МП
            //else {
            if (is_null($pv['param']['calcP6'] ?? null)) $models[$bar][$pk]['param']["lvl32'to2'6"] = 'NA';
            else {
                $t2_level = (isset($pv['t2\''])) ? high($pv['t2\''], $v) : high($pv['t2'], $v);
                $dist_3_2 = abs(low($pv['t3'], $v) - $t2_level);
                $dist_6_2 = abs(abs($pv['param']['calcP6']) - abs($t2_level));
                if ($dist_3_2 > $dist_6_2 && $dist_6_2 / $dist_3_2 < 0.01) $models[$bar][$pk]['param']["lvl32'to2'6"] = "NoAM_INF";
                else $models[$bar][$pk]['param']["lvl32'to2'6"] = round($dist_3_2 / $dist_6_2, 1);
            }
        }
    }
    // 10. (п.13) Соотношение уровней т.2 (2')-т.3 и т.2(2')-уровень расчетной т.6 для вспомогательных моделей.
    foreach ($models as $bar => $models_at_bar) foreach ($models_at_bar as $pk => $pv) {
        $v = $pv['v'];
        // if(in_array($pv['param']['G1'],['EAM','AM','AM/DBM'])){ //Данный параметр рассчитывается для ВМП, ЧМП и МДР/ЧМП.
        if (is_null($pv['param']['auxP6'] ?? null)) $models[$bar][$pk]['param']["lvl32'to2'6aux"] = 'NoAM';
        else {
            $t2_level = (isset($pv['t2\''])) ? high($pv['t2\''], $v) : high($pv['t2'], $v);
            $dist_3_2 = abs(low($pv['t3'], $v) - $t2_level);
            $dist_6_2 = abs(abs($pv['param']['auxP6']) - abs($t2_level));
            if ($dist_3_2 > $dist_6_2 && $dist_6_2 / $dist_3_2 < 0.01) $models[$bar][$pk]['param']["lvl32'to2'6aux"] = "NoAM_INF";
            else $models[$bar][$pk]['param']["lvl32'to2'6aux"] = round($dist_3_2 / $dist_6_2, 1);
        }
        // }

    }
    // 11. (п.14) Соотношение уровней т.3-т.4 и т.4-уровень расчетной т.6.
    foreach ($models as $bar => $models_at_bar) foreach ($models_at_bar as $pk => $pv) {
        $v = $pv['v'];
        if (in_array($pv['param']['G1'], ['EAM', 'AM', 'AM/DBM'])) { //Данный параметр рассчитывается для ВМП, ЧМП и МДР/ЧМП.
            if (is_null($pv['param']['calcP6'] ?? null)) $models[$bar][$pk]['param']["lvl34to46"] = 'NA';
            else {
                $val1 = abs(low($pv['t3'], $v) - high($pv['t4'], $v));
                $val2 = abs(abs(high($pv['t4'], $v)) - abs($pv['param']['calcP6']));
                if ($val1 > $val2 && $val2 / $val1 < 0.01) $models[$bar][$pk]['param']["lvl34to46"] = "NoAM_INF";
                else $models[$bar][$pk]['param']["lvl34to46"] = round($val1 / $val2, 1);
            }
        }
    }
    // 12. (п.15) Соотношение уровней т.3-т.4 и т.4-уровень расчетной т.6.
    foreach ($models as $bar => $models_at_bar) foreach ($models_at_bar as $pk => $pv) {
        $v = $pv['v'];
        if (!is_null($pv['param']['auxP6'] ?? null)) {
            $val1 = abs(low($pv['t3'], $v) - high($pv['t4'], $v));
            $val2 = abs(abs(high($pv['t4'], $v)) - abs($pv['param']['auxP6']));
            if ($val1 > $val2 && $val2 / $val1 < 0.01) $models[$bar][$pk]['param']["lvl34to46aux"] = "NoAM_INF";
            else $models[$bar][$pk]['param']["lvl34to46aux"] = round($val1 / $val2, 1);
        } else $models[$bar][$pk]['param']["lvl34to46aux"] = "NoAM";
    }
    // 13. (п.16) Соотношение между расстояниями от бара т.2(2') до т.5 к расстоянию от т.5 до т.6.
    foreach ($models as $bar => $models_at_bar) foreach ($models_at_bar as $pk => $pv) {
        $v = $pv['v'];
        if (in_array($pv['param']['G1'], ['EAM', 'AM', 'AM/DBM'])) { //Данный параметр рассчитывается для ВМП, ЧМП и МДР/ЧМП.
            // if (is_null($pv['param']['calcP6'] ?? null) || !isset($pv['t5'])) $models[$bar][$pk]['param']["ll2'5to56"] = 'NoAM';
            if (is_null($pv['param']['calcP6'] ?? null) || !isset($pv['t5'])) $models[$bar][$pk]['param']["ll2'5to56"] = 'NA';
            else {
                $t2_bar = (isset($pv['t2\''])) ? $pv['t2\''] : $pv['t2'];
                $dist_2_5 = abs($t2_bar - $pv['t5']);
                $dist_5_6 = abs($pv['t5'] - $pv['param']['calcP6t']);
                if ($dist_2_5 > $dist_5_6 && $dist_5_6 / $dist_2_5 < 0.01) $models[$bar][$pk]['param']["ll2'5to56"] = "NoAM_INF";
                else
                    $models[$bar][$pk]['param']["ll2'5to56"] = round($dist_2_5 / $dist_5_6, 1);
            }
        }
    }
    // 14. (п.17) Соотношение между расстояниями от бара т.2(2') до т.5 к расстоянию от т.5 до т.6 для вспомогательных моделей
    foreach ($models as $bar => $models_at_bar) foreach ($models_at_bar as $pk => $pv) {
        $v = $pv['v'];
        if (!is_null($pv['param']['auxP6'] ?? null) && isset($pv['t5'])) { //Данный параметр рассчитывается для ВМП, ЧМП и МДР/ЧМП.
            $t2_bar = (isset($pv['t2\''])) ? $pv['t2\''] : $pv['t2'];
            $dist_2_5 = abs($t2_bar - $pv['t5']);
            $dist_5_6 = abs($pv['t5'] - $pv['param']['auxP6t']);
            if ($dist_2_5 > $dist_5_6 && $dist_5_6 / $dist_2_5 < 0.01) $models[$bar][$pk]['param']["ll2'5to56aux"] = "NoAM_INF";
            else
                $models[$bar][$pk]['param']["ll2'5to56aux"] = round($dist_2_5 / $dist_5_6, 1);
        } else $models[$bar][$pk]['param']["ll2'5to56aux"] = "NoAM";
    }
    // 15. (п.21) Параметр, отражающий построена ли модель через т.5 или т.5"
    if (ALGORITHM_NUM == 2)
        foreach ($models as $bar => $models_at_bar) foreach ($models_at_bar as $pk => $pv) {
            // if (!isset($pv['t5"']))$models[$bar][$pk]['param']['EAM5"'] = 'EAMtl5"';
            if (isset($pv['t5"'])) $models[$bar][$pk]['param']['EAM5"'] = 'EAMtl5"';
            else $models[$bar][$pk]['param']['EAM5"'] = 'NoEAMtl5"';
        }
    // 16. (п.22)параметр, который будет отражать, является ли точка 5, через которую построена МП, абсолютным экстремумом на участке между т.4 и точкой возврата цены к уровню т.4.
    // Параметр фиксируется при возврате цены к уровню т.4 после т.5 (т.е. при подтверждении т.4). Параметр рассчитывается для ВМП и вспомогательных МП. (только для Алг1 при наличии auxP6
    foreach ($models as $bar => $models_at_bar) foreach ($models_at_bar as $pk => $pv) if (ALGORITHM_NUM == 1 && !is_null($pv['param']['auxP6'] ?? null)) { //только для Алг1 при наличии auxP6
        if (!isset($pv['t5'])) {  // Если модель без 6-ой, в отчете отображается: abs5='No6';
            $models[$bar][$pk]['param']['abs5'] = "No6";
        } else {
            $v = $pv['v'];
            $level_t5 = low($pv['t5'], $v);
            $level_t4 = high($pv['t4'], $v);
            $models[$bar][$pk]['param']['abs5'] = "5"; // ставим пока, если уровень пробъется, то изменится
            for ($i = $pv['t4'] + 1; $i < nBars; $i++) if ($i !== $pv['t5']) {
                if (is_extremum($i, $v) && low($i, $v) < $level_t5) {
                    $models[$bar][$pk]['param']['abs5'] = "5'"; // экстремум не абсолютный, выходим
                    break;
                }
                if (high($i, $v) >= $level_t4) break; // пробили т4 - закончили цикл
            }
        }
    }
    // 17. (п.23) Параметры с точкой т.3#
    foreach ($models as $bar => $models_at_bar) foreach ($models_at_bar as $pk => $pv) if ((ALGORITHM_NUM == 2 || isset($pv['param']['auxP6'])) && isset($pv['t5'])) { // только для алгоритма 2 или вспомогательных моделей первого алгоритма
        $v = $pv['v'];
        $t2name = isset($pv['t2\'']) ? 't2\'' : 't2';
        $t5name = 't5'; // isset($pv['t5"'])?'t5"':'t5'; - оставили для будущих времен
        if ($pv[$t5name] > $pv['t4']) {
            $t3xline = ['bar' => $pv[$t2name], 'level' => high($pv[$t2name], $v), 'angle' => (low($pv[$t5name], $v) - high($pv['t4'], $v)) / ($pv[$t5name] - $pv['t4'])];
            $t3name = (isset($pv['t3\'мп'])) ? 't3\'мп' : ((isset($pv['t3\''])) ? 't3\'' : 't3');
            //            if(ALGORITHM_NUM==1){
            $LT = ['bar' => $pv[$t3name], 'level' => low($pv[$t3name], $v), 'angle' => (low($pv[$t5name], $v) - low($pv[$t3name], $v)) / ($pv[$t5name] - $pv[$t3name])];
            //            }
            //            else{
            //                $LT=['bar' => $pv[$t3name], 'level' => low($pv[$t3name], $v), 'angle' => (low($pv['t5'], $v) - low($pv[$t3name], $v)) / ($pv['t5'] - $pv[$t3name])];
            //            }
            $li = linesIntersection($t3xline, $LT);
            $models[$bar][$pk]['param']['_cross_p23'] = round($li['bar'], 3) . " (" . round(abs($li['level']), 5) . ")";

            $dist_3x_t5 = abs($li['bar'] - $pv[$t5name]);
            $dist_t2_3x = abs($li['bar'] - $pv[$t2name]);
            $models[$bar][$pk]['param']["ll2'3#5"] = round($dist_3x_t5 / $dist_t2_3x, 2); // расстояние по времени от т.3# до т.5 (или т.5' если модель построена через т.5') делить на расстояние от т.2 (или 2') до т.3#

            if (ALGORITHM_NUM == 1 || isset($pv['param']['calcP6t']) && isset($pv['param']['calcP6t'])) { //  The code checks if either ALGORITHM_NUM is equal to 1 or if the keys calcP6t or auxP6t exist in the $pv array. If either of these conditions is true, it proceeds to calculate and store values in the $models array.


                // First, the code calculates the value of $dist_t5_P6 as the absolute difference between $pv['param']['calcP6t'] or $pv['param']['auxP6t'] (depending on the value of ALGORITHM_NUM) and $pv[$t5name].
                if (ALGORITHM_NUM == 2) $dist_t5_P6 = abs($pv['param']['calcP6t'] - $pv[$t5name]);
                else $dist_t5_P6 = abs($pv['param']['auxP6t'] - $pv[$t5name]);

                // The result is then divided by $dist_t2_3x and rounded to 2 decimal places, and stored in the $models array under the key "ll2'3#56".
                $models[$bar][$pk]['param']["ll2'3#56"] = round($dist_t5_P6 / $dist_t2_3x, 2); // расстояние по времени от т.5(или т.5') до узла т.6 делить на расстояние от т.2 (или т.2') до т.3#

                // Next, the code calculates the value of $level_dist_3x_t2 as the absolute difference between the absolute value of $li['level'] (p3# level) and the absolute value of p2/p2'.
                $level_dist_3x_t2 = abs(abs($li['level']) - abs(high($pv[$t2name], $v)));

                // Then, it calculates $level_dist_t2_P6 as the absolute difference between the absolute value of $pv['param']['calcP6'] or $pv['param']['auxP6'] (depending on the value of ALGORITHM_NUM) and the absolute value of the higher value of of p2/p2'.
                if (ALGORITHM_NUM == 2) $level_dist_t2_P6 = abs(abs($pv['param']['calcP6']) - abs(high($pv[$t2name], $v)));
                else $level_dist_t2_P6 = abs(abs($pv['param']['auxP6']) - abs(high($pv[$t2name], $v)));

                // Finally, the code compares $level_dist_t2_P6 with $level_dist_3x_t2 by multiplying $level_dist_t2_P6 by 100 and checking if the result is less than $level_dist_3x_t2. If it is, the value 100 is stored in the $models array under the key "lvl3#2'6".
                if ($level_dist_t2_P6 * 100 < $level_dist_3x_t2) $models[$bar][$pk]['param']["lvl3#2'6"] = 100;
                //Otherwise, the result of dividing $level_dist_3x_t2 by $level_dist_t2_P6 and rounding to 2 decimal places is stored under the same key.
                else $models[$bar][$pk]['param']["lvl3#2'6"] = round($level_dist_3x_t2 / $level_dist_t2_P6, 2); // cоотношение расстояния между между уровнями т.3# и т.2(2') к расстоянию между уровнями т.2(2') и расч. т.6

                // The same process is repeated for the calculation of the value of "lvl3#46", but this time the values used are $pv['t4'] instead of $pv[$t2name].
                $level_dist_3x_t4 = abs(abs($li['level']) - abs(high($pv['t4'], $v)));
                if (ALGORITHM_NUM == 2) $level_dist_t4_P6 = abs(abs($pv['param']['calcP6']) - abs(high($pv['t4'], $v)));
                else $level_dist_t4_P6 = abs(abs($pv['param']['auxP6']) - abs(high($pv['t4'], $v)));
                if ($level_dist_t4_P6 * 100 < $level_dist_3x_t4) $models[$bar][$pk]['param']["lvl3#46"] = 100;
                else $models[$bar][$pk]['param']["lvl3#46"] = round($level_dist_3x_t4 / $level_dist_t4_P6, 2); // cоотношение расстояния между между уровнями т.3# и т.2(2') к расстоянию между уровнями т.2(2') и расч. т.6
            }
            $level_dist_t4_t5 = abs(high($pv['t4'], $v) - low($pv[$t5name], $v));
            if ($level_dist_t4_t5 * 100 < $level_dist_3x_t2) $models[$bar][$pk]['param']["lvl23#45"] = 100;
            else $models[$bar][$pk]['param']["lvl23#45"] = round($level_dist_3x_t2 / $level_dist_t4_t5, 2); //cоотношение расстояния между уровнями т.3# и т.2(2') к расстоянию между уровнями т.4 и т.5'

            if (isset($pv['param']['auxP6'])) {
                $level_dist_t5_P6aux = abs(abs(low($pv['t5'], $v)) - abs($pv['param']['auxP6']));
                if ($level_dist_t5_P6aux * 100 < $level_dist_3x_t2) $models[$bar][$pk]['param']["ll2'3#56aux"] = 100;
                else $models[$bar][$pk]['param']["ll2'3#56aux"] = round($level_dist_3x_t2 / $level_dist_t5_P6aux, 2);
            }
        }
    }
    // 18. (п.24) Параметр, отражающий соотношение расстояний между уровнями т.3-т.5 к уровням т.5 - расчётная т.6.
    foreach ($models as $bar => $models_at_bar) foreach ($models_at_bar as $pk => $pv) {
        $v = $pv['v'];
        if ($pv['param']['G1'] == 'EAM') $field_name = 'calcP6';  //Если G1 не равно EAM рассчитывается с учетом auxP6, в другом случае для  calcP6
        else $field_name = 'auxP6';
        if (isset($pv['t5']) && isset($pv['param'][$field_name])) {
            $val1 = abs(low($pv['t3'], $v) - low($pv['t5'], $v));
            $val2 = abs(abs(low($pv['t5'], $v)) - abs($pv['param'][$field_name]));
            if ($val2 * 100 < $val1) $models[$bar][$pk]['param']["ParE"] = 100;
            else $models[$bar][$pk]['param']["ParE"] = round($val1 / $val2, 2); //cоотношение расстояния между уровнями т.3# и т.2(2') к расстоянию между уровнями т.4 и т.5'
        }
    }
    // 19. Дополнительно 20201103: Соотношение уровней т.2-т.3 и т.4-т.5
    foreach ($models as $bar => $models_at_bar) foreach ($models_at_bar as $pk => $pv) {
        $v = $pv['v'];
        if (!is_null($pv['t2'] ?? null) && !is_null($pv['t5'] ?? null)) {
            $val1 = abs(low($pv['t3'], $v) - high($pv['t2'], $v));
            $val2 = abs(high($pv['t4'], $v) - low($pv['t5'], $v));
            if ($val2 * 100 < $val1) $models[$bar][$pk]['param']["lvl23to45"] = "100";
            else $models[$bar][$pk]['param']["lvl23to45"] = round($val1 / $val2, 1);
        }
    }
    // 20. Дополнительно 20201103: Соотношение уровней т.4-т.5 и т.2-т.5
    foreach ($models as $bar => $models_at_bar) foreach ($models_at_bar as $pk => $pv) {
        $v = $pv['v'];
        if (!is_null($pv['t2'] ?? null) && !is_null($pv['t5'] ?? null)) {
            $val1 = abs(low($pv['t5'], $v) - high($pv['t4'], $v));
            $val2 = abs(high($pv['t2'], $v) - low($pv['t5'], $v));
            if ($val2 * 100 < $val1) $models[$bar][$pk]['param']["lvl45to25"] = "100";
            else $models[$bar][$pk]['param']["lvl45to25"] = round($val1 / $val2, 1);
        }
    }
    // 21. Дополнительно 20211214:  параметры TLexs13' , TLexs3'4 , ALexs12' , ALexs2'4 , auxTLexs , auxALexs
    $fnave = ['clear', 'cls', 'exs']; // наименование значений параметров
    foreach ($models as $bar => $models_at_bar) foreach ($models_at_bar as $pk => $pv) {
        // if ($pips != MIN_PIPS && $pips / 10 != MIN_PIPS && $pips / 100 != MIN_PIPS) $models[$bar][$pk]['param']['___pipSize'] = $pips; // просто для контроля
        //$models[$bar][$pk]['param']['___tmp_pipsCalcCnt']=$tmp_pipsCalcCnt;
        $v = $pv['v'];
        // TLexs13'
        if (isset($pv['t1']) && $pv['param']['G1'] !== 'EAM') {
            $t3name = isset($pv['t3\'']) ? 't3\'' : 't3';
            $LT = ['bar' => $pv['t1'], 'level' => low($pv['t1'], $v), 'angle' => (low($pv[$t3name], $v) - low($pv['t1'], $v)) / ($pv[$t3name] - $pv['t1'])];
            $flag = 0; // 0 - нет подхода, 1 - есть подход, 2 - есть касание или пробой
            for ($i = $pv['t1'] + 1; $i < $pv[$t3name]; $i++) {
                $BAL = $LT['level'] + $LT['angle'] * ($i - $pv['t1'] - 0.5); // bar appearance level - уровень появления бара - начало следующего после т1 бара на расстоянии 0.5 бара от точки А
                $low_ = low($i, $v);
                if ($low_ <= $BAL) {
                    $flag = 2;
                    break; // был пробой, дальше не смотрим
                }
                if ($low_ <= ($BAL + $pips * EXS_APPROACH_IN_PIPS)) $flag = 1; // зафиксирован подход, но не выходим и смотрим дальше (вдруг еще пробой будет)
            }
            $models[$bar][$pk]['param']['TLexs13\''] = $fnave[$flag];
        }

        // TLexs3'4
        if (isset($pv['t1']) || ($pv['param']['G1'] ?? "NA") == 'EAM') {
            $t3name = isset($pv['t3\'']) ? 't3\'' : 't3';
            if (($pv['param']['G1'] ?? "NA") == 'EAM') { // определяем точку А b B  для расчета линии тренда
                $point_A_of_LT = $t3name;
                $point_B_of_LT = 't5';
            } else { // если не EAM
                $point_A_of_LT = 't1';
                $point_B_of_LT = 't3';
            }
            $LT = ['bar' => $pv[$point_A_of_LT], 'level' => low($pv[$point_A_of_LT], $v), 'angle' => (low($pv[$point_B_of_LT], $v) - low($pv[$point_A_of_LT], $v)) / ($pv[$point_B_of_LT] - $pv[$point_A_of_LT])];
            $flag = 0;
            for ($i = $pv[$t3name] + 1; $i < $pv['t4']; $i++) {  // не включая т4
                $BAL = $LT['level'] + $LT['angle'] * ($i - $pv[$point_A_of_LT] - 0.5);
                $low_ = low($i, $v);
                if ($low_ <= $BAL) {
                    $flag = 2;
                    break;
                }
                if ($low_ <= ($BAL + $pips * EXS_APPROACH_IN_PIPS)) $flag = 1;
            }
            $models[$bar][$pk]['param']['TLexs3\'4'] = $fnave[$flag];
        }

        // ALexs12'
        if (isset($pv['t1']) && $pv['param']['G1'] !== 'EAM') {
            $t2name = isset($pv['t2\'']) ? 't2\'' : 't2';
            $LC = ['bar' => $pv[$t2name], 'level' => high($pv[$t2name], $v), 'angle' => (high($pv['t4'], $v) - high($pv[$t2name], $v)) / ($pv['t4'] - $pv[$t2name])];
            $flag = 0;
            for ($i = $pv['t1'] + 1; $i < $pv[$t2name]; $i++) { // не включая т1
                $BAL = $LC['level'] + $LC['angle'] * ($i - $pv[$t2name] - 0.5);
                $high_ = high($i, $v);
                if ($high_ >= $BAL) {
                    $flag = 2;
                    break;
                }
                if ($high_ >= ($BAL - $pips * EXS_APPROACH_IN_PIPS)) $flag = 1;
            }
            $models[$bar][$pk]['param']['ALexs12\''] = $fnave[$flag];
        }

        // ALexs2'4
        $t2name = isset($pv['t2\'']) ? 't2\'' : 't2';
        $LC = ['bar' => $pv[$t2name], 'level' => high($pv[$t2name], $v), 'angle' => (high($pv['t4'], $v) - high($pv[$t2name], $v)) / ($pv['t4'] - $pv[$t2name])];
        $flag = 0;
        for ($i = $pv[$t2name] + 1; $i < $pv['t4']; $i++) {
            $BAL = $LC['level'] + $LC['angle'] * ($i - $pv[$t2name] - 0.5);
            $high_ = high($i, $v);
            if ($high_ >= $BAL) {
                $flag = 2;
                break;
            }
            if ($high_ >= ($BAL - $pips * EXS_APPROACH_IN_PIPS)) $flag = 1;
        }
        $models[$bar][$pk]['param']['ALexs2\'4'] = $fnave[$flag];


        if (
            isset($models[$bar][$pk]['param']['AUX']) &&
            $models[$bar][$pk]['param']['AUX'] == 'AM' &&
            (isset($pv['t3\'мп']) || isset($pv['t3\''])) && isset($pv['t1'])  && isset($pv['t5'])  && $pv['param']['G1'] !== 'EAM' && $pv['param']['G1'] !== 'WEDGE'
        ) {
            // auxTLexs
            $t3name = isset($pv['t3\'мп']) ? 't3\'мп' : 't3\'';
            $t2name = isset($pv['t2\'']) ? 't2\'' : 't2';
            $LT = ['bar' => $pv[$t3name], 'level' => low($pv[$t3name], $v), 'angle' => (low($pv['t5'], $v) - low($pv[$t3name], $v)) / ($pv['t5'] - $pv[$t3name])];
            $flag = 0;
            for ($i = $pv[$t3name] + 1; $i < $pv['t5']; $i++) {
                $BAL = $LT['level'] + $LT['angle'] * ($i - $pv[$t3name] - 0.5);
                $low_ = low($i, $v);
                if ($low_ <= $BAL) {
                    $flag = 2;
                    break; // был пробой, дальше не смотрим
                }
                if ($low_ <= ($BAL + $pips * EXS_APPROACH_IN_PIPS)) $flag = 1;
            }
            $models[$bar][$pk]['param']['auxTLexs'] = $fnave[$flag];

            // auxALexs
            //            $t2name = isset($pv['t2\'']) ? 't2\'' : 't2';
            //            $LC = ['bar' => $pv[$t2name], 'level' => high($pv[$t2name], $v), 'angle' => (high($pv['t4'], $v) - high($pv[$t2name], $v)) / ($pv['t4'] - $pv[$t2name])];
            //            $flag=0;
            //            for($i=$pv[$t2name]+1;$i<$pv['t4'];$i++){
            //                $BAL=$LC['level']+$LC['angle']*($i-$pv[$t2name]-0.5);
            //                $high_=high($i, $v);
            //                if($high_>=$BAL){
            //                    $flag=2;
            //                    break;
            //                }
            //                if($high_>=($BAL+$pips*EXS_APPROACH_IN_PIPS))$flag=1;
            //            }
            $models[$bar][$pk]['param']['auxALexs'] = $models[$bar][$pk]['param']['ALexs2\'4']; //$fnave[$flag];

        }
    }

    // 22. Дополнительно (добавлено 20220818): 40 новых параметров:
    //  параметры, характеризующие положение уровней модели относительно 6-ой в %-ах от размера моделей (параметры реперных точек)
    //- по горизонтали
    //- по вертикали
    //(округляем до 2 знаков после запятой)
    //
    //p5lvl6 = расстояние по уровням (P6-т.5)/(P6 - уровень опорной точки)
    //p4lvl6 = расстояние по уровням (P6-т.4)/(P6 - уровень опорной точки)
    //p3_lvl6 = расстояние по уровням (P6-т.3'/т.3)/(P6 - уровень опорной точки)
    //p2_lvl6 = расстояние по уровням (P6-т.2'/т.2)/(P6 - уровень опорной точки)
    //p1lvl6 = расстояние по уровням (P6-т.1)/(P6 - уровень опорной точки)
    //
    //p5ll6 = расстояние по времени (P6-т.5)/(P6 - время опорной точки)
    //p4ll6 = расстояние по времени (P6-т.4)/(P6 - время опорной точки)
    //p3_ll6 = расстояние по времени (P6-т.3'/т.3)/(P6 - время опорной точки)
    //p2_ll6 = расстояние по времени (P6-т.2'/т.2)/(P6 - время опорной точки)
    //p1ll6 = расстояние по времени (P6-т.1)/(P6 - время опорной точки)
    //
    //Параметры ниже рассчитываеются для всех моделей, содержащих AuxP6 (расчётная 6-ая вспомогательных МП)
    //p5lvlAuxP6 = расстояние по уровням (AuxP6-т.5)/(AuxP6 - уровень опорной точки)
    //p4lvlAuxP6 = расстояние по уровням (AuxP6-т.4)/(AuxP6 - уровень опорной точки)
    //p3_lvlAuxP6 = расстояние по уровням (AuxP6-т.3'/т.3)/(AuxP6 - уровень опорной точки)
    //p2_lvlAuxP6 = расстояние по уровням (AuxP6-т.2'/т.2)/(AuxP6 - уровень опорной точки)
    //p1lvlAuxP6 = расстояние по уровням (AuxP6-т.1)/(AuxP6 - уровень опорной точки)
    //
    //Параметры ниже рассчитываеются для всех моделей, содержащих AuxP6 (расчётная 6-ая вспомогательных МП)
    //p5llAuxP6 = расстояние по времени (AuxP6-т.5)/(AuxP6 - время опорной точки)
    //p4llAuxP6 = расстояние по времени (AuxP6-т.4)/(AuxP6 - время опорной точки)
    //p3_llAuxP6 = расстояние по времени (AuxP6-т.3'/т.3)/(AuxP6 - время опорной точки)
    //p2_llAuxP6 = расстояние по времени (AuxP6-т.2'/т.2)/(AuxP6 - время опорной точки)
    //p1llAuxP6 = расстояние по времени (AuxP6-т.1)/(AuxP6 - время опорной точки)
    //
    //Параметры ниже рассчитываеются для всех моделей, содержащих AuxP6' (расчётная 6-ая вспомогательных МП от т.5')
    //p5'lvlAuxP6' = расстояние по уровням (AuxP6'-т.5')/(AuxP6' - уровень опорной точки)
    //p4lvlAuxP6' = расстояние по уровням (AuxP6'-т.4)/(AuxP6' - уровень опорной точки)
    //p3_lvlAuxP6' = расстояние по уровням (AuxP6'-т.3'для5'/т3'/т.3)/(AuxP6' - уровень опорной точки)
    //p2_lvlAuxP6' = расстояние по уровням (AuxP6'-т.2'/т.2)/(AuxP6' - уровень опорной точки)
    //p1lvlAuxP6' = расстояние по уровням (AuxP6'-т.1)/(AuxP6' - уровень опорной точки)
    //
    //p5'llAuxP6' = расстояние по времени (AuxP6'-т.5')/(AuxP6' - время опорной точки)
    //p4llAuxP6' = расстояние по времени (AuxP6'-т.4)/(AuxP6' - время опорной точки)
    //p3_llAuxP6' = расстояние по времени (AuxP6'-т.3'для5'/т3'/т.3)/(AuxP6' - время опорной точки)
    //p2_llAuxP6' = расстояние по времени (AuxP6'-т.2'/т.2)/(AuxP6' - время опорной точки)
    //p1llAuxP6' = расстояние по времени (AuxP6'-т.1)/(AuxP6' - время опорной точки)
    //
    //Параметры ниже рассчитываеются для всех моделей, содержащих P6" (расчётная 6-ая от т.5")
    //p5"lvlP6" = расстояние по уровням (P6"-т.5")/(P6" - уровень опорной точки)
    //p4lvlP6" = расстояние по уровням (P6"-т.4)/(P6" - уровень опорной точки)
    //p3_lvlP6" = расстояние по уровням (P6"-т.3"/т.3'/т.3)/(P6" - уровень опорной точки)
    //p2_lvlP6" = расстояние по уровням (P6"-т.т.2'/т.2)/(P6" - уровень опорной точки)
    //p1lvlP6" = расстояние по уровням (P6"-т.1)/(P6" - уровень опорной точки)
    //
    //Параметры ниже рассчитываеются для всех моделей, содержащих P6" (расчётная 6-ая от т.5")
    //p5"llP6" = расстояние по  времени (P6"-т.5")/(P6" - время опорной точки)
    //p4llP6" = расстояние по  времени (P6"-т.4)/(P6" - время опорной точки)
    //p3_llP6" = расстояние по  времени (P6"-т.3"/т.3'/т.3)/(P6" - время опорной точки)
    //p2_llP6" = расстояние по  времени (P6"-т.т.2'/т.2)/(P6" - время опорной точки)
    //p1llP6" = расстояние по  времени (P6"-т.1)/(P6" - время опорной точки)
    foreach ($models as $bar => $models_at_bar) foreach ($models_at_bar as $pk => $model) {
        $aimParamNames = ["P6" => "calcP6", "AuxP6" => "auxP6", "AuxP6'" => "auxP6'", 'P6"' => 'calcP6"']; // фрагмент имени параметра -> соответсвующее название параметра модели (для уровня и бара при добавлнии 't' в конце)
        $v = $model['v'];
        $G1 = $model['param']['G1'];
        $K = ($v == 'low') ? 1 : -1;
        foreach ($aimParamNames as $aim => $paramName) { // перебор всех возможных целей - смотрим, есть ли она у модели и создаем соответствующие параметры
            if (is_null($model['param'][$paramName] ?? null)) continue; // ножного параметра P6 у модели нет -> переходим к следующему
            $CP_level = $model['param'][$paramName] * $K;
            $CP_bar = $model['param'][$paramName . "t"];
            // * Определяем размер модели по уровням и времени
            $t2_fieldName = (is_null($model['t2\''] ?? null)) ? 't2' : 't2\'';
            if ($G1 == 'AM/DBM' || $G1 == 'AM') {
                $sizeLevel = $CP_level - low($model['t1'], $v);
                $sizeTime = $CP_bar - $model['t1'];
                $bar_0 = $model['t1'];
                $lvl_0 = min(abs($CP_level), abs(low($model['t1'], $v)));
            } else {
                $sizeLevel = $CP_level - low($model['t3'], $v);
                $sizeTime = $CP_bar - $model[$t2_fieldName];
                $bar_0 = $model[$t2_fieldName];
                $lvl_0 = min(abs($CP_level), abs(low($model['t3'], $v)));
            }
            if ($sizeTime <= 0) continue; // кросс-поинт (P6) левее опорного бара -> выходим

            // в зависимости от вида цели,определяем переменные для вычисления параметров

            $t2_bar = $model[$t2_fieldName] ?? null;
            $t2_level = is_null($t2_bar) ? null : high($t2_bar, $v);

            if ($aim == 'P6"') $t5_bar = $model['t5"'] ?? null;
            else if ($aim == "AuxP6'") $t5_bar = $model["t5'"] ?? null;
            else $t5_bar = $model["t5"] ?? null;
            $t5_level = is_null($t5_bar) ? null : low($t5_bar, $v);

            $t4_bar = $model['t4'];
            $t4_level = high($t4_bar, $v);

            if ($aim == 'P6"') $t3_bar = $model['t3"'] ?? ($model["t3'"] ?? ($model["t3"] ?? null));
            else if ($aim == "AuxP6'") $t3_bar = $model["t3'мп5'"] ?? ($model["t3'"] ?? ($model["t3"] ?? null));
            else $t3_bar = $model["t3"] ?? null;
            $t3_level = is_null($t3_bar) ? null : low($t3_bar, $v);

            $t1_bar = $model['t1'] ?? null;
            $t1_level = is_null($t1_bar) ? null : low($t1_bar, $v);

            // заносим в параметры 5+5 значение (но только для тех, где в формуле определены все переменные, иначе пропускаем)

            $p5_part = "p5";
            if ($aim == 'P6"') $p5_part = 'p5"';
            else if ($aim == "AuxP6'") $p5_part = "p5'";
            else $p5_part = "p5";

            // вначале 5 по времени:
            if ($t5_bar) $models[$bar][$pk]['param'][$p5_part . 'll' . $aim] = round(($CP_bar - $t5_bar) / $sizeTime, 2);
            if ($t4_bar) $models[$bar][$pk]['param']['p4ll' . $aim] = round(($CP_bar - $t4_bar) / $sizeTime, 2);
            if (isset($State['conf_t4']))$models[$bar][$pk]['param']['llconf_t4' . $aim] = round(($CP_bar - $State['conf_t4']) / $sizeTime, 2);
            if ($t3_bar) $models[$bar][$pk]['param']['p3_ll' . $aim] = round(($CP_bar - $t3_bar) / $sizeTime, 2);
            if ($t2_bar) $models[$bar][$pk]['param']['p2_ll' . $aim] = round(($CP_bar - $t2_bar) / $sizeTime, 2);
            if ($t1_bar) $models[$bar][$pk]['param']['p1ll' . $aim] = round(($CP_bar - $t1_bar) / $sizeTime, 2);

            // затем 5 по уровням:
            if ($t5_level) $models[$bar][$pk]['param'][$p5_part . 'lvl' . $aim] = round(($CP_level - $t5_level) / $sizeLevel, 2);
            if ($t4_level) $models[$bar][$pk]['param']['p4lvl' . $aim] = round(($CP_level - $t4_level) / $sizeLevel, 2);
            if ($t3_level) $models[$bar][$pk]['param']['p3_lvl' . $aim] = round(($CP_level - $t3_level) / $sizeLevel, 2);
            if ($t2_level) $models[$bar][$pk]['param']['p2_lvl' . $aim] = round(($CP_level - $t2_level) / $sizeLevel, 2);
            if ($t1_level) $models[$bar][$pk]['param']['p1lvl' . $aim] = round(($CP_level - $t1_level) / $sizeLevel, 2);

            //            foreach($models[$bar][$pk]['param'] as $k_=>$val_)if(substr($k_,0,1)=='p' && is_nan($val_)){
            //                file_put_contents($curDir."/tmp/TMP___json.json",json_encode($model));
            //                file_put_contents($curDir."/tmp/TMP___debug.txt","NaN: $k_ $sizeLevel $sizeTime $CP_level $CP_bar | $t1_level $t2_level $t3_level $t4_level $t5_level | $t1_bar $t2_bar $t3_bar $t4_bar $t5_bar |");
            //                die();
            //            }

        }
    }

    foreach ($models as $bar => $models_at_bar) foreach ($models_at_bar as $pk => $pv) ksort($models[$bar][$pk]['param']); // добавлена сортировка парамеров по именам по алфавиту
    return ($models);
}

//	* Definition of Sacral Point (SP) and model type.
//	* ОПРЕДЕЛНИЕ СТ И ТИПА МОДЕЛИ.
function defineG1($State)
{
    global $res;
    $v = $State['v'];
    $LT = LT($State);
    $LCs = LCs($State);
    $t6_ = linesIntersection($LT, $LCs);
    $State['param']['G1_sd'] = 1;
    if (isset($State['t2\''])) { // испр.алг - если нашли т2' //7.1.В случае если ЛЦ построена через т.2 (а не через т.2', т.е. линия от через точки т.2 и т.4) не имеет пересечения с ценой на участке т.1-т.2

        if ($t6_) { // есть пересечение справа
            if ($t6_['bar'] > $State['t4']) { // 7.1.1. Точка пересечения ЛТ и ЛЦ' лежит правее т.4. В данном случае точка пересечения линий является расчетной точкой 6 (далее - расчетная т.6).), то программа рассчитывает соотношение отрезков времени от т.1 до т.4 и от т.4 до расчетной т.6 для ЧМП.
                $State['param']['G1'] = "NA_7_1_1";

                $P6 = $State['param']['calcP6'] = round(abs($t6_['level']), 5);
                $State['param']['calcP6t'] = round($t6_['bar'], 3);
                $dist_1_4 = $State['t4'] - $State['t1'];
                $dist_4_6 = $t6_['bar'] - $State['t4'];

                if ($dist_1_4 * 3 > $dist_4_6) { //7.1.1.1.Если участок от т.1 до т.4, умноженный на 3 больше участка от т.4 до расчетной т.6 для ЧМП, данная модель является ЧМП.
                    $State['param']['G1'] = 'AM';
                } //$dist_1_4*3>$dist_4_6
                else if ($dist_1_4 * 12 > $dist_4_6) { //$dist_1_4*3<=$dist_4_6
                    // $State = myLog($State, "7.1.1.2. данная модель является ЧМП/МДР.");
                    $State['param']['G1'] = 'AM/DBM';
                }
                if ($dist_1_4 * 12 <= $dist_4_6) {
                    $State['param']['G1'] = 'DBM';
                }
            } //t6 правее т4
            else { //t6 левее т4
                // $ST_str = substr(" " . $t6_['bar'], 1, 7) . " (" . substr(" " . abs($t6_['level']), 1, 7) . ")";
                // $ST_str = round($t6_['bar'], 3) . " (" . round(abs($t6_['level']), 5) . ")";
                // $State['param']['_CT'] = $ST_str;

                // $State['param']['calcP6'] = substr(" " . abs($t6_['level']), 1, 7);
                // $State['param']['calcP6t'] = substr(" " . $t6_['bar'], 1, 7);

                // $State['param']['calcP6'] = round( abs($t6_['level']), 5);
                // $State['param']['calcP6t'] = round($t6_['bar'], 3);
                //$State = myLog($State, "Найдена Сакральная Точка СТ $ST_str");
                if (($State['t4'] - $State['t1']) * 3 <= ($State['t1'] - $t6_['bar'])
                    && ($State['t4'] - $State['t1']) * 12 > ($State['t1'] - $t6_['bar'])
                ) {
                    //$State = myLog($State, "7.1.1.2. данная модель является МДР/МР.");
                    $State['param']['G1'] = 'EM/DBM';
                    $ST_str = round($t6_['bar'], 3) . " (" . round(abs($t6_['level']), 5) . ")";
                    $State['param']['_CT'] = $ST_str;
                    $State['param']['calcP6'] = round(abs($t6_['level']), 5);
                    $State['param']['calcP6t'] = round($t6_['bar'], 3);
                } else if (($State['t4'] - $State['t1']) * 12 > ($State['t1'] - $t6_['bar'])) {
                    //$State = myLog($State, "7.1.1.2. данная модель является  это МР.");
                    $State['param']['G1'] = 'EM';
                    $ST_str = round($t6_['bar'], 3) . " (" . round(abs($t6_['level']), 5) . ")";
                    $State['param']['_CT'] = $ST_str;
                    $State['param']['calcP6'] = round(abs($t6_['level']), 5);
                    $State['param']['calcP6t'] = round($t6_['bar'], 3);
                } else {
                    //$State = myLog($State, "7.1.1.3 ЛТ и ЛЦ модели параллельны, данная модель является МДР.");
                    $State['param']['G1'] = 'DBM';
                }
            }
        } // t6 найдена - есть пересечение ЛТ и ЛЦ'
        else { //***Если ЛТ и ЛЦ модели параллельны, то данная модель является МДР.
            //            $State = myLog($State, "7.1.2 ЛТ и ЛЦ модели параллельны, данная модель является МДР.");
            $State['param']['G1'] = 'DBM';
            //$State=fix_model($State,"МДР");
        }
    } // 7.1.	В случае если ЛЦ построили через т2' (т2' была найдена  - цена не пересекает ЛЦ' на т1-т2
    else { //7.2 t2' найти не смогли - цена пересекает ЛЦ' на т1-т2

        if ($t6_) { // есть пересечение - t6 найдена
            $State['param']['G1'] = "NA_7_2";
            if ($t6_['bar'] > $State['t4']) { // 7.2.1 t6 справа от т2 и т4
                //                if (low($State['t4'], $v) <= lineLevel($LT, $State['t4']) || low($State['t4'] + 1, $v) <= lineLevel($LT, $State['t4'] + 1)) {
                //
                //                    //7.2.1.1. Если ЛТ ЧМП пробита баром т.4 или баром, следующим сразу за т.4, программа осуществляет поиск новой т.3, для чего обрабатывает бар, пробивший ЛТ ЧМП по п.2.
                //                    $State = myLog($State, "ЛТ ЧМП пробита баром т.4 или баром, следующим сразу за т.4 - ищем новую т3");
                //                    //                    $State=fix_model($State,"---");
                //                    if(($State['draw_flag'] ?? false))$State['next_step'] = 'stop';
                //                    else $State['next_step'] = 'step_2';
                //                    //$State['next_step'] = 'step_2';
                //                    if (low($State['t4'], $v) <= lineLevel($LT, $State['t4'])) $State['curBar'] = $State['t4'];
                //                    else                                                 $State['curBar'] = $State['t4'] + 1;
                //
                //                    $State = clearState($State, "2,3,t1,t2,t3-");
                //                    return ([$State]);
                //                } else { //7.2.1.2. Если ЛТ ЧМП не пробита баром т.4 или баром, следующим сразу за ним, то программа осуществляет поиск новой т.4, для чего обрабатывает бар, следующий за баром кандидата в т.4 по 3.4.
                //                    $State = myLog($State, "ЛТ ЧМП не пробита баром т.4 или баром, следующим сразу за т.4 - обрабатывает бар, следующий за баром кандидата в т.4 по 3.4.");
                //                    // $State = fix_model($State, "модель в п7.2.1.2.");
                //                    // $State['param']['alt'] = 1;
                //                    $State['curBar'] = $State['t4'] + 1;
                //                    $State = myLog($State, "ЧМП через т.2 не абсолют, ишемм следующую т4 начиная с бара " . $State['curBar']);
                //                    $State['next_step'] = 'step_3_4';
                //                    return ([$State]);
                //                }

                $P6 = round(" " . abs($t6_['level']), 5);
                // $State['param']['calcP6'] = $P6;
                // $State['param']['calcP6t'] = round(" " . $t6_['bar'], 3);
                $dist_1_4 = $State['t4'] - $State['t1'];
                $dist_4_6 = $t6_['bar'] - $State['t4'];
                if ($dist_1_4 * 3 > $dist_4_6) { //7.2.1.1.Если участок от т.1 до т.4, умноженный на 3 больше участка от т.4 до расчетной т.6, то данная модель является некорректной ЧМП
                    $State['param']['G1'] = "NA_7_2_1_1"; //  - некорректная ЧМП
                    $State['param']['calcP6'] = $P6;
                    $State['param']['calcP6t'] = round(" " . $t6_['bar'], 3);
                } else
                    if ($dist_1_4 * 12 > $dist_4_6) { //7.2.1.2.Если участок от т.1 до т.4, умноженный на 3 больше участка от т.4 до расчетной т.6, то данная модель является ЧМП/МДР
                        $State = myLog($State, "7.2.1.2. данная модель является ЧМП/МДР.");
                        $State['param']['G1'] = 'AM/DBM';
                        $State['param']['calcP6'] = $P6;
                        $State['param']['calcP6t'] = round(" " . $t6_['bar'], 3);
                    } else { // 7.2.1.3. участок от т.1 до т.4 умноженный на 12 меньше или равен участку от СТ до т.1 то данная модель является МДР.

                        $State = myLog($State, "7.2.1.3. данная модель является МДР.");
                        $State['param']['G1'] = 'DBM';
                    }
            } // 7.2.1 t6 справа от т2 и т4
            else { // 7.2.2 t6 слева от т2 и т4
                $t3_ = (isset($State['t3\''])) ? $State['t3\''] : $State['t3'];
                $State['param']['G1'] = 'NA_7_2_2';
                if ($t6_['bar'] > $State['t1'] && $t6_['bar'] < $t3_) {  // псевдоСТ (t6) между т1 и т3/т3'
                    $State = myLog($State, "7.2.2.1. Если псевдо СТ лежит правее (т.е. позже) т.1 до т.3 -> сильная по СТО МР");
                    $State['param']['G1'] = 'EM';
                    $State['param']['SP'] = 'strongpseudoSP';
                } else { // псевдоСТ (t6) слева от т1
                    if (($t3_ - $State['t1']) > ($State['t1'] - $t6_['bar'])) {
                        $State = myLog($State, "7.2.2.2.1 -> МР, сила модели не определена");
                        $State['param']['G1'] = 'EM';
                        $State['param']['SP'] = 'undef';
                    }
                    if (($t3_ - $State['t1']) <= ($State['t1'] - $t6_['bar']) && ($t3_ - $State['t1']) * 3 >= ($State['t1'] - $t6_['bar'])) {
                        $State = myLog($State, "7.2.2.2.2. -> МР, модель слабая по СТО");
                        $State['param']['G1'] = 'EM';
                        $State['param']['SP'] = 'weakpseudoST';
                    }
                    if (($t3_ - $State['t1']) * 3 >= ($State['t1'] - $t6_['bar'])) {
                        $State = myLog($State, "7.2.2.2.3. -> тип модели МР/МДР");
                        $State['param']['G1'] = 'EM/DBM';
                    }
                } // псевдо СТ (t6) слева от т1
            } // 7.2.2 t6 слева от т2 и т4

        } else { // ЛЦ' и ЛТ параллельны, t6 не найдена
            //  $State = myLog($State, "аналог 7.1.2 ЛТ и ЛЦ модели параллельны, данная модель является МДР.");
            $State['param']['G1'] = 'DBM';
        }
    }

    //$State['next_step'] = 'step_8';
    //if($State['draw_flag']??false)$State['next_step'] = 'stop';
    if (!isset($State['param']['G1'])) $State['param']['G1'] = "NA_empty";
    return ($State);
}
//function LCs($State)
//{ // определение ЛЦ'
//    $v = $State['v'];
//    $t2_ = (isset($State['t2\''])) ? 't2\'' : 't2';
//    return (['bar' => $State[$t2_], 'level' => high($State[$t2_], $v), 'angle' => (high($State['t4'], $v) - high($State[$t2_], $v)) / ($State['t4'] - $State[$t2_])]);
//}
//
//function LT($State)
//{ // определение линии тренда
//    $v = $State['v'];
//    $t3_ = (isset($State['t3\''])) ? 't3\'' : 't3';
//    return (['bar' => $State['t1'], 'level' => low($State['t1'], $v), 'angle' => (low($State[$t3_], $v) - low($State['t1'], $v)) / ($State[$t3_] - $State['t1'])]);
//}
function is_ajax()
{ // проверка был ли вызов по ajax или вручную (см. в интернете)
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        return (true);
    } else return (false);
}
$chUnique = [];
function checkUnique($State, $keyList)
{
    global $res, $chUnique;
    $keys = explode(",", $keyList);
    $ind = $State['next_step'] . ':';
    foreach ($keys as $pk => $key) $ind .= ' ' . $key . '=' . ($State[$key] ?? 'null');
    if (!isset($res['info']['checkUnique'][$ind])) {
        $res['info']['checkUnique'][$ind] = 1;
        $chUnique[$ind] = []; //22222222222222222
        $chUnique[$ind][] = $State; //2222222222222
        return (false);
    } else {
        $res['info']['checkUnique'][$ind]++;
        $chUnique[$ind][] = $State; //2222222222222
        return ("Ошибка уникальности: " . $ind . " split= " . $State['split'] . " пришли из " . $res['info']['last_function_ok']);
    }
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
function JSON_log($arrOut, $filename = "JSON_log.json")
{
    if (strtoupper(substr($filename, -5)) !== ".JSON") $filename .= '.json';
    file_put_contents($filename, json_encode($arrOut, true));
}
function whereFrom($isFuncNameNeeded)
{ // служебная функция - сообщает краткую инфу (для анализа статистика по логу - откуда сколько раз был вызов (для оптимизации лога - чтобы убрать частые сообщания)
    // выход "Имя фала"_"номер строки" - откуда была вызвана функция (например, myLog)
    $e = new Exception();
    $arr_e = $e->getTrace();
    $file = $arr_e[1]['file'];
    //    echo "file = $file<br>";
    $pos = strrpos($file, "\\");
    //    echo "pos=$pos <br>";
    if ($pos === false) $pos = strrpos($file, "/");
    if ($pos === false) $file = "";
    else $file = substr($file, $pos + 1);
    if ($isFuncNameNeeded) {
        $function = (isset($arr_e[2]['function'])) ? $arr_e[2]['function'] : "main";
        return ("$file (" . $arr_e[1]['line'] . ") " . $function);
    } else
        return ("$file (" . $arr_e[1]['line'] . ")");
}
function calcPips($Chart)
{
    $pips = 11111111111111; // определяем размер пипса для текущего инструмента тупо анализируя наш чарт
    $tmp_pipsCalcCnt = 0;
    for ($i = 0; $i < count($Chart) - 1; $i++) {
        $tmp_pipsCalcCnt++;
        $diff = ($Chart[$i]['high'] - $Chart[$i]['open']);
        if ($diff >= MIN_PIPS && $diff < $pips) $pips = $diff;
        $diff = ($Chart[$i]['high'] - $Chart[$i]['close']);
        if ($diff >= MIN_PIPS && $diff < $pips) $pips = $diff;
        $diff = ($Chart[$i]['open'] - $Chart[$i]['low']);
        if ($diff >= MIN_PIPS && $diff < $pips) $pips = $diff;
        $diff = ($Chart[$i]['close'] - $Chart[$i]['low']);
        if ($diff >= MIN_PIPS && $diff < $pips) $pips = $diff;
        if ($pips <= (MIN_PIPS + 0.0000001)) break; // достигли минимально возможного (на большинстве инструментов = 0.00001)
    }
    $pips = round($pips, 6);
    $tmp = str_replace("0", "", "" . ($pips * 1000000000)); // получаем последнюю цифру/цифры
    return round($pips / $tmp, 6);
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
