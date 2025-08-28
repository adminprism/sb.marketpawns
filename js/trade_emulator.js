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
  $("#calc_progress").css("display","block");
}
function loaderOff() {
  $("#calc_progress").css({ display: "none" });
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
    
    // Remove previous active highlighting
    $('.conditions1').removeClass('active-setup');
    
    // Add active highlighting to current setup
    $('#condition1_'+ind).parent('.conditions1').addClass('active-setup');
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
let isCalculationRunning = false;

function calculatePlease(){ // запрашиваем PHP модуль calc_setups.php рассчитать торговлю по выбранным сетапам
    l=[]
    cbs=$('.conditions1 input') // список чекбоксов
    for(i in cbs){
        if($('#condition1_'+i).is(":checked"))l.push(i)
    }
    // допускаем расчет, если нет выбранных базовых сетапов, но есть кастомные
    if(l.length==0 && (!Array.isArray(CustomSetups) || CustomSetups.length===0)) alert('You need to select at least one setup or add a custom setup!')
    else{ // вызываем AJAX для расчета выбранных сетапов (и/или кастомных)
        // Clear active setup highlighting when starting calculation
        $('.conditions1').removeClass('active-setup');
        $("#setup_selection_info").html('<p><i>Select a setup to view its details...</i></p>');
        
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
                html_+="<p class=\"click_for_graph\" onclick=showGraph_open("+cnt_+",0)>"+$info_+"</p><p class=\"click_for_graph1\" onclick=showGraph_open("+cnt_+",1)>"+"&nbsp;&nbsp;&nbsp;&nbsp;(chart in %%)"+"</p><br>"
                cnt_+=1
            }
            $("#report_files").html(html_)
            //$("#report_files").html("<pre>" + JSON2html(reportFiles) + "</pre>")
        }
        loaderOn(); //блокировка экрана, показ вращающегося лоадера
        // помечаем расчёт как запущенный (сохраняем флаг между перезагрузками)
        isCalculationRunning = true;
        try { localStorage.setItem('mp_calc_running','1'); } catch(e){}
        setupIDs=""
        for(v of l)setupIDs+=(","+v)

        // формируем payload
        var payload = {
            mode: "CALC",
            tool: $("#select-name").val(),
            setupIDs: setupIDs.substr(1,999)
        };
        if(Array.isArray(CustomSetups) && CustomSetups.length>0){ payload.custom_setups = JSON.stringify(CustomSetups); }

        var request = $.ajax({
            url: url_,
            type: "POST",
            timeout: 0,
            data: payload,
            dataType: "json",
        })
            .done(function (data, textStatus, jqXHR) {
                showProgress(true)
                loaderOff();
                isCalculationRunning = false; try { localStorage.removeItem('mp_calc_running'); } catch(e){}

                ResAJAX = data;
                if (typeof ResAJAX['Errors'] ==='undefined' || ResAJAX['Errors'].length==0) {
                    reportFiles=ResAJAX['answer']['out_files'];
                    tmp= delete ResAJAX['answer']['out_files'];
                    $("#trade_emulator_errors").html("OK");
                    $("#trade_emulator_errors").css("display","none");
                    showResults(ResAJAX.answer)
                    // финальный статус не должен повторно открывать оверлей
                    if(isLoaderOn){ setTimeout(() => {showProgress(true);}, 2000) }
                } else {
                    const errText = (Array.isArray(ResAJAX.Errors) && ResAJAX.Errors.length>0)
                        ? (''+ResAJAX.Errors[0])
                        : 'Unknown error';
                    $("#debug").html("<pre>" + JSON2html(ResAJAX) + "</pre>")
                    $("#debug").css("display", "block");
                    $("#trade_emulator_errors").html("<pre>" + JSON2html(ResAJAX.Errors) + "</pre>")
                    $("#trade_emulator_errors").css("display","block");
                    alert("Error in calc_setups.php: " + errText)
                    isCalculationRunning = false; try { localStorage.removeItem('mp_calc_running'); } catch(e){}
                }
            })
            .fail(function (jqXHR, textStatus, errorThrown) {
                alert("Error(2) - AJAX request");
                console.log("fail -  jqXHR: ");
                console.log(jqXHR);
                loaderOff();
                isCalculationRunning = false; try { localStorage.removeItem('mp_calc_running'); } catch(e){}
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
          // Текстовый статус
          $("#progress_info").html(data.answer)
          // Структурированный прогресс
          try{
            var p=data.progress||null;
            if(p){
              var inst=(p.instruments||{}), sets=(p.setups||{}), mods=(p.models||{});
              var instPct = (inst.total>0)? Math.floor((inst.done/inst.total)*100) : 0;
              var setsPct = (sets.total>0)? Math.floor((sets.done/sets.total)*100) : 0;
              var modsPct = (mods.total>0)? Math.floor((mods.done/mods.total)*100) : 0;
              var running = (inst.total>0 && inst.done<inst.total) || (sets.total>0 && sets.done<sets.total) || (mods.total>0 && mods.done<mods.total);
              if(running){
                $("#calc_progress").css("display","block");
                $(".parent_wait").css({ display: "none" });
              } else {
                // если расчёт не идёт — скрываем оверлей
                loaderOff();
              }
              $("#p_inst_text").text((inst.done||0)+"/"+(inst.total||0)+" ("+instPct+"%) "+(inst.current||''));
              $("#p_inst_bar").css("width", instPct+"%" );
              $("#p_setup_text").text((sets.done||0)+"/"+(sets.total||0)+" ("+setsPct+"%)");
              $("#p_setup_bar").css("width", setsPct+"%" );
              $("#p_setup_tag").text(sets.tag||'');
              $("#p_model_text").text((mods.done||0)+"/"+(mods.total||0)+" ("+modsPct+"%)");
              $("#p_model_bar").css("width", modsPct+"%" );
              $("#p_extra").text("Trades calculated: "+(p.trades_calculated||0));
            } else if(!isLoaderOn) {
              // нет валидного progress — при загрузке страницы просто ничего не показываем
              $("#calc_progress").css("display","none");
              // если сервер сообщил о завершении — сбросим локальный флаг
              if(data && data.done===true){ try { localStorage.removeItem('mp_calc_running'); } catch(e){} }
            }
          }catch(e){}
        })
        .fail(function (jqXHR, textStatus, errorThrown) {
          // silent
        });
  }
}

function cancelCalculation(){
  if(!confirm('Stop current calculation?')) return;
  $.ajax({
    url: url_,
    type: 'POST',
    dataType: 'json',
    data: { mode: 'CANCEL' },
    timeout: 0,
  }).always(function(){
    try{ $('#p_inst_text').text('Cancelled'); }catch(e){}
    try{ $('#calc_progress').css('display','none'); }catch(e){}
    alert('Calculation cancelled');
    try { localStorage.removeItem('mp_calc_running'); } catch(e){}
  });
}

// === Builder: state and helpers ===
let CustomSetups = [];
let baseSetupsCount = 0;

function syncSL(){
  const t = $('#b_sl_type').val();
  const mode = $('#b_sl_mode').val ? $('#b_sl_mode').val() : 'single';
  if(t==='t5'){
    $('#b_sl_val').val('t5');
    $('#b_sl_range_wrap').css('display','none');
    $('#b_sl_val').css('display','');
  }
  else {
    if(mode==='range') { $('#b_sl_range_wrap').css('display','grid'); $('#b_sl_val').css('display','none'); }
    else { $('#b_sl_range_wrap').css('display','none'); $('#b_sl_val').css('display',''); }
  }
  try { renderBuilderPreview(); } catch(e){}
}

function syncAim(){
  const m=$('#b_aim_mode').val();
  if(m==='range') { $('#b_aim_range_wrap').css('display','grid'); $('#b_aim_single').css('display','none'); }
  else { $('#b_aim_range_wrap').css('display','none'); $('#b_aim_single').css('display',''); }
  try { renderBuilderPreview(); } catch(e){}
}

function syncTrigger(n){
  const mode = $('#b_tr'+n+'_mode').val();
  if(mode==='range') { $('#b_tr'+n+'_range_wrap').css('display','grid'); $('#b_tr'+n+'_single').css('display','none'); }
  else { $('#b_tr'+n+'_range_wrap').css('display','none'); $('#b_tr'+n+'_single').css('display',''); }
  try { renderBuilderPreview(); } catch(e){}
}

function syncTrailing(n){
  if(n===1){
    const t=$('#b_trl1_type').val();
    if(t==='t5') $('#b_trl1_val').val('t5');
    else if(t==='AMrealP6') $('#b_trl1_val').val('AMrealP6');
    else if(!$('#b_trl1_val').val()||$('#b_trl1_val').val()==='t5'||$('#b_trl1_val').val()==='AMrealP6') $('#b_trl1_val').val('10%');
  } else {
    const t=$('#b_trl2_type').val();
    if(t==='t5') $('#b_trl2_val').val('t5'); else if(!$('#b_trl2_val').val()||$('#b_trl2_val').val()==='t5') $('#b_trl2_val').val('10%');
  }
  try { renderBuilderPreview(); } catch(e){}
}

function buildTradeType(){
  const tgt=$('#b_target').val();
  const v=$('#b_variant').val();
  return (tgt||'P6') + (v||'rev');
}

function buildCondition1(){
  const mode=$('input[name="condmode"]:checked').val();
  if(mode==='advanced') return $('#c_adv1').val()||'';
  let cond=[];
  const g1=$('#c_g1').val(); if(g1) cond.push('$G1=="'+g1+'"');
  const bad=$('#c_bad').val(); if(bad!=='' && bad!=null) cond.push('$bad=='+bad);
  const sec=$('#c_section_min').val(); if(sec) cond.push('$section>'+sec);
  const n1=$('#c_nn1').val(); if(n1) cond.push('$NN1_probability_1'+n1);
  const n2=$('#c_nn2').val(); if(n2) cond.push('$NN2_probability_1'+n2);
  const n3=$('#c_nn3').val(); if(n3) cond.push('$NN3_probability_1'+n3);
  return cond.length?cond.join(' && '):'';
}

function buildCondition2(){
  const mode=$('input[name="condmode"]:checked').val();
  if(mode==='advanced') return $('#c_adv2').val()||'';
  return '';
}

function toggleCondMode(){
  const mode=$('input[name="condmode"]:checked').val();
  if(mode==='advanced'){
    $('#cond_adv_wrap').show();
    $('#cond_simple_wrap').hide();
  } else {
    $('#cond_adv_wrap').hide();
    $('#cond_simple_wrap').show();
  }
  try { renderBuilderPreview(); } catch(e){}
}

function presetVariantDefaults(v){
  if(v==='rev'){
    $('#b_sl_type').val('percent'); $('#b_sl_val').val('-6%');
    $('#b_sl_mode').val('single'); $('#b_sl_range_wrap').css('display','none');
    $('#b_aim_mode').val('single'); $('#b_aim_single').val('30%'); syncAim();
    $('#b_trl1_type').val('AMrealP6'); $('#b_trl1_val').val('AMrealP6');
    $('#b_tr1_mode').val('single'); $('#b_tr1_single').val('15%'); syncTrigger(1);
    $('#b_tr2_mode').val('single'); $('#b_tr2_single').val(''); syncTrigger(2);
    $('#b_trl2_type').val('percent'); $('#b_trl2_val').val('');
  } else if(v==='reach'){
    $('#b_sl_type').val('t5'); syncSL();
    $('#b_sl_mode').val('single'); $('#b_sl_range_wrap').css('display','none');
    $('#b_aim_mode').val('single'); $('#b_aim_single').val('6%'); syncAim();
    $('#b_trl1_type').val('t5'); $('#b_trl1_val').val('t5');
    $('#b_tr1_mode').val('single'); $('#b_tr1_single').val('55%'); syncTrigger(1);
    $('#b_tr2_mode').val('single'); $('#b_tr2_single').val(''); syncTrigger(2);
    $('#b_trl2_type').val('percent'); $('#b_trl2_val').val('');
  } else if(v==='over'){
    $('#b_sl_type').val('percent'); $('#b_sl_val').val('10%');
    $('#b_sl_mode').val('range'); $('#b_sl_from').val('8%'); $('#b_sl_to').val('14%'); $('#b_sl_step').val('1%'); $('#b_sl_range_wrap').css('display','grid');
    $('#b_aim_mode').val('range'); $('#b_aim_from').val('28%'); $('#b_aim_to').val('48%'); $('#b_aim_step').val('4%'); syncAim();
    $('#b_trl1_type').val('percent'); $('#b_trl1_val').val('10%');
    $('#b_tr1_mode').val('range'); $('#b_tr1_from').val('12%'); $('#b_tr1_to').val('22%'); $('#b_tr1_step').val('2%'); syncTrigger(1);
    $('#b_tr2_mode').val('single'); $('#b_tr2_single').val(''); syncTrigger(2);
    $('#b_trl2_type').val('percent'); $('#b_trl2_val').val('');
  } else if(v==='disrupt'){
    $('#b_sl_type').val('percent'); $('#b_sl_val').val('15%');
    $('#b_sl_mode').val('range'); $('#b_sl_from').val('12%'); $('#b_sl_to').val('18%'); $('#b_sl_step').val('1%'); $('#b_sl_range_wrap').css('display','grid');
    $('#b_aim_mode').val('range'); $('#b_aim_from').val('-55%'); $('#b_aim_to').val('-35%'); $('#b_aim_step').val('5%'); syncAim();
    $('#b_tr1_mode').val('range'); $('#b_tr1_from').val('-28%'); $('#b_tr1_to').val('-16%'); $('#b_tr1_step').val('4%'); syncTrigger(1);
    $('#b_trl1_type').val('percent'); $('#b_trl1_val').val('10%');
    $('#b_tr2_mode').val('single'); $('#b_tr2_single').val(''); syncTrigger(2);
    $('#b_trl2_type').val('percent'); $('#b_trl2_val').val('');
  }
  try { syncSL(); } catch(e){}
}

function onVariantChange(){
  const v = $('#b_variant').val();
  if(v==='rev') $('#b_alttr1_wrap').show(); else $('#b_alttr1_wrap').hide();
  if(v==='over'){ $('#cond2_label').show(); $('#c_adv2').show(); } else { $('#cond2_label').hide(); $('#c_adv2').hide().val(''); }
  presetVariantDefaults(v);
  try { renderBuilderPreview(); } catch(e){}
}

function collectBuilderSetup(){
  const tradeType = buildTradeType();
  const cancel = ($('#b_cancel').val()||'85%');
  const actual = ($('#b_actual').val()||'200%');
  // InitStopLoss
  let initSL='';
  if($('#b_sl_type').val()==='t5') initSL='t5';
  else if($('#b_sl_mode').val && $('#b_sl_mode').val()==='range') initSL = ($('#b_sl_from').val()||'10%')+', '+($('#b_sl_to').val()||'20%')+', '+($('#b_sl_step').val()||'5%');
  else initSL = ($('#b_sl_val').val()||'-6%');
  // Aim1
  let aim1='';
  if($('#b_aim_mode').val()==='range') {
    let from=$('#b_aim_from').val()||'28%';
    let to=$('#b_aim_to').val()||'30%';
    const step=$('#b_aim_step').val()||'1%';
    if(from.indexOf('-')===0 && to.indexOf('-')===0){
      const f=parseFloat(from); const t=parseFloat(to);
      if(!isNaN(f) && !isNaN(t) && f < t){ const tmp=from; from=to; to=tmp; }
    }
    aim1 = from+', '+to+', '+step;
  }
  else aim1 = ($('#b_aim_single').val()||'30%');
  // Trigger1
  let tr1='';
  if($('#b_tr1_mode').val()==='range') {
    let from=$('#b_tr1_from').val()||'15%';
    let to=$('#b_tr1_to').val()||'20%';
    const step=$('#b_tr1_step').val()||'2%';
    if(from.indexOf('-')===0 && to.indexOf('-')===0){
      const f=parseFloat(from); const t=parseFloat(to);
      if(!isNaN(f) && !isNaN(t) && f < t){ const tmp=from; from=to; to=tmp; }
    }
    tr1 = from+', '+to+', '+step;
  }
  else tr1 = ($('#b_tr1_single').val()||'');
  // Trigger2
  let tr2='';
  if($('#b_tr2_mode').val()==='range') tr2 = ($('#b_tr2_from').val()||'')+', '+($('#b_tr2_to').val()||'')+', '+($('#b_tr2_step').val()||'');
  else tr2 = ($('#b_tr2_single').val()||'');
  // Trailing
  let trl1Type = $('#b_trl1_type').val();
  if(!tradeType.endsWith('rev') && trl1Type==='AMrealP6') trl1Type='percent';
  let trl1 = (trl1Type==='t5') ? 't5' : (trl1Type==='AMrealP6' ? 'AMrealP6' : ($('#b_trl1_val').val()||'10%'));
  let trl2 = ($('#b_trl2_type').val()==='t5') ? 't5' : ($('#b_trl2_val').val()||'');
  const alt1 = (tradeType.endsWith('rev') ? ($('#b_alttr1').val()||'') : '');
  const cond1 = buildCondition1();
  const cond2 = buildCondition2();
  return {
    'condition1': cond1,
    'condition2': cond2,
    'trade type': tradeType,
    'CancelLevel': cancel,
    'InitStopLoss': initSL,
    'Aim1': aim1,
    'Trigger1': tr1,
    'AlternateTrigger1': alt1,
    'Trailing1': trl1,
    'Trigger2': tr2,
    'Trailing2': trl2,
    'Actual': actual
  };
}

function renderBuilderPreview(){
  try{
    const s = collectBuilderSetup();
    const order = [
      'condition1','condition2','trade type','CancelLevel','InitStopLoss','Aim1',
      'Trigger1','AlternateTrigger1','Trailing1','Trigger2','Trailing2','Actual'
    ];
    const phpEscape = (val) => {
      const str = (val==null? '' : String(val));
      return str.replace(/\\/g,'\\\\').replace(/'/g,"\\'");
    };
    let lines = ['['];
    order.forEach((k, idx) => {
      if(typeof s[k] === 'undefined') return;
      const v = phpEscape(s[k]);
      lines.push("    '"+k+"' => '"+v+"',");
    });
    lines.push('],');
    const txt = lines.join('\n');
    $('#builder_preview').text(txt);
  }catch(e){ $('#builder_preview').text('[]'); }
}

function copyBuilderPreview(){
  try{
    const txt = $('#builder_preview').text();
    if(navigator.clipboard && navigator.clipboard.writeText){
      navigator.clipboard.writeText(txt).then(function(){ alert('Copied to clipboard'); }).catch(function(){ alert('Copy failed'); });
    } else {
      const ta=document.createElement('textarea');
      ta.value=txt;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
      alert('Copied to clipboard');
    }
  }catch(e){ alert('Copy failed'); }
}

function renderCustomSetupsList(){
  if(typeof CustomSetups==='undefined') return;
  let html='';
  CustomSetups.forEach((s, idx)=>{
    html += '<div class="session-card" style="display:flex; align-items:center; justify-content:space-between; gap:8px;">'
          + '<div style="font-size:12px;">['+idx+'] '+(s['trade type']||'')+' | '+(s['condition1']||'')+'</div>'
          + '<button class="delete-btn" onclick="deleteCustomSetup('+idx+')"><i class="fas fa-trash"></i></button>'
          + '</div>';
  });
  $('#custom_setups_list').html(html);
}

function deleteCustomSetup(i){
  if(i>=0 && i<CustomSetups.length){ CustomSetups.splice(i,1); renderCustomSetupsList(); }
}

// Удаление сохранённых пользовательских сетапов из общего списка доступных (при старте страницы)
function deleteSavedPreset(presetId){
  if(!confirm('Delete this preset from available setups?')) return;
  $.ajax({
    url: url_,
    type: 'POST',
    dataType: 'json',
    data: { mode: 'DELETE_PRESET', preset_id: presetId },
    success: function(res){
      if(res && res.answer==='OK'){
        alert('Preset deleted');
        location.reload();
      } else {
        alert('Failed to delete preset');
      }
    },
    error: function(){ alert('Failed to delete preset'); }
  });
}

function addCustomSetup(){
  const s = collectBuilderSetup();
  CustomSetups.push(s);
  renderCustomSetupsList();
  renderBuilderPreview();
  alert('Custom setup added to list');
}

function saveCustomSetups(){
  if(typeof CustomSetups==='undefined' || CustomSetups.length===0){ alert('No custom setups to save'); return; }
  $.ajax({
    url: url_,
    type: 'POST',
    dataType: 'json',
    data: { mode: 'SAVE_SETUPS', custom_setups: JSON.stringify(CustomSetups) },
    success: function(res){
      if(res && res.answer==='OK') alert('Presets saved');
      else alert('Error saving presets');
    },
    error: function(xhr){
      try { alert('Error saving presets: '+ (xhr.responseText||'')); }
      catch(e){ alert('Error saving presets'); }
    }
  });
}

$(document).ready(function () {
  penDown = false;
  // получаем список сетапов
  function setSetupsIDs(answer){
    $("#debug").html("<pre>"+JSON2html(answer)+"</pre>")

    loaderOff()
    if(answer['Errors'].length>0){
      alert("Error getting setups list!")
      $("#debug").css("display","block");
    }
    else{
      html_=""
      i=0
      for(a of answer['answer']){ // перебор всех полученных сетапов
        //alert(answer['answer'][ind_]['condition1'])
        setupList.push(a['condition1'])
        Setups.push(a)
        var extraBtn='';
        if(a['___preset_src']==='user'){
          var pid=(typeof a['___preset_id']!=='undefined')?a['___preset_id']:i;
          extraBtn='<button title="Delete preset" class="delete-btn" style="margin-left:6px;" onclick="deleteSavedPreset('+pid+'); event.stopPropagation(); return false;"><i class="fas fa-trash"></i></button>';
        }
        html_+='<div class="conditions1">'+
              '<input type="checkbox" value="None" id="condition1_'+i+'" name="condition1"/>'+
              '<label onmouseover="showSetup('+i+')" for="condition1_'+i+'" ><span class="conditions_span" data-tooltip="west" title="'+"123"+'">&nbsp;['+i+' '+a['trade type']+'] '+a['condition1']+'</span></label>'+ extraBtn +
          '</div>'
        i+=1
      }
      $('#setup_selection_list').html(html_)
      
      // Update setup count
      $('#setup_count').html(i + ' setups');

      // Глобальная галочка «All» (отметить/снять все)
      try {
        $('#setup_select_all').off('change').on('change', function(){
          const checked = $(this).is(':checked');
          if(checked) selectSetups(1); else selectSetups(0);
        });
      } catch(e){}

      $('.conditions_span').each(function(index,item){
        $(item).attr("title",item.innerText)
      });
      $("#progress_info").html("Setups list received. Count: "+i)

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
        // показываем прогресс только если ранее явно запускали расчёт
        let running=false; try { running = (localStorage.getItem('mp_calc_running')==='1'); } catch(e){}
        if(running) showProgress(true); else loaderOff();
        try { onVariantChange(); renderBuilderPreview(); renderCustomSetupsList(); } catch(e){}
      })
      .fail(function (jqXHR, textStatus, errorThrown) {
        $("#progress_info").html("AJAX error while getting setups list")
        alert("Error(1) - AJAX request");
        console.log("fail -  jqXHR: ");
        console.log(jqXHR);
        loaderOff();
      });
});
