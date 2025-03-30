<?php
die(); // старая версия - теперь calc_statistics.php
ini_set('date.timezone', 'Europe/Moscow');
define("WRITE_LOG", 0); // уровень логирования 0-нет 9 - максимальный
define("REC_LIMIT_DEBUG", 100000000); // количество записей (ограничение для отладки)
set_time_limit(0);
error_reporting(E_ALL); //error_reporting(E_ALL & ~E_NOTICE);
$res = [];
$res['info']['limit'] = REC_LIMIT_DEBUG;
require_once 'login_log.php';
$connection_dop = new mysqli($dbhost, $dbuser, $dbpass, $dbname, 3306);
if ($connection_dop->connect_error) die($connection_dop->connect_error);
$startTime = microtime(true);
$startTimeSql = microtime(true);
//$result=queryMysql("select * from mf limit 1000000;",false,MYSQLI_USE_RESULT);// ,MYSQLI_USE_RESULT);
$result = queryMysql("truncate table token_strings;");
$result = queryMysql("truncate table lists;");
$list_headers = [];
$result = queryMysql("select * from list_headers;", false, MYSQLI_USE_RESULT);
while ($rec = $result->fetch_assoc()) $list_headers[] = $rec;
$result = queryMysql("select m.*,c.*,n.section from models m left join controls c on m.id=c.model_id left join chart_names n on m.name_id=n.id  limit " . REC_LIMIT_DEBUG . ";", false, MYSQLI_USE_RESULT); // ,MYSQLI_USE_RESULT);

$calcTime_sql = microtime(true) - $startTimeSql;
//$result->data_seek(0);
$num_r = $result->num_rows;
echo "num_rows=$num_r<br>";
$i = 0;
$res['tokens'] = $tokens = [];
//$res['info']['tmp_cnt']=0;
//$res['info']['ar_tmp']=[];
while ($rec = $result->fetch_assoc()) {
    $i++;
    $token_string = "";
    $tokensOfModel = []; // коллекция токенов для данной модели
    foreach ($rec as $pk => $pv) {
        if (!is_null($pv)) {
            if ($token = getToken($pk, $pv)) { // подсчет заполненных и посчитанных токенов
                if (isset($tokens[$pk][$token])) $tokens[$pk][$token]++;
                else $tokens[$pk][$token] = 1;
                $token_string .= " | " . $token;
                $tokensOfModel[$pk] = $token;
            } else { // считаем количесво полей, где не null, но токена нет (вернулся false)
                if (isset($res["NoTokenFields"][$pk])) $res["NoTokenFields"][$pk]++;
                else $res["NoTokenFields"][$pk] = 1;
            }
        } else { // считаем кол-во полей, где null
            if (isset($res["NullFields"][$pk])) $res["NullFields"][$pk]++;
            else $res["NullFields"][$pk] = 1;
        }
    }
    if (strlen($token_string) > 3) $result1 = $connection_dop->query("insert into token_strings (model_id,token_str) VALUES (" . $rec['id'] . ",'" . str_replace("'", "\'", substr($token_string, 3)) . "');");
    // блок расчета и заполнения статистики по листам
    if ($rec['G1'] !== "WEDGE" && $rec['alt'] <= 7) {

        foreach ($list_headers as $pk_lh => $pv_lh) if ($pv_lh['G1'] == $rec['G1']) {
            $match = true;
            if (!(!is_null($rec[$pv_lh['aim1']]) || !is_null($pv_lh['aim2']) && !is_null($rec[$pv_lh['aim2']]))) $match = false; // определено значение хотя бы одной из указанных в list_headers цедей
            if ($match) for ($i = 1; $i <= 8; $i++) { // перебор 8 параметров
                $p = $pv_lh['param' . $i];

                if (!isset($tokensOfModel[$p])) { // хотя бы один токен отсутствует, значит этот лист не рассматриваем
                    $match = false;
                    //$res['info']['tmp_cnt']++;
                    break;
                }
            }
            if ($match) {  // все токены для данного листа присутсвуют у модели + есть хотя бы одна цель!=null - заполняем lists
                //                $res['info']['tmp2']='мы тут';
                addLineIntoList($pv_lh, $tokensOfModel, $rec, true); // заполняет лист нашего участка графиков (section = 1,2 или 3)
                addLineIntoList($pv_lh, $tokensOfModel, $rec, false); // общие листы для всех участков (section=0)
            }
        }
    }
}
//foreach($insertTokensString as $pk=>$pv){
//    $result1=queryMysql("insert into token_strings (model_id,token_str) VALUES (".$rec['id'].",'".str_replace("'","\'",substr($token_string,3))."');");
//}
//echo "i=$i calcTime=".(microtime(true)-$startTime)." calcTime_sql=$calcTime_sql<br><br>";
//foreach($tmpAr as $pk=>$pv)echo "($pk) : $pv<br>";
$res['tokens'] = $tokens;
ksort($res['tokens']);
ksort($res['NoTokenFields']);
ksort($res['NullFields']);
foreach ($res['tokens'] as $pk => $pv) ksort($res['tokens'][$pk]);
$result = queryMysql("truncate table tokens;");
foreach ($res['tokens'] as $token_name => $tokens_) foreach ($tokens_ as $token_value => $qnt) {
    $result = queryMysql("insert into tokens (param,token,qnt) VALUES ('" . str_replace("'", "\'", $token_name) . "','" . str_replace("'", "\'", $token_value) . "',$qnt);");
}
//$res['list_headers']=$list_headers;
die();
function addLineIntoList($list_header, $tokens, $rec, $selectedSection = false)
{
    global $connection_dop, $res;
    $section = $selectedSection ? $rec['section'] : 0;
    $query_ = "select * from lists where G1='" . $rec['G1'] . "' and section=$section ";
    for ($i = 1; $i <= 8; $i++) $query_ .= " and token$i='" . str_replace("'", "\'", $tokens[$list_header["param$i"]]) . "'";
    $res['info']['tmp_query'] = $query_;
    $result = $connection_dop->query($query_);
    if ($result->num_rows > 1) {
        $res['Errors'][] = "ERROR - больше 1 записи при отборе " . $query_;
        die();
    }
    $aim1_name = $list_header['aim1'];
    $aim2_name = $list_header['aim2'];

    if ($result->num_rows == 0) {
        $res['info']['tmp1'] = "Ветка 1";
        $aim1_0 = $aim1_15 = $aim1_30 = $aim1_50 = $aim1_80 = $aim1_120 = 0;
        $aim2_0 = $aim2_15 = $aim2_30 = $aim2_50 = $aim2_80 = $aim2_120 = 0;
        if (!is_null($rec[$aim1_name])) {
            $aim1_all = 1;
            if ($rec[$aim1_name] == "0") $aim1_0 = 1;
            else if ($rec[$aim1_name] == "15%") $aim1_15 = 1;
            else if ($rec[$aim1_name] == "30%") $aim1_30 = 1;
            else if ($rec[$aim1_name] == "50%") $aim1_50 = 1;
            else if ($rec[$aim1_name] == "80%") $aim1_80 = 1;
            else if ($rec[$aim1_name] == "120%") $aim1_120 = 1;
        } else $aim1_all = 0;

        if (!is_null($aim2_name) && !is_null($rec[$aim2_name])) {
            $aim2_all = 1;
            if ($rec[$aim2_name] == "0") $aim2_0 = 1;
            else if ($rec[$aim2_name] == "15%") $aim2_15 = 1;
            else if ($rec[$aim2_name] == "30%") $aim2_30 = 1;
            else if ($rec[$aim2_name] == "50%") $aim2_50 = 1;
            else if ($rec[$aim2_name] == "80%") $aim2_80 = 1;
            else if ($rec[$aim2_name] == "120%") $aim2_120 = 1;
        } else $aim2_all = 0;

        $query_ = "insert into lists (list_id,section,G1,token1,token2,token3,token4,token5,token6,token7,token8,aim1_all,aim1_0,aim1_15,aim1_30,aim1_50,aim1_80,aim1_120,aim2_all,aim2_0,aim2_15,aim2_30,aim2_50,aim2_80,aim2_120) VALUES(" .
            $list_header['id'] . ",$section,'" . $rec['G1'] . "',";
        for ($i = 1; $i <= 8; $i++) $query_ .= "'" . str_replace("'", "\'", $tokens[$list_header["param$i"]]) . "',";
        $query_ .= "$aim1_all,$aim1_0,$aim1_15,$aim1_30,$aim1_50,$aim1_80,$aim1_120,$aim2_all,$aim2_0,$aim2_15,$aim2_30,$aim2_50,$aim2_80,$aim2_120);";
        $res['info']['tmp_query2'] = $query_;
        $result = $connection_dop->query($query_);
    } else { // такая строка в БД уже есть, просто наращиваем счетчики
        $listRec = $result->fetch_assoc();
        $set = "";
        if (!is_null($rec[$aim1_name])) {
            $set .= ",aim1_all=aim1_all+1";
            if ($rec[$aim1_name] == "0") $set .= ",aim1_0=aim1_0+1";
            else if ($rec[$aim1_name] == "15%") $set .= ",aim1_15=aim1_15+1";
            else if ($rec[$aim1_name] == "30%") $set .= ",aim1_30=aim1_30+1";
            else if ($rec[$aim1_name] == "50%") $set .= ",aim1_50=aim1_50+1";
            else if ($rec[$aim1_name] == "80%") $set .= ",aim1_80=aim1_80+1";
            else if ($rec[$aim1_name] == "120%") $set .= ",aim1_120=aim1_120+1";
        }
        if (!is_null($aim2_name) && !is_null($rec[$aim2_name])) {
            $set .= ",aim2_all=aim2_all+1";
            if ($rec[$aim2_name] == "0") $set .= ",aim2_0=aim2_0+1";
            else if ($rec[$aim2_name] == "15%") $set .= ",aim2_15=aim2_15+1";
            else if ($rec[$aim2_name] == "30%") $set .= ",aim2_30=aim2_30+1";
            else if ($rec[$aim2_name] == "50%") $set .= ",aim2_50=aim2_50+1";
            else if ($rec[$aim2_name] == "80%") $set .= ",aim2_80=aim2_80+1";
            else if ($rec[$aim2_name] == "120%") $set .= ",aim2_120=aim2_120+1";
        }
        $set = substr($set, 1);
        $query_ = "update lists SET $set where id=" . $listRec['id'] . ";";
        $result = $connection_dop->query($query_);
    }
}
function getToken($field_name, $field_value)
{ // функция по имени параметра вычисляет значение токена
    // НАПОМИНАНИЕ: потом поменять alt_chk на alt
    if (is_null($field_value)) return ("
}ERROR_isNull");
    switch ($field_name) {
        case "AUX":
            return ($field_name . "_" . trim($field_value));  // стр.60 в XLS
        case "AUX5'":
            return ($field_name . "_" . trim($field_value));  // стр.62
        case "AimsBlock5'":
            return ($field_name . "_" . trim($field_value));  // стр.61
        case "E1":
        case "E3":
        case "E4":
            return ($field_name . "_" . trim($field_value));  // стр.12,14,15
        case "E6":
            return ($field_name . "_" . trim($field_value));  // стр.36
        case "EAM5\"":
            return ($field_name . "_" . trim($field_value));  // стр.63
        case "EAMP3":
            return ($field_name . "_" . trim($field_value));  // стр.16
        case "G1":
        case "G3":
            return ($field_name . "_" . trim($field_value));  // стр.3,7
        case "P3":
            return ($field_name . "_" . trim($field_value));  // стр.18
        case "P6aims":
            return ($field_name . "_" . trim($field_value));  // стр.154
        case "P6aims\"":
            return ($field_name . "_" . trim($field_value));  // стр.205
        case "P6dev": // стр.156
        case "auxP6dev": // стр.173
        case "auxP6dev'": // стр.204
        case "P6dev\"":
            return ($field_name . "_" . trim($field_value)); // стр.207
        case "Par25prcnt": // стр.31
            $tmp = str_replace("%", "", $field_value);
            if (!is_numeric($tmp)) return ($field_name . "_" . trim($field_value));
            if (intval($tmp) == 0) return ($field_name . "_" . "0%");
            if (intval($tmp) <= 5) return ($field_name . "_" . "<=5%");
            if (intval($tmp) <= 25) return ($field_name . "_" . "<=25%");
            if (intval($tmp) <= 50) return ($field_name . "_" . "<=50%");
            return ($field_name . "_" . ">50%");
        case "SP":
            return ($field_name . "_" . trim($field_value)); // стр.10
        case "SPc": // стр.9 // =A9&"_"&(ЕСЛИ(ЕЧИСЛО(--C9)=ИСТИНА;ЕСЛИ(--C9<=1;"strong";ЕСЛИ(--C9<=2;"weak";"weakest"));C9))
            if (is_numeric($field_value)) {
                if (floatval($field_value) <= 1) return ($field_name . "_strong");
                if (floatval($field_value) <= 2) return ($field_name . "_weak");
                return ($field_name . "_weakest");
            } else return ($field_name . "_" . trim($field_value));
        case "abs5":
            return ($field_name . "_" . trim($field_value)); // стр.59
        case "auxP3":
            return ($field_name . "_" . trim($field_value)); // стр.17
        case "auxP6aims": // стр.171
        case "auxP6aims'":
            return ($field_name . "_" . trim($field_value)); // стр.222
        case "ll2'3#5": // стр.44
        case "ll2'3#56": // стр.45
        case "lvl3#2'6": // стр.46
        case "lvl3#46": // стр.47
        case "lvl32'to2'6": // стр.38
        case "lvl32'to2'6aux": // стр.39
            //=A45&"_"&(ЕСЛИ(ЕЧИСЛО(--C45)=ИСТИНА;
            //ЕСЛИ(--C45=0;0;
            //ЕСЛИ(--C45<0,25;"<0,25";
            //ЕСЛИ(--C45<0,5;"0,25-<0,5";
            //ЕСЛИ(--C45<0,75;"0,5-<0,75";
            //ЕСЛИ(--C45<1;"0,75-<1";
            //ЕСЛИ(--C45<2;"1-<2";
            //ЕСЛИ(--C45<3;"2-<3";
            //ЕСЛИ(--C45<4;"3-<4";
            //ЕСЛИ(--C45<5;"4-<5";
            //ЕСЛИ(--C45<6;"5-<6";
            //ЕСЛИ(--C45<7;"6-<7";
            //ЕСЛИ(--C45<8;"7-<8";
            //ЕСЛИ(--C45<9;"8-<9";
            //ЕСЛИ(--C45<10;"9-<10";">=10" ))))))))))))));C45))

            if (is_numeric($field_value)) {
                $val = floatval($field_value);
                if ($val == 0) return ($field_name . "_" . "0");
                if ($val < 0.25) return ($field_name . "_" . "<0,25");
                if ($val < 0.5) return ($field_name . "_" . "0,25-<0,5");
                if ($val < 0.75) return ($field_name . "_" . "0,5-<0,75");
                if ($val < 1) return ($field_name . "_" . "0,75-<1");
                if ($val < 2) return ($field_name . "_" . "1-<2");
                if ($val < 3) return ($field_name . "_" . "2-<3");
                if ($val < 4) return ($field_name . "_" . "3-<4");
                if ($val < 5) return ($field_name . "_" . "4-<5");
                if ($val < 6) return ($field_name . "_" . "5-<6");
                if ($val < 7) return ($field_name . "_" . "6-<7");
                if ($val < 8) return ($field_name . "_" . "7-<8");
                if ($val < 9) return ($field_name . "_" . "8-<9");
                if ($val < 10) return ($field_name . "_" . "9-<10");
                return ($field_name . "_" . ">=10");
            } else return ($field_name . "_" . trim($field_value));
        case "ll2'5to56": // стр.42
        case "ll2'5to56aux": // стр.43
        case "lvl34to46": // стр.40
        case "lvl34to46aux": // стр.41
        case "ParE": // стр.41
            //=A42&"_"&(ЕСЛИ(ЕЧИСЛО(--C42)=ИСТИНА;
            //ЕСЛИ(--C42=0;0;
            //ЕСЛИ(--C42<1;"0-1";ЕСЛИ(--C42<2;"1-2";ЕСЛИ(--C42<3;"2-3";ЕСЛИ(--C42<4;"3-4";ЕСЛИ(--C42<5;"4-5"; ЕСЛИ(--C42<6;"5-6";ЕСЛИ(--C42<7;"6-7";ЕСЛИ(--C42<8;"7-8";ЕСЛИ(--C42<9;"8-9";ЕСЛИ(--C42<10;"9-10";">=10" )) )))))))));C42))

            if (is_numeric($field_value)) {
                $val = floatval($field_value);
                if ($val == 0) return ($field_name . "_" . "0");
                if ($val < 1) return ($field_name . "_" . "0-1");
                if ($val < 2) return ($field_name . "_" . "1-2");
                if ($val < 3) return ($field_name . "_" . "2-3");
                if ($val < 4) return ($field_name . "_" . "3-4");
                if ($val < 5) return ($field_name . "_" . "4-5");
                if ($val < 6) return ($field_name . "_" . "5-6");
                if ($val < 7) return ($field_name . "_" . "6-7");
                if ($val < 8) return ($field_name . "_" . "7-8");
                if ($val < 9) return ($field_name . "_" . "8-9");
                if ($val < 10) return ($field_name . "_" . "9-10");
                return ($field_name . "_" . ">=10");
            } else return ($field_name . "_" . trim($field_value));

        case "lvl23#45": // стр.48
            //        =A48&"_"&(ЕСЛИ(ЕЧИСЛО(--C48)=ИСТИНА;
            //        ЕСЛИ(--C48>=0;(
            //    ЕСЛИ(--C48=0;0;
            //        ЕСЛИ(--C48<0,25;"<0,25";
            //        ЕСЛИ(--C48<0,5;"0,25-<0,5";
            //        ЕСЛИ(--C48<0,75;"0,5-<0,75";
            //        ЕСЛИ(--C48<1;"0,75-<1";
            //        ЕСЛИ(--C48<2;"1-<2";
            //        ЕСЛИ(--C48<3;"2-<3";
            //        ЕСЛИ(--C48<4;"3-<4";
            //        ЕСЛИ(--C48<5;"4-<5";
            //        ЕСЛИ(--C48<6;"5-<6";
            //        ЕСЛИ(--C48<7;"6-<7";
            //        ЕСЛИ(--C48<8;"7-<8";
            //        ЕСЛИ(--C48<9;"8-<9";
            //        ЕСЛИ(--C48<10;"9-<10";">=10" )))))))))))))));
            //ЕСЛИ(--C48>-0,25;">-0,25";
            //ЕСЛИ(--C48>-0,5;"-0,25->-0,5";
            //ЕСЛИ(--C48>-0,75;"-0,5->-0,75";
            //ЕСЛИ(--C48>-1;"-0,75->-1";
            //ЕСЛИ(--C48>-2;"-1->-2";
            //ЕСЛИ(--C48>-3;"-2->-3";
            //ЕСЛИ(--C48>-4;"-3->-4";
            //ЕСЛИ(--C48>-5;"-4->-5";
            //ЕСЛИ(--C48>-6;"-5->-6";
            //ЕСЛИ(--C48>-7;"-6->-7";
            //ЕСЛИ(--C48>-8;"-7->-8";
            //ЕСЛИ(--C48>-9;"-8->-9";
            //ЕСЛИ(--C48>-10;"-9->-10";"<-=10"))))))))))))));#ССЫЛКА!))
            if (is_numeric($field_value)) {
                $val = floatval($field_value);
                if ($val == 0) return ($field_name . "_" . "0");
                if ($val > 0) {
                    if ($val < 0.25) return ($field_name . "_" . "<0,25");
                    if ($val < 0.5) return ($field_name . "_" . "0,25-<0,5");
                    if ($val < 0.75) return ($field_name . "_" . "0,5-<0,75");
                    if ($val < 1) return ($field_name . "_" . "0,75-<1");
                    if ($val < 2) return ($field_name . "_" . "1-<2");
                    if ($val < 3) return ($field_name . "_" . "2-<3");
                    if ($val < 4) return ($field_name . "_" . "3-<4");
                    if ($val < 5) return ($field_name . "_" . "4-<5");
                    if ($val < 6) return ($field_name . "_" . "5-<6");
                    if ($val < 7) return ($field_name . "_" . "6-<7");
                    if ($val < 8) return ($field_name . "_" . "7-<8");
                    if ($val < 9) return ($field_name . "_" . "8-<9");
                    if ($val < 10) return ($field_name . "_" . "9-<10");
                    return ($field_name . "_" . ">=10");
                } else {
                    if ($val > -0.25) return ($field_name . "_" . ">-0,25");
                    if ($val > -0.5) return ($field_name . "_" . "-0,25->-0,5");
                    if ($val > -0.75) return ($field_name . "_" . "-0,5->-0,75");
                    if ($val > -1) return ($field_name . "_" . "-0,75->-1");
                    if ($val > -2) return ($field_name . "_" . "-1->-2");
                    if ($val > -3) return ($field_name . "_" . "-2->-3");
                    if ($val > -4) return ($field_name . "_" . "-3->-4");
                    if ($val > -5) return ($field_name . "_" . "-4->-5");
                    if ($val > -6) return ($field_name . "_" . "-5->-6");
                    if ($val > -7) return ($field_name . "_" . "-6->-7");
                    if ($val > -8) return ($field_name . "_" . "-7->-8");
                    if ($val > -9) return ($field_name . "_" . "-8->-9");
                    if ($val > -10) return ($field_name . "_" . "-9->-10");
                    return ($field_name . "_" . "<=-10");
                }
            }
            return ($field_name . "_" . ">=10");


        case "alt":
            return ($field_name . "_" . (($field_value) ? "1+" : "0"));  // стр.6
            //        case "G1": return("_".$field_value);
            //        case "G3": return("_".$field_value);
    }
    return (false);
}
