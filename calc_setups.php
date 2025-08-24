<?php
// эмуляция торгов (вариант от 2022-06-10 - обрабытваем все заданные сетапы согласно ТЗ и выводим отчет в json - запускается из JS (trade_emulador.php) через AJAX
// setupIDs - массив номеров сетапов, которые нужно посчитать. Если не задано, то считаем все, что указаны
// если указано mode=list, то вместо расчета возвращаем массив из текстов сетапов
ini_set('date.timezone', 'Europe/Moscow');
// ini_set('memory_limit', '4096M');
ini_set('memory_limit', '8192M');
set_time_limit(0);
define("LOG_FILE","calc_setups.log"); //
define("WRITE_LOG",0); //
define("MIN_PIPS", 0.00001);
define("NN_NUM", 3); // сколько у нас нейронок всего (соответсвующих полей в matches)
define("TTLS_LIMIT", 300000); // лимит числа трейдов при котором происходит выгрузка на диск для экономии памяти
define("DEBUG_TOOL_LIMIT", 1000000); // для отладки - максимальное количество инструментов (всего сейчас 140, это много для полного пересчета при отладке)
define("DEBUG_REPORT_LIMIT", 50000); // для отладки - максимальное количество отчетов (они довольно долго генерятся)
define("PROGRESS_FILENAME","___calc_setup_progress.txt"); // в этом файле периодически сохраняем текущий статус, чтобы потом получить его запросом их AJAX (должен совпадать с указанным в JS)

define("PIC_WIDTH",1600); // размер графика по горизонтали
define("PIC_HEIGHT",800); // размер графика по вертикали
define("PIC_FRAME",25); // границы до края области рисования
define("PIC_BORDER",21); // размер рамки на картинке графика

// определение настроек для уровней - в % от рамера модели
define("LVL_BREAKDOWN", -6); // пробой уровня МП
define("LVL_APPROACH", 6); // подход к уровню МП
define("LVL_BUMPER1", 20); // бамперный уровень для МП (начальный)
define("LVL_BUMPER2", 15); // бамперный уровень для МП (при достижении уровня МП)

$debug_limit=10000000; // !!!!!!!!!!!!!!!! для отладки - максимальный id
require_once 'login_log.php';
//require_once "inc_getToken.php";
$setups=[
    [
        'condition1' => '$G1=="EAM" && $bad==0 && $NN1_probability_1>0.5 && $section>3',
        'condition2' => '', // условие 2 задано пустым значением
        'trade type' => 'P6reach',
        'CancelLevel' => '85%',
        // 'InitStopLoss' => '-6%, -3%, 1%', // stop задан диапазоном
        'InitStopLoss' => '15%', // stop откладывается от уровня подтверждения т.4
        // 'Aim1' => '28%, 30%, 1%', // - уровень цели задан диапазоном
        'Aim1' => '-30%', // - уровень цели задан диапазоном
        // 'Trigger1' => '15%, 20%, 5%', // триггер для переноса стопа задан также диапазоном
        'Trigger1' => '15%, 20%, 2%', // триггер для переноса стопа задан также диапазоном
        // 'AlternateTrigger1' => '10%, 15%, 2%', // альтернативный триггер задан диапазоном, причем на третьем шаге попадаем за пределы диапазона (в этом случа должно быть использовано крайнее значение диапазона = 15%
        'AlternateTrigger1' => '', // альтернативный триггер задан пустым значением,т.к. для этого типа трейда он не логичен
        'Trailing1' => 't5',
        'Trigger2' => '', // триггер 2 задан пустым значением
        'Trailing2' => '',
        'Actual' => '200%'
    ],


    [ // аналогичный предыдущему, только trigger2 задан пустым значением
        'condition1' => '$G1=="EAM" && $bad==0 && $NN2_probability_1<0.5 && $NN2_probability_1>0 && $section>3',
        'condition2' => '',
        'trade type' => 'P6rev',
        'CancelLevel' => '85%',
        // 'InitStopLoss' => '-6%, -3%, 1%', // stop задан диапазоном
        'InitStopLoss' => '-6%',
        // 'Aim1' => '28%, 30%, 1%', // - уровень цели задан диапазоном
        // 'Aim1' => '27%, 30%, 1%', // - уровень цели задан диапазоном
        'Aim1' => '30%', // - уровень цели задан диапазоном
        // 'Trigger1' => '15%, 20%, 5%', // триггер для переноса стопа задан также диапазоном
        'Trigger1' => '15%',
        // 'AlternateTrigger1' => '10%, 15%, 2%', // альтернативный триггер задан диапазоном, причем на третьем шаге попадаем за пределы диапазона (в этом случа должно быть использовано крайнее значение диапазона = 15%
        'AlternateTrigger1' => '10%', // альтернативный триггер задан диапазоном, причем на третьем шаге попадаем за пределы диапазона (в этом случа должно быть использовано крайнее значение диапазона = 15%
        'Trailing1' => 'AMrealP6',
        'Trigger2' => '', // триггер 2 задан пустым значением
        'Trailing2' => '',
        'Actual' => '200%'
    ],
    [
        'condition1' => '$G1=="EAM" && $bad==0 && $NN3_probability_1<0.5 && $NN3_probability_1>0 && $section>3',
        'condition2' => '',
        'trade type' => 'P6rev',
        'CancelLevel' => '85%',
        // 'InitStopLoss' => '-6%, -3%, 1%', // stop задан диапазоном
        'InitStopLoss' => '-6%',
        // 'Aim1' => '28%, 30%, 1%', // - уровень цели задан диапазоном
        'Aim1' => '30%', // - уровень цели задан диапазоном
        // 'Trigger1' => '15%, 20%, 5%', // триггер для переноса стопа задан также диапазоном
        'Trigger1' => '15%',
        // 'AlternateTrigger1' => '10%, 15%, 2%', // альтернативный триггер задан диапазоном, причем на третьем шаге попадаем за пределы диапазона (в этом случа должно быть использовано крайнее значение диапазона = 15%
        'AlternateTrigger1' => '10%', // альтернативный триггер задан диапазоном, причем на третьем шаге попадаем за пределы диапазона (в этом случа должно быть 
        'Trailing1' => 'AMrealP6',
        'Trigger2' => '', // триггер 2 задан одним значением
        'Trailing2' => '',
        'Actual' => '200%'
    ],
    [
        'condition1' => '$G1=="EAM" && $bad==0 && $NN3_probability_1<0.5 && $NN3_probability_1>0',
        'condition2' => '',
        'trade type' => 'P6rev',
        'CancelLevel' => '85%',
        'InitStopLoss' => '-6%, -3%, 1%', // stop задан диапазоном
        'Aim1' => '28%, 30%, 1%', // - уровень цели задан диапазоном
        'Trigger1' => '15%, 20%, 5%', // триггер для переноса стопа задан также диапазоном
        'AlternateTrigger1' => '10%, 15%, 2%', // альтернативный триггер задан диапазоном, причем на третьем шаге попадаем за пределы диапазона (в этом случа должно быть использовано крайнее значение диапазона = 15%
        'Trailing1' => 'AMrealP6',
        'Trigger2' => '25%', // триггер 2 задан одним значением
        'Trailing2' => '10%',
        'Actual' => '200%'
    ],
    [
        'condition1' => '$G1=="EAM" && $bad==0',
        'condition2' => '', // условие 2 задано пустым значением
        'trade type' => 'P6over',
        'CancelLevel' => '85%',
        'InitStopLoss' => '10%, 20%, 5%', // stop задан диапазоном
        'Aim1' => '-50%, -30%, 5%', // - уровень цели задан диапазоном
        'Trigger1' => '-20%, -15%, 5%', // триггер для переноса стопа задан также диапазоном
        'AlternateTrigger1' => '', // альтернативный триггер не задан, т.к. тип 'P6over'
        'Trailing1' => '10%',
        'Trigger2' => '-40%', // триггер 2 задан одним значением
        'Trailing2' => '0%',
        'Actual' => '200%'
    ],
    [
        'condition1' => '$G1=="AM" && $bad==0',
        'condition2' => '', // условие 2 задано пустым значением
        'trade type' => 'P6reach',
        'CancelLevel' => '85%',
        'InitStopLoss' => 't5', // stop задан как т.5
        'Aim1' => '-6%, 6%, 6%',  // ДЛЯ ЭТОГО ТИПА ТРЕЙДА СТОП ЛОСС ЗАДАЕТСЯ В ПРЕДЕЛАХ УРОВНЕЙ ПОДХОДА К Т.6 И УРОВНЯ ПРОБОЯ
        'Trigger1' => '55%, 60%, 5%', // ДЛЯ ЭТОГО ТИПА ТРЕЙДА ТРИГГЕР СРАБАТЫВАЕТ ТОЛЬКО ЕСЛИ ОН БЛИЖЕ К Т.6 ДЛЯ ДАННОЙ МОДЕЛИ, ЧЕМ InitStopLoss'. Это имеет значение в случаях, когда InitStopLoss задан как т.5
        'AlternateTrigger1' => '', // альтернативный триггер задан пустым значением
        'Trailing1' => '50%',
        'Trigger2' => '', // триггер 2 задан пустым значением
        'Trailing2' => '',
        'Actual' => '200%'
    ],
    [
        'condition1' => '$G1=="EAM" && $bad==0 && $section>3',
        'condition2' => '', // условие 2 задано пустым значением
        'trade type' => 'P6reach',
        'CancelLevel' => '85%',
        'InitStopLoss' => '50%', // stop задан как т.5
        'Aim1' => '-6%, 6%, 6%',  // ДЛЯ ЭТОГО ТИПА ТРЕЙДА СТОП ЛОСС ЗАДАЕТСЯ В ПРЕДЕЛАХ УРОВНЕЙ ПОДХОДА К Т.6 И УРОВНЯ ПРОБОЯ
        'Trigger1' => '55%, 60%, 5%', // ДЛЯ ЭТОГО ТИПА ТРЕЙДА ТРИГГЕР СРАБАТЫВАЕТ ТОЛЬКО ЕСЛИ ОН БЛИЖЕ К Т.6 ДЛЯ ДАННОЙ МОДЕЛИ, ЧЕМ InitStopLoss'. Это имеет значение в случаях, когда InitStopLoss задан как т.5
        'AlternateTrigger1' => '', // альтернативный триггер задан пустым значением
        'Trailing1' => 't5',
        'Trigger2' => '', // триггер 2 задан пустым значением
        'Trailing2' => '',
        'Actual' => '200%'
    ],
    [ // аналогичный предыдущему, только trigger2 задан пустым значением
        'condition1' => '$G1=="EAM" && $bad==0 && $NN3_probability_1<0.5 && $NN3_probability_1>0 && $section>3',
        'condition2' => '',
        'trade type' => 'P6rev',
        'CancelLevel' => '85%',
        // 'InitStopLoss' => '-6%, -3%, 1%', // stop задан диапазоном
        'InitStopLoss' => '-6%',
        // 'Aim1' => '28%, 30%, 1%', // - уровень цели задан диапазоном
        'Aim1' => '30%', // - уровень цели задан диапазоном
        // 'Trigger1' => '15%, 20%, 5%', // триггер для переноса стопа задан также диапазоном
        'Trigger1' => '15%',
        // 'AlternateTrigger1' => '10%, 15%, 2%', // альтернативный триггер задан диапазоном, причем на третьем шаге попадаем за пределы диапазона (в этом случа должно быть использовано крайнее значение диапазона = 15%
        'AlternateTrigger1' => '10%', // альтернативный триггер задан диапазоном, причем на третьем шаге попадаем за пределы диапазона (в этом случа должно быть использовано крайнее значение диапазона = 15%
        'Trailing1' => 'AMrealP6',
        'Trigger2' => '', // триггер 2 задан пустым значением
        'Trailing2' => '',
        'Actual' => '200%'
    ],
//    // ниже остался от текста в ТЗ
//    [
//        'condition1' => '$G1=="EAM" && $bad==0 && best_lists_proc(4,5)>= 45 && worst_lists_proc(4,5)>=30 && $bad==0 && $Clst_II_E', // - условие вызова сетапа для рассматриваемой модели, полностью аналогично фильтрам
//        'condition2' => '',  // для агрессивной тактика
//        'trade type' => 'P6rev', // - вариант сетапа, возможные вариации: P6rev - трейд на разворот от P6, P6"rev - трейд на разворот от P6", аналогично для вспомогательных МП auxP6rev, auxP6'rev
//        // варианта трейда на достижение уровня 6-ой P6reach, P6"reach, auxP6reach, auxP6'reach
//        // варианта трейда на пробой 6-ой P6over, P6"over, auxP6over, auxP6'over
//
//        'CancelLevel' => '85%', // - уровень отмены трейда.
//        'InitStopLoss' => '-6%', // - начальный уровень стоп-лоса. Для вариантов трейда на пробой т.6 и на достижение т.6 может быть задан как уровень т.5,  'InitStopLoss' => 't5'
//        'Aim1' => '33%, 55%, 1%', // - уровень цели
//        'Trigger1' => '25%', // - уровень при достижении которого осуществляется перенос уровня стопа 1ый раз. Значение может быть пустым.
//        'AlternateTrigger1' => '15%', // - уровень при достижении которого осуществляется перенос уровня стопа 1ый раз в том случае, если достигнут уровень рассчетной т.6 (а не только уровня подхода к т.6). Данный параметр актуален только для вариантов трейда на разворот.
//        'Trailing1' => 'AMrealP6', // - уровень, на который переносится стоп при достижении уровня Trigger. В данном примере задан как уровень "потенциальной реальной т.6", но может быть указан как 'Trailing1' => 't5'  или в %. Если значение Trigger1 пустое, то значение Trailing1 не учитывается
//        'Trigger2' => '20%', // - уровень при достижении которого осуществляется перенос уровня стопа 2ый раз. Значение может быть пустым.
//        'Trailing2' => '10%', // - уровень, на который переносится стоп при достижении уровня Trigger. В данном примере задан как уровень реальной 6-ой, но может быть указан и в %. Если значение Trigger1 пустое, то значение Trailing1 не учитывается
//        'Actual' => '200%' // переменная для расчета времени прекращения отслеживания соотвествующей модели
//    ],
];

$aimFields=['P6aims','P6aims"',"auxP6aims","auxP6aims'"]; // перечень всех возможных целей (из list_headers - по ним отдельно ищем модели, у которых они определены
$sortOrder=[ // служебный массив - в каком порядке сортировать поля в выходном ассоциативном массиве для удобства чтения - меньшее значение - раньше
        // поля сетапов
        'condition1' => 10,
        'condition2' => 20,
        'trade type' => 30,
        'CancelLevel' => 40,
        'InitStopLoss' => 50, // stop задан диапазоном
        'Aim1' => 60, // - уровень цели задан диапазоном
        'Trigger1' => 70, // триггер для переноса стопа задан также диапазоном
        'AlternateTrigger1' => 80, // альтернативный триггер задан диапазоном, причем на третьем шаге попадаем за пределы диапазона (в этом случа должно быть использовано крайнее значение диапазона = 15%
        'Trailing1' => 90,
        'Trigger2' => 100, // триггер 2 задан одним значением
        'Trailing2' => 110,
        'Actual' => 120,

        // имена веток и основных переменных
        'curSetup'=>10,
        'setupParams'=>20,

        'PROFIT'=>30,
        'LOSS'=>40,
        'TP_open'=>50,
        'SL_open'=>55,
        'TP_close'=>60,
        'SL_close'=>65,
        'ALL_CNT'=>70,
        'CANCEL_CNT'=>80,
        'TRADE_CNT'=>90,
        'AIM_CNT'=>100,
        'SL1_CNT'=>110,
        'SL2_CNT'=>120,
        'SL3_CNT'=>130,

        'PNLs'=>1000,

        'Отчет'=>0
    ];
$tmp_CT=0;

$Chart=[]; // массив баров по текущему инструменты - перезаполняется в основном цикле (по name_id)
$barId2barNum=[]; // вспомогательный массив для определения номера бара в чарте по его id (bar_id модели)
$models=[]; // массив моделей по текущему инструменты - также перезаполняется в основном цикле (по name_id)
$NN_Answers=[]; // массив - заполняется однократно - вся инфа которая есть по ответам нейронок - ключи: mode_id,aim_name,NN_number
$TTLs=[]; // накопительные итоги, счетчики + списки результатов торгов по всем моделям (суммы) - индексы 1) тэг сетапа 2) G1 или ALL_G1 для сводного 3) наименование парамета(или суммы)
//$CNTs=[]; //счетчики - аналогично
//$PNLs=[]; // массив результатов по каждой модели - индексы 1) тэг сетапа 2) G1 или ALL_G1 для сводного 3) номер по порядку -> асс.массив с полями: "model_id","close_time","pnl"
$size_and_levels=[]; // фрагмент БД по текущему name_id. Индексы: 1) model_id 2) aim_field
$listArrFull=[]; // ассоциативный массив - список строк lists для текущего name_id- (ключи model_id, aim_field)
$listsArr=[]; // фрагмент предыдущего массива - листы по текущей модели и цели
//$listHeadersFullArr=[]; // ассоциативный массив - просто копия list_headers из БД (ключи G1,aim_name,номер по порядку)
$tradesCnt=0;
ob_start();
$res=[]; // возвращаемый результат json

//$res['_____test1']="";
//$res['_____test2']="";


$res['Error']='Error_01';
$res['Errors']=[];
$res['info']['type']='_GET';
$PARAM=$_GET;

if(isset($_POST['setupIDs'])||isset($_POST['mode'])){$res['info']['type']='_POST'; $PARAM=$_POST;}


// если запрос на чтение текущего "прогресса" - файла  ___calc_setup_progress.txt
if(isset($PARAM['mode'])&&strtoupper($PARAM['mode'])=='PROGRESS'){
    $out=file_get_contents(PROGRESS_FILENAME);
    if($out)$res['answer']=$out;
    else $res['answer']="ERROR! Error reading file ".PROGRESS_FILENAME;
    $res['answer']=$out;

    unset($res['Error']);
    die();
}

$sectionByNameId=[]; // возвращает номер секции инструмента по name_id
$result=queryMysql("select name_id,name,n.section from (select distinct name_id from models) i left join chart_names n on i.name_id=n.id;",true);
if($result) {
    $toolsList=[];
    $nameIdList=[];
    while ($rec = $result->fetch_assoc()) {
        $toolsList[]=$rec['name'];
        $nameIdList[]=$rec['name_id'];
        $sectionByNameId[$rec['name_id']]=$rec['section'];
    }
    $result->close();
}
else{
    $res['Errors'][]="Error reading instruments list from database!";
    die();
}

// если запрос на получение всех сетапов (первоначальный запрос при старте)
if(isset($PARAM['mode'])&&strtoupper($PARAM['mode'])=='LIST'){
    $res['answer']=$setups;
    $res['tools']=$toolsList;
    $res['PIC_WIDTH']=PIC_WIDTH;
    $res['PIC_HEIGHT']=PIC_HEIGHT;
    writeProgress("Setups list loaded. Count: ".count($setups));
    unset($res['Error']);
    die();
}



// основной блок - эмуляция трейдов
if(WRITE_LOG>0)$f_header = fopen(LOG_FILE, 'w');
write_log("************ ".date("Y-m-d H:i:s")." start".PHP_EOL,1);


// проверяем существование папки с отчетами и создаем, если ее нет
if(!is_dir('Reports'))mkdir('Reports');
$dir_name='Reports/'.date("Y-m-d H_i_s");
$tmp=mkdir($dir_name);
if(!$tmp){
    $res['Errors'][]="ERROR! Failed to create directory \"$dir_name\"";
    die();
}
$tmp=mkdir($dir_name."/TTLs");
if(!$tmp){
    $res['Errors'][]="ERROR! Failed to create directory \"$dir_name/TTLs\"";
    die();
}


$setupList=[]; // список id сетапов
if (isset($PARAM['setupIDs'])){ // задан в параметрах список id сетапов
    $tmpArr=explode(',',$PARAM['setupIDs']);
    foreach($tmpArr as $id_)if(isset($setups[intval($id_)]))$setupList[]=intval($id_); // проверяем, что такой индекс есть в списке сетапов
}
else{
    foreach($setups as $id_=>$txt_)$setupList[]=$id_;
}
if(count($setupList)==0){
    $res['Errors'][]="Ошибочный список номеров сетапов!";
    die();
}
$tool=null; // название инструмента из БД, например EURUSD240
$selected_name_id=null;  // name_id инструмента из БД
if(isset($PARAM['tool'])){
    $tool=$PARAM['tool'];
    $result=queryMysql("select max(id) name_id from chart_names where name='$tool'");
    $selected_name_id=$result->fetch_assoc()['name_id'];
    $result->close();
    if(is_null($selected_name_id)){
        $tool=null;
    }
}

$res['answer']['tool']=$tool;
$res['answer']['selected__name_id']=$selected_name_id;
$res['answer']['setupList']=$setupList;

$columnsInModels=[]; // служебный массив - список всех полей таблицы models
$result=queryMysql("SHOW COLUMNS FROM models");
while($rec=$result->fetch_assoc()){
    //$res['info']['колонки таблицы models'][$rec['Field']]="";//$rec['Field'];
    $columnsInModels[$rec['Field']]=$rec['Type'];
}
// определяем поля тоблицы models который используются в условиях сетапов (что-бы для экономии времени не вычислять все)
//$k = str_replace('-', '____', str_replace('#', '___', str_replace('"', '__', str_replace("'", "_", $pk))));
$varsToDefine=[];
$fieldListForSelect='id,v,bar_id,t4,t5,`t5"`,`t5\'`,calcP6,`calcP6"`,auxP6,`auxP6\'`';
foreach($columnsInModels as $pk=>$pv){
    // замена спецсимволов в названии полей на подчеркивания ( - на ____ # на  ___ " на  __ и 'на  _ )
    $k = str_replace('-', '____', str_replace('#', '___', str_replace('"', '__', str_replace("'", "_", $pk))));
    foreach($setups as $ind_=>$setup){
        if(!(strpos($setup['condition1'] ?? "",'$'.$k)===false) || !(strpos($setup['condition2'] ?? "",'$'.$k)===false)){
            $varsToDefine[$pk]=true;
            $fieldListForSelect.=",`".$pk."`";
        }
    }
}

//file_put_contents("tmp_log/_______debug_ " . shortFileName(__FILE__) . "_(" . __LINE__ . ").json", json_encode(get_defined_vars(), JSON_PARTIAL_OUTPUT_ON_ERROR)); // for debug only

$result=queryMysql("select name_id,count(*) models from models group by name_id;");
$debug_models_cnt=[];
while($rec=$result->fetch_assoc()){
    $debug_models_cnt[$rec['name_id']]=$rec['models']; // число моделей для каждого инструмента
}
$result->close();


//получаем список моделей, которые нам интересны для каждого name_id  по отдельности
$ind=0;
//$name_id_CNT=0;
$st=microtime(true);
$modelCnt=0;
$last_modelCnt=0;
$lastStatus=0;
foreach($nameIdList as $pk=>$name_id)if(is_null($tool)||$name_id==$selected_name_id){ // либо перебор всех (tool не задан) либо выполняем только на заданном tool
    $curModelsArr=[]; // вспомогательный массив - id моделей, участвующих в эмуляции для текущего инструмента
    $section=$sectionByNameId[$name_id]; // определили переменную $section для использования в дальнейшем в условиях сетапов
    if($ind>=DEBUG_TOOL_LIMIT)break; // для отладки - прерываем по достижению лимита
    $curTool=$toolsList[$pk];

    // if($debug_models_cnt[$name_id]>50000 || $name_id>17)continue; // for debug only

    writeProgress("Processing instrument: $curTool, previously processed: $ind --- trades calculated: ".$tradesCnt);
    write_log("---------- Processing instrument: $curTool, previously processed: $ind \n".PHP_EOL,3);

    // загружаем чарт по текущему инструменту
    $Chart=[];
    $result=queryMysql("select * from charts where name_id=$name_id order by dandt");
    $ind_=0;
    $barId2barNum=[];
    while($rec=$result->fetch_assoc()) {
        $Chart[]=$rec;
        $barId2barNum[$rec['id']]=$ind_++; // по bar_id опрелеляес элемент в массиве $Chart
    }
    $result->close();

    // if(count($Chart)<1000){ // если что-то не так - нет баров по данному инструменту, например
    if(count($Chart)<100){ // если что-то не так - нет баров по данному инструменту, например
        $res['Errors'][]=$res['Error']="Chart error (too few bars) name_id=$name_id";
        die();
    }
    $pips=calcPips_clone($Chart); // определили размер пипса для текущего инструмента
    write_log("Loaded bars: ".count($Chart)." pips=$pips".PHP_EOL,9);
    //$res['answer']['tmp_pips_'.$name_id."_$curTool"]=$pips;



    $result=queryMysql("select $fieldListForSelect from models where (`calcP6` is not null or `calcP6\"` is not null or `auxP6` is not null or `auxP6'` is not null) and name_id=$name_id order by id;");
    $models=[];

    while($rec=$result->fetch_assoc()){ // записали модели в массив $models
        $models[]=$rec;
    }
    $result->close();
    write_log("Loaded models: ".count($models).PHP_EOL,7);

    // заносим в массивы всю нужную инфу по "геометрии" моделей (size_and_levels)
    $size_and_levels=[];
    $result=queryMysql("select * from size_and_levels where model_id in (select distinct id from models where name_id=$name_id);");
    while($rec=$result->fetch_assoc()) {
        $_tmp=[];
        $_model_id=$rec['model_id'];
        $_aim_field=$rec['aim_field'];
        // сохраняем во временный саммив нужные в дальнейшем поля из таблицы saze_and_levels;
        $_tmp['pips']=$pips; // добавляем доп. рараметр - размер пипса
        $_tmp['bar_0']=$rec['bar_0'];
        $_tmp['lvl_0']=$rec['lvl_0'];
        $_tmp['size_time']=$rec['size_time'];
        $_tmp['size_level']=$rec['size_level'];
        $_tmp['lvl_am']=$rec['lvl_am'];
        $_tmp['lvl_appr']=$rec['lvl_appr'];
        $_tmp['lvl_brkd']=$rec['lvl_brkd'];
        //unset($rec['model_id']);
        //unset($rec['aim_field']);
        $size_and_levels[$_model_id][$_aim_field]=$_tmp;
    }
    $result->close();
    //$res['_____SIZE&LEVELS']=$size_and_levels;
    write_log("Loaded size_and_levels: ".count($size_and_levels).PHP_EOL,7);

    // заносим в массив $NN_Answers всю инфу по ответам нейронок, какая есть
    $add1=$add2="";
    $NN_Answers=[];
    $res['info']['MGU1']=memory_get_usage();
    $result=queryMysql("select * from nn_answers where model_id in (select distinct id from models where name_id=$name_id);");
    while($rec=$result->fetch_assoc()){
        $NN_Answers[$rec['model_id']][str_replace("P6","P6aims",$rec['aim_name'])][$rec['nn_number']]=floatval($rec['answer']);
    }
    $result->close();
    write_log("Loaded nn_answers: ".count($NN_Answers).PHP_EOL,7);
    //$res['_____NN_answers']=$NN_Answers;

    foreach($setups as $curSetupNum=>$curSetup)if(in_array($curSetupNum,$setupList)){ // берем только отмеченные сетапы

        $setupParams=[]; // в этот массив запишем "распарсенные" параметры - например, значение  'AlternateTrigger1' => '10%, 15%, 2%' превретится в 'AlternateTrigger1' => [10,12,14,15]
        $valueCnt=[]; // вспомогательный массив - число вариантов в сетапе для каждого поля по порядку
        $ind2name=[]; // вспомогательный массив - имена полей в сетапе по индексу (пропуская condition1 и condition2)
        foreach($curSetup as $_field=>$_value)if(!in_array(strtoupper($_field),["CONDITION1",'CONDITION2'])) {
            $setupParams[$_field] = valueParsing($_value);
            $valueCnt[]=count($setupParams[$_field]); // число вариантов для каждого поля по порядку
            $ind2name[]=$_field;
        }
        $indArr=[];
        $_cnt=0;
        foreach($setupParams as $pk1=>$pv1){
            $newIndArr=[];
            foreach($pv1 as $pk2=>$pv2){
                if($_cnt==0)
                    $newIndArr[]=[$pv2];
                else{
                    foreach($indArr as $pk3=>$pv3)$newIndArr[]=array_merge($pv3,[$pv2]);
                }
            }
            $_cnt++;
            $indArr=$newIndArr;
        }
        //$res['___$indArr']=$indArr; /////////////////////////////////////

        // заменяем цифровые индексы на теги и имена полей
        $parsedSetupList=[]; // получившийся список сетапов
        foreach($indArr as $pk=>$pv){ // перебор всех получившихся комбинаций
            // фомируем тег для каждой комбинации параметров (в тег входят только поля, по которым задан диапазон
            $tag="[".$curSetupNum." ".str_replace("'","``",str_replace('"',"`",$curSetup['trade type'])).'] ';
            $tmpRes=[];
            foreach($pv as $pk1=>$pv1){
                $tmpRes[$ind2name[$pk1]]=$pv1;
                if($valueCnt[$pk1]>1) { // если по данному полю больше одного значения, то указываем данное знаяение в tag
                    $tag .=$ind2name[$pk1]."=".$pv1.",";
                }
            }
            $tag=substr($tag,0,strlen($tag)-1);
            $tmpRes['tag']=$tag;
            $parsedSetupList[]=$tmpRes;
        }
        //$res['___$parsedSetupList']=$parsedSetupList; /////////////////////////////////////
        $splitCnt=-1;
        $checkModels=[];
        foreach($parsedSetupList as $parsedInd=>$curParsedSetup){
            if(($TTLs[$curParsedSetup['tag']] ?? 0)==="S") {
                $st=microtime(true);
                $tmp=file_get_contents($dir_name."/TTLs/".$tag.".json");
                $TTLs[$curParsedSetup['tag']] = json_decode($tmp, true);
                calcTime("readTTLs",$st);
            }
            $splitCnt++;
            write_log("##### Processing models for $curTool ($curSetupNum / $splitCnt) '".$curParsedSetup['tag']."'".PHP_EOL,7);
            if(($modelCnt+count($checkModels))>($last_modelCnt+10) || $lastStatus<(microtime(true)-5)) {
                writeProgress("Processing instrument: $curTool, previously processed: $ind --- trades calculated: ".$tradesCnt);
                $lastStatus=microtime(true);
                $last_modelCnt=$modelCnt+count($checkModels);
            }

            foreach($models as $model) { // перебор всех моделей

//                write_log("- tmp1 ($curSetupNum / $splitCnt) id:".$model['id'].PHP_EOL,9);

                $id_=$model['id'];
                if(isset($checkModels[$id_]) && $checkModels[$id_]===false)continue; // модель ранее проверялась и не подошла
                if(isset($checkModels[$id_])) { // модель ранее проверялась и подошла
                    write_log("*** tradeEmulation repeat *** id:".$model['id'].PHP_EOL,9);
                    foreach($checkModels[$id_]['aims'] as $ind_=>$aim_) {
                        $st=microtime(true);
                        tradeEmulation($curParsedSetup, $model, $aim_, $curSetup, $checkModels[$id_]['check2'], $checkModels[$id_]['aggressive']);
                        calcTime("tradeEmulation",$st);

                    }
                    continue;
                }

                    $st=microtime(true);
                    $bad=0;
                    $check_=false;
                    foreach ($varsToDefine as $pk => $pv) {
                        // определяем общие для всех целей параметры модели переменные для использования в условиях setups
                        // заменяем в имени поля символы (запрещенные в именах PHP) (' " # -) на, соответсвенно, (_ __ ___ ____)
                        $k = str_replace('-', '____', str_replace('#', '___', str_replace('"', '__', str_replace("'", "_", $pk))));
                        $pv = "" . $model[$pk];
                        $eval_str = '$' . $k . '=\'' . str_replace("'", "\'", $pv) . '\';'; // ВНИМАНИЕ! - null тут превращается в пустую строку - вроде, так норм???
                        eval($eval_str); // создание собственно переменных
                    }
                    if (!$bad) $bad = 0;
                    calcTime("setVars",$st);


                if(!isset($checkModels[$id_]))$checkModels[$id_]=false;
                foreach($aimFields as $curAim) { // перебираем все возможные цели и проверяем на соответсвие модели сетапам

                    // проверяем, что у модели есть данная шестая (цель), иначе - пропускаем
                    if(
                    !($curAim=='P6aims'&&isset($model['calcP6'])||
                        $curAim=='P6aims"'&&isset($model['calcP6"'])||
                        $curAim=='auxP6aims'&&isset($model['auxP6'])||
                        $curAim=='auxP6aims\''&&isset($model['auxP6\''])
                    )
                    ){
                        continue;
                    }


                    //$listsArr=$listArrFull[$model['id']][$curAim] ?? []; // глобальный массиа - список всех дистов для данной модели и цели

                    for ($NN_Number = 1; $NN_Number <= NN_NUM; $NN_Number++) {
                        $NN_=$NN_Answers[$model['id']][$curAim][$NN_Number] ?? -1;
                        eval('$NN' . $NN_Number . '_probability_1=getProbability_1_2(' . $NN_ . ')[0];');
                        eval('$NN' . $NN_Number . '_probability_2=getProbability_1_2(' . $NN_ . ')[1];');
                    } // определили переменные NN1, NN2 и т.д.

                    // перебираем все сетапы и отрабатываем торги по тем, которые подходят


                        // проверяем, что сетап подходит для данной цели (соответсвует указанной "trade type" ) иначе выходим
                        $isMatched=false;
                        $tradeType=$curSetup['trade type'];
                        if(!$isMatched)if($curAim=='P6aims'&&in_array($tradeType,['P6rev','P6reach','P6over']))$isMatched=true; // есть соответствие
                        if(!$isMatched)if($curAim=='P6aims"'&&in_array($tradeType,['P6"rev','P6"reach','P6"over']))$isMatched=true; // есть соответствие
                        if(!$isMatched)if($curAim=='auxP6aims'&&in_array($tradeType,['auxP6rev','auxP6reach','auxP6over']))$isMatched=true; // есть соответствие
                        if(!$isMatched)if($curAim=='auxP6aims\''&&in_array($tradeType,['auxP6\'rev','auxP6\'reach','auxP6\'over']))$isMatched=true; // есть соответствие
                        if(!$isMatched)continue;



                        $eval_str = '$check_=(' . $curSetup['condition1'] . ');';
                        //if(count($res['_____TEST']['eval_str']?? [])<1000)
                        //    if($NN1_probability_1>0||$NN2_probability_1>0||$NN3_probability_1>0)$res['_____TEST']['eval_str'][]=$NN2_probability_1." _ ".$NN3_probability_1."   > ".$eval_str;
                        eval($eval_str); // определили - попадает ли текущая модель/цель под условия очередного сетапа

                        //$reason_="Condition: (".$curSet['condition1'].") Result: (".eval('return "'.str_replace('"','\"',$curSet['condition1']).'";').")";

                        write_log("--- check: ".($check_?"YES!":"no")." [".$eval_str."|".eval('return "'.str_replace('"','\"',$curSetup['condition1']).'";')."]$curSetupNum".PHP_EOL,9);


                        $checkModels[$id_]=false;
                        if ($check_){ // модель-цель соответсвует условию данного сетапа


                            // если нужно, то проверим condition2
                            $isAggressive=false;
                            if(trim($curSetup['condition2'])){ // если condition2 задан
                                $eval_str2 = '$check2_=(' . $curSetup['condition2'] . ');';
                                eval($eval_str2); // проверяем второе условие
                                $isAggressive=true;
                            }
                            else $check2_=false;


                                if(isset($size_and_levels[$id_][$curAim])) {
                                    $curModelsArr[$model['id']]=($curModelsArr[$model['id']] ?? 0) +1;
                                  //  file_put_contents("tmp_log/_______debug_ " . shortFileName(__FILE__) . "_(" . __LINE__ . "_$name_id).json", json_encode(get_defined_vars(), JSON_PARTIAL_OUTPUT_ON_ERROR)); // for debug only
                                    write_log("*** tradeEmulation *** id: $id_ [".$eval_str."|".eval('return "'.str_replace('"','\"',$curSetup['condition1']).'";')."]$curSetupNum".PHP_EOL,9);

                                    if($checkModels[$id_]===false)$checkModels[$id_]=[];
                                    $checkModels[$id_]['check2'] = $check2_;

                                    $checkModels[$id_]['aggressive']=$isAggressive;
                                    $checkModels[$id_]['aims'][$curAim]=$size_and_levels[$id_][$curAim];
                                    $st=microtime(true);
                                    tradeEmulation($curParsedSetup, $model, $checkModels[$id_]['aims'][$curAim], $curSetup, $checkModels[$id_]['check2'], $checkModels[$id_]['aggressive']);
                                    calcTime("tradeEmulation",$st);
                                  //  file_put_contents("tmp_log/_______debug_ " . shortFileName(__FILE__) . "_(" . __LINE__ . "_$name_id).json", json_encode(get_defined_vars(), JSON_PARTIAL_OUTPUT_ON_ERROR)); // for debug only
                                  //  die();
                                }

                        }

                } // перебор всех возможных целей


            }  // перебор всех моделей текущего инструмента (name_id) для текущего tag

            // блок сохранения больших веток $TTLs на диске (для экономя памяти в случае большого числа трейдов по сетапам
            if(count($TTLs[$curParsedSetup['tag']]['ALL_G1']['PNLs'] ?? [])>TTLS_LIMIT){ // число трейдов больше лимита -> выгружаем на диск
                $st=microtime(true);
                file_put_contents($dir_name."/TTLs/".$curParsedSetup['tag'].".json",json_encode($TTLs[$curParsedSetup['tag']]));
                $TTLs[$curParsedSetup['tag']]="S";
                calcTime("writeTTLs",$st);
            }
        } // перебор всех сетапов (распарсенных)
          $modelCnt+=count($checkModels);
    } // перебор всех сетапов (нераспарсенных)


    $gdv_memory=gdvCalcMemory(get_defined_vars());
    //file_put_contents("tmp_log/_______gdv_ " . shortFileName(__FILE__) . "_(" . __LINE__ . "_$name_id).json", json_encode($gdv_memory, JSON_PARTIAL_OUTPUT_ON_ERROR)); // for debug only
    //file_put_contents("tmp_log/_______debug_ " . shortFileName(__FILE__) . "_(" . __LINE__ . "_$name_id).json", json_encode(get_defined_vars(), JSON_PARTIAL_OUTPUT_ON_ERROR)); // for debug only

    $ind++;
    writeProgress("Last processed instrument: $curTool, total processed: $ind --- trades calculated: ".$tradesCnt);

} // перебор всез tool
$st=microtime(true);
writeReports($dir_name);
calcTime("writeReports",$st);
$res['_____MGU_exit']=memory_get_usage();
unset($TTLs);
$res['_____MGU_without_TTLs']=memory_get_usage();
unset($res['Error']);
$res['info']['MGU_exit']=memory_get_usage();
file_put_contents("tmp_log/_calc_setups_res_ " . shortFileName(__FILE__) . "_(" . __LINE__ .").json", json_encode($res, JSON_PARTIAL_OUTPUT_ON_ERROR)); // for debug only
die();

function fieldCompFunc($k1,$k2){
    global $sortOrder;
    $i1=$sortOrder[$k1] ?? 9999; // если индекс не указан, то сортировка по самим ключам (по алфавиту)
    $i2=$sortOrder[$k2] ?? 9999;
    if($i1<$i2)return(-1);
    if($i1>$i2)return(1);
    return 0;
}
function closeTime_CompFunc($t1,$t2){
    if($t1['close_time']<$t2['close_time'])return(-1);
    else return(1);
}
function writeReports($dir_name){ // генерация и запись отчета (и графика)

    global $res,$TTLs,$tmp_CT,$tradesCnt;
    ksort($TTLs);
    $ttt=microtime(true);
    $cnt=0;
    //file_put_contents("tmp_log/_______debug_ " . shortFileName(__FILE__) . "_(" . __LINE__ . ").json", json_encode(get_defined_vars(), JSON_PARTIAL_OUTPUT_ON_ERROR)); // for debug only
    foreach($TTLs as $tag=>$pv1){
        if($pv1==="S") {
            $st=microtime(true);
            $tmp=file_get_contents($dir_name."/TTLs/".$tag.".json");
            $pv1 = json_decode($tmp, true);
            calcTime("readTTLs",$st);
        }
        foreach($pv1 as $G1=>$pv2){
            if($G1=='ALL_G1'&&count($pv1)==2)continue; // если в сетапе только один G1, то ALL_G1 будет полный дубль и его не пишем
            if($cnt>=DEBUG_REPORT_LIMIT)break;
            $cnt++;
            $fileName=$dir_name."/".$tag."_".$G1.".json";
            $header=fopen($fileName,"w");
            //uksort($pv2,'fieldCompFunc');
            uksort($pv2['curSetup'],'fieldCompFunc');
            uksort($pv2['setupParams'],'fieldCompFunc');
            if($pv2['PNLs'] ?? [])
                usort($pv2['PNLs'],'closeTime_CompFunc'); // сортируем сделки по времени закрытия по возрастанию
            $pv2['PROFIT']=$pv2['PROFIT'] ?? 0;
            $pv2['PROFIT_proc']=($pv2['PROFIT_proc'] ?? 0)/($pv2['TRADE_CNT'] ?? 1);
            $pv2['LOSS']=$pv2['LOSS'] ?? 0;
            $pv2['LOSS_proc']=($pv2['LOSS_proc'] ?? 0)/($pv2['TRADE_CNT'] ?? 1);
            $pv2['Отчет']=[];
            // формируем сводные параметры и пишем (пока) в json
            $pv2['Отчет']['1. Количество моделей, попавших в данный сетап']=$pv2['ALL_CNT'] ?? 0;
            $pv2['Отчет']['2. Количство моделей по которым был проведен трейд']=$pv2['TRADE_CNT'] ?? 0;
            $pv2['Отчет']['3. Количство моделей по которым была получена та или иная отмена']=$pv2['CANCEL_CNT'] ?? 0;
            $pv2['Отчет']['4. Количество моделей, достгших Aim1/InitStopLoss/Trailing1/Trailing2']=($pv2['AIM_CNT'] ?? 0)." / ".($pv2['SL1_CNT'] ?? 0)." / ".($pv2['SL2_CNT'] ?? 0)." / ".($pv2['SL3_CNT'] ?? 0);
            $pv2['Отчет']['5. Среднее TP/SL на момент открытия сделки']=round(($pv2['TP_open'] ?? 0)/($pv2['SL_open'] ?? 0.001),2);
            $pv2['Отчет']['6. Среднее TP/SL на момент закрытия сделки']=round(($pv2['TP_close'] ?? 0)/($pv2['SL_close'] ?? 0.001),2);
            $pv2['Отчет']['7. Суммарный ДОХОД в пипсах']=$pv2['PROFIT'] ?? 0;
            $pv2['Отчет']['7\'. Суммарный ДОХОД в %%']=round($pv2['PROFIT_proc'] ?? 0,2);
            $pv2['Отчет']['8. Суммарный УБЫТОК в пипсах']=$pv2['LOSS'] ?? 0;
            $pv2['Отчет']['8\'. Суммарный УБЫТОК в %%']=round($pv2['LOSS_proc'] ?? 0,2);
            $pv2['Отчет']['9. Итоговая разница между доходом и убытком в пипсах']=($pv2['PROFIT'] ?? 0)-($pv2['LOSS'] ?? 0);
            $pv2['Отчет']['9\'. Итоговая разница между доходом и убытком в %%']=round(($pv2['PROFIT_proc'] ?? 0)-($pv2['LOSS_proc'] ?? 0),2);
            uksort($pv2,'fieldCompFunc');

            $profitCnt=0;
            $maxProfitSeria=0;
            $lossCnt=0;
            $maxLossSeria=0;
            if(isset($pv2['PNLs']))
                foreach($pv2['PNLs'] as $i=>$trade){
                    if($trade['pnl']>0){
                        $profitCnt++;
                        $maxProfitSeria=max($maxProfitSeria,$profitCnt);
                        $lossCnt=0;
                    }
                    if($trade['pnl']<0){
                        $lossCnt++;
                        $maxLossSeria=max($maxLossSeria,$lossCnt);
                        $profitCnt=0;
                    }
                }
            $pv2['Отчет']['10. Наиболее продолжительная серия прибыльных сделок']=$maxProfitSeria;
            $pv2['Отчет']['11. наиболее продолжительная серия убыточных сделок']=$maxLossSeria;

            fwrite($header,json_encode($pv2));

            $graphTypes=['pips',"proc"];  // 2 типа графиков - в пипсах и процентах - рисуем 2 картинки соответсвенно
            if(isset($pv2['PNLs']))
                foreach($graphTypes as $graphType) {
                    // блок отрисовки и оохранения графика PNL в пипсах
                    if($graphType=="pips")$fileNameJPG = $dir_name . "/" . $tag . "_" . $G1 . ".jpg";
                    else $fileNameJPG = $dir_name . "/" . $tag . "_" . $G1 . "_proc.jpg";
                    $im = imagecreatetruecolor(PIC_WIDTH, PIC_HEIGHT);
                    $text_color = imagecolorallocate($im, 43, 69, 99);
                    $line_color = imagecolorallocate($im, 35, 32, 212);
                    $axes_color = imagecolorallocate($im, 100, 100, 100);
                    $frame_color = imagecolorallocate($im, 220, 227, 175);
                    $bg_color = imagecolorallocate($im, 221, 237, 234); //

                    imagefill($im, 0, 0, $frame_color);
                    imagefilledrectangle($im, PIC_BORDER, PIC_BORDER, PIC_WIDTH - PIC_BORDER, PIC_HEIGHT - PIC_BORDER, $bg_color);

                    imagestring($im, 3, PIC_BORDER, 2, $tag . "_" . $G1, $text_color);
                    imagestring($im, 2, PIC_BORDER, PIC_HEIGHT - PIC_BORDER + 3, $fileNameJPG, $text_color);

                    // определяем наибольшее и наименьшее значение графика
                    $max_ = $min_ = $balance = 0;
                    if(!isset($pv2['PNLs']))file_put_contents("tmp_log/_______debug_ " . shortFileName(__FILE__) . "_(" . __LINE__ . ").json", json_encode(get_defined_vars(), JSON_PARTIAL_OUTPUT_ON_ERROR)); // for debug only
                    foreach ($pv2['PNLs'] as $ind => $tr) {
                        if($graphType=="pips")$balance += $tr['pnl'];
                        else $balance += $tr['pnl']/$tr['sizeLevel']*100;
                        $max_ = max($max_, $balance);
                        $min_ = min($min_, $balance);
                    }
                    $tradesQnt = count($pv2['PNLs'] ?? []);
                    $max_=round($max_,2);
                    $min_=round($min_,2);

                    imagestring($im, 5, PIC_FRAME + 4, PIC_FRAME, "max: " . $max_, $text_color);
                    imagestring($im, 5, PIC_FRAME + 4, PIC_HEIGHT - PIC_FRAME - 12, "min: " . $min_ . ", number of trades: $tradesQnt", $text_color);

                    $tmp = (0 - $min_) / ($max_ - $min_); // в какой позиции находится нулевая ось для дистанции min-max
                    $axe_Y = PIC_HEIGHT - PIC_FRAME - $tmp * (PIC_HEIGHT - PIC_FRAME * 2);
                    imageline($im, PIC_FRAME, intval($axe_Y), PIC_WIDTH - PIC_FRAME, intval($axe_Y), $axes_color);
                    imagestring($im, 5, PIC_FRAME - 10, intval($axe_Y) - 8, "0", $text_color);

                    $lastBalance = $Balance = 0;
                    foreach ($pv2['PNLs'] as $ind => $tr) {
                        $addPnl=($graphType=="pips"?$tr['pnl']:$tr['pnl']/$tr['sizeLevel']*100);
                        $Balance = $lastBalance + $addPnl;
                        // рисуем отрезок от предыдущей точки ($lastBalance) к текущей ($Balance)
                        $x1 = PIC_FRAME + ($ind / $tradesQnt * (PIC_WIDTH - PIC_FRAME * 2));
                        $y1 = (PIC_HEIGHT - PIC_FRAME) - ($lastBalance - $min_) / ($max_ - $min_) * (PIC_HEIGHT - PIC_FRAME * 2);
                        $x2 = PIC_FRAME + (($ind + 1) / $tradesQnt * (PIC_WIDTH - PIC_FRAME * 2));
                        $y2 = (PIC_HEIGHT - PIC_FRAME) - ($Balance - $min_) / ($max_ - $min_) * (PIC_HEIGHT - PIC_FRAME * 2);
                        imageline($im, intval($x1), intval($y1), intval($x2), intval($y2), $line_color);
                        if ($tradesQnt < 100) imagestring($im, 1, intval($x2), intval($y2), "[" . $ind . "] " . ($tr['pnl'] > 0 ? "+" : "-") . abs(round($addPnl,2)).($graphType=='pips'?"":"%"), $text_color);
                        $lastBalance = $Balance;
                    }
                    imagestring($im, 5, intval($x2) - 50, intval($y2), $Balance, $text_color);


                    // Сохраняем изображение в файл jpg с тем же именем, что и отчет json
                    imagejpeg($im, $fileNameJPG, 85);

                    // Освобождаем память
                    imagedestroy($im);
                }

            $res['answer']['out_files'][$cnt.") ".$tag."_"."$G1|trades:".($pv2['TRADE_CNT']??0).", pnl: ".$pv2['PROFIT']."-".$pv2['LOSS']."=   ".($pv2['PROFIT']-$pv2['LOSS'])." "]=$fileName;
            fclose($header);
            $tmp_CT=(microtime(true)-$ttt);
            if($cnt % 10 ==0)writeProgress("Generating and writing reports: $cnt");
        }
    }
}

function tradeEmulation($setup,$model,$size_and_levels,$curSetup,$check2=false,$isAggressive=false){ // функция для эмуляции торгов по заданной модели, цели и варианту сетапа
    global $TTLs,$res,$Chart,$barId2barNum,$tradesCnt;
    $res['_____DEBUG_setup']=$setup;
    $res['_____DEBUG_model']=$model;
    $res['_____DEBUG_size_and_levels']=$size_and_levels;

    $G1 = $model['G1'];

    $TTLs[$setup['tag']][$G1]['curSetup']=$curSetup; // сохраняем в виде отдельной ветки параметры сетапа, как они определены в тексте PHP
    $TTLs[$setup['tag']][$G1]['setupParams']=$setup; // сохраняем в виде отдельной ветки параметры сетапа, как они определены в тексте PHP
    unset($TTLs[$setup['tag']][$G1]['setupParams']['tag']);
    $TTLs[$setup['tag']]['ALL_G1']['curSetup']=$curSetup; // сохраняем в виде отдельной ветки параметры сетапа, как они определены в тексте PHP
    $TTLs[$setup['tag']]['ALL_G1']['setupParams']=$setup; // сохраняем в виде отдельной ветки параметры сетапа, как они определены в тексте PHP
    unset($TTLs[$setup['tag']]['ALL_G1']['setupParams']['tag']);


    $v = $model['v'];
    $K = ($v == 'low') ? 1 : -1; // множитель для зеркального переворачивания моделей high - НАДО НЕ ЗАБЫВАТЬ ЕГО ИСПОЛЬЗОВАТЬ ПРИ ИСПОЛЬЗОВАНИИ ЛЮБЫХ УРОВНЕЙ
    $baseBarNum=$barId2barNum[$model['bar_id']];

    $ChartLen=count($Chart);
    //die();

    $pips = floatval($size_and_levels['pips']);
    $bar_0 = intval($size_and_levels['bar_0']);
    //$lvl_0 = $size_and_levels['lvl_0']*$K;
    $size_time = floatval($size_and_levels['size_time']);
    $size_level = floatval($size_and_levels['size_level']);
    $lvl_am = $size_and_levels['lvl_am']*$K;
    $appr_level=$size_and_levels['lvl_appr']*$K;
    $brkd_level=$size_and_levels['lvl_brkd']*$K;
    $cancel_level=$lvl_am-$size_level*$setup['CancelLevel']/100;
    $aim1_level=$lvl_am-$size_level*$setup['Aim1']/100;


    if(substr($setup['trade type'],-3)=='rev'){ // [1] вариaнт торговли на разворот
        $tradesCnt++;
        $TTLs[$setup['tag']][$G1]['ALL_CNT']=($TTLs[$setup['tag']][$G1]['ALL_CNT'] ?? 0)+1;
        $TTLs[$setup['tag']]['ALL_G1']['ALL_CNT']=($TTLs[$setup['tag']]['ALL_G1']['ALL_CNT'] ?? 0)+1;


//  Вначале,  проверяем на Исключения:
//        -если бар т.5 является баром, достигшим уровня подхода к т.6 (из соотвествующего данному сетапу варианта P6, P6", auxP6, auxP6'), трейд не ведется.
//-В случае P6"rev (тестируем трейд на разворот от P6") если бар т.5" является баром, достигшим уровня подхода к расчетной т.6", трейд не ведется.
//	Таким образом, если расписать по парам т.5 и расчетные 6-ые получается:
//	В случае P6 (модели EAM, AM), речь идёт о t5
//	В случае P6" (модель EAM), речь идёт о t5 и t5"
//	В случае auxP6 или auxP6'  (модели EM, AM, DBM, AM/DBM, EM/DBM) речь идёт о t5
        $is_T5_ok=true;
        if(in_array($setup['trade type'],["P6rev","auxP6rev","auxP6'rev"])){
            if(is_null($model['t5'])||high($model['t5']+$baseBarNum,$v)>=$appr_level)$is_T5_ok=false;
            else $t5_=$model['t5']; // начинаем отслеживать подход c $t5_+1
        }
        elseif($setup['trade type']=="P6\"rev"){
            if(is_null($model['t5'])&&is_null($model['t5"']))$is_T5_ok=false;
            if(is_null($model['t5"']))$is_T5_ok=false;
            if(!is_null($model['t5'])&&high($model['t5']+$baseBarNum,$v)>=$appr_level||!is_null($model['t5"'])&&high($model['t5"']+$baseBarNum,$v)>=$appr_level)$is_T5_ok=false;
            if($is_T5_ok) {
                //if (!is_null($model['t5'])) $t5_ = $model['t5'];
                //else
                $t5_ = $model['t5"'];
            }
        }
        if($is_T5_ok===false){
            $TTLs[$setup['tag']][$G1]['CANCEL_CNT']=($TTLs[$setup['tag']][$G1]['CANCEL_CNT'] ?? 0)+1;
            $TTLs[$setup['tag']]['ALL_G1']['CANCEL_CNT']=($TTLs[$setup['tag']]['ALL_G1']['CANCEL_CNT'] ?? 0)+1;
            return; // если проверка т5 (исключения) провалилась, то ничего не делаем и выходим (зафиксировав отмену)
            }


        $State=0; // меняем состояние в цикле - 0=нет торговли 1-достигло подхода  99 - закрыли сделку и выходим из цикла либо выходим по превышению ожидания
        $realP6level = -999999999999;
        $curBar=$t5_+1; // начальный бар для отслеживания - t5(t5")+1
        $SL_num=0; // сколько раз переставили StopLoss - 0 - не в позиции, 1 - первоначальный , 2=переставили SL один раз, 3= переставили 2 раза (третий возможный SL)
        $AMrealP6_done=false; // флаг показывает, что был перенос на AMrealP6 - то есть больше $realP6level не меняем
        $SL_level=$initSL_level=0;
        while ($State !== 99) {  // полный перебор баров до конца чарта - выходим раньше по условиям
            $wasEvents = false; // флаг, показывающий, что на данной итерации ($curBar) были какие-то собыдтия (триггеры) (если не было, то двигаемся дальше)

            if (($curBar+$baseBarNum)  >= $ChartLen) { // дошли до правой границы чарта
                $State = 99; // выходим,  фиксируем отмену по времени, даже если была открыта позиция (так как резальтат не известен)
                $TTLs[$setup['tag']][$G1]['CANCEL_CNT']=($TTLs[$setup['tag']][$G1]['CANCEL_CNT'] ?? 0)+1;
                $TTLs[$setup['tag']]['ALL_G1']['CANCEL_CNT']=($TTLs[$setup['tag']]['ALL_G1']['CANCEL_CNT'] ?? 0)+1;
                break;
            }
            $open = open($curBar+$baseBarNum, $v);
            $close = close($curBar+$baseBarNum, $v);
            $low = low($curBar+$baseBarNum, $v);
            $high = high($curBar+$baseBarNum, $v);
            switch ($State) {
                case 0: // уровень подхода пока не достигнут

                    //(??? уточнить) если одновременно достигли уровень подхода и отмены, но бар НИСХОДЯШЩИЙ - считаем, что вначале был подход
                    if($low<=$cancel_level && $high>=$appr_level && $open>$close){
                        $State=1;
                        $wasEvents=true;
                        break;
                    }
                    // проверяем на уровень отмены:
                    if($low<=$cancel_level){
                        $State=99;
                        $TTLs[$setup['tag']][$G1]['CANCEL_CNT']=($TTLs[$setup['tag']][$G1]['CANCEL_CNT'] ?? 0)+1;
                        $TTLs[$setup['tag']]['ALL_G1']['CANCEL_CNT']=($TTLs[$setup['tag']]['ALL_G1']['CANCEL_CNT'] ?? 0)+1;
                        break;
                    }
                    // проверям на отмену по времени
                    if($curBar>($bar_0+$size_time*(1+$setup['Actual']/100))){
                        $TTLs[$setup['tag']][$G1]['CANCEL_CNT']=($TTLs[$setup['tag']][$G1]['CANCEL_CNT'] ?? 0)+1;
                        $TTLs[$setup['tag']]['ALL_G1']['CANCEL_CNT']=($TTLs[$setup['tag']]['ALL_G1']['CANCEL_CNT'] ?? 0)+1;
                        $State=99;
                        break;
                    }

                    // проверяем на достижение уровня подхода
                    if($high>=$appr_level){
                        $State=1;
                        $wasEvents = true;
                    }
                    break;
                case 1: // достигли уровня подхода
                    if (($high > $realP6level) && !$AMrealP6_done) { // в данном варианте не проверяется, не превысили ли мы уровень пробоя (Уровень пробоя нигде не фигурирует)
                       $realP6level = $high; // обновляем уровень реальной P6
                       $realP6bar = $curBar;
                       $approachLevelBar = $curBar; // ! вводим переменную с баром достижения текущего бара, чтобы проконтролировать срабатываение триггеров и достижение целей (не могут быть на этом баре)
                    }
                    // устанавливаем первоначальные значения SL и TP либо переставляем (до двух раз), если сработал соответсвующий триггер
                if($SL_num==0) { // только открываем сделку и ставим InitStopLoss
                    $SL_level = $initSL_level = $lvl_am - $size_level * $setup['InitStopLoss'] / 100;
                    $SL_num = 1; // ("переставили" 1 раз = установлен первоначальный)
                    $wasEvents = true; // было событие - на следующей итерации снова анализируем этот бар - может был SL
                    $State=1; // он не поменялся, просто для порядка
                    break;
                }

                // торговля уже идет - проверяем на срабатывание SL (делаем допущение, что на баре на котором сработал SL, цель достигнута быть не может (пессимистичный сценарий)
                if($high>$SL_level){
                    // фиксируем срабатывание SL
                    $TTLs[$setup['tag']][$G1]['TRADE_CNT']=($TTLs[$setup['tag']][$G1]['TRADE_CNT'] ?? 0)+1;
                    $TTLs[$setup['tag']]['ALL_G1']['TRADE_CNT']=($TTLs[$setup['tag']]['ALL_G1']['TRADE_CNT'] ?? 0)+1;

                    $SL_field_name="SL$SL_num"."_CNT"; // имя поля счетчика - в зависимости от номера SL (сколько было перестановок)
                    $TTLs[$setup['tag']][$G1][$SL_field_name]=($TTLs[$setup['tag']][$G1][$SL_field_name] ?? 0)+1;
                    $TTLs[$setup['tag']]['ALL_G1'][$SL_field_name]=($TTLs[$setup['tag']]['ALL_G1'][$SL_field_name] ?? 0)+1;

                    $PNL=round(($appr_level-$SL_level)/$pips,0); // фин.результат сделки без учета спреда в пипсах
                    if($PNL>0){
                        $TTLs[$setup['tag']][$G1]['PROFIT']=($TTLs[$setup['tag']][$G1]['PROFIT'] ?? 0)+$PNL;
                        $TTLs[$setup['tag']]['ALL_G1']['PROFIT']=($TTLs[$setup['tag']]['ALL_G1']['PROFIT'] ?? 0)+$PNL;
                        $TTLs[$setup['tag']][$G1]['PROFIT_proc']=($TTLs[$setup['tag']][$G1]['PROFIT_proc'] ?? 0)+$PNL/$size_time*100;
                        $TTLs[$setup['tag']]['ALL_G1']['PROFIT_proc']=($TTLs[$setup['tag']]['ALL_G1']['PROFIT_proc'] ?? 0)+$PNL/$size_time*100;
                    }
                    else{
                        $TTLs[$setup['tag']][$G1]['LOSS']=($TTLs[$setup['tag']][$G1]['LOSS'] ?? 0)-$PNL;
                        $TTLs[$setup['tag']]['ALL_G1']['LOSS']=($TTLs[$setup['tag']]['ALL_G1']['LOSS'] ?? 0)-$PNL;
                        $TTLs[$setup['tag']][$G1]['LOSS_proc']=($TTLs[$setup['tag']][$G1]['LOSS_proc'] ?? 0)-$PNL/$size_time*100;
                        $TTLs[$setup['tag']]['ALL_G1']['LOSS_proc']=($TTLs[$setup['tag']]['ALL_G1']['LOSS_proc'] ?? 0)-$PNL/$size_time*100;

                    }
                    // добавляем результаты в массив
                    $TTLs[$setup['tag']][$G1]['PNLs'][]=['model_id'=>intval($model['id']),'close_time'=>$Chart[$curBar+$baseBarNum]['dandt'],'pnl'=>$PNL,'close_lvl'=>abs($SL_level),"sizeLevel"=>$size_level/$pips];
                    $TTLs[$setup['tag']]['ALL_G1']['PNLs'][]=['model_id'=>intval($model['id']),'close_time'=>$Chart[$curBar+$baseBarNum]['dandt'],'pnl'=>$PNL,'close_lvl'=>abs($SL_level),"sizeLevel"=>$size_level/$pips];

                    // добавляем соотношения потенциальной прибыли и убытка на начало и конец трэйда
                    $tp_tmp=$appr_level-$aim1_level;
                    $sl_tmp=$initSL_level-$appr_level;
                    $TTLs[$setup['tag']][$G1]['TP_open']=($TTLs[$setup['tag']][$G1]['TP_open'] ?? 0)+$tp_tmp;
                    $TTLs[$setup['tag']][$G1]['SL_open']=($TTLs[$setup['tag']][$G1]['SL_open'] ?? 0)+$sl_tmp;
                    $TTLs[$setup['tag']]['ALL_G1']['TP_open']=($TTLs[$setup['tag']]['ALL_G1']['TP_open'] ?? 0)+$tp_tmp;
                    $TTLs[$setup['tag']]['ALL_G1']['SL_open']=($TTLs[$setup['tag']]['ALL_G1']['SL_open'] ?? 0)+$sl_tmp;

                    //$tp_tmp=$appr_level-$aim1_level;
                    $sl_tmp=$SL_level-$appr_level;
                    $TTLs[$setup['tag']][$G1]['TP_close']=($TTLs[$setup['tag']][$G1]['TP_close'] ?? 0)+$tp_tmp;
                    $TTLs[$setup['tag']][$G1]['SL_close']=($TTLs[$setup['tag']][$G1]['SL_close'] ?? 0)+$sl_tmp;
                    $TTLs[$setup['tag']]['ALL_G1']['TP_close']=($TTLs[$setup['tag']]['ALL_G1']['TP_close'] ?? 0)+$tp_tmp;
                    $TTLs[$setup['tag']]['ALL_G1']['SL_close']=($TTLs[$setup['tag']]['ALL_G1']['SL_close'] ?? 0)+$sl_tmp;

                    $State=99;
                    break;
                } // вышли по SL

                 //торговля уже идет, проверяем на срабатывание триггеров
                    // проверяем не нужно ли отработать AlternateTrigger1 (достигнут уровень расчетной т6
                    if($setup['AlternateTrigger1'] ?? false) // задан альтернативный триггер1
                        if($SL_num==1 && $low<($lvl_am-$size_level*$setup['AlternateTrigger1']/100) && $realP6level>=$lvl_am && $curBar>($t5_+1)
                            && $curBar != $approachLevelBar // ! триггер не может запускаться на баре,на котором достигнут уровень подхода к 6-ой
                        ){ // выполнилось условие и бар не является баром открытия сделки
                        $wasEvents=true;
                        $State=1;
                        $SL_num=2;
                        if($setup['Trailing1']=='AMrealP6'){
                            $AMrealP6_done=true; // принято решение о переносе SL на AMrealP6 -> перестаем отслеживать реальную P6
                            $SL_level=$realP6level+$pips;
                        }
                        else $SL_level=$lvl_am-$size_level*$setup['Trailing1']/100;
                        break;
                    }

                    // проверяем отработку обычного триггера1
                    if($setup['Trigger1'] ?? false)
                        if($SL_num==1 && $low<($lvl_am-$size_level*$setup['Trigger1']/100) && $curBar>($t5_+1)
                            && $curBar != $approachLevelBar // ! триггер не может запускаться на баре,на котором достигнут уровень подхода к 6-ой
                        ){ // выполнилось условие и бар не является баром открытия сделки
                        $wasEvents=true;
                        $State=1;
                        $SL_num=2;
                        if($setup['Trailing1']=='AMrealP6'){
                            $AMrealP6_done=true; // ??? был первый перенос -> перестаем отслеживать реальную P6
                            $SL_level=$realP6level+$pips;
                        }
                        else $SL_level=$lvl_am-$size_level*$setup['Trailing1']/100;
                        break;
                    }

                    // проверяем отработку триггера2
                    if($setup['Trigger2'] ?? false)
                        if($SL_num<=2 && $low<($lvl_am-$size_level*$setup['Trigger2']/100) && $curBar>($t5_+1)
                            && $curBar != $approachLevelBar // ! триггер не может запускаться на баре,на котором достигнут уровень подхода к 6-ой
                        ){ // выполнилось условие и бар не является баром открытия сделки
                            // ВНИМАНИЕ! (сейчас сделано, что даже если триггер1 не сработал, то второй может сработать)
                            $wasEvents=true;
                            $State=1;
                            $SL_num=3;
                            if($setup['Trailing2']=='AMrealP6'){
                                $AMrealP6_done=true; // ??? был первый перенос -> перестаем отслеживать реальную P6
                                $SL_level=$realP6level+$pips;
                            }
                            else $SL_level=$lvl_am-$size_level*$setup['Trailing2']/100;
                            break;
                        }

                    //далее проверяем достижение Aim1

                    if($low<$aim1_level
                        && $curBar != $approachLevelBar // ! триггер не может запускаться на баре,на котором достигнут уровень подхода к 6-ой
                    ){ // выполнилось условие
                        // фиксируем срабатывание TP (Aim1)
                        $TTLs[$setup['tag']][$G1]['TRADE_CNT']=($TTLs[$setup['tag']][$G1]['TRADE_CNT'] ?? 0)+1;
                        $TTLs[$setup['tag']]['ALL_G1']['TRADE_CNT']=($TTLs[$setup['tag']]['ALL_G1']['TRADE_CNT'] ?? 0)+1;

                        $TTLs[$setup['tag']][$G1]['AIM_CNT']=($TTLs[$setup['tag']][$G1]['AIM_CNT'] ?? 0)+1;
                        $TTLs[$setup['tag']]['ALL_G1']['AIM_CNT']=($TTLs[$setup['tag']]['ALL_G1']['AIM_CNT'] ?? 0)+1;

                        $PNL=round(($appr_level-($lvl_am-$size_level*$setup['Aim1']/100))/$pips,0); // фин.результат сделки без учета спреда - ур.входа-TP
                        if($PNL>0){
                            $TTLs[$setup['tag']][$G1]['PROFIT']=($TTLs[$setup['tag']][$G1]['PROFIT'] ?? 0)+$PNL;
                            $TTLs[$setup['tag']]['ALL_G1']['PROFIT']=($TTLs[$setup['tag']]['ALL_G1']['PROFIT'] ?? 0)+$PNL;
                            $TTLs[$setup['tag']][$G1]['PROFIT_proc']=($TTLs[$setup['tag']][$G1]['PROFIT_proc'] ?? 0)+$PNL/$size_time*100;
                            $TTLs[$setup['tag']]['ALL_G1']['PROFIT_proc']=($TTLs[$setup['tag']]['ALL_G1']['PROFIT_proc'] ?? 0)+$PNL/$size_time*100;

                        }
                        else{
                            $TTLs[$setup['tag']][$G1]['LOSS']=($TTLs[$setup['tag']][$G1]['LOSS'] ?? 0)-$PNL;
                            $TTLs[$setup['tag']]['ALL_G1']['LOSS']=($TTLs[$setup['tag']]['ALL_G1']['LOSS'] ?? 0)-$PNL;
                            $TTLs[$setup['tag']][$G1]['LOSS_proc']=($TTLs[$setup['tag']][$G1]['LOSS_proc'] ?? 0)-$PNL/$size_time*100;
                            $TTLs[$setup['tag']]['ALL_G1']['LOSS_proc']=($TTLs[$setup['tag']]['ALL_G1']['LOSS_proc'] ?? 0)-$PNL/$size_time*100;
                        }
                        // добавляем результаты в массив
                        $TTLs[$setup['tag']][$G1]['PNLs'][]=['model_id'=>intval($model['id']),'close_time'=>$Chart[$curBar+$baseBarNum]['dandt'],'pnl'=>$PNL,'close_lvl'=>abs($aim1_level),"sizeLevel"=>$size_level/$pips];
                        $TTLs[$setup['tag']]['ALL_G1']['PNLs'][]=['model_id'=>intval($model['id']),'close_time'=>$Chart[$curBar+$baseBarNum]['dandt'],'pnl'=>$PNL,'close_lvl'=>abs($aim1_level),"sizeLevel"=>$size_level/$pips];

                        // добавляем соотношения потенциальной прибыли и убытка на начало и конец трэйда
                        $tp_tmp=$appr_level-$aim1_level;
                        $sl_tmp=$initSL_level-$appr_level;
                        $TTLs[$setup['tag']][$G1]['TP_open']=($TTLs[$setup['tag']][$G1]['TP_open'] ?? 0)+$tp_tmp;
                        $TTLs[$setup['tag']][$G1]['SL_open']=($TTLs[$setup['tag']][$G1]['SL_open'] ?? 0)+$sl_tmp;
                        $TTLs[$setup['tag']]['ALL_G1']['TP_open']=($TTLs[$setup['tag']]['ALL_G1']['TP_open'] ?? 0)+$tp_tmp;
                        $TTLs[$setup['tag']]['ALL_G1']['SL_open']=($TTLs[$setup['tag']]['ALL_G1']['SL_open'] ?? 0)+$sl_tmp;

                        //$tp_tmp=$appr_level-$aim1_level;
                        $sl_tmp=$SL_level-$appr_level;
                        $TTLs[$setup['tag']][$G1]['TP_close']=($TTLs[$setup['tag']][$G1]['TP_close'] ?? 0)+$tp_tmp;
                        $TTLs[$setup['tag']][$G1]['SL_close']=($TTLs[$setup['tag']][$G1]['SL_close'] ?? 0)+$sl_tmp;
                        $TTLs[$setup['tag']]['ALL_G1']['TP_close']=($TTLs[$setup['tag']]['ALL_G1']['TP_close'] ?? 0)+$tp_tmp;
                        $TTLs[$setup['tag']]['ALL_G1']['SL_close']=($TTLs[$setup['tag']]['ALL_G1']['SL_close'] ?? 0)+$sl_tmp;

                        $State=99;
                        break;
                    }


            }

            if(!$wasEvents)$curBar++; // следующий бар если событий не было - иначе отрабатываем новый $State
            } //while ($State !== 99)

    } //[1] вариaнт торговли на разворот

    if(substr($setup['trade type'],-5)=='reach'){ // [2] вариaнт торговли на достижение 6-ой (P6reach, P6"reach, auxP6reach, auxP6'reach)
        $tradesCnt++;
        $TTLs[$setup['tag']][$G1]['ALL_CNT']=($TTLs[$setup['tag']][$G1]['ALL_CNT'] ?? 0)+1;
        $TTLs[$setup['tag']]['ALL_G1']['ALL_CNT']=($TTLs[$setup['tag']]['ALL_G1']['ALL_CNT'] ?? 0)+1;

        $t5_ = $model['t5']; // (уточнить!!! - может t5" для P6"reach ?)

        $State=0; // меняем состояние в цикле - 0=нет торговли 1-достигло подхода  99 - закрыли сделку и выходим из цикла либо выходим по превышению ожидания
        $curBar=$t5_; // начальный бар для отслеживания - t5 (начинаем с t5, так как нужно зафиксировать отмену, если бар t5 подтвердил t4
        $SL_num=0; // сколько раз переставили StopLoss - 0 - не в позиции, 1 - первоначальный , 2=переставили SL один раз, 3= переставили 2 раза (третий возможный SL)
        $SL_level=$initSL_level=0;
        $t4_level=high($model['t4']+$baseBarNum,$v);
        while ($State !== 99) {  // полный перебор баров до конца чарта - выходим раньше по условиям
            $wasEvents = false; // флаг, показывающий, что на данной итерации ($curBar) были какие-то собыдтия (триггеры) (если не было, то двигаемся дальше)

            if (($curBar+$baseBarNum)  >= $ChartLen) { // дошли до правой границы чарта
                $State = 99; // выходим,  фиксируем отмену по времени, даже если была открыта позиция (так как резальтат не известен)
                $TTLs[$setup['tag']][$G1]['CANCEL_CNT']=($TTLs[$setup['tag']][$G1]['CANCEL_CNT'] ?? 0)+1;
                $TTLs[$setup['tag']]['ALL_G1']['CANCEL_CNT']=($TTLs[$setup['tag']]['ALL_G1']['CANCEL_CNT'] ?? 0)+1;
                break;
            }
            $open = open($curBar+$baseBarNum, $v);
            $close = close($curBar+$baseBarNum, $v);
            $low = low($curBar+$baseBarNum, $v);
            $high = high($curBar+$baseBarNum, $v);
            switch ($State) {
                case 0: // ждем подтверждения t4

                    //(??? уточнить) если одновременно достигли уровень t4 (подтвердили t4) и отмены, но бар НИСХОДЯШЩИЙ - считаем, что вначале был t4
                    if($low<=$cancel_level && $high>=$t4_level && $open>$close){
                        //-если бар т.5 является баром, подтвердившим т.4, то трейд не ведется.
                        //-В случае P6"reach (тестируем трейд на достижение P6") если бар т.5" является баром подтвердившим т.4, то трейд не ведется.
                        if($curBar==$t5_||$setup['trade type']=='P6"reach'&&$curBar==$model['t5"']){
                            $TTLs[$setup['tag']][$G1]['CANCEL_CNT']=($TTLs[$setup['tag']][$G1]['CANCEL_CNT'] ?? 0)+1;
                            $TTLs[$setup['tag']]['ALL_G1']['CANCEL_CNT']=($TTLs[$setup['tag']]['ALL_G1']['CANCEL_CNT'] ?? 0)+1;
                            $State=99;
                            break;
                        }
                        $State=1; // отрыли позицию
                        $wasEvents=true;
                        break;
                    }
                    // проверяем на уровень отмены:
                    if($low<=$cancel_level){
                        $State=99;
                        $TTLs[$setup['tag']][$G1]['CANCEL_CNT']=($TTLs[$setup['tag']][$G1]['CANCEL_CNT'] ?? 0)+1;
                        $TTLs[$setup['tag']]['ALL_G1']['CANCEL_CNT']=($TTLs[$setup['tag']]['ALL_G1']['CANCEL_CNT'] ?? 0)+1;
                        break;
                    }
                    // проверям на отмену по времени
                    if($curBar>($bar_0+$size_time*(1+$setup['Actual']/100))){
                        $TTLs[$setup['tag']][$G1]['CANCEL_CNT']=($TTLs[$setup['tag']][$G1]['CANCEL_CNT'] ?? 0)+1;
                        $TTLs[$setup['tag']]['ALL_G1']['CANCEL_CNT']=($TTLs[$setup['tag']]['ALL_G1']['CANCEL_CNT'] ?? 0)+1;
                        $State=99;
                        break;
                    }

                    // проверяем на достижение уровня t4 (подтвердили)
                    if($high>=$t4_level){
                        //-если бар т.5 является баром, подтвердившим т.4, то трейд не ведется.
                        //-В случае P6"reach (тестируем трейд на достижение P6") если бар т.5" является баром подтвердившим т.4, то трейд не ведется.
                        if($curBar==$t5_||$setup['trade type']=='P6"reach'&&$curBar==$model['t5"']){
                            $TTLs[$setup['tag']][$G1]['CANCEL_CNT']=($TTLs[$setup['tag']][$G1]['CANCEL_CNT'] ?? 0)+1;
                            $TTLs[$setup['tag']]['ALL_G1']['CANCEL_CNT']=($TTLs[$setup['tag']]['ALL_G1']['CANCEL_CNT'] ?? 0)+1;
                            $State=99;
                            break;
                        }
                        $State=1;
                        $wasEvents = true;
                        break;
                    }
                    break;
                case 1: // достигли уровня t4 (подтвердили t4) и проверили на исключение
                    // устанавливаем первоначальные значения SL и TP либо переставляем (до двух раз), если сработал соответсвующий триггер
                    if($SL_num==0) { // только открываем сделку и ставим InitStopLoss
                        if($setup['InitStopLoss']=='t5')$SL_level=$initSL_level=low($model['t5']+$baseBarNum,$v)-$pips;
                        else $SL_level=$initSL_level=$t4_level-$size_level*$setup['InitStopLoss']/100;
                        $SL_num = 1; // ("переставили" 1 раз = установлен первоначальный)
                        $wasEvents = true; // было событие - на следующей итерации снова анализируем этот бар - может был SL
                        $State=1; // он не поменялся, просто для порядка
                        break;
                    }
                    // торговля уже идет - проверяем на срабатывание SL (делаем допущение, что на баре на котором сработал SL, цель достигнута быть не может (пессимистичный сценарий)
                    if($low<$SL_level){
                        // фиксируем срабатывание SL
                        $TTLs[$setup['tag']][$G1]['TRADE_CNT']=($TTLs[$setup['tag']][$G1]['TRADE_CNT'] ?? 0)+1;
                        $TTLs[$setup['tag']]['ALL_G1']['TRADE_CNT']=($TTLs[$setup['tag']]['ALL_G1']['TRADE_CNT'] ?? 0)+1;

                        $SL_field_name="SL$SL_num"."_CNT"; // имя поля счетчика - в зависимости от номера SL (сколько было перестановок)
                        $TTLs[$setup['tag']][$G1][$SL_field_name]=($TTLs[$setup['tag']][$G1][$SL_field_name] ?? 0)+1;
                        $TTLs[$setup['tag']]['ALL_G1'][$SL_field_name]=($TTLs[$setup['tag']]['ALL_G1'][$SL_field_name] ?? 0)+1;

                        $PNL=round(($SL_level-$t4_level)/$pips,0); // фин.результат сделки без учета спреда в пипсах
                        if($PNL>0){
                            $TTLs[$setup['tag']][$G1]['PROFIT']=($TTLs[$setup['tag']][$G1]['PROFIT'] ?? 0)+$PNL;
                            $TTLs[$setup['tag']]['ALL_G1']['PROFIT']=($TTLs[$setup['tag']]['ALL_G1']['PROFIT'] ?? 0)+$PNL;
                            $TTLs[$setup['tag']][$G1]['PROFIT_proc']=($TTLs[$setup['tag']][$G1]['PROFIT_proc'] ?? 0)+$PNL/$size_time*100;
                            $TTLs[$setup['tag']]['ALL_G1']['PROFIT_proc']=($TTLs[$setup['tag']]['ALL_G1']['PROFIT_proc'] ?? 0)+$PNL/$size_time*100;

                        }
                        else{
                            $TTLs[$setup['tag']][$G1]['LOSS']=($TTLs[$setup['tag']][$G1]['LOSS'] ?? 0)-$PNL;
                            $TTLs[$setup['tag']]['ALL_G1']['LOSS']=($TTLs[$setup['tag']]['ALL_G1']['LOSS'] ?? 0)-$PNL;
                            $TTLs[$setup['tag']][$G1]['LOSS_proc']=($TTLs[$setup['tag']][$G1]['LOSS_proc'] ?? 0)-$PNL/$size_time*100;
                            $TTLs[$setup['tag']]['ALL_G1']['LOSS_proc']=($TTLs[$setup['tag']]['ALL_G1']['LOSS_proc'] ?? 0)-$PNL/$size_time*100;
                        }
                        // добавляем результаты в массив
                        $TTLs[$setup['tag']][$G1]['PNLs'][]=['model_id'=>intval($model['id']),'close_time'=>$Chart[$curBar+$baseBarNum]['dandt'],'pnl'=>$PNL,'close_lvl'=>abs($SL_level),"sizeLevel"=>$size_level/$pips];
                        $TTLs[$setup['tag']]['ALL_G1']['PNLs'][]=['model_id'=>intval($model['id']),'close_time'=>$Chart[$curBar+$baseBarNum]['dandt'],'pnl'=>$PNL,'close_lvl'=>abs($SL_level),"sizeLevel"=>$size_level/$pips];

                        // добавляем соотношения потенциальной прибыли и убытка на начало и конец трэйда
                        $tp_tmp=$aim1_level-$t4_level;
                        $sl_tmp=$t4_level-$initSL_level;
                        $TTLs[$setup['tag']][$G1]['TP_open']=($TTLs[$setup['tag']][$G1]['TP_open'] ?? 0)+$tp_tmp;
                        $TTLs[$setup['tag']][$G1]['SL_open']=($TTLs[$setup['tag']][$G1]['SL_open'] ?? 0)+$sl_tmp;
                        $TTLs[$setup['tag']]['ALL_G1']['TP_open']=($TTLs[$setup['tag']]['ALL_G1']['TP_open'] ?? 0)+$tp_tmp;
                        $TTLs[$setup['tag']]['ALL_G1']['SL_open']=($TTLs[$setup['tag']]['ALL_G1']['SL_open'] ?? 0)+$sl_tmp;

                        //$tp_tmp=$aim1_level-$t4_level;
                        $sl_tmp=$t4_level-$SL_level;
                        $TTLs[$setup['tag']][$G1]['TP_close']=($TTLs[$setup['tag']][$G1]['TP_close'] ?? 0)+$tp_tmp;
                        $TTLs[$setup['tag']][$G1]['SL_close']=($TTLs[$setup['tag']][$G1]['SL_close'] ?? 0)+$sl_tmp;
                        $TTLs[$setup['tag']]['ALL_G1']['TP_close']=($TTLs[$setup['tag']]['ALL_G1']['TP_close'] ?? 0)+$tp_tmp;
                        $TTLs[$setup['tag']]['ALL_G1']['SL_close']=($TTLs[$setup['tag']]['ALL_G1']['SL_close'] ?? 0)+$sl_tmp;

                        $State=99;
                        break;
                    } // вышли по SL

                    //торговля уже идет, проверяем на срабатывание триггеров (альтернативных триггеров тут, насколько я понял, нет
                    if(($setup['Trigger1'] ?? false) && $SL_num==1) {
                        $trigger1_level=$lvl_am - $size_level * $setup['Trigger1'] / 100;

                        if($setup['Trailing1']=='t5')$trailing1_level=low($model['t5']+$baseBarNum,$v)-$pips;
                        else $trailing1_level=$lvl_am - $size_level * $setup['Trailing1'] / 100;

                        if($high>$trigger1_level && $trigger1_level>$SL_level && $trailing1_level>$SL_level){ //триггер должен быть ближе к т.6, чем стоп лос и трэилинг должен быть ближе к т.6, чем стоп лос
                            $wasEvents = true;
                            $State = 1;
                            $SL_num = 2;
                            $SL_level = $trailing1_level;
                            break;
                        }
                    }

                    // проверяем отработку триггера2
                    if(($setup['Trigger2'] ?? false) && $SL_num<=2) {
                        $trigger2_level=$lvl_am - $size_level * $setup['Trigger2'] / 100;

                        if($setup['Trailing2']=='t5')$trailing2_level=low($model['t5']+$baseBarNum,$v);
                        else $trailing2_level=$lvl_am - $size_level * $setup['Trailing2'] / 100;

                        if($high>$trigger2_level && $trigger2_level>$SL_level && $trailing2_level>$SL_level){ //триггер должен быть ближе к т.6, чем стоп лос и трэилинг должен быть ближе к т.6, чем стоп лос
                            $wasEvents = true;
                            $State = 1;
                            $SL_num = 3;
                            $SL_level = $trailing2_level;
                            break;
                        }
                    }

                    //далее проверяем достижение Aim1

                    if($high>=$aim1_level){ // выполнилось условие достижения цели
                        // фиксируем срабатывание TP (Aim1)
                        $TTLs[$setup['tag']][$G1]['TRADE_CNT']=($TTLs[$setup['tag']][$G1]['TRADE_CNT'] ?? 0)+1;
                        $TTLs[$setup['tag']]['ALL_G1']['TRADE_CNT']=($TTLs[$setup['tag']]['ALL_G1']['TRADE_CNT'] ?? 0)+1;

                        $TTLs[$setup['tag']][$G1]['AIM_CNT']=($TTLs[$setup['tag']][$G1]['AIM_CNT'] ?? 0)+1;
                        $TTLs[$setup['tag']]['ALL_G1']['AIM_CNT']=($TTLs[$setup['tag']]['ALL_G1']['AIM_CNT'] ?? 0)+1;

                        $PNL=round(($aim1_level-$t4_level)/$pips,0); // фин.результат сделки без учета спреда - ур.входа-TP
                        if($PNL>0){
                            $TTLs[$setup['tag']][$G1]['PROFIT']=($TTLs[$setup['tag']][$G1]['PROFIT'] ?? 0)+$PNL;
                            $TTLs[$setup['tag']]['ALL_G1']['PROFIT']=($TTLs[$setup['tag']]['ALL_G1']['PROFIT'] ?? 0)+$PNL;
                            $TTLs[$setup['tag']][$G1]['PROFIT_proc']=($TTLs[$setup['tag']][$G1]['PROFIT_proc'] ?? 0)+$PNL/$size_time*100;
                            $TTLs[$setup['tag']]['ALL_G1']['PROFIT_proc']=($TTLs[$setup['tag']]['ALL_G1']['PROFIT_proc'] ?? 0)+$PNL/$size_time*100;
                        }
                        else{
                            $TTLs[$setup['tag']][$G1]['LOSS']=($TTLs[$setup['tag']][$G1]['LOSS'] ?? 0)-$PNL;
                            $TTLs[$setup['tag']]['ALL_G1']['LOSS']=($TTLs[$setup['tag']]['ALL_G1']['LOSS'] ?? 0)-$PNL;
                            $TTLs[$setup['tag']][$G1]['LOSS_proc']=($TTLs[$setup['tag']][$G1]['LOSS_proc'] ?? 0)-$PNL/$size_time*100;
                            $TTLs[$setup['tag']]['ALL_G1']['LOSS_proc']=($TTLs[$setup['tag']]['ALL_G1']['LOSS_proc'] ?? 0)-$PNL/$size_time*100;
                        }
                        // добавляем результаты в массив
                        $TTLs[$setup['tag']][$G1]['PNLs'][]=['model_id'=>intval($model['id']),'close_time'=>$Chart[$curBar+$baseBarNum]['dandt'],'pnl'=>$PNL,'close_lvl'=>$aim1_level,"sizeLevel"=>$size_level/$pips];
                        $TTLs[$setup['tag']]['ALL_G1']['PNLs'][]=['model_id'=>intval($model['id']),'close_time'=>$Chart[$curBar+$baseBarNum]['dandt'],'pnl'=>$PNL,'close_lvl'=>$aim1_level,"sizeLevel"=>$size_level/$pips];

                        // добавляем соотношения потенциальной прибыли и убытка на начало и конец трэйда
                        $tp_tmp=$aim1_level-$t4_level;
                        $sl_tmp=$t4_level-$initSL_level;
                        $TTLs[$setup['tag']][$G1]['TP_open']=($TTLs[$setup['tag']][$G1]['TP_open'] ?? 0)+$tp_tmp;
                        $TTLs[$setup['tag']][$G1]['SL_open']=($TTLs[$setup['tag']][$G1]['SL_open'] ?? 0)+$sl_tmp;
                        $TTLs[$setup['tag']]['ALL_G1']['TP_open']=($TTLs[$setup['tag']]['ALL_G1']['TP_open'] ?? 0)+$tp_tmp;
                        $TTLs[$setup['tag']]['ALL_G1']['SL_open']=($TTLs[$setup['tag']]['ALL_G1']['SL_open'] ?? 0)+$sl_tmp;

                        //$tp_tmp=$aim1_level-$t4_level;
                        $sl_tmp=$t4_level-$SL_level;
                        $TTLs[$setup['tag']][$G1]['TP_close']=($TTLs[$setup['tag']][$G1]['TP_close'] ?? 0)+$tp_tmp;
                        $TTLs[$setup['tag']][$G1]['SL_close']=($TTLs[$setup['tag']][$G1]['SL_close'] ?? 0)+$sl_tmp;
                        $TTLs[$setup['tag']]['ALL_G1']['TP_close']=($TTLs[$setup['tag']]['ALL_G1']['TP_close'] ?? 0)+$tp_tmp;
                        $TTLs[$setup['tag']]['ALL_G1']['SL_close']=($TTLs[$setup['tag']]['ALL_G1']['SL_close'] ?? 0)+$sl_tmp;

                        $State=99;
                        break;
                    }


            }

            if(!$wasEvents)$curBar++; // следующий бар если событий не было - иначе отрабатываем новый $State
        } //while ($State !== 99)

    } //[2] вариaнт торговли на достижение P6

    if(substr($setup['trade type'],-4)=='over'){ // [3] вариaнт торговли на пробой 6-ой (P6over, P6"over, auxP6over, auxP6'over)
        $tradesCnt++;
        $TTLs[$setup['tag']][$G1]['ALL_CNT']=($TTLs[$setup['tag']][$G1]['ALL_CNT'] ?? 0)+1;
        $TTLs[$setup['tag']]['ALL_G1']['ALL_CNT']=($TTLs[$setup['tag']]['ALL_G1']['ALL_CNT'] ?? 0)+1;

        $t5_ = $model['t5']; //

        $State=0; // меняем состояние в цикле - 0=нет торговли 1-достигло подхода  99 - закрыли сделку и выходим из цикла либо выходим по превышению ожидания
        $curBar=$t5_; // начальный бар для отслеживания - t5 (начинаем с t5, так как нужно зафиксировать отмену, если бар t5 подтвердил t4
        $SL_num=0; // сколько раз переставили StopLoss - 0 - не в позиции, 1 - первоначальный , 2=переставили SL один раз, 3= переставили 2 раза (третий возможный SL)
        $SL_level=$initSL_level=0;
        $t4_level=high($model['t4']+$baseBarNum,$v);
        $open_level=$t4_level; // потом переопределим в зависимости от тактики
        $open_bar=-1; // бар, на котором открыли позицию - определим при достижении нужного уровня
        while ($State !== 99) {  // полный перебор баров до конца чарта - выходим раньше по условиям
            $wasEvents = false; // флаг, показывающий, что на данной итерации ($curBar) были какие-то собыдтия (триггеры) (если не было, то двигаемся дальше)

            if (($curBar+$baseBarNum)  >= $ChartLen) { // дошли до правой границы чарта
                $State = 99; // выходим,  фиксируем отмену по времени, даже если была открыта позиция (так как резальтат не известен)
                $TTLs[$setup['tag']][$G1]['CANCEL_CNT']=($TTLs[$setup['tag']][$G1]['CANCEL_CNT'] ?? 0)+1;
                $TTLs[$setup['tag']]['ALL_G1']['CANCEL_CNT']=($TTLs[$setup['tag']]['ALL_G1']['CANCEL_CNT'] ?? 0)+1;
                break;
            }
            $open = open($curBar+$baseBarNum, $v);
            $close = close($curBar+$baseBarNum, $v);
            $low = low($curBar+$baseBarNum, $v);
            $high = high($curBar+$baseBarNum, $v);
            switch ($State) {
                case 0: // ждем пробоя P6 для классической тактики либо подтверждения t4 для агрессивной

                    //(??? уточнить) если одновременно достигли нужного уровня и уровня отмены, но бар НИСХОДЯШЩИЙ - считаем, что вначале был нужный уровень
                    if($low<=$cancel_level && ($high>=$t4_level && $isAggressive || $high>=$brkd_level && !$isAggressive) && $open>$close){
                        //-если бар т.5 является баром, на котором случилось событие, то Cancel
                        if($curBar==$t5_||$setup['trade type']=='P6"over'&&$curBar==$model['t5"']){
                            $TTLs[$setup['tag']][$G1]['CANCEL_CNT']=($TTLs[$setup['tag']][$G1]['CANCEL_CNT'] ?? 0)+1;
                            $TTLs[$setup['tag']]['ALL_G1']['CANCEL_CNT']=($TTLs[$setup['tag']]['ALL_G1']['CANCEL_CNT'] ?? 0)+1;
                            $State=99;
                            break;
                        }
                        $State=1; // отрыли позицию
                        $open_bar=$curBar;
                        $wasEvents=true;
                        break;
                    }
                    // проверяем на уровень отмены:
                    if($low<=$cancel_level){
                        $State=99;
                        $TTLs[$setup['tag']][$G1]['CANCEL_CNT']=($TTLs[$setup['tag']][$G1]['CANCEL_CNT'] ?? 0)+1;
                        $TTLs[$setup['tag']]['ALL_G1']['CANCEL_CNT']=($TTLs[$setup['tag']]['ALL_G1']['CANCEL_CNT'] ?? 0)+1;
                        break;
                    }
                    // проверям на отмену по времени
                    if($curBar>($bar_0+$size_time*(1+$setup['Actual']/100))){
                        $TTLs[$setup['tag']][$G1]['CANCEL_CNT']=($TTLs[$setup['tag']][$G1]['CANCEL_CNT'] ?? 0)+1;
                        $TTLs[$setup['tag']]['ALL_G1']['CANCEL_CNT']=($TTLs[$setup['tag']]['ALL_G1']['CANCEL_CNT'] ?? 0)+1;
                        $State=99;
                        break;
                    }

                    // проверяем на достижение уровня (t4 или пробоя P6 в зависимости от тактики торговли)
                    if($high>=$t4_level && $isAggressive || $high>=$brkd_level && !$isAggressive){
                        //-если бар т.5 является баром, подтвердившим т.4, то трейд не ведется.
                        //-В случае P6"reach (тестируем трейд на достижение P6") если бар т.5" является баром подтвердившим т.4, то трейд не ведется.
                        if($curBar==$t5_||$setup['trade type']=='P6"over'&&$curBar==$model['t5"']){
                            $TTLs[$setup['tag']][$G1]['CANCEL_CNT']=($TTLs[$setup['tag']][$G1]['CANCEL_CNT'] ?? 0)+1;
                            $TTLs[$setup['tag']]['ALL_G1']['CANCEL_CNT']=($TTLs[$setup['tag']]['ALL_G1']['CANCEL_CNT'] ?? 0)+1;
                            $State=99;
                            break;
                        }
                        $State=1;
                        $open_bar=$curBar;
                        $wasEvents = true;
                        break;
                    }
                    break;
                case 1: // достигли уровня (t4 или пробоя P6 в зависимости от тактики торговли)
                    // устанавливаем первоначальные значения SL и TP либо переставляем (до двух раз), если сработал соответсвующий триггер
                    if($SL_num==0) { // только открываем сделку и ставим InitStopLoss
                        if($setup['InitStopLoss']=='t5')$SL_level=$initSL_level=low($model['t5']+$baseBarNum,$v)-$pips;
                        else {
                            if($isAggressive)$SL_level=$initSL_level=$t4_level-$size_level*$setup['InitStopLoss']/100;
                            else $SL_level=$initSL_level=$lvl_am-$size_level*$setup['InitStopLoss']/100;
                        }
                        $open_level=$isAggressive?$t4_level:$brkd_level; // уровень открытия позиции (в зависимости от тактики)
                        $SL_num = 1; // ("переставили" 1 раз = установлен первоначальный)
                        $wasEvents = true; // было событие - на следующей итерации снова анализируем этот бар - может был SL
                        $State=1; // он не поменялся, просто для порядка
                        break;
                    }
                    // торговля уже идет - проверяем на срабатывание SL (делаем допущение, что на баре на котором сработал SL, цель достигнута быть не может (пессимистичный сценарий)

                    if($low<$SL_level){
                        // фиксируем срабатывание SL
                        $TTLs[$setup['tag']][$G1]['TRADE_CNT']=($TTLs[$setup['tag']][$G1]['TRADE_CNT'] ?? 0)+1;
                        $TTLs[$setup['tag']]['ALL_G1']['TRADE_CNT']=($TTLs[$setup['tag']]['ALL_G1']['TRADE_CNT'] ?? 0)+1;

                        $SL_field_name="SL$SL_num"."_CNT"; // имя поля счетчика - в зависимости от номера SL (сколько было перестановок)
                        $TTLs[$setup['tag']][$G1][$SL_field_name]=($TTLs[$setup['tag']][$G1][$SL_field_name] ?? 0)+1;
                        $TTLs[$setup['tag']]['ALL_G1'][$SL_field_name]=($TTLs[$setup['tag']]['ALL_G1'][$SL_field_name] ?? 0)+1;

                        $PNL=round(($SL_level-$open_level)/$pips,0); // фин.результат сделки без учета спреда в пипсах
                        if($PNL>0){
                            $TTLs[$setup['tag']][$G1]['PROFIT']=($TTLs[$setup['tag']][$G1]['PROFIT'] ?? 0)+$PNL;
                            $TTLs[$setup['tag']]['ALL_G1']['PROFIT']=($TTLs[$setup['tag']]['ALL_G1']['PROFIT'] ?? 0)+$PNL;
                            $TTLs[$setup['tag']][$G1]['PROFIT_proc']=($TTLs[$setup['tag']][$G1]['PROFIT_proc'] ?? 0)+$PNL/$size_time*100;
                            $TTLs[$setup['tag']]['ALL_G1']['PROFIT_proc']=($TTLs[$setup['tag']]['ALL_G1']['PROFIT_proc'] ?? 0)+$PNL/$size_time*100;
                        }
                        else{
                            $TTLs[$setup['tag']][$G1]['LOSS']=($TTLs[$setup['tag']][$G1]['LOSS'] ?? 0)-$PNL;
                            $TTLs[$setup['tag']]['ALL_G1']['LOSS']=($TTLs[$setup['tag']]['ALL_G1']['LOSS'] ?? 0)-$PNL;
                            $TTLs[$setup['tag']][$G1]['LOSS_proc']=($TTLs[$setup['tag']][$G1]['LOSS_proc'] ?? 0)-$PNL/$size_time*100;
                            $TTLs[$setup['tag']]['ALL_G1']['LOSS_proc']=($TTLs[$setup['tag']]['ALL_G1']['LOSS_proc'] ?? 0)-$PNL/$size_time*100;
                        }
                        // добавляем результаты в массив
                        $TTLs[$setup['tag']][$G1]['PNLs'][]=['model_id'=>intval($model['id']),'close_time'=>$Chart[$curBar+$baseBarNum]['dandt'],'pnl'=>$PNL,'close_lvl'=>abs($SL_level),"sizeLevel"=>$size_level/$pips];
                        $TTLs[$setup['tag']]['ALL_G1']['PNLs'][]=['model_id'=>intval($model['id']),'close_time'=>$Chart[$curBar+$baseBarNum]['dandt'],'pnl'=>$PNL,'close_lvl'=>abs($SL_level),"sizeLevel"=>$size_level/$pips];

                        // добавляем соотношения потенциальной прибыли и убытка на начало и конец трэйда
                        $tp_tmp=$aim1_level-$open_level;
                        $sl_tmp=$open_level-$initSL_level;
                        $TTLs[$setup['tag']][$G1]['TP_open']=($TTLs[$setup['tag']][$G1]['TP_open'] ?? 0)+$tp_tmp;
                        $TTLs[$setup['tag']][$G1]['SL_open']=($TTLs[$setup['tag']][$G1]['SL_open'] ?? 0)+$sl_tmp;
                        $TTLs[$setup['tag']]['ALL_G1']['TP_open']=($TTLs[$setup['tag']]['ALL_G1']['TP_open'] ?? 0)+$tp_tmp;
                        $TTLs[$setup['tag']]['ALL_G1']['SL_open']=($TTLs[$setup['tag']]['ALL_G1']['SL_open'] ?? 0)+$sl_tmp;

                        //$tp_tmp=$aim1_level-$t4_level;
                        $sl_tmp=$open_level-$SL_level;
                        $TTLs[$setup['tag']][$G1]['TP_close']=($TTLs[$setup['tag']][$G1]['TP_close'] ?? 0)+$tp_tmp;
                        $TTLs[$setup['tag']][$G1]['SL_close']=($TTLs[$setup['tag']][$G1]['SL_close'] ?? 0)+$sl_tmp;
                        $TTLs[$setup['tag']]['ALL_G1']['TP_close']=($TTLs[$setup['tag']]['ALL_G1']['TP_close'] ?? 0)+$tp_tmp;
                        $TTLs[$setup['tag']]['ALL_G1']['SL_close']=($TTLs[$setup['tag']]['ALL_G1']['SL_close'] ?? 0)+$sl_tmp;

                        $State=99;
                        break;
                    } // вышли по SL


                    //	   	В случае, если модель не подходит под проверочные условия сетапа, трейд закрывается
                    //		--  если бар, подтвердивший т.4 достигает стоп-лос, сделка закрывается по стоп лосу
                    //		--	 иначе позиция закрывается на уровне close бара, подтвердившего т.4.
                    if($isAggressive && $curBar==$open_bar && !$check2){ // выходим на close бара открытия (не выполнилось условие2 при агрессивной тактике
                        $TTLs[$setup['tag']][$G1]['TRADE_CNT']=($TTLs[$setup['tag']][$G1]['TRADE_CNT'] ?? 0)+1;
                        $TTLs[$setup['tag']]['ALL_G1']['TRADE_CNT']=($TTLs[$setup['tag']]['ALL_G1']['TRADE_CNT'] ?? 0)+1;

                        $SL_field_name="SL$SL_num"."_CNT"; // имя поля счетчика - в зависимости от номера SL (сколько было перестановок)
                        $TTLs[$setup['tag']][$G1][$SL_field_name]=($TTLs[$setup['tag']][$G1][$SL_field_name] ?? 0)+1;
                        $TTLs[$setup['tag']]['ALL_G1'][$SL_field_name]=($TTLs[$setup['tag']]['ALL_G1'][$SL_field_name] ?? 0)+1;

                        $PNL=round(($close-$open_level)/$pips,0); // фин.результат сделки без учета спреда в пипсах
                        if($PNL>0){
                            $TTLs[$setup['tag']][$G1]['PROFIT']=($TTLs[$setup['tag']][$G1]['PROFIT'] ?? 0)+$PNL;
                            $TTLs[$setup['tag']]['ALL_G1']['PROFIT']=($TTLs[$setup['tag']]['ALL_G1']['PROFIT'] ?? 0)+$PNL;
                            $TTLs[$setup['tag']][$G1]['PROFIT_proc']=($TTLs[$setup['tag']][$G1]['PROFIT_proc'] ?? 0)+$PNL/$size_time*100;
                            $TTLs[$setup['tag']]['ALL_G1']['PROFIT_proc']=($TTLs[$setup['tag']]['ALL_G1']['PROFIT_proc'] ?? 0)+$PNL/$size_time*100;
                        }
                        else{
                            $TTLs[$setup['tag']][$G1]['LOSS']=($TTLs[$setup['tag']][$G1]['LOSS'] ?? 0)-$PNL;
                            $TTLs[$setup['tag']]['ALL_G1']['LOSS']=($TTLs[$setup['tag']]['ALL_G1']['LOSS'] ?? 0)-$PNL;
                            $TTLs[$setup['tag']][$G1]['LOSS_proc']=($TTLs[$setup['tag']][$G1]['LOSS_proc'] ?? 0)-$PNL/$size_time*100;
                            $TTLs[$setup['tag']]['ALL_G1']['LOSS_proc']=($TTLs[$setup['tag']]['ALL_G1']['LOSS_proc'] ?? 0)-$PNL/$size_time*100;
                        }
                        // добавляем результаты в массив
                        $TTLs[$setup['tag']][$G1]['PNLs'][]=['model_id'=>intval($model['id']),'close_time'=>$Chart[$curBar+$baseBarNum]['dandt'],'pnl'=>$PNL,'close_lvl'=>$close,"sizeLevel"=>$size_level/$pips];
                        $TTLs[$setup['tag']]['ALL_G1']['PNLs'][]=['model_id'=>intval($model['id']),'close_time'=>$Chart[$curBar+$baseBarNum]['dandt'],'pnl'=>$PNL,'close_lvl'=>$close,"sizeLevel"=>$size_level/$pips];

                        // добавляем соотношения потенциальной прибыли и убытка на начало и конец трэйда
                        $tp_tmp=$aim1_level-$open_level;
                        $sl_tmp=$open_level-$initSL_level;
                        $TTLs[$setup['tag']][$G1]['TP_open']=($TTLs[$setup['tag']][$G1]['TP_open'] ?? 0)+$tp_tmp;
                        $TTLs[$setup['tag']][$G1]['SL_open']=($TTLs[$setup['tag']][$G1]['SL_open'] ?? 0)+$sl_tmp;
                        $TTLs[$setup['tag']]['ALL_G1']['TP_open']=($TTLs[$setup['tag']]['ALL_G1']['TP_open'] ?? 0)+$tp_tmp;
                        $TTLs[$setup['tag']]['ALL_G1']['SL_open']=($TTLs[$setup['tag']]['ALL_G1']['SL_open'] ?? 0)+$sl_tmp;

                        //$tp_tmp=$aim1_level-$t4_level;
                        $sl_tmp=$open_level-$SL_level;
                        $TTLs[$setup['tag']][$G1]['TP_close']=($TTLs[$setup['tag']][$G1]['TP_close'] ?? 0)+$tp_tmp;
                        $TTLs[$setup['tag']][$G1]['SL_close']=($TTLs[$setup['tag']][$G1]['SL_close'] ?? 0)+$sl_tmp;
                        $TTLs[$setup['tag']]['ALL_G1']['TP_close']=($TTLs[$setup['tag']]['ALL_G1']['TP_close'] ?? 0)+$tp_tmp;
                        $TTLs[$setup['tag']]['ALL_G1']['SL_close']=($TTLs[$setup['tag']]['ALL_G1']['SL_close'] ?? 0)+$sl_tmp;

                        $State=99;
                        break;
                    } // вышли на close бара открытия (не выполнилось условие2 при агрессивной тактике

                    // если дошли до сюда - значит торгуем как обычно (классическая тактика либо уловие2 сработало при агрессивной

                    // проверяем на срабатывание триггеров (альтернативных триггеров тут, насколько я понял, нет
                    if(($setup['Trigger1'] ?? false) && $SL_num==1) {
                        $trigger1_level=$lvl_am - $size_level * $setup['Trigger1'] / 100;

                        if($setup['Trailing1']=='t5')$trailing1_level=low($model['t5']+$baseBarNum,$v)-$pips;
                        else {
                            if($isAggressive)$trailing1_level=$t4_level - $size_level * $setup['Trailing1'] / 100;
                            else $trailing1_level=$lvl_am - $size_level * $setup['Trailing1'] / 100;
                        }
                        if($high>$trigger1_level && ($trailing1_level>$SL_level && $trigger1_level>$SL_level)) {
                            $wasEvents = true;
                            $State = 1;
                            $SL_num = 2;
                            $SL_level = $trailing1_level;
                            break;
                        }
                    }

                    // проверяем отработку триггера2
                        if(($setup['Trigger2'] ?? false) && $SL_num<=2) {
                            $trigger2_level=$lvl_am - $size_level * $setup['Trigger2'] / 100;

                            if($setup['Trailing2']=='t5')$trailing2_level=low($model['t5']+$baseBarNum,$v)-$pips;
                            else {
                                if($isAggressive)$trailing2_level=$t4_level - $size_level * $setup['Trailing2'] / 100;
                                else $trailing2_level=$lvl_am - $size_level * $setup['Trailing2'] / 100;
                            }
                            if($high>$trigger2_level && ($trailing2_level>$SL_level && $trigger2_level>$SL_level)) {
                                $wasEvents = true;
                                $State = 1;
                                $SL_num = 3;
                                $SL_level = $trailing2_level;
                                break;
                            }
                        }

                    //далее проверяем достижение Aim1

                    if($high>$aim1_level){ // выполнилось условие
                        // фиксируем срабатывание TP (Aim1)
                        $TTLs[$setup['tag']][$G1]['TRADE_CNT']=($TTLs[$setup['tag']][$G1]['TRADE_CNT'] ?? 0)+1;
                        $TTLs[$setup['tag']]['ALL_G1']['TRADE_CNT']=($TTLs[$setup['tag']]['ALL_G1']['TRADE_CNT'] ?? 0)+1;

                        $TTLs[$setup['tag']][$G1]['AIM_CNT']=($TTLs[$setup['tag']][$G1]['AIM_CNT'] ?? 0)+1;
                        $TTLs[$setup['tag']]['ALL_G1']['AIM_CNT']=($TTLs[$setup['tag']]['ALL_G1']['AIM_CNT'] ?? 0)+1;

                        $PNL=round(($aim1_level-$open_level)/$pips,0); // фин.результат сделки без учета спреда - ур.входа-TP
                        if($PNL>0){
                            $TTLs[$setup['tag']][$G1]['PROFIT']=($TTLs[$setup['tag']][$G1]['PROFIT'] ?? 0)+$PNL;
                            $TTLs[$setup['tag']]['ALL_G1']['PROFIT']=($TTLs[$setup['tag']]['ALL_G1']['PROFIT'] ?? 0)+$PNL;
                            $TTLs[$setup['tag']][$G1]['PROFIT_proc']=($TTLs[$setup['tag']][$G1]['PROFIT_proc'] ?? 0)+$PNL/$size_time*100;
                            $TTLs[$setup['tag']]['ALL_G1']['PROFIT_proc']=($TTLs[$setup['tag']]['ALL_G1']['PROFIT_proc'] ?? 0)+$PNL/$size_time*100;
                        }
                        else{
                            $TTLs[$setup['tag']][$G1]['LOSS']=($TTLs[$setup['tag']][$G1]['LOSS'] ?? 0)-$PNL;
                            $TTLs[$setup['tag']]['ALL_G1']['LOSS']=($TTLs[$setup['tag']]['ALL_G1']['LOSS'] ?? 0)-$PNL;
                            $TTLs[$setup['tag']][$G1]['LOSS_proc']=($TTLs[$setup['tag']][$G1]['LOSS_proc'] ?? 0)-$PNL/$size_time*100;
                            $TTLs[$setup['tag']]['ALL_G1']['LOSS_proc']=($TTLs[$setup['tag']]['ALL_G1']['LOSS_proc'] ?? 0)-$PNL/$size_time*100;
                        }
                        // добавляем результаты в массив
                        $TTLs[$setup['tag']][$G1]['PNLs'][]=['model_id'=>intval($model['id']),'close_time'=>$Chart[$curBar+$baseBarNum]['dandt'],'pnl'=>$PNL,'close_lvl'=>$aim1_level,"sizeLevel"=>$size_level/$pips];
                        $TTLs[$setup['tag']]['ALL_G1']['PNLs'][]=['model_id'=>intval($model['id']),'close_time'=>$Chart[$curBar+$baseBarNum]['dandt'],'pnl'=>$PNL,'close_lvl'=>$aim1_level,"sizeLevel"=>$size_level/$pips];

                        // добавляем соотношения потенциальной прибыли и убытка на начало и конец трэйда
                        $tp_tmp=$aim1_level-$open_level;
                        $sl_tmp=$open_level-$initSL_level;
                        $TTLs[$setup['tag']][$G1]['TP_open']=($TTLs[$setup['tag']][$G1]['TP_open'] ?? 0)+$tp_tmp;
                        $TTLs[$setup['tag']][$G1]['SL_open']=($TTLs[$setup['tag']][$G1]['SL_open'] ?? 0)+$sl_tmp;
                        $TTLs[$setup['tag']]['ALL_G1']['TP_open']=($TTLs[$setup['tag']]['ALL_G1']['TP_open'] ?? 0)+$tp_tmp;
                        $TTLs[$setup['tag']]['ALL_G1']['SL_open']=($TTLs[$setup['tag']]['ALL_G1']['SL_open'] ?? 0)+$sl_tmp;

                        //$tp_tmp=$aim1_level-$t4_level;
                        $sl_tmp=$open_level-$SL_level;
                        $TTLs[$setup['tag']][$G1]['TP_close']=($TTLs[$setup['tag']][$G1]['TP_close'] ?? 0)+$tp_tmp;
                        $TTLs[$setup['tag']][$G1]['SL_close']=($TTLs[$setup['tag']][$G1]['SL_close'] ?? 0)+$sl_tmp;
                        $TTLs[$setup['tag']]['ALL_G1']['TP_close']=($TTLs[$setup['tag']]['ALL_G1']['TP_close'] ?? 0)+$tp_tmp;
                        $TTLs[$setup['tag']]['ALL_G1']['SL_close']=($TTLs[$setup['tag']]['ALL_G1']['SL_close'] ?? 0)+$sl_tmp;

                        $State=99;
                        break;
                    }


            }

            if(!$wasEvents)$curBar++; // следующий бар если событий не было - иначе отрабатываем новый $State
        } //while ($State !== 99)

    } //[3] вариaнт торговли на пробой P6

    return; // ничего не возвращаем - просто дополнили накопительные глобальные массивы по всем подсетапам - $TTLs,
}

function valueParsing($_value){
    // служебная процедурка - превращает строку со значением параметра в массив значение, например, '10%, 15%, 2%' превретится в  [10,12,14,15]
    $tmp=str_replace("%","",$_value); // игнорируем "%"
    $tmp=str_replace(" ","",$tmp); // пробел также игнорируем
    if(substr_count($tmp,",")==2){//проверяем, что это обозначение диапазона (типа было '10%, 15%, 2%')
        $tmpArr=explode(",",$tmp); //
        $from=floatval($tmpArr[0]);
        $to=floatval($tmpArr[1]);
        $step=floatval($tmpArr[2]);
        $retArr=[$from];
        $cont=true;
        $curValue=$from;
        while($cont){
            $next=$curValue+$step;
            if($next>=$to) { // если очередное значение больше или равно конечному - то берем конечное и завершаем формирование списка
                $retArr[]=$to;
                $cont=false;
            }
            else{
                $retArr[]=$curValue=$next;
            }
        }
        return($retArr);
    }
    else{ // текстовое(числовое) значение параметра - возвращаем массив из одного элемента
        if(is_numeric($tmp)) return([floatval($tmp)]); // возвращаем массив из одного элемента
        else return([$tmp]); // возвращаем массив из одного элемента
    }
}

//function best_lists_proc($N, $K)
//{
//    return get_lists_proc("proc1", $N, $K);
//}
//function worst_lists_proc($N, $K)
//{
//    return get_lists_proc("proc2", $N, $K);
//}
//function listCompare($a, $b)
//{
//    $aims1 = $a[2] + $a[3] + $a[4] + $a[5];
//    $aims2 = $b[2] + $b[3] + $b[4] + $b[5];
//    $proc1 = $aims1 / ($aims1 + $a[0] + $a[1]);
//    $proc2 = $aims2 / ($aims2 + $b[0] + $b[1]);
//    if ($proc1 < $proc2) return (-1);
//    if ($proc1 > $proc2) return (1);
//    return (0);
//}
//function get_lists_proc($type, $N, $K)
//{
//    global $listsArr,$res;
//
//    $curAimArr = $listsArr;
//
//    usort($curAimArr, "listCompare"); // сортируем по возрастанию Proc
//    $worst_place = $first_place = [];  // массив с информаций по средневзвешенным по лучшим/худшим N листов [procent,list(N list_id),cnt,aim_0,aim_15,aim_30,aim_50,aim_80,aim_120
//
//    // получаем $first_place
//    $aim_0 = $aim_15 = $aim_30 = $aim_50 = $aim_80 = $aim_120 = 0;
//    $list = "";
//    for ($i = count($curAimArr) - 1; ($i >= (count($curAimArr) - $N)) && ($i >= 0); $i--) { // последние N записей с хвоста массива (т.к. отсортирован по возрастанию Proc)
//        $aim_0 += $curAimArr[$i][0];
//        $aim_15 += $curAimArr[$i][1];
//        $aim_30 += $curAimArr[$i][2];
//        $aim_50 += $curAimArr[$i][3];
//        $aim_80 += $curAimArr[$i][4];
//        $aim_120 += $curAimArr[$i][5];
//        $list .= (($i == (count($curAimArr) - 1)) ? "" : ",") . $curAimArr[$i][6];
//    }
//    $first_place["list"] = $list;
//    $first_place["cnt"] = $aim_0 + $aim_15 + $aim_30 + $aim_50 + $aim_80 + $aim_120;
//    if ($first_place["cnt"] > 0) $first_place["proc"] = round(($aim_30 + $aim_50 + $aim_80 + $aim_120) / $first_place["cnt"] * 100, 3);
//    else $first_place["proc"] = 0;
//    $first_place["aim_0"] = $aim_0;
//    $first_place["aim_15"] = $aim_15;
//    $first_place["aim_30"] = $aim_30;
//    $first_place["aim_50"] = $aim_50;
//    $first_place["aim_80"] = $aim_80;
//    $first_place["aim_120"] = $aim_120;
//
//    // получаем $worst_place
//    $aim_0 = $aim_15 = $aim_30 = $aim_50 = $aim_80 = $aim_120 = 0;
//    $list = "";
//    for ($i = 0; ($i < $N) && ($i < count($curAimArr)); $i++) {
//        $aim_0 += $curAimArr[$i][0];
//        $aim_15 += $curAimArr[$i][1];
//        $aim_30 += $curAimArr[$i][2];
//        $aim_50 += $curAimArr[$i][3];
//        $aim_80 += $curAimArr[$i][4];
//        $aim_120 += $curAimArr[$i][5];
//        $list .= ($i ? "," : "") . $curAimArr[$i][6];
//    }
//    $worst_place["list"] = $list;
//    $worst_place["cnt"] = $aim_0 + $aim_15 + $aim_30 + $aim_50 + $aim_80 + $aim_120;
//    if ($worst_place["cnt"] > 0) $worst_place["proc"] = round(($aim_30 + $aim_50 + $aim_80 + $aim_120) / $worst_place["cnt"] * 100, 3);
//    else $worst_place["proc"] = 0;
//    $worst_place["aim_0"] = $aim_0;
//    $worst_place["aim_15"] = $aim_15;
//    $worst_place["aim_30"] = $aim_30;
//    $worst_place["aim_50"] = $aim_50;
//    $worst_place["aim_80"] = $aim_80;
//    $worst_place["aim_120"] = $aim_120;
//
//    $proc1 = $first_place['proc'];
//    $proc2 = $worst_place['proc'];
//
//    if ($type == "proc1") return ($proc1);
//    if ($type == "proc2") return ($proc2);
//    return ("NA");
//}
/// ПОХОДУ, осталась с пред.версии - не испгользуется
//function getNextVariantOfSetup($setupNum){ // выдает новый вариант сетапа (для Глубинного исследования - перебирает все сочетания плавающих параметров
//    // если больше нет (второй вызов при обычном исследовании либо все комбинации перебрали при Глубинном, то вернется false)
//    // иначе структура с текущими параметрами, включая уникальный тэг очередного (либо единственного) варианта сетапа
//    global $setups;
//    static $last_setupNum=-1;
//    static $iteration=0;
//
//    //проверяем, что предыдущая выдача была последней и возвращаем false в этом случае
//    if($last_setupNum==$setupNum) { // запрос на сетап, который был в прошлый раз
//        return(false);
//    }
//    else{
//        $iteration=0;
//        $last_setupNum=$setupNum;
//    }
////    $isDeep=false; // определяем, что присутсвует "глубинное" тестирование
////    if(!$isDeep){ // нег "глубинного" и уже была выдача параметров при прошлом запросе
////
////    }
//
//    $tag="(".$setupNum.") ".$setups[$setupNum]['condition1'];
//    $params=[];
//    $params['tag']=$tag;
//    return($params);
//}

function getProbability_1_2($NN){
    if($NN<0 || $NN>1)return([-1,-1]); // -1, -1 означает, что нейронка не вызывалась, нет ответа
    return([$NN,1.-$NN]);
}

function calcPips_clone($Chart){
    $pips = 11111111111111; // определяем размер пипса для текущего инструмента тупо анализируя наш чарт
//    $tmp_pipsCalcCnt = 0;
    for ($i = 0; $i < count($Chart) - 1; $i++) {
//        $tmp_pipsCalcCnt++;
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
    $tmp=str_replace("0","","".($pips*1000000000)); // получаем последнюю цифру/цифры
    return round($pips/$tmp,6);
}
function writeProgress($status)
{   global $startTime,$tmp_CT;
    $ct=round(microtime(true)-$startTime,0)." sec.";
    $mgu=number_format(memory_get_usage()/1024/1024,0)." MB";
    $status = "[" . date("Y-m-d H:i:s") . "] $status (calculation time: $ct, memory usage: $mgu )";// /*".round($tmp_CT,3)."c.*/";
    $p_header = fopen(PROGRESS_FILENAME, 'w');
    fwrite($p_header, $status);
    fclose($p_header);
}
function not_v($v)
{ // из high->low и наоборот
    return (($v == 'low') ? 'high' : 'low');
}

// набор функций, возвращающие open, close, high и low бара с номером $i (J для модели, где точка 1=low либо с отр. знаком, если high (чтобы не делать 2 копии алгоритма)
function high($i, $v)
{
    global $Chart;
    return (($v == 'low') ? $Chart[$i]['h'] : $Chart[$i]['l'] * (-1));
}

function low($i, $v)
{
    global $Chart;
    return (($v == 'low') ? $Chart[$i]['l'] * 1 : $Chart[$i]['h'] * (-1));
}

function open($i, $v)
{
    global $Chart;
    return (($v == 'low') ? $Chart[$i]['o'] : $Chart[$i]['o'] * (-1));
}

function close($i, $v)
{
    global $Chart;
    return (($v == 'low') ? $Chart[$i]['c'] : $Chart[$i]['c'] * (-1));
}

function gdvCalcMemory($get_defined_vars){
    $out=[];
    $all=0;
    foreach($get_defined_vars as $pk=>$pv){
        if($pv && is_array($pv)){
            $tmp1=json_encode($pv);
            $tmp2=json_decode($tmp1,true);
            $mgu1=memory_get_usage(false);
            unset($tmp2);
            $mgu2=memory_get_usage(false);
            $out[$pk]=$mgu1-$mgu2;
            $all+=$out[$pk];
        }
        $out['_ALL_']=$all;
    }
    return($out);
}
function shortFileName($f){ // отрезает путь к файлу слева
    $pos=strrpos($f,"\\");
    if($pos===false)$pos=strrpos($f,"/");
    if($pos===false)$out=$f;
    else $out=substr($f,$pos+1);
    return($out);
}
function calcTime($src,$startTime=0){ // добавляем всемя выполнения по разным замерам ($src)
    // очередность вывода таймингов в MT4 задается в массиве $Timings
    global $res;
    if(!$startTime){ // стартовое время не задано -> нужно вернуть накопленное значение в виде строки
        if(!isset($res['info']['Timing'][$src]))return("'$src'-> ERROR_KEY "); // неверный ключ
        if(!isset($res['info']['Timing'][$src]['CNT']) || $res['info']['Timing'][$src]['CNT']==0)
            return($src."(0): 0 sec. ");
        if($res['info']['Timing'][$src]['CNT']==1)
            return($src.": ".round($res['info']['Timing'][$src]['calcTime'],3)." sec. ");
        return($src."(".$res['info']['Timing'][$src]['CNT']."): ".round($res['info']['Timing'][$src]['calcTime'],3)." sec. ");
    }
    $res['info']['Timing'][$src]['CNT']=($res['info']['Timing'][$src]['CNT'] ?? 0)+1; // первый элемент - счетчик обращений
    $res['info']['Timing'][$src]['calcTime']=($res['info']['Timing'][$src]['calcTime'] ?? 0.0)+(microtime(true)-$startTime); // второй элемент - время выполнения
}