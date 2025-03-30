<?php
// 2023-01-13 Загрузки пятиминуток (из MT4 или MT5) из заданного каталога + генерация по ним старших таймфремов и расчет моделей
// ВНИМАНИЕ! Программа перебирает все пятиминутки из каталога. При нахождении очередной пары, проискходит удаление из БД ВСЕЙ инфы по данной паре и загрузка новой
// если нужно обнулить всю БД, то truncate можно сделать вручную - это несколько ускорит работу + обнулит стартовык ID в таблицах
//
/*
-- скрипт для очистки БД (закомментированы truncate таблиц, которые больше не используются (статистика, токены, gold/shit фильтры)
-- truncate table avg_proc_all;
truncate table chart_names;
truncate table charts;
truncate table controls;
-- truncate table gold;
truncate table idinners_map;
truncate table idprevs_map;
-- truncate table list_rec_map;
-- truncate table lists;
truncate table matches;
truncate table models;
truncate table nn_answers;
truncate table pnl;
truncate table scores;
-- truncate table shit;
-- truncate table signals;
truncate table size_and_levels;
truncate table status_map;
truncate table statuses;
-- truncate table token_strings;
-- truncate table tokens;
*/

ini_set('date.timezone', 'Europe/Moscow');
ini_set('memory_limit', '2048M');
set_time_limit(0);
DEFINE("WRITE_LOG", 0); //
DEFINE("BARS_PER_STEP", 5000); // сколько баров за раз читаем и рассчитываем
DEFINE("SHIFT_FOR_NEXT", 4500); // на сколько баров двигаемся (нахлест)
DEFINE("MIN_CHART_LENGTH", 200); // минимально допустимое количество баров в чарте
DEFINE("DIR_NAME","big_saves"); // каталог (начиная от текущего без "/")

$tfList=[5,15,30,60,240,1440];

require_once "login_log.php";

$pointList = [ // массив-списов всех точек, которые могут быть у моделей (по ним ставим относительное позиционирование по базовой точке)
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

// определение глобальных переменных
$arrId=[]; // массив id баров на чарте
$arModels1_Ids = []; // ассоциативный массив, куда запоминаем id модели первого алгоритма по ключу типа "p962-1"
$statuses=[]; // асс.массив - id статуса по наименованию

$res['Errors']=[];
$res['Error']="";
$res['info']['start time']=date("Y-m-d H:i:s");

$res['info']['dir with charts']=$dir_=$curDir."/".DIR_NAME;

$url = ((!empty($_SERVER['HTTPS'])) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$url = explode('?', $url);
$url = $url[0];
$pos=strrpos($url,"/");
$url_path=$res['info']['url']=substr($url,0,$pos+1);

// читаем в ассоциативный массив статусы и из id
$statuses=[]; // тут хранимм id статусов (ключи - сами статусы)
$result=queryMysql("select status,id from statuses;");
if($result->num_rows>0){
    $result->data_seek(0);
    while($rec=$result->fetch_assoc())$statuses[$rec['status']]=$rec['id'];
}
$result->close();

$tables=[];
$result=queryMysql("show table status");
while($rec=$result->fetch_assoc())$tables[$rec['Name']]=$rec;
$result->close();
//$res['debug']['tables']=$tables;

$files = listdir($dir_);
sort($files, SORT_LOCALE_STRING);

$files_cnt = 0;
$bar_total_cnt = 0;
$file_cnt = 0;
foreach ($files as $f) {
    $file_cnt++;
    $startTime1 = microtime(true);
    loadAndCalc($f);
    //$path = substr($f,0,strlen($f) - strlen($filename));
   // echo "path: ($path) filename: ($filename) <br>";
    //echo  $f."  ".filesize($f). "   ".$filename. "<br>";
    $files_cnt++;
}
$res['result']['file_cnt']=$files_cnt;

//echo $filePath."<br>";
die_();

function loadAndCalc($fileName_full) {
// читает чарт из указанного файла
// определяем название пары и ТФ, стираем из БД всю информацию по этим инструментам (пара+ТФ)
// формирует чарты всех ТФ (текущий + старшие) по списку
// вызывает поочередно Алгоритм 1 и 2 и записывает в БД информацию по моделям
    global $tfList,$res,$connection,$arrId,$tables;
    $st=microtime(true);
    $fileName=shortFileName($fileName_full);
    $res['result'][$fileName_full]['fileName']=$fileName;
    $instrumentName = strtoupper($fileName);
    if(!(($pos = strrpos(strtoupper($fileName),".CSV")) === false))$instrumentName = substr($instrumentName,0,$pos);

    // определяем имя пары - таймфрейм узнаем из содержимого
    $instrumentName = str_replace("_I_M","",$instrumentName);
    $instrumentName = str_replace("_I_H","",$instrumentName);
    $instrumentName = str_replace("_I_D","",$instrumentName);
    $instrumentName = str_replace("_M","",$instrumentName);
    $instrumentName = str_replace("_H","",$instrumentName);
    $instrumentName = str_replace("_D","",$instrumentName);
    $pair = preg_replace('/[0-9,_]/', '', $instrumentName);

    if(strlen($pair)<6 || strlen($pair)>8){
        $res['Errors'][]="Некорректное имя файла $fileName ";
        $res['result'][$fileName_full]['pair']="N/A (Error)";
        return;
    }
    $res['result'][$fileName_full]['pair']=$pair;
    $tmp=getTFandChart($fileName_full);
    if($tmp===false){
        $res['result'][$fileName_full]['timeFrame']="N/A (Error)";
        return;
    }
    $tf=$res['result'][$fileName_full]['timeFrame']=$tmp['tf'];
    $res['result'][$fileName_full]['lines in file']=$tmp['cnt'];
    if($tmp['cnt']<MIN_CHART_LENGTH){
        $res['result'][$fileName_full]['timeFrame']="N/A (Error)";
        $res['Errors'][]="Мало баров в файле $fileName (".$tmp['cnt'].") должно быть не меньше ".MIN_CHART_LENGTH;
        return;
    }

    $name_idByTF=[]; // вспомогательные массив - определение name_id по таймфрему для данной пары
    $name_id_list="";
    $tf_list="";
    foreach($tfList as $pk=>$pv){
        if($pv<($tf/60))continue; // таймфрейм младше прочитанного - игнорируем
        if(($pv % ($tf/60))!=0){
            $res['Errors'][]="Таймфрейм $pv не кратен таймфрейму в файле $fileName (".($tf/60).");";
            return;
        }
        $tf_=$pv*60;
        if($pv>=5)
            $name_idByTF[$pv]=$name_id=updateChart_Names($pair,$tf_,$fileName); // добавляет или обнавляет запись в табл. chart_names
            $name_id_list.=($name_id_list?",":"").$name_id;
            $tf_list.=($tf_list?",":"").$pv;
    }
    $res['result'][$fileName_full]['name_id']=$name_idByTF[$tf/60] ?? "NA($tf)";
    $res['result'][$fileName_full]['name_id list']=$name_id_list;
    $res['result'][$fileName_full]['tf list']=$tf_list;
    $res['result'][$fileName_full]['first_bar time']=$tmp['first_bar'];
    $res['result'][$fileName_full]['last_bar time']=$tmp['last_bar'];
    $res['result'][$fileName_full]['mgu']=$tmp['mgu'];

    //формируем чарты для всех таймфремов
    $Charts=makeCharts($tf,$tmp['chart']); // по полученному чарту (младшего таймфрема) формируем все старшие
    //$res['result'][$fileName_full]['Charts']=$Charts;
    foreach($Charts as $pk=>$pv)$res['result'][$fileName_full]['number of bars'][$pk]=count($pv);
    // чистка БД по текущей паре

    queryMysql("CREATE TABLE IF NOT EXISTS `temp` (`id` int(11) NOT NULL, PRIMARY KEY (`id`)) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");
    queryMysql("truncate table temp");
    queryMysql("insert into temp select distinct id from models where name_id in ($name_id_list);");

    // список таблиц, в которых нужно почистить записи, относящиеся к моделям по текущей паре (все name_id)
    $tmp_tablesToClear=['controls','idinners_map','idprevs_map','matches','nn_answers','pnl','scores','size_and_levels','status_map'];
    foreach($tables as $table_name=>$_tmp){
        if(in_array($table_name, $tmp_tablesToClear))
            queryMysql("delete from $table_name where model_id in (select id from temp);");
    }
    queryMysql("delete from models where name_id in ($name_id_list);");
    queryMysql("delete from charts where name_id in ($name_id_list);");

    file_put_contents("tmp_log/___load_progress.json",json_encode($res));

    foreach($Charts as $pk=>$pv){
        $name_id_=$name_idByTF[$pk];
        $savedId=[];
        // запись чарта в БД
        foreach($pv as $ind=>$bar){
            $dandt_=date("Y-m-d H:i:s",$bar['open_time']);
            $o_=$bar['open'];
            $h_=$bar['high'];
            $l_=$bar['low'];
            $c_=$bar['close'];
            $v_=$bar['volume'];
            queryMysql("insert into charts (dandt,o,h,l,c,v,name_id) VALUES ('$dandt_',$o_,$h_,$l_,$c_,$v_,$name_id_);");
            $savedId[]=$connection->insert_id; // запоминаем получившиеся id в charts
        }
        // готовим кусок чарта (идем с нахлестом для рассчета моделей
        for($i=0;$i<count($pv);$i+=SHIFT_FOR_NEXT){
            $startFrom=$i;
            if($startFrom>(count($pv)-(BARS_PER_STEP-SHIFT_FOR_NEXT)-5))$startFrom=count($pv)-(BARS_PER_STEP-SHIFT_FOR_NEXT)-5;
            if($startFrom<0)$startFrom=0; // но такого быть не может, так как ранее размер чарта проверяли
            $arrId=[]; // обнуляем глобальный массив - id бара из БД по номеру бара в "куске" чарта для расчета
            $chartFragment=[];
            for($j=$startFrom;$j<count($pv)-1 && ($j-$startFrom)<BARS_PER_STEP;$j++){
                $arrId[]=$savedId[$j];
                $chartFragment[]=$pv[$j];
            }
            calcModels($chartFragment,$pk,$name_idByTF[$pk],$fileName_full);
        }

    }



    $res['result'][$fileName_full]['calcTime']=round(microtime(true)-$st,6);

    // получаем таймфрейм

    return;
}
function makeCharts($tf_orig_sec,$Chart_orig){
    global $tfList,$res;
    $Charts=[];
//$Chart[]=["open_time"=>$ot,"open"=>$open,"high"=>$high,"low"=>$low,"close"=>$close,"volume"=>$vol,"close_time"=>($ot+$interval_sec)];
    foreach($tfList as $pk=>$pv){
        if($pv<($tf_orig_sec/60))continue; // младшие посчитать не можем
        if($pv==($tf_orig_sec/60))$Charts[$pv]=$Chart_orig; // просто копируем, если таймфрем равен минимальному (прочитанному из файла)
        else{ // формируем старший таймфрейм
            $chart_tmp=[];
            $begin_=beginOfBar(date("Y-m-d H:i:s",$Chart_orig[0]['open_time']),$pv); //начало первого бара
            $begin_uts=(new DateTime($begin_))->getTimestamp();
            $barTmp=["open_time"=>$begin_uts,"open"=>0,"high"=>0,"low"=>0,"close"=>0,"volume"=>0,"close_time"=>($begin_uts+$tf_orig_sec)];
            for($i=0;$i<count($Chart_orig);$i++){ // перебор всех баров оригинального чарта
                $begin_=beginOfBar(date("Y-m-d H:i:s",$Chart_orig[$i]['open_time']),$pv); //начало бара старшего ТФ
                $begin_uts=(new DateTime($begin_))->getTimestamp();
                if($begin_uts>=$barTmp['close_time']){ // появился новый бар
                    if($barTmp['open']>0)$chart_tmp[]=$barTmp; // если текущий не пустой, то добавляем его в чарт
                    $barTmp=["open_time"=>$begin_uts,"open"=>0,"high"=>0,"low"=>0,"close"=>0,"volume"=>0,"close_time"=>($begin_uts+$tf_orig_sec)];
                }
                 // добавляем инфу из очередного бара в бар старшего ТФ
                if($barTmp['open']==0)$barTmp['open'] = $Chart_orig[$i]['open']; // если это первый бар, то проставляем цену открытия
                $barTmp['volume'] = $barTmp['volume']+$Chart_orig[$i]['volume'];
                if($barTmp['high']==0 || $barTmp['high']<$Chart_orig[$i]['high'])$barTmp['high'] = $Chart_orig[$i]['high'];
                if($barTmp['low']==0 || $barTmp['low']>$Chart_orig[$i]['low'])$barTmp['low'] = $Chart_orig[$i]['low'];
                $barTmp['close'] = $Chart_orig[$i]['close'];
            }
            if($barTmp['open']>0)$chart_tmp[]=$barTmp;

            if(count($chart_tmp)>=MIN_CHART_LENGTH)$Charts[$pv]=$chart_tmp; // если баров достаточно, то добавляем в массив чартов
        }
    }
    return($Charts);
}
function getTFandChart($fileName_full){ // читает файл, определяет тайм-фрейм и формирует чарт
    // если это минутка, то формируем сразу 5-минутный чарт (для исключения переполнения памяти при большом числе минутных баров
    global $res,$tfList;
    $res['debug']['___mgu_start']=number_format(memory_get_usage());
    $st=microtime(true);
    $txt=file_get_contents($fileName_full);
    $arTxt = explode("\n", $txt);
    unset($txt);
    $tmp1=microtime(true)-$st;

    $fileFormat="MT4";
    if(substr($arTxt[0],0,6)=="<DATE>") {
        $fileFormat = "MT5";
        array_shift($arTxt);
    }
    $cnt=count($arTxt);
    $Chart=[];

// прокручиваем несколько баров с начала - просто определяем интервал между барами
    $interval_sec=1000000000;
    $tmpCnt=[];
    if($fileFormat=="MT4")
        for ($i = 0; $i < $cnt-10; $i++) { // 20220311 исправлено на прокрутку ВСЕГО массива, так как было обнаружено что в некоторых файлах (часовики, 4часовики) первные сотня записей с шагом 1 сутки (почему то)
            $arRec = explode(",", $arTxt[$i]);
            $arRec_next = explode(",", $arTxt[$i+1]);
            $rec_dandt = $arRec[0] . $arRec[1];
            $rec_dandt_next = $arRec_next[0] . $arRec_next[1];
            $rec_dandt_str = substr($rec_dandt, 0, 4) . '-' . substr($rec_dandt, 5, 2) . '-' . substr($rec_dandt, 8, 2);
            $rec_dandt_str .= ' ' . substr($rec_dandt, 10, 2) . ':' . substr($rec_dandt, 13, 2) . ':00';
            $rec_dandt_str_next = substr($rec_dandt_next, 0, 4) . '-' . substr($rec_dandt_next, 5, 2) . '-' . substr($rec_dandt_next, 8, 2);
            $rec_dandt_str_next .= ' ' . substr($rec_dandt_next, 10, 2) . ':' . substr($rec_dandt_next, 13, 2) . ':00';
            $interval_=date_format(date_create($rec_dandt_str_next), 'U')-date_format(date_create($rec_dandt_str), 'U');
            if($interval_<=$interval_sec) {
                $interval_sec = $interval_;
                $tmpCnt[$interval_sec]=($tmpCnt[$interval_sec] ?? 0)+1;
                if($tmpCnt[$interval_sec]>100 && (in_array($interval_sec/60,$tfList) || $interval_sec==60))break; // много подтверждений - выходим, определили
            }
        }
    else
        for ($i = 0; $i < $cnt - 10; $i++) { // 20220311 исправлено на прокрутку ВСЕГО массива, так как было обнаружено что в некоторых файлах (часовики, 4часовики) первные сотня записей с шагом 1 сутки (почему то)
            $arRec = explode("\t", $arTxt[$i]);
            $arRec_next = explode("\t", $arTxt[$i + 1]);
            $res['debug']['arRec']=$arRec;
            $rec_dandt = $arRec[0] . $arRec[1] . ".00";
            $rec_dandt_next = $arRec_next[0] . $arRec_next[1] . ".00";
            $rec_dandt_str = substr($rec_dandt, 0, 4) . '-' . substr($rec_dandt, 5, 2) . '-' . substr($rec_dandt, 8, 2);
            $rec_dandt_str .= ' ' . substr($rec_dandt, 10, 2) . ':' . substr($rec_dandt, 13, 2) . ':00';
            $rec_dandt_str_next = substr($rec_dandt_next, 0, 4) . '-' . substr($rec_dandt_next, 5, 2) . '-' . substr($rec_dandt_next, 8, 2);
            $rec_dandt_str_next .= ' ' . substr($rec_dandt_next, 10, 2) . ':' . substr($rec_dandt_next, 13, 2) . ':00';
            $interval_ = date_format(date_create($rec_dandt_str_next), 'U') - date_format(date_create($rec_dandt_str), 'U');
            if ($interval_ <= $interval_sec) {
                $interval_sec = $interval_;
                $tmpCnt[$interval_sec] = ($tmpCnt[$interval_sec] ?? 0) + 1;
                $tf = round($interval_sec / 60);
                if ($tmpCnt[$interval_sec] > 100 && (in_array($tf, $tfList) || $interval_sec == 60)) break; // много подтверждений - выходим, определили
            }
        }
        if($interval_sec>10000000)return(['tf'=>0,'chart'=>"ERROR - ТФ не определился"]); // TF не определился
        $tf=round($interval_sec/60); // TF в минутах
        if(!in_array($tf,$tfList) && $tf!=1)return(['tf'=>0,'chart'=>"ERROR - ТФ=$tf не входит в список допустимых"]);

        // формируем чарт из содержимого прочитанного файла
    $delimiter=$fileFormat=="MT4"?",":"\t";
    $first_bar_time="";
    $last_bar_time="";
    $res['debug']['_____fileFormat']="$fileFormat($delimiter)";

    if($interval_sec==60) { // если это минутка, то формируем сразу пятиминутку, минутку не сохраняем для экономии памяьт
        $Chart = [];
        $arRec = explode($delimiter, $arTxt[0]);
        $rec_dandt = $arRec[0] . $arRec[1];
        $rec_dandt_str = substr($rec_dandt, 0, 4) . '-' . substr($rec_dandt, 5, 2) . '-' . substr($rec_dandt, 8, 2);
        $bar_uts=(new DateTime($rec_dandt_str))->getTimestamp();
        $begin_ = beginOfBar(date("Y-m-d H:i:s", $bar_uts), 5); //начало первого бара
        $begin_uts = (new DateTime($begin_))->getTimestamp();
        $barTmp = ["open_time" => $begin_uts, "open" => 0, "high" => 0, "low" => 0, "close" => 0, "volume" => 0, "close_time" => ($begin_uts + $interval_sec)];
        for ($i = 0; $i < count($arTxt); $i++) { // перебор всех баров оригинального чарта
            if(strlen($arTxt[$i])<10)continue;
            $arRec = explode($delimiter, $arTxt[$i]);
            $rec_dandt = $arRec[0] . $arRec[1];
            $rec_dandt_str = substr($rec_dandt, 0, 4) . '-' . substr($rec_dandt, 5, 2) . '-' . substr($rec_dandt, 8, 2);
            $rec_dandt_str .= ' ' . substr($rec_dandt, 10, 2) . ':' . substr($rec_dandt, 13, 2) . ':00';
            $bar_uts=(new DateTime($rec_dandt_str))->getTimestamp();
            $open = $arRec[2];
            $high = $arRec[3];
            $low = $arRec[4];
            $close = $arRec[5];
            $vol = $fileFormat=="MT4"?intval($arRec[6]):intval($arRec[8]); // заменил на spread, volume все равно не использовался
            $begin_ = beginOfBar(date("Y-m-d H:i:s", $bar_uts), 5); //начало бара старшего ТФ
            $begin_uts = (new DateTime($begin_))->getTimestamp();
            if ($begin_uts >= $barTmp['close_time']) { // появился новый бар
                if ($barTmp['open'] > 0) $Chart[] = $barTmp; // если текущий не пустой, то добавляем его в чарт
                $barTmp = ["open_time" => $begin_uts, "open" => 0, "high" => 0, "low" => 0, "close" => 0, "volume" => 0, "close_time" => ($begin_uts + $interval_sec)];
            }
            // добавляем инфу из очередного бара в бар старшего ТФ
            if ($barTmp['open'] == 0) $barTmp['open'] = $open; // если это первый бар, то проставляем цену открытия
            $barTmp['volume'] = $barTmp['volume'] + $vol;
            if ($barTmp['high'] == 0 || $barTmp['high'] < $high) $barTmp['high'] = $high;
            if ($barTmp['low'] == 0 || $barTmp['low'] > $low) $barTmp['low'] = $low;
            $barTmp['close'] = $close;
        }
        if ($barTmp['open'] > 0) $Chart[] = $barTmp;
        $ret_interval=300; // 5 минут
    }
    else {
        for ($i = 0; $i < count($arTxt); $i++) {
            if (strlen($arTxt[$i]) < 20) continue; // очень короткая
            $res['info']['lastLine'] = $arTxt[$i];
            $arRec = explode($delimiter, $arTxt[$i]);
            $rec_dandt = $arRec[0] . $arRec[1];
            $rec_dandt_str = substr($rec_dandt, 0, 4) . '-' . substr($rec_dandt, 5, 2) . '-' . substr($rec_dandt, 8, 2);
            $rec_dandt_str .= ' ' . substr($rec_dandt, 10, 2) . ':' . substr($rec_dandt, 13, 2) . ':00';
            $open = $arRec[2];
            $high = $arRec[3];
            $low = $arRec[4];
            $close = $arRec[5];
            $vol = $fileFormat == "MT4" ? intval($arRec[6]) : intval($arRec[8]); // заменил на spread, volume все равно не использовался
            if (!$first_bar_time) $first_bar_time = $rec_dandt_str;
            $last_bar_time = $rec_dandt_str;
            $ot = intval(date_format(date_create($rec_dandt_str), 'U'));
            $Chart[] = ["open_time" => $ot, "open" => $open, "high" => $high, "low" => $low, "close" => $close, "volume" => $vol, "close_time" => ($ot + $interval_sec)];
        }
        $ret_interval=$interval_sec;
    }
        unset($arTxt);
        return(['tf'=>$ret_interval,
            'chart'=>$Chart,
            'cnt'=>count($Chart),
            'mgu'=>(number_format(round(memory_get_usage()/1024),0,"."," ")." Kb"),
            'first_bar'=>substr($first_bar_time,0,16),
            'last_bar'=>substr($last_bar_time,0,16)
            ]);
}
function updateChart_Names($pair,$tf,$fileName){
    global $connection,$res;
    $result=queryMysql("select ifnull(max(id),0) id from chart_names where tool='$pair' and timeframe=$tf;");
    $id=$result->fetch_assoc()['id'];
    if($id>0){ // такой инструмент уже есть - обновляем запись
        $result=queryMysql("update chart_names set filename='$fileName' where id=$id;");
        return(intval($id));
    }
    $tf_min=$tf/60;
    $result=queryMysql("insert into chart_names (name,tool,timeframe,filename) VALUES ('".$pair.$tf_min."','$pair',$tf,'".$fileName."');");
    $id=$connection->insert_id;
    return($id);
}
function listdir($dir = '.') {
    if (!is_dir($dir)) {
        return false;
    }
    $files = array();
    listdiraux($dir, $files);
    return $files;
}
function listdiraux($dir, &$files) {
    $handle = opendir($dir);
    while (($file = readdir($handle)) !== false) {
        if ($file == '.' || $file == '..') {
            continue;
        }
        $filepath = $dir == '.' ? $file : $dir . '/' . $file;
        if (is_link($filepath))
            continue;
        if (is_file($filepath))
            $files[] = $filepath;
        else if (is_dir($filepath))
            listdiraux($filepath, $files);
    }
    closedir($handle);
}
function shortFileName($f){ // отрезает путь к файлу слева
    $pos1=strrpos($f,"\\");
    if($pos1===false)$pos1=-1;
    $pos2=strrpos($f,"/");
    if($pos2===false)$pos2=-1;
    $pos=max($pos1,$pos2);
    return(substr($f,$pos+1));
}
function die_($s=""){ // вспомогательная (замена die()) - показывает в $res - откуда был выход
    global $res;
    $e = new Exception();
    $trace = explode("\n", $e->getTraceAsString());
    $res['debug']['die_() trace']=$trace;
    $res['debug']['mgu before die']=number_format(memory_get_usage());
    $res['info']['die_() from']=shortFileName($trace[0] ?? "NA");
    die($s);
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
        case 1440:
            return ($begin . "00:00:00");
    }
    return ($begin . "00:00:00");
}
function calcModels($Chart,$tf,$name_id,$fileName_full,$startBar=0){
    // 2222222
    global $res,$connection,$tfList,$arrId,$arModels1_Ids,$statuses,$url_path;

    if(count($Chart)<MIN_CHART_LENGTH-5){  // вроде, не должно такого быть, но оставил на всякий случай проверку
        $res['debug']['Chart']=$Chart;
        $res['Errors'][]="ERROR - пустой или очень маленький чарт!";
        file_put_contents("tmp_log/_______error_".shortFileName(__FILE__)."(".__LINE__.").json",json_encode(get_defined_vars(),JSON_PARTIAL_OUTPUT_ON_ERROR)); // for debug only
        die_();
    }

// расчет моделей по ПЕРВОМУ алгоритму
    $param=["Chart"=>json_encode($Chart),"paramArr"=>["mode"=>"mode1","selectedbar"=>"0","log"=>"0","text_res"=>"0"],"Models1"=>"[]"];
//file_put_contents("TMP_res_____.json",json_encode($res,JSON_UNESCAPED_UNICODE));

    $ch = curl_init($url_path."build_models_A1.php");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($param,'', '&')); //["a"=>"aa","b"=>"bb","c"=>["c"=>"cc","d"=>"dd"]]);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, false);

    $tmp_time=microtime(true);
    $res_alg1 = curl_exec($ch);
    $res['tmp1_ch_error']=curl_error($ch);
    $calcTime_alg1=(microtime(true)-$tmp_time);
    $res['info']['calcTime_Alg1']=($res['info']['calcTime_Alg1'] ?? 0) + $calcTime_alg1;
    if($res_alg1===false){
        $res['Errors'][]="Ошибка вызова Alg1 - curl_error: ".curl_error($ch);
        file_put_contents("tmp_log/_______error_".shortFileName(__FILE__)."(".__LINE__.").json",json_encode(get_defined_vars(),JSON_PARTIAL_OUTPUT_ON_ERROR)); // for debug only
        die_();
    }

//    $err     = curl_errno( $ch ); set_debug_info($err,"postRecord : err");
//    $errmsg  = curl_error( $ch ); set_debug_info($errmsg,"postRecord : errmsg");
//    $header = curl_getinfo( $ch ); set_debug_info($header,"postRecord : header");

    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
//$res['tmp1']['$header_size']=$header_size;
    $headers = substr($res_alg1, 0, $header_size);
    $res['tmp1']['$headers']=$headers;
    $body = substr($res_alg1, $header_size);
    $res_Alg1_Arr = json_decode($body,true);
    if(!$res_Alg1_Arr  || count($res_Alg1_Arr['Errors']) > 0){
        $res['tmp1']['$res_Alg1'] = $res_Alg1_Arr;
        $res['Errors'][] = "Ошибки при расчете Alg1 :";
        $res['tmp1']['$body']=$body;
        $res['tmp1']['$param']=$param;
        $res['Errors']['Alg1_errors']=$res_Alg1_Arr['Errors'] ?? ["null"];
        file_put_contents("tmp_log/_______error_".shortFileName(__FILE__)."(".__LINE__.").json",json_encode(get_defined_vars(),JSON_PARTIAL_OUTPUT_ON_ERROR)); // for debug only
        die_();
    }
    $Models1 = $res_Alg1_Arr['Models']; // Массив моделей Алгоритма I
    if ($res_alg1 === false) throw new Exception('Could not get reply: ' . curl_error($ch));

//  БД ЗАНОСИМ МОДЕЛИ АЛГ. I В БД
//file_put_contents("TMP_fields___.txt",PHP_EOL);
//file_put_contents("TMP_insert_m___.txt",PHP_EOL);
// ` ////////////////////////////////////!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
    $arModels1_Ids = []; // ассоциативный массив, куда запоминаем id модели первого алгоритма по ключу типа "p962-1"
    foreach ($Models1 as $pk1 => $pv1)
        foreach ($pv1 as $pk2 => $model){
            $list_f = "";
            $list_v = "";
            $model_t = translate_model(1, $model);
            foreach($model_t as $pk => $field){ // все модели из массива Models1 разбиваются на содержимое по field
                // if(!($pk=="status"||$pk=="param"||$pk=="auxP6t_from"||$pk=="split"||$pk=="id")) {
                if(!($pk == "status" || $pk == "param" || $pk == "auxP6t_from" || $pk == "split" || $pk == "id" ||$pk == "IDprevs")) {
                    $v = $pk;
                    if($v == "2" || $v == "3")$v = "_" . $v;
                    $list_f .= ",`". $v ."`";
                    $list_v .= ",'". $field . "'";
                }
            }
            foreach($model_t as $pk => $field){
                if($pk == "param") foreach($field as $pkf => $prm) if($pkf !== "auxP6t_from"){
                    $list_f .= ",`" . $pkf . "`";
                    $list_v .= ",'" . str_replace("'","\'", $prm) . "'";
                }
            }
//    file_put_contents("TMP_fields___.txt",$list_f." (".$list_v.")".PHP_EOL,FILE_APPEND);
            $list_f = "`name_id`,`bar_id`,`alg`" . $list_f;
            $list_v = "'$name_id','" . $arrId[$model['t1']] . "','1'".$list_v;
            write_log("list_f= " . $list_f . PHP_EOL, 8);
            write_log("list_v= " . $list_v . PHP_EOL, 8);
            if(($model1_id = isModelExists($name_id, 1, $arrId[$model['t1']], $model_t['param']['_points'])) === false) { // если модели нет в БД, добавляем её
                $result = queryMysql("insert into models ($list_f) VALUES ($list_v);");
                $model1_id = $connection -> insert_id;
                // setStatus($connection -> insert_id, $model_t);
                setStatus($model1_id, $model_t);
                // setIDprevs($connection -> insert_id, $model_t); // добавляем ссылки на Prevs для моделей 1-го алгоритма
                //        file_put_contents("TMP_insert_m___.txt", "insert into models ($list_f) VALUES ($list_v);" . PHP_EOL, FILE_APPEND);
            }
            $arModels1_Ids[$pk1.'-'.$pk2] = $model1_id;
            write_log("Внесли в модель " . $pk1.'-'.$pk2 . " её id ". $model1_id . " arrId[model['t1']] = ". $arrId[$model['t1']] . PHP_EOL, 8);
        }
    foreach ($Models1 as $pk1 => $pv1)
        foreach ($pv1 as $pk2 => $model)
            if(($model1_id = isModelExists($name_id, 1, $arrId[$model['t1']], $model_t['param']['_points'])) != false)
            {
                $model_t = translate_model(1, $model);
                // $model1_id = $connection -> insert_id;
                setIDprevs($model1_id, $model_t); // добавляем ссылки на Prevs для моделей 1-го алгоритма
            }

// расчет моделей по ВТОРОМУ алгоритму
    $param = ["Chart" => json_encode($Chart),"paramArr" => ["mode" => "mode1","selectedbar" => "0","log" => "0","text_res"=>"0"],"Models1" => json_encode($Models1)];
//file_put_contents("TMP_res_____.json",json_encode($res,JSON_UNESCAPED_UNICODE));

    $ch = curl_init($url_path . "build_models_A2.php");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($param,'', '&')); //["a"=>"aa","b"=>"bb","c"=>["c"=>"cc","d"=>"dd"]]);
    curl_setopt($ch, CURLOPT_HEADER, true);

    $tmp_time=microtime(true);
    $res_alg2 = curl_exec($ch);
    $calcTime_alg2=(microtime(true)-$tmp_time);
    $res['info']['calcTime_Alg2']=($res['info']['calcTime_Alg2'] ?? 0) + $calcTime_alg2;
    if($res_alg1===false){
        $res['Errors'][]="Ошибка вызова Alg1 - curl_error: ".curl_error($ch);
        file_put_contents("tmp_log/_______error_".shortFileName(__FILE__)."(".__LINE__.").json",json_encode(get_defined_vars(),JSON_PARTIAL_OUTPUT_ON_ERROR)); // for debug only
        die_();
    }

    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
//$res['tmp2']['$header_size']=$header_size;
    $headers = substr($res_alg2, 0, $header_size);
    $res['tmp2']['$headers']=$headers;
    $body = substr($res_alg2, $header_size);
    $res_Alg2_Arr=json_decode($body,true);
    if(!$res_Alg2_Arr || count($res_Alg2_Arr['Errors'])>0){
        $res['tmp2']['$res_Alg2']=$res_Alg2_Arr;
        $res['Errors'][]="Ошибки при расчете Alg2 :";
        $res['tmp2']['$body']=$body;
        $res['Errors']['Alg2_errors']=$res_Alg2_Arr['Errors'] ?? ["null"];
        file_put_contents("tmp_log/_______error_".shortFileName(__FILE__)."(".__LINE__.").json",json_encode(get_defined_vars(),JSON_PARTIAL_OUTPUT_ON_ERROR)); // for debug only
        die_();
    }
    $Models2 = $res_Alg2_Arr['Models2']; //$res['tmp2']['$body']['Models2'];
    if ($res_alg2 === false) throw new Exception('Could not get reply: ' . curl_error($ch));

//  БД ЗАНОСИМ МОДЕЛИ АЛГ. II В БД
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
    foreach($Models2 as $pk1 => $pv1)
        foreach($pv1 as $pk2 => $model){
            $list_f = "";
            $list_v = "";
            $model_t = translate_model(2, $model);
            foreach($model_t as $pk => $field){
                if(!($pk == "status" || $pk == "param" || $pk == "split" || $pk == "id" || $pk == "Presupp" || $pk == "IDinners" || $pk == "IDprevs" || $pk == "p12candidates")) {
                    $v = $pk;
                    //if($v=="2"||$v=="3")$v="_".$v;
                    $list_f .= ",`" . $v . "`";
                    $list_v .= ",'" . $field . "'";
                }
            }
            foreach($model_t as $pk => $field){
                if($pk == "param") foreach($field as $pkf => $prm){
                    $list_f .= ",`" . $pkf . "`";
                    $list_v .= ",'" . str_replace("'", "\'", $prm) . "'";
                }
            }
            // file_put_contents("TMP_fields___.txt",$list_f." (".$list_v.")".PHP_EOL,FILE_APPEND);
            $list_f = "`name_id`,`bar_id`,`alg`" . $list_f;
            $list_v = "'$name_id','" . $arrId[$model['t3']] . "','2'" . $list_v;
            if(isModelExists($name_id, 2, $arrId[$model['t3']], $model_t['param']['_points']) === false) {
                $result = queryMysql("insert into models ($list_f) VALUES ($list_v);");
                $insertedID = $connection -> insert_id;
                setStatus($insertedID, $model_t);
                setIDprevs($insertedID, $model_t);
                setIDinners($insertedID, $model_t);
                //        file_put_contents("TMP_insert_m___.txt", "insert into models ($list_f) VALUES ($list_v);" . PHP_EOL, FILE_APPEND);
            }
        }
}
function setIDinners($model_id, $model){ // добавляем новые ссылки на модели Prevs
    global $connection, $arrId, $arModels1_Ids;
    if(isset($model['IDinners']) && count($model['IDinners']) > 0)
        foreach($model['IDinners'] as $pk => $pv){
            $ar_tmp1 = explode('-', $pv);
            $bar_alg1_t1 = substr($ar_tmp1[1], 1); // номер опорного бара модели алг.1
            $ind = $ar_tmp1[2]; // номер модели в массииве моделей опорного бара (t1)
            $model1_id = $arModels1_Ids[$ar_tmp1[1] . '-' . $ind];
            $result = queryMysql("insert into idinners_map (model_id,inner_model_id) VALUES ($model_id, $model1_id);");
        }
}

function setIDprevs($model_id, $model){ // добавляем новые ссылки на модели Prevs
    global $connection, $arrId, $arModels1_Ids, $res;
    if(isset($model['IDprevs']) && count($model['IDprevs']) > 0)
        foreach($model['IDprevs'] as $pk => $pv){
            write_log("model['IDprevs'] as pk => " . $pv . PHP_EOL, 8);
            $ar_tmp1 = explode('-', $pv);
            $bar_alg1_t1 = substr($ar_tmp1[1], 1); // номер опорного бара модели алг.1
            write_log("ar_tmp1[1] " . $ar_tmp1[1]  . PHP_EOL, 8);
            $ind = $ar_tmp1[2]; // номер модели в массииве моделей опорного бара (t1)
            $P6_bar = $ar_tmp1[3]; // бар расчетной т6
            $type = $ar_tmp1[4]; // тип модели - (тип=1 = непосредственно предшествующая , тип=2 = предшествующая коррекционная)
            $model1_id = $arModels1_Ids[$ar_tmp1[1] . '-' . $ind];
            //  $res['TMP__']="P6_bar=$P6_bar bar_alg1_t1=$bar_alg1_t1 pv=$pv";
            $result = queryMysql("insert into idprevs_map (model_id,prev_model_id,P6,type) VALUES ($model_id,$model1_id,".($P6_bar-$bar_alg1_t1).",$type);");
        }
}

function setStatus($model_id, $model){ // добавляем новые статусы в таблицу статусов по необходимости и прописываем мэппинг (соответсвие статусов для данной модели)
    global $statuses, $connection;

    foreach($model['status'] as $pk => $pv) if(substr($pk, 0, 3) !== 'TMP'){
        if (isset($statuses[$pk])) $status_id = $statuses[$pk];
        else {// такого статуса в нашем массиве пока нет - создаем его
            $status_text = str_replace("'","\'",$pk);
            $result = queryMysql("insert into statuses (status) VALUES ('$status_text');");
            $statuses[$pk] = $status_id = $connection -> insert_id;
        }
        $result = queryMysql("insert into status_map (model_id,status_id) VALUES ($model_id,$status_id)");
    }
}

function isModelExists($nameId, $Alg, $barID, $_points){ // возвращаем id, если модель с такими _points уже есть в базе и false в противном случае
    $result = queryMysql("select count(*) cnt,max(id) id from models where name_id= $nameId and Alg= $Alg and bar_id= $barID and _points='$_points'");
    //$result->data_seek(0);
    $fa = $result -> fetch_assoc();
    if($fa['cnt'])return($fa['id']);
    return(false);
}
function translate_model($algNum, $model){
    global $pointList,$res;
    $model_out = $model;
    $basePointName = ($algNum == 1) ? 't1' : 't3';
    $baseBarNum = $model_out[$basePointName];
    foreach($model as $key => $field) { // J замена абсолютных номеров точек на относительные к т.1/т.3 ?
        if (in_array($key, $pointList))
            $model_out[$key] = $field - $baseBarNum;
    }
    if(isset($model_out['param']))foreach($model_out['param'] as $key => $field){
        if (in_array($key, $pointList)) {
            //if (count($res['Errors']) > 0)
            //    $res['info']['ERROR'] = "TMP_ key=$key field=$field baseBarNum=$baseBarNum";
            $model_out['param'][$key] = round($field - $baseBarNum,3);

        }
    }
    if(isset($model_out['Presupp']))foreach($model_out['Presupp'] as $presupp){
        foreach($presupp as $presuppKey=>$presuppVal)$model_out[$presuppKey]=$presuppVal - $baseBarNum;
        break;
    }
    if(isset($model_out['param']['_CT']))$model_out['param']['_CT']=CP_translate($baseBarNum,$model_out['param']['_CT']);
    if(isset($model_out['param']['_cross_point']))$model_out['param']['_cross_point']=CP_translate($baseBarNum,$model_out['param']['_cross_point']);
    if(isset($model_out['param']['_cross_point2']))$model_out['param']['_cross_point2']=CP_translate($baseBarNum,$model_out['param']['_cross_point2']);
    if(isset($model_out['param']['_cross_p23']))$model_out['param']['_cross_p23']=CP_translate($baseBarNum,$model_out['param']['_cross_p23']);
    if(isset($model_out['param']['_points']))$model_out['param']['_points']=points_translate($algNum,$model_out['param']['_points']);
    return($model_out);
}
function CP_translate($baseBarNum,$CP){ // переводит (транслирует) строку для Кросс-поинт и пр. (формат "<дробный номер бара> (<Уровень пересечения >)" относительно опорного баоа - меняется только число до скобок
    $pos=strpos($CP,"(");
    $bar=substr($CP,0,$pos-1);
    //file_put_contents("TMP_err1__.txt","bar: ($bar) baseBarNum: ($baseBarNum) $CP: ($CP)".PHP_EOL,FILE_APPEND);
    return(round($bar-$baseBarNum,3).substr($CP,$pos-1));
}
function points_translate($algNum,$_points){ // переводит (транслирует) строку для _points  в относительные координаты
    $pArr=explode(" ",$_points);
    $arP=[];
    $arV=[];
    $baseBarNum=0;
    for($i=0;$i<count($pArr);$i++){
        $ar=explode(":",$pArr[$i]);
        $arP[]=$ar[0];
        $arV[]=$ar[1];
        if($algNum==1&&$ar[0]=='t1'||$algNum==2&&$ar[0]=='t3')$baseBarNum=$ar[1];
    }
    $out="";
    for($i=0;$i<count($arP);$i++){
        $out.=" ".$arP[$i].":".($arV[$i]-$baseBarNum);
    }
    return(substr($out,1));

}