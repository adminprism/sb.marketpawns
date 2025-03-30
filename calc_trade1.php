<?php
// эмуляция торгов (вариант #1 - пипсы)
define("NUM", 1); // версия алгоритма эмуляции
$where = " where (not isnull(`P6aims`) or not isnull(`P6aims\"`) or not isnull(`auxP6aims`) or not isnull(`auxP6aims'`)) ";
ini_set('date.timezone', 'Europe/Moscow');
set_time_limit(0);
define("READ_MODEL_NUM", 3000); // по сколько моделей читаем из БД за раз
define("READ_BAR_BEFORE", 3300); // по сколько уходим влево при чтении очередной порции баров (зависит по скольку читали при загрузке базы)
define("READ_BAR_NUM", 15000); // по сколько баров читаем из БД за раз
define("WRITE_LOG", 9); // уровень логирования 0-нет 9 - максимальный
define("LOG_FILE", "calc_trade1.log"); //

// определение настроек для уровней - в % от рамера модели
define("LVL_BREAKDOWN", -6); // пробой уровня МП
define("LVL_APPROACH", 6); // подход к уровню МП
define("LVL_BUMPER1", 25); // бамперный уровень для МП (начальный)
define("LVL_BUMPER2", 15); // бамперный уровень для МП (при достижении уровня МП)
define("LVL_LOST", 70); // потеря уровня МП // ??????????????
define("LVL_AIM_1", 30); // первая цель
define("LVL_AIM_2", 50); // вторая цель
define("LVL_AIM_3", 80); // треья цель
define("LVL_AIM_4", 120); // четвертая цель
define("TIME_DEPTH", 100); // один размер по времени, отложенный от времени расчётной т.6
define("TIME_DEPTH_AIMS", 1000); // Глубина отслеживания целей МП (2 размера МП, отложенные от расчетной Т6
$aimLevels = [0, LVL_AIM_1, LVL_AIM_2, LVL_AIM_3, LVL_AIM_4];
$fixProfitParts = [0, 30, 20, 20, 30]; // какими частями в % фиксируем прибыль при достижении целей 1,2,3,4 - первый элемент массива =0, просто для красоты :)

$debug_limit = 10000000; // !!!!!!!!!!!!!!!! для отладки - максимальный id

require_once 'login_log.php';
ob_start();

$err_cnt = 0;
$ems = [];
$res = []; // возворащаемый результат json
$res['Error'] = 'Error_01';
$res['Errors'] = [];
$Chart = []; // кусок графика (подкачивается по мере надобности)
$baseBarNum = 0; // номер опорного бара для текущей модели
$chartNumById = []; // ассоциативный массив, возвращает номер (индекс) в массиве chart по bar_id
$lastBarPortion = 0; // сколько баров загрузили в крайний раз (типа, дошли до конца или что-то осталось еще)
$res['info']['type'] = '_GET';
$PARAM = $_GET;
if (WRITE_LOG > 0) $f_header = fopen(LOG_FILE, 'w');
if (isset($_POST['nameId']) || isset($_POST['modelId'])) {
    $res['info']['type'] = '_POST';
    $PARAM = $_POST;
}

$add_where = "";
$cur_id = 0;
$isALL = false;
if (isset($PARAM['nameId'])) {
    $nameId = SanitizeString($PARAM['nameId']);
    $add_where = " and m.name_id=$nameId ";
    if (strtoupper($nameId) == "ALL") {
        $add_where = " ";
        $isALL = true;
    }
}
if (isset($PARAM['modelId'])) {
    $modelId = SanitizeString($PARAM['modelId']);
    $add_where = " and m.id=$modelId ";
    $isALL = false;
}
if ($add_where == "") {
    $res['Errors'][] = "Не заданы параметры modelId или nameID";
    die();
}
write_log(date("Y-m-d H:i:s") . " start" . PHP_EOL, 1);

$spreads = []; // таблица с константами (размер шага и спред из таблицы Альпари)
$result = queryMysql("select * from spreads;", false, MYSQLI_USE_RESULT);
while ($rec = $result->fetch_assoc()) {
    foreach ($rec as $pk => $pv) {
        switch ($pk) {
            case "id":
                $rec[$pk] = intval($pv);
                break;
            case "tool_name":
            case "full_name":
                break;
            default:
                $rec[$pk] = floatval($pv);
        }
        $spreads[$rec['tool_name']] = $rec;
    }
}
$res['spreads'] = $spreads;

if ($isALL) {
    $result = queryMysql("delete from pnl where num=" . NUM . ";");
}
$result = queryMysql("select n.name,n.tool,m.*,c.* from models m left join controls c on m.id=c.model_id  left join chart_names n on m.name_id=n.id $where " . $add_where . " and m.id>$cur_id and m.id<$debug_limit order by m.id limit " . READ_MODEL_NUM . ";");
$n_rec = $result->num_rows;
$res['reading_models'][] = "id=$cur_id ; n_rec=$n_rec";
$result->data_seek(0);
$i = 0;
$barById = []; // номер бара в массиве по его ID

while ($n_rec > 0) {
    while ($cur_model = $result->fetch_assoc()) {
        $i++;
        $cur_id = $cur_model['id'];
        tradeModel($cur_model);
        //$res['out'][]="$i) $cur_id";
    }
    if ($n_rec == READ_MODEL_NUM) {
        $result = queryMysql("select n.name,n.tool,m.*,c.* from models m left join controls c on m.id=c.model_id  left join chart_names n on m.name_id=n.id $where " . $add_where . " and m.id>$cur_id and m.id<$debug_limit order by m.id limit " . READ_MODEL_NUM . ";");
        $n_rec = $result->num_rows;
        $result->data_seek(0);
        $res['reading_models'][] = "id=$cur_id ; n_rec=$n_rec";
    } else $n_rec = 0;
}
$res['info']['CNT'] = $i;
write_log(PHP_EOL . date("Y-m-d H:i:s") . " end" . PHP_EOL, 1);
unset($res['Error']);
die();

function tradeModel($model)
{ // расчет контрольных параметров для заданной модели
    global $res, $baseBarNum, $isALL;
    static $cnt = 0;
    $cnt++;
    setNewModel($model);
    $G1 = $model['G1'];
    $result1 = $result2 = $result3 = $result4 = 0;
    if (!is_null($model["P6aims"])) {
        $result1 = tradeAim($model, "P6aims", $model["_cross_point"]);
    }

    if (!is_null($model["P6aims\""])) {
        $result2 = tradeAim($model, "P6aims\"", $model["calcP6\"t"] . " (" . $model['calcP6"'] . ")");
    }

    if (!is_null($model["auxP6aims"])) {
        $result3 = tradeAim($model, "auxP6aims", $model["auxP6t"] . " (" . $model['auxP6'] . ")");
    }

    if (!is_null($model["auxP6aims'"])) {
        $result4 = tradeAim($model, "auxP6aims'", $model["auxP6't"] . " (" . $model["auxP6'"] . ")");
    }

    if (!$isALL) $result_tmp = queryMysql("delete from pnl where model_id=" . $model['id'] . " and num=" . NUM . ";", true);
    $fields_str = "model_id";
    $values_str = $model['id'];
    $cnt_fld = 0;
    if (($result1 + $result2 + $result3 + $result4) !== 0)
        $result_tmp = queryMysql("insert into pnl (model_id,num,`pnl_P6aims`,`pnl_P6aims\"`,`pnl_auxP6aims`,`pnl_auxP6aims'`) VALUES (" . $model['id'] . "," . NUM . ",$result1,$result2,$result3,$result4);");
}
function tradeAim($model, $title, $CP)
{ // функция для расчета контрольных параметров по одному набору точек (одна P6 - передается в параметре
    global $baseBarNum, $res, $Chart, $aimLevels, $spreads, $fixProfitParts;
    $G1 = $model['G1'];
    $Alg = $model['Alg'];
    $v = $model['v'];
    $K = ($v == 'low') ? 1 : -1; // множитель для зеркального переворачивания моделей high - НАДО НЕ ЗАБЫВАТЬ ЕГО ИСПОЛЬЗОВАТЬ ПРИ ИСПОЛЬЗОВАНИИ ЛЮБЫХ УРОВНЕЙ
    $spreadSize = $spreads[$model['tool']]['spread'];
    $stepSize = $spreads[$model['tool']]['step'];
    $procRest = 0;  // сколько процентов позиции еще не закрыли (при частичном закрытии при достижении целей, данный переменная уменьшается - устанавливется =100 при открытии позиции
    $pnlPips = 0; // накапливается прибыль/убыток в пипсах
    $aimsReached = [false, false, false, false, false]; // флажки достижения целей 0=бампреный уровень - false либо номер бара, на котором достигнута цель;

    write_log("--- tradeAim($title): G1='$G1' Alg=$Alg  v=$v CP='$CP'" . PHP_EOL, 7);

    $pos = strpos($CP, "(");
    $CP_bar = floatval(substr($CP, 0, $pos - 1));
    $CP_level = round(floatval(substr($CP, $pos + 1)) * $K, 5);
    $controlParams = [];
    $sizeLevel = 0;

    write_log("CP: '$CP' - bar=$CP_bar level=$CP_level" . PHP_EOL, 1);
    if (!(strpos($title, 'auxP6') === false) && $model['G1'] !== 'AM') {
        $extraCheckLT = true; // требуется доп проверка на пересечения ценой ЛТ пока 'unrchd';
        write_log("т.к. определяем параметры для aux, то определяем ЛТ для доп.проверки ее касания ценой" . PHP_EOL, 8);
        $LT = LT($model); // линия тренда
    } else $extraCheckLT = false; // доп проверка на достижение ценой ЛТ не требуется

    // определяем размер модели по уровням и времени
    if ($G1 == 'AM/DBM' || $G1 == 'AM') {
        $sizeLevel = $CP_level - low($model['t1'] + $baseBarNum, $v);
        $sizeTime = $CP_bar - $model['t1']; //t1==0 всегда, написал  просто для порядка
        write_log("G1=$G1 => размер модели по уровню = t6-t1 =" . $CP_level . " - " . low($model['t1'] + $baseBarNum, $v) . " =" . round($sizeLevel, 6) . " по времени t6-t1 =" . $CP_bar . " - " . $model['t1'] . " = " . round($sizeTime, 6) . PHP_EOL, 7);
        $first_bar_num = $model["t4"] + 1;
        write_log("Начинаем перебор с бара t4+1=$first_bar_num до CP+sizeTime*TIME_DEPTH% =" . $CP_bar . "+" . $sizeTime . "*" . TIME_DEPTH . "%=" . ($CP_bar + $sizeTime * TIME_DEPTH / 100) . PHP_EOL, 8);
        $controlParams['bar_0'] = $model['t1'];
        $controlParams['lvl_0'] = min(abs($CP_level), abs(low($model['t1'] + $baseBarNum, $v)));
    } else {
        $sizeLevel = $CP_level - low($model['t3'] + $baseBarNum, $v);
        $t2_fieldName = is_null($model['t2\'']) ? 't2' : 't2\'';
        $sizeTime = $CP_bar - $model[$t2_fieldName];
        write_log("G1=$G1 => размер модели по уровню = t6-t3 =" . $CP_level . " - " . low($model['t3'] + $baseBarNum, $v) . " =" . round($sizeLevel, 6) . " по времени t6-$t2_fieldName =" . $CP_bar . " - " . $model[$t2_fieldName] . " = " . round($sizeTime, 6) . PHP_EOL, 7);
        $first_bar_num = $model["t5"];
        write_log("Начинаем перебор с бара t5=$first_bar_num до CP+sizeTime*TIME_DEPTH% =" . $CP_bar . "+" . $sizeTime . "*" . TIME_DEPTH . "%=" . ($CP_bar + $sizeTime * TIME_DEPTH / 100) . PHP_EOL, 8);
        $controlParams['bar_0'] = $model[$t2_fieldName];
        $controlParams['lvl_0'] = min(abs($CP_level), abs(low($model['t3'] + $baseBarNum, $v)));
    }
    $controlParams['size_time'] = $sizeTime;
    $controlParams['size_level'] = round($sizeLevel, 5);


    $last_bar_num = $CP_bar + $sizeTime * TIME_DEPTH / 100;
    if ($last_bar_num > ($first_bar_num + 500)) {
        write_log("Слишком большая модель, выходим.", 3);
        return (0);
    }
    // перебор баров начиная с $first_bar_num
    $State = 0; //  уникальный идентификатор текущего момента в алгоритме, однозначно описывающий текущую ситуацию поведения модели после фиксации
    // 0: начало алгоритма, находимся на первом баре после фиксации (t4+1 либо t5) либо далее, если пока не случилось событий (трриггеров)
    // 1:  достигли уровня подхода к уровню МП и отслеживаем достижение уроня МП либо бамперного уровня
    // 2: достигли уровня МП -> ищем реальную Т6, отслеживаем пробой или поход на бамперный уровень
    // 3: определили реальную Т6 и дошли до бамрперного уровня -> отслеживаем достижение реальной Т6 или целей 1 2 3 4

    $curBar = $first_bar_num; // текущий анализируемый бар, далее идем вправо пока не сформируются все нужные контрольные параметры
    $CNT = count($Chart);
    $realP6level = -999999999999;
    $calcP6reached = false;
    $aimLevel = $CP_level;
    $approachLevelBar = 0; // номер бара, на котором достигли уровня подхода к уровню МП
    $controlParams['P6dev'] = 'unrchd'; // устанавливаем в самом начале 'unrchd', потом, возможно переопределим в зависимости от успехов
    $first_state3 = true; // триггер, показывающий, что state=3 у нас первый раз - что-бы однократно вывести в лог информацию об уровнях целей при попадании на state=3
    // вычисляем и вывподим в лог уровни
    $level_lost = round($CP_level - $sizeLevel * LVL_LOST / 100, 5); // уровень потери МП
    $level_approach = round($CP_level - $sizeLevel * LVL_APPROACH / 100, 5); // уровень подхода к уровню МП
    if ($level_approach < high($model['t4'] + $baseBarNum, $v)) {
        $level_approach = round(high($model['t4'] + $baseBarNum, $v), 5) + 0.00001;
        write_log("ВНИМАНИЕ! Передвигаем уровень подхода к уровню МП на уровень t4 + 1 пункт ($level_approach)" . PHP_EOL, 3);
    }
    $level_bumper = round($CP_level - $sizeLevel * LVL_BUMPER1 / 100, 5); // бамперный уровень
    $level_bumper_proc = LVL_BUMPER1;
    $level_breakdown = round($CP_level - $sizeLevel * LVL_BREAKDOWN / 100, 5); // уровень пробоя МП
    $level_stopLoss = $CP_level;
    $noBumperCheck = 0; // доп.флажек, который ставится в номер бара, на котором не нужно проверять пробой бамперного уровня в п.2
    while ($State !== 99) {
        $wasEvents = false; // флаг, показывающий, что на данной итерации ($curBar) быди какие-то собыдтия (триггеры) (если не было, то двигаемся дальше)
        if (($curBar + $baseBarNum) == $CNT) {
            write_log("Достиглм правой границы графика=$CNT, выход", 8);
            $State = 99;
            break;
        }
        $open = open($curBar + $baseBarNum, $v);
        $close = close($curBar + $baseBarNum, $v);
        $low = low($curBar + $baseBarNum, $v);
        $high = high($curBar + $baseBarNum, $v);
        $bar_direct = ($open < $close && $v == 'low' || $open > $close && $v == 'high') ? "ВОСХОДЯЩИЙ" : "НИСХОДЯЩИЙ";
        switch ($State) {

                // если события (дост. подхода к МП + Ур.потери цели/пробой лин.тренда) наложидись, то
                // если свеча вверх, то сначала пробит уровень потери
            case 0: // первый шаг алгоритма, зафиксировали модель и движемся до первого "события-триггера"
                if ($curBar > ($CP_bar + $sizeTime * TIME_DEPTH / 100)) {
                    write_log("Достигли глубины ожидания подхода к уровню МП на баре  $curBar -> выход" . PHP_EOL, 8);
                    $State = 99;
                    break;
                }
                $flag_breakLost = ($low < $level_lost) ? true : false;
                $flag_breakLT = ($extraCheckLT && lineLevel($LT, $curBar + $baseBarNum) >= $low) ? true : false;
                $flag_approach = ($high > $level_approach) ? true : false;

                if ($flag_breakLost && $flag_approach) { //на текущем баре пробили уровень подхода к уровню МП и уровень потери МП
                    if ($open < $close) { // вначале пробили уровень потери МП
                        write_log(" ОДНОВРЕМЕННО пробили уровень потери МП ($level_lost) и уровень подхода к уровню МП ($level_approach) на баре $curBar - бар $bar_direct -> выход" . PHP_EOL, 3);
                        $State = 99;
                        break;
                    } else { // вначале достигли уровень подхода к уровню МП
                        write_log(" ОДНОВРЕМЕННО пробили уровень потери МП ($level_lost) и уровень подхода к уровню МП ($level_approach) на баре $curBar - бар $bar_direct -> ОТКРЫВАЕМ ПОЗИЦИЮ + отслеживаем достижение уровня МП либо бамперного уровня" . PHP_EOL, 3);
                        $wasEvents = true;
                        $approachLevelBar = $curBar;
                        $procRest = 100;
                        $level_open = $level_approach;
                        $State = 1;
                        break;
                    }
                }
                if ($flag_breakLT && $flag_approach) { //на текущем баре пробили уровень подхода к уровню МП и достигли уровня ЛТ
                    if ($open < $close) { // вначале пересекли ЛТ
                        write_log(" ОДНОВРЕМЕННО достигли уровень подхода у уровню МП ($level_lost) и достигли линию ЛТ на баре $curBar - бар $bar_direct -> выход" . PHP_EOL, 3);
                        $State = 99;
                        break;
                    } else { // вначале достигли уровень подхода у уровню МП
                        write_log(" ОДНОВРЕМЕННО достигли уровень подхода у уровню МП ($level_lost) и достигли линию ЛТ на баре $curBar - бар $bar_direct -> ОТКРЫВАЕМ ПОЗИЦИЮ отслеживаем достижение уровня МП либо бамперного уровня" . PHP_EOL, 3);
                        $wasEvents = true;
                        $approachLevelBar = $curBar;
                        $procRest = 100;
                        $level_open = $level_approach;
                        $State = 1;
                        break;
                    }
                }

                if ($flag_breakLost) {
                    write_log("Пробили уровень потери МР ($level_lost) на баре $curBar -> выход" . PHP_EOL, 5);
                    $State = 99;
                    break;
                }
                if ($flag_breakLT) { // доп.проверка на касание ценой линии тренда - цена достигла ЛТ
                    write_log("доп.проверка(1) : цена достигла ЛТ на баре $curBar" . PHP_EOL, 6);
                    $State = 99;
                    break;
                }
                if ($high > $level_approach) {
                    write_log("Пробили уровень подхода к уровню МП ($level_approach) на баре $curBar - ОТКРЫВАЕМ ПОЗИЦИЮ " . PHP_EOL, 5);
                    $wasEvents = true;
                    $approachLevelBar = $curBar;
                    $procRest = 100;
                    $level_open = $level_approach;
                    $State = 1;
                    break;
                }
                break;
            case 1: // достигли уровня подхода к уровню МП, ОТКРЫЛИ ПОЗИЦИЮ и отслеживаем достижение уровня РЕАЛЬНОЙ P6 либо бамперного уровня (начального)
                // если события (дост.  МП + Бамперный уровень/пробой лин.тренда) наложидись, то
                // если свеча вверх, то сначала пробит бамперный уровень/LT
                if ($realP6level < $high && $high <= $CP_level) {
                    $realP6level = $high;
                    $realP6bar = $curBar;
                    write_log("Переставили уровень реальной P6 в state1 на ($realP6level) на баре $curBar" . PHP_EOL, 9);
                }
                $flag_breakBump = ($low < $level_bumper && $approachLevelBar !== $curBar) ? true : false;  //добавил (21.10.2020): на баре. который достиг ур.подхода к МП, пробой бамперного уровня не проверять !!!!
                //                    $flag_breakLT=($extraCheckLT&&$controlParams['P6dev']=='unrchd'&&lineLevel($LT,$curBar+$baseBarNum)>=$low)?true:false;
                $flag_AM = ($high > $CP_level) ? true : false;

                //                    if($flag_AM&&$flag_breakLT){ //на текущем баре достигли уровень МП и пробили линюю ЛТ
                //                        if($open<$close){ // вначале пробили ЛТ
                //                            write_log(" ОДНОВРЕМЕННО достили уровень МП ($CP_level) и линию ЛТ на баре $curBar - бар $bar_direct -> выход".PHP_EOL,3);
                //                            $State=99;
                //                            break;
                //                        }
                //                        else{ // вначале достигли уровня МП
                //                            write_log(" ОДНОВРЕМЕННО пробили уровень МП ($CP_level) и линию ЛТ на баре $curBar - бар $bar_direct -> достигли МП и отслеживаем доcтижение РЕАЛЬНОЙ Т6 или целей 1 2 3 4".PHP_EOL,3);
                //                            $controlParams['P6dev']='rchd';
                //                            $calcP6reached=$curBar;
                //                            $wasEvents=true;
                //                            $State=2; // достигли уровня МП
                //                            break;                   }
                //                    }

                if ($flag_AM && $flag_breakBump) { //на текущем баре достигли уровень МП и пробили бамперный уровень
                    if ($open < $close) { // вначале достигли бамперного уровня
                        //                            $controlParams['P6dev']=round(($CP_level-$realP6level)/$sizeLevel*100+0.499999,0); // округляем в большую сторону, так как не дошли, то число положительное
                        //                            if($controlParams['P6dev']=="-0")$controlParams['P6dev']="0";
                        //                            $controlParams['P6aims']=$level_bumper_proc;
                        write_log("ОДНОВРЕМЕННО прошли бамперный уровень ($level_bumper) и достигли уровня МП ($CP_level) на баре $curBar - бар $bar_direct -> начинаем отслеживать цели и уровень РЕАЛЬНОЙ Т6 =StopLoss ($realP6level) " . PHP_EOL, 7);
                        $wasEvents = true;
                        $aimsReached[0] = $curBar;
                        $level_stopLoss = $realP6level;
                        $State = 3;
                        break;
                    } else { // вначале достигли уровня МП
                        write_log("ОДНОВРЕМЕННО прошли бамперный уровень ($level_bumper) и достигли уровня МП ($CP_level) на баре $curBar - бар $bar_direct -> ищем реальную Т6, отслеживаем пробой или поход на бамперный уровень" . PHP_EOL, 3);
                        //                            $controlParams['P6dev']='rchd';
                        $calcP6reached = $curBar;
                        $wasEvents = true;
                        $State = 2; // достигли уровня МП
                        $level_bumper = round($CP_level - $sizeLevel * LVL_BUMPER2 / 100, 5); // бамперный уровень
                        $level_bumper_proc = LVL_BUMPER2;
                        //                            $controlParams['lvl_bump']=abs($level_bumper);
                        write_log("Передвинули бамперный уровень на " . LVL_BUMPER2 . "% ($level_bumper)", 3);
                        break;
                    }
                }
                //                     if($flag_breakLT) { // доп.проверка на касание ценой линии тренда, если пока "unrchd"
                //                            write_log("доп.проверка(2) : цена достигла ЛТ на баре $curBar" . PHP_EOL, 6);
                //                            $State = 99;
                //                            break;
                //                    }
                if ($flag_AM) { // достигли уровня МП
                    //                        $controlParams['P6dev']='rchd';
                    $calcP6reached = $curBar;
                    $wasEvents = true;
                    write_log("Достигли уровня МП ($CP_level) на баре $curBar -> ищем реальную Т6, отслеживаем пробой или поход на бамперный уровень" . PHP_EOL, 7);
                    $level_bumper = round($CP_level - $sizeLevel * LVL_BUMPER2 / 100, 5); // бамперный уровень
                    //                        $controlParams['lvl_bump']=abs($level_bumper);
                    $level_bumper_proc = LVL_BUMPER2;
                    write_log("Передвинули бамперный уровень на " . LVL_BUMPER2 . "% ($level_bumper)", 3);
                    if ($open < $close && $low < $level_bumper) {
                        $noBumperCheck = $curBar;
                        write_log(" На данном баре ($curBar) также пройден бамперный уровень, но т.к. бар $bar_direct, то это событие игнорируем" . PHP_EOL, 3);
                    }
                    $State = 2; // достигли уровня МП
                    break;
                }
                if ($flag_breakBump) { // прошли вниз бамперный уровень
                    //                        $controlParams['P6dev']=round(($CP_level-$realP6level)/$sizeLevel*100+0.499999,0); // округляем в большую сторону, так как не дошли, то число положительное
                    //  write_log("TMP___ CPlevel=$CP_level, sizaLevel=$sizeLevel realP6level=$realP6level".PHP_EOL,9);
                    //                        if($controlParams['P6dev']=="-0")$controlParams['P6dev']="0";
                    //                        $controlParams['P6aims']=$level_bumper_proc;
                    write_log("Прошли бамперный уровень ($level_bumper) на баре $curBar -> начинаем отслеживать цели и уровень РЕАЛЬНОЙ Т6 =StopLoss($realP6level" . PHP_EOL, 7);
                    $aimsReached[0] = $curBar;
                    $wasEvents = true;
                    $level_stopLoss = $realP6level;
                    $State = 3;
                    break;
                }
                break;
            case 2: // достигли уровня МП -> ищем реальную Т6, отслеживаем пробой или поход на бамперный уровень
                if ($realP6level < $high && $high <= $level_breakdown) {
                    $realP6level = $high;
                    write_log("Переставили уровень реальной P6 в state2 на ($realP6level) на баре $curBar" . PHP_EOL, 9);
                    $realP6bar = $curBar;
                }
                if ($high > $level_breakdown) {
                    //  В случае, если данная модель иммет значение контрольного параметра P6aims (или аналога) = 0, то экономинческий результат от трейда данной модели = Lcor
                    //   Lcor = -(L + spread), где L - расстояние от уровня входа до уровня пробоя МП;
                    $pnlPips = round((abs($level_breakdown - $level_approach) / $stepSize + $spreadSize) * (-1), 6);
                    $procRest = 0;
                    write_log("Пройден вверх уровень пробоя МП ($level_breakdown) на баре $curBar - фиусируем убыток=$pnlPips" . PHP_EOL, 6);
                    //                        $controlParams['P6dev']="X";
                    //                        $controlParams['P6aims']="0";
                    $State = 99;
                    break;
                }
                if ($low < $level_bumper && $noBumperCheck != $curBar) { // прошли вниз бамперный уровень
                    //                        $controlParams['P6dev']=round(($CP_level-$realP6level)/$sizeLevel*100-0.499999,0); // округляем в меньшую сторону сторону, так как дошли до МП, то число отрицательное, например -2.01 -> 3%
                    //                        if($controlParams['P6dev']=="-0")$controlParams['P6dev']="0";
                    //                        $controlParams['P6aims']=$level_bumper_proc;
                    write_log("Прошли вниз бамперный уровень ($level_bumper) на баре $curBar -> начинаем отслеживать цели и уровень РЕАЛЬНОЙ Т6 =StopLoss ($realP6level" . PHP_EOL, 7);
                    $wasEvents = true;
                    $level_stopLoss = $realP6level;
                    $aimsReached[0] = $curBar;
                    $State = 3;
                    break;
                }
                break;
            case 3: //определили реальную Т6 и дошли до бамрперного уровня -> отслеживаем доcтижение реальной или расчетной Т6 или целей 1 2 3 4
                // если события (дост.  цель + пробой Реальной либо Расчетной Т6) наложидись, то
                // ВСПЕГДА по наихудшему - сначала пробит уровень Т6

                if ($first_state3) { // попали сюда в первый раз - расчитываем и выводим в лог уровни целей
                    $aim_30 = round($CP_level - $aimLevels[1] * $sizeLevel / 100, 5);
                    $aim_50 = round($CP_level - $aimLevels[2] * $sizeLevel / 100, 5);
                    $aim_80 = round($CP_level - $aimLevels[3] * $sizeLevel / 100, 5);
                    $aim_120 = round($CP_level - $aimLevels[4] * $sizeLevel / 100, 5);
                    //                        $controlParams['lvl_aim1']=abs($aim_30);
                    //                        $controlParams['lvl_aim2']=abs($aim_50);
                    //                        $controlParams['lvl_aim3']=abs($aim_80);
                    //                        $controlParams['lvl_aim4']=abs($aim_120);
                    write_log("Уровни целей: №1: $aim_30 №2: $aim_50 №3: $aim_80 №4: $aim_120 " . PHP_EOL, 7);
                    $first_state3 = false;
                }
                //                    if($aimLevel>$low){
                //                        $aimLevel=$low;
                //                        $aimBar=$curBar;
                //                    }
                if ($curBar > ($CP_bar + $sizeTime * TIME_DEPTH_AIMS / 100)) { // прошли глубину поиска отслеживания целей

                    $pnl = round((($level_approach - $close) / $stepSize - $spreadSize) * $procRest / 100);
                    write_log("Прошли глубину поиска отслеживания целей на баре $curBar -> фиксируем фин.рез-т на закрытии текущего бара (верно???) по незакрытой позиции ($procRest%) = $pnl + ранее накопленная прибыль ($pnlPips)" . PHP_EOL, 7);
                    $pnlPips += $pnl;
                    $procRest = 0;

                    //if(isset($controlParams['P6aims']))$controlParams['P6aims'].="%";
                    $State = 99;
                    break;
                }

                if ($high > $level_stopLoss) { // сработал StopLoss
                    $pnl = round((($level_approach - $level_stopLoss) / $stepSize - $spreadSize) * $procRest / 100, 6);  // по незакрытой (части) позиции
                    write_log("Сработал StopLoss ($level_stopLoss) на баре $curBar -> фиксируем результат по незакрытой позиции ($procRest%) = $pnl + ранее накопленная прибыль ($pnlPips)" . PHP_EOL, 7);
                    $pnlPips += $pnl;
                    $procRest = 0;
                    // if(isset($controlParams['P6aims']))$controlParams['P6aims'].="%";
                    $State = 99;
                    break;
                }

                $curAimLevel = ($CP_level - $low) / $sizeLevel * 100;
                // какую цель достигли (1-4)
                for ($curAimNum = 1; $curAimNum <= 4; $curAimNum++) { // определяем номер достигнутой цели текущим баром
                    if ($curAimLevel > $aimLevels[$curAimNum]) { // цель номер $i достигнута на данном баре - если в первый раз, то производим частичное закрытие позиции
                        if ($aimsReached[$curAimNum] === false) {
                            $level_cur_aim = $CP_level - $sizeLevel * $aimLevels[$curAimNum] / 100;
                            $proc_to_fix = $fixProfitParts[$curAimNum];
                            if ($curAimNum == 4 || $proc_to_fix > $procRest) $proc_to_fix = $procRest; // если последняя (четвертая) цель, либо пытаемся зафиксировать больше, чем осталось
                            $pnl = round((($level_approach - $level_cur_aim) / $stepSize - $spreadSize) * $proc_to_fix / 100, 6);
                            write_log("Достигли $curAimNum цели на баре $curBar (уровень $curAimNum цели=" . $aimLevels[$curAimNum] . ") - фиксируем $proc_to_fix% открытой позиции. Прибыль=$pnl" . PHP_EOL, 7);
                            $pnlPips += $pnl;
                            $procRest -= $proc_to_fix;
                            $aimsReached[$curAimNum] = $curBar;
                            switch ($curAimNum) {
                                case 1:
                                    write_log("Впервые достигли ПЕРВОЙ цели - переставляем StopLoss на безубыток (уровень подхода) = $level_approach" . PHP_EOL, 2);
                                    $level_stopLoss = $level_approach;
                                    break;
                                case 2:
                                    write_log("Впервые достигли ВТОРОЙ цели - переставляем StopLoss на бамперный уровень ($level_bumper_proc) = $level_bumper" . PHP_EOL, 2);
                                    $level_stopLoss = $level_bumper;
                                    break;
                                case 3:
                                    write_log("Впервые достигли ТРЕТЬЕЙ цели - переставляем StopLoss на уровень первой цели (" . LVL_AIM_1 . ") = $aim_30" . PHP_EOL, 2);
                                    $level_stopLoss = $aim_30;
                                    break;
                                case 4:
                            }
                        }
                    } else break;
                }
                if ($procRest == 0) {

                    $State = 99;
                }
                break;
            case 99:
                break;
        }
        if (!$wasEvents) $curBar++;
    }
    if ($pnlPips !== 0) write_log(" позиция закрыта, выходим. Фин.рез-т= $pnlPips" . PHP_EOL, 6);
    //        if(isset($controlParams['P6dev'])&&$controlParams['P6dev']!="X"&&$controlParams['P6dev']!="rchd"&&$controlParams['P6dev']!="unrchd")$controlParams['P6dev'].="%";
    //        if(isset($controlParams['P6aims'])&&$controlParams['P6aims']!="0")$controlParams['P6aims'].="%";
    //        return($controlParams);
    return (round($pnlPips, 6));
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
        $i = 0;
        while ($curBar = $result->fetch_assoc()) {
            $Chart[] = ["dandt" => $curBar['dandt'], "o" => $curBar['o'], "c" => $curBar['c'], "h" => $curBar['h'], "l" => $curBar['l']];
            $chartNumById[$curBar['id']] = $i;
            if ($barId == $curBar['id']) $baseBarNum = $i;
            $i++;
        }
        //   $res['out_tmp_bar']=$Chart;
    } else $baseBarNum = $chartNumById[$model['bar_id']];
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
    global $Chart, $res, $maxBar4Split, $curSplit;
    //    if(!isset($maxBar4Split[$curSplit])||$maxBar4Split[$curSplit]<$i)$maxBar4Split[$curSplit]=$i;
    return (($v == 'low') ? $Chart[$i]['h'] : $Chart[$i]['l'] * (-1));
}

function low($i, $v, $line = "0")
{ // возвращает high либо -low
    global $Chart, $res, $maxBar4Split, $curSplit;
    //if(!isset($i))$res['tmp_info_low_func']="!!! low: i: ".$i." v: ".$v." line: ".$line;
    //    if(!isset($maxBar4Split[$curSplit])||$maxBar4Split[$curSplit]<$i)$maxBar4Split[$curSplit]=$i;
    return (($v == 'low') ? $Chart[$i]['l'] * 1 : $Chart[$i]['h'] * (-1));
}

function open($i, $v)
{ // возвращает high либо -low
    global $Chart, $maxBar4Split, $curSplit;
    //    if(!isset($maxBar4Split[$curSplit])||$maxBar4Split[$curSplit]<$i)$maxBar4Split[$curSplit]=$i;
    return (($v == 'low') ? $Chart[$i]['o'] : $Chart[$i]['o'] * (-1));
}

function close($i, $v)
{ // возвращает high либо -low
    global $Chart, $maxBar4Split, $curSplit;
    //    if(!isset($maxBar4Split[$curSplit])||$maxBar4Split[$curSplit]<$i)$maxBar4Split[$curSplit]=$i;
    return (($v == 'low') ? $Chart[$i]['c'] : $Chart[$i]['c'] * (-1));
}
