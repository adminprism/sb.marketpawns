arrClicks={};
function body_m_move(event){
    for(var id in arrClicks){
        if(arrClicks[id].down==1){
            var t=$('#'+id);
            var top=Number(t.css('top').replace('px',''));
            var left=Number(t.css('left').replace('px',''));
            var scroll=getScroll();
            var X=event.pageX-scroll.x-left;
            var Y=event.pageY-scroll.y-top;
            var dx=event.pageX-arrClicks[id].pageX;
            var dy=event.pageY-arrClicks[id].pageY;
            arrClicks[id].pageX=event.pageX;
            arrClicks[id].pageY=event.pageY;
             $(t).css({'top':(top+dy)+'px','left':(left+dx)+'px'});
        }
        if(arrClicks[id].down==2){
            var t=$('#'+id).children('.moving-box-content');
            if(t!==null&&t.length!==0&&event.pageX>0&&event.pageY>0){
                var w=Number(t.width());
                var h=Number(t.height());
                var dx=event.pageX-arrClicks[id].pageX;
                var dy=event.pageY-arrClicks[id].pageY;
                arrClicks[id].pageX=event.pageX;
                arrClicks[id].pageY=event.pageY;
                 $(t).width(w+dx);
                 $(t).height(h+dy);
             }     
        }
    }    
    
}
function body_m_up(event){
        var t=event.target;
        t=$(t).closest('.moving-box')
        if(t!==null&&t.length!==0&&event.pageX>0&&event.pageY>0){
            var id=t.attr('id');
            var top=Number(t.css('top').replace('px',''));
            var left=Number(t.css('left').replace('px',''));
            var scroll=getScroll();
            arrClicks[id].down=false;
            var X=event.pageX-scroll.x-left;
            var Y=event.pageY-scroll.y-top;
       //     $("#debug_info").html(" body_m_up> "+$(t).attr('id')+" ("+event.pageX+" "+event.pageY+") X:"+X+" Y:"+Y)
            if(X<16&&Y<16){
             //   alert(id);
                var tc=t.children('.moving-box-content').first();
                if(tc.css('display')==='none')tc.fadeIn(500);//.css({display:'block'});
                else tc.fadeOut(300);//.css({display:'none'});
            }

        }
}
function body_m_down(event){
        var t=event.target;
        t=$(t).closest('.moving-box')
        if(t!==null&&t.length!==0&&event.pageX>0&&event.pageY>0){
            var id=t.attr('id');
            var top=Number(t.css('top').replace('px',''));
            var left=Number(t.css('left').replace('px',''));
            var scroll=getScroll();
            var X=event.pageX-scroll.x-left;
            var Y=event.pageY-scroll.y-top;
                arrClicks[id]={};
                arrClicks[id].pageX=event.pageX;
                arrClicks[id].pageY=event.pageY;
            if(X>16&&Y<16)arrClicks[id].down=1;
                
            else if(X>t.width()-16&&Y>t.height()-16&&t.height()>25)arrClicks[id].down=2;
        }
}
function getScroll(w) {
	w = w || window;
	
	if(w.pageXOffset != null) {
		return {x:w.pageXOffset,y:w.pageYOffset}
	}
}

function show_model_for_key(){ // показывает модель или State по ключу (key) (m1-p977-1)

    //Algorithm_num,Errors,info,log_selected_bar,log,Models2,Models,FlatLog_Statistics,paramArr,States,debug_info
    var model_str=$('#popUpText').val();
    var tmpArr=model_str;
    var m_ind='NA';
    tmpArr=tmpArr.split("-");
    if(tmpArr.length<2){
        alert("Введите ключ (key) модели !");
        //$('#popUpText').focus();
        return;
    }
    if(typeof ResAJAX==='undefined'){
        alert("Не получен график !");
        $('#popUpText').focus();
        return;
    }
    if(typeof ResAJAX['Models']==='undefined'){
        alert("Модели не определены !");
        console.log(ResAJAX);
        $('#popUpText').focus();
        return;
    }
    if(tmpArr[0]==='m1')m_ind='Models';
    if(tmpArr[0]==='m2') {
        m_ind = 'Models2';
        if(typeof ResAJAX[m_ind]==='undefined'){
            alert("Модели второго алгоритма не определены !");
            $('#popUpText').focus();
            return;
        }
    }
    if(typeof ResAJAX[m_ind]==='undefined'){
        alert("Ключ модели некорректен !");
        $('#popUpText').focus();
        return;
    }

    if(typeof ResAJAX[m_ind][tmpArr[1]]==='undefined'){
        alert("Модель ("+model_str+") не определенa! m_ind="+m_ind+" tmpArr[1]="+tmpArr[1]);
        $('#popUpText').focus();
        return;
    }
    if(tmpArr.length>2 && (typeof ResAJAX[m_ind][tmpArr[1]][tmpArr[2]])!=='undefined'){ // есть детализация до номера модеди на опорной точке
        $("#debugPopUp-main").html("<pre>" + JSON2html(ResAJAX[m_ind][tmpArr[1]][tmpArr[2]], 0, "") + "</pre>");
    }
    else   $("#debugPopUp-main").html("<pre>" + JSON2html(ResAJAX[m_ind][tmpArr[1]], 0, "") + "</pre>");

}
function show_resAJAX_info(field_name){
    if(typeof ResAJAX==='undefined'){
        alert("Не получен график !");
        return;
    }
    if(typeof ResAJAX[field_name]==='undefined'){
        alert("Модели не определены !");
        return;
    }
    $("#debugPopUp-main").html("<pre>" + JSON2html(ResAJAX[field_name], 0, "") + "</pre>");
    return;
}