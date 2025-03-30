<?php
ini_set('date.timezone', 'Europe/Moscow');
ini_set('memory_limit', '256M');
set_time_limit(60);
define("WRITE_LOG",0); // уровень логирования (пока про запас)
require_once 'login_log.php';
// функция берет иззаданного файла кумок с историей и заполняет БД - сам чарт и найденные можели по первому и второму алгоритму
// параметры:
// filePath - путь к файлу с историйе (.CSV)
// startBar - индекс первого бара (номер строки, начиная с 0)
// numBars - количество баров, начиная со startBar
// truncate - 1 либо 0 (по умолчнию) - нужно ли стирать, если по этому файлу в БД уже что-то есть (актуально для первой порции
//
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

ob_start();
if(WRITE_LOG>0)$f_header = fopen("fill_db4chart_.log", 'a');
$err_cnt=0;
$res=[]; // возворащаемый результат json
$res['Error']='Error_01';
$res['Errors']=[];
$res['info']['curDir']=$curDir;
$res['info']['type']='_GET';
$PARAM=$_GET;
if(isset($_POST['filePath'])){$res['info']['type']='_POST'; $PARAM=$_POST;}

//usleep(1000000);
$p=[]; $p['1m']=2; $p['5m']=3; $p['10m']=4; $p['15m']=5; $p['30m']=6; $p['1h']=7; $p['1D']=8; $p['1W']=9; $p['1M']=10; // список доступных интервалов - определяем по разнице времени между соседними барами (берем минимальное из всех (для случая гэпов)
//$sec=[]; $sec['1m']=60; $sec['5m']=60*5; $sec['10m']=60*10; $sec['15m']=60*15; $sec['30m']=60*30; $sec['1h']=60*60; $sec['1D']=24*60*60; $sec['1W']=7*24*60*60; $sec['1M']=30*7*24*60*60; // сколько сенунд в интервале

$url = ((!empty($_SERVER['HTTPS'])) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$url = explode('?', $url);
$url = $url[0];
$pos=strrpos($url,"/");
$url_path=$res['info']['url']=substr($url,0,$pos+1);

$filePath="big_saves\\EURUSD15.csv";
if (isset($PARAM['filePath'])) $filePath=$PARAM['filePath'];
$delimiter='\\';
$pos=strrpos($filePath,$delimiter);
if($pos===false){
    $pos=strrpos($filePath,'/');
    $delimiter='/';
}
if($pos===false){
    $pos=-1;
    $delimiter='\\';
}
$fileName=substr($filePath,$pos+1);
$res['info']['filePath_'.$filePath]=$filePath;
$res['info']['fileName_'.$fileName]=$fileName;
if(!file_exists($curDir.$delimiter.$filePath)){
    $res['Errors'][]='Файл '.$curDir.$delimiter.$filePath." не найден";
    die();
}

$truncate = isset($PARAM['truncate']) ? intval($PARAM['truncate']):0;
$startBar = isset($PARAM['startBar']) ? intval($PARAM['startBar']):0;
$res['info']['startBar']=$startBar;
$numBars = isset($PARAM['numBars']) ? intval($PARAM['numBars']):1000;
$res['info']['numBars']=$numBars;

if($numBars < 300 || $numBars > 10000){
    $res['Errors'][]="Некорректный параметр numBars: $numBars (должен быть от 300 до 10000)";
    die();
}

$res['info']['numBars'] = $numBars;
$result = queryMysql("select count(*) cnt,ifnull(max(id),0) id from chart_names where filename='$fileName'");
//$result->data_seek(0);
$fa = $result -> fetch_assoc();
$id = $fa['id'];
$res['info']['cnt']=$fa['cnt'];
$res['info']['id']=$name_id=$fa['id']; ////////////
if($fa['cnt'] === "0"){ // такого инструмента в БД пока нет
 $isFirstPart = true;
 $res['info']['insert'] = "Insert ".$fileName;
 $instrumentName = strtoupper($fileName);
 if(!(($pos = strrpos(strtoupper($fileName),".CSV")) === false))$instrumentName = substr($instrumentName,0,$pos);

                         // блок для имен файлов из МТ5
                            $instrumentName = str_replace("_I_H4","240",$instrumentName);
                            $instrumentName = str_replace("_I_H1","60",$instrumentName);
                            $instrumentName = str_replace("_I_M15","15",$instrumentName);
                            $instrumentName = str_replace("_I_M30","30",$instrumentName);
                            $instrumentName = str_replace("_I_M5","5",$instrumentName);
                            if( !(($pos = strpos(strtoupper($instrumentName),"_")) === false))$instrumentName=substr($instrumentName,0,$pos);
 $res['info']['instrumentName_'.$fileName]=$instrumentName;
 if(strlen($instrumentName)<=6 || strlen($instrumentName)>12){
     $res['Errors'][]="Некорректное имя файла $filePath ";
     die();
 }
 $toolName = preg_replace('/[0-9,]/', '', $instrumentName);
 $result = queryMysql("insert into chart_names (name,tool,timeframe,filename) VALUES('$instrumentName','$toolName','30m','$fileName');");
 $err = $connection -> error;
 $name_id = $connection -> insert_id;

    if($connection->error){
        $res['Errors'][]="Ошибка добавления записи chart_names - ".$connection->error;
        die();
    }
    else $res['info']['inserted_id']=$name_id;
}
else if($truncate==1){
        $isFirstPart=true;
    $res['info']['delete']="Delete id ".$fa['id'];
    $result=queryMysql("delete from charts where name_id='".$fa['id']."';");
    if(!$result){
        $res['Errors'][]="Ошибка удаления записей из charts - ".$connection->error;
        die();
    }
    else $res['info']['deleted']='Deleted name_id='.$fa['id'];
    $result=queryMysql("truncate table temp;");
    $result=queryMysql("insert into temp (select id from models where name_id='".$fa['id']."');"); // список ранее сформированных id моделей по данному инструменту
    $result=queryMysql("delete from models where name_id='".$fa['id']."';");
    if(!$result){
        $res['Errors'][]="Ошибка удаления записей из models - ".$connection->error;
        die();
    }

    $result=queryMysql("delete from status_map where model_id in (select id from temp);"); // стираем мэппинг статусов по данноому списку id моделей (инструменту)
    $result=queryMysql("delete from idprevs_map where model_id in (select id from temp);"); // стираем мэппинг IDprevs
    $result=queryMysql("delete from idinners_map where model_id in (select id from temp);"); // стираем мэппинг IDinners
    $result=queryMysql("delete from controls where model_id in (select id from temp);"); // стираем контрольные параметры моделей
}

// читаем в ассоциативный массив статусы и из id
$statuses=[]; // тут хранимм id статусов (ключи - сами статусы
$result=queryMysql("select status,id from statuses;");
if($result->num_rows>0){
    $result->data_seek(0);
    while($rec=$result->fetch_assoc())$statuses[$rec['status']]=$rec['id'];
}
//$res['statuses']=$statuses;
$tmp_time=microtime(true);
$txt=file_get_contents($curDir.$delimiter.$filePath);

$arTxt = explode("\n", $txt);

$fileFormat="MT4";
if(substr($arTxt[0],0,6)=="<DATE>") {
    $fileFormat = "MT5";
    array_shift($arTxt);
}
//if(strlen($arTxt[count($arTxt)-1])<20)unset($arTxt[count($arTxt)-1]);
//file_put_contents("TMP___.txt","lastBarTime=$lastBarTime lastBar=".$_POST['lastBar']);
//$isFound=false;
$cnt=count($arTxt);
$res['info']['count_bars']=$cnt;
$res['calctime_make_arr']=microtime(true)-$tmp_time;
$Chart=[];
// прокручиваем несколько баров с начала - просто определяем интервал между барами
$interval_sec=1000000000;
$res['info']['firstLine']=$arTxt[$startBar];


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
        if($interval_<$interval_sec)$interval_sec=$interval_;
    }
else
    for ($i = 0; $i < $cnt-10; $i++) { // 20220311 исправлено на прокрутку ВСЕГО массива, так как было обнаружено что в некоторых файлах (часовики, 4часовики) первные сотня записей с шагом 1 сутки (почему то)
        $arRec = explode("\t", $arTxt[$i]);
        $arRec_next = explode("\t", $arTxt[$i + 1]);
            $rec_dandt = $arRec[0] . $arRec[1] . ".00";
            $rec_dandt_next = $arRec_next[0] . $arRec_next[1] . ".00";
            $rec_dandt_str = substr($rec_dandt, 0, 4) . '-' . substr($rec_dandt, 5, 2) . '-' . substr($rec_dandt, 8, 2);
            $rec_dandt_str .= ' ' . substr($rec_dandt, 10, 2) . ':' . substr($rec_dandt, 13, 2) . ':00';
            $rec_dandt_str_next = substr($rec_dandt_next, 0, 4) . '-' . substr($rec_dandt_next, 5, 2) . '-' . substr($rec_dandt_next, 8, 2);
            $rec_dandt_str_next .= ' ' . substr($rec_dandt_next, 10, 2) . ':' . substr($rec_dandt_next, 13, 2) . ':00';
            $interval_ = date_format(date_create($rec_dandt_str_next), 'U') - date_format(date_create($rec_dandt_str), 'U');
            if ($interval_ < $interval_sec) $interval_sec = $interval_;
    }

if($truncate==1)$result=queryMysql("update chart_names set timeframe='$interval_sec' where  id=$name_id;");

$res['info']['interval_sec']=$interval_sec;

$arrId=[]; // массив id баров на чарте
$barCnt=0;
for ($i = $startBar; $i < $cnt && $i<($startBar+$numBars); $i++) {
    if (strlen($arTxt[$i]) > 20) {
        $delimiter=$fileFormat=="MT4"?",":"\t";
        $res['info']['lastLine']=$arTxt[$i];
        $arRec = explode($delimiter, $arTxt[$i]);
   //     $arRec_next = explode(",", $arTxt[$i+1]);
        $rec_dandt = $arRec[0] . $arRec[1];
//        $rec_dandt_next = $arRec_next[0] . $arRec_next[1];
        $rec_dandt_str = substr($rec_dandt, 0, 4) . '-' . substr($rec_dandt, 5, 2) . '-' . substr($rec_dandt, 8, 2);
        $rec_dandt_str .= ' ' . substr($rec_dandt, 10, 2) . ':' . substr($rec_dandt, 13, 2) . ':00';
//        $rec_dandt_str_next = substr($rec_dandt_next, 0, 4) . '-' . substr($rec_dandt_next, 5, 2) . '-' . substr($rec_dandt_next, 8, 2);
//        $rec_dandt_str_next .= ' ' . substr($rec_dandt_next, 10, 2) . ':' . substr($rec_dandt_next, 13, 2) . ':00';
        $open = $arRec[2];
        $high = $arRec[3];
        $low = $arRec[4];
        $close = $arRec[5];
        $vol = $fileFormat=="MT4"?$arRec[6]:$arRec[8]; // заменил на spread, volume все равно не использовался
//        $ret[] = ['open_time' => strtotime($rec_dandt_str) * 1000,
//            'open' => floatval($open),
//            'high' => floatval($high),
//            'low' => floatval($low),
//            'close' => floatval($close),
//            'volume' => floatval($vol),
//            'close_time' => strtotime($rec_dandt_str_next)  * 1000,
//        ];
        $result=queryMysql("select count(*) cnt,max(id) bar_id from charts where name_id=$name_id and dandt='$rec_dandt_str'");
        $ar_tmp=$result->fetch_assoc();
        if($ar_tmp['cnt']>0){ // такой бар уже был занесен в БД (нахлест при заполнении)
            $arrId[$i-$startBar]=$ar_tmp['bar_id'];
        }
        else{
            $result=queryMysql("insert into charts (dandt,o,c,h,l,v,name_id) VALUES('$rec_dandt_str',$open,$close,$high,$low,$vol,$name_id);");
            if(!$result){
                $res['Errors'][]="Ошибка добавления записи в charts - ".$connection->error;
                die();
            }
            $arrId[$i-$startBar]=$connection->insert_id;
        }
        $ot=date_format(date_create($rec_dandt_str), 'U');
        $Chart[]=["open_time"=>$ot,"open"=>$open,"high"=>$high,"low"=>$low,"close"=>$close,"volume"=>$vol,"close_time"=>($ot+$interval_sec)];
        $res['info']['maxBarTime']=$rec_dandt_str;
        $barCnt++;
    }
}

$res['info']['barCnt']=$barCnt;
// расчет моделей по ПЕРВОМУ алгоритму
$param=["Chart"=>json_encode($Chart),"paramArr"=>["mode"=>"mode1","selectedbar"=>"0","log"=>"0"],"Models1"=>"[]"];
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
$res['info']['calcTime_Alg1']=$calcTime_alg1;
if($res_alg1===false){
    $res['Errors'][]="Ошибка вызова Alg1 - curl_error: ".curl_error($ch);
    die();
}


//    $err     = curl_errno( $ch ); set_debug_info($err,"postRecord : err");
//    $errmsg  = curl_error( $ch ); set_debug_info($errmsg,"postRecord : errmsg");
//    $header = curl_getinfo( $ch ); set_debug_info($header,"postRecord : header");

$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
//$res['tmp1']['$header_size']=$header_size;
$headers = substr($res_alg1, 0, $header_size);
$res['tmp1']['$headers']=$headers;
$body = substr($res_alg1, $header_size);
//file_put_contents("TMP___Alg1_res.json",$body);
$res_Alg1_Arr = json_decode($body,true);

$res['tmp1']['$res_Alg1'] = $res_Alg1_Arr;
if(count($res_Alg1_Arr['Errors']) > 0){
    $res['Errors'][] = "Ошибки при расчете Alg1 :";
    $res['Errors']['Alg1_errors']=$res_Alg1_Arr['Errors'];
    die();
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
$param = ["Chart" => json_encode($Chart),"paramArr" => ["mode" => "mode1","selectedbar" => "0","log" => "0"],"Models1" => json_encode($Models1)];
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
$res['info']['calcTime_Alg2']=$calcTime_alg2;
if($res_alg1===false){
    $res['Errors'][]="Ошибка вызова Alg1 - curl_error: ".curl_error($ch);
    die();
}

$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
//$res['tmp2']['$header_size']=$header_size;
$headers = substr($res_alg2, 0, $header_size);
$res['tmp2']['$headers']=$headers;
$body = substr($res_alg2, $header_size);
$res_Alg2_Arr=json_decode($body,true);
$res['tmp2']['$res_Alg2']=$res_Alg2_Arr;

if(count($res_Alg2_Arr['Errors'])>0){
    $res['Errors'][]="Ошибки при расчете Alg2 :";
    $res['Errors']['Alg2_errors']=$res_Alg2_Arr['Errors'];
    die();
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

unset($res['Error']);
die();


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

?>