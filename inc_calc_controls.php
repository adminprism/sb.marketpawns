<?php
// определение настроек для уровней - в % от рамера модели
define("LVL_BREAKDOWN", -6); // пробой уровня МП
define("LVL_APPROACH", 6); // подход к уровню МП
// define("LVL_BUMPER1", 25); // бамперный уровень для МП (начальный)
define("LVL_BUMPER1", 20); // бамперный уровень для МП (начальный)
define("LVL_BUMPER2", 15); // бамперный уровень для МП (при достижении уровня МП)
// define("LVL_LOST", 70); // потеря уровня МП
define("LVL_LOST", 85); // потеря уровня МП // !!!
define("LVL_AIM_1", 30); // первая цель
define("LVL_AIM_2", 50); // вторая цель
define("LVL_AIM_3", 80); // третья цель
define("LVL_AIM_4", 120); // четвертая цель
// ! Breakdown AM aims, added 09/01/22
define("LVL_REVSTOP", 15); // Уровень отмены целей пробоя МП 
define("LVL_TRSTOP_1", 6); // трейлинг стоп для целей пробоя МП 
define("LVL_TRSTOP_2", -15); // трейлинг стоп для целей пробоя МП 
define("LVL_TRSTOP_3", -30); // трейлинг стоп для целей пробоя МП 
define("LVL_TRSTOP_4", -50); // трейлинг стоп для целей пробоя МП 
define("LVL_TRSTOP_5", -100); // трейлинг стоп для целей пробоя МП 
define("LVL_AIM_5_REV_30", -30); // первая цель пробоя МП 
define("LVL_AIM_6_REV_50", -50); // вторая цель пробоя МП 
define("LVL_AIM_7_REV_80", -80); // третья цель пробоя МП 
define("LVL_AIM_8_REV_100", -100); // четвертая цель пробоя МП 
define("LVL_AIM_9_REV_150", -150); // пятая цель пробоя МП 
define("LVL_AIM_10_REV_200", -200); // шестая цель пробоя МП 

// ! PRE- levels
define("LVL_PREAPPROACH", 15);  // preapproach level - when the level is reached, it's time to start defining precontrols

define("TIME_DEPTH", 200); // Глубина достижения МП - % от размера по времени, отложенный от времени расчётной т.6
define("TIME_DEPTH_AIMS", 400); // Глубина отслеживания целей МП (% от размера МП, отложенные от расчетной Т6
$aimLevels = [0, LVL_AIM_1, LVL_AIM_2, LVL_AIM_3, LVL_AIM_4, LVL_AIM_5_REV_30, LVL_AIM_6_REV_50, LVL_AIM_7_REV_80, LVL_AIM_8_REV_100, LVL_AIM_9_REV_150, LVL_AIM_10_REV_200]; // служебный массив - перечисление целей 1,2,3,4, а также целей пробоя МП // ! Reverse AM aims, added 09/01/22
$revStopLevels = [LVL_REVSTOP, LVL_TRSTOP_1, LVL_TRSTOP_2, LVL_TRSTOP_3, LVL_TRSTOP_4, LVL_TRSTOP_5];

// * Функция для расчета контрольных параметров по одному набору точек (одна P6 - передается в параметре)
function calcParams($model, $title, $CP, $pips = 0)
{ // функция для расчета контрольных параметров по одному набору точек (одна P6 - передается в параметре
    global $res, $Chart, $aimLevels, $revStopLevels, $baseBarNum, $allowedProtoSetId;
    $G1 = $model['param']['G1'] ?? $model['G1'];
    $Alg = $model['Alg'];
    $v = $model['v'];
    $K = ($v == 'low') ? 1 : -1; // множитель для зеркального переворачивания моделей high - НАДО НЕ ЗАБЫВАТЬ ЕГО ИСПОЛЬЗОВАТЬ ПРИ ИСПОЛЬЗОВАНИИ ЛЮБЫХ УРОВНЕЙ
    //$aim_name=str_replace("dev","",str_replace("ищем ","",$title)); // из title вырезали название цели
    if ($pips <= 0) $pips = calcPips($Chart); // если $pips в функцию не передали, то считаем его по текущему чарту (но в настоящее время он должен везде передаваться :)
    $precision = log10($pips) * -1; // сколько знаков для округления уровней в зависимости от $pips


    write_log("--- calcParams($title): id='" . $model['id'] . "' G1='$G1' Alg=$Alg  v=$v CP='$CP'" . PHP_EOL, 7);

    $pos = strpos($CP, "("); // Find the position of the first occurrence of a substring in a string/Возвращает позицию первого вхождения подстроки
    $CP_bar = floatval(substr($CP, 0, $pos - 1)); // бар узла 6-ой 
    $CP_level = round(floatval(substr($CP, $pos + 1)) * $K, $precision); // уровень узла 6-ой благодаря $K уровень возвращается со знаком +/- в зависимости от направленности модели
    $controlParams = [];
    $sizeLevel = 0;
    if (!$CP || $CP_level == 0 || $CP_bar < $model['t4']) {
        write_log("CP не определен либо левее Т4" . PHP_EOL, 3);
        return (false);
        //$CP_ok=false;
    }
    if ($G1 == 'DBM' && $title == "ищем P6dev") {
        write_log("В модели DBM P6dev не определяем, выходим." . PHP_EOL, 3);
        return (false);
    }
    write_log("CP: '$CP' - bar=$CP_bar level=$CP_level" . PHP_EOL, 3);
    if (!(strpos($title, 'auxP6') === false) && $G1 !== 'AM') {
        $extraCheckLT = true; // требуется доп проверка на пересечения ценой ЛТ пока 'unrchd';
        write_log("т.к. определяем параметры для aux, то определяем ЛТ для доп.проверки ее касания ценой" . PHP_EOL, 8);
        $LT = LT($model); // линия тренда
    } else $extraCheckLT = false; // доп проверка на достижение ценой ЛТ не требуется

    // * Определяем размер модели по уровням и времени
    if ($G1 == 'AM/DBM' || $G1 == 'AM') {
        $sizeLevel = round($CP_level - low($model['t1'] + $baseBarNum, $v), $precision);
        $sizeTime = $CP_bar - $model['t1']; //t1==0 всегда, написал  просто для порядка
        write_log("G1=$G1 => размер модели по уровню = t6-t1 =" . $CP_level . " - " . low($model['t1'] + $baseBarNum, $v) . " =" . round($sizeLevel, 6) . " по времени t6-t1 =" . $CP_bar . " - " . $model['t1'] . " = " . round($sizeTime, 6) . PHP_EOL, 7);
        $first_bar_num = $model["t4"] + 1;
        write_log("Начинаем перебор с бара t4+1=$first_bar_num до CP+sizeTime*TIME_DEPTH% =" . $CP_bar . "+" . $sizeTime . "*" . TIME_DEPTH . "%=" . ($CP_bar + $sizeTime * TIME_DEPTH / 100) . PHP_EOL, 8);
        $controlParams['bar_0'] = $model['t1'];
        $controlParams['lvl_0'] = min(abs($CP_level), abs(low($model['t1'] + $baseBarNum, $v)));
    } else {
        $sizeLevel = round($CP_level - low($model['t3'] + $baseBarNum, $v), $precision);
        $t2_fieldName = (!isset($model['t2\'']) || is_null($model['t2\''])) ? 't2' : 't2\'';
        $sizeTime = $CP_bar - $model[$t2_fieldName];
        write_log("G1=$G1 => размер модели по уровню = t6-t3 =" . $CP_level . " - " . low($model['t3'] + $baseBarNum, $v) . " =" . round($sizeLevel, 6) . " по времени t6-$t2_fieldName =" . $CP_bar . " - " . $model[$t2_fieldName] . " = " . round($sizeTime, 6) . PHP_EOL, 7);
        if (!isset($model["t5"]) || is_null($model["t5"])) {
            write_log("Т5 не определена - выходим" . PHP_EOL, 3);
            return (false);
        }
        $first_bar_num = $model["t5"];
        write_log("Начинаем перебор с бара t5=$first_bar_num до CP+sizeTime*TIME_DEPTH% =" . $CP_bar . "+" . $sizeTime . "*" . TIME_DEPTH . "%=" . ($CP_bar + $sizeTime * TIME_DEPTH / 100) . PHP_EOL, 8);
        $controlParams['bar_0'] = $model[$t2_fieldName];
        $controlParams['lvl_0'] = min(abs($CP_level), abs(low($model['t3'] + $baseBarNum, $v)));
    }
    $controlParams['size_time'] = round($sizeTime, 3);
    $controlParams['size_level'] = round($sizeLevel, $precision);


    if (($CP_bar + $sizeTime * TIME_DEPTH / 100) > ($first_bar_num + 500)) {
        write_log("Слишком большая модель, выходим." . PHP_EOL, 3);
        return (false);
    }
    if ($sizeLevel <= $pips) {
        write_log("Очень маленькая модель (size_level<=pips), выходим" . PHP_EOL, 3);
        return (false);
    }
    // перебор баров начиная с $first_bar_num
    $State = 0; // * уникальный идентификатор текущего момента в алгоритме, однозначно описывающий текущую ситуацию поведения модели после фиксации
    // * 0: начало алгоритма, находимся на первом баре после фиксации (t4+1 либо t5) либо далее, если пока не случилось событий (трриггеров)
    // * 1:  достигли уровня подхода к уровню МП и отслеживаем достижение уроня МП либо бамперного уровня
    // * 2: достигли уровня МП -> ищем реальную Т6, отслеживаем пробой или поход на бамперный уровень
    // * 3: определили реальную Т6 и дошли до бамрперного уровня -> отслеживаем достижение реальной Т6 или целей 1 2 3 4
    // ! Reverse AM aims, added 09/01/22
    // * 4: пробой МП, отслеживаем Бамперный уровень пробоя МП (LVL_AIM_5_REV_30) и Уровень отмены целей пробоя МП

    // * 99: прекращение отслеживания ситуации, осуществляется в случаях:
    // * - пробили ЛТ 
    // * - достигли TimeDepth или достигли правой границы графика 
    // * - достигли все цели или вышли по стопу и т.п.

    $curBar = $first_bar_num; // текущий анализируемый бар, далее идем вправо пока не сформируются все нужные контрольные параметры
    $CNT = count($Chart);

    $realP6level = -999999999999;
    $realP6bar = false;
    $calcP6reached = false;
    $aimLevel = $CP_level;
    $approachLevelBar = 0; // номер бара, на котором достигли уровня подхода к уровню МП
    $controlParams['P6dev'] = 'unrchd'; // устанавливаем в самом начале 'unrchd', потом, возможно переопределим в зависимости от успехов
    $first_state3 = $first_state4 = true; // триггер, показывающий, что state=3/4 у нас первый раз - что-бы однократно вывести в лог информацию об уровнях целей при попадании на state=3
    // вычисляем и вывподим в лог уровни
    $level_lost = round($CP_level - $sizeLevel * LVL_LOST / 100, $precision); // уровень потери МП
    // $level_lost = round($CP_level - $sizeLevel  * $K * LVL_LOST / 100, $precision); // уровень потери МП
    $level_approach = $lvl_appr_orig = round($CP_level - $sizeLevel * LVL_APPROACH / 100, $precision); // уровень подхода к уровню МП
    // $level_approach = round($CP_level - $sizeLevel * $K * LVL_APPROACH / 100, $precision); // уровень подхода к уровню МП
    if ($level_approach < high($model['t4'] + $baseBarNum, $v)) {
        $level_approach = round(high($model['t4'] + $baseBarNum, $v) + $pips, $precision);
        write_log("ВНИМАНИЕ! Передвигаем уровень подхода к уровню МП на уровень t4 + 1 пункт ($level_approach)" . PHP_EOL, 3);
    }
    $level_bumper = round($CP_level - $sizeLevel * LVL_BUMPER1 / 100, $precision); // бамперный уровень
    // $level_bumper = round($CP_level - $sizeLevel * $K * LVL_BUMPER1 / 100, $precision); // бамперный уровень
    $level_bumper_proc = LVL_BUMPER1;
    $level_breakdown = round($CP_level - $sizeLevel * LVL_BREAKDOWN / 100, $precision); // уровень пробоя МП
    // $level_breakdown = round($CP_level - $sizeLevel * $K * LVL_BREAKDOWN / 100, $precision); // уровень пробоя МП
    // * Reverse AM aims, added 09/01/22
    // ! Fixed 23/01/22
    // $level_RevStop = round($CP_level - $sizeLevel * LVL_REVSTOP / 100, $precision); // Уровень отмены целей пробоя МП
    $level_RevStop = round($CP_level - $sizeLevel * LVL_REVSTOP / 100, $precision); // Уровень отмены целей пробоя МП

    $controlParams['lvl_am'] = abs($CP_level);
    $controlParams['lvl_brkd'] = abs($level_breakdown);
    $controlParams['lvl_appr'] = abs($level_approach);
    $controlParams['lvl_bump'] = abs($level_bumper);
    $controlParams['lvl_lost'] = abs($level_lost);
    //$controlParams['lvl_preappr'] = abs($CP_level - $sizeLevel * LVL_PREAPPROACH / 100);

    $aim_30 = round($CP_level - $aimLevels[1] * $sizeLevel / 100, $precision);
    $aim_50 = round($CP_level - $aimLevels[2] * $sizeLevel / 100, $precision);
    $aim_80 = round($CP_level - $aimLevels[3] * $sizeLevel / 100, $precision);
    $aim_120 = round($CP_level - $aimLevels[4] * $sizeLevel / 100, $precision);
    // ! Reverse AM aims, added 09/01/22
    $aim_rev_30 = round($CP_level - $aimLevels[5] * $sizeLevel / 100, $precision);
    $aim_rev_50 = round($CP_level - $aimLevels[6] * $sizeLevel / 100, $precision);
    $aim_rev_80 = round($CP_level - $aimLevels[7] * $sizeLevel / 100, $precision);
    $aim_rev_100 = round($CP_level - $aimLevels[8] * $sizeLevel / 100, $precision);
    $aim_rev_150 = round($CP_level - $aimLevels[9] * $sizeLevel / 100, $precision);
    $aim_rev_200 = round($CP_level - $aimLevels[10] * $sizeLevel / 100, $precision);

    $controlParams['lvl_aim1'] = abs($aim_30);
    $controlParams['lvl_aim2'] = abs($aim_50);
    $controlParams['lvl_aim3'] = abs($aim_80);
    $controlParams['lvl_aim4'] = abs($aim_120);
    // ! Reverse AM aims, added 09/01/22
    $controlParams['lvl_aim5_rev30'] = abs($aim_rev_30);
    $controlParams['lvl_aim6_rev50'] = abs($aim_rev_50);
    $controlParams['lvl_aim7_rev80'] = abs($aim_rev_80);
    $controlParams['lvl_aim8_rev100'] = abs($aim_rev_100);
    $controlParams['lvl_aim9_rev150'] = abs($aim_rev_150);
    $controlParams['lvl_aim10_rev200'] = abs($aim_rev_200);

    $noBumperCheck = 0; // доп.флажок, который ставится в номер бара, на котором не нужно проверять пробой бамперного уровня в п.2
    $flag_RevBar = 0; // ! added 25/01/22 this bar is not supposed to be checked for LVL_REVSTOP crossing
    $lastState = 0; // добавлено 2022-09-08 - сохраняем State, с которого вылетели из цикла ниже (while)
    // (используется при определении, нужно ли считать параметры "next" - для следующего(будущего) бара, если дошли до границы и подхода не было)
    $isNextParamsNeeded = false; // 2022-09-08, флаг, что нужно посчитать параметры преконтроля для "будушего" бара
    while ($State !== 99) {
        $wasEvents = false; // флаг, показывающий, что на данной итерации ($curBar) были какие-то собыдтия (триггеры) (если не было, то двигаемся дальше)
        if (($curBar + $baseBarNum) == $CNT) {
            write_log("Достиглм правой границы графика=$CNT, выход", 8);
            if ($lastState == 0 && NEXT_NEEDED) $isNextParamsNeeded = true; // если ни один из триггеров не сработал, то нужно посчитать "next" параметры преконтроля (но только для RT)
            // т.е. какие они будут если на след.баре будет достижение уровня подхода
            //(на след.баре цена идет от close последнего бара вверх до уровня подхода)
            // при этом игнорируем пробой линии потери - так как уровни потери в сетарах могут быть различные а не как указано для ИСТОРИИ
            $State = 99;
            continue; // фактически, break
        }
        $open = open($curBar + $baseBarNum, $v);
        $close = close($curBar + $baseBarNum, $v);
        $low = low($curBar + $baseBarNum, $v);
        $high = high($curBar + $baseBarNum, $v);
        $bar_direct = ($open < $close && $v == 'low' || $open > $close && $v == 'high') ? "ВОСХОДЯЩИЙ" : "НИСХОДЯЩИЙ";
        switch ($State) { // Checking current State identifier and then changing it depnding on if(...) - circumstances

                // ! Если события (достигнут уровень подхода к МП (approach) а также уровень потери цели/пробой лин.тренда) наложились, то 
                // ! - для нисходящей модели если свеча вверх, то сначала пробит уровень потери цели/линии тренда
            case 0: // * Первый шаг алгоритма, зафиксировали модель и движемся до первого "события-триггера"
                $lastState = 0;
                if ($curBar > ($CP_bar + $sizeTime * TIME_DEPTH / 100) && !NEXT_NEEDED) { // выходим только если считаем историю, в RT глубина определяется в сетах
                    write_log("Достигли глубины ожидания подхода к уровню МП на баре  $curBar -> выход" . PHP_EOL, 8);
                    $controlParams['Done'] = "DEPTH APPROACH";
                    $controlParams['Reached_at'] = $curBar; // * is used to define left chart margin
                    $State = 99;
                    break;
                }
                $flag_breakLost = ($low < $level_lost) ? true : false; // current bar's low is compared with AM's aims-lost-level
                $flag_breakLT = ($extraCheckLT && lineLevel($LT, $curBar + $baseBarNum) >= $low) ? true : false; // Если уровень ЛТ выше low текущего бара, ЛТ пробита
                $flag_approach = ($high > $level_approach) ? true : false;
                // ! Reverse AM aims, added 09/01/22
                $flag_RevStop = ($low < $level_RevStop) ? true : false; // current bar's low is compared with AM's ReverseAims-lost-level

                // ! moved lower 23/01/22
                // if ($high > $level_breakdown) {
                //     // write_log("Пройден вверх уровень пробоя МП ($level_breakdown) на баре $curBar, преодолевшем уровень подхода $level_approach" . PHP_EOL, 6);
                //     write_log("Пройден вверх уровень пробоя МП ($level_breakdown) на баре $curBar, преодолевшем уровень подхода $level_approach . -> начинаем отслеживать цели пробоя МП" . PHP_EOL, 6);
                //     $controlParams['P6dev'] = "X";
                //     $controlParams['P6aims'] = "0";
                //     // ! Reverse AM aims, added 09/01/22
                //     // $controlParams['Done'] = "REACHED";
                //     $controlParams['Done'] = "REVERSE"; // ! In Inc_CheckNewModel.php this flag is used for Clst_II and Clst_II_E models 1 checking if the P6 is reached. Text is not important.
                //     // $controlParams['Reached_at'] = $curBar;
                //     $controlParams['Reached_at'] = $controlParams['ApprReachedAt'] =  $curBar; // ! $controlParams['ApprReachedAt'] added 07/01/22 is used to define precontrol parameters  (like _ll25apprP6 and _ll25apprP6")

                //     // $State = 99;
                //     $State = 4;
                //     break; // ! added 23/01/22
                // }

                // ! Reverse AM aims, added 09/01/22
                // if ($level_breakdown && $flag_RevStop) { // ! fixed 23/01/22
                if (($high > $level_breakdown) && $flag_RevStop) { // * на текущем баре пробили МП и Уровень отмены целей пробоя МП
                    if ($open < $close) { // * вначале пробили Уровень отмены целей пробоя МП, затем прошёл пробой МП (и следовательно считаем,что он не отменён)
                        $flag_RevBar = $curBar; // ! added 25/01/22 this bar is not supposed to be checked for LVL_REVSTOP crossing
                        // write_log(" ОДНОВРЕМЕННО пробили МП ($level_breakdown) и Уровень отмены целей пробоя МП ($flag_RevStop) на баре $curBar - бар $bar_direct -> выход" . PHP_EOL, 3);
                        write_log("ОДНОВРЕМЕННО пробили МП ($level_breakdown) и Уровень отмены целей пробоя МП ($flag_RevStop) на баре $curBar - бар . $bar_direct . -> начинаем отслеживать уровень цели пробоя" . PHP_EOL, 3);
                        $wasEvents = true; // ! added 23/01/22 in case there were any events, next "while" iteration will be applied to the same bar
                        $controlParams['P6dev'] = "X";
                        $controlParams['Done'] = "REVERSE";
                        $controlParams['Reached_at'] = $controlParams['ApprReachedAt'] = $curBar; // ! $controlParams['ApprReachedAt'] added 07/01/22 is used to define precontrol parameters  (like _ll25apprP6 and _ll25apprP6")
                        $State = 4;
                        break;
                    } else { // * вначале достигли уровень пробоя МП
                        // write_log(" ОДНОВРЕМЕННО пробили уровень потери МП ($level_lost) и уровень подхода к уровню МП ($level_approach) на баре $curBar - бар $bar_direct -> отслеживаем достижение уровня МП либо бамперного уровня" . PHP_EOL, 3);
                        write_log(" ОДНОВРЕМЕННО пробили МП ($level_breakdown) и Уровень отмены целей пробоя МП ($flag_RevStop) на баре $curBar - бар . $bar_direct . -> выход" . PHP_EOL, 3);
                        $wasEvents = true; // * in case there were no events, next "while" iteration will be applied to the next bar
                        $approachLevelBar = $curBar; // ! добавлен флаг (21.10.2020), чтобы на баре, который достиг ур.подхода к МП, пробой бамперного уровня не проверять !!!!
                        // $controlParams['Reached_at'] = $curBar;
                        $controlParams['Reached_at'] = $controlParams['ApprReachedAt'] = $curBar; // ! Добавлен элемент массива "бар достижения уровня подхода к МП"
                        // $controlParams['Done'] = "APPROACH";
                        $controlParams['Done'] = "REVERSE LOST";
                        $State = 99;
                        break;
                    }
                }
                // ! moved here 23/01/22
                if ($high > $level_breakdown) {
                    // write_log("Пройден вверх уровень пробоя МП ($level_breakdown) на баре $curBar, преодолевшем уровень подхода $level_approach" . PHP_EOL, 6);
                    write_log("Пройден вверх уровень пробоя МП ($level_breakdown) на баре $curBar, преодолевшем уровень подхода $level_approach . -> начинаем отслеживать цели пробоя МП" . PHP_EOL, 6);
                    $controlParams['P6dev'] = "X";
                    $controlParams['P6aims'] = "0";
                    // ! Reverse AM aims, added 09/01/22
                    // $controlParams['Done'] = "REACHED";
                    $controlParams['Done'] = "REVERSE"; // ! this flag is used for Clst_II and Clst_II_E models 1 checking if the P6 is reached in Inc_CheckNewModel.php. Text is not important.
                    // $controlParams['Reached_at'] = $curBar;
                    $controlParams['Reached_at'] = $controlParams['ApprReachedAt'] = $curBar; // ! $controlParams['ApprReachedAt'] added 07/01/22 is used to define _ll25apprP6 and _ll25apprP6" parameters
                    // $State = 99;
                    $State = 4;
                    break; // ! added 23/01/22
                }

                if ($flag_breakLost && $flag_approach) { // * на текущем баре пробили уровень подхода к уровню МП и уровень потери МП
                    if ($open < $close) { // * вначале пробили уровень потери МП
                        write_log(" ОДНОВРЕМЕННО пробили уровень потери МП ($level_lost) и уровень подхода к уровню МП ($level_approach) на баре $curBar - бар $bar_direct -> выход" . PHP_EOL, 3);
                        $controlParams['Done'] = "LOST AM";
                        $controlParams['Reached_at'] = $controlParams['ApprReachedAt'] = $curBar; // ! Добавлен элемент массива "бар достижения уровня подхода к МП"
                        if (NEXT_NEEDED) $isNextParamsNeeded = true; // если находимся в RT, то нужно посчитать параметры для NEXT
                        // пробой уровня потери игнорируем, так как в торговых сетапах могут быть другие уровни потери
                        $State = 99;
                        break;
                    } else { // * вначале достигли уровень подхода к уровню МП
                        // write_log(" ОДНОВРЕМЕННО пробили уровень потери МП ($level_lost) и уровень подхода к уровню МП ($level_approach) на баре $curBar - бар $bar_direct -> отслеживаем достижение уровня МП либо бамперного уровня" . PHP_EOL, 3);
                        write_log(" ОДНОВРЕМЕННО пробили уровень потери МП ($level_lost) и уровень подхода к уровню МП ($level_approach) на баре $curBar - бар $bar_direct -> выход" . PHP_EOL, 3);
                        $wasEvents = true;
                        $controlParams['P6dev'] = round(($CP_level - $realP6level) / $sizeLevel * 100 + 0.499999, 0);
                        if ($controlParams['P6dev'] == "-0") $controlParams['P6dev'] = "0";
                        $approachLevelBar = $curBar; // ! добавлен флаг (21.10.2020), чтобы на баре, который достиг ур.подхода к МП, пробой бамперного уровня не проверять !!!!
                        // $controlParams['Reached_at'] = $curBar;
                        $controlParams['Reached_at'] = $controlParams['ApprReachedAt'] = $curBar; // ! Добавлен элемент массива "бар достижения уровня подхода к МП
                        $controlParams['Done'] = "APPROACH";
                        $State = 1;
                        break;
                    }
                }
                if ($flag_breakLT && $flag_approach) { // * на текущем баре пробили уровень подхода к уровню МП и достигли уровня ЛТ
                    if ($open < $close) { // * вначале пересекли ЛТ
                        write_log(" ОДНОВРЕМЕННО достигли уровень подхода к уровню МП ($level_lost) и достигли линию ЛТ на баре $curBar - бар $bar_direct -> выход" . PHP_EOL, 3);
                        $controlParams['Done'] = "BREAK LT";
                        $controlParams['Reached_at'] = $curBar;
                        $State = 99;
                        break;
                    } else { // * вначале достигли уровень подхода к уровню МП
                        // write_log(" ОДНОВРЕМЕННО достигли уровень подхода к уровню МП ($level_lost) и достигли линию ЛТ на баре $curBar - бар $bar_direct -> отслеживаем достижение уровня МП либо бамперного уровня" . PHP_EOL, 3);
                        write_log(" ОДНОВРЕМЕННО достигли уровень подхода к уровню МП ($level_lost) и достигли линию ЛТ на баре $curBar - бар $bar_direct -> выход" . PHP_EOL, 3);
                        $wasEvents = true;
                        $controlParams['P6dev'] = round(($CP_level - $realP6level) / $sizeLevel * 100 + 0.499999, 0);
                        if ($controlParams['P6dev'] == "-0") $controlParams['P6dev'] = "0";
                        $approachLevelBar = $curBar;
                        // $controlParams['Reached_at'] = $curBar;
                        $controlParams['Reached_at'] = $controlParams['ApprReachedAt'] = $curBar; // ! Добавлен элемент массива "бар достижения уровня подхода к МП"
                        $controlParams['Done'] = "APPROACH";
                        $State = 1;
                        break;
                    }
                }

                if ($flag_breakLost) {
                    $controlParams['Done'] = "LOST AM";
                    if (!NEXT_NEEDED) { // если находимся в RT, то нужно посчитать параметры для NEXT -> не выходим по данному событию (потеря МП):
                        // пробой уровня потери игнорируем, так как в торговых сетапах могут быть другие уровни потери
                        write_log("Пробили уровень потери МР ($level_lost) на баре $curBar -> выход" . PHP_EOL, 5);
                        $State = 99;
                        break;
                    }
                    break;
                }
                if ($flag_breakLT) { // доп.проверка на касание ценой линии тренда - цена достигла ЛТ
                    $controlParams['Done'] = "BREAK LT";
                    if (!NEXT_NEEDED) {
                        write_log("доп.проверка(1) : цена достигла ЛТ на баре $curBar" . PHP_EOL, 6);
                        $State = 99;
                        break;
                    }
                    break;
                }
                if ($high > $level_approach) {
                    write_log("Пробили уровень подхода к уровню МП ($level_approach) на баре $curBar " . PHP_EOL, 5);
                    $wasEvents = true;
                    $approachLevelBar = $curBar;
                    // $controlParams['Reached_at'] = $curBar;"
                    $controlParams['Reached_at'] = $controlParams['ApprReachedAt'] = $curBar; // ! Добавлен элемент массива "бар достижения уровня подхода к МП"
                    $controlParams['Done'] = "APPROACH";
                    $State = 1;
                    break;
                }
                break;
            case 1: // * достигли уровня подхода к уровню МП и отслеживаем достижение уровня МП либо бамперного уровня (начального)
                // * если события (достижение уровня МП + Бамперный уровень/пробой лин.тренда) наложились, то если свеча вверх, то сначала пробит бамперный уровень/LT
                $lastState = 1;
                // ! fixed 23/01/22
                // if ($realP6level < $high && $high <= $CP_level) {
                if (($high > $realP6level) && ($high <= $CP_level)) {
                    $realP6level = $high;
                    $realP6bar = $curBar;
                    write_log("Переставили уровень реальной P6 в state1 на ($realP6level) на баре $curBar" . PHP_EOL, 9);
                }
                $flag_breakBump = ($low < $level_bumper && $approachLevelBar !== $curBar) ? true : false;  //добавил (21.10.2020): на баре. который достиг ур.подхода к МП, пробой бамперного уровня не проверять !!!!
                $flag_breakLT = ($extraCheckLT && $controlParams['P6dev'] == 'unrchd' && lineLevel($LT, $curBar + $baseBarNum) >= $low) ? true : false;
                $flag_CP = ($high > $CP_level) ? true : false;

                if ($flag_CP && $flag_breakLT) { //на текущем баре достигли уровень МП и пробили линюю ЛТ
                    if ($open < $close) { // * вначале пробили ЛТ
                        write_log(" ОДНОВРЕМЕННО достили уровень МП ($CP_level) и линию ЛТ на баре $curBar - бар $bar_direct -> выход" . PHP_EOL, 3);
                        $controlParams['Done'] = "BREAK LT";
                        $State = 99;
                        break;
                    } else { // * вначале достигли уровня МП
                        write_log(" ОДНОВРЕМЕННО пробили уровень МП ($CP_level) и линию ЛТ на баре $curBar - бар $bar_direct ->  ищем реальную Т6, отслеживаем пробой или поход на бамперный уровень" . PHP_EOL, 3);
                        $wasEvents = true;
                        // $controlParams['P6dev'] = 'rchd'; // ! P6dev fixed 23/01/22
                        $controlParams['P6dev'] = round(($CP_level - $realP6level) / $sizeLevel * 100 + 0.499999, 0); // округляем в большую сторону, так как не дошли, то число
                        if ($controlParams['P6dev'] == "-0") $controlParams['P6dev'] = "0";
                        $controlParams['Done'] = "REACHED";
                        $calcP6reached = $curBar;
                        $State = 2; // достигли уровня МП
                        break;
                    }
                }

                if ($flag_CP && $flag_breakBump) { //на текущем баре достигли уровень МП и пробили бамперный уровень
                    if ($open < $close) { // вначале достигли бамперного уровня
                        $controlParams['P6dev'] = round(($CP_level - $realP6level) / $sizeLevel * 100 + 0.499999, 0); // округляем в большую сторону, так как не дошли, то число положительное
                        if ($controlParams['P6dev'] == "-0") $controlParams['P6dev'] = "0";
                        $controlParams['P6aims'] = $level_bumper_proc;
                        write_log("ОДНОВРЕМЕННО прошли бамперный уровень ($level_bumper) и достигли уровня МП ($CP_level) на баре $curBar - бар $bar_direct -> начинаем отслеживать цели и уровень РЕАЛЬНОЙ Т6 " . PHP_EOL, 7);
                        $controlParams['Done'] = "REACHED";
                        $wasEvents = true;
                        $State = 3;
                        break;
                    } else { // вначале достигли уровня МП
                        write_log("ОДНОВРЕМЕННО прошли бамперный уровень ($level_bumper) и достигли уровня МП ($CP_level) на баре $curBar - бар $bar_direct -> ищем реальную Т6, отслеживаем пробой или поход на бамперный уровень" . PHP_EOL, 3);
                        $controlParams['P6dev'] = 'rchd';
                        $controlParams['Done'] = "REACHED";
                        $calcP6reached = $curBar;
                        $wasEvents = true;
                        $State = 2; // достигли уровня МП
                        $level_bumper = round($CP_level - $sizeLevel * LVL_BUMPER2 / 100, $precision); // бамперный уровень
                        $level_bumper_proc = LVL_BUMPER2;
                        $controlParams['lvl_bump'] = abs($level_bumper);
                        write_log("Передвинули бамперный уровень на " . LVL_BUMPER2 . "% ($level_bumper)", 3);
                        break;
                    }
                }
                if ($flag_breakLT) { // доп.проверка на касание ценой линии тренда, если пока "unrchd"
                    write_log("доп.проверка(2) : цена достигла ЛТ на баре $curBar" . PHP_EOL, 6);
                    $controlParams['Done'] = "BREAK LT";
                    $State = 99;
                    break;
                }
                if ($flag_CP) { // достигли уровня МП
                    $controlParams['P6dev'] = 'rchd';
                    $controlParams['Done'] = "REACHED";
                    $calcP6reached = $curBar;
                    $wasEvents = true;
                    write_log("Достигли уровня МП ($CP_level) на баре $curBar -> ищем реальную Т6, отслеживаем пробой или поход на бамперный уровень" . PHP_EOL, 7);
                    $level_bumper = round($CP_level - $sizeLevel * LVL_BUMPER2 / 100, $precision); // бамперный уровень
                    $controlParams['lvl_bump'] = abs($level_bumper);
                    $level_bumper_proc = LVL_BUMPER2;
                    write_log("Передвинули бамперный уровень на " . LVL_BUMPER2 . "% ($level_bumper)" . PHP_EOL, 3);
                    if ($open < $close && $low < $level_bumper) {
                        $noBumperCheck = $curBar;
                        write_log(" На данном баре ($curBar) также пройден бамперный уровень, но т.к. бар $bar_direct, то это событие игнорируем" . PHP_EOL, 3);
                    }
                    $State = 2; // достигли уровня МП
                    break;
                }
                if ($flag_breakBump) { // прошли вниз бамперный уровень
                    $controlParams['P6dev'] = round(($CP_level - $realP6level) / $sizeLevel * 100 + 0.499999, 0); // округляем в большую сторону, так как не дошли, то число положительное
                    //  write_log("TMP___ CPlevel=$CP_level, sizaLevel=$sizeLevel realP6level=$realP6level".PHP_EOL,9);
                    if ($controlParams['P6dev'] == "-0") $controlParams['P6dev'] = "0";
                    $controlParams['P6aims'] = $level_bumper_proc;
                    write_log("Прошли бамперный уровень ($level_bumper) на баре $curBar -> начинаем отслеживать цели и уровень РЕАЛЬНОЙ Т6 " . PHP_EOL, 7);
                    $wasEvents = true;
                    $State = 3;
                    break;
                }
                break;

            case 2: // * достигли уровня МП -> ищем реальную Т6, отслеживаем пробой или поход на бамперный уровень
                $lastState = 2;
                if ($realP6level < $high && $high <= $level_breakdown) {
                    $realP6level = $high;
                    write_log("Переставили уровень реальной P6 в state2 на ($realP6level) на баре $curBar" . PHP_EOL, 9);
                    $realP6bar = $curBar;
                }
                if ($high > $level_breakdown) {
                    write_log("Пройден вверх уровень пробоя МП ($level_breakdown) на баре $curBar" . PHP_EOL, 6);
                    $wasEvents = true; // ! added 23/01/22
                    $controlParams['P6dev'] = "X";
                    $controlParams['P6aims'] = "0";
                    // ! Reverse AM aims, added 09/01/22
                    // $controlParams['Done'] = "BREAKDOWN";
                    // $State = 99;
                    $controlParams['Done'] = "REVERSE";
                    $State = 4;
                    break;
                }
                if ($low < $level_bumper && $noBumperCheck != $curBar) { // прошли вниз бамперный уровень
                    $wasEvents = true;
                    $controlParams['P6dev'] = round(($CP_level - $realP6level) / $sizeLevel * 100 - 0.499999, 0); // округляем в меньшую сторону сторону, так как дошли до МП, то число отрицательное, например -2.01 -> 3%
                    if ($controlParams['P6dev'] == "-0") $controlParams['P6dev'] = "0";
                    $controlParams['P6aims'] = $level_bumper_proc;
                    write_log("Прошли вниз бамперный уровень ($level_bumper) на баре $curBar -> начинаем отслеживать цели и уровень РЕАЛЬНОЙ Т6 " . PHP_EOL, 7);
                    $State = 3;
                    break;
                }
                break;

            case 3: // * определили реальную Т6 и дошли до бамрперного уровня -> отслеживаем доcтижение реальной или расчетной Т6 или целей 1 2 3 4
                if ($lastState != 3) { // если тут оказались впервые, то есть пробили бампер на данном баре, то вычисляем параметры постконтроля (pst)
                    $controlParams = calc_pst_params($v, $model['t4'], $sizeTime, $sizeLevel, $controlParams, $baseBarNum, $curBar, $calcP6reached, $realP6bar, $realP6level, $CP_bar, $CP_level);
                }
                $lastState = 3;
                // * если события (дост.  цель + пробой Реальной либо Расчетной Т6) наложидись, то
                // * ВСЕГДА по наихудшему - сначала пробит уровень Т6

                if ($first_state3) { // попали сюда в первый раз - расчитываем и выводим в лог уровни целей
                    $aim_30 = round($CP_level - $aimLevels[1] * $sizeLevel / 100, $precision);
                    $aim_50 = round($CP_level - $aimLevels[2] * $sizeLevel / 100, $precision);
                    $aim_80 = round($CP_level - $aimLevels[3] * $sizeLevel / 100, $precision);
                    $aim_120 = round($CP_level - $aimLevels[4] * $sizeLevel / 100, $precision);
                    $controlParams['lvl_aim1'] = abs($aim_30);
                    $controlParams['lvl_aim2'] = abs($aim_50);
                    $controlParams['lvl_aim3'] = abs($aim_80);
                    $controlParams['lvl_aim4'] = abs($aim_120);
                    write_log("Уровни целей: №1: $aim_30 №2: $aim_50 №3: $aim_80 №4: $aim_120 " . PHP_EOL, 7);
                    $first_state3 = false;
                }
                //                    if($aimLevel>$low){
                //                        $aimLevel=$low;
                //                        $aimBar=$curBar;
                //                    }
                if ($curBar > ($CP_bar + $sizeTime * TIME_DEPTH_AIMS / 100)) { // прошли глубину поиска отслеживания целей
                    write_log("Прошли глубину поиска отслеживания целей на баре $curBar -> фиксируем, что есть (" . $controlParams['P6aims'] . "%)" . PHP_EOL, 7);
                    //if(isset($controlParams['P6aims']))$controlParams['P6aims'].="%";
                    //$controlParams['Done']="DEPTH AIMS";
                    $State = 99;
                    break;
                }

                //                    if($calcP6reached!==false) {
                // if ($high > $realP6level) {
                if ($high >= $realP6level) { // fixed 11/01/22
                    write_log("Пробили уровень РЕАЛЬНОЙ Т6 ($realP6level) на баре . $curBar . -> фиксируем достигнутую цель (" . $controlParams['P6aims'] . "%) и  выход " . PHP_EOL, 7);
                    // if(isset($controlParams['P6aims']))$controlParams['P6aims'].="%";
                    //$controlParams['Done']="BREAKDOWN";
                    $State = 99;
                    break;
                }
                //                    }
                //                    else{ // дополнение 20201111 если уровень МП не достигался то стоп на уровень реальной Т6 не передвигаем
                //                        if ($high > $CP_level) {
                //                            write_log("Пробили уровень РАСЧЕТНОЙ Т6 ($CP_level) на баре $curBar -> фиксируем достигнутую цель (".$controlParams['P6aims']."%) и  выход " . PHP_EOL, 7);
                //                            // if(isset($controlParams['P6aims']))$controlParams['P6aims'].="%";
                //                            $State = 99;
                //                            break;
                //                        }
                //                    }


                //                    $low_=low($curBar+$baseBarNum,$v);
                $curAimLevel = ($CP_level - $low) / $sizeLevel * 100;
                $curAimNUM = 0; // какую цель достигли (1-4)
                for ($i = 1; $i <= 4; $i++) { // определяем номер достигнутой цели текущим баром
                    // if ($curAimLevel > $aimLevels[$i]) $curAimNUM = $i;
                    if ($curAimLevel >= $aimLevels[$i]) $curAimNUM = $i;
                    else break;
                }
                if (!isset($controlParams['P6aims']) || $controlParams['P6aims'] < $aimLevels[$curAimNUM]) {
                    write_log("Достигли $curAimNUM цели на баре $curBar (уровень $curAimNUM цели=" . $aimLevels[$curAimNUM] . ")" . PHP_EOL, 7);
                    $controlParams['Done'] = "AIM" . $curAimNUM;
                    $controlParams['P6aims'] = $aimLevels[$curAimNUM];
                    if ($curAimLevel == 4) {
                        //$controlParams['Done']="AIM 4";
                        $State = 99;
                    }
                    $aimBar = $curBar;
                }
                break;
            case 4: // * 4: пробой МП, отслеживаем Бамперный уровень пробоя МП (LVL_AIM_5_REV_30) и Уровень отмены целей пробоя МП
                // * ВСЕГДА по наихудшему - сначала пробит стоп
                $lastState = 4;
                if ($first_state4) { // попали сюда в первый раз - расчитываем и выводим в лог уровни целей
                    // ! Закомментировано,т.к. дублирует со строками начиная со стр. 154
                    $aim_rev_30 = round($CP_level - $aimLevels[5] * $sizeLevel / 100, $precision);
                    $aim_rev_50 = round($CP_level - $aimLevels[6] * $sizeLevel / 100, $precision);
                    $aim_rev_80 = round($CP_level - $aimLevels[7] * $sizeLevel / 100, $precision);
                    $aim_rev_150 = round($CP_level - $aimLevels[8] * $sizeLevel / 100, $precision);
                    $aim_rev_200 = round($CP_level - $aimLevels[9] * $sizeLevel / 100, $precision);
                    $aim_rev_200 = round($CP_level - $aimLevels[10] * $sizeLevel / 100, $precision);
                    $controlParams['lvl_aim5_rev30'] = abs($aim_rev_30);
                    $controlParams['lvl_aim6_rev50'] = abs($aim_rev_50);
                    $controlParams['lvl_aim7_rev80'] = abs($aim_rev_80);
                    $controlParams['lvl_aim8_rev100'] = abs($aim_rev_100);
                    $controlParams['lvl_aim9_rev150'] = abs($aim_rev_150);
                    $controlParams['lvl_aim10_rev200'] = abs($aim_rev_200);
                    write_log("Уровни целей: №5: $aim_rev_30 №6: $aim_rev_50 №7: $aim_rev_80 №8: $aim_rev_100 №9: $aim_rev_150 №10: $aim_rev_200" . PHP_EOL, 7);
                    $first_state3 = false;
                }
                if ($curBar > ($CP_bar + $sizeTime * TIME_DEPTH_AIMS / 100)) { // прошли глубину поиска отслеживания целей
                    write_log("Прошли глубину поиска отслеживания целей на баре . $curBar . -> фиксируем, что есть (" . ($controlParams['P6aims'] ?? "NA") . "%)" . PHP_EOL, 7);
                    //if(isset($controlParams['P6aims']))$controlParams['P6aims'].="%";
                    //$controlParams['Done']="DEPTH AIMS";
                    $State = 99;
                    break;
                }


                // ! Added 11/01/2022

                if (isset($controlParams['P6aims'])) {
                    // * Checking for already reached reverse AM aims level and setting current stop loss
                    for ($i = 5; $i <= 10; $i++) {
                        $k = $i - 4;
                        if ($controlParams['P6aims'] == $aimLevels[$i]) $curRevStopLevel = $revStopLevels[$k];
                        else {
                            $curRevStopLevel = $revStopLevels[0];
                            break;
                        }
                    }
                    write_log("Actual aim (reached by the moment of bar = . $curBar .)  is . '$controlParams[P6aims]' .   -> actual stop level is (" . $curRevStopLevel . "%)" . PHP_EOL, 7);

                    // ! Moved lower 25/01/22
                    // // * Checking if current stop level is not reached
                    // // $CurRevReturnLevel = ($low - $CP_level) / $sizeLevel * 100; // curbar's percantage made towards CP level
                    // $CurRevReturnLevel = ($CP_level - $low) / $sizeLevel * 100; // curbar's percantage made towards CP level
                    // // if ($CurRevReturnLevel <= $curRevStopLevel) {
                    // if ($CurRevReturnLevel >= $curRevStopLevel) {
                    //     write_log("Пробили уровень отмены целей пробоя МП/трейлинг стоп= . $curRevStopLevel . на баре . $curBar . -> фиксируем достигнутую цель (" . $controlParams['P6aims'] . "%) и  выход " . PHP_EOL, 7);
                    //     // if(isset($controlParams['P6aims']))$controlParams['P6aims'].="%";
                    //     //$controlParams['Done']="BREAKDOWN";
                    //     $State = 99;
                    //     break;
                    // }
                } else {
                    $curRevStopLevel = $revStopLevels[0];
                    $controlParams['P6aims'] = 0;
                    write_log("Actual aim (reached by the moment of bar = . $curBar .)  is unset -> actual stop level is (" . $curRevStopLevel . "%)" . PHP_EOL, 7);
                    // break;
                }

                // ! Moved here 25/01/22
                // * Checking if current stop level is not reached
                $CurRevReturnLevel = ($CP_level - $low) / $sizeLevel * 100; // curbar's percantage made towards CP level
                if ($CurRevReturnLevel >= $curRevStopLevel && $flag_RevBar != $curBar) {
                    write_log("Пробили уровень отмены целей пробоя МП/трейлинг стоп= . $curRevStopLevel . на баре . $curBar . -> фиксируем достигнутую цель (" . $controlParams['P6aims'] . "%) и  выход " . PHP_EOL, 7);
                    $State = 99;
                    break;
                }

                // $curRevAimLevel = ($high - $CP_level) / $sizeLevel * 100; // $curAimLevel - curbar's percantage made towards AMrev aims
                $curRevAimLevel = ($CP_level - $high) / $sizeLevel * 100; // $curAimLevel - curbar's percantage made towards AMrev aims
                $curRevAimNUM = 0; // какую цель достигли (5-10)
                for ($i = 5; $i <= 10; $i++) { // checking if/what aims are reached by the curbar
                    // if ($curRevAimLevel >= $aimLevels[$i]) // if the aim is reached on the curbar
                    if ($curRevAimLevel <= $aimLevels[$i]) { // if the aim is reached on the curbar
                        $curRevAimNUM = $i; //  new acchieved AimLevel is prepared to be compared with $controlParams['P6aims']
                        write_log("Reverse AM aim  . $aimLevels[$i] . is reached on bar = " . $curBar . " curRevAimNum =" . $i . " " . PHP_EOL, 7);
                    } else break; // if no aim is reached on the curbar - pass to the next
                }

                // if (!isset($controlParams['P6aims']) || $controlParams['P6aims'] < $aimLevels[$curRevAimNUM]) { // ! fixed 25/01/22
                if (!isset($controlParams['P6aims']) || $controlParams['P6aims'] > $aimLevels[$curRevAimNUM]) {
                    write_log("Достигли $curRevAimNUM цели переворота МП на баре $curBar (уровень $curRevAimNUM цели=" . $aimLevels[$curRevAimNUM] . ")" . PHP_EOL, 7);
                    $controlParams['Done'] = "AIM" . $curRevAimNUM;
                    $controlParams['P6aims'] = $aimLevels[$curRevAimNUM];
                    if ($curRevAimLevel == 10) {
                        //$controlParams['Done']="AIM 4";
                        $State = 99;
                    }
                    $aimBar = $curBar;
                }
                break;
            case 99:
                break;
        }
        if (!$wasEvents) $curBar++;
    }
    if (isset($controlParams['P6dev']) && $controlParams['P6dev'] != "X" && $controlParams['P6dev'] != "rchd" && $controlParams['P6dev'] != "unrchd") $controlParams['P6dev'] .= "%";
    if (isset($controlParams['P6aims']) && $controlParams['P6aims'] != "0") $controlParams['P6aims'] .= "%";

    // added 2022-04-04: Блок определения параметров преконтроля
    // 1. определяем conf_t4 (в алгоритмах с этим параметром, походу, баг, находим заново :)
    if (isset($controlParams['ApprReachedAt'])) { // если было достижение уровня подхода
        $lvl_preapp = $lvl_preapp_orig = $CP_level - $sizeLevel * LVL_PREAPPROACH / 100; // - preapproach level - from where this level is reached, precontrol parameters are calculated. Take a note thats the level is an absolute number.
        $controlParams['p4xPreap@'] = ($lvl_preapp > high($model['t4'] + $baseBarNum, $v)) ? 0 : 1; //0 если уровень т.4 дальше от расчётной т.6, чем [LVL_PREAPPROACH] иначе 1
        $tmp___t4_level = high($model['t4'] + $baseBarNum, $v);
        $tmp___appr_level = high($controlParams['ApprReachedAt'] + $baseBarNum, $v);
        if ($tmp___t4_level > $tmp___appr_level) {
            // на баре подхода high ниже чем уровень t4
            file_put_contents("tmp_log/_______debug_" . shortFileName(__FILE__) . "_(" . __LINE__ . ").json", json_encode(get_defined_vars(), JSON_PARTIAL_OUTPUT_ON_ERROR)); // for debug only
        } else {
            $conf_t4 = 0; // по идее, false остаться не должно, "расширенный" уровень подхода по любому есть :)
            $t4_level = high($model['t4'] + $baseBarNum, $v); // уровень т4, ищем бар повторно достигнувший его после бара т5 (подтвердивщий т4)
            //если бар т.5 является баром, подтвердившим т.4, то трейд не ведется.
            for ($i = $model['t4'] + 1; $i <= $controlParams['ApprReachedAt']; $i++) { // "<=" т.к. могут быть варианты, когда достижение уровня подхода и подтверждение т4 происходит на одном баре
                if (high($i + $baseBarNum, $v) >= $t4_level) {
                    $conf_t4 = $i;
                    break;
                }
            }
            //$res['_____id_aim']="id: ".$model['id']." aim: $title";
            //$res['_____ApprReachedAt']=$controlParams['ApprReachedAt'];
            assert($conf_t4 > 0); //,"conf_t4 not found !"); // убеждаемся, что conf_t4 нашли
            // теперь ищем бар на котором достигли "расширенного" уровня подхода
            if ($lvl_preapp < $t4_level) $lvl_preapp = $t4_level; // когда lvl_preapp лежит дальше от т.6,чем уровень т.4, смещаем lvl_preapp на уровень т.4
            $lvl_preapp_bar = 0; // бар, на котором достигли "расширенного" уровня подхода
            for ($i = $conf_t4; $i <= $controlParams['ApprReachedAt']; $i++) { // "<=" т.к. могут быть варианты, когда достижение "расширенного" и "простого" уровня подхода происходит на одном баре
                if (high($i + $baseBarNum, $v) >= $lvl_preapp) {
                    $lvl_preapp_bar = $i;
                    break;
                }
            }
            assert($lvl_preapp_bar > 0); //,"lvl_preapp_bar not found !"); // убеждаемся, что lvl_preapp_bar нашли
            // определяем параметр _llappr<aim>
            $controlParams['_llappr@'] = round(($controlParams['ApprReachedAt'] - $lvl_preapp_bar) / $sizeTime, $precision);
            // определяем параметр _ll4appr<aim>
            $controlParams['_ll4appr@'] = round(($controlParams['ApprReachedAt'] - $lvl_preapp_bar) / ($model['t4'] - $model['t2']), $precision);
            // определяем параметр _lvlappr<aim>
            $lvl_cur_preapp = 999999999; // наиболее удалённая от т.6 цена на участке преконтроля. Обновляется в конце проверки конкретного бара на участке преконтроля для сравнения последующих баров с уровнями, достигнутыми на предыдущих барах.
            $controlParams['_lvlappr@'] = 0; // значение по умолчанию, если никакие уровни не достигнуты
            for ($n = $lvl_preapp_bar + 1; $n < $controlParams['ApprReachedAt']; $n++) { // от времени начала преконтроля до времени достижения Уровня подхода к МП ведём преконтроль
                $low_ = low($n + $baseBarNum, $v);
                if (
                    $level_bumper < $lvl_preapp  // чтобы значение бамперного уровня в параметре имело смысл, рассматривается только случай,когда бамперный уровень дальше уровня преконтроля. При значенях по умолчанию LVL_BUMPER2 = LVL_PREAPPROACH = 15%, только в случае когда бамперный уровень 25%. Если же значение LVL_PREAPPROACH будет = 25%-29%,то значение бамперного уровня не будет попадать в этот параметр вообще.

                    // && $level_bumper < $lvl_cur_preapp // наиболее удаленный от т.6 бар среди ранее достигших целей преконтроля на участке преконтроля должен быть дальше рассматриваемого
                    // ! Не понимаю,откуда взялось это условие и с кодом не вполне совпадает по смыслу.В таком виде оно просто прекращает отслеживание папраметров после достижения уровня бампера,что не нужно... (25.02.24)
                ) {
                    if (
                        $low_ <= $level_bumper
                        && $low_ <= $lvl_cur_preapp
                    ) {
                        $controlParams['_lvlappr@'] = round(($CP_level - $low_) / $sizeLevel, $precision);
                        $lvl_cur_preapp = $low_;
                    }
                }
                if ( // В этом блоке не учитывается условие $level_bumper < $lvl_preapp, т.е. если цена уходит ниже уровня цели, то уже не важно взаимное пложение бамперного уровня и уровня LVL_PREAPPROACH. Параметр в любом случае надо вычислить  (25.02.24)
                    $low_ <= $aim_30
                    && $low_ < $lvl_cur_preapp
                ) {
                    $controlParams['_lvlappr@'] = round(($CP_level - $low_) / $sizeLevel, $precision);
                    $lvl_cur_preapp = $low_;
                }
            }

            // из ТЗ по блоку 3 Расчёт всех параметров, о которых речь идёт ниже осуществляется только если уровень т.4 лежит дальше по уровням от т.6, чем [APPROACH] (не путать с LVL_PREAPPROACH).
            if (
                $t4_level < $lvl_appr_orig &
                $model['t2'] + $baseBarNum > 0
            )
                $controlParams = calc_20230814_part2(
                    $model,
                    $controlParams,
                    compact('baseBarNum', 'curBar', 'sizeTime', 'sizeLevel', 'CP_level', 'level_approach', 'lvl_appr_orig', 'CP_bar', 'lvl_preapp', 'lvl_preapp_orig'),
                    false
                );
        } // t4 дальше от P6 чем appr_lvl
        // ТЗ от 2023-08-14 второй блок новых параметров

    } // если было достижение уровня подхода - заверщение блока параметров _llappr<aim>, _ll4appr<aim>, _lvlappr<aim>


    // added 2022-08-23: Блок определения ДОПОЛНИТЕЛЬНЫХ параметров преконтроля
    if (isset($controlParams['ApprReachedAt'])) {
        if ($controlParams['ApprReachedAt'] < round($CP_bar, 0)) $controlParams['_llappr@ba'] = 0; // касание уровня подхода произошло до расчетной шестой
        else $controlParams['_llappr@ba'] = 1;
    }
    if (isset($conf_t4)) {
        $X_bar = round(($CP_bar + $conf_t4) / 2, 0); // середина отрезка от бара, подтвердлившено t4 и P6
        if ($conf_t4 <= $CP_bar) { // далее параметры считаем, только если есть подтверждение t4 и если подтверждение т4 не правее, чем CP_level
            if (!isset($controlParams['ApprReachedAt']) || $controlParams['ApprReachedAt'] > $X_bar) { // рассчитываем только если касание уровня подхода не было до бара X (включителдьно)
                if (($X_bar + $baseBarNum) < $CNT) { //Если бар X еще не появился, то параметр не рассчитывается
                    $controlParams['_lvlappr@half'] = round((high($X_bar + $baseBarNum, $v) - ($CP_level - $sizeLevel)) / $sizeLevel, 6);
                }
            }
        }
        if (isset($controlParams['ApprReachedAt'])) {
            if ($controlParams['ApprReachedAt'] <= $X_bar) $controlParams['_llappr@halfba'] = 1; // В случае, если до или в момент достижения точки(момента) X уровень подхода к уровню т.6 оказывается достигнут, параметр получает значение 1.
            else $controlParams['_llappr@halfba'] = 0;
            // 2023-09-01 правильный расчет параметра _lvlappr<P6>halfba (раньше под этим именем был параметр _lvlappr<P6>halfba, который теперь называется NEXT_llappr<P6>halfba
            if ($controlParams['_llappr@halfba'] == 1)
                $controlParams['_lvlappr@halfba'] = 0;
            else
                $controlParams['_lvlappr@halfba'] = ($CP_level - high($X_bar + $baseBarNum, $v)) / $sizeLevel;
        }
    }


    // added 2022-09-08 : Блок определения NEXT-параметров (какие будут значения параметров + ответы нейронок, если на след.баре будет достигнут уровень подхода
    // выполняется только для РТ
    if (!isset($controlParams['ApprReachedAt']) && $isNextParamsNeeded) { // достигли конца графика и нужно посчитать NEXT-параметры
        //file_put_contents("tmp_log/_______debug_".__FUNCTION__."_".__LINE__.".json",json_encode(get_defined_vars(),JSON_PARTIAL_OUTPUT_ON_ERROR)); // for debug only
        // блок TRADE-параметров для дальнейше передачи MT4-советнику, если стработает сет
        $controlParams['TRADE']['CP_lvl'] = $CP_level;
        $controlParams['TRADE']['sizeLevel'] = $sizeLevel;
        $controlParams['TRADE']['sizeTime'] = round($sizeTime, 3); // размер модели по времени
        $controlParams['TRADE']['appr_lvl'] = $level_approach;
        $controlParams['TRADE']['preappr_lvl'] = $CP_level - $sizeLevel * LVL_PREAPPROACH / 100; //$controlParams['lvl_preappr'];
        //$controlParams['TRADE']['appr_proc']=round(($CP_level-$level_approach)/$sizeLevel*100,2);
        $controlParams['TRADE']['t4'] = high($model['t4'] + $baseBarNum, $v);
        //$controlParams['TRADE']['t4_proc']=round(($CP_level-high($model['t4'] + $baseBarNum, $v))/$sizeLevel*100,2);
        if (isset($model['t5'])) {
            $controlParams['TRADE']['t5'] = low($model['t5'] + $baseBarNum, $v);
            //  $controlParams['TRADE']['t5_proc']=round(($CP_level-low($model['t5'] + $baseBarNum, $v))/$sizeLevel*100,2);
        } else {
            $controlParams['TRADE']['t5'] = 0;
            //$controlParams['TRADE']['t5_proc']=0;
        }
        $controlParams['TRADE']['received'] = $Chart[$CNT - 1]['open_time'] / 1000; // время открытия крайнего правого бара
        $controlParams['TRADE']['formed_at'] = $Chart[$first_bar_num + $baseBarNum]['open_time'] / 1000; // время вормирования модели + 1 бар
        $controlParams['TRADE']['close'] = close($CNT - 1, $v);
        $controlParams['TRADE']['CP_dist'] = round($curBar - $CP_bar, 3); // текущее расстояние от CP до последнего бара

        $min_ = 999999999;
        $max_ = -999999999;
        for ($i = $first_bar_num; $i < ($CNT - $baseBarNum); $i++) { // ищем минимальный и максимальный уровень до тек.момента, используется для фильтров
            $low_ = low($i + $baseBarNum, $v);
            $high_ = high($i + $baseBarNum, $v);
            if ($low_ < $min_) $min_ = $low_;
            if ($high_ > $max_) $max_ = $high_;
        }
        $controlParams['TRADE']['min'] = $min_;
        $controlParams['TRADE']['max'] = $max_;


        $controlParams['NEXT']['ApprReachedAt'] = $CNT - $baseBarNum; // устанавливаем индекс для следующего (отсутсвующего пока) бара
        // за основу взят блок (выше) - для ситуации с достижением approach, но данный блок выполняется для предположения, что approach БУДЕТ достигнут на следующем баре (которого пока нет)
        $conf_t4 = 0; // по идее, false остаться не должно, "расширенный" уровень подхода по любому есть :)
        $t4_level = high($model['t4'] + $baseBarNum, $v); // уровень т4, ищем бар повторно достигнувший его после бара т4 (подтвердивщий т4)
        for ($i = $model['t4'] + 1; $i <= ($CNT - $baseBarNum); $i++) { // перебор всех баров после t5 + один NEXT бар (цена в котором идет который идет от close последнего существующего до approach_level)
            if ($i == ($CNT - $baseBarNum)) $conf_t4 = $i; // если раньше подтверждения t4 не случилось, значит считаем, что подтвердили на NEXT баре, на котором достигли и approach_level
            else if (high($i + $baseBarNum, $v) >= $t4_level) {
                $conf_t4 = $i;
                break;
            }
        }
        // теперь ищем бар на котором достигли "расширенного" уровня подхода
        $lvl_preapp = $CP_level - $sizeLevel * LVL_PREAPPROACH / 100; // - preapproach level - from where this level is reached, precontrol parameters are calculated. Take a note thats the level is an absolute number.
        if ($lvl_preapp < $t4_level) $lvl_preapp = $t4_level; // когда lvl_preapp лежит дальше от т.6,чем уровень т.4, смещаем lvl_preapp на уровень т.4
        $lvl_preapp_bar = 0; // бар, на котором достигли "расширенного" уровня подхода
        for ($i = $conf_t4; $i <= ($CNT - $baseBarNum); $i++) { // "<=" т.к. могут быть варианты, когда достижение "расширенного" и "простого" уровня подхода происходит на одном баре
            if ($i == ($CNT - $baseBarNum)) $lvl_preapp_bar = $i; // если раньше расширенного подхода не случилось, значит считаем, что достигли его только на NEXT баре (где и approach_level)
            else if (high($i + $baseBarNum, $v) >= $lvl_preapp) {
                $lvl_preapp_bar = $i;
                break;
            }
        }
        // определяем _ll параметры, учитывая, что уровень подхода достигнут только на несуществующем пока "NEXT-баре"
        // определяем параметр _llappr<aim>
        $controlParams['NEXT']['_llappr@'] = round(($CNT - $baseBarNum - $lvl_preapp_bar) / $sizeTime, $precision);
        // определяем параметр _ll4appr<aim>
        $controlParams['NEXT']['_ll4appr@'] = round(($CNT - $baseBarNum - $lvl_preapp_bar) / ($model['t4'] - $model['t2']), $precision);
        // определяем параметр _lvlappr<aim>
        $lvl_cur_preapp = 999999999; // наиболее удалённая от т.6 цена на участке преконтроля. Обновляется в конце проверки конкретного бара на участке преконтроля для сравнения последующих баров с уровнями, достигнутыми на предыдущих барах.
        $controlParams['NEXT']['_lvlappr@'] = 0; // значение по умолчанию, если никакие уровни не достигнуты
        for ($n = $lvl_preapp_bar + 1; $n < ($CNT - $baseBarNum); $n++) { // от времени начала преконтроля до времени достижения Уровня подхода к МП ведём преконтроль
            $low_ = low($n + $baseBarNum, $v);
            if (
                $level_bumper < $lvl_preapp  // чтобы значение бамперного уровня в параметре имело смысл, рассматривается только случай,когда бамперный уровень дальше уровня преконтроля. При значенях по умолчанию LVL_BUMPER2 = LVL_PREAPPROACH = 15%, только в случае когда бамперный уровень 25%. Если же значение LVL_PREAPPROACH будет = 25%-29%,то значение бамперного уровня не будет попадать в этот параметр вообще.
                // && $level_bumper < $lvl_cur_preapp // наиболее удаленный от т.6 бар среди ранее достигших целей преконтроля на участке преконтроля должен быть дальше рассматриваемого
                //! не понятно как код связан с этим комментарием, данное требование реализовано ниже как  && $low_ <= $lvl_cur_preapp (25.02.24)
            ) {
                if (
                    $low_ <= $level_bumper
                    && $low_ <= $lvl_cur_preapp
                ) {
                    $controlParams['NEXT']['_lvlappr@'] = round(($CP_level - $low_) / $sizeLevel, $precision);
                    $lvl_cur_preapp = $low_;
                }
            }
            if (
                $low_ <= $aim_30
                && $low_ < $lvl_cur_preapp
            ) {
                $controlParams['NEXT']['_lvlappr@'] = round(($CP_level - $low_) / $sizeLevel, $precision);
                $lvl_cur_preapp = $low_;
            }
        }

        // аналогично ДОПОЛНИТЕЛЬНЫЕ параметры преконтроля для NEXT бара
        if (($CNT - $baseBarNum) < round($CP_bar, 0)) $controlParams['NEXT']['_llappr@ba'] = 0; // касание уровня подхода произошло до расчетной шестой
        else $controlParams['NEXT']['_llappr@ba'] = 1;

        $X_bar = round(($CP_bar + $conf_t4) / 2, 0); // середина отрезка от бара, подтвердлившено t4 и P6

        if ($conf_t4 < $CP_bar) { // проверка, что есть подтверждение t4 здесь убрана, так как для NEXT всегда есть подтверждение, как минимум на NEXT=баре
            if (($CNT - $baseBarNum) > $X_bar) { // рассчитываем только если касание уровня подхода не было до бара X (включителдьно)
                if (($X_bar + $baseBarNum) < ($CNT + 1)) { //Если бар X еще не появился (на Next-баре), то параметр не рассчитывается
                    $controlParams['NEXT']['_lvlappr@half'] = (high($X_bar + $baseBarNum, $v) - ($CP_level - $sizeLevel)) / $sizeLevel;
                }
            }
        }
        if (($CNT - $baseBarNum) <= $X_bar) $controlParams['NEXT']['_llappr@halfba'] = 1; // В случае, если до или в момент достижения точки(момента) X уровень подхода к уровню т.6 оказывается достигнут, параметр получает значение 1.
        else $controlParams['NEXT']['_llappr@halfba'] = 0;
        // 2023-09-01 правильный расчет параметра NEXT_lvlappr<P6>halfba (раньше под этим именем был параметр NEXT_lvlappr<P6>halfba, который теперь называется NEXT_llappr<P6>halfba
        if ($controlParams['NEXT']['_llappr@halfba'] == 1)
            $controlParams['NEXT']['_lvlappr@halfba'] = 0;
        else
            $controlParams['NEXT']['_lvlappr@halfba'] = ($CP_level - high($X_bar + $baseBarNum, $v)) / $sizeLevel;

        // 2023-09-01 новая порция NEXT-параметров
        if (!isset($lvl_preapp_orig)) {
            $lvl_preapp = $lvl_preapp_orig = $CP_level - $sizeLevel * LVL_PREAPPROACH / 100;
            if ($lvl_preapp < $t4_level) $lvl_preapp = $t4_level;
        }
        if (
            $t4_level < $lvl_appr_orig &
            $model['t2'] + $baseBarNum > 0
        )
            $controlParams = calc_20230814_part2(
                $model,
                $controlParams,
                compact('baseBarNum', 'curBar', 'sizeTime', 'sizeLevel', 'CP_level', 'level_approach', 'lvl_appr_orig', 'CP_bar', 'lvl_preapp', 'lvl_preapp_orig'),
                true
            );
    } // завершен блок расчета NEXT-параметров

    // added 2023-06-15 : определение NEXT-параметров для pst (если в режиме онлайн и дошли до последнего бара
    // который по времени (реальному) является последним, при этом было достижение Approach level
    if (($curBar + $baseBarNum) == $CNT && NEXT_NEEDED && isset($controlParams['ApprReachedAt'])) {
        //определяем, правда ли это последний бар опираясь на текущее время
        $now_uts = date_create()->getTimestamp();
        $tf_ = ($Chart[count($Chart) - 1]['close_time'] - $Chart[count($Chart) - 1]['open_time']) / 1000;
        if (($Chart[count($Chart) - 1]['close_time'] / 1000 + $tf_) > $now_uts) // если следующий после конца чарта бар еще не завершен
            $controlParams = calc_pst_params($v, $model['t4'], $sizeTime, $sizeLevel, $controlParams, $baseBarNum, $curBar, $calcP6reached, $realP6bar, $realP6level, $CP_bar, $CP_level, true);
    }

    return ($controlParams);
}

$debug_protoCnt = 0;
function calc_20230814_part2($model, $controlParams, $vars, $isNEXTbar = false)
{
    global $Chart, $debug_protoCnt;
    // просто для удобства в виде отдельной процедуры - расчет параметров (2 этоп ТЗ от 14.08.2023- новые 60 параметров)
    // также, как и calc_pst_params вызывается 2 раза для расчета параметров в случае достижения Approach и для расчета NEXT пораметров
    // если установлен в true $isNEXTbar, то значит в онлайн-работе дошли до последнего бара и нужно посчитать NEXT-параметр а не обычный
    $NEXTprefix = $isNEXTbar ? "NEXT" : "";
    //$fh = fopen("____debug.txt", "a");
    $CP_level = round($vars['CP_level'], 3); // требование в ТЗ (устно обсуждали) - при расчете использовать округление CP_level дло трех знаков
    $CP_bar = round($vars['CP_bar'], 3);
    $baseBarNum = $vars['baseBarNum']; // для удобства - в переменную
    $t4_level = high($model['t4'] + $baseBarNum, $v = $model['v']);
    $lastBarBeforeAppr = $isNEXTbar ? count($Chart) - 1 - $baseBarNum : $controlParams['ApprReachedAt'] - 1; # конец диапазона проверки пробоя ЛЦ
    // в случае, если это NEXT параметры, то может не быть подтверждения Т4, PreApproach - поэтому пытаемся найти заново (в процедуру не передавали
    // и если не нашли, то считаем, что она на "несуществующем пока" NEXT баре
    // определяем бар на котором произошло достижение первоначального LVL_PREAPPROACH
    $appr_orig_bar = false; // бар на котором был достигнут первоначальный уровень (до возможной перестановки на Т4) PreApproach
    $preapp_bar = false; // аналогично PreApproach и подтверждение t4
    $conf_t4bar = false;
    $appr_bar = false;
    $preapp_orig_bar = false;

    for ($i = $model['t4'] + 1; $i <= $lastBarBeforeAppr; $i++) {
        if (high($i + $baseBarNum, $v) >= $vars['lvl_appr_orig'] && $appr_orig_bar === false) $appr_orig_bar = $i;
        if (high($i + $baseBarNum, $v) >= $vars['lvl_preapp'] && $preapp_bar === false) $preapp_bar = $i;
        if (high($i + $baseBarNum, $v) >= $t4_level && $conf_t4bar === false) $conf_t4bar = $i;
        if (high($i + $baseBarNum, $v) >= $vars['level_approach'] && $appr_bar === false) $appr_bar = $i;
        if (high($i + $baseBarNum, $v) >= $vars['lvl_preapp_orig'] && $preapp_orig_bar === false) $preapp_orig_bar = $i;
    }

    if ($appr_orig_bar === false) // здесь и далее в блоке: если не найдено достижение уровня - значит на баре Approach
        $appr_orig_bar = $lastBarBeforeAppr + 1;
    if (!$preapp_bar)
        $preapp_bar = $lastBarBeforeAppr + 1;
    if (!$conf_t4bar)
        $conf_t4bar = $lastBarBeforeAppr + 1;
    if (!$appr_bar)
        $appr_bar = $lastBarBeforeAppr + 1;
    if (!$preapp_orig_bar)
        $preapp_orig_bar = $lastBarBeforeAppr + 1;

    //При достижении [APPROACH] проверяется наличие пробоя ЛЦ МП на участке от бара подтверждения т.4 [conf_t4] (включительно) до бара [APPROACH] (не включая)
    $LC = calcLC($model, $baseBarNum);

    $lcBrokenBar = false;
    $lcFirstBrokenLevel = false;
    for ($bar = $conf_t4bar; $bar <= $lastBarBeforeAppr; $bar++) {
        $lcLevel = $LC['level'] + ($bar - $model['t2']) * $LC['angle'];
        if (high($bar + $baseBarNum, $model['v']) >= $lcLevel) {
            if (!$lcBrokenBar) { // берем первое пересечение ЛЦ
                $lcBrokenBar = $bar;
                $lcFirstBrokenLevel = $lcLevel;
            }
            break;
        }
    }
    $controlParams[$NEXTprefix . '_c4preapAL@'] = $lcBrokenBar ? 1 : 0;
    if ($lcBrokenBar) { // если пробоя ЛЦ не было, то остальные параметры не рассчитываем
        // п.2
        if (($CP_bar - $lcBrokenBar) != 0)
            $controlParams[$NEXTprefix . '_llc4preapAL@'] = ($lcBrokenBar - $conf_t4bar) / ($CP_bar - $lcBrokenBar);
        // п.3.1
        if (($CP_level - $lcFirstBrokenLevel) != 0)
            $controlParams[$NEXTprefix . '_lvlconf4AL@'] = ($lcFirstBrokenLevel - $t4_level) / ($CP_level - $lcFirstBrokenLevel);
        // п.3.2
        if (($CP_bar - $conf_t4bar) != 0)
            $controlParams[$NEXTprefix . '_llALap@'] = ($lastBarBeforeAppr + 1 - $lcBrokenBar) / ($CP_bar - $conf_t4bar);
        // п.3.3
        if (($CP_level - $t4_level) != 0)
            $controlParams[$NEXTprefix . '_lvlALap@'] = ($vars['level_approach'] - $lcFirstBrokenLevel) / ($CP_level - $t4_level);
        // п.4
        if (($CP_bar - $conf_t4bar) != 0)
            $controlParams[$NEXTprefix . '_llAL@'] = ($CP_bar - $lcBrokenBar) / ($CP_bar - $conf_t4bar);
        // п.5
        if (($CP_level - $t4_level) != 0)
            $controlParams[$NEXTprefix . '_lvlAL@'] = ($CP_level - $lcFirstBrokenLevel) / ($CP_level - $t4_level);
        // п.6
        $controlParams[$NEXTprefix . '_ALcor@'] = 0; // предварительно. Если что-то найдем, то поменяем
        $A_B_pairs = []; // массив пар A-B
        for ($i = $lcBrokenBar; $i <= $lastBarBeforeAppr; $i++) {
            if (isExtremumHigh($i + $baseBarNum, $v)) { // кандидат в А - ищем для него самый удаленный противонаправленный экстремум
                $maxDist = 0;
                $A_level = high($i + $baseBarNum, $v);
                $B_bar = false;
                for ($j = $i + 1; $j <= $lastBarBeforeAppr; $j++) {
                    if (isExtremumLow($j + $baseBarNum, $v)) { // кандидат в B для текущего A
                        if (($dist = ($A_level - low($j + $baseBarNum, $v))) > $maxDist) {
                            $maxDist = $dist;
                            $B_level = low($j + $baseBarNum, $v);
                            $B_bar = $j;
                        }
                    }
                }
                if ($B_bar) { // пара A-B нашлась - запоминаем ее в массив (вдруг, будет несколько
                    $A_B_pairs[] = ['A' => $i, 'B' => $B_bar, 'A_level' => $A_level, 'dist' => $A_level - $B_level];
                }
            }
        }
        if ($A_B_pairs) { // только если нашлись пары A-B
            $controlParams[$NEXTprefix . '_ALcor@'] = 1;
            // ишем самую большую пару A-B
            $maxDist = 0;
            $bestABindex = 0;
            for ($i = 0; $i < count($A_B_pairs); $i++)
                if ($A_B_pairs[$i]['dist'] > $maxDist) {
                    $maxDist = $A_B_pairs[$i]['dist'];
                    $bestABindex = $i;
                }
            // п.7
            if (($CP_bar - $conf_t4bar) != 0)
                $controlParams[$NEXTprefix . '_llALcorsize@'] = ($A_B_pairs[$bestABindex]['B'] - $A_B_pairs[$bestABindex]['A']) / ($CP_bar - $conf_t4bar);
            // п.8
            if (($CP_level - $t4_level) != 0)
                $controlParams[$NEXTprefix . '_lvlALcorsize@'] = $A_B_pairs[$bestABindex]['dist'] / ($CP_level - $t4_level);
            // п.9
            if (($CP_bar - $conf_t4bar) != 0)
                $controlParams[$NEXTprefix . '_llALcordist@'] = ($CP_bar - $A_B_pairs[$bestABindex]['A']) / ($CP_bar - $conf_t4bar);
            // п.10
            if (($CP_level - $t4_level) != 0)
                $controlParams[$NEXTprefix . '_lvlALcordist@'] = ($CP_level - $A_B_pairs[$bestABindex]['A_level']) / ($CP_level - $t4_level);
            // п.11
            $controlParams[$NEXTprefix . '_llALcorpos@'] = ($CP_bar - $A_B_pairs[$bestABindex]['A']) / $vars['sizeTime'];
            // п.12
            $controlParams[$NEXTprefix . '_lvlALcorpos@'] = ($CP_level - $A_B_pairs[$bestABindex]['A_level']) / $vars['sizeLevel'];
        }
    } // был пробой - посчитали параметры второй части

    // далее часть 3 ТЗ от 14авг.23
    //    !!!  условие preap_app_llcond включает 2 условия:
    //1.	 расчёт всех параметров, о которых речь идёт ниже осуществляется только если LVL_PREAPPROACH (или в случае conf_preap_lvlrule уровень т.4)
    // достигнут раньше, чем бар Уровня подхода к т.6 [APPROACH].
    //2.	Если бар conf_t4 совпадает с баром достижения (первоначального) LVL_PREAPPROACH или лежит ПОЗЖЕ(испр.2023-09-06) него,
    //расчёт нижеприведенных параметров не осуществляется.
    if ($preapp_bar < $appr_bar && $conf_t4bar < $preapp_orig_bar && ($controlParams['_lvlappr@'] ?? 0)) {
        $prelead_bar = false;
        $prelead_level = 999999999;
        $debug_protoCnt++;
        //fwrite($fh, "\nproto_$debug_protoCnt id:{$model['id']} CP_bar: {$vars['CP_bar']} sizeTime: {$vars['sizeTime']} curBar: {$vars['curBar']} LBBA: {$lastBarBeforeAppr} preappr_orig_bar: $preapp_orig_bar appr_bar: $appr_bar");
        $controlParams[$NEXTprefix . '_@proto'] = 0; // предварительно 0, если найдем экстремум, то поставим 1
        for ($i = $preapp_orig_bar + 1; $i < $appr_bar; $i++) {
            if (isExtremumLow($i + $baseBarNum, $v))
                if (($lvl = low($i + $baseBarNum, $v)) < $prelead_level) {
                    $prelead_bar = $i;
                    $prelead_level = $lvl;
                    $controlParams[$NEXTprefix . '_@proto'] = 1;
                }
        }
        $proto6_bar = false;
        if ($prelead_bar) {
            $proto6_level = -999999999;
            for ($i = $preapp_orig_bar; $i < $prelead_bar; $i++) {
                if (isExtremumHigh($i + $baseBarNum, $v))
                    if (($lvl = high($i + $baseBarNum, $v)) > $proto6_level) {
                        $proto6_bar = $i;
                        $proto6_level = $lvl;
                    }
            }
        }
        if ($proto6_bar) {
            $controlParams[$NEXTprefix . '_llprotosize@'] = ($prelead_bar - $proto6_bar) / $vars['sizeTime'];
            $controlParams[$NEXTprefix . '_lvlprotosize@'] = ($proto6_level - $prelead_level) / $vars['sizeLevel'];
            $controlParams[$NEXTprefix . '_llprotopos@'] = ($CP_bar - $proto6_bar) / $vars['sizeTime'];
            $controlParams[$NEXTprefix . '_lvlprotopos@'] = ($CP_level - $proto6_level) / $vars['sizeLevel'];
        }
    }

    return ($controlParams);
}

function calcLC($model, $baseBarNum)
{ // определение ЛЦ'
    $v = $model['v'];
    return ([
        'bar' => $model['t2'] + $baseBarNum,
        'level' => high($model['t2'] + $baseBarNum, $v),
        'angle' => (high($model['t4'] + $baseBarNum, $v) - high($model['t2'] + $baseBarNum, $v)) / ($model['t4'] - $model['t2'])
    ]);
}

function calc_pst_params($v, $t4_bar, $sizeTime, $sizeLevel, $controlParams, $baseBarNum, $curBar, $calcP6reached, $realP6bar, $realP6level, $CP_bar, $CP_level, $isNEXTbar = false)
{
    // процедура вызывается при достижении бамперного уровня (1 или 2 в зависимости от достижения уровня МП ранее)
    // $curBar - это номер бара, пробившего бамперный уровень

    if ($isNEXTbar && isset($controlParams['pst_appr@bmp'])) // если были запрошены NEXT параметры (дошли до конца чарта), а есть посчитанные обычные pst-параметры, то NEXT не считаем
        return ($controlParams);

    // если установлен в true $isNEXTbar, то значит в онлайн-работе дошли до последнего бара и нужно посчитать NEXT-параметр а не обычный
    $NEXTprefix = $isNEXTbar ? "NEXT" : "";

    //if($isNEXTbar) {
    //    file_put_contents("tmp_log/_______debug_" . shortFileName(__FILE__) . "_(" . __LINE__ . ").json", json_encode(get_defined_vars(), JSON_PARTIAL_OUTPUT_ON_ERROR)); // for debug only
    //    die_();
    //}
    // 1. группа pst_appr{P6}bmp
    $controlParams[$NEXTprefix . 'pst_appr@bmp'] = ($curBar - $controlParams['ApprReachedAt']) / $sizeTime;

    // 2. группа pst_{P6}bmp
    if ($calcP6reached)
        $controlParams[$NEXTprefix . 'pst_@bmp'] = ($curBar - $calcP6reached) / $sizeTime;

    // 3. группа pst_P4_{P6}bmp
    $controlParams[$NEXTprefix . 'pst_P4_@bmp'] = ($curBar - $t4_bar) / $sizeTime;

    // 4. и 5. - группа pstex_conf_R{P6} и pstex_R{P6}bmp

    $conf_t4 = 0; // ищем точку подтверждения т4
    $t4_level = high($t4_bar + $baseBarNum, $v); // уровень т4, ищем бар повторно достигнувший его после бара т5 (подтвердивщий т4)
    //если бар т.5 является баром, подтвердившим т.4, то трейд не ведется.
    for ($i = $t4_bar + 1; $i <= $controlParams['ApprReachedAt']; $i++) {
        if (high($i + $baseBarNum, $v) >= $t4_level) {
            $conf_t4 = $i;
            break;
        }
    }
    // highExtremum - противонаправленный t3, lowExtremum - сонаправленный
    $lead_highExtremums = []; // массив high-экстремумов(противонаправленных т3) - из 2х элементов: номер бара (в рамках модели) + уровень
    $lead_lowExtremums = []; // массив low-экстремумов(сонаправленных т3)
    // highExtremum - противонаправленный t3, lowExtremum - сонаправленный
    $fall_highExtremums = []; // массив high-экстремумов(противонаправленных т3) - из 2х элементов: номер бара (в рамках модели) + уровень
    $fall_lowExtremums = []; // массив low-экстремумов(сонаправленных т3

    if ($conf_t4) { // по идее, остаться $conf_t4 нулевым не может, так как по любому, если было достижение уровня подхода, то было и подтверждение Т4 (хотя бы на баре Approach)

        // подсчет экстремумов для  pstex_conf_R{P6} (параллельно формируем массив lead-экстремумов для pst_lead_RP6
        $lowExtremumCnt = $highExtremumCnt = 0;


        for ($i = $conf_t4; $i < $realP6bar; $i++) {
            $isHighExtremum = ($v == 'low') ? isExtremum($i + $baseBarNum, 'high') : isExtremum($i + $baseBarNum, 'low');
            $isLowExtremum = ($v == 'low') ? isExtremum($i + $baseBarNum, 'low') : isExtremum($i + $baseBarNum, 'high');
            if ($i == $conf_t4) { //на баре, подтверждающем т.4  (conf_t4) учитывается только экстремум, противонаправленный т.3 модели
                if ($isHighExtremum) {
                    $highExtremumCnt++;
                    $lead_highExtremums[] = ['bar' => $i, 'level' => high($i + $baseBarNum, $v)];
                }
            } else {
                if ($isLowExtremum) {
                    $lowExtremumCnt++;
                    $lead_lowExtremums[] = ['bar' => $i, 'level' => low($i + $baseBarNum, $v)];
                }
                if ($isHighExtremum) {
                    $highExtremumCnt++;
                    $lead_highExtremums[] = ['bar' => $i, 'level' => high($i + $baseBarNum, $v)];
                }
            }
        }
        $controlParams[$NEXTprefix . 'pstex_conf_R@'] = $lowExtremumCnt + $highExtremumCnt;

        // подсчет экстремумов для  pstex_R{P6}bmp
        $lowExtremumCnt = $highExtremumCnt = 0;
        for ($i = $realP6bar + 1; $i < $curBar; $i++) { // На баре достигшем бамперного уровня экстремум  не  учитывается
            $isHighExtremum = ($v == 'low') ? isExtremum($i + $baseBarNum, 'high') : isExtremum($i + $baseBarNum, 'low');
            $isLowExtremum = ($v == 'low') ? isExtremum($i + $baseBarNum, 'low') : isExtremum($i + $baseBarNum, 'high');
            if ($isLowExtremum) {
                $lowExtremumCnt++;
                $fall_lowExtremums[] = ['bar' => $i, 'level' => low($i + $baseBarNum, $v)];
            }
            if ($isHighExtremum) {
                $highExtremumCnt++;
                $fall_highExtremums[] = ['bar' => $i, 'level' => high($i + $baseBarNum, $v)];
            }
        }
        $controlParams[$NEXTprefix . 'pstex_R@bmp'] = $lowExtremumCnt + $highExtremumCnt;
    }
    // 2023-06-14 вторая часть параметров постконтроля
    // 6.  - группа pst_conf_R{P6}
    $controlParams[$NEXTprefix . 'pst_conf_R@'] = ($realP6bar - $t4_bar) / $sizeTime;

    // 7. - группа pst_lead_R{P6}

    // перебор всех экструмумов U-множества (lead) - ищем R
    $R_ = 0;
    $lead_bar = false;
    foreach ($lead_lowExtremums as $pk_l => $extr_l) {
        foreach ($lead_highExtremums as $pk_h => $extr_h) {
            if ($extr_h['bar'] >= $extr_l['bar']) continue;
            if (($extr_h['level'] - $extr_l['level']) > $R_) {
                $R_ = $extr_h['level'] - $extr_l['level'];
                $lead_bar = $extr_l['bar'];
            }
        }
    }
    if ($R_) $controlParams[$NEXTprefix . 'pst_lead_R@'] = ($realP6bar - $lead_bar) / $sizeTime;

    // 8. - группа pst_leadsize_R{P6}
    if ($R_) $controlParams[$NEXTprefix . 'pst_leadsize_R@'] = $R_ / $sizeTime;

    // 9. - группа pst_R{P6}fall
    // перебор всех экструмумов U-множества (fall) - ищем R
    $R_ = 0;
    $fall_bar = false;
    foreach ($fall_highExtremums as $pk_h => $extr_h) {
        foreach ($fall_lowExtremums as $pk_l => $extr_l) {
            if ($extr_l['bar'] >= $extr_h['bar']) continue;
            if (($extr_h['level'] - $extr_l['level']) > $R_) {
                $R_ = $extr_h['level'] - $extr_l['level'];
                $fall_bar = $extr_h['bar'];
            }
        }
    }
    if ($R_) $controlParams[$NEXTprefix . 'pst_R@fall'] = ($fall_bar - $realP6bar) / $sizeTime;

    // 10. - группа pst_R{P6}fallsize
    if ($R_) $controlParams[$NEXTprefix . 'pst_R@fallsize'] = $R_ / $sizeTime;

    // новые параметры из ТЗ от 2023-08-14
    //pst_llRP6pos
    $controlParams[$NEXTprefix . 'pst_llR@pos'] = ($CP_bar - $realP6bar) / $sizeTime;
    $controlParams[$NEXTprefix . 'pst_llR@prfll'] = ($realP6bar - $fall_bar) / $sizeTime;
    $controlParams[$NEXTprefix . 'pst_lvlR@prfll'] = ($realP6level - $fall_bar) / $sizeLevel;


    return ($controlParams);
}

function isExtremumHigh($n, $v)
{ // функция обертка - проверка на экстремум high с учетом направления модели v
    return ($v == 'low' ? isExtremum($n, 'high') : isExtremum($n, 'low'));
}

function isExtremumLow($n, $v)
{ // функция обертка - проверка на экстремум low с учетом направления модели v
    return ($v == 'low' ? isExtremum($n, 'low') : isExtremum($n, 'high'));
}

function isExtremum($n, $type)
{ //проверка на экстремум по правилу N1
    global $Chart;
    $H = isset($Chart[0]['h']) ? 'h' : 'high'; // как-то так исторически сложилось, что для Истории и РТ по назному называются бары
    $L = isset($Chart[0]['l']) ? 'l' : 'low'; // как-то так исторически сложилось, что для Истории и РТ по назному называются бары
    $max_bar = count($Chart) - 1;
    if ($n >= $max_bar || $n < 1) return (false);
    if ($type == 'high') {  // проверка на экстремум high
        if ($Chart[$n - 1][$H] >= $Chart[$n][$H]) return (false);
        for ($i = $n + 1; $i < $max_bar; $i++) {
            if ($Chart[$i][$H] < $Chart[$n][$H]) return (true);
            if ($Chart[$i][$H] > $Chart[$n][$H]) return (false);
        }
        return (false);
    }
    if ($type == 'low') { // проверка на экстремум low
        if ($Chart[$n - 1][$L] <= $Chart[$n][$L]) return (false);
        for ($i = $n + 1; $i < $max_bar; $i++) {
            if ($Chart[$i][$L] > $Chart[$n][$L]) return (true);
            if ($Chart[$i][$L] < $Chart[$n][$L]) return (false);
        }
        return (false);
    }
}
