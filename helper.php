<?php
require_once 'login_4js.php';
echo <<<_END
<html><head><title>вспомогательная программка</title></head><body>
<form method='post' action='helper.php' enctype='multipart/form-data'>
Id строки статистики:
<input type='text' name='str_id' size='10'>
Id строки статистики заголовка листа:
<input type='text' name='list_id' size='10'>
также нужен список:<input type='checkbox' name='need_list' >
<input type='submit' value='Сформировать запрос/список'></form>
_END;
echo "<pre>";
$need_list=$_POST['need_list']??false;
$str_id=$_POST['str_id']??false;
$list_id=$_POST['list_id']??false;
if ($str_id){ //запрос по id стрики статистики - возвращаем список id строк + (альтернативный вариант) - select по условиям (tokens)
   echo "<br>Запрос: str_id=".$_POST['str_id'].($need_list?" (SELECT+LIST)":" (SELECT only)")."<br><br>";
   $result=queryMysql("select * from lists where id=$str_id");
   if($result->num_rows>1)echo "<br>Какая-то странная ошибка - больше 1 строки статистики. Такого не может быть...";
   if($result->num_rows==0)echo "<br>Строка статистики с данным ID не найдена в БД";
   if($result->num_rows==1){
       //echo "<br>Строка статистики с данным ID=$str_id найдена. Ура!<br>";
       $statsRec=$result->fetch_assoc();
       $result=queryMysql("select * from list_headers where id=".$statsRec['list_id']);
       $headerRec=$result->fetch_assoc();
       foreach($statsRec as $pk=>$pv)echo "$pk: $pv<br>";
       echo "<br>list_header:<br><br>";
       foreach($headerRec as $pk=>$pv)echo "$pk: $pv<br>";
       echo "<br>";
       if($statsRec['section'])$query="select * from mf where section=".$statsRec['section']." and G1='".$statsRec['G1']."'";
       else $query="select * from mf where G1='".$statsRec['G1']."'";

       for($i=1;$i<=8;$i++)$query.=("<br> and token_str like '%".(substr($statsRec["token$i"],0,3)=="alt"?"":" ").str_replace('_','\_',str_replace("'","\'",$statsRec["token$i"]))." %'");

//       if(is_null($headerRec['aim2'])){
//           $query.=" <br> and not isnull(`".$headerRec['aim1']."`)";
//       }
//       else{
//           $query.=" <br> and (not isnull(`".$headerRec['aim1']."`) or  not isnull(`".$headerRec['aim2']."`))";
//       }
       $query.=" <br> and not isnull(`".$headerRec['aim_field']."`)";

       // добавлен (20201107) блое дополнительной проверки - нужно ли отражать данную строку в статистике данного листа
       //Модели, содержащие AUX=AM должны учитываться в листах, заголовок которых содержит параметр auxP3  только в том случае, если: значения параметров lvl34to46aux ll2'5to56aux лежат в диапазоне от 1 до 11 включительно
       //Модели, содержащие G1=EAM, AM, AM/DBM должны учитываться листах, заголовок которых не содержит параметр auxP3 только в том случае, если: lvl34to46 ll2'5to56 лежат в диапазоне от 1 до 11 включительно
       if($headerRec['param3']=='auxP3')$query.="<br> and (AUX!='AM' or (`lvl34to46aux` between 1 and 11 and `ll2'5to56aux` between 1 and 11))";
       if($headerRec['param3']!=='auxP3'&&in_array($headerRec['G1'],['EAM','AM','AM/DBM']))$query.="<br> and (`lvl34to46` between 1 and 11 and `ll2'5to56` between 1 and 11)";

       echo $query.";<br>";
       if($need_list){
           $result=queryMysql(str_replace("<br>"," ",$query));
           $list="";
           while($mfRec=$result->fetch_assoc())$list.=(",".$mfRec['id']);
           echo "<br> список ID(".$result->num_rows." строк): (".substr($list,1).")<br>";
           echo "<br> ";

       }
   }


}
if ($list_id){
    echo "<br>------------<br>Запрос: list_id=".$_POST['list_id']."<br><br>";
    $result=queryMysql("select * from list_headers where id=".$list_id);
    if($result->num_rows>1)echo "<br>Какая-то странная ошибка - больше 1 строки заголовка list_header. Такого не может быть...<br>";
    if($result->num_rows==0)echo "<br>Заголовок (lest_headers) с данным ID не найден в БД<br>";
    if($result->num_rows==1) {
        $headerRec=$result->fetch_assoc();
        $query="select * from mf where G1='".$headerRec['G1']."'";
        for($i=1;$i<=8;$i++)$query.=("<br> and token_str like '%".(substr($headerRec["param$i"],0,3)=="alt"?"":" ").str_replace("'","\'",$headerRec["param$i"])."\_%'");
//        if(is_null($headerRec['aim2'])){
//            $query.=" <br> and not isnull(`".$headerRec['aim1']."`)";
//        }
//        else{
//            $query.=" <br> and (not isnull(`".$headerRec['aim1']."`) or  not isnull(`".$headerRec['aim2']."`))";
//        }
        $query.=" <br> and not isnull(`".$headerRec['aim_field']."`)";
        // добавлен (20201107) блое дополнительной проверки - нужно ли отражать данную строку в статистике данного листа
        //Модели, содержащие AUX=AM должны учитываться в листах, заголовок которых содержит параметр auxP3  только в том случае, если: значения параметров lvl34to46aux ll2'5to56aux лежат в диапазоне от 1 до 11 включительно
        //Модели, содержащие G1=EAM, AM, AM/DBM должны учитываться листах, заголовок которых не содержит параметр auxP3 только в том случае, если: lvl34to46 ll2'5to56 лежат в диапазоне от 1 до 11 включительно
        if($headerRec['param3']=='auxP3')$query.="<br> and (AUX!='AM' or (`lvl34to46aux` between 1 and 11 and `ll2'5to56aux` between 1 and 11))";
        if($headerRec['param3']!=='auxP3'&&in_array($headerRec['G1'],['EAM','AM','AM/DBM']))$query.="<br> and (`lvl34to46` between 1 and 11 and `ll2'5to56` between 1 and 11)";

        echo $query.";<br>";
    }
}

echo "</body></html>";
?>