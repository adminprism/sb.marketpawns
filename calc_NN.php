<?php
// функция для заполнения полей NN<n> и adjNN<n> таблицы matches - ответ нейронок по данной модели(и цели)
// tool - наименование инструмента (напр. EURUSD), если он указан, то считаем только по данному инструменту (все таймфремы)
// вызов без паратра tool - запрос по всем инструментам, либо, например вызывать .../tool=EURUSD
// (!!!) вызывать нужно после calc_matches - так как все параметры кластеров (tf1,tf2,X,Slvl,St,`Range`,Koef) считаются там при нахождении кластера
// 13.05.2022 - добавлена возможность не запрашивать ответы по нейронкам онлайн + добавлена нейронка №3 - использование параметров преконтроля
//  3 ключа запуска 1) mode=OUT (или если без параметра)- не вызывается НС, а формируются CSV файлы для расчета в Colab (по одному для каждой НС
//                  2) mode=FILL - обратный процесс - по выгруженным из Colab результатам (csv) загружаются ответы нейронок в matches и считаются scores
//                  3) mode-FETCH - старый режим (онлайн запросы)
//
ini_set('date.timezone', 'Europe/Moscow');
set_time_limit(0);
// ini_set('memory_limit', '2048M');
ini_set('memory_limit', '10240M');
define("WRITE_LOG", 3); // уровень логирования 0-нет 9 - максимальный
define("LOG_FILE", "calc_NN.log"); //

define("NN_NUM", 3); // сколько всего у нас нейронок используется (сейчас 3 поля в таблице сделано с запасом, потом, если нужно, надо просто поменять данную константу и добавить полей в таблицу matches
define("NN_URL", "http://176.58.61.128:3001/analize");

require_once 'login_log.php';
$connection_dop = new mysqli($dbhost, $dbuser, $dbpass, $dbname, 3306);
ob_start();
$aimFields = ['P6', 'P6"', "auxP6", "auxP6'"];
$err_cnt = 0;
$res = []; // возворащаемый результат json
$res['Error'] = 'Error_01';
$res['Errors'] = [];
$res['info']['fetchNN_cnt'] = 0; // счетчик числа обращений в нейронку (просто информационный)
$aimList = ["P6", 'P6"', "auxP6", "auxP6'"]; // перечень наименований целей

$res['info']['type'] = '_GET';
$PARAM = $_GET;
if (WRITE_LOG > 0) $f_header = fopen(LOG_FILE, 'w');
if (isset($_POST['tool'])) {
    $res['info']['type'] = '_POST';
    $PARAM = $_POST;
}

write_log(date("Y-m-d H:i:s") . " start" . PHP_EOL, 1);

// * Creating Scores table if it doesn't exists yet
$query = <<<END
CREATE TABLE IF NOT EXISTS `scores` (
  `model_id` int(11) NOT NULL,
  `aim_name` enum('P6','P6"','auxP6','auxP6''') COLLATE utf8_unicode_ci NOT NULL,
  `nn_number` int(11) NOT NULL,
  `m2_NN` float DEFAULT 0,
  `m1_quant` float DEFAULT 0,
  `m1_quant_E` float DEFAULT 0,
  `NNClstAll` float DEFAULT 0,
  `NNClstAllPl` float DEFAULT 0,
  `NNClstAllMax` float DEFAULT 0,
  `NNClstAllMin` float DEFAULT 0,
  `NNClst` float DEFAULT 0,
  `NNClstPl` float DEFAULT 0,
  `NNClstMax` float DEFAULT 0,
  `NNClstMin` float DEFAULT 0,
  `NNClst_E` float DEFAULT 0,
  `NNClstMax_E` float DEFAULT 0,
  `NNClstMin_E` float DEFAULT 0,
  `NNClstPl_E` float DEFAULT 0,
  `NNClstAll_r` float DEFAULT 0,
  `NNClstAllPl_r` float DEFAULT 0,
  `NNClst_r` float DEFAULT 0,
  `NNClstPl_r` float DEFAULT 0,
  `NNClst_E_r` float DEFAULT 0,
  `NNClstPl_E_r` float DEFAULT 0,
  `cor_NNClstAll` float DEFAULT 0,
  `cor_NNClstAllPl` float DEFAULT 0,
  `cor_NNClst` float DEFAULT 0,
  `cor_NNClstPl` float DEFAULT 0,
  `cor_NNClst_E` float DEFAULT 0,
  `cor_NNClstPl_E` float DEFAULT 0,
  `cor_NNClstAll_r` float DEFAULT 0,
  `cor_NNClstAllPl_r` float DEFAULT 0,
  `cor_NNClst_r` float DEFAULT 0,
  `cor_NNClstPl_r` float DEFAULT 0,
  `cor_NNClst_E_r` float DEFAULT 0,
  `cor_NNClstPl_E_r` float DEFAULT 0,  
  PRIMARY KEY `scores` (`model_id`,`aim_name`,`nn_number`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
END;
$result = queryMysql($query);

// * Creating nn_answers table if it doesn't exists yet
$query = <<<END
CREATE TABLE IF NOT EXISTS `nn_answers` (
`model_id` int(11) NOT NULL,
  `aim_name` varchar(20) NOT NULL,
  `nn_number` tinyint(4) NOT NULL,
  `answer` float DEFAULT NULL COMMENT 'probability1',
  KEY `nn_answers` (`model_id`,`aim_name`,`nn_number`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;
END;
$result = queryMysql($query);

// следующий фрагмент - скопирован из calc_matches на всякий случай - служебные массивы
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


$cur_id = 0;
$isALL = false;
if (isset($PARAM['tool']) && (isset($ourTools[$PARAM['tool']]) || $PARAM['tool'] == "ALL")) {
    if ($PARAM['tool'] == "ALL") {
        $isALL = true;
        $tool = "ALL";
    } else
        $tool = SanitizeString($PARAM['tool']);
} else {
    $res['Errors'][] = "Не задан параметр tool (инструмент) либо по заданному инструменту нет моделей в базе";
    die();
}


$mode = "OUT"; // по умолчанию выгружаем CSV для обработки в колабе
if (isset($PARAM['mode'])) {
    if (strtoupper($PARAM['mode']) == "FILL" || strtoupper($PARAM['mode']) == 'FETCH' || strtoupper($PARAM['mode']) == 'OUT') { // FILL = прочитать готовый файл, выгруженный ИЗ Колаба, FETCH - опрашивать нейронки онлайн (как было первоначально сделано)
        $mode = strtoupper($PARAM['mode']);
    } else {
        $res['Errors'][] = "Не опознанный параметр 'mode' (" . $PARAM['mode'] . ") ! Допустимые значения: OUT, FILL либо FETCH";
        die();
    }
}
$res['info']['mode'] = $mode;

$out_fileName = "Colab_CSV/NN_№_$tool.csv"; // шаблон для вывода CSV для дальнейшей обработки в Colab
$in_fileName = "Colab_CSV/from_NN_№_$tool.csv"; // шаблон для ввода результатов работы с НС (загружено из Colab)
$out_header = [];

if ($mode == "OUT") {
    $res['info']['out_filename'] = $out_fileName;
}
for ($NN_Number = 1; $NN_Number <= NN_NUM; $NN_Number++) $out_header[$NN_Number] = fopen(str_replace("№", "" . $NN_Number, $out_fileName), 'w');

if ($isALL) {
    $result = queryMysql("truncate table nn_answers;");
} else {
    $result = queryMysql("delete from nn_answers where model_id in (select distinct id from models where name_id in (" . $ourTools[$tool]['name_id_list'] . "));");
}
write_log("Start MGU=" . memory_get_usage() . PHP_EOL, 1);
$scores_names = [
    'NNClstAll', 'NNClstAllPl', 'NNClstAllMax', 'NNClstAllMin', 'NNClst', 'NNClstPl', 'NNClstMax', 'NNClstMin', 'NNClst_E', 'NNClstMax_E', 'NNClstMin_E', 'NNClstPl_E', 'NNClstAll_r', 'NNClstAllPl_r', 'NNClst_r', 'NNClstPl_r', 'NNClst_E_r', 'NNClstPl_E_r',
    'cor_NNClstAll', 'cor_NNClstAllPl', 'cor_NNClst', 'cor_NNClstPl', 'cor_NNClst_E', 'cor_NNClstPl_E', 'cor_NNClstAll_r', 'cor_NNClstAllPl_r', 'cor_NNClst_r', 'cor_NNClstPl_r', 'cor_NNClst_E_r', 'cor_NNClstPl_E_r'
];

$NN_answers = []; // ответы нейронок - ассоциативный массив по трем индексам - id + aim_name + NN_Num
$NN_answers_full = []; // полные списки по всем tool - для каждого своя ветка,


// если выбран режим FILL (прочитать готовые ответы из Colab, то сразу заполняем структуру $NN_answers и в дальнейшем при вызове  fetch_NN_for_cluster просто береми ответы из этой структуры
if ($mode == "FILL")
    for ($NN_Number = 1; $NN_Number <= NN_NUM; $NN_Number++) { // читаем NN_NUM файлов с ответами НС
        $fn = str_replace("№", "$NN_Number", $in_fileName);
        $fp = @fopen($fn, "r");
        $lineCnt = 0;
        if ($fp) {
            while (($buffer = fgets($fp, 4096)) !== false) {

                if ($lineCnt) {
                    $tmp_arr = explode(",", str_replace("\n", "", $buffer)); // Номер по порядку,model_id,aim_name,tool,NN_Number,predict
                    $tmp_model_id = $tmp_arr[1];
                    $tmp_aim_name = $tmp_arr[2];
                    $tmp_tool = $tmp_arr[3];
                    $tmp_NN_Number = $tmp_arr[4];
                    $tmp_predict = $tmp_arr[5];
                    $NN_answers_full[$tmp_tool][$tmp_model_id][$tmp_aim_name][$tmp_NN_Number] = floatval($tmp_predict); // занесли значение в массив
                    $result = queryMysql("insert into nn_answers (model_id,aim_name,nn_number,answer) values($tmp_model_id,'" . str_replace("'", "''", $tmp_aim_name) . "',$tmp_NN_Number,$tmp_predict);");
                }
                $lineCnt++;
            }
            if ($lineCnt < 1) {
                $res['Errors'][] = "Ошибка чтения файла $fn";
                die();
            }
            fclose($fp);
        } else {
            $res['Errors'][] = "Ошибка: fgets() - ошибка открытия файла: $fn";
            die();
        }
    }


$modelsFields = []; // в этом массиве сохраняем набор полей, которые есть в таблице models - их все передаем в нейронки
$result = queryMysql("select * from models limit 1", FALSE); // просто чтобы получить список всех полей
$tmp_model = $result->fetch_assoc();
foreach ($tmp_model as $pk => $pv) $modelsFields[] = $pk; // поместили в массив список всех полей в таблице models (потом по этому списку будет отправлять данные в НС)
$modelsFields[] = "NN_Number"; // кроме всех полей из таблицы models будем передавать номер нейронки - NN_Number

foreach ($ourTools as $cur_tool => $toolInfo) if ($isALL || $cur_tool == $tool) { // перебор всех инструментов (выполнится один раз, если задан конкретный)
    if ($mode == "FILL") $NN_answers = $NN_answers_full[$tool]; // берем ранее заполненную ветку по инструменту (посчитана в Colab)
    else  $NN_answers = [];
    //$Scores_sum=[]; // сумма оценок - также по индексам id + NN_Num + aim_name
    //$Scores_cnt=[]; // количество оценок - также по индексам id + NN_Num + aim_name  - для вычисления среднего значения

    write_log(PHP_EOL . date("Y-m-d H:i:s") . " ***** mode=$mode tool: $cur_tool  MGU=" . memory_get_usage() . PHP_EOL . PHP_EOL, 1);
    //

    // если выбран режим OUT (нужно просто подготовить датасеты для отправки в Colab)
    if ($mode == "OUT") {
        // запрашиваем ответы нейронок по ВСЕМ моделям текущего инструмента
        $query = "select * from models m where  name_id in (" . $ourTools[$cur_tool]['name_id_list'] . ");";
        $result = queryMysql($query);
        $Models = [];
        while ($model = $result->fetch_assoc()) {
            $Models[intval($model['id'])] = $model; //  запоминаем все модели по инструменту  в ассоцциативном массиве по индексу модели
        }
        $result->close();
        foreach ($Models as $id => $model)
            foreach ($aimFields as $pk => $aim_name) { // перебираем все 4 возможные цели (пока нейронки есть только для P6, по остальным вернется null)
                for ($NN_Number = 1; $NN_Number <= NN_NUM; $NN_Number++) { // перебор всех возможных нейронок
                    $NN_answer_null = fetch_NN_for_model($model, $model['id'], $aim_name, $NN_Number); // получаем ответ нейронок (в данном случаем пустой, так просто формируем датасеты) по модели 2 (переменная не используется)
                }
            }
        continue; // в режиме OUT просто создаем датасеты и выходим
    }

    //    // все модели по текущему инструменту, которые входят в кластеры в качестве Model 2
    //    $Models = []; // массив моделей по текущему инструменту (по индексу id)
    //    $query = "select * from models m  where  name_id in (" . $ourTools[$cur_tool]['name_id_list'] . ") and id in (select distinct model_id from matches);";
    //    $result = queryMysql($query);
    //    while ($model = $result->fetch_assoc()) {
    //        $Models[intval($model['id'])] = $model; //  запоминаем все модели по инструменту  в ассоцциативном массиве по индексу модели
    //    }
    //    $result->close();

    $result = queryMysql("delete from scores where model_id in (select distinct id from models where name_id in (" . $ourTools[$cur_tool]['name_id_list'] . "));"); // стерли все старые оценки по инструменту

    ////    Старый кусок - раньше запрашивали только ответы нейронок по моделям , которые входят в кластеры
    //    // проходим все кластеры по текущему инструменту и запрашиваем нейронку по обоим моделям из кластера (если еще не получили)
    //    $query = "select cl.*,m.* from matches cl left join models m on m.id=cl.link_id where type not like 'clstA%' and name_id in (" . $ourTools[$cur_tool]['name_id_list'] . ") and Koef>0 and m1_NN1 is null;";
    //    $result = queryMysql($query, FALSE);
    //    $cluster_and_models_cnt = 0;
    //    while ($cluster_and_model = $result->fetch_assoc()) {
    //        $model1_id = intval($cluster_and_model['link_id']); // id модели 1 (link_id)
    //        $model2_id = intval($cluster_and_model['model_id']); // id модели 2 (model_id)
    //        write_log("Опрашиваем НС по модели1 $model1_id" . PHP_EOL, 3);
    //        $cluster_and_models_cnt++;
    //        $NN_update_str = "";
    //        for ($NN_Number = 1; $NN_Number <= NN_NUM; $NN_Number++) { // вызываем процедуру запроса нейронки - по количеству нейронок
    //            $NN_answer = fetch_NN_for_cluster($cluster_and_model, $model1_id, $cluster_and_model['link_aim_name'], $NN_Number); // получаем ответ нейронок по модели 1
    //            $NN_answer_for_model2 = fetch_NN_for_cluster($Models[$model2_id], $model2_id, $cluster_and_model['aim_name'], $NN_Number); // получаем ответ нейронок по модели 2 (переменная не используется)
    //            //$NN_update_str .= ($NN_Number == 1 ? "" : ",") . "m1_NN$NN_Number=" . $NN_answer[0] . ",m2_NN$NN_Number=" . $NN_answer_for_model2[0];
    //        }
    //        $tmp_time_1 = microtime(true);
    //        $query = "update matches set $NN_update_str where model_id=$model2_id and type='" . $cluster_and_model['type'] . "' and aim_name='" . str_replace("'", "\'", $cluster_and_model['aim_name']) .
    //            "' and  link_id=" . $cluster_and_model['link_id'] . " and link_aim_name='" . str_replace("'", "\'", $cluster_and_model['link_aim_name']) . "';";
    //        if($mode!=="OUT")$result1 = $connection_dop->query($query); // заносим в matches ответы нейронки, только если режим FETCH или FILL
    //        $time__ = microtime(true) - $tmp_time_1;
    //        if (isset($sql_time)) $sql_time += $time__;
    //    }
    //    $result->close();



    // заполнили таблицу matches + по каждой модели(и цели) запомнили ответы нейронки -> получаем и сохраняем в массиве итоговые 6 параметров
    $finalParams = []; // индексы - 1)id модели  2) наименование цели 3) номер нейронки 4) наименование параметра


    for ($NN_Number = 1; $NN_Number <= NN_NUM; $NN_Number++) { // считаем отдельно по каждой нейронке

        // 1. 2.) NNClstAll и NNClstAllPl
        $query = "select cl.model_id,cl.aim_name,sum(m1_NN$NN_Number) sum_,max(m1_NN$NN_Number) max_,min(m1_NN$NN_Number) min_,sum(m1_NN$NN_Number*Koef) cor_sum,sum(1-m1_NN$NN_Number) sum_r,sum((1-m1_NN$NN_Number)*Koef) cor_sum_r,m2_NN$NN_Number,count(*) cnt_ from matches cl left join models m on m.id=cl.model_id " .
            "left join (select model_id,aim_name,answer m1_NN$NN_Number from nn_answers where nn_number=$NN_Number) a1 on a1.model_id=cl.link_id and a1.aim_name=cl.link_aim_name " .
            "left join (select model_id,aim_name,answer m2_NN$NN_Number from nn_answers where nn_number=$NN_Number) a2 on a2.model_id=cl.model_id and a2.aim_name=cl.aim_name where type like 'clstII%' and name_id in (" . $ourTools[$cur_tool]['name_id_list'] .
            ") and m1_NN$NN_Number is not null group by cl.model_id,cl.aim_name,a2.m2_NN$NN_Number;";
        $res['info']['tmp_query1'] = $query;
        $result = queryMysql($query);
        while ($rec = $result->fetch_assoc()) {
            $model_id = intval($rec['model_id']);
            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['NNClstAll'] = $rec['sum_'] / $rec['cnt_'];
            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['NNClstAll_r'] = $rec['sum_r'] / $rec['cnt_'];
            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['NNClstAllPl'] = is_null($rec["m2_NN$NN_Number"]) ? 0 : (($rec['sum_'] + $rec["m2_NN$NN_Number"]) / ($rec['cnt_'] + 1));
            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['NNClstAllPl_r'] = is_null($rec["m2_NN$NN_Number"]) ? 0 : (($rec['sum_r'] + (1 - $rec["m2_NN$NN_Number"])) / ($rec['cnt_'] + 1));
            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['cor_NNClstAll'] = $rec['cor_sum'] / $rec['cnt_'];
            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['cor_NNClstAll_r'] = $rec['cor_sum_r'] / $rec['cnt_'];
            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['cor_NNClstAllPl'] = is_null($rec["m2_NN$NN_Number"]) ? 0 : (($rec['cor_sum'] + $rec["m2_NN$NN_Number"]) / ($rec['cnt_'] + 1));
            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['cor_NNClstAllPl_r'] = is_null($rec["m2_NN$NN_Number"]) ? 0 : (($rec['cor_sum_r'] + (1 - $rec["m2_NN$NN_Number"])) / ($rec['cnt_'] + 1));
            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['m1_quant'] = $rec['cnt_'];

            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['NNClstAllMax'] = $rec['max_'];
            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['NNClstAllMin'] = $rec['min_'];
        }
        $result->close();

        // 3. 4.) NNClst и NNClstPl (аналогично, но в условие добавляем "and tf1=tf2"
        $query = "select cl.model_id,cl.aim_name,sum(m1_NN$NN_Number) sum_,max(m1_NN$NN_Number) max_,min(m1_NN$NN_Number) min_,sum(m1_NN$NN_Number*Koef) cor_sum,sum(1-m1_NN$NN_Number) sum_r,sum((1-m1_NN$NN_Number)*Koef) cor_sum_r,m2_NN$NN_Number,count(*) cnt_ from matches cl left join models m on m.id=cl.model_id " .
            "left join (select model_id,aim_name,answer m1_NN$NN_Number from nn_answers where nn_number=$NN_Number) a1 on a1.model_id=cl.link_id and a1.aim_name=cl.link_aim_name " .
            "left join (select model_id,aim_name,answer m2_NN$NN_Number from nn_answers where nn_number=$NN_Number) a2 on a2.model_id=cl.model_id and a2.aim_name=cl.aim_name where type like 'clstII%' and name_id in (" . $ourTools[$cur_tool]['name_id_list'] .
            ") and m1_NN$NN_Number is not null and tf1=tf2 group by cl.model_id,cl.aim_name,a2.m2_NN$NN_Number;";
        $res['info']['tmp_query2'] = $query;
        $result = queryMysql($query);
        while ($rec = $result->fetch_assoc()) {
            $model_id = intval($rec['model_id']);
            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['NNClst'] = $rec['sum_'] / $rec['cnt_'];
            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['NNClst_r'] = $rec['sum_r'] / $rec['cnt_'];
            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['NNClstPl'] = is_null($rec["m2_NN$NN_Number"]) ? 0 : (($rec['sum_'] + $rec["m2_NN$NN_Number"]) / ($rec['cnt_'] + 1));
            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['NNClstPl_r'] = is_null($rec["m2_NN$NN_Number"]) ? 0 : (($rec['sum_r'] + (1 - $rec["m2_NN$NN_Number"])) / ($rec['cnt_'] + 1));
            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['cor_NNClst'] = $rec['cor_sum'] / $rec['cnt_'];
            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['cor_NNClst_r'] = $rec['cor_sum_r'] / $rec['cnt_'];
            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['cor_NNClstPl'] = is_null($rec["m2_NN$NN_Number"]) ? 0 : (($rec['cor_sum'] + $rec["m2_NN$NN_Number"]) / ($rec['cnt_'] + 1));
            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['cor_NNClstPl_r'] = is_null($rec["m2_NN$NN_Number"]) ? 0 : (($rec['cor_sum_r'] + (1 - $rec["m2_NN$NN_Number"])) / ($rec['cnt_'] + 1));

            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['NNClstMax'] = $rec['max_'];
            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['NNClstMin'] = $rec['min_'];
        }
        $result->close();

        // 5. 6.) NNClst_E  и NNClstPl_E (также, аналогично, но условие  "and tf1>tf2"
        $query = "select cl.model_id,cl.aim_name,sum(m1_NN$NN_Number) sum_,max(m1_NN$NN_Number) max_,min(m1_NN$NN_Number) min_,sum(m1_NN$NN_Number*Koef) cor_sum,sum(1-m1_NN$NN_Number) sum_r,sum((1-m1_NN$NN_Number)*Koef) cor_sum_r,m2_NN$NN_Number,count(*) cnt_ from matches cl left join models m on m.id=cl.model_id " .
            "left join (select model_id,aim_name,answer m1_NN$NN_Number from nn_answers where nn_number=$NN_Number) a1 on a1.model_id=cl.link_id and a1.aim_name=cl.link_aim_name " .
            "left join (select model_id,aim_name,answer m2_NN$NN_Number from nn_answers where nn_number=$NN_Number) a2 on a2.model_id=cl.model_id and a2.aim_name=cl.aim_name where type like 'clstII%' and name_id in (" . $ourTools[$cur_tool]['name_id_list'] .
            ") and m1_NN$NN_Number is not null and tf1>tf2 group by cl.model_id,cl.aim_name,a2.m2_NN$NN_Number;";
        $res['info']['tmp_query3'] = $query;
        $result = queryMysql($query);
        while ($rec = $result->fetch_assoc()) {
            $model_id = intval($rec['model_id']);
            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['NNClst_E'] = $rec['sum_'] / $rec['cnt_'];
            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['NNClst_E_r'] = $rec['sum_r'] / $rec['cnt_'];
            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['NNClstPl_E'] = is_null($rec["m2_NN$NN_Number"]) ? 0 : (($rec['sum_'] + $rec["m2_NN$NN_Number"]) / ($rec['cnt_'] + 1));
            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['NNClstPl_E_r'] = is_null($rec["m2_NN$NN_Number"]) ? 0 : (($rec['sum_r'] + (1 - $rec["m2_NN$NN_Number"])) / ($rec['cnt_'] + 1));
            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['cor_NNClst_E'] = $rec['cor_sum'] / $rec['cnt_'];
            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['cor_NNClst_E_r'] = $rec['cor_sum_r'] / $rec['cnt_'];
            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['cor_NNClstPl_E'] = is_null($rec["m2_NN$NN_Number"]) ? 0 : (($rec['cor_sum'] + $rec["m2_NN$NN_Number"]) / ($rec['cnt_'] + 1));
            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['cor_NNClstPl_E_r'] = is_null($rec["m2_NN$NN_Number"]) ? 0 : (($rec['cor_sum_r'] + (1 - $rec["m2_NN$NN_Number"])) / ($rec['cnt_'] + 1));
            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['m1_quant_E'] = $rec['cnt_'];

            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['NNClstMax_E'] = $rec['max_'];
            $finalParams[$model_id][$rec['aim_name']][$NN_Number]['NNClstMin_E'] = $rec['min_'];
        }
        $result->close();
    }

    // сорхраняем массив $finalParams в таблице "scores" БД
    foreach ($finalParams as $model_id => $model_scores) // по всем моделям 2
        foreach ($model_scores as $aim_name => $aim_name_scores) // по всем целям модели 2
            foreach ($aim_name_scores as $NN_Number => $NN_scores) { // по всем нейронкам (2 штуки пока)
                $insert_names_str = ""; // строка для insert с перечислением полей
                $insert_values_str = ""; // строка для insert с перечислением значений полей
                foreach ($scores_names as $scores_name) { // перебор всех шести имен параметров
                    $insert_names_str .= "," . $scores_name;
                    $insert_values_str .= "," . ($NN_scores[$scores_name] ?? 0);
                }
                $aim_name_ = str_replace("'", "''", $aim_name);
                $query = "insert into scores (model_id,aim_name,nn_number,m1_quant,m1_quant_E,m2_NN$insert_names_str) VALUES($model_id,'$aim_name_',$NN_Number," .
                    ($finalParams[$model_id][$aim_name][$NN_Number]['m1_quant'] ?? 0) . "," . ($finalParams[$model_id][$aim_name][$NN_Number]['m1_quant_E'] ?? 0) . "," .
                    ($NN_answers[$model_id][$aim_name][$NN_Number] ?? "null") . "$insert_values_str);";
                $result = queryMysql($query);
            }
} // перебор всех инструментов (либо 1 раз, если задан конкретный
for ($NN_Number = 1; $NN_Number <= NN_NUM; $NN_Number++) fclose($out_header[$NN_Number]);
write_log(PHP_EOL . date("Y-m-d H:i:s") . " end" . PHP_EOL, 1);
unset($res['Error']);
die();

function fetch_NN_for_model($model, $id, $aim_name, $NN_Number)
{ // в JSON добавлем параметр NN_Number - номер нейронки, которую опрашиваем (в дальнейшем, предполагается, что для разных нейронок будет свой вабор параметров в JSON
    global $res, $NN_answers, $mode, $out_header, $tool, $modelsFields;
    static $isFirstCall = []; // флаг, что это первый вызов и нужно записать первую строку - имена полей (после записи зща
    static $nFields = 0;
    static $last_data = 0;
    /////////////////////////////////////////
    if ($model['id'] !== $id) {
        $res['Errors'][] = "ERROR - DEBUG fetch_NN";
        $res['_____model'] = $model;
        $res['_____id'] = $id;
    }

    $id = $model['id'];

    // 15.05.2022 - добавили проверку, что для данной модели-цели нужно запрашивать нейнонку № $NN_Number
    if ($model["G1"] !== "EAM" || $aim_name != 'P6') return (['null', 'null']);
    if ($NN_Number == 3 && (is_null($model['_ll25apprP6']) || is_null($model['_llapprP6']) || is_null($model['_ll4apprP6']) || is_null($model['_lvlapprP6']))) return (['null', 'null']);
    //////////////////////////////////////////////////////////////////////////////////////////////////////


    $Koef = $model['Koef'] ?? 1; // получаем результирующий коэффициент (если не определен (такое будет при запросе НС не по Модели1 а по Модели2), то берем 1

    if (isset($NN_answers[$id][$aim_name][$NN_Number])) { // стандартная ситуация - если посчитано в Colab, то предполагается, что инфа в массиве уже есть по всем моделям
        //$res['TMP_____'] = $NN_answers;
        return ([$NN_answers[$id][$aim_name][$NN_Number], $NN_answers[$id][$aim_name][$NN_Number] * $Koef]); // уже был посчитан, просто возвращаем
    }

    $data = $model; // в новой версии копируем все поля - формирование DataFrame теперь выполняется в Python, также, по новой концепции - не выполняем никакой предобработки - все в Python
    $data['NN_Number'] = $NN_Number;
    $data['id'] = $id;

    //$data_string = str_replace('"NA"', '0', json_encode($data)); // for the telegram messages
    $data_string = json_encode($data); // for the telegram messages
    $res['info']['last_NN_Request'] = $data_string;

    try {
        $ch = curl_init();

        // Check if initialization had gone wrong*
        if ($ch === false) {
            throw new Exception('failed to initialize');
        }

        // Better to explicitly set URL
        curl_setopt($ch, CURLOPT_URL, NN_URL);
        // That needs to be set; content will spill to STDOUT otherwise
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // Set more options
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string)
            )
        );
        $tmp_t = microtime(true);

        // ВНИМАНИЕ!!! Это заглушка для тестирования - эмуляуция ответа нейронки - просто случайное число от 0 до 1
        // для реального опроса нейронки - просто нужно РАСКОММЕНТИРОВАТЬ строку $content = curl_exec($ch);
        $rand = rand(1, 1000000) / 1000000;
        $content = "[[$rand " . (1 - $rand) . "]]";
        // реальнрый запрос на сервер, только если нужно
        if ($mode == "FETCH") {
            $content = curl_exec($ch);
            $res['info']['fetchNN_cnt']++;
        }
        ///////////////////////////////////////////////////////////////////////////////////////////////
        if ($mode == "OUT") {
            if ($isFirstCall[$NN_Number] ?? True) { // если это первая строка по нейронке с номером $NN_Number
                $isFirstCall[$NN_Number] = False;
                $firstLine = "model_id,aim_name,tool";
                foreach ($data as $pk => $pv) if (in_array($pk, $modelsFields)) $firstLine .= ',' . $pk;
                fwrite($out_header[$NN_Number], $firstLine . PHP_EOL);
                $nFields = count($data);
            }
            $outLine = "$id,$aim_name,$tool";
            foreach ($data as $pk => $pv) if (in_array($pk, $modelsFields)) $outLine .= ',' . $pv;
            fwrite($out_header[$NN_Number], $outLine . PHP_EOL);
            $last_data = $data;
        }

        // Check the return value of curl_exec(), too
        if ($content === false) {
            $res['Errors'][] = "NN answer Error - content is null";
            die();
            //throw new Exception(curl_error($ch), curl_errno($ch));
        }

        // Check HTTP return code, too; might be something else than 200
        $httpReturnCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        /* Process $content here */
    } catch (Exception $e) {
        $res['Errors'][] = "NN answer Error - Exception";
        die();
    } finally {
        // Close curl handle unless it failed to initialize
        //if (is_resource($ch)) {
        curl_close($ch);
        if (substr($content, 0, 2) == "[[") {
            $NN_answers_tmp = explode(" ", $content);
            $NN_answers_ = [substr($NN_answers_tmp[0], 2) * 1.0, substr($NN_answers_tmp[1], 0, strlen($NN_answers_tmp[1]) - 2) * 1.0];
        } else {
            //$NN_answers_ = [0, 0];
            $res['Errors'][] = "NN answer Error :" . $content;
            die();
        }
        $NN_answers[$id][$aim_name][$NN_Number] = $NN_answers_[0]; // запоминаем ответ нейронки в глобальном массиве
        return ([$NN_answers_[0], $NN_answers_[0] * $Koef]);
        //}
    }
}
