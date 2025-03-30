<?php
// set_time_limit(120);
set_time_limit(500);
ini_set('memory_limit', '1024M');
//$tmp___=file_get_contents('php://input');
//file_put_contents("i:\\TMP_POST1___.txt",$tmp___);
//file_put_contents("i:\\TMP_POST1___.json",json_encode($_POST));


DEFINE("ALGORITHM_NUM", 1); // algorithm number
DEFINE("BAR_50", 50); // the depth of the search for the last intersection of potential t.1  level (to confirm t.1 in Step I of the algorithm)
DEFINE("BAR_T3_50", 50); // The number of bars for continuous point 3 (t.3) search (Step II of the algorithm)
DEFINE("BAR_T3_150", 150); // количество бар, глубина поиска точки т3 (пункт 2 алгоритма)
DEFINE("PAR246aux", 0.5); // минимальное соотношение т.2-т.4 к т.4-т.6
DEFINE("CALC_G3_DEPTH", 150); // глубина поиска при определении G3

// фиксированные поля в $State, их не очищаем при переходах между пунктами алгоритма
DEFINE("KEYS_LIST_STATIC", "v,mode,curBar,next_step,cnt,flat_log,status,param,split");
DEFINE("SHOW_LOG_STATISTICS", true); //служебная константа - если true - выводим доп.ветку в res - сколько раз вызывалась myLog из разных строк программы - для оптимизации лога (когда очень большой), поиск самых частых обращений
define("WRITE_LOG", 9);
error_reporting(-1); //error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 'On');

require_once 'build_models_common.php';

$modelNextId = 1;
// In this segment of the code, initialization and configuration of the $res array continues, which seems to be used to collect various types of results and script execution logs. 
$res = [];
$res['Algorithm_num'] = ALGORITHM_NUM; // the flag is the number of the algorithm, we just send the information to the java script - which of the algorithms returned the result
$res['Errors'] = [];
//$res['calcTime']=[];
$res['info'] = [];
$res['info']['calcTimes'] = [];

$res['log_selected_bar'] = [];
$lastT3fromT1 = []; // an array that determines which bar to search for a new t3 from (the previously considered t3 is indicated)
$res['log'] = []; // общий лог, не привязанный к State (в наст момент не используется, но для отладки туда можно что-то выводить $res['log'][]="text")
$res['Models'] = []; // разервируем ветку для моделей - при выходе, она заполняется в shutdown()
if (SHOW_LOG_STATISTICS) $res['FlatLog_Statistics'] = []; // if necessary, creates a branch to analyze the number of myLog executions from different lines of the program
//$Models_Alg1=false;
//if(!is_ajax()){$res['Errors']='Запускать можно только через AJAX, сорри.'; die();}


if (!isset($_POST['paramArr'])) throw new Exception('paramArr is not set!');
$paramArr = $_POST['paramArr']; // J берет из джава скрипта параметры модели?

// Mode selection: The $mode variable is set based on the input parameters obtained from JavaScript. This allows you to adapt the behavior of the script depending on the requirements of the user or the task.
$mode = isset($paramArr['mode']) ? $paramArr['mode'] : "mode1"; // J Если не был установлен мод, устанавливает "mode1". За установку мода отвечает .js файл
// Selected Bar: $selectedBar is set from the input data, with a default value provided if no data is provided. This value is then defined as a constant for future use.
$selectedBar = isset($paramArr['selectedBar']) ? intval($paramArr['selectedBar']) : 0; // J Берет  'selectedBar' из джава, либо устанавливает на 0
DEFINE("selectedBar", $selectedBar);
if ($mode == 'mode1') $mode = 'all';
if ($mode == 'mode2') $mode = 'last';
if ($mode == 'mode3') $mode = 'selected';

$res['paramArr'] = $paramArr;
$res['info']['mode'] = $mode;
$res['info']['selectedBar'] = $selectedBar;

if (!isset($_POST['Chart'])) throw new Exception('Chart is not set!');
$Chart = json_decode($_POST['Chart'], true);

// Getting/determining the pip size
if (isset($paramArr['pips'])) $pips = $paramArr['pips'];
else $pips = calcPips($Chart);
if ($pips < 0.00000001) throw new Exception('!!! pips==0 (' . $pips . ')');

// 2023-09-01 The NEED_TEXT_RES constant is set based on the text_res input parameter. This allows you to control whether the results of the algorithm execution should be written to a text file, and provides flexibility in managing data output.
if (isset($paramArr['text_res'])) {
    if ($paramArr['text_res'] != "0") { // if 0 is set, it means that we do not save the result in a text file when exiting
        // ! ATTENTION! DEFAULT VALUE :
        // false = do not write
        // true = every time to a new file with time indication (auto-naming)
        if ($paramArr['text_res'] == 1) DEFINE("NEED_TEXT_RES", true); // задан как 1 - задаем true - When setting NEED_TEXT_RES to true, the file name for recording the results is generated automatically with the date and time, which makes it easier to organize and analyze the results.
        else DEFINE("NEED_TEXT_RES", $paramArr['text_res']); // задан фиксированный файл
    }
} else DEFINE("NEED_TEXT_RES", "tmp_log/_res_Alg1_{DANDT}.txt");
// OR specify the file name (e.g. "tmp_log/_res_Alg2_{DANDT}.txt"), if it contains {DANDT}, then the date and time will be substituted

// The number of bars (nBars) is defined as the total number of elements in the $Chart array, which involves preparing for the analysis of chart data.
DEFINE("nBars", count($Chart));

//$res['Chart']=count($Chart);
$res['info']['count'] = nBars; //count($Chart); 

// The $State array is initialized to store statuses, current calculations and possible other parameters related to the execution of the algorithm. This is the main algorithm manager.
$State = [
    'status' => [], // models statuses
    //'nBars' => count($Chart), - лишняя переменная, засоряет $State - убрал в константу
    'split' => 0,
    'mode' => $mode,
    //'selectedBar' => $selectedBar, //- лишняя переменная, засоряет $State - убрал в константу
    'curBar' => 0, // current bar
    'next_step' => 'step_1', // which step is next - changes when performing checks at the current step, stop=branch completion
    'cnt' => 0, // counter for calling various procedures (algorithm steps)
    'flat_log' => [], // плоский лог - массив строк по порядку лога
    'param' => [] // models parameters
];  

if (isset($paramArr['log']) && $paramArr['log'] == 0) unset($State['flat_log']); // убираем ветку flat_log, если не указан подробный лог (галочка в js)
$State = myLog($State, Date("Y-m-d H:i:s") . "> Start Algorithm_1");
$State['curBar'] = ($selectedBar == 0 || $mode == 'all') ? (nBars - 10) : $selectedBar; // the first bar to check for point 1 candidate. It is the 10th bar on the right, or the selected bar (if selected). 
$States = [];
$States[] = $State; // первый стэйт 

// This code segment represents a part of the main processing cycle that controls the execution of the algorithm through state iteration ($States). Let's look at the key aspects.
// The main execution cycle: The $toRepeat variable is used to control the repetition of the loop. The cycle continues as long as there is a need for additional processing iterations.
$toRepeat = true;
// Inside the main 'while' loop, all states in the $States array are iterated over, and a series of operations based on the value of 'next_step' are performed for each state.
while ($toRepeat) { // пока $toRepeat != false

    $toRepeat = false; //если ветвления алгоритма не будет, отработаем все состояния и выход
    foreach ($States as $pk => $pv) { // $pk здесь - это перебор $State, а $pv - элементы
        $tmp_cnt = 0;
        // Inside the main while loop, all states in the $States array are iterated over, and a series of operations based on the value of 'next_step' are performed for each state.
        while (
            isset($States[$pk]) //  пока есть хотя бы один элемент в $States, у которого...(смотрим следующую строку)
            && $States[$pk]['next_step'] !== 'stop'
            && $tmp_cnt < 25000 //ограничиваем количество итераций для
        ) {
            $tmp_cnt++;
            $States[$pk]['cnt']++; // счетчик итераций диспетчера, пока не знаю, зачем он нужен
            $start_proc_time = microtime(true);
            $functionName = $States[$pk]['next_step'];  //имя процедуры-расчета следующего шага алгоритма (своеобразная диспетчеризация)
            $res['info']['last_function'] = $functionName . " State N $pk  tmp_cnt=$tmp_cnt"; // для отладки - ловим функию, на которой зависает (если, конечно, зависает :)
            $curState = $pk;
            $curSplit = $States[$pk]['split'];
            $States_tmp = $functionName($States[$pk]); // вызов нужной функции для очередного step - имя этой функции было в поле "next_step"
            $res['info']['last_function'] .= ' done'; // показали, что из функцими вышли
            $res['info']['last_function_ok'] = $functionName; // показали, что из функцими вышли
            $finish_proc_time = microtime(true); // время выполнения очередного шага
            if (!isset($res['info']['calcTimes'][$functionName . '_time'])) {  // если не указано время расчета одной из процедур в подмассиве calcTimes, то...
                $res['info']['calcTimes'][$functionName . '_time'] = $finish_proc_time - $start_proc_time; // рассчитываем его
                $res['info']['calcTimes'][$functionName . '_cnt'] = 1;  // т.к. при проверке выявили, что время расчета процедуры не указано, то устанавливаем счетчик на 1
            } else {
                $res['info']['calcTimes'][$functionName . '_cnt']++; // в случае, когда время расчета было указано, то добавляем к счетчику +1
                $res['info']['calcTimes'][$functionName . '_time'] += ($finish_proc_time - $start_proc_time);
            }
            if (!is_array($States_tmp)) $res['log'][] = "ERROR! - not array in result. functionName=" . $functionName; // вернулся и не State и не массив (ошибка)
            else {
                if (isset($States_tmp['v'])) { // вернулся одиночный State
                    $States[$pk] = $States_tmp;
                } //  вернулся не массив а один State
                else { // было расщепление
                    if (count($States_tmp) > 1)
                        if (isset($res['info']['splitting'][$functionName])) $res['info']['splitting'][$functionName] += count($States_tmp) - 1;
                        else $res['info']['splitting'][$functionName] = count($States_tmp) - 1;
                    $States[$pk] = $States_tmp[0]; // первый элемент из вернувшегося массива States пишем на старое место
                    if (count($States_tmp) > 1) { // произошло разветвление алгоритма, вместо одного вернулось >=2 состояния - начиная со второго - создаем новые State в массиве $States
                        for ($n = 1; $n < count($States_tmp); $n++) $States[] = $States_tmp[$n]; // добавляем новый State в массив
                        $toRepeat = true;
                    }
                }
            } // вернулось нормально значение $States_tmp - массив  из State  или один State
        }
    }
}
$res['States'] = $States;

die();

function myLog_start($State, $step)
{
    global $res;
    $curBar = $State['curBar'];
    $v = $State['v'];
    //$txt = "[" . $State['split'] . "] *** step $step ($curBar $v) t1";
    //$txt = "[" . $State['split'] . "] *** step $step (".$State['curBar']." ".$State['v'].") t1";
    $txt = "*** step $step (" . $State['curBar'] . " " . $State['v'] . ") t1";
    $tArr = [];

    // We inform the log at the start of a new step - the values of the main important points found on the tech.moment + low or high model is being built
    if (isset($State['t2'])) $tArr[] = 't2';  // This line checks whether an array element with the key 't2' is installed in the $State array. If this element exists, its key is added to the $tArr array.
    if (isset($State['t3'])) $tArr[] = 't3';
    if (isset($State['t4'])) $tArr[] = 't4';
    if (isset($State['t5'])) $tArr[] = 't5';
    if (isset($State['conf_t4'])) $tArr[] = 'conf_t4';

    // Combining strings using the $tArr array: First, a $txt string is created, which is formed by adding elements of the $tArr array separated by a dash. 
    foreach ($tArr as $pk => $pv) {
        $txt .= "-" . $pv;
    }
    // Then the variable $s, initialized with the value from $State['t1'], is combined with the elements $State[$pv], where $pv is the value from the array $tArr, also separated by a dash.
    $s = $State['t1'];
    foreach ($tArr as $pk => $pv) {
        $s .= "-" . $State[$pv];
    }
    // Calling the myLog function: After forming strings, the $State variable is updated with the result of calling the myLog function, to which the current state and the generated string are passed.
    $State = myLog($State, $txt . ": " . $s);

    // Logging condition: The comment indicates the elimination of the previous conditional logging in favor of a direct call to myLog, suggesting that myLog itself may contain logic to determine whether logging is necessary.
    //if (!isset($State['t3']) || selectedBar == $State['t3']) $res['log_t3'][] = $txt . ": " . $s;  
    return ($State);
}

function step_1($State)
{  // Step I.	POINT 1 SEARCH. SEARCH OF A CONFIRMING EXTREMUM

    global $res, $splitCnt;
    $State['t1'] = $curBar = $State['curBar']; // The value of the current bar is assigned to the element of the $State['t1'] array
    $v = isset($State['v']) ? $State['v'] : ""; // If $State['v'] is assigned, then $v takes this value, if not, then the value ""

    if (!isset($State['v'])) { // первый вызов (если НЕ присвоен $State['v'] - "расщепляем" на веткии high и low
        $State = myLog($State, 'Splitting into high and low branches ');
        $state1 = $state2 = $State;
        $state1['v'] = 'high';
        $state2['v'] = 'low';
        $state2['split'] = $splitCnt++; // подсчет отпочковавшейся ветки Алгоритма
        //$state2['split'] = $splitCnt++;
        return ([$state1, $state2]);
    }

    $State = myLog_start($State, "1");

    $check = checkUnique($State, "v,curBar");
    if ($check) {
        $State = myLog($State, $check);
        $State['next_step'] = 'stop';
        return ($State);
    }

    // If the analyzed bar is closer to the beginning of the chart than 3 bars, then the calculation stops
    if ($State['curBar'] < 3) {
        $State = myLog($State, " STOP: the beginning of the chart is reached");
        $State['next_step'] = 'stop';
        return ([$State]);
    }

    // checking for an extremum - we are looking for a candidate for point 1 (Sub-step 1.1)
    $cont = true;

    for ($i = $State['curBar']; $i > 3 && $cont; $i--) { // counting back until we reach the 3rd bar from the beginning of the chart
        $State['t1'] = $i;
        if (is_extremum($i, $v)) {
            myLog_selected_bar($State, "Found extremum  ($v) - t1 candidate: $i");
            $price = low($i, $v); // the level of the checked t.1
            
            // checking the candidate for point 1
            $cont = true;
            for ($j = $i - 1; $j > 0 && $j > ($i - BAR_50); $j--) { // ищем либо пересечение цены, либо 50 бар от выбранного бара, либо начало графика
                // if (low($j, $v) == $price)  continue 2;
                if (low($j, $v) < $price && high($j, $v, __LINE__) >= $price) break;
            }

            myLog_selected_bar($State, "Numbers of bars checked: " . ($i - $j - 1) . " Confirming bar (3) = $j"); // "bar (3)" is the bar that crossed t.1 level
            // $j -  the bar from which we are looking for a confirming extremum
            
            // Search for confirming extremum
            $PrevTrend_level = low($State['t1'], $v); // объявляем переменную для уровня подтверждающего экстремума (уровень начала предшествующего тренда) и пока что приравниваем её т.1
            for ($k = $j; $k < $i; $k++) {
                if (
                    is_extremum($k, not_v($v))
                    && high($k, $v) > $PrevTrend_level
                    // бар является экстремумом
                ) {
                    $PrevTrend_level = high($k, $v);

                    for ($m = $k + 1; $m < $State['t1']; $m++) {
                        if (high($m, $v) > $PrevTrend_level) continue 2; // Проверка абсолютности. Программа осуществляет поиск бара с максимальной (если т.3 - low) или  минимальной (если т.3 - high) ценой на участке от кандидата в A2prev до т.1
                        if (low($m, $v) == $price) { // 1.2.1.2. Экстремум найден. Кандидат в т.1 подтвержден. В этом случае программа проверяет отсутствие экстремумов, равных т.1 на участке от начала предшествующего до т.1.
                            $State = myLog($State, "Найден более раниий экстремум ($m , $v) - кандидат в т.1 на уровне рассматриваемого кандадидата: $i - low($i, $v) на участке после начала предшествующего тренда");
                            continue 3;
                        }
                    }

                    $State['t1'] = $i;
                    $State['2'] = $k;
                    $State['3'] = $j;
                    // $State['flat_log'][] = "(1->2) $i $k $j";
                    myLog_selected_bar($State, "Найден подтверждающий экстремум (2): " . $k . " переход к п.2");
                    $State['curBar'] = $i + 1;
                    $State['next_step'] = 'step_2';  // нашли подтверждающий экстремум - переход к п2 алгоритма
                    $State = myLog($State, "Создаем новый State для поиска новой т1 ($v) split: $splitCnt curBar= " . ($i - 1));
                    // параллельно создаем новую ветку для поиска другой
                    $state2 = clearState($State, "t1"); // создаем новую ветку - ищем новую т1 + стираем все ненужные ключи массива
                    $state2['param'] = [];
                    $state2['cnt'] = 0;
                    $state2['curBar'] = $i - 1; // ищем вглубь, начиная с предыдущего бара
                    if (isset($state2['flat_log'])) $state2['flat_log'] = []; // если был, то стираем лог
                    $state2 = myLog($state2, "Ответвление на step_1 - ищем новую т1 начиная с " . $state2['curBar'] . " (" . $state2['v'] . ")");
                    $state2['next_step'] = "step_1"; // ответвление продолжает обрабатывать новые т3
                    $state2['split'] = $splitCnt++;
                    return ([$State, $state2]);
                }
            }

            myLog_selected_bar($State, "Подтверждающего экстремума (2) не найдено");
        } else { // выход, если нужно было строить модель по выбранному бару j (см. следующую строку)
            if ($State['mode'] == 'selected') {
                $State['next_step'] = 'stop';
                myLog_selected_bar($State, "Выбранный бар не является экстремумом ($v)");
                return ([$State]);
            }
        }
    }

    // если не отработали условные блоки 
    $State['next_step'] = 'stop';
    $State = myLog($State, "(1) exit");
    return ([$State]);
}

//пункт 2.	ПОИСК ТОЧЕК 3 И 2.
function step_2($State)
{
    global $res, $lastT3fromT1;
    $v = $State['v'];
    $curBar = $State['curBar'];
    if (!isset($State['t3-'])) $t3_last_level = 1000000000; // 't3-' уровень предыдущей(отмененной) т3
    else $t3_last_level = low($State['t3-'], $v);

    $State = myLog_start($State, "2");

    $check = checkUnique($State, "v,curBar,t1,t3-");
    if ($check) {
        $State = myLog($State, $check);
        $State['next_step'] = 'stop';
        return ($State);
    }

    for ($i = $curBar; $i < (nBars - 3)
        && $i <= ($State['t1'] + BAR_T3_150)
        && $i <= ($curBar + BAR_T3_50); $i++) {
        //         if(!isset($lastT3fromT1[$State['t1']])||$lastT3fromT1[$State['t1']]<$i)$lastT3fromT1[$State['t1']]=$i; // обновляем значение последнего старка поиска т3 для тек. т1
        if (low($i, $v) < low($State['t1'], $v)) { // отменяем т1 - пробит уровень т1
            $State = myLog($State, "отмена t1 (" . $State['t1'] . ") - преодолен уровень t1 на баре $i");
            // $lastT3fromT1[$State['t1']] = 1000000000; // отсечка дальнейшего поиска т3 по данной т1 J почему не BAR_T3_150??
            // $lastT3fromT1[$State['t1']] = $State['t1']+BAR_T3_150; // J альтернатива закомментированной выше строке
            return ([next_T1($State)]);
        }

        // if (low($i, $v) <= $t3_last_level || !isset($t3_last_level)) {
        if (low($i, $v) < $t3_last_level || !isset($t3_last_level)) {
            if (is_extremum($i, $v)) { //кандидат в т3
                myLog_selected_bar($State, "($v) кандидат в t3: " . $i);
                $State['t3'] = $i;

                // ищем подтверждающий экстремум = т2
                $is_candidate_found = false; // J т.к. только что найден т.3, здесь по любому будем искать для неё новую т.2 
                // $t2_level = low($State['t1'], $v); // J это уровень т.1, здесь ошибка, поэтому добавлю снизу исправленную строку
                $t2_level = low($State['t3'], $v); // исправленная строка
                if (is_extremum($State['t1'], not_v($v))) $t2_level = high($State['t1'], $v); // в случае, когда т.1 содержит экстремум, сонаправленный с т.2, т.2 должен быть выше
                for ($j = $State['t1'] + 1; $j < $i; $j++) { // поиск абсолютного максмума J точка т2 строго между барами т1 и т3 J (убрал знаки вопроса)
                    if (high($j, $v, __LINE__) > $t2_level) { // J если текущий хай (в случаях т.1-лоу) больше уровня т.3, приравниваем $t2_level текущему хай
                        $t2_level = high($j, $v, __LINE__); // в переменной $t2_level теперь уровень т.2
                        $is_candidate_found = true; // кандидат в 2 выше уровня т.3 найден
                    }
                }

                if ($is_candidate_found) for ($j = $State['t1'] + 1; $j < $i; $j++) {  // поиск экстремума (т2) J эти действия осуществляются после выявления уровня т.2
                    // if (high($j, $v, __LINE__) == $t2_level) 
                    // if (is_extremum($j, not_v($v))) // противонаправленный экстремум J кандидат в т.2 является экстремумом, протвонаправленным т.1
                    if (
                        high($j, $v, __LINE__) == $t2_level
                        && is_extremum($j, not_v($v))
                    ) { // альтернатива двум строчкам, закомментированным выше
                        $State['t2'] = $j; // заносим бар т.2 в массив
                        $State = myLog($State, "Подтвердили t3: " . $State['t3'] . '  t1=' . $State['t1'] . ' t2=' . $State['t2']);
                        // перехож к п.3.1 алгоритма - проверка на фрагментированность базы
                        $State['curBar'] = $State['t3'] + 1; // не нужен для 3.1, просто для порядка
                        $State['next_step'] = 'step_3_1';

                        if (!isset($lastT3fromT1[$State['t1']]) || $lastT3fromT1[$State['t1']] < $State['t3']) // зачем, если есть 260-ая строка? 
                            $lastT3fromT1[$State['t1']] = $State['t3']; // обновляем значение последнего старка поиска т3 для тек. т1
                        return ([$State]);
                    }
                }
            }
        }
    }

    // срабатывают, если во время цикла не сработал ни один из условных блоков
    $lastT3fromT1[$State['t1']] = $i;
    $State = myLog($State, "Прошли до $i - переход к поиску новой t1");
    return ([next_T1($State)]); // J вызов функции
}

function step_3_1($State)
{ //3.1. ПРОВЕРКА НА ФРАГМЕНТИРОВАННОСТЬ БАЗЫ.
    $v = $State['v'];
    //$curBar=$State['curBar'];
    $State = myLog_start($State, "3.1");
    // ищем ЭФБ (сонаправленные с t1 и t3)
    // for ($i = $State['t1'] + 1; $i < $State['t3']; $i++) { // J Здесь на самом деле вместо 't3' должно быть 't2', т.к. эфб ищутся на участке т.1-т.2
    for ($i = $State['t1'] + 1; $i < $State['t2']; $i++) { // J исправленная строка 
        //   $res['log'][]="i: ".$i;
        // if (is_extremum($i, $v) && low($i, $v) >= low($State['t1'], $v) && low($i, $v) <= low($State['t3'], $v)) { //экстремум между уровнями т1 и т3 J (включая уровни...)
        if (is_extremum($i, $v) && low($i, $v) <= low($State['t3'], $v)) { //экстремум между уровнями т1 и т3 J (включая уровни...)
            $State = myLog($State, "кандидат в ЭФБ: " . $i);
            $level_ = low($i, $v);
            // программа проверяет каждый ЭФБ на наличие подтверждающего противонаправленного экстремума на участке от последнего пересечения ценой уровня ЭФБ до ЭФБ
            // точка последнего пересечения ценой уровня ЭФБ до ЭФБ:
            // for ($j = $i - 1; $j > $State['t1']; $j--) {
            for ($j = $i - 1; $j >= $State['t1']; $j--) {
                //  $res['log'][]="j: ".$j;
                // if (low($j, $v) < $level_ && high($j, $v, __LINE__) > $level_) { //нашли точку пересечения цены ЭФБ, проверяем есть ли подтверждающий фрагментацию базы противонаправленный экстремум
                if (low($j, $v) < $level_) { //нашли точку пересечения цены ЭФБ, проверяем есть ли подтверждающий фрагментацию базы противонаправленный экстремум
                    for ($k = $j; $k < $i; $k++)
                        if (is_extremum($k, not_v($v))) { //есть противонаправленный экстремум, база фрагментирована
                            $State = myLog($State, "База фрагментирована ЭФБ: t1-t2-t3:" . $State['t1'] . "-" . $State['t2'] . "-" . $State['t3'] . "; подтв.экстремум: " . $k . " Отмена t3 и возврат на п.2");
                            //$State['t3_last_level']=low($State['t3'],$v);
                            $State['t3-'] = $State['t3'];
                            $State['curBar'] = $State['t3-'] + 1;
                            $State['next_step'] = 'step_2';
                            return ([$State]);
                        }
                    break; // если на участке после точки пересечения цены ЭФБ экстремум не был найден, то дальше новую точку пересечения ценой ЭФБ искать уже не надо
                }
            }
        }
    }

    $State = myLog($State, "п.3.1.2. проверка ФБ успешно пройдена");
    $State['next_step'] = 'step_3_2';
    //$res['log'][]="end of step_3_1";
    return ([$State]);
}

function step_3_2($State)
{ //ПОСТРОЕНИЕ И ПРОВЕРКА ЛТ НА УЧАСТКЕ 1-3.  ОПРЕДЕЛЕНИЕ УЧАСТКА, НА КОТОРОМ МОЖЕТ БЫТЬ НАЙДЕНА Т.3’. ПОИСК Т.3’.
    $v = $State['v'];
    //$curBar=$State['curBar'];
    $State = myLog_start($State, "3.2");
    // определение ЛТ (дельта цены на один бар)
    $LT = ['bar' => $State['t1'], 'level' => low($State['t1'], $v), 'angle' => (low($State['t3'], $v) - low($State['t1'], $v)) / ($State['t3'] - $State['t1'])];
    // определяем, был ли пробой ЛТ
    //$lt_probita=false;
    for ($i = $State['t1'] + 1; $i < $State['t3']; $i++) {
        if (low($i, $v) <= lineLevel($LT, $i)) {  //  3.2.1. ЛТ пробита. Бар подтвержденной т.3 проверяется на пробой уровня т.2.
            $State = myLog($State, "3.2.1 ЛТ пробита на баре " . $i);
            if (high($State['t3'], $v, __LINE__) >= high($State['t2'], $v, __LINE__)) { //3.2.1.1. Если уровень т.2 пробит баром подтвержденной т.3
                $State = myLog($State, "3.2.1.1 уровень т.2 пробит баром подтвержденной т.3 :" . $State['t3'] . " переход к п.2");
                $State = myLog($State, "отмененная т.3: " . $State['t3']);
                $State['t3-'] = $State['t3'];
                $State['curBar'] = $State['t3'] + 1;
                $State['next_step'] = 'step_2';
                $State = clearState($State, "2,3,t1,t2,t3-");
                return ([$State]);
            } else { // уровень Т.2 не пробит подтвержденной Т.3
                $State = myLog($State, "3.2.1.2. уровень т.2 не пробит баром подтвержденной т.3 :" . $State['t3'] . " переход к п.3.2.3");
                //3.2.3. ОПРЕДЕЛЕНИЕ УЧАСТКА, НА КОТОРОМ МОЖЕТ БЫТЬ НАЙДЕНА Т.3’.
                $State['curBar'] = $State['t3'] + 1;
                $State['next_step'] = 'step_3_2_3';
                return ([$State]);
            }
        }
    }
    // ЛТ не пробита - переход к п.3.3
    $State = myLog($State, "3.2.1 ЛТ не пробита - переход к п.3.3");
    $State['next_step'] = 'step_3_3';
    $State['curBar'] = $State['t3'] + 1;
    return ([$State]);
}

function step_3_2_3($State)
{ //3.2.3. ОПРЕДЕЛЕНИЕ УЧАСТКА, НА КОТОРОМ МОЖЕТ БЫТЬ НАЙДЕНА Т.3’.
    $v = $State['v'];
    $curBar = $State['curBar'];
    $State = myLog_start($State, "3.2.3");
    for ($i = $curBar; $i < ($State['t1'] + BAR_T3_150) && $i < (nBars - 3); $i++) { // движение по ветке 3.2.3

        myLog_selected_bar($State, "цикл 3.2.3 - проверяем бар " . $i);
        if (low($i, $v) <= low($State['t3'], $v)) { //Анализируемый бар проверяется на пробой уровня подтвержденной т.3.
            $State = myLog($State, "3.2.3.1. Уровень подтвержденной т.3 (" . $State['t3'] . ") пробит на баре $i, ищем новую т.3");
            $State = myLog($State, "отмененная т.3: " . $State['t3']);
            $State['t3-'] = $State['t3'];
            $State['curBar'] = $i;
            $State = clearState($State, "2,3,t1,t2,t3-");
            $State['next_step'] = 'step_2';
            return ([$State]);
        } else {
            myLog_selected_bar($State, "уровень т.3 не пробит, программа проверяет анализируемый бар на пробой уровня т.2"); // J 3.2.3.2.
            if (high($i, $v) > high($State['t2'], $v, __LINE__)) { //уровень т.2 пробит J 3.2.3.2.2.
                myLog_selected_bar($State, "уровень т.2 пробит на баре $i, ищем т.3'");
                //3.2.3.2.2. ПОИСК Т.3’. Если пробивает уровень т.2, то программа ищет на участке от т.3 до бара, пробившего уровень т.2 (включительно), такую точку 3’ (далее т.3’)
                // через которую можно построить линию т.1-т.3’, которая не будет содержать лишних касаний ценой на участке от бара т.1 до барат.3’.
                $isT3shtrFound = false;
                for ($j = $State['t3'] + 1; $j <= $i; $j++) { // перебор возможных т.3' J т.3' на участке от бара, следующего за т.3(включительно), до бара, пробившего т.2(включительно)
                    $wasTouch = false;
                    $LT_candidat = ['bar' => $State['t1'], 'level' => low($State['t1'], $v), 'angle' => (low($j, $v) - low($State['t1'], $v)) / ($j - $State['t1'])];
                    // проверяем есть ли касания ЛТ-кандидата
                    for ($k = $State['t1'] + 1; $k <= $j + 1 || $k < $i; $k++) if ($k !== $j) { // исключаем т3' + след бар +участок до пробоя т2 J $j-это бар т.3', $i-бар, пробивший т.2, k-это анализируемый бар;  "а также эта линия не должна сдержать лишних пробоев! ценой до бара, пробившего уровень т.2 (не включая)"
                        if (low($k, $v) < lineLevel($LT_candidat, $k)) { // есть пробой J проверка на касания не нужна на т.1-т.3', т.к. выбирается первый по времени возможный кандидат
                            $wasTouch = true;
                            break;
                        }
                    }
                    if (!$wasTouch) { //найдена т.3' = $j}
                        // исправление п 3.2.3.2.2 - проверяем, чтобы бар, следующий за кандидатом в т3' не пересекал новую ЛТ
                        //  if(low($j+1,$v)>lineLevel($LT_candidat,$k+1)){ // нет касания
                        $isT3shtrFound = $j;
                        break;
                        //  }
                        //  else{
                        //      $State=myLog($State,"Т3' забракована - есть касание ценой ЛТ на след баре");
                        //  }
                    }
                }

                if ($isT3shtrFound) { //3.2.3.2.2.А.  т.3'  найдена, ищем Т.4 - переход на п.3.4
                    $State['t3\''] = $isT3shtrFound;
                    // определяем бар для перехода на п.3.4
                    if ($isT3shtrFound == $i) $State['curBar'] = $i + 1; // t3' совпадает с баром, пробившим т.2
                    else $State['curBar'] = $i;
                    $State = myLog($State, "3.2.3.2.2.А.  т.3'  найдена ($isT3shtrFound), ищем Т.4 - переход на п.3.4");
                    $State['next_step'] = 'step_3_4';
                    return ([$State]);
                } else { // 3.2.3.2.2.Б. Если точка т.3’ не найдена, то программа ищет новую т.3 - переход на п.2
                    $State = myLog($State, "3.2.3.2.2.Б. Если точка т.3' не найдена,  ищем новую т.3 - переход на п.2");
                    $State = myLog($State, "отмененная т.3: " . $State['t3']);
                    $State['t3-'] = $State['t3'];
                    $State['curBar'] = $i;
                    $State = clearState($State, "2,3,t1,t3-");
                    $State['next_step'] = 'step_2';
                    return ([$State]);
                }
            } else { //3.2.3.2.1. Если уровень т.2 не пробит, программа анализирует следующий бар в соответствии с пп.3.2.3.
                // продолжение цикла 3.2.3
            }
        } //3.2.3.2. уровень т.3 не пробит, программа проверяет анализируемый бар на пробой уровня т.2
    } // цикл 3.2.3 макс глубиной 150
    $State = myLog($State, $State['t1'] . "-" . $State['2'] . "-" . $State['3'] . " - прошли 150 баров до  ($i). Ищем новую t1");
    return ([next_T1($State)]);;
}

function step_3_3($State)
{ //3.3. ПРОВЕРКА ЛТ НА УЧАСТКЕ ПОСЛЕ Т.3.
    $v = $State['v'];
    $curBar = $State['curBar'];
    $State = myLog_start($State, "3.3");
    $LT = ['bar' => $State['t1'], 'level' => low($State['t1'], $v), 'angle' => (low($State['t3'], $v) - low($State['t1'], $v)) / ($State['t3'] - $State['t1'])]; // линия тренда т1-т3
    if (low($curBar, $v) >= lineLevel($LT, $curBar)) { //3.3.1. ЛТ не преодолена — анализируемый бар обрабатывается по пп.3.4.
        $State = myLog($State, "3.3.1. ЛТ не преодолена — анализируемый бар ($curBar) обрабатывается по пп.3.4. ");
        $State['next_step'] = 'step_3_4';
        return ([$State]);
    } else { //ЛТ преодолена
        // 3.3.2 проверяем, достигался ли уровень Т.2 между проверяемым баром (невключая) и Т.3 (включая)
        for ($i = $State['t3']; $i < $curBar; $i++) {
            if (high($i, $v, __LINE__) >= high($State['t2'], $v, __LINE__)) {
                $State = myLog($State, "3.3.2.2. Уровень Т.2 был достигнут на баре ($i). Отбрасываем Т3, переход на п.2");
                $State['t3-'] = $State['t3'];
                $State['curBar'] = $State['t3'] + 1;
                $State = clearState($State, "2,3,t1,t3-");
                $State['next_step'] = 'step_2';
                return ([$State]);
            }
        }
        //3.3.2.1. Если между анализируемым баром (не включая) и баром т.3 (включая) уровень т.2 не достигался, программа осуществляет поиск т.3’, для чего переходит к пп.3.2.3.
        $State = myLog($State, "3.3.2.2. Уровень Т.2 не достигался между ($i) и Т.3. Ищем Т.3', переход на п.3.2.3");
        $State['next_step'] = 'step_3_2_3';
        return ([$State]);
    }
    $State = myLog($State, "ОШИБКА - сюда не могли попасть");
    $State['next_step'] = 'stop';
    return ([$State]);
}

function step_3_4($State)
{ //ПОИСК ПОТЕНЦИАЛЬНОЙ ЛИБО АЛЬТЕРНАТИВНОЙ Т.4.
    $v = $State['v'];
    $curBar = $State['curBar'];
    $State = myLog_start($State, "3.4");

    $check = checkUnique($State, "v,curBar,t1,t2,t3,t3-,t4,draw_flag");
    if ($check) {
        $State = myLog($State, $check);
        $State['next_step'] = 'stop';
        return ($State);
    }
    // определяем линию тренда
    $LT = LT($State);

    $isAlternativeT4 = false; // пока не понятно, признак, чего ищем - Т4 или альтернативную Т4 (пока ЗАГЛУШКА!!!!!!!!!!!!!!!!!)
    // if (isset($State['alt_old']) && $State['alt_old'] == 1) $isAlternativeT4 = true;
    if (isset($State['param']['alt_old']) && $State['param']['alt_old'] != 0) $isAlternativeT4 = true;
    for ($i = $curBar; $i < nBars; $i++) {
        if (
            !$isAlternativeT4 && ($curBar - $State['t1']) > ($State['t3'] - $State['t1']) * 5
            // || $isAlternativeT4 && ($curBar - $State['t1']) > ($State['t3'] - $State['t1']) * 10 // В случае, если программа осуществляет поиск альтернативной т.4, а анализируемый бар дальше от т.4, чем [количество баров между т.1 и т.3] взятое 10 раз , то поиск альтернативной т.4 прекращается.
        ) {
            //Если программа осуществляет поиск т.4, а проанализированный бар дальше от т.1, чем [расстояние между т.1 и т.3] взятое 5 раз , то данная потенциальная т.1 отбрасывается.
            $State = myLog($State, "Превысили лимит глубины поиска T.4 Дошли до бара $i - переходим к следующей T.1");
            return ([next_T1($State)]);
        }
        if ($isAlternativeT4 && ($curBar - $State['t1']) > ($State['t3'] - $State['t1']) * 10) {
            //Если программа опрограмма осуществляет поиск альтернативной т.4, а анализируемый бар дальше от т.4, чем [количество баров между т.1 и т.3] взятое 10 раз , то поиск альтернативной т.4 прекращается.
            $State = myLog($State, "Превысили лимит глубины поиска т.4  альтернативной модели. Поиск прекращен");
            $State['next_step'] = 'stop'; /// ЗАГЛУШКА
            return ([$State]);
        }
        if (!is_extremum($i, not_v($v))) { //3.4.1. Если бар не экстремум — то программа проверяет анализируемый бар на пробой (в данном случае не касание) ЛТ.
            if (low($i, $v) <= lineLevel($LT, $i)) { // пробой ЛТ баром $i
                $State = myLog($State, "Пробой ЛТ баром ($i) - анализируемый бар ($i) обрабатывается по аналогии с п. 3.3.2.");
                // далее вставка "по аналогии с 3.3.2"
                for ($ii = $State['t3']; $ii < $i; $ii++) {
                    if (high($ii, $v, __LINE__) >= high($State['t2'], $v, __LINE__)) {  //  ИСПРАВИЛ ТЕСТ t3 на т2
                        $State = myLog($State, "аналог 3.3.2.2. Уровень Т.2 был достигнут на баре ($ii). Отбрасываем Т3, переход на п.2");
                        $State['t3-'] = $State['t3'];
                        $State['curBar'] = $State['t3'] + 1;
                        $State = clearState($State, "2,3,t1,t3-");
                        $State['next_step'] = 'step_2';
                        return ([$State]);
                    }
                }
                //как 3.3.2.1. Если между анализируемым баром (не включая) и баром т.3 (включая) уровень т.2 не достигался, программа осуществляет поиск т.3’, для чего переходит к пп.3.2.3.
                $State = myLog($State, "аналог 3.3.2.2. Уровень Т.2 не достигался между ($i) и Т.3. Ищем Т.3', переход на п.3.2.3");
                $State['next_step'] = 'step_3_2_3';
                return ([$State]);

                // конец вставки из 3.3.2
            } else { // ЛТ не пробита - обрабатывается следующий бар по 3.4 (продолжение цикла)
                continue;
            }
        } else { //3.4.2. Если бар экстремум — это потенциально т.4, переход к пп.3.5.
            $State = myLog($State, "бар $i - экстреммум, потенциальная Т.4 - переход на п.3.5");
            $State['t4'] = $State['curBar'] = $i;
            // curBar нужно задать ???????????????
            $State['next_step'] = 'step_3_5';
            return ([$State]);
        }
    }
    $State = myLog($State, "ОШИБКА? вышли из цикла 3.4 на баре ($i) - ищем другую т.1");
    return ([next_T1($State)]);
}

function step_3_5($State)
{ //3.5. ПРОВЕРКА РАСПОЛОЖЕНИЯ Т.4.
    $v = $State['v'];
    $curBar = $State['curBar']; // равен т4!
    $LT = LT($State);
    $State = myLog_start($State, "3.5");
    // проверяем, кто ближе к т.1 - кандидат в т.4 или т.2
    if (high($State['t4'], $v, __LINE__) < high($State['t2'], $v, __LINE__)) {
        $State = myLog($State, "3.5.1. Уровень потенциальной т.4 ближе к уровню т.1, чем уровень т.2 — найден кандидат на т.4 для модели типа Клин");
        //далее - проверка на потенциальную абсолютность экстремума потенциальной т.4.
        $isAbsExtr = true;
        for ($i = $State['t3'] + 1; $i < $curBar; $i++) {
            if (high($i, $v, __LINE__) >= high($State['t4'], $v, __LINE__)) {
                $isAbsExtr = false;
                break;
            }
        }
        if ($isAbsExtr) { // кандидат в т.4 - абс.экстремум после т.3
            $State = myLog($State, "3.5.1.0.2. (ранее 3.5.1.0.1.2.) Потенциальной т.4 является потенциально абсолютным экстремумом");
            // .В этом случае проводится проверка на пересечение линии от т.2 к т.4 (ЛЦ) ценой на участке 2-4. 
            $LC = LC($State);  // линия целей
            $LC_broken = false;
            for ($i = $State['t2'] + 1; $i < $State['t4']; $i++) {
                if (high($i, $v, __LINE__) > lineLevel($LC, $i)) {
                    $LC_broken = true;
                    break;
                }
            }
            if ($LC_broken) {
                $State = myLog($State, "3.5.1.1. Если пересечение есть, — проверяем анализируемый бар на достижение ЛТ");
                if (low($curBar, $v) >= lineLevel($LT, $curBar)) { //ЛТ не достигнута
                    $State = myLog($State, "3.5.1.1.1 ЛТ не достигнута — следующий бар обрабатывается по пп.3.4. ");
                    $State['next_step'] = 'step_3_4';
                    $State = clearState($State, "2,3,t1,t2,t3,t3-,t3'");
                    $State['curBar']++;
                    return ([$State]);
                } else {
                    $State = myLog($State, "3.5.1.1.2. ЛТ преодолена. Ищем нового кандидата в т.3,т3' по п.3.2.3");
                    $State['next_step'] = 'step_3_2_3';
                    $State = clearState($State, "2,3,t1,t2,t3,t3-");
                    return ([$State]);
                }
            } else { //3.5.1.2. Если пересечения нет, то бар т.4 проверяется на достижение ЛТ
                global $res, $lastT3fromT1, $splitCnt;
                if (low($curBar, $v) <= lineLevel($LT, $curBar)) { // ЛТ  достигнута
                    $State = myLog($State, "3.5.1.2.1. бар т.4($curBar) достиг ЛТ -> фиксируется [Найден Клин] модель Клин");
                    $State['param']['G1'] = "WEDGE";
                    $State['param']['fixed_at'] = $curBar;
                    $State = fix_model($State, "Найден Клин");

                    // $State['next_step'] = 'step_3_2_3';
                    // $State = clearState($State, "2,3,t1,t2,t3,t3-");

                    $State['next_step'] = 'stop';

                    $State2 = $State;
                    $State2['next_step'] = 'step_3_2_3';

                    if (isset($State2['flat_log'])) $State2['flat_log'] = [];
                    $State2['split'] = $splitCnt;
                    $State2 = clearState($State, "2,3,t1,t2,t3,t3-");
                    $State2 = myLog($State2, "Новая ветка после Клина [$splitCnt] поиска модели");
                    $State2['param']['alt_old'] = $State['param']['alt_old'] ?? 0;
                    $lastT3fromT1[$State2['t1']] = $State2['t3'];
                    $splitCnt++;
                    // return ([$State]); 
                    return ([next_T1($State), $State2]); //программа осуществляет поиск т.3’ при тех же точках 1,2,3, или поиск новой т.3. Для этого программа обрабатывает анализируемый бар (в данном случае бар т.4,  при этом достигший ЛТ) начиная с пп.3.2.3
                } else { //проверяемый бар (т4) не достиг ЛТ
                    $limit = ($State['t3'] - $State['t1']) * 10;
                    for ($j = $curBar + 1; $j < ($curBar + $limit) && $j < nBars; $j++) { // лимит поиска ????????????????? бесконечный цикл возможен?
                        if (low($j, $v) <= lineLevel($LT, $j)) { //3.5.1.2.2.1.  анализируемы бар достиг ЛТ
                            $State = myLog($State, "3.5.1.2.1. бар ($j) достиг ЛТ -> фиксируется [Найден Клин] модель Клин");
                            $State['param']['G1'] = "WEDGE";
                            $State['param']['fixed_at'] = $j;
                            $State = fix_model($State, "Найден Клин");
                            // $State['next_step'] = 'step_3_2_3';
                            // $State['curBar'] = $j;
                            // $State = clearState($State, "2,3,t1,t2,t3,t3-");
                            $State['next_step'] = 'stop';

                            $State2 = $State;
                            $State2['next_step'] = 'step_3_2_3';
                            $State2['curBar'] = $j;

                            if (isset($State2['flat_log'])) $State2['flat_log'] = [];
                            $State2['split'] = $splitCnt;
                            $State2 = clearState($State, "2,3,t1,t2,t3,t3-");
                            $State2 = myLog($State2, "Новая ветка после Клина [$splitCnt] поиска модели с бара (" . "$j");
                            $State2['param']['alt_old'] = $State['param']['alt_old'] ?? 0;
                            $lastT3fromT1[$State2['t1']] = $State2['t3'];
                            $splitCnt++;
                            // return ([$State]);
                            return ([next_T1($State), $State2]);
                        } else { // 3.5.1.2.2.2. Если ЛТ не достигнута - проверяемм на достижение уровня т4
                            if (high($j, $v, __LINE__) >= high($State['t4'], $v, __LINE__)) { // уровень т4 достигнут
                                $State = myLog($State, "3.5.1.2.2.2.Б. Если уровень т.4 достигнут на баре $j - переход на п.3.4");
                                $State['next_step'] = 'step_3_4';
                                $State['curBar'] = $j;
                                $State = clearState($State, "2,3,t1,t2,t3,t3-");
                                return ([$State]);
                            }
                            // т4 не достигнут - continue
                        }
                    }
                    $State = myLog($State, "ОШИБКА? - вышли из цикла в 3.5 - лимит глубины на баре ($j)");  // ????????????????????????
                    return ([next_T1($State)]);
                }
            }
        } else { // не абс.экстремум - есть более удаленные от т.3 бары между ним и т.3 - обрабатываем след. бар по пункту 3.4
            $State = myLog($State, "3.5.1.0.1. (ренее 3.5.1.0.1.1.) потенц. т.4 не является абсолютным экстремумом - обрабатываем след.бар по п.3.4 ");
            $State['next_step'] = 'step_3_4';
            $State = clearState($State, "2,3,t1,t2,t3,t3-,t3'");
            $State['curBar']++;
            return ([$State]);
        }
    } // t4 меньше т2
    else { //  t4 больше или равен t2
        // 3.5.2. Уровень потенциальной т.4 дальше от уровня т.1, чем уровень потенциальной т.2. — переход к п.4. 
        if (high($State['t4'], $v, __LINE__) > high($State['t2'], $v, __LINE__)) { // t4 выше t2
            $State = myLog($State, "3.5.2 т.4 дальше от т1 чем т2 - переход к п.3.4 ");
            $State['next_step'] = 'step_4';
            //$State = clearState($State, "2,3,t1,t2,t3,t3-,t3'");
            // $State['curBar']; - чему равен? нужно заносить? ????????????????????? тут равен т4
            return ([$State]);
        } else { // 3.5.3. Уровень потенциальной т.4 равен уровню потенциальной т.2 
            $State = myLog($State, "3.5.3 уровни т.4 и т2  равны - проверяем пробой ЛТ баром т4 ");
            if (low($curBar, $v) > lineLevel($LT, $curBar)) { // curBar равен т4
                $State = myLog($State, "3.5.3.1 ЛТ не пробита баром т4($curBar) - ищем  нового кандидата в т4");
                $State['next_step'] = 'step_3_4';
                $State = clearState($State, "2,3,t1,t2,t3,t3-,t3'");
                $State['curBar']++;
                return ([$State]);
            } else {
                $State = myLog($State, "3.5.3.2 ЛТ пробита баром т4($curBar) - ищем  нового кандидата в т3");
                $State['t3-'] = $State['t3'];
                $State['next_step'] = 'step_2';
                $State = clearState($State, "2,3,t1,t3-");
                $State['curBar'] = $State['t3-'] + 1;
                return ([$State]);
            }
        }
    }
    // сюда не можем попасть
}

function step_4($State)
{ //ПРОВЕРКА НА ПЕРЕСЕЧЕНИЕ ЛЦ ЦЕНОЙ НА УЧАСТКЕ 2-4.

    $v = $State['v'];
    $curBar = $State['curBar']; // равен т4!
    $State = myLog_start($State, "4");
    $LC = LC($State);
    $LT = LT($State);  // лини целей и тренда

    for ($i = $State['t2'] + 1; $i < $State['t4']; $i++) {
        if (high($i, $v) > lineLevel($LC, $i)) { // 4.1 ЛЦ пересекается ценой на участке 2-4 (на баре $i)
            if (low($curBar, $v) < lineLevel($LT, $curBar)) { // ЛТ пробита баром т4 (curBar)
                $State = myLog($State, "4.1.2. ЛТ пробита, программа осуществляет поиск нового кандидата в т.3 - п.2");
                $State['t3-'] = $State['t3'];
                // $State['curBar'] = $State['t3'] + 1; // здесь возможно можно оптимизировать, см. следующую закомментированную строку
                $State['curBar'] = $i;
                $State = clearState($State, "2,3,t1,t3-");
                $State['next_step'] = 'step_2';
                return ([$State]);
            } else {
                $State = myLog($State, "4.1.1. ЛТ не достигнута, программа обрабатывает следующий бар  по пп. 3.4.");
                $State['next_step'] = 'step_3_4';
                $State = clearState($State, "2,3,t1,t2,t3,t3-,t3'");
                $State['curBar']++;
                return ([$State]);
            }
        }
    }
    // 4.2. ЛЦ не пересекается ценой на участке 2-4 – программа переходит к п.5.
    $State = myLog($State, "4.2. ЛЦ не пересекается ценой на участке 2-4 – переходим к п.5.");
    $State['next_step'] = 'step_5';
    return ([$State]);
}

function step_5($State)
{ //5.	ФИКСАЦИЯ МОДЕЛИ ПРИ ПРОБОЕ ЛТ.  ПОИСК Т.5. ПОИСК Т.6.

    global $splitCnt;
    $v = $State['v'];
    $curBar = $State['curBar'];
    $State = myLog_start($State, "5");

    $check = checkUnique($State, "v,curBar,t1,t2,t3,t4,alt,draw_flag");
    if ($check) {
        $State = myLog($State, $check);
        $State['next_step'] = 'stop';
        return ($State);
    }

    if ($curBar >= nBars) { // дошли до границы графика
        $State = myLog($State, "Дошли до правой границы графика, поиск следующей т1");
        return ([next_T1($State)]);
    }

    $t5_level = 1000000000; // уровень нужен для ситуации, когда т.4 содержит сонаправленный с т.5 бар
    // проверяем текущий бар на касание ЛТ
    $LT = LT($State);
    if (low($curBar, $v) < lineLevel($LT, $curBar)) { // $curBar пробил ЛТ
        $State = myLog($State, "текущий бар ($curBar) пробил ЛТ -> 5.1 проверяем, преодолен ли ур.т4 между т4 и тек.баром (не включая)");

        // 5.1 проверяем, преодолен ли ур.т.4 между т.4 и тек.баром (не включая)
        for ($i = $State['t4'] + 1; $i < $curBar; $i++) {
            if (high($i, $v, __LINE__) > high($State['t4'], $v, __LINE__)) {  // ур.т4 преодолен на баре $i
                $State = myLog($State, "5.1.2.  ур.т4 преодолен на баре ($i) - ищем т.5");
                $State['conf_t4'] = $i;
                //5.1.2. Если преодолен уровень т.4 программа ищет абсолютный экстремум на участке от т.4 (не включая) до бара, достигшего уровень т.4 (включительно), в качестве точки 5 (далее – т.5).
                $t5_candidate = false;

                if (is_extremum($State['t4'], not_v($v))) $t5_level = low($State['t4'], $v); // если бару т.4 принадлежит экстремум, сонаправленный с т.5, т.5 должен быть ниже этого экстремума
                for ($j = $State['t4'] + 1; $j <= $i; $j++) {
                    // if (is_extremum($j, $v)) if (!$t5_candidate || low($j, $v) < low($t5_candidate, $v)) $t5_candidate = $j; // первая часть || верна до первого экстремума, а вторая верна если найден новый экстремум
                    if (is_extremum($j, $v)) if ((!$t5_candidate || low($j, $v) < low($t5_candidate, $v)) && low($j, $v) < $t5_level) $t5_candidate = $j; // первая часть || верна до первого экстремума, а вторая верна если найден новый экстремум
                }
                if ($t5_candidate) { //5.1.2.1. Если экстремум найден – это потенциальная т.5.
                    $State = myLog($State, "5.1.2.1. экстремум найден – это потенциальная т.5. ($t5_candidate)");
                    if ($i == $t5_candidate) { //5.1.2.1.1. Т.5 принадлежит бару, который пробил уровень т.4.

                        // Программа осуществляет проверку, является ли бар, который содержит т.5 (и который пробил уровень т.4) баром, который также содержит и абсолютный экстремум на участке от этого бара (включительно) до точки пробоя ЛТ, (т.е. текущим баром) (не включая).
                        $isAbsExtr = false;
                        for ($k = $t5_candidate; $k < $curBar; $k++) {  // до точки пробоя ЛТ (не включительно) 
                            if (is_extremum($k, not_v($v))) if (!$isAbsExtr || high($k, $v, __LINE__) > high($isAbsExtr, $v, __LINE__)) $isAbsExtr = $k;
                        }
                        if ($isAbsExtr == $t5_candidate) { //5.1.2.1.1.1. Бар т.5 содержит абсолютный экстремум , сонаправленный с т.4.
                            $State = myLog($State, "5.1.2.1.1.1. Бар т.5 содержит абсолютный экстремум, сонаправленный с т.4.");
                            //a. Для рассматриваемой модели в отчёте будет отображаться: p5conf2 = 	0
                            //                            $State['param']['p5conf2'] = 0;
                            //                            $State['param']['fixed_at']=$curBar;
                            //                            $State = fix_model($State, "серая модель p5conf2 = 0");
                            //                            unset($State['param']['p5conf2']);

                            // зафиксирована модель (серая) - дальше расчет для НОВОЙ модели
                            $State['t4'] = $isAbsExtr; //б.  данный абсолютный экстремум, сонаправленный с т.4 является новой потенциальной т.4. для новой модели.
                            // Программа строит ЛЦ через данную т.4 и проверяет ЛЦ на отсутствие пересечений ценой на участке 2-4. 
                            $LC_new = ['bar' => $State['t2'], 'level' => high($State['t2'], $v), 'angle' => (high($State['t4'], $v) - high($State['t2'], $v)) / ($State['t4']  - $State['t2'])]; // J $t5_candidate используется т.к. он равен $state['t4']
                            //   $LC_touch=false;
                            for ($l = $State['t2'] + 1; $l < $State['t4']; $l++) {
                                if (high($l, $v, __LINE__) > lineLevel($LC_new, $l)) { //5.1.2.1.1.1.А. На участке 2-4 ЛЦ пересекается ценой. В этом случае программа ищет новую т.3, для чего обрабатывает бар, достигший ЛТ по п.2 и далее по алгоритму
                                    $State = myLog($State, "5.1.2.1.1.1.А. На участке 2-4 ЛЦ пересекается ценой на ($l) - ищем новую т.3 - п.2");
                                    $State['t3-'] = $State['t3'];
                                    $State['curBar'] = $curBar; //программа ищет новую т.3, для чего обрабатывает бар, достигший ЛТ по п.2 и далее по алгоритму
                                    $State['next_step'] = 'step_2';
                                    $State = clearState($State, "2,3,t1,t2,t3-");
                                    return ([$State]);
                                }
                            }
                            $State = myLog($State, "5.1.2.1.1.1.Б. На участке 2-4 ЛЦ не пересекается ценой. - фиксируем [Модель без 6] и переход к п.6.");
                            //$State['param']['p5conf2'] = 1;
                            $State['param']['fixed_at'] = $curBar;
                            $State = fix_model($State, "Модель без 6");
                            //                            $State['next_step'] = 'step_6';
                            //                            // $State['curBar'] = ??????????????????????
                            //                            $State['t4'] = $t5_candidate; // t4???????????????????
                            //                            $State['t5'] = $t5_candidate;
                            unset($State['t5']);
                            $State['next_step'] = 'step_6';
                            return ([$State]);
                        } else { //5.1.2.1.1.2. Бар т.5 не содержит абсолютный экстремум, сонаправленный с т.4. Модель получает статус сформированной [Сформирована ??]. в отчёте будет отображаться: p5conf2 : 1

                            $State = myLog($State, "5.1.2.1.1.2. Бар т.5 не содержит абсолютный экстремум, сонаправленный с т.4. Модель=, переход на п.6");
                            //    $State['param']['p5conf2'] = 1;
                            $State['t5'] = $t5_candidate;
                            $State['param']['fixed_at'] = $curBar;
                            $State = fix_model($State, "модель п.5.1.2.1.1.2");
                            $State['next_step'] = 'step_6';
                            return ([$State]);
                        }
                    } else { // 5.1.2.1.2. Т.5. принадлежит бару между баром т.4 и баром, пробившем уровень т.4. Программа переходит к п.6
                        $State = myLog($State, "5.1.2.1.2. Т.5. принадлежит бару между баром т.4 и баром, пробившем уровень т.4. Программа переходит к п.6");
                        $State['t5'] = $t5_candidate;
                        $State['next_step'] = 'step_6';
                        return ([$State]);
                    }
                } else { //5.1.2.2. Экстремум не найден, программа осуществляет поиск следующего кандидата на т.4. Для этого программа обрабатывает бар, преодолевший уровень потенциальной т.4 по п.3.4.
                    $State = myLog($State, "5.1.2.2. Экстремум не найден - ищем новую т4 (п.3.4 - бар, преодолевший ур.т4 : $i");
                    $State['next_step'] = 'step_3_4';
                    $State = clearState($State, "2,3,t1,t2,t3,t3-,t3'");
                    $State['curBar'] = $i;
                    return ([$State]);
                }
            }
        }
        // ур.т4 не преодолен на уч. 4-тек.бар
        $State = myLog($State, "5.1.1. уровень т.4 не преодолен, модель получает статус сформированной [Сформирована модель без 6].");
        $State['param']['fixed_at'] = $curBar;
        $State = fix_model($State, "Модель без 6");
        $State['next_step'] = 'step_6';
        return ([$State]);
    } else { //5.2. ЛТ не достигнута
        if ($State['curBar'] == $State['t4']) { //5.2 Если анализируемый бар содержит потенциальную т.4, программа проверяет следующий бар по п.5.
            $State = myLog($State, "5.2. ЛТ не достигнута. Анализируемый бар содержит потенциальную т.4, проверяем следующий бар по п.5");
            $State['next_step'] = 'step_5';
            $State['curBar']++;
            return ([$State]);
        }

        // Если анализируемый бар не содержит потенциальную т.4, то он подвергается одновременной проверке:
        //
        // - на экстремальность по правилу N1 (в качестве т.5);
        $isExtremum = (is_extremum($State['curBar'], $v)) ? true : false;
        //- на наличие между анализируемым баром и баром потенциальной т.4 баров, содержащих значения цены, более удаленные от потенциальной т.4, чем наиболее удаленное от т.4 значение анализируемого бара, а также
        $isLowerPriceBetween = false;
        if (is_extremum($State['t4'], not_v($v))) $t5_level = low($State['t4'], $v);
        // for ($i = $State['t4'] + 1; $i < $curBar; $i++) if (low($i, $v) <= low($curBar, $v)) { // поменял < на <= чтобы проверить и на наличие между анализируемым баром и баром т.4 значений цены равных наиболее удаленному значению цены анализируемого бара
        for ($i = $State['t4'] + 1; $i < $curBar; $i++) if (low($i, $v) <= low($curBar, $v) || $t5_level <= low($curBar, $v)) { // поменял < на <= чтобы проверить и на наличие между анализируемым баром и баром т.4 значений цены равных наиболее удаленному значению цены анализируемого бара
            $isLowerPriceBetween = true;
            break;
        }

        if ($isExtremum && !$isLowerPriceBetween) { //5.2.1. Если бар является экстремумом и он потенциально абсолютный, то данный бар является потенциальной т.5.   Программа проверяет данный бар на достижение уровня т.4.
            $State = myLog($State, "5.2.1. Анализируемый бар является потенциально абс.экстремумом (потенц.т5)-  проверяем данный бар ($curBar) на достижение уровня т.4");
            if (high($State['curBar'], $v, __LINE__) >= high($State['t4'], $v, __LINE__)) { //5.2.1.1. Если уровень т.4 достигнут 
                $State = myLog($State, "5.2.1.1. Уровень т.4 подтверждён баром потенциальной т.5");
                if (is_extremum($State['curBar'], not_v($v))) { //5.2.1.1.1. Бар т.5 содержит экстремум, сонаправленный с т.4.
                    $State = myLog($State, "5.2.1.1.1. Бар т.5  содержит экстремум, сонаправленный с т.4. Бар т.5 становится новой потенциальной т.4.");
                    // unset ($State['t5']);
                    $State['t4'] = $curBar;
                    $LC_new = ['bar' => $State['t2'], 'level' => high($State['t2'], $v), 'angle' => (high($State['t4'], $v) - high($State['t2'], $v)) / ($State['t4']  - $State['t2'])]; // ЛЦ перестраивается по новой т.4 и проверяется на пересечения ценой на 2-4
                    for ($l = $State['t2'] + 1; $l < $State['t4']; $l++) {
                        if (high($l, $v, __LINE__) > lineLevel($LC_new, $l)) { //5.2.1.1.1.А. На участке 2-4 ЛЦ пересекается ценой. Программа обрабатывает следующий бар по пп. 3.4. 
                            $State = myLog($State, "5.2.1.1.1.А. На участке 2-4 ЛЦ пересекается ценой на баре $l");
                            $State['next_step'] = 'step_3_4';
                            $State = clearState($State, "2,3,t1,t2,t3,t3-,t3'");
                            $State['curBar']++;
                            return ([$State]);
                        }
                        // else {
                        //     $State = myLog($State, "5.2.1.1.1.Б. На участке 2-4 ЛЦ не пересекается ценой");
                        //     $State['next_step'] = 'step_5';
                        //     $State['curBar']++;
                        //     return ([$State]); 
                        // }
                    }
                    $State = myLog($State, "5.2.1.1.1.Б. На участке 2-4 ЛЦ не пересекается ценой");
                    $State['next_step'] = 'step_5';
                    $State['curBar']++;
                    return ([$State]);
                }
                // $State = myLog($State, "5.2.1.1 уровень т.4 достигнут Модель фиксируется [Подтверждена т.4]. Программа переходит к п.6 (* было раньше +и расщепление в п.3.4)");
                // $State = myLog($State, "5.2.1.1 уровень т.4 достигнут  Программа переходит к п.6 (* было раньше +и расщепление в п.3.4)");
                $State = myLog($State, "5.2.1.1.2. Бар т.5 не содержит абсолютный экстремум, сонаправленный с т.4.  Программа переходит к п.6 (* было раньше +и расщепление в п.3.4)");
                $State['conf_t4'] = $curBar;
                $State['t5'] = $curBar;
                $State['curBar']++;
                $State = myLog($State, "Подтверждение т4 баром т.5 = $curBar");
                //                $State = fix_model($State, "Подтверждена т.4");
                $State = myLog($State, "Подтверждение т4");
                // $State['t4']=$curBar; // ?????????????????????? t4 фиксируется равной t5?
                $State['next_step'] = 'step_6';
                // добавлено расщепление 09.08.2019 "Параллельно программа ищет альтернативную т4, для чего анализирует бар, подтвердивший т.4 по п.3.4 алгоритма
                // 20200925 - убрали ветвление при совместной проверке
                //                $State2 = $State;
                //                $State2['split'] = $splitCnt;
                //                $State2['status'] = $State2['param'] = [];
                //                if(isset($State2['flat_log']))$State2['flat_log']=[];
                //                $State2 = clearState($State2, "2,3,t1,t2,t3,t3-,t3'");
                //                $State2 = myLog($State2, "New State (split_$splitCnt)   5.2.1.1");
                //                $splitCnt++;
                //                $State2['next_step'] = 'step_3_4';
                //                $State2['mode'] = 'selected';
                //                $State2['param']['alt_old'] = 1;
                //                return ([$State, $State2]);
                return ([$State]);
            } else { //5.2.1.2. Если уровень т.4 не достигнут. Программа переходит к п.6, а параллельно программа проверяет следующий бар в соответствии с данным пунктом (т.е. по п.5), чтобы найти другую модель с теми же т.1,т.2,т.3, но другой т.5.
                // расщеплееие $State
                $State = myLog($State, "расщепление State (split_$splitCnt)  5.2.1.2. Если уровень т.4 не достигнут на ($curBar). Новый - на п.5");
                $State2 = $State; // #State2- новый
                $State2['status'] = $State2['param'] = [];
                if (isset($State2['flat_log'])) $State2['flat_log'] = [];
                $State2['status'] = $State2['param'] = [];
                $State2['split'] = $splitCnt;
                $State2 = myLog($State2, "New State (split_$splitCnt)  5.2.1.2. Если уровень т.4 не достигнут. Программа переходит к п.6, а параллельно программа проверяет следующий бар по п.5");
                $splitCnt++;
                $State['draw_flag'] = true;
                $State['next_step'] = 'step_6';
                $State2['curBar']++;
                $State2['param']['alt_old'] = $State['param']['alt_old'] ?? 0;

                $State2['next_step'] = 'step_5';
                //$State2['mode'] = 'selected';
                $State['t5'] = $curBar;
                // $State['t4']=$curBar; // ?????????????????????? t4 фиксируется равной t5?
                //  file_put_contents("_5_2_1_2_newSplit_".$State2['split'].".json",json_encode($State2)); //TMP!!!1104
                return ([$State, $State2]);
                //return([$State]);
            }
        } else { //5.2.2. Если бар не является экстремумом, либо не является потенциально абсолютным, то программа проверяет его на достижение уровня потенциальной т.4.
            if (high($State['curBar'], $v, __LINE__) >= high($State['t4'], $v, __LINE__)) { //5.2.2.1. Если бар достигает уровня т.4, то программа ищет абсолютный экстремум между т.4 и точкой подтверждения т.4 (т.е. повторного после т.4 достижения уровня т.4) в качестве т.5
                $State['conf_t4'] = $curBar;
                $State = myLog($State, "5.2.2.1. Подтверждение т4 баром = $curBar");
                $isExtrT5Found = false;
                if (is_extremum($State['t4'], not_v($v))) $t5_level = low($State['t4'], $v);
                // $State=myLog($State,"t5_level = $t5_level t4 =  $State[t4]  отрицание = " . !$isExtrT5Found . low($i, $v). "i - экстремум? =" . is_extremum($i, $v)); // 222
                for ($i = $State['t4'] + 1; $i < $curBar; $i++) { // ищет абсолютный экстремум между т.4 и точкой подтверждения т.4 в качестве т.5
                    if (
                        is_extremum($i, $v)
                        && (!$isExtrT5Found || (low($i, $v) < low($isExtrT5Found, $v))) // экстремум должен быть абсолютным
                        && low($i, $v) < $t5_level
                    ) {
                        $isExtrT5Found = $i;
                        $t5_level = low($isExtrT5Found, $v);
                        $State = myLog($State, "найден кандидат в т.5 абсолют = $isExtrT5Found");
                    }
                }
                if ($isExtrT5Found) { //5.2.2.1.1. При наличии т.5 модель фиксируется, а   программа переходит к п.6. Параллельно программа ищет альтернативную т.4, для чего анализирует бар, подтвердивший т.4 по п.3.4. и далее.
                    $State = myLog($State, "5.2.2.1.1. При наличии т.5 программа переходит к п.6. ");
                    //                    $State = myLog($State, "расщепление State (split_$splitCnt)  5.2.2.1.1 - новый на п.3.4");
                    //                    $State2 = $State;
                    //                    $State2['status'] = $State2['param'] = [];
                    //                    if(isset($State2['flat_log']))$State2['flat_log']=[];
                    //                    $State2['split'] = $splitCnt;
                    $State['t5'] = $isExtrT5Found;
                    $State['next_step'] = 'step_6';
                    //                    $State = fix_model($State, "модель в п.5.2.2.1.1");
                    //                    $State2 = clearState($State2, "2,3,t1,t2,t3,t3-,t3'");
                    //                    $State2 = myLog($State2, "New State (split_$splitCnt)   5.2.2.1.1");
                    //                    $splitCnt++;
                    //                    $State2['next_step'] = 'step_3_4';
                    //                    $State2['mode'] = 'selected';
                    //                    //     $State2['models']=[];
                    //                    $State2['param']['alt_old'] = 1;
                    //                    return ([$State, $State2]);
                    return ([$State]);
                } else { //5.2.2.1.2. При отсутствии т.5, программа переходит к поиску новой т.4, для чего обрабатывает бар, достигший уровень т.4 начиная с п.3.4.
                    $State = myLog($State, "5.2.2.1.2. При отсутствии т.5, программа переходит к поиску новой т.4, для чего обрабатывает бар, достигший уровень т.4 начиная с п.3.4.");
                    $State = clearState($State, "2,3,t1,t2,t3,t3-,t3'");
                    $State['next_step'] = 'step_3_4';
                    return ([$State]);
                }
            } else { //5.2.2.2. Если бар не достигает уровня потенциальной т.4, то программа обрабатывает следующий бар с начала этого пункта (т.е. по п.5).
                $State = myLog($State, "5.2.2.2. бар не достигает уровня потенциальной т.4, обрабатываем следующий бар по п.5");
                $State['curBar']++;
                $State['next_step'] = 'step_5';
                return ([$State]);
            }
        }
    }
}

function step_6($State)
{ //6.	ПОИСК КАCАТЕЛЬНЫХ И ПОСТРОЕНИЕ ВСПОМОГАТЕЛЬНОЙ МП.

    global $res;
    $v = $State['v'];
    $curBar = $State['curBar'];
    $State = myLog_start($State, "6");

    // поиск т2'
    $isT2s_found = false;
    for ($i = $State['t2']; $i > $State['t1']; $i--) { // $i - проверяем как кандидата в т2' на 1 (не включая) - 2 (включая)
        // $isTouch = false; // J заменил название переменной
        $isBroken = false;
        $LC_s = ['bar' => $i, 'level' => high($i, $v), 'angle' => (high($State['t4'], $v) - high($i, $v)) / ($State['t4'] - $i)];

        for ($j = $State['t2']; $j >= $State['t1']; $j--) if ($i != $j) { // пропускаем при проверке бар кандидата в т.2'
            if (high($j, $v) >= lineLevel($LC_s, $j)) { // есть касание J При >= в качестве т.2' будет выбрана первая по времени
                // if (high($j, $v) > lineLevel($LC_s, $j)) { // J это было неверное исправление, т.к. такие т.2' будут найдены несколько
                $isBroken = true;
                break;
            }
        }
        // if (!$isTouch) { // t2' найдена J заменил название переменной
        if (!$isBroken) { // t2' найдена
            $State = myLog($State, "т2' найдена ($i)");
            $isT2s_found = $i; //LC_s - ЛЦ'
            break;
        }
    }
    if ($isT2s_found) { //6.1.1. Если т.2’ найдена (в т.ч. в случае, если т.2’ совпадает с т.2), то программа рассчитывает Линию целей по касательной от т.2’ к т.4 (дал ее -ЛЦ’). Далее проверяется, имеет ли рассматриваемая модель статус [Сформирована модель без 6].
        $LC_s = ['bar' => $isT2s_found, 'level' => high($isT2s_found, $v), 'angle' => (high($State['t4'], $v) - high($isT2s_found, $v)) / ($State['t4'] - $isT2s_found)];
        $State['t2\''] = $isT2s_found;
        if (isset($State['status']['Модель без 6'])) { //6.1.1.1. Модель имеет статус [Сформирована модель без 6].   – программа переходит к п. 7.
            $State = myLog($State, "6.1.1.1. Модель имеет статус [Модель без 6] –  переходим к п.7");
            $State['next_step'] = 'step_7';
            return ([$State]);
        } else { //6.1.1.2. Модель не имеет статуса [Сформирована модель без 6].  – программа переходит к пп.6.2.
            $State = myLog($State, "6.1.1.2. Модель не имеет статуса [Модель без 6].  – переходим к пп.6.2.");
            // пусто - дальше пойдет на 6.2
        }
    } else { //6.1.2. Если т.2’ не найдена (т.е. невозможно построить такую линию по точкам т.2’ и т.4, которая не будет содержать лишних точек на участке 1-2), то это модель без вспомогательной МП. Программа переходит к п.7.

        $State['next_step'] = 'step_7';
        return ([$State]);
    }

    //6.2. ПОИСК Т.3’ ВСПОМОГАТЕЛЬНОЙ МП.
    $State = myLog_start($State, "6.2");

    //    $State = myLog($State, "ЗАГЛУШКА п.6.2 - ищем новую т.1"); // ЗАГЛУШКА, новая т1
    //    $State=fix_model($State,"ЗАГЛУШКА п.6.2"); // ЗАГЛУШКА, новая т1
    //    return([next_T1($State)]); // ЗАГЛУШКА, новая т1

    //    $res['log'][]="ERROR - test! $curBar ".$State['t5'];
    //    if(!isset($State['t5']))$res['log'][]="ERROR - нет т5! ($curBar) ";
    $t3_ = (isset($State['t3\''])) ? $State['t3\''] : $State['t3'];
    $State = myLog($State, "t3_ установлено на баре ($t3_)");
    // $t3_ = $State['t3'];
    //$State['param']['__t3_']=$t3_;
    $LT_vmp = ['bar' => $t3_, 'level' => low($t3_, $v), 'angle' => (low($State['t5'], $v) - low($t3_, $v)) / ($State['t5'] - $t3_)];
    $isIntersectionFound = false;
    for ($i = $State['t3'] + 1; $i < $State['t5']; $i++) if ($i !== $t3_) { // не проверяем на т.3_
        if (low($i, $v) < lineLevel($LT_vmp, $i)) { //6.2.1. Пересечения найдены. Линия т.3/т.3’-т.5 в этом случае не отображается на графике. Программа ищет на участке от бара т.3/т.3’ (включая) до бара т.4 (не включая) (далее участок 3’-4) такую точку 3’ вспомогательной Модели Притяжения (далее т.3’мп), через которую возможно построить линию, которая не будет иметь пересечений с ценой на участке от т.3/т.3’ до т.5.
            $State = myLog($State, "пересечение с ЛТвмп найдено на баре ($i)");
            $isIntersectionFound = true; // пересечение ЛТ вспомогательной МП от т.3 или т.3' основной модели
            $isT3vmpFound = false;

            // ???? можно ли делать цикл от $i а не от t3_? (да, можно:)
            for ($j = $i; $j < $State['t4']; $j++) { //перебор возможных т3'вмп
                // for ($j = $t3_; $j < $State['t4']; $j++) { //перебор возможных т3'вмп
                //   $State = myLog($State, "кандидат в т3'мп ($j)");
                $LT_vmp_tmp = ['bar' => $j, 'level' => low($j, $v), 'angle' => (low($State['t5'], $v) - low($j, $v)) / ($State['t5'] - $j)]; // ЛТ вспомогательной МП через т.3'мп
                // $isTouch_new = false;
                $isBroken_new = false;
                for ($k = $t3_ + 1; $k < $State['t5']; $k++) if ($k !== $j) { // проверка всех баров начиная с $t3 на пробой ЛТ вспом. МП через т.3'мп (не проверяем на т3'мп)
                    // if (low($k, $v) <= lineLevel($LT_vmp_tmp, $k)) {
                    if (low($k, $v) < lineLevel($LT_vmp_tmp, $k)) {
                        $lowtmp = low($k, $v);
                        $tmplvl = lineLevel($LT_vmp_tmp, $k);
                        $isBroken_new = true;
                        // $State = myLog($State, "кандидат в т3'мп отменяется баром ($k) т.к. его значение ($lowtmp) пробивает ЛТ ($tmplvl) ");
                        break;
                    }
                }
                // if (!$isTouch_new) {
                if (!$isBroken_new) {
                    $isT3vmpFound = $j;
                    break;
                }
            }
            if ($isT3vmpFound) { // нашли т3' вспомогательной МП
                //6.2.1.1. Если т.3’мп найдена, то программа строит Линию тренда вспомогательной модели притяжения по касательной от т.3’мп к т.5 (далее – ЛТмп’) и рассчитывает уровень пересечения ЛЦ’ и ЛТмп’ (далее – расчётная т.6)
                // $State=myLog($State,"6.2.1.1. нашли t3мп' на баре ($isT3vmpFound)");
                $State['t3\'мп'] = $isT3vmpFound;

                // ищем расчетную т.6
                $y_top = lineLevel($LC_s, $isT3vmpFound); // цена на ЛЦ' на баре т3'мп
                $y_bottom = low($isT3vmpFound, $v);
                $dy = $LT_vmp_tmp['angle'] - $LC_s['angle']; // на сколько сближаются ЛЦ' и ЛТ'вмп за 1 бар
                $State = myLog($State, "6.2.1.1. т.3'мп найдена ($isT3vmpFound), скорость сближения линий (dy) = $dy");
                if ($dy > 0) { // 6.2.1.1.1. Если расчётная т.6 найдена программа проверяет, пробивает ли бар т.5 уровень расчётной т.6
                    $t6 = $isT3vmpFound + ($y_top - $y_bottom) / $dy; // на каком баре находится пересечение (t6) - дробное значение
                    $t6_level = lineLevel($LC_s, $t6);
                    //$State=fix_model($State,"Найдена расчётная т.6 ($t6 $t6_level isT3vmpFound:$isT3vmpFound y_top:$y_top y_bottom:$y_bottom dy:$dy)");
                    // $State['t6мп']=substr($t6,0,7).' ('.substr(($v=='low')?($t6_level*1):(-1)*$t6_level,0,7).')'; //ЗАГЛУШКА
                    // $State['param']['auxP6'] = substr(" " . abs($t6_level), 1, 7);
                    // $State['param']['auxP6t'] = substr(" " . $t6, 1, 7);
                    $State['param']['auxP6'] = round(" " . abs($t6_level), 5);
                    $State['param']['auxP6t'] = round(" " . $t6, 7);
                    $State['param']['auxP6t_from'] = __LINE__;
                    $State = myLog($State, "рассчитали auxP6 в 6.2.1.1. : (" . $State['param']['auxP6t'] . ") " . $State['param']['auxP6']);
                    $State = myLog($State, "  draw_flag= " . ($State['draw_flag'] ?? 0)); // 2222222222222222222222222
                    //            $State['param']['_tmp']='line_920 6.2.1.1.1';
                    //////// Добавляем проверку на соотношение т.2'-т.4 к т.4-т.6 //////
                    $p24_4aux6 = ($State['t4'] - $State['t2\'']) / ($State['param']['auxP6t'] - $State['t4']);
                    if ($p24_4aux6 < PAR246aux) {
                        $State = myLog($State, "Т.6 слишком далеко (в 6.2.1.1.1.)");
                        unset($State['param']['auxP6'], $State['param']['auxP6t']);
                        //// убрано при корректировке пункта - идем теперь на 6.3 13/05/21
                        // if(($State['draw_flag'] ?? false))$State['next_step'] = 'stop';
                        // else $State['next_step'] = 'step_3_4'; 
                        // $State = clearState($State, "2,3,t1,t2,t3,t3-,t3'"); //// unset всех полей кроме перечисленных и служебных
                        // return ([$State]); 
                    } else {
                        //////// конец проверки ///////

                        // $State = fix_model($State, "Найдена расчётная т.6"); // J перенёс на 2 стрроки ниже, т.к. модель фиксируется в зависимости от указанного ниже условия
                        if (high($State['t5'], $v) <= $t6_level) { //6.2.1.1.1. а. Если бар т.5 не пробивает уровень расчётной т.6, то программа переходит к пп.6.3. В этом случае в отчете отображается: AUX	: AM
                            $State = myLog($State, "6.2.1.1.1. а. Бар т.5 не пробивает уровень расчётной т.6,  программа переходит к пп.6.3. В отчете отображается: AUX	: AM");
                            // $State = myLog($State, "  draw_flag= " . ($State['draw_flag']??0)); // 2222222222222222222222222
                            $State['param']['AUX'] = 'AM';
                            // $State['param']['fixed_at']=$State['t5'];
                            // // $State = fix_model($State, "Найдена расчётная т.6"); // J перенесённая сверху строка
                            break; // переход к п.6.3
                        } else { // 6.2.1.1.1.б. Если бар т.5 пробивает уровень расчётной т.6, то данная МП не может быть построена программа ищет новую т.4. для чего следующий бар проверяется по подпункту 3.4.
                            $State = myLog($State, "6.2.1.1.1.б. Бар т.5 пробивает уровень расчётной т.6, данная МП не может быть построена программа ищет новую т.4. -> п.3.4");
                            //  $State['next_step'] = 'step_3_4';
                            if (($State['draw_flag'] ?? false)) $State['next_step'] = 'stop';
                            else $State['next_step'] = 'step_3_4';
                            $State['curBar'] = $State['t5'] + 1;
                            $State = clearState($State, "2,3,t1,t2,t3,t3-,t3'"); //// unset всех полей кроме перечисленных и служебных

                            unset($State['param']['auxP6']);
                            unset($State['param']['auxP6t']);

                            return ([$State]);
                        }
                    }
                } else { //6.2.1.1.2. Прогнозная 6-ая не найдена (т.е. линии не пересекаются). В этом случае ЛТмп не отображается на графике.
                    //Модель фиксируется [Найдена модель без вспомогательной МП], в отчете отображается:AUX : NoAM
                    $State = myLog($State, "Прогнозная 6-ая не найдена. Модель фиксируется БЕЗ УЧЕТА Т5 [Модель без вспомогательной МП], AUX : NoAM - идем на п6.3 (корр.алг)");
                    $State['param']['AUX'] = 'NoAM';
                    if (isset($State['t3\'мп'])) unset($State['t3\'мп']);
                    // $State['param']['fixed_at']=$State['t5'];
                    // // $State = fix_model($State, "Модель без вспомогательной МП", true);
                    // $State = fix_model($State, "Модель без вспомогательной МП");
                    // $State['next_step']='step_7';
                    // return([$State]); убрано при корректировке пункта - идем теперь на 6.3
                }
            } else { //6.2.1.2. Если т.3’мп не найдена, то модель фиксируется [Найдена модель без вспомогательной МП], в отчете отображается: AUX : NoAM. Программа переходит к п.7
                $State = myLog($State, "6.2.1.2. т.3'мп не найдена, модель фиксируется [Модель без вспомогательной МП], AUX : NoAM. Переходим  к п.7");
                $State['param']['AUX'] = 'NoAM';
                if (isset($State['t3\'мп'])) unset($State['t3\'мп']);
                // $State['param']['fixed_at']=$State['t5'];
                // $State = fix_model($State, "Модель без вспомогательной МП");
                $State['next_step'] = 'step_7';
                return ([$State]);
            }
        }
    } // поиск в цикле пересечения с т3/t3'-t5

    if (!$isIntersectionFound) { //6.2.2. Пересечения не найдены. В этом случае программа рассчитывает уровень пересечения ЛТмп и ЛЦ’ (это уровень расчетной т.6). Возможны 2 варианта по аналогии с подпунктами 6.2.1.1.1. и 6.2.1.1.2.
        //       if(!isset($State['t5']))$res['log'][]="ERROR - нет т5! ($curBar) ";

        // далее вставка "аналог пп 6.2.1.1.1. и 6.2.1.1.2.)" /////////////////////////////////////////////////////////////
        $State = myLog($State, "6.2.2. Пересечения ЛТ не найдены.Рассчитываем уровень пересечения ЛТмп и ЛЦ’ (по аналогии с пп 6.2.1.1.1. и 6.2.1.1.2.)");
        // используем $LT_vmp и $LC_s
        // ищем расчетную т6
        $y_top = lineLevel($LC_s, $t3_); // цена на ЛЦ' на баре т3'вспомогательной мп
        $y_bottom = low($t3_, $v);
        $dy = $LT_vmp['angle'] - $LC_s['angle']; // на сколько сближаются ЛЦ' и ЛТ'вмп за 1 бар
        if ($dy > 0) { // аналог 6.2.1.1.1. Если расчётная т.6 найдена. Модель фиксируется [Найдена расчётная т.6], программа проверяет, пробивает ли бар т.5 уровень расчётной т.6
            $t6 = $t3_ + ($y_top - $y_bottom) / $dy; // на каком баре находится пересечение (t6) - дробное значение
            $t6_level = lineLevel($LC_s, $t6);
            //$State['param']['_t6']=substr($t6,0,7); //ЗАГЛУШКА
            //$State['param']['_t6_price']=substr(($v=='low')?($t6_level*1):(-1)*$t6_level,0,7); //ЗАГЛУШКА
            //  $State['t6мп']=substr($t6,0,7).' ('.substr(($v=='low')?($t6_level*1):(-1)*$t6_level,0,7).')'; // ЗАГЛУШКА
            // $State['param']['auxP6'] = substr(" " . abs($t6_level), 0, 7);
            // $State['param']['auxP6t'] = substr($t6, 0, 7);
            $State['param']['auxP6'] = round(" " . abs($t6_level), 5);
            $State['param']['auxP6t'] = round($t6, 7);
            $State['param']['auxP6t_from'] = __LINE__;
            $State = myLog($State, "рассчитали auxP6 в 6.2.2.: (" . $State['param']['auxP6t'] . ") " . $State['param']['auxP6']);
            //   $State['param']['_tmp']='line_975 аналог 6.2.1.1.1';

            //////// Добавляем проверку на соотношение т.2'-т.4 к т.4-т.6 //////   
            $p24_4aux6 = ($State['t4'] - $State['t2\'']) / ($State['param']['auxP6t'] - $State['t4']);
            $State = myLog($State, "p24_4aux6 = $p24_4aux6 ");
            if ($p24_4aux6 < PAR246aux) {
                $State = myLog($State, "Т.6 слишком далеко в 6.2.2.");
                unset($State['param']['auxP6'], $State['param']['auxP6t']);
                if (isset($State['t3\'мп'])) unset($State['t3\'мп']);
                //// убрано при корректировке пункта - идем теперь на 6.3 13/05/21                
                // if(($State['draw_flag'] ?? false))$State['next_step'] = 'stop';
                // else $State['next_step'] = 'step_3_4';
                // // $State['next_step'] = 'step_3_4';
                // $State = clearState($State, "2,3,t1,t2,t3,t3-,t3'"); //// unset всех полей кроме перечисленных и служебных
                // return ([$State]);
            } else {

                //// конец вставки ///

                // $State = fix_model($State, "аналог Найдена расчётная т.6"); // J строка смещена вниз
                if (high($State['t5'], $v) <= $t6_level) { //6.2.1.1.1. а. Если бар т.5 не пробивает уровень расчётной т.6, то программа переходит к пп.6.3. В этом случае в отчете отображается: AUX	: AM
                    // $State['param']['fixed_at']=$State['t5'];
                    // $State = fix_model($State, "Найдена расчётная т.6");
                    $State = myLog($State, "аналог 6.2.1.1.1. а. Бар т.5 не пробивает уровень расчётной т.6,  программа переходит к пп.6.3. В отчете отображается: AUX	: AM");
                    $State['param']['AUX'] = 'AM';
                    // переход к п.6.3
                } else { // аналог 6.2.1.1.1.б. Если бар т.5 пробивает уровень расчётной т.6, то данная МП не может быть построена программа ищет новую т.4. для чего следующий бар проверяется по подпункту 3.4.
                    $State = myLog($State, "аналог 6.2.1.1.1.б. Бар т.5 пробивает уровень расчётной т.6, данная МП не может быть построена программа ищет новую т.4. -> п.3.4");
                    //$State['next_step'] = 'step_3_4';
                    if (($State['draw_flag'] ?? false)) $State['next_step'] = 'stop';
                    else $State['next_step'] = 'step_3_4';
                    $State['curBar'] = $State['t5'] + 1;
                    $State = clearState($State, "2,3,t1,t2,t3,t3-,t3'");

                    unset($State['param']['auxP6']);
                    unset($State['param']['auxP6t']);

                    return ([$State]);
                }
            }
        } else { //аналог 6.2.1.1.2. Прогнозная 6-ая не найдена (т.е. линии не пересекаются). В этом случае ЛТмп не отображается на графике.
            //Модель фиксируется [Найдена модель без вспомогательной МП], в отчете отображается:AUX : NoAM
            $State = myLog($State, "аналог Прогнозная 6-ая не найдена. Модель фиксируется БЕЗ УЧЕТА Т5 [Модель без вспомогательной МП], AUX : NoAM, - идем на п6.3 (корр.алг) ");
            if (isset($State['t3\'мп'])) unset($State['t3\'мп']);
            $State['param']['AUX'] = 'NoAM';
            // $State['param']['fixed_at']=$State['t5'];
            // // $State = fix_model($State, "Модель без вспомогательной МП", true);
            // $State = fix_model($State, "Модель без вспомогательной МП");
            //$State['next_step']='step_7';  // корректировка алгоритма 07.08.2019 - идем на 6.3
            //return([$State]);
        }
        // конец вставки ///////////////////////////////////////////////////////////////////////////
    }

    // п.6.3 ПОСТРОЕНИЕ ВСПОМОГАТЕЛЬНОЙ МП ЧЕРЕЗ Т.5’.
    $State = myLog_start($State, "6.3");
    // 6.3 программа проводит проверку на наличие т.5’ (первый возможный по времени локальный экстремум на участке т.4-т.5).
    $isT5sFound = false;
    for ($i = $State['t4'] + 1; $i < $State['t5']; $i++) { // перебор от t4 не включая до т.5
        if (is_extremum($i, $v)) {
            // Если т.5’ найдена, в отчете отображается: AimsBlock5’ : 5' + (изм 07.08.2019 - если удается построить модель через т.5', модель фиксируется со статусом [Построена МП через т.5']
            $State['param']["AimsBlock5'"] = '5\'';
            $State['t5\''] = $i;
            // $State = myLog($State, "Установлен новый статус [Построена МП через т.5']"); 
            // $State['status']['Построена МП через т.5\''] = 0; 
            $State = myLog($State, "Найдена т.5'");
            $State['status']['Найдена т.5\''] = 0;

            // В случае наличия т.5’ программа по аналогии с п. 6.2. строит Линию Тренда вспомогательной модели притяжения, но уже через т.5’ и при необходимости – через другую подходящую т.3’ (далее т.3’мп5’. Если программе удаётся построить МП через т.5’, в отчете отображается: AUX5’	AUX5’
            // $t3_ = $State['t3']; // J строкой ниже вводим другую переменную 
            $t3_5s = $State['t3'];
            // if (isset($State['t3\''])) $t3_ = $State['t3\''];
            if (isset($State['t3\''])) $t3_5s = $State['t3\''];
            // if (isset($State['t3\'мп'])) $t3_ = $State['t3\'мп']; 
            if (isset($State['t3\'мп'])) $t3_5s = $State['t3\'мп'];
            $State = myLog($State, "проверяем, пересекает ли цена ЛТмп5' на участке $t3_5s-" . $State['t5\'']);
            //$State['param']['__t3_']=$t3_;
            $LT_vmp5s = ['bar' => $t3_5s, 'level' => low($t3_5s, $v), 'angle' => (low($State['t5\''], $v) - low($t3_5s, $v)) / ($State['t5\''] - $t3_5s)];
            $isIntersectionFound = false;
            // проверяем пересекает ли цена на т3_5s-т5'
            // for ($k = $State['t3'] + 1; $k < $State['t5\'']; $k++) {
            for ($k = $t3_ + 1; $k < $State['t5\'']; $k++) {
                if (low($k, $v) < lineLevel($LT_vmp5s, $k)) { //аналог 6.2.1. Пересечения найдены. Линия т.3/т.3’-т.5 в этом случае не отображается на графике. Программа ищет на участке от бара т.3/т.3’ (включая) до бара т.4 (не включая) (далее участок 3’-4) такую точку 3’ вспомогательной Модели Притяжения (далее т.3’мп), через которую возможно построить линию, которая не будет иметь пересечений с ценой на участке от т.3/т.3’ до т.5.
                    $State = myLog($State, "аналог 6.2.1. пересечение с ЛТмп5' найдено на баре ($k) ищем т3'мп5' от $k(вкл) до " . $State['t4'] . " не вкл.");
                    $isIntersectionFound = true;
                    $isT3vmpFound = false;
                    for ($j = $k; $j < $State['t4']; $j++) { //перебор возможных т3'мп5'
                        //           $State = myLog($State, "кандидат в т3'мп5' ($j)");
                        //           $State=myLog($State,"проверяем $j");
                        $LT_aux_tmp5s = ['bar' => $j, 'level' => low($j, $v), 'angle' => (low($State['t5\''], $v) - low($j, $v)) / ($State['t5\''] - $j)];
                        // $isTouch_new = false;
                        $isBroken_new = false;
                        for ($l = $t3_5s + 1; $l < $State['t5\'']; $l++) if ($l !== $j) { // не проверяем на т.3'мп5'
                            if (low($l, $v) < lineLevel($LT_aux_tmp5s, $l)) {
                                //                  $State=myLog($State,"есть касание на  $l");
                                // $isTouch_new = true;
                                $isBroken_new = true;
                                $State = myLog($State, " пересечение с ЛТмп5' найдено на баре ($l) ищем т3'мп5' от '$j'(не вкл) до " . $State['t4'] . " не вкл.");
                                break;
                            }
                        }
                        // if (!$isTouch_new) {
                        if (!$isBroken_new) {
                            $isT3aux5sFound = $j;
                            $State = myLog($State, " t3'мп5'=$j");
                            break;
                        }
                    }

                    if (isset($isT3aux5sFound) && $isT3aux5sFound) { // нашли т3'мп5' // Е: 20201001 ОШИБКА - ПРОВЕРИТЬ было - if ($isT3aux5sFound) и "Undefined variable: isT3aux5sFound"
                        $LT_aux_tmp5s = ['bar' => $j, 'level' => low($j, $v), 'angle' => (low($State['t5\''], $v) - low($j, $v)) / ($State['t5\''] - $j)];
                        // J ПРОВЕРКА НА СХОДИМОСТЬ ЛИНИЙ //////
                        $State = myLog($State, " Рассчитываем уровень пересечения ЛТмп5' и ЛЦ’ (по аналогии с пп. 6.2.1.1.1. и 6.2.1.1.2.)");
                        // используем $LT_vmp и $LC_s
                        // ищем расчетную т6
                        $y_top = lineLevel($LC_s, $isT3aux5sFound); // цена на ЛЦ' на баре т3'мп5'
                        $y_bottom = low($isT3aux5sFound, $v);
                        $dy = $LT_aux_tmp5s['angle'] - $LC_s['angle']; // на сколько сближаются ЛЦ' и ЛТ'вмп за 1 бар
                        if ($dy > 0) { // аналог 6.2.1.1.1. Если расчётная т.6' найдена. Модель фиксируется [Найдена расчётная т.6']
                            // $t6s = $t3_5s + ($y_top - $y_bottom) / $dy; // на каком баре находится пересечение (t6) - дробное значение
                            $t6s = $isT3aux5sFound + ($y_top - $y_bottom) / $dy; // на каком баре находится пересечение (t6) - дробное значение
                            $t6s_level = lineLevel($LC_s, $t6s);
                            //$State['param']['_t6']=substr($t6,0,7); //ЗАГЛУШКА
                            //$State['param']['_t6_price']=substr(($v=='low')?($t6_level*1):(-1)*$t6_level,0,7); //ЗАГЛУШКА
                            //  $State['t6мп']=substr($t6,0,7).' ('.substr(($v=='low')?($t6_level*1):(-1)*$t6_level,0,7).')'; // ЗАГЛУШКА
                            //  $State['t6мп']=substr($t6,0,7).' ('.substr(($v=='low')?($t6_level*1):(-1)*$t6_level,0,7).')'; // ЗАГЛУШКА
                            // $State['param']['aux5'P6'] = substr(" " . abs($t6_level), 0, 7);
                            // $State['param']['aux5'P6t'] = substr($t6, 0, 7);
                            $State['param']['auxP6\''] = round(" " . abs($t6s_level), 5);
                            $State['param']['auxP6\'t'] = round($t6s, 7);
                            $State = myLog($State, "рассчитали auxP6' : (" . $State['param']['auxP6\'t'] . ") " . $State['param']['auxP6\'']);

                            //конец вставки

                            ////// Добавляем проверку на соотношение т.2'-т.4 к т.4-т.6' //////   
                            $p24_4aux6s = ($State['t4'] - $State['t2\'']) / ($State['param']['auxP6\'t'] - $State['t4']);
                            if ($p24_4aux6s < PAR246aux) {
                                unset($State['param']['auxP6\''], $State['param']['auxP6\'t']);
                                if (isset($State['t3\'мп'])) unset($State['t3\'мп']);
                                $State = myLog($State, "Т.6' слишком далеко в 6.3.");
                                ///// Правка от 13.05.21 - идём на п.7 /////
                                // if(($State['draw_flag'] ?? false))$State['next_step'] = 'stop';
                                // else $State['next_step'] = 'step_3_4';
                                // //  $State['next_step'] = 'step_3_4';
                                // $State = clearState($State, "2,3,t1,t2,t3,t3-,t3'"); //// unset всех полей кроме перечисленных и служебных
                                // return ([$State]);
                                $State['next_step'] = 'step_7'; // - идём на п.7
                                return ([$State]);
                            } else {

                                // конец вставки ///

                                $State = myLog($State, "аналог 6.2.1.1. нашли t3'мп5' на баре ($isT3aux5sFound) AUX5' , переход к п.7");
                                $State['t3\'мп5\''] = $isT3aux5sFound; // если найдена и сходятся линии, фиксируем точку. Проверка на дальность сходимости должн быть выше этой строочки
                                $State['param']["AUX5'"] = "AUX5'"; // парметру присваеивается одноименное значение, указывающее не наличие МП через т.5'
                                $State['next_step'] = 'step_7';
                                $State['param']['fixed_at'] = $State['t5\''];
                                $State = fix_model($State, "Построена МП через т.5'");
                                return ([$State]);
                            }
                        }

                        // пропускаем проверку на достиженем бара т.5' уровня т.6, которая есть в других похожих ветках

                        else { //аналог 6.2.1.1.2. Прогнозная 6-ая не найдена (т.е. линии не пересекаются). В этом случае ЛТмп через т.5' не отображается на графике.
                            //Модель фиксируется [Найдена модель без вспомогательной МП через т.5'], в отчете отображается:AUX : NoAM
                            $State = myLog($State, "Прогнозная 6-ая не найдена. Модель фиксируется  [Модель без вспомогательной МП через т.5'], идем на п.7 ");
                            // $State['param']['AUX'] = 'NoAM';
                            $State['param']['fixed_at'] = $State['t5\''];
                            // $State = fix_model($State, "Модель без вспомогательной МП через т.5'", true);
                            $State = fix_model($State, "Модель без вспомогательной МП через т.5'");
                            $State['next_step'] = 'step_7';
                            return ([$State]);
                        }
                    }
                    // пересечения найдены, а т3'мп5' не найдена
                    $State = myLog($State, "пересечения найдены, t5'(" . $State['t5\''] . ") найдена, а т3'мп5' не найдена, NoAUX5', переход к п7");
                    $State['param']["AUX5'"] = "NoAUX5'";
                    $State['next_step'] = 'step_7';
                    return ([$State]);
                } // аналог 6.2.2. пересечения на $t3_5s - т.5' не найдены 

            } // цикл проверки ЛЦ т3_ - т.5' завершился 

            $isT5sFound = true; // найдена первая возможная т.5'
            break; //  прерываем цикл
        } // экстремум в кач-ве т.5' не найден
    } // цикл поиска т.5' завершено

    if (
        $isT5sFound == true
    ) {
        $t3_5s = $State['t3']; // 
        $LT_vmp5s = ['bar' => $t3_5s, 'level' => low($t3_5s, $v), 'angle' => (low($State['t5\''], $v) - low($t3_5s, $v)) / ($State['t5\''] - $t3_5s)];
        $State = myLog($State, "т.3-т.5' не пересекается ");
        $y_top = lineLevel($LC_s, $t3_5s); // цена на ЛЦ' на баре т3'мп5'
        $y_bottom = low($t3_5s, $v);
        $dy = $LT_vmp5s['angle'] - $LC_s['angle']; // на сколько сближаются ЛЦ' и ЛТ'вмп за 1 бар
        if ($dy > 0) { // если линии сходятся
            $t6s = $t3_5s + ($y_top - $y_bottom) / $dy; // находим время т.5'
            $t6s_level = lineLevel($LC_s, $t6s); // находим уровень т.5'
            $State['param']['auxP6\''] = round(" " . abs($t6s_level), 5);
            $State['param']['auxP6\'t'] = round($t6s, 7);
            $State = myLog($State, "рассчитали auxP6' : (" . $State['param']['auxP6\'t'] . ") " . $State['param']['auxP6\'']);

            ////// Добавляем проверку на соотношение т.2'-т.4 к т.4-т.6' //////   
            $p24_4aux6s = ($State['t4'] - $State['t2\'']) / ($State['param']['auxP6\'t'] - $State['t4']);
            if ($p24_4aux6s < PAR246aux) {
                unset($State['param']['auxP6\''], $State['param']['auxP6\'t']);
                // if (isset($State['t3\'мп'])) unset ($State['t3\'мп']);
                $State = myLog($State, "Т.6' слишком далеко в 6.3.");
                ///// Правка от 13.05.21 - идём на п.7 /////
                // if(($State['draw_flag'] ?? false))$State['next_step'] = 'stop';
                // else $State['next_step'] = 'step_3_4';
                // //  $State['next_step'] = 'step_3_4';
                // $State = clearState($State, "2,3,t1,t2,t3,t3-,t3'"); //// unset всех полей кроме перечисленных и служебных
                // return ([$State]);
                $State['next_step'] = 'step_7';  // - идём на п.7                   
                return ([$State]);
            } else {
                // конец вставки ///

                $State = myLog($State, "аналог 6.2.1.1.  для ситуации AUX5' через т.3 = ($t3_5s) , переход к п.7");
                // $State['t3\'мп5\''] = $isT3aux5sFound; // если найдена и сходятся линии, фиксируем точку. Проверка на дальность сходимости должна быть выше этой строочки
                $State['param']["AUX5'"] = "AUX5'"; // парметру присваеивается одноименное значение, указывающее не наличие МП через т.5'
                $State['next_step'] = 'step_7';
                $State['param']['fixed_at'] = $State['t5\''];
                $State = fix_model($State, "Построена МП через т.5'");
                return ([$State]);
            }
        } else { //аналог 6.2.1.1.2. Прогнозная 6-ая не найдена (т.е. линии не пересекаются). В этом случае ЛТмп через т.5' не отображается на графике.
            //Модель фиксируется [Найдена модель без вспомогательной МП через т.5'], в отчете отображается:AUX : NoAM
            $State = myLog($State, "Прогнозная 6-ая не найдена. Модель фиксируется  [Модель без вспомогательной МП через т.5'], идем на п.7 ");
            // $State['param']['AUX'] = 'NoAM';
            $State['param']['fixed_at'] = $State['t5\''];
            // $State = fix_model($State, "Модель без вспомогательной МП через т.5'", true);
            $State = fix_model($State, "Модель без вспомогательной МП через т.5'");
            $State['next_step'] = 'step_7';
            return ([$State]);
        };
    }


    // прошли весь цикл = t5' не найдена
    if (!$isT5sFound) {
        $State = myLog($State, " 6.3 t5' не найдена AimsBlock5' : No5', переход к п.7");
        $State['param']["AimsBlock5'"] = 'No5\'';
    }
    $State['next_step'] = 'step_7';
    return ([$State]);
}

function step_7($State)
{ //7.	ОПРЕДЕЛНИЕ СТ И ТИПА МОДЕЛИ.
    global $res, $pips;
    $v = $State['v'];
    $K = ($v == 'low') ? 1 : -1;
    $curBar = $State['curBar'];
    $State = myLog_start($State, "7");
    //    $State = myLog($State, "ЗАГЛУШКА п.7 - ищем новую т.1");
    //    $State=fix_model($State,"ЗАГЛУШКА п.7");
    $LT = LT($State);
    $LCs = LCs($State);
    $t6_ = linesIntersection($LT, $LCs);
    if (isset($State['t2\''])) { // испр.алг - если нашли т2' 
        //7.1. В случае если ЛЦ построена через т.2 (а не через т.2’, т.е. линия от через точки т.2 и т.4) не имеет пересечения с ценой на участке т.1-т.2
        //if(!isset($State['t6мп']))$res['log'][]="!!! (".modelPoints($State).")нет т6, t6_=".$t6_['bar'];
        //else $res['log'][]="!!! (".modelPoints($State).") т6:".$State['t6'].", t6_=".$t6_['bar'];
        if ($t6_) { // есть пересечение
            if ($t6_['bar'] > $State['t4']) { // 7.1.1. Точка пересечения ЛТ и ЛЦ’ лежит правее т.4. В данном случае точка пересечения линий является расчетной точкой 6 (далее - расчетная т.6).), то программа рассчитывает соотношение отрезков времени от т.1 до т.4 и от т.4 до расчетной т.6 для ЧМП.


                // $P6 = round(" " . abs($t6_['level']), 5);  // для записи в комментарий лога
                $P6 = $State['param']['calcP6'] = round(abs($t6_['level']), 5);
                $State['param']['calcP6t'] = round($t6_['bar'], 3);
                $State = myLog($State, "Найдена расчтная т.6 " . $P6);
                $dist_1_4 = $State['t4'] - $State['t1'];
                $dist_4_6 = $t6_['bar'] - $State['t4'];

                if ($dist_1_4 * 3 > $dist_4_6) { //7.1.1.1.Если участок от т.1 до т.4, умноженный на 3 больше участка от т.4 до расчетной т.6 для ЧМП, данная модель является ЧМП.
                    $State['param']['G1'] = 'AM';
                    // $State = myLog($State, "Найдена расчтная т.6 " . $P6);
                    $State = myLog($State, "проверка dist_1_4=$dist_1_4 dist_4_6=$dist_4_6 P6=$P6 split=" . $State['split']);

                    //Если рассматриваемая модель является ЧМП и при этом сформирована (т.е. после т.4 пробита ЛТ), программа проверяет каждый бар после бара т.4, до одного из следующих событий:
                    // проверяем пробой ЛТ после т4
                    $isLTbroken = false;
                    if (isset($State['t5'])) for ($i = $State['t4'] + 1; $i <= $State['t5']; $i++) {
                        if (low($i, $v) < lineLevel($LT, $i)) {
                            // $isLTbroken = false;
                            $isLTbroken = true; // исправление 14.06.21
                            break;
                        }
                    }
                    if ($isLTbroken) { // сформирована (т.е. после т.4 пробита ЛТ)
                        for ($i = $State['t4']; $i <= $State['t5']; $i++) {
                            if (low($i, $v) <= $State['t3'] && high($i, $v) <= $State['t4']) { //•	Найден бар, достигший уровня т.3 (но не достигший уровень т.4). В этом случае данная ветвь алгоритма завершена.
                                $State = myLog($State, "7.1.1.1.1. Найден бар ($i), достигший уровня т.3 (но не достигший уровень т.4). Данная ветвь алгоритма завершена");
                                return ([next_T1($State)]);
                            }
                            if (high($i, $v) >= $State['t4']) { //•	Найден бар, достигший уровень т.4 (при этом либо не достигший, либо достигший уровень т.3).
                                $State = myLog($State, "7.1.1.1.2. Найден бар ($i), достигший уровня т.4, ищем сонаправленный с т1 экстремум с т4 до пробоя уровня т4 ");
                                //В этом случае программа проводит проверку на наличие на участке от т.4(не включая) до бара, достигшего уровень т.4(включительно) экстремума, противонаправленного экстремуму т.4 (т.е. сонаправленного с экстремумом т.1 анализируемой модели.
                                //                                $isExtremumFound=false;
                                for ($j = $State['t4']; $j <= $i; $j++) {
                                    if (is_extremum($j, $v)) { // - экстремум найден. - фиксируем модель + поиск АЛЬТЕРНАТИВНОЙ т4 с t1,t2,t3 !!!!!!!!!!!!!!!!!!! ДОДЕЛАТЬ
                                        $State = myLog($State, "7.1.1.1.2.а. экстремум противонаправленный т.4 найден ($j) ");
                                        // $State = fix_model($State, "экстремум найден в п7.1.1.1");
                                        // $State['param']['alt_old'] = 1;
                                        // $State['curBar'] = $State['t4'] + 1;
                                        // $State = myLog($State, "ишемм альтернативную т4 начиная с бара " . $State['curBar']);
                                        if (($State['draw_flag'] ?? false)) $State['next_step'] = 'stop';
                                        // else $State['next_step'] = 'step_3_4';
                                        else $State['next_step'] = 'step_8';
                                        //                                        $State['next_step'] = 'step_3_4';
                                        //                                        $isExtremumFound=true;
                                        return ([$State]);
                                    }
                                }
                                //  экстремум не найден. В таком случае, статус сформированной модели отменяется (модель отбрасывается), программа осуществляет поиск следующего кандидата на т.3. Для этого программа обрабатывает бар, пробивший ЛТ по п.2.
                                // ЗАГЛУШКА - ВСТАВИТЬ процедуру убивания сформированной модели
                                //$State['status']='МОДЕЛЬ УБИТА в п.7.1.1.1';
                                $State = myLog($State, "МОДЕЛЬ УБИТА в п.7.1.1.1");
                                // $State['param']['fixed_at']=$State['t5'];
                                // $State = fix_model($State, "МОДЕЛЬ УБИТА в п.7.1.1.1");
                                if (($State['draw_flag'] ?? false)) $State['next_step'] = 'stop';
                                else $State['next_step'] = 'step_2';
                                //$State['next_step='] = 'step_2';
                                $State['t3-'] = $State['t3'];
                                $State = clearState($State, "2,3,t1,t2,t3-");
                                return ([$State]);
                            }
                        }
                    }
                } //$dist_1_4*3>$dist_4_6
                else if ($dist_1_4 * 12 > $dist_4_6) { //$dist_1_4*3<=$dist_4_6
                    $State = myLog($State, "7.1.1.2. данная модель является ЧМП/МДР.");
                    $State['param']['G1'] = 'AM/DBM';
                    //      $State=fix_model($State,"ЧМП/МДР");
                    // $State['param']['calcP6'] = $P6;
                    // $State['param']['calcP6t'] = $P6;
                    // $P6 = $State['param']['calcP6'] = round(abs($t6_['level']), 5);
                    // $State['param']['calcP6t'] = round( $t6_['bar'], 3);
                    // // $State = myLog($State, "Найдена расчтная т.6 $P6");
                    // $State = myLog($State, "Найдена расчтная т.6 " . $P6);
                }
                // else 
                if ($dist_1_4 * 12 <= $dist_4_6) { // 7.1.1.3. участок от т.1 до т.4 умноженный на 12 меньше или равен участку от СТ до т.1 то данная модель является МДР. 
                    $State = myLog($State, "7.1.1.3. данная модель является МДР.");
                    $State['param']['G1'] = 'DBM';
                }
            } //t6 правее т4
            else { //t6 левее т4
                // $ST_str = substr(" " . $t6_['bar'], 1, 7) . " (" . substr(" " . abs($t6_['level']), 1, 7) . ")";
                // $ST_str = round($t6_['bar'], 3) . " (" . round(abs($t6_['level']), 5) . ")";
                // $State['param']['_CT'] = $ST_str;
                // $State['param']['calcP6'] = substr(" " . abs($t6_['level']), 1, 7);
                // $State['param']['calcP6t'] = substr(" " . $t6_['bar'], 1, 7);

                // $State['param']['calcP6'] = round(abs($t6_['level']), 5);
                // $State['param']['calcP6t'] = round($t6_['bar'], 3);
                // $State = myLog($State, "Найдена Сакральная Точка СТ $ST_str");
                $dist_1_4 = $State['t4'] - $State['t1'];
                $dist_ST_1 = $State['t1'] - $t6_['bar'];
                if (
                    $dist_1_4 * 3 <= $dist_ST_1
                    && $dist_1_4 * 12 > $dist_ST_1
                ) {
                    $State = myLog($State, "7.1.2.1. данная модель является МР/МДР.");
                    // $State = myLog($State, "  draw_flag= " . ($State['draw_flag'] ?? 0)); // 2222222222222222222222222
                    $State['param']['G1'] = 'EM/DBM';
                    $ST_str = round($t6_['bar'], 3) . " (" . round(abs($t6_['level']), 5) . ")";
                    $State['param']['_CT'] = $ST_str;
                    $State['param']['calcP6'] = round(abs($t6_['level']), 5);
                    $State['param']['calcP6t'] = round($t6_['bar'], 3);
                    $State = myLog($State, "Найдена Сакральная Точка СТ $ST_str");
                } else
                 if (($State['t4'] - $State['t1']) * 12 > ($State['t1'] - $t6_['bar'])) {
                    $State = myLog($State, "7.1.2.2. данная модель МР.");
                    $State['param']['G1'] = 'EM';
                    $ST_str = round($t6_['bar'], 3) . " (" . round(abs($t6_['level']), 5) . ")";
                    $State['param']['_CT'] = $ST_str;
                    $State['param']['calcP6'] = round(abs($t6_['level']), 5);
                    $State['param']['calcP6t'] = round($t6_['bar'], 3);
                    $State = myLog($State, "Найдена Сакральная Точка СТ $ST_str");
                } else {
                    $State = myLog($State, "7.1.2.3 ЛТ и ЛЦ модели параллельны, данная модель является МДР.");
                    $State['param']['G1'] = 'DBM';
                }
            }
        } // t6 найдена - есть пересечение ЛТ и ЛЦ'
        else { //***Если ЛТ и ЛЦ модели параллельны, то данная модель является МДР.
            $State = myLog($State, "7.1.2*** ЛТ и ЛЦ модели параллельны, данная модель является МДР.");
            $State['param']['G1'] = 'DBM';
            //$State=fix_model($State,"МДР");
        }
    } // 7.1.	В случае если ЛЦ построили через т2' (т2' была найдена  - цена не пересекает ЛЦ' на т1-т2
    else { //7.2 t2' найти не смогли - цена пересекает ЛЦ' на т1-т2
        if ($t6_) { // есть пересечение - t6 найдена
            if ($t6_['bar'] > $State['t4']) { // 7.2.1 t6 справа от т2 и т4
                $P6 = $State['param']['calcP6'] = round(" " . abs($t6_['level']), 5);
                $State['param']['calcP6t'] = round(" " . $t6_['bar'], 3);
                $dist_1_4 = $State['t4'] - $State['t1'];
                $dist_4_6 = $t6_['bar'] - $State['t4'];
                if ($dist_1_4 * 3 > $dist_4_6) { //7.2.1.1.Если участок от т.1 до т.4, умноженный на 3 больше участка от т.4 до расчетной т.6, то данная модель является некорректной ЧМП
                    $State = myLog($State, "7.2.1.1. данная модель является некорректной ЧМП");
                    if (low($State['t4'], $v) <= lineLevel($LT, $State['t4']) || low($State['t4'] + 1, $v) <= lineLevel($LT, $State['t4'] + 1)) {

                        //7.2.1.1.1. Если ЛТ некорретной ЧМП пробита баром т.4 или баром, следующим сразу за т.4, программа осуществляет поиск новой т.3, для чего обрабатывает бар, пробивший ЛТ по п.2.
                        $State = myLog($State, "7.2.1.1.1. ЛТ некорретной ЧМП пробита баром т.4 или баром, следующим сразу за т.4 - ищем новую т3");
                        //                    $State=fix_model($State,"---");
                        if (($State['draw_flag'] ?? false)) $State['next_step'] = 'stop';
                        else $State['next_step'] = 'step_2';
                        //$State['next_step'] = 'step_2';
                        $State['t3-'] = $State['t3'];
                        if (low($State['t4'], $v) <= lineLevel($LT, $State['t4'])) $State['curBar'] = $State['t4'];
                        else $State['curBar'] = $State['t4'] + 1;
                        $State = clearState($State, "2,3,t1,t2,t3-,t3'");
                        return ([$State]);
                    } else { //7.2.1.1.2. Если ЛТ не пробита баром т.4 или баром, следующим сразу за ним, то программа осуществляет поиск новой т.4, для чего обрабатывает бар, следующий за баром кандидата в т.4 по 3.4.
                        $State = myLog($State, "7.2.1.1.2. ЛТ некорретной ЧМП не пробита баром т.4 или баром, следующим сразу за т.4 - обрабатывает бар, следующий за баром кандидата в т.4 по 3.4.");
                        // $State = fix_model($State, "модель в п7.2.1.1.2.");
                        // $State['param']['alt_old'] = 1;
                        $State['curBar'] = $State['t4'] + 1;
                        $State = myLog($State, "Некорретная ЧМП, ишем следующую т4 начиная с бара " . $State['curBar']);
                        $State['next_step'] = 'step_3_4';
                        $State = clearState($State, "2,3,t1,t2,t3,t3-,t3'");
                        return ([$State]);
                    }
                } else 
                if ($dist_1_4 * 12 > $dist_4_6) { //7.2.1.2.Если участок от т.1 до т.4, умноженный на 3 больше участка от т.4 до расчетной т.6, то данная модель является ЧМП/МДР
                    $State = myLog($State, "7.2.1.2. данная модель является ЧМП/МДР.");
                    $State['param']['G1'] = 'AM/DBM';
                } else { // 7.2.1.3. участок от т.1 до т.4 умноженный на 12 меньше или равен участку от СТ до т.1 то данная модель является МДР. 

                    $State = myLog($State, "7.2.1.3. данная модель является МДР.");
                    $State['param']['G1'] = 'DBM';
                }
            } // 7.2.1 t6 справа от т2 и т4
            else { // 7.2.2 t6 слева от т2 и т4
                $t3_ = (isset($State['t3\''])) ? $State['t3\''] : $State['t3'];
                if ($t6_['bar'] > $State['t1'] && $t6_['bar'] < $t3_) {  // псевдоСТ (t6) между т1 и т3/т3'
                    $State = myLog($State, "7.2.2.1. Если псевдо СТ лежит правее (т.е. позже) т.1 до т.3 -> сильная по псевдо СТ МР");
                    $State['param']['G1'] = 'EM';
                    $State['param']['SP'] = 'strongpseudoSP';
                } else { // псевдоСТ (t6) слева от т1
                    if (($t3_ - $State['t1']) > ($State['t1'] - $t6_['bar'])) {
                        $State = myLog($State, "7.2.2.2.1 -> МР, сила модели не определена");
                        $State['param']['G1'] = 'EM';
                        $State['param']['SP'] = 'undef';
                    }
                    if (($t3_ - $State['t1']) <= ($State['t1'] - $t6_['bar']) && ($t3_ - $State['t1']) * 3 >= ($State['t1'] - $t6_['bar'])) {
                        $State = myLog($State, "7.2.2.2.2. -> МР, модель слабая по псевдо СТ");
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
            $State = myLog($State, "аналог 7.1.2 ЛТ и ЛЦ модели параллельны, данная модель является МДР.");
            $State['param']['G1'] = 'DBM';
        }
    }
    // ! added 14/02/22
    // $State['param']['TLSpeedMain'] = round($LT['angle']/$pips*1000,2);
    if (
        isset($State['param']['calcP6'])
        && abs($State['param']['calcP6'] - low($State['t1'], $v)) != 0
    ) {
        $State['param']['TLSpeedMain'] = round($LT['angle'] / abs($State['param']['calcP6'] - low($State['t1'], $v)), 4); // this parametr is calculated if there is AL and TL crossin (if ($t6_))
        $State['param']['SpeedMain'] = round($LT['angle'] / $LCs['angle'], 2);
        if (isset($State['param']['G1']) && $State['param']['G1'] != 'DBM') {
            // ! added 14/02/22
            $State['param']['MainSize'] = round(abs($State['param']['calcP6'] * $K - low($State['t1'], $v)) / $pips, 0);
            $State['param']['MainSizeT'] = round(abs($State['param']['calcP6t'] - $State['t1']), 0);
        }
    }

    $State['next_step'] = 'step_8';
    if ($State['draw_flag'] ?? false) $State['next_step'] = 'stop';
    return ([$State]);
}

function step_8($State) // п.8 - Поиск альтернативной модели
{
    global $res, $lastT3fromT1, $splitCnt, $paramArr;
    $v = $State['v'];
    $curBar = $State['curBar'];
    $State = myLog_start($State, "8");
    if (!isset($State['param']['alt_old'])) $State['param']['alt_old'] = 0;
    $State['param']['fixed_at'] = $curBar; //???????????
    $State = fix_model($State, "п.8 фиксация");
    if ($State['draw_flag'] ?? false) {
        $State['next_step'] = 'stop';
        return ($State);
    }

    // блок добавле 09.08.2019 - при фиксации модели по п.8, запускаем новую ветку по поиску новой т3
    //    $res['log'][]="--NEW split at ".$State['t1']." ----".$lastT3fromT1[$State['t1']]." ".$State['t3'];
    if (isset($State['conf_t4'])) {
        $State = myLog($State, "п.8 запускаем поиск альтернативной модели");
        $State3 = $State;
        $State3['next_step'] = 'step_3_4';
        $State3['status'] = $State3['param'] = [];
        $State3['param']['alt_old'] = $State['param']['alt_old'] + 1;
        if (isset($State3['flat_log'])) $State3['flat_log'] = [];
        $State3['split'] = $splitCnt;
        //$State3['param']['alt_old']++;
        $State3['curBar'] = $State['conf_t4'] + 1;
        $State3 = clearState($State3, "2,3,t1,t2,t3-,t3,t3'");
        $State3 = myLog($State3, "Новая ветка [$splitCnt] поиска альтернативной модели с бара (" . ($State['conf_t4'] + 1));
        $lastT3fromT1[$State['t1']] = $State['t3'];
        //  file_put_contents("step8_3_newSplit_".$State3['split'].".json",json_encode($State3)); //TMP!!!1104
        $splitCnt++;
    }

    if (!isset($lastT3fromT1[$State['t1']]) || ($State['t3'] + 1) < $lastT3fromT1[$State['t1']]) {
        //  $res['log'][] = "NEW split at " . $State['t1'];
        //        if (!isset($paramArr['log']) || $paramArr['log'] != 0) {
        //            if (!isset($res['log']["_" . $State['t1']])) $res['log']["_" . $State['t1']] = [];
        //            $res['log']["_" . $State['t1']][] = "[$splitCnt] " . ($State['t3'] + 1);
        //        } - убрал блок, не понятен смысл ветки log - пока (27.07.2020) сюда ничего не пишем
        $State2 = $State;
        $State2['t3-'] = $State['t3'];
        $State2['next_step'] = 'step_2';
        $State2['mode'] = 'selected';
        $State2['status'] = $State2['param'] = [];
        if (isset($State2['flat_log'])) $State2['flat_log'] = [];
        $State2['split'] = $splitCnt;
        $State2['curBar'] = $State['t3'] + 1;
        $State2 = clearState($State2, "2,3,t1,t2,t3-");
        $State2 = myLog($State2, "Новая ветка [$splitCnt] поиска т3 с бара (" . ($State['t3'] + 1) . ") при фиксации в п.8");
        $State2['param']['alt_old'] = $State['param']['alt_old'];
        //  file_put_contents("step8_2_newSplit_".$State2['split'].".json",json_encode($State3)); //TMP!!!1104
        $lastT3fromT1[$State['t1']] = $State['t3'] + 1;
        $splitCnt++;
        if (isset($State3)) return ([next_T1($State), $State2, $State3]);
        else return ([next_T1($State), $State2]);
    }
    if (isset($State3)) return ([next_T1($State), $State3]);
    return ([next_T1($State)]);

    //    $State=myLog($State,"МОДЕЛЬ УБИТА в п.7.1.1.1");
    //    $State=fix_model($State,"МОДЕЛЬ УБИТА в п.7.1.1.1");
    //    $State['next_step=']='step_2';
    //    $State = clearState($State, "2,3,t1,t2,t3-");
    //    return([$State]);
}


function LC($State)
{ // определение линии целей
    $v = $State['v'];
    return (['bar' => $State['t2'], 'level' => high($State['t2'], $v), 'angle' => (high($State['t4'], $v) - high($State['t2'], $v)) / ($State['t4'] - $State['t2'])]);
}

function LCs($State)
{ // определение ЛЦ'
    $v = $State['v'];
    $t2_ = (isset($State['t2\''])) ? 't2\'' : 't2';
    return (['bar' => $State[$t2_], 'level' => high($State[$t2_], $v), 'angle' => (high($State['t4'], $v) - high($State[$t2_], $v)) / ($State['t4'] - $State[$t2_])]);
}

function LT($State)
{ // определение линии тренда
    $v = $State['v'];
    $t3_ = (isset($State['t3\''])) ? 't3\'' : 't3';
    return (['bar' => $State['t1'], 'level' => low($State['t1'], $v), 'angle' => (low($State[$t3_], $v) - low($State['t1'], $v)) / ($State[$t3_] - $State['t1'])]);
}

function fix_model($State, $name, $wo_t5 = false)
{ // фиксируем модель - пополняем общую коллекцию моделей в $res['Models']
    global $res, $modelNextId, $maxBar4Split, $curSplit;
    static $cnt_fix = 0;
    $cnt_fix++;
    $model = [];
    //if(!isset($State['status']))$State['status']=[];
    // доп.блок - определяем G3 (модель по тренду или нет
    $State['param']['G3'] = getModelTrendType_G3($State);
    // доп.блок - добавляемм служебный параметр _CROSS_POINT - пересечение ЛТ и ЛЦ'
    $LT = LT($State);
    $LCs = LCs($State);
    $_CP = linesIntersection($LT, $LCs);
    if ($_CP && $_CP['bar'] > $State['t4'])
        // $State['param']['_cross_point'] = substr(" " . $_CP['bar'], 1, 7) . " (" . substr(" " . abs($_CP['level']), 1, 7) . ")";
        $State['param']['_cross_point'] = round(" " . $_CP['bar'], 3) . " (" . round(" " . abs($_CP['level']), 5) . ")";
    // конец доп.блока _CROSS_POINT

    if ($name) $State['status'][$name] = 0;
    $State['param']['_points'] = $newModelPoints = modelPoints($State, $wo_t5);  // ЗАГЛУШКА тест J мы здесь получили перечень названий + значений точек, которые есть в $State (т.к. State здесь используется как $StateX из определения функции modelPoints)
    $State = myLog($State, "!!! Фиксируем модель [$name]: $newModelPoints " . ($wo_t5 ? "ФИКСАЦИЯ БЕЗ УЧЕТА Т5!!!" : ""));
    static $field_names = ['v', 'alt_old', 'draw_flag', 'split', 'status', 'param', '3', '2', 't1', 't2', 't2\'', 't3-', 't3', 't3\'', 't3\'мп', 't3\'мп5\'', 't4', 't5', 't5\'']; // поля, которые заносим в модель
    foreach ($field_names as $pk => $pv) {
        if (isset($State[$pv])) $model[$pv] = $State[$pv]; // если в $State есть [$pv], то создаётся $model[$pv] равное $State[$pv] 
    }
    $model['param']['max_bar'] = $maxBar4Split[$curSplit];

    // 20201024 - добавлена проверка (если в текущей нет draw_flag) для всех моделей, ранее зафиксированных с такими же t1,t2,t3,t3',t4 и с draw_flag  заново расчитать параметр abs5
    //   при этом, если у них нет auxP6, то просто unset этих моделей + считаем abs5 для текущей модели

    // пополняем общую коллекцию моделей в $res['Models']
    $t1 = $State['t1']; // номер бара т.1 рассматриваемой модели (которая сейчас в $State)
    if (!isset($res['Models'][$t1])) { // в подмассиве ['Models'] проверяется наличие ключа = номеру бара t1, к которому подвязаны все [$model] с соотвествующей т.1
        $model['id'] = $modelNextId++;
        $res['Models'][$t1] = [$model]; // массив, пока из одной модели по ключу t1
    } else { // ключ т1 уже есть
        // на всякий случай ищем дубликаты (вроде, их не должно быть там...)
        $isDublicateFound = false;
        foreach ($res['Models'][$t1] as $pk => $pv) // все модели, подвявзанные к ['Models'][$t1] проверяются на 
            if ($pv['param']['_points'] == $newModelPoints) { // совпадение с текущей моделю $newModelPoints
                $isDublicateFound = $pk; // где $pk- это номер модели в ['Models'][$t1], которая совпадает с рассматриваемой
                break;
                //$res['log'][]="ОШИБКА - дубликат модели на $t1 ".$model['v'];
            }
        if ($isDublicateFound === false) { // если дублей не найдено
            // file_put_contents("FIX_".$cnt_fix."_NEW_Split_".$State['split'].".json",json_encode($State)); //TMP!!!1104
            $model['id'] = $modelNextId++; // рассматриваемой модели присваивается новый id
            $res['Models'][$t1][] = $model; // рассматриваемая модель заносится в ['Models'][$t1]
        } else { // модель с такими же ключевыми точками найдена
            if (($State['draw_flag'] ?? false) === true) {
                $State = myLog($State, "Попытка повторной фиксации с draw_flag заблокирована");
                return ($State);
            }
            $model['id'] = $res['Models'][$t1][$isDublicateFound]['id'];
            // file_put_contents("FIX_".$cnt_fix."_DUB_Split_".$State['split'].".json",json_encode($State)); //TMP!!!1104
            // unset($res['Models'][$t1][$isDublicateFound]['draw_flag']);
            // foreach ($model['status'] as $pk => $pv)
            // if (!isset($res['Models'][$t1][$isDublicateFound]['status'][$pk]))
            // $res['Models'][$t1][$isDublicateFound]['status'][$pk] = $pv;
            // //foreach($model['param'] as $pk=>$pv)$res['Models'][$t1][$isDublicateFound]['param'][$pk]=$pv;
            // $res['Models'][$t1][$isDublicateFound]['param'] = $model['param'];
            //  foreach ($field_names as $pk => $pv) if ($pv !== 'status' && $pv !== 'param') {
            //  if (isset($model[$pv])) $res['Models'][$t1][$isDublicateFound][$pv] = $model[$pv];
            //  }
            $res['Models'][$t1][$isDublicateFound] = $model;
            // $res['Models'][$t1][$isDublicateFound]['param']['Dub']=$cnt_fix;
        }
    }
    return ($State);
}

// function modelPoints($model, $wo_t5 = false)
function modelPoints($StateX, $wo_t5 = false)
{
    static $point_names = ['t1', 't2', 't3', 't4', 't5']; // поля, которые определяют уникальную модель
    $points = "";
    // добавлено исключение т5, когда это нужно, напр. пп.6.2.1.1.2, пп.6.2.2.1.2 Алгоритма 1
    foreach ($point_names as $pk => $pv) if (!$wo_t5 || $pv !== 't5') { // если $wo_t5 положительное, то т.5 пропускается 
        // if (isset($model[$pv])) $points .= $pv . ":" . $model[$pv] . " "; // $pv - это название точки, а $model[$pv] - это номер бара данной точки 
        if (isset($StateX[$pv])) $points .= $pv . ":" . $StateX[$pv] . " "; // $pv - это название точки, а $StateX[$pv] - это номер бара данной точки 
        // получается, что для всех точек, номера баров которых определены в массиве $StateX, название + номер бара этих точек добавляются в переменную $points через пробел
    }
    return (trim($points)); // таким образом данная функця возвращает  названия + значения точек, слоты для которых есть в $StateX
}
function getModelTrendType_G3($model)
{ // Определение модели «по тренду» или «от начала тренда».
    // вначале ищем пересечение ценой уровня т1 (либо глубина 50 бар:
    $v = $model['v'];
    $t1_level = low($model['t1'], $v); // уровень т1
    $t2_level = high($model['t2'], $v); // уровень т2
    $t2_broken = false; //    1. найдено пересечение ценой уровня т.2
    $t1_broken = false; //    2. найдено пересечение ценой уровня т.1
    $limitBar = $model['t1'] - CALC_G3_DEPTH; // левая граница поиска
    if ($limitBar < 0) $limitBar = 0;
    for ($i = $model['t1'] - 1; $i >= $limitBar; $i--) {  // Е: не включая?
        if (high($i, $v) > $t2_level) {
            $t2_broken = $i;
            break;
        }
        if (low($i, $v) < $t1_level) {
            $t1_broken = $i;
            break;
        }
    }
    if ($t1_broken === false && $t2_broken === false) { //-Если программа проверила менее 50 баров до т.1 модели и достигнут начальный бар графика,
        //  а искомое пересечение не найдено, модель рассматривается как  модель от начала тренда в отчете отображается: G3=NoData
        return ("NoData"); // модель от начала тренда
    }
    //-Если найдено пересечение т.1 до того, как найдено пересечение т.2, то модель является моделью по тренду в отчете отображается:G3=HTModel
    // Е: "до того" - это по времени на графике или по циклу (который назад) ? !!!!!!!!!!!!!!!
    if ($t2_broken === false || $t1_broken > $t2_broken) {
        return ("HTModel");
    }
    return ("BTModel");
}

function next_T1($State)
{ // переход в новой Т1 с проверками, нужно ли продолжать
    global $res;
    // Е: 20200925 - поменяли подход, теперь ветвление для поиска новой т1 приисходит при нахождении кандидата на step_1 -
    // данная функция теперь просто завершает ветку с пометкой, что т1 неудачная
    // все, что ниже return - оставлено для справки (что было раньше)
    //
    $State = myLog($State, " Т1 отклонена - завершаем данный State");
    $State['next_step'] = 'stop';
    return ($State);

    ///////////////// старая версия

    if ($State['mode'] == 'selected') {
        $State = myLog($State, "Завершение ветки - mode=selected = изменение t1 запрещено");
        //      $State=fix_model($State,"ОТЛАДКА - фиксация при смене т.1"); ///////////////////////////ЗАГЛУШКА
        $State['next_step'] = 'stop';
        return ($State);
    }
    if ($State['t1'] < 3) {
        $State = myLog($State, "Завершение ветки - дошли до начала графика");
        $State['next_step'] = 'stop';
        return ($State);
    }
    if ($State['mode'] == 'last') { // переходим к новой т1 только если не были зафиксированы модели
        $v = $State['v'];
        foreach ($res['Models'] as $pk => $pv) foreach ($pv as $pk1 => $pv1) {
            if ($pv1['v'] == $v) {
                $State = myLog($State, "Завершение ветки - mode=last и уже есть зафиксированные модели");
                $State['next_step'] = 'stop';
                return ($State);
            }
        }
    }
    $State['curBar'] = $State['t1'] - 1;
    $State['next_step'] = 'step_1';
    $State = clearState($State, "t1");
    $State['param'] = [];
    $State['status'] = [];
    return ($State);
}


function calcStatistics()
{ // получение финальной статистики по найденным моделям
    global $res, $Chart, $pips;
    // J не догоняю, что в блоке ниже происходит
    if ($res['info']['mode'] == 'last') { // оставляем только последние low и high (возможны лишние при разщеплениях алгоритма, увы
        $last_low = false;
        $last_high = false;
        foreach ($res['Models'] as $pk => $pv) foreach ($pv as $pk1 => $pv1) {
            if ($pv1['v'] == 'low') if (!$last_low || $last_low < $pk) $last_low = $pk;
            if ($pv1['v'] == 'high') if (!$last_high || $last_high < $pk) $last_high = $pk;
        }
        foreach ($res['Models'] as $pk => $pv) {
            $ar = [];
            foreach ($pv as $pk1 => $pv1) {
                if ($last_low && $pv1['v'] == 'low' && $last_low == $pk) $ar[] = $pv1;
                if ($last_high && $pv1['v'] == 'high' && $last_high == $pk) $ar[] = $pv1;
            }
            if (count($ar) == 0) unset($res['Models'][$pk]);
            else $res['Models'][$pk] = $ar;
        }
    }
    // ! added 15/02/22
    // * блок расчета Speed for auxP6
    foreach ($res['Models'] as $pk => $pv) foreach ($pv as $pk1 => $pv1) {
        $v1 = $pv1['v'];
        $K1 = ($v1 == 'low') ? 1 : -1;
        if (isset($pv1['t5'])) {
            $t3_ = $pv1['t3'];
            $level_t3 = low($pv1['t3'], $pv1['v']);
            if (isset($pv1['t3\''])) {
                $t3_ = $pv1['t3\''];
                $level_t3 = low($pv1['t3\''], $pv1['v']);
            }
            if (isset($pv1['t3\'мп'])) {
                $t3_ = $pv1['t3\'мп'];
                $level_t3 = low($pv1['t3\'мп'], $pv1['v']);
            }
            $t2_ = (isset($pv1['t2\''])) ? $pv1['t2\''] : $pv1['t2'];
            $level_t2 = (isset($pv1['t2\''])) ? high($pv1['t2\''], $pv1['v']) : high($pv1['t2'], $pv1['v']);
            $LT__ = ['bar' => $t3_, 'level' => $level_t3, 'angle' => (low($pv1['t5'], $pv1['v']) - low($t3_, $pv1['v'])) / ($pv1['t5'] - $t3_)];
            $LC__ = ['bar' => $t2_, 'level' => $level_t2, 'angle' => (high($pv1['t4'], $pv1['v']) - high($t2_, $pv1['v'])) / ($pv1['t4'] - $t2_)];
            // $t6_ = linesIntersection($LT__, $LC__);
            if (isset($res['Models'][$pk][$pk1]['param']['auxP6'])) {
                // $State['param']['TLSpeedAux'] = round($LT__['angle']/$pips*1000,2);
                if ($res['Models'][$pk][$pk1]['param']['auxP6'] != $level_t2) // 2022-12-30 добавил проверку - замечен очень редкий "баг" - деление на ноль!
                    $res['Models'][$pk][$pk1]['param']['TLSpeedAux'] = round($LT__['angle'] / abs($res['Models'][$pk][$pk1]['param']['auxP6'] - $level_t2), 4);
                $res['Models'][$pk][$pk1]['param']['SpeedAux'] = round($LT__['angle'] / $LC__['angle'], 2);
                $res['Models'][$pk][$pk1]['param']['AuxSize'] = round(abs($res['Models'][$pk][$pk1]['param']['auxP6'] * $K1 - low($pv1['t3'], $pv1['v'])) / $pips, 0);
                $res['Models'][$pk][$pk1]['param']['AuxSizeT'] = round(abs($res['Models'][$pk][$pk1]['param']['auxP6t'] - $t2_), 0);
                $res['Models'][$pk][$pk1]['param']['pips'] = $pips;
            }
        }
    }
    // блок расчета t6 для t5' - auxP6'
    foreach ($res['Models'] as $pk => $pv) foreach ($pv as $pk1 => $pv1) {
        $v1 = $pv1['v'];
        $K1 = ($v1 == 'low') ? 1 : -1;
        if (isset($pv1['t5\''])) { // в модели есть т5' - добавляем расчетную Т6
            //$res['log'][] = "new t5' на $pk $pk1"; /////////////////////////////////////////
            $t3_ = $pv1['t3'];
            $level_t3 = low($pv1['t3'], $pv1['v']);
            if (isset($pv1['t3\''])) {
                $t3_ = $pv1['t3\''];
                $level_t3 = low($pv1['t3\''], $pv1['v']);
            }
            if (isset($pv1['t3\'мп'])) {
                $t3_ = $pv1['t3\'мп'];
                $level_t3 = low($pv1['t3\'мп'], $pv1['v']);
            }
            if (isset($pv1['t3\'мп5\''])) {
                $t3_ = $pv1['t3\'мп5\''];
                $level_t3 = low($pv1['t3\'мп5\''], $pv1['v']);
            }
            $t2_ = (isset($pv1['t2\''])) ? $pv1['t2\''] : $pv1['t2'];
            $level_t2 = (isset($pv1['t2\''])) ? high($pv1['t2\''], $pv1['v']) : high($pv1['t2'], $pv1['v']);
            $LT__ = ['bar' => $t3_, 'level' => $level_t3, 'angle' => (low($pv1['t5\''], $pv1['v']) - low($t3_, $pv1['v'])) / ($pv1['t5\''] - $t3_)];
            $LC__ = ['bar' => $t2_, 'level' => $level_t2, 'angle' => (high($pv1['t4'], $pv1['v']) - high($t2_, $pv1['v'])) / ($pv1['t4'] - $t2_)];
            $t6_ = linesIntersection($LT__, $LC__);
            if ($t6_)
                if (abs($t6_['level']) > 0 && isset($pv1['param']["AUX5'"]) && $pv1['param']["AUX5'"] == "AUX5'") {
                    // $res['Models'][$pk][$pk1]['param']['auxP6\''] = substr(" " . abs($t6_['level']), 1, 7);
                    // $res['Models'][$pk][$pk1]['param']['auxP6\'t'] = substr(" " . $t6_['bar'], 1, 7);
                    $res['Models'][$pk][$pk1]['param']['auxP6\''] = round(" " . abs($t6_['level']), 5);
                    $res['Models'][$pk][$pk1]['param']['auxP6\'t'] = round(" " . $t6_['bar'], 7);
                    // ! added 15/02/22
                    // $State['param']['TLSpeedAux5\''] = round($LT__['angle']/$pips*1000,2);
                    // $res['Models'][$pk][$pk1]['param']['TLSpeedAux5\''] = round($LT__['angle']/($pv1['t4'] - $pv1['t1']),4);
                    if ($res['Models'][$pk][$pk1]['param']['auxP6\''] != $level_t2) // 2022-12-30 добавил проверку - замечен очень редкий "баг" - деление на ноль!
                        $res['Models'][$pk][$pk1]['param']['TLSpeedAux5\''] = round($LT__['angle'] / ($res['Models'][$pk][$pk1]['param']['auxP6\''] - $level_t2), 4);
                    $res['Models'][$pk][$pk1]['param']['SpeedAux5\''] = round($LT__['angle'] / $LC__['angle'], 2);
                    $res['Models'][$pk][$pk1]['param']['AuxSize\''] = round(abs($res['Models'][$pk][$pk1]['param']['auxP6\''] * $K1 - low($pv1['t3'], $pv1['v'])) / $pips, 0);
                    $res['Models'][$pk][$pk1]['param']['AuxSizeT\''] = round(abs($res['Models'][$pk][$pk1]['param']['auxP6\'t'] - $t2_), 0);
                    $res['Models'][$pk][$pk1]['param']['pips'] = $pips;
                } else {
                    //$State['status']['ЗАГАДОЧНАЯ ОШИБКА С _auxP6\'==0 max_bar='.date("Y-m-D H:i:s",$Chart[nBars-1]['close_time'])]=0;  // ругалась тут  на date() и вообще этот блок явно ошибочный... $State тут вообше не определена и непонятно, зачем в нее писать что-то
                    $pv1['status']['ЗАГАДОЧНАЯ ОШИБКА С _auxP6\'==0 max_bar=' . $Chart[nBars - 1]['close_time']] = 0;  // ?
                }
        }
    }
    //////////////////////////////////

    $res['info']['models_total'] = [];
    foreach ($res['Models'] as $pk => $pv) foreach ($pv as $pk1 => $pv1) {
        $key = $pv1['v'];
        if (isset($res['info']['models_total'][$key])) $res['info']['models_total'][$key]++;
        else $res['info']['models_total'][$key] = 1;
    }
    foreach ($res['Models'] as $pk => $pv) foreach ($pv as $pk1 => $pv1) {
        foreach ($pv1['status'] as $pk2 => $pv2) {
            $key = $pk2;
            if (isset($res['info']['models_total'][$key])) $res['info']['models_total'][$key]++;
            else $res['info']['models_total'][$key] = 1;
        }
    }
    return ($res);
}
