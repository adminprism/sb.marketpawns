JSON2htmlStrCnt=0;
function JSON2html(json,iteration=0,text=""){
    var out,i;
    out= (text==="")?"":(text+"<br>");
    if((typeof iteration)==='undefined')iteration=0;
    if(iteration==0)JSON2htmlStrCnt=0;
    if((typeof json)!=='object')return(""+json);
    for(var p in json){
        if((typeof json[p])==='object'){
            for(i=0;i<iteration;i++)out+='  ';
            v=" :</b> <br>"
            if(json[p]==null)v=" :</b> null<br>"
            else if(json[p].length==0)v=" :</b> []<br>"
            out+="<b>"+p.fontcolor('green')+v+JSON2html(json[p],iteration+1);
            JSON2htmlStrCnt++;
        }
        else{
            for(i=0;i<iteration;i++)out+='  ';
            var ss=""+json[p];
            if(typeof json[p]==='string')out+="<b>"+p+" :</b> "+ss.fontcolor('red')+"<br>";
            else out+="<b>"+p+" :</b> "+ss.fontcolor('blue')+"<br>";
            JSON2htmlStrCnt++;
        }
    }
    return(out);
}
function get_date_str(d,vid,msec_needed){
    month_= String(d.getMonth()+1); if(month_.length==1)month_="0"+month_;
    day_=String(d.getDate()); if(day_.length==1)day_="0"+day_;
    hour_=String(d.getHours()); if(hour_.length==1)hour_="0"+hour_;
    min_=String(d.getMinutes()); if(min_.length==1)min_="0"+min_;
    sec_=String(d.getSeconds()); if(sec_.length==1)sec_="0"+sec_;
    if(vid==1)return(day_+"."+month_+"."+d.getFullYear()+" "+hour_+":"+min_+":"+sec_+((msec_needed==1)?"."+d.getMilliseconds():""));
    else return(d.getFullYear()+"-"+month_+"-"+day_+" "+hour_+":"+min_+":"+sec_+((msec_needed==1)?"."+d.getMilliseconds():""));
}
function timestampToDate(ts) {
    var d = new Date();
    d.setTime(ts);
    return (d);
}
function O(i) { return typeof i == 'object' ? i : document.getElementById(i) }
function S(i) { return O(i).style                                            }
function C(i) { return document.getElementsByClassName(i)  }
function line_ep(context,x1,y1,x2,y2,color)
{
    context.strokeStyle=color;
    context.beginPath();
    context.moveTo( x1,y1);
    context.lineTo( x2,y2);
    context.stroke();
    context.closePath();
}
function fillRect_ep(context,x1,y1,w,h,color){
    var oldStyle=context.fillStyle;
    context.fillStyle=color;
    context.fillRect(x1-0.5,y1,w,h);
    context.fillStyle=oldStyle;
}
// Функция для добавления обработчика событий
function addHandler(object, event, handler) {
    if (object.addEventListener) {
        object.addEventListener(event, handler, false);
    }
    else if (object.attachEvent) {
        object.attachEvent('on' + event, handler);
    }
    else alert("Обработчик не поддерживается");
}
/***
 number - исходное число
 decimals - количество знаков после разделителя
 dec_point - символ разделителя
 thousands_sep - разделитель тысячных
 ***/
function number_format(number, decimals, dec_point, thousands_sep) {
    number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
    var n = !isFinite(+number) ? 0 : +number,
        prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
        sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
        dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
        s = '',
        toFixedFix = function(n, prec) {
            var k = Math.pow(10, prec);
            return '' + (Math.round(n * k) / k)
                .toFixed(prec);
        };
    // Fix for IE parseFloat(0.55).toFixed(0) = 0;
    s = (prec ? toFixedFix(n, prec) : '' + Math.round(n))
        .split('.');
    if (s[0].length > 3) {
        s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
    }
    if ((s[1] || '')
        .length < prec) {
        s[1] = s[1] || '';
        s[1] += new Array(prec - s[1].length + 1)
            .join('0');
    }
    return s.join(dec);
}
        