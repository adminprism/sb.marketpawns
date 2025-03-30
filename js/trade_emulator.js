// alert("Yo!!!");
// Параметры (настройка)
BAR_LIMIT = 1000;
Setups=[] // сюда помещаеим тексты сетапов, полученные при старте
penDown = false; // нажатие левой кнопки мыши
debugMode = false;
loaderInterval = 10;
showProgressInterval=1000; // с какой периодичностью обновляем прогресс
function loaderOn() {
  loaderSec = 0; //старт нового загрузчика
  isLoaderOn = true;
  $(".parent_wait").css({ display: "block" });
}
function loaderOff() {
  $(".parent_wait").css({ display: "none" });
  isLoaderOn = false;
  loaderSec = 0;
}
setInterval(showProgress, showProgressInterval);

setInterval(showLoaderInfo, loaderInterval);

function showLoaderInfo() {
  if (isLoaderOn) {
    loaderSec = loaderSec + loaderInterval / 1000;
    s = number_format(loaderSec, 2, ".", "");
    $("#loader-text").text("" + s + "");
  }
}
setupList=[]
function showSetup(ind){ // показ сетапа
    $("#setup_selection_info").html('<pre><i>--- setup #'+ind+' ---</i><br><br>'+JSON2html(Setups[ind])+"</pre>")
}
function selectSetups(x){ // показ сетапа
    if(x==1)$('.conditions1 input').prop('checked', true);
    else $('.conditions1 input').prop('checked', false);
}
function showGraph_close(){
    $(".show_graph").css("display","none")
}
function showGraph_open(num,type_){
    i=0;

    url="";
    for(v in reportFiles) {
        if(i==num){
            if(type_==1)url = reportFiles[v].replace('.json','_proc.jpg') // нужно показать график в %%
            else url = reportFiles[v].replace('.json','.jpg')
        }
        i+=1
    }
    $(".show_graph").css({"display":"block","background-image": "url('"+url+"')"})
}
reportFiles={} // сюда будем помещать список отчетов после вызова функции (ниже)

function calculatePlease(){ // запрашиваем PHP модуль calc_setups.php рассчитать торговлю по выбранным сетапам
    l=[]
    cbs=$('.conditions1 input') // список чекбоксов
    for(i in cbs){
        if($('#condition1_'+i).is(":checked"))l.push(i)
    }
    if(l.length==0)alert('Нужно выбрать хотя бы один сетап!')
    else{ // вызываем AJAX для расчета выбранных сетапов
        function showResults(answer){
            $("#debug").html("<pre>" + JSON2html(ResAJAX) + "</pre>")
            //$("#debug").css("display", "block");

            html_=""
            cnt_=0
            for(i in reportFiles){
                $tag_=i.split('|')[0]
                $info_=i.split('|')[1]
                html_+='<a class="file_link" href="'+reportFiles[i]+'"  target="_blank">'+$tag_+'</a>'
                //html_+='<a class="trade_graph" href="'+reportFiles[i].replace('json','jpg')+'"  target="_blank">'+"(график)"+'</a><br>'
                html_+="<p class=\"click_for_graph\" onclick=showGraph_open("+cnt_+",0)>"+$info_+"</p><p class=\"click_for_graph1\" onclick=showGraph_open("+cnt_+",1)>"+"&nbsp;&nbsp;&nbsp;&nbsp;(график в %%)"+"</p><br>"
                cnt_+=1
            }
            $("#report_files").html(html_)
            //$("#report_files").html("<pre>" + JSON2html(reportFiles) + "</pre>")
        }
        loaderOn(); //блокировка экрана, показ вращающегося лоадера
        setupIDs=""
        for(v of l)setupIDs+=(","+v)

        var request = $.ajax({
            url: url_,
            type: "POST",
            timeout: 0,
            data: {
                mode: "CALC",
                tool: $("#select-name").val(),
                setupIDs: setupIDs.substr(1,999)
            },
            dataType: "json",
        })
            .done(function (data, textStatus, jqXHR) {
                showProgress(true)
                loaderOff();

                ResAJAX = data;
                if (typeof ResAJAX['Errors'] ==='undefined' || ResAJAX['Errors'].length==0) {
                    reportFiles=ResAJAX['answer']['out_files'];
                    tmp= delete ResAJAX['answer']['out_files'];
                    $("#trade_emulator_errors").html("OK");
                    $("#trade_emulator_errors").css("display","none");
                    showResults(ResAJAX.answer)
                    setTimeout(() => {showProgress(true);}, 2000)
                } else {
                    $("#debug").html("<pre>" + JSON2html(ResAJAX) + "</pre>")
                    $("#debug").css("display", "block");
                    $("#trade_emulator_errors").html("<pre>" + JSON2html(ResAJAX.Errors) + "</pre>")
                    $("#trade_emulator_errors").css("display","block");
                    alert("Ошибка в calc_setups.php!")
                }
            })
            .fail(function (jqXHR, textStatus, errorThrown) {
                alert("error(2) - AJAX запрос");
                console.log("fail -  jqXHR: ");
                console.log(jqXHR);
                loaderOff();
            });
    }
}
function debug_on_off(event) {
    debugMode = !debugMode;
    if (debugMode) {
        $("#debug").css({ display: "block" });
    } else {
        $("#debug").css({ display: "none" });
    }
    if (event.preventDefault) event.preventDefault();
}
function showProgress(forced=false){ // запрос и показ текущего прогресса
   // return(0);
  if(isLoaderOn||forced) {
    var request = $.ajax({
      url: url_,
      type: "POST",
      timeout: 500,
      data: {
        mode: "PROGRESS",
      },
      dataType: "json",
    })
        .done(function (data, textStatus, jqXHR) {
          $("#progress_info").html(data.answer)
        })
        .fail(function (jqXHR, textStatus, errorThrown) {
          //$("#progress_info").html("Ошибка AJAX при получении статуса")
          //alert("error(2) - AJAX запрос");
          //console.log("fail -  jqXHR: ");
          //console.log(jqXHR);
        });
  }
}

$(document).ready(function () {
  penDown = false;
  // получаем список сетапов
  function setSetupsIDs(answer){
    $("#debug").html("<pre>"+JSON2html(answer)+"</pre>")

    loaderOff()
    if(answer['Errors'].length>0){
      alert("Ошибка получения списка сетапов!")
      $("#debug").css("display","block");
    }
    else{
      html_=""
      i=0
      for(a of answer['answer']){ // перебор всех полученных сетапов
        //alert(answer['answer'][ind_]['condition1'])
        setupList.push(a['condition1'])
        Setups.push(a)
        html_+='<div class="conditions1">'+
              '<input type="checkbox" value="None" id="condition1_'+i+'" name="condition1" checked/>'+
              '<label onmouseover="showSetup('+i+')" for="condition1_'+i+'" ><span class="conditions_span" data-tooltip="west" title="'+"123"+'">&nbsp;['+i+' '+a['trade type']+'] '+a['condition1']+'</span></label>'+
          '</div>'
        i+=1
      }
      $('#setup_selection_list').html(html_)



      $('.conditions_span').each(function(index,item){
        $(item).attr("title",item.innerText)
      });
      $("#progress_info").html("Получен список сетапов. Количество: "+i)

      html_=$("#select-name").html()
      for(a of answer['tools']){ // перебор всех имеющихся в БД инструментов - заподняем комбо-бокс выбора инструмента
          html_+='\n<option value="'+a+'" >'+a+'</option>'
      }
      $('#select-name').html(html_)

    }
  }

  loaderOn(); //блокировка экрана, показ вращающегося лоадера

  var request = $.ajax({
    url: url_,
    type: "POST",
    timeout: 60000,
    data: {
      mode: "LIST",
    },
    dataType: "json",
  })
      .done(function (data, textStatus, jqXHR) {
        ResAJAX = data;
        $(".show_graph").css({"width":ResAJAX['PIC_WIDTH'],"height":ResAJAX['PIC_HEIGHT']})
        setSetupsIDs(ResAJAX)
        showProgress(true) // после получения списка сетапов обновляем текущий статус
      })
      .fail(function (jqXHR, textStatus, errorThrown) {
        $("#progress_info").html("Ошибка AJAX при получении списка сетапов")
        alert("error(1) - AJAX запрос");
        console.log("fail -  jqXHR: ");
        console.log(jqXHR);
        loaderOff();
      });
});
