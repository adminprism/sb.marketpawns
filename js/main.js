BAR_LIMIT = 1000;
ConsoleLoggingOn = false;
// * Service constant-array - names of the keys denoting the points of the model / Cлужебный константа-массив - наименование ключей, обозначающих точки модели
PointsList = [
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
  't5"',
  't3"',
  "A2Prev",
];

Model_colors = {
  inactive: {
    point: "rgba(150,150,150,0.5)",
    t1: "rgba(50,50,50,0.5)",
  },
  active: {
    point: "rgba(0,150,10,0.7)",
    "t3-": "rgba(255,0,0,0.7)",
    "t3'": "rgba(255,0,0,0.7)",
  },
  // IDprev_1: "rgba(0,0,0,0.6)",
  IDprevs: "rgb(23,33,255,0.5)",
  IDprev_0: "#4682B4",
  // IDprev_1: "#B0C4DE",
  IDprev_1: "rgba(255,0,0,0.7)",
  IDinner_1: "#96356F",
  // IDprev_2: "rgba(0,0,0,0.4)",
  // IDprev_2: "#DCDCDC",
  // IDprev_2: "#96356F",
  // IDinners: "rgb(23,33,255,0.5)",
  IDinners: "#B0C4DE",
  modelSize: "rgba(0,0,255,0.3)",
  lvl_am: "rgba(0,255,255,0.4)",
  lvl_brkd: "rgba(255,9,9,0.4)",
  lvl_appr: "rgb(23,33,255,0.4)",
  lvl_bump: "rgba(0,0,0,0.4)",
  lvl_lost: "rgba(0,0,0,0.4)",
  lvl_aim1: "rgb(255,233,9,0.7)",
  lvl_aim2: "rgb(255,233,9,0.7)",
  lvl_aim3: "rgb(255,233,9,0.7)",
  lvl_aim4: "rgb(255,233,9,0.7)",
};

Graph_settings = {
  // width: 1200, // Width of the canvas. Ширина холста
  // width: document.getElementById("canvas-wrapper").clientWidth + 50, // Width of the canvas / Ширина холста
  width: document.getElementById("canvas-wrapper").clientWidth, // Width of the canvas / Ширина холста
  height: 520, // Height of the canvas. Высотка холста
  left: 10.5, // Chart area  -  X-axis offset (from the left) / Область графика - отступ по оси Х (слева)
  top: 5.5, // Chart area -  Y-axis offset (from above)
  right: 86.5, // Chart area  -  X-axis offset (from the right)
  bottom: 22.5, // Chart area -  Y-axis offset  (from the bottom)
  rightSideBarWidth: 310, // ширина блока справа от графика +margin 10px слева и справа
  scale: {
    // размер баров для разного масштаба - ширина и расстояние между барами (ширина бара должна быть нечетным значением, что-бы фитиль был по центру
    0: { width: 17, step: 25 },
    1: { width: 15, step: 22 },
    2: { width: 13, step: 18 },
    3: { width: 11, step: 15 },
    4: { width: 9, step: 12 },
    5: { width: 7, step: 9 },
    6: { width: 5, step: 7 },
    7: { width: 3, step: 5 },
    8: { width: 3, step: 4 },
    9: { width: 3, step: 3 },
  },
  max_scale: 9,
  color: {
    // Colors - up body, down body, candles shadows
    down: "#f00",
    up: "#00f",
    downLine: "#f00",
    upLine: "#00f",
    fieldBorder: "#ddd",
  },
};
intervalList = ["1m", "5m", "10m", "15m", "30m", "1h", "1D", "1W"];
intervalSelected = 1; // индекс в intervalList - значение по умолчанию (при F5)
penDown = false; // Pressing the left mouse button
// pairList={  // для BINANCE - test
//     "BTCUSDT": "BTC_USDT",
//     "ETHUSDT": "ETH_USDT",
//     "LTCUSDT": "LTC_USDT",
//     "ETHBTC": "ETH_BTC",
//     "LTCBTC": "LTC_BTC",
// }

pairList = {
  // для FOREX Finam https://www.finam.ru
  EURUSD: "EUR/USD",
  //  JPYUSD: "JPY/USD",
  CADUSD: "CAD/USD",
  GBPUSD: "GBP/USD",
  CHFUSD: "CHF/USD",
  //    "PLNUSD": "PLNUSD",
  USDRUB: "USD/RUB",
  EURJPY: "EUR/JPY",
  //    "USDUAH": "USDUAH",
  //    "USDTRY": "USDTRY",
};

pairSelected = "EURUSD"; // Pairllist index - default value (at F5)
Data_settings = {
  n_bar: 0, // заполняется при получении данных - количество баров
  bar_n_min: 0, // заполняется при получении данных - количество минут в баре (интервал)
  cur_bar: 0, // текущий (правый) бар для показа
  activeBar: 0, // активный бар - для выделения модели, на бар t1 которой установили курсов (или еще каким-то способом выбрали)
  activeBarModelsNum: 0, // номер модели (J На активном баре? J) (начиная с 0) которую нужно показывать
  activeBarModelsNums: 0, // количество моделей на активном баре
  pointedBar: 0, // куда указывает курсор при движении мыши по графику
  scale: 7, // масштаб в сторону уменьщения баров : 0,1,2... > индекс для Graph_settings.scale
  max_v: 0, //максимальное значение цены на текущем графике
  min_v: 0, //минимальное значение цены на текущем графике
  X_right: 0, // координата X самого правого бара
  barsOnDesk: 0, // сколько помещается баров на экране
  offset: 0, // Переменная для горизонтальной проекрутки
};
Models = []; // массив моделей, получается AJAX (Расчет алгоритма_1)
Models2 = []; // массив моделей, получается AJAX (Расчет алгоритма_2)
whatIsCalculatingNow = 0; // 0=расчет в настоящий момент не запущен, 1 или 2 = запущен расчет, соответсвенно I или II алгоритма по AJAX - ждем ответа
algorithmCalculated = 0; // устанавливается при получении ответа по AJAX - номер алгоритма, который вернул результат
//переключатель - "показывть окно debug?"
debugMode = false;
createCanvas();
isLoaderOn = false; // нужно ли показывать лоадер в настоящий момент (ждем ответа от AJAX и блокируем экран)
loaderSec = 0; //время, прошедшее с начала загрузки - точность до 0.1 сек.
loaderInterval = 10;
Alg_num = 0; // номер алгоритма, который отработал 0-не было запуска, 1 или 2
Alg2Show = 0; // номер алгоритма, модели которого нужно показывать
//loaderOn();
//S(graph_obj).border = '1px solid black'
function setAlg_num(num) {
  // вызывается, когда отработал первый или второй аглоритм, естанавливает глобальную переменную Alg_num и пр.дейстия
  //if(num==Alg_num)return;
  switch (num) {
    case 1: // отработал первый алгоритм
      Alg_num = 1;
      Alg2Show = 1;
      if (Models.length > 0)
        $(".next-prev-model-btns").css({ display: "inline-block" });
      else $(".next-prev-model-btns").css({ display: "none" });
      break;
    case 2: // отработал второй алгоритм
      Alg_num = 2;
      if (Models2.length > 0) {
        Alg2Show = 2;
        $("#showAlg2").click();
        $(".next-prev-model-btns").css({ display: "inline-block" });
      } else {
        Alg2Show = 1;
        if (Models.length > 0)
          $(".next-prev-model-btns").css({ display: "inline-block" });
        else $(".next-prev-model-btns").css({ display: "none" });
      }
      break;
    default:
      Alg_num = 0;
      break;
  }
  if (Models.length > 0 && Models2.length > 0) {
    $("#showSwitch").css({ display: "inline-block" });
    $("#showLevels").css({ display: "inline-block" });
  } else {
    $("#showSwitch").css({ display: "none" });
    $("#showLevels").css({ display: "none" });
  }
}
function createCanvas() {
  // * Creates a canvas
  // Graph_settings.width =
  //   document.body.clientWidth - Graph_settings.rightSideBarWidth - 55;
  // if (Graph_settings.width < 640) Graph_settings.width = 640;
  $("#canvas-wrapper").html(
    "<canvas id='graph' width='" +
      Graph_settings.width +
      "' height='" +
      Graph_settings.height +
      "'></canvas><div id=\"graph-cover\"></div><div id='line-g'></div><div id='line-v'>" +
      '</div><div id="last-price"></div><div id="point-price"></div><div id="from-to-text"></div><div id="candle-info"></div>'
  );
}
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
setInterval(showLoaderInfo, loaderInterval);

function showLoaderInfo() {
  if (isLoaderOn) {
    loaderSec = loaderSec + loaderInterval / 1000;
    s = number_format(loaderSec, 2, ".", "");
    $("#loader-text").text("" + s + "");
  }
}
$(document).ready(function () {
  let selected = $("#source-switch input[type='radio']:checked"); // Switch the data source
  if (ConsoleLoggingOn) console.log(selected[0].id);

  if (selected[0].id == "rb-forex") changeSource("forex");
  if (selected[0].id == "rb-saves") changeSource("saves");
  if (selected[0].id == "rb-mysql") changeSource("mysql");
  else changeSource("saves");
  let h = "";
  for (t in intervalList)
    h +=
      '<option value="' +
      intervalList[t] +
      '"' +
      (t == intervalSelected ? "selected" : "") +
      ">" +
      intervalList[t] +
      "</option>\n";
  $("#select-interval").html(h);
  h = "";
  for (t in pairList)
    h +=
      '<option value="' +
      t +
      '"' +
      (t == pairSelected ? " selected" : "") +
      ">" +
      pairList[t] +
      "</option>\n";
  $("#select-pair").html(h);
  $("#canvas-wrapper").css({ height: Graph_settings.height });
  graph_obj = O("graph");
  graph_context = graph_obj.getContext("2d"); // function O(i) { return typeof i == 'object' ? i : document.getElementById(i)
  S(graph_obj).background = "azure";

  $("#line-g").css({
    width: Graph_settings.width - Graph_settings.left - Graph_settings.right,
    left: Graph_settings.left,
  });
  $("#line-v").css({
    height: Graph_settings.height - Graph_settings.top - Graph_settings.bottom,
    top: Graph_settings.top,
  });
  $("#right-block").css({ height: Graph_settings.height });
  $("#candle-info").css({
    top: Graph_settings.top - 4,
    left: Graph_settings.left + 1,
  });
  graph_position = $("#graph").offset(); // graph - is a canvas ID in the index.php file

  if (Data_settings.n_bar > 0) drawGraph();

  penDown = false;
  wasMoved = false; // * Добавлено
  $("#graph-cover").mousemove(function (event) {
    if (typeof Data === "undefined") return;
    if (typeof Data[0] === "undefined") {
      $("#line-g").css({ top: -1000 });
      $("#line-v").css({ left: -1000 });
      $("#last-price").css({ top: -1000 });
      $("#point-price").css({ top: -1000 });
      $("#candle-info").text("");
      $("#from-to-text").css({ left: -1000 });
      return;
    }
    //console.log(Data[0]); alert("mouse move");
    var price_, bar_ind, d1, d2, ftt, xx, dx, old_cb;
    if (Data_settings["n_bar"] > 0) {
      graph_position = $("#graph").offset(); // <canvas id='graph'
      var xpos = event.pageX - graph_position.left;
      var ypos = event.pageY - graph_position.top;
      if (penDown == false) {
        // Moving the guiding liens on the chart
        if (
          ypos >= Graph_settings.top &&
          ypos <= Graph_settings.height - Graph_settings.bottom &&
          xpos >= Graph_settings.left &&
          // xpos <= Graph_settings.width - Graph_settings.right
          xpos <= Graph_settings.width
        ) {
          // Устанавливает позицию горизонтальной линии (элемент с id "line-g"), смещая её на ypos + 0.5 пикселей сверху.
          $("#line-g").css({ top: ypos + 0.5 });
          // Вычисляет значение цены на основе позиции курсора (ypos) с учетом настроек графика. Формула преобразует координату Y в соответствующее значение цены в пределах между min_v и max_v.
          price_ =
            Data_settings.max_v -
            ((ypos - Graph_settings.top) /
              (Graph_settings.height -
                Graph_settings.top -
                Graph_settings.bottom)) *
              (Data_settings.max_v - Data_settings.min_v);
          // Ограничивает длину строки цены до 10 символов и отображает её в элементе с id "point-price".
          price_ = ("" + price_).substr(0, 10);
          // Устанавливает позицию элемента с ценой на графике.
          $("#point-price").text(price_);
          $("#point-price").css({
            left: Graph_settings.width - Graph_settings.right,
            top: ypos - 8,
          });
          // Вычисляет номер бара на основе позиции курсора по оси X.
          let graphX = xpos - Graph_settings.left;
          let totalWidth = Graph_settings.width - Graph_settings.left - Graph_settings.right;
          let barWidth = Graph_settings.scale[Data_settings.scale].step;
          let visibleBars = Math.floor(totalWidth / barWidth);
          
          // Calculate which bar was clicked relative to the right edge
          // Use Math.round to get the closest bar to the cursor
          let barOffset = Math.round((Data_settings.X_right - graphX) / barWidth - 0.5);
          
          // Ensure barOffset is within valid range
          barOffset = Math.max(0, Math.min(barOffset, visibleBars));
          
          // Calculate the actual bar index that was clicked
          Data_settings.pointedBar = Math.min(
            Math.max(0, Data_settings.cur_bar - barOffset),
            Data_settings.n_bar - 1
          );
          
          // Update nbar for visual feedback
          nbar = barOffset;
          
          if (ConsoleLoggingOn) {
            console.log("Graph click details:", {
              graphX,
              totalWidth,
              barWidth,
              visibleBars,
              barOffset,
              pointedBar: Data_settings.pointedBar,
              cur_bar: Data_settings.cur_bar,
              X_right: Data_settings.X_right,
              xpos,
              step: Graph_settings.scale[Data_settings.scale].step
            });
          }

          // Убеждается, что номер бара не превышает допустимое значение.
          if (nbar > Data_settings.cur_bar) nbar = Data_settings.cur_bar;
          // Устанавливает позицию вертикальной линии на графике.
          $("#line-v").css({
            left:
              Data_settings.X_right -
              nbar * Graph_settings.scale[Data_settings.scale].step -
              0.5,
          });

          // Проверка на наличие open_time и close_time
          index = Data_settings.cur_bar - nbar;

          if (!Data[index]) {
            console.error(`Ошибка: Data[${index}] не найдено`, Data);
          } else {
            if (!("open_time" in Data[index])) {
              console.error(
                `Ошибка: open_time отсутствует в Data[${index}]`,
                Data[index]
              );
            } else {
              d1 = get_date_str(timestampToDate(Data[index].open_time), 0);
            }

            if (!("close_time" in Data[index])) {
              console.error(
                `Ошибка: close_time отсутствует в Data[${index}]`,
                Data[index]
              );
            } else {
              d2 = get_date_str(timestampToDate(Data[index].close_time + 1), 0);
            }
          }

          d1 = get_date_str(
            timestampToDate(Data[Data_settings.cur_bar - nbar].open_time),
            0
          );
          d2 = get_date_str(
            timestampToDate(Data[Data_settings.cur_bar - nbar].close_time + 1),
            0
          );
          // ftt=d1.substr(0,10)+" ["+d1.substr(11,5)+"-"+d2.substr(11,5)+"]";

          ftt = d1.substr(0, 16) + " ~ " + d2.substr(0, 16);
          /* The code is using jQuery to select an element with the id "bar-info" and setting its text content to
          a string that includes the values of variables xpos, ypos, nbar, Data_settings["cur_bar"], and ftt.
          The string is formatted to display these values in a specific way. */
          $("#bar-info").text(
            "X: " +
              xpos +
              " Y: " +
              ypos +
              " nbar: " +
              nbar +
              " cur_bar: " +
              (Data_settings["cur_bar"] - nbar) +
              " ftt: " +
              ftt
          );
          xx = xpos - 145;
          if (xx < Graph_settings.left) xx = Graph_settings.left;
          if (xx > Graph_settings.width - Graph_settings.right - 290)
            xx = Graph_settings.width - Graph_settings.right - 290;
          $("#from-to-text").text(ftt);
          $("#from-to-text").css({ left: xx, top: Graph_settings.height - 17 });
          $("#candle-info").text(
            "" +
              (Data_settings["cur_bar"] - nbar) +
              " (" +
              (Data_settings.cur_bar - nbar - Data_settings.activeBar) +
              ")" +
              " Open: " +
              ("" + Data[Data_settings.cur_bar - nbar].open).substr(0, 10) +
              " " +
              "High: " +
              ("" + Data[Data_settings.cur_bar - nbar].high).substr(0, 10) +
              " " +
              "Low: " +
              ("" + Data[Data_settings.cur_bar - nbar].low).substr(0, 10) +
              " " +
              "Close: " +
              ("" + Data[Data_settings.cur_bar - nbar].close).substr(0, 10) +
              " "
          );
        }
      } else {
        // нажата левая кнопка мыши - двигаем график
        dx = Math.round(
          (xpos - xDown) / Graph_settings.scale[Data_settings.scale].step
        );
        $("#bar-info").text(
          "dx: " +
            dx +
            " cur_bar: " +
            Data_settings.cur_bar +
            " cur_barDown: " +
            cur_barDown +
            " n_bar: " +
            Data_settings.n_bar
        );
        old_cb = Data_settings.cur_bar;
        Data_settings.cur_bar = cur_barDown - dx;
        if (Data_settings.cur_bar < Data.barsOnDesk - 1)
          Data_settings.cur_bar = Data.barsOnDesk - 1;
        if (Data_settings.cur_bar >= Data_settings.n_bar)
          Data_settings.cur_bar = Data_settings.n_bar - 1;
        if (old_cb !== Data_settings.cur_bar) {
          wasMoved = true;
          drawGraph();
          updateScrollBar(); // * Обновляем scroll-bar при перемещении графика
        }
      }
    }
  });
  $("#graph-cover").click(function (event) {
    if (Data_settings["n_bar"] > 0 && !(penDown && wasMoved)) {
      //alert("click");
      let old_activBar = Data_settings.activeBar;
      Data_settings.activeBar = Data_settings.pointedBar;

      if (ConsoleLoggingOn)
        console.log(
          `Clicked bar: old_activBar=${old_activBar}, new activeBar=${Data_settings.activeBar}`
        );

      if (Data_settings.activeBar !== old_activBar) {
        $("#active-bar span").text(Data_settings.activeBar);
        // есть ли модели у данного бара
        Data_settings["activeBarModelsNum"] = 0;
        Data_settings["activeBarModelsNums"] = 0;
        if (
          typeof Models[Data_settings.activeBar] !== "undefined" &&
          Alg2Show == 1
        )
          Data_settings["activeBarModelsNums"] =
            Models[Data_settings.activeBar].length;
        if (
          typeof Models2[Data_settings.activeBar] !== "undefined" &&
          Alg2Show == 2
        )
          Data_settings["activeBarModelsNums"] =
            Models2[Data_settings.activeBar].length;
        // $('#switch-model-btn').prop("disabled",(Data_settings['activeBarModelsNums']<2));
        drawGraph();
      } else {
        // повторный клик на активный бар
        if (Data_settings["activeBarModelsNums"] > 1) {
          Data_settings["activeBarModelsNum"]++;
          if (
            Data_settings["activeBarModelsNum"] >=
            Data_settings["activeBarModelsNums"]
          )
            Data_settings["activeBarModelsNum"] = 0;
          drawGraph();
        }
      }
      $("#model-info").html("<hr>" + modelInfo());
      $("#popUpText").val(getModelNumString());
    }
  });
  $("#graph-cover").mousedown(function (event) {
    //alert("down");
    penDown = true;
    wasMoved = false;
    xDown = event.pageX; // запоминаем, где нажали кнопку на графике
    yDown = event.pageY - graph_position.top;
    cur_barDown = Data_settings.cur_bar;
  });
  $("#graph-cover").mouseup(function (event) {
    setTimeout(function () {
      penDown = false;
    }, 10);
  });
  // Добавляем обработчики колесика мыши для разных браузеров
  addHandler(window, "DOMMouseScroll", wheel);
  addHandler(window, "mousewheel", wheel);
  addHandler(document, "mousewheel", wheel);
  //  addHandler(graph_obj, 'DOMMouseScroll', wheel);
  //  addHandler(graph_obj, 'mousewheel', wheel);

  // * Обновление для скроллинга

  // Обработчики перетаскивания scrollBar
  let isScrollBarDragging = false;
  $("#scroll-bar").mousedown(function (event) {
    event.preventDefault();
    isScrollBarDragging = true;
    xDownScrollBar = event.pageX - graph_position.left; // запоминаем, где нажали кнопку на scrollBar
  });

  $(document).mousemove(function (event) {
    if (isScrollBarDragging) {
      const xpos = event.pageX - graph_position.left;

      // Ограничиваем xpos в пределах области скролл-бара
      const scrollBarWidth = $("#scroll-bar").width();
      const minX = 0;
      const maxX = scrollBarWidth;
      const clampedXpos = Math.max(minX, Math.min(xpos, maxX));

      // Рассчитываем пропорциональное изменение cur_bar
      const scrollValue = (clampedXpos / scrollBarWidth) * 100; // Процентное изменение скролл-бара
      const maxScroll = Data_settings.n_bar - Data.barsOnDesk; // Максимальное количество баров для прокрутки
      const newCurBar =
        Math.round((scrollValue / 100) * maxScroll) + (Data.barsOnDesk - 1);

      // Обновляем cur_bar
      Data_settings.cur_bar = Math.max(
        0,
        Math.min(newCurBar, Data_settings.n_bar - 1)
      );

      // Перерисовываем график и обновляем скролл-бар
      drawGraph();
      updateScrollBar();
    }
  });

  $(document).mouseup(function (event) {
    if (isScrollBarDragging) {
      isScrollBarDragging = false;
    }
  });

  function handleScrollBarChange(event) {
    const scrollBar = document.getElementById("scroll-bar");

    if (!scrollBar) {
      console.error("Элемент scroll-bar не найден в DOM.");
      return;
    }

    const scrollValue = parseFloat(event.target.value); // Значение scroll-bar

    // Рассчитываем cur_bar на основе значения scroll-bar
    const maxScroll = Data_settings.n_bar - Data.barsOnDesk;
    const newCurBar = Math.round(scrollValue) + (Data.barsOnDesk - 1);

    // Ограничиваем cur_bar в допустимых пределах
    Data_settings.cur_bar = Math.max(
      0,
      Math.min(newCurBar, Data_settings.n_bar - 1)
    );

    if (ConsoleLoggingOn)
      console.log(
        `handleScrollBarChange: scrollValue=${scrollValue}, newCurBar=${newCurBar}, cur_bar=${Data_settings.cur_bar}`
      );

    // Перерисовываем график
    drawGraph();
  }

  // Эта функция корректирует значение Data_settings.cur_bar в зависимости от перемещения мыши при перетаскивании scrollBar. Она также учитывает ограничения на минимальное и максимальное значения cur_bar.
  function updateCurBarFromScrollBar(dx) {
    if (Data_settings.n_bar <= Data.barsOnDesk) return;

    const maxScroll = Data_settings.n_bar - Data.barsOnDesk;
    // Рассчитываем новое положение cur_bar
    let newCurBar = Data_settings.cur_bar + dx;

    // Ограничиваем newCurBar в пределах от (barsOnDesk - 1) до (n_bar - 1)
    newCurBar = Math.max(
      Data.barsOnDesk - 1,
      Math.min(newCurBar, Data_settings.n_bar - 1)
    );

    Data_settings.cur_bar = newCurBar;
  }
});

function updateScrollBar() {
  // Если доступен модуль ScrollHandler, используем его
  if (window.ScrollHandler && window.ScrollHandler.initialized) {
    window.ScrollHandler.update({
      curBar: Data_settings.cur_bar,
      totalBars: Data_settings.n_bar,
      visibleBars: Data.barsOnDesk
    });
    return;
  }
  
  // Код ниже выполняется только если ScrollHandler недоступен (запасной вариант)
  const scrollBar = document.getElementById("scroll-bar");

  if (!scrollBar) {
    console.error("Элемент scroll-bar не найден в DOM.");
    return;
  }

  // Если количество баров меньше или равно количеству баров на экране, scroll-bar не нужен
  if (Data_settings.n_bar <= Data.barsOnDesk) {
    // Если все бары видны, scroll-bar на начальной позиции
    scrollBar.min = 0;
    scrollBar.max = 0;
    scrollBar.value = 0;
    return;
  }

  // Рассчитываем максимальное значение для scroll-bar
  const maxScroll = Data_settings.n_bar - Data.barsOnDesk;
  scrollBar.min = 0;
  scrollBar.max = maxScroll;

  // Рассчитываем значение scroll-bar
  const scrollValue = Data_settings.cur_bar - (Data.barsOnDesk - 1);

  // Устанавливаем значение scroll-bar
  scrollBar.value = scrollValue;
  if (ConsoleLoggingOn)
    console.log(
      `updateScrollBar: cur_bar=${Data_settings.cur_bar}, scrollValue=${scrollValue}, maxScroll=${maxScroll}`
    );
}

function getModelNumString() {
  // возврящаем номер модели в виде m1-p800-0
  var modelNum4popUp = Alg2Show == 2 ? "m2-p" : "m1-p";
  let models_ = Alg2Show == 2 ? Models2 : Models;
  if (typeof models_[Data_settings["activeBar"]] !== "undefined") {
    modelNum4popUp +=
      Data_settings["activeBar"] + "-" + Data_settings["activeBarModelsNum"];
    return modelNum4popUp;
  } else return "";
}
function modelInfo() {
  // J инфа по модели, в данный момент отобржается под кнопкой "Расчет модели по Алг. 1"
  // alert("!");
  let models_ = Models;
  if (Alg2Show == 2) models_ = Models2;
  if (typeof models_[Data_settings["activeBar"]] !== "undefined") {
    let df =
      typeof models_[Data_settings["activeBar"]][
        Data_settings["activeBarModelsNum"]
      ]["draw_flag"] !== "undefined"
        ? ", draw_flag"
        : "";
    $("#switch-model-btn").prop(
      "disabled",
      Data_settings["activeBarModelsNums"] < 2
    ); // J кнопка switch-model-btn в положении выключена, если количество моделей на активном баре меньше 2
    let t =
      "<strong>Model: " +
      (Data_settings["activeBarModelsNum"] + 1) + // J порядковый номер модели на этом баре т.1
      " / " +
      Data_settings["activeBarModelsNums"] + // J сколько всего моделейна этом баре т.1
      "</strong> <span class='model-info-v'>(id: " +
      models_[Data_settings["activeBar"]][Data_settings["activeBarModelsNum"]][
        "id"
      ] + // J id отображаемой модели
      ", " +
      models_[Data_settings["activeBar"]][Data_settings["activeBarModelsNum"]][
        "v"
      ] + // J т.1 - хай или лоу
      ", split:" +
      models_[Data_settings["activeBar"]][Data_settings["activeBarModelsNum"]][
        "split"
      ] +
      df +
      ")</span><br>";
    for (let a in models_[Data_settings["activeBar"]][
      Data_settings["activeBarModelsNum"]
    ]["status"]) {
      t += "<span class='model-info-status'>" + a + "</span><br>"; // Перечисление всех статусов циклом
    }
    //        t+=Models[Data_settings['activeBar']][Data_settings['activeBarModelsNum']]['v']+"<br>";
    for (let a in models_[Data_settings["activeBar"]][
      Data_settings["activeBarModelsNum"]
    ]) {
      if (a == "2" || a == "3" || a.substr(0, 1) === "t")
        t +=
          a +
          ": " +
          models_[Data_settings["activeBar"]][
            Data_settings["activeBarModelsNum"]
          ][a] +
          " ";
    }
    t += "<div class='model-info-div'>";
    for (let p in models_[Data_settings["activeBar"]][
      Data_settings["activeBarModelsNum"]
    ]["param"]) {
      t +=
        "<span class='model-info-param-name'>" +
        p +
        ":</span><span class='model-info-param-val'>" +
        models_[Data_settings["activeBar"]][
          Data_settings["activeBarModelsNum"]
        ]["param"][p] +
        "</span><br>";
    }
    t += "</div>";
    return t;
  } else {
    $("#switch-model-btn").prop("disabled", true);
    //$('.next-prev-model-btns').css("display","none");
    return "";
  }
}

function get_fragment(type) {
  ResAJAX = false;
  $(".next-prev-model-btns").css("display", "none");

  $("body").css({ cursor: "wait" });
  $("#get-data-btn3").css({ cursor: "wait" });
  setAlg_num(0);
  Alg2Show = 0;
  $("#model-info").html("");
  var nBars_ = $("#nBars4get_fragment").val();
  nBars_ = Number(nBars_);
  //alert("1) " + nBars_+typeof nBars_);
  if (typeof nBars_ !== "number" || nBars_ == 0) nBars_ = BAR_LIMIT;
  var dandt_ = $("#lastBar4get_fragment").val();
  var modelId_ = $("#modelId4get_fragment").val();

  let post_data = {
    InstrumentName: $("#select-name").val(),
    numBars: nBars_,
    lastBarTime: dandt_,
    modelId: modelId_,
  };
  let startTime = new Date(); // J Creates a new Date object.
  loaderOn();
  var request = $.ajax({
    url: "get_fragment.php",
    type: "POST",
    timeout: 10000, //
    data: post_data,
    dataType: "json",
  })
    .done(function (data, textStatus, jqXHR) {
      $("#info-timing").text("" + (new Date() - startTime) / 1000 + " сек.");
      if (ConsoleLoggingOn) console.log(data);
      parse_data_from_db(data);
      $("body").css({ cursor: "default" });
      $("#get-data-btn3").css({ cursor: "default" });
      loaderOff();
      if (ConsoleLoggingOn) console.log("!!! request.statusText :");
      if (ConsoleLoggingOn) console.log(request);
    })
    .fail(function (jqXHR, textStatus, errorThrown) {
      $("body").css({ cursor: "default" });
      $("#get-data-btn3").css({ cursor: "default" });
      $("#info-timing").text("" + (new Date() - startTime) / 1000 + " сек.");
      loaderOff();
      alert("error - AJAX запрос");
      if (ConsoleLoggingOn) console.log("fail -  jqXHR: ");
      if (ConsoleLoggingOn) console.log(jqXHR);
    });
}

function parse_data_from_db(data) {
  var el, ot, ct;
  //modelInfo();
  if (!(typeof data["Error"] === "undefined")) {
    alert("Ошибка: " + data["Error"]);
    $("#last-price").css({ top: -1000 });
    if (ConsoleLoggingOn) console.log(data);
    return false;
  }
  Data_settings.activeBar = Data_settings.pointedBar = 0;
  $("#active-bar span").text(Data_settings.activeBar);

  Data = data["Chart"];

  if (Data.length > 500) data["Chart"] = "deleted :" + data["Chart"].length;
  $("#debug").html("<pre>" + JSON2html(data, 0, "data:") + "</pre>");
  $("#info-log").html("(" + JSON2htmlStrCnt + " стр.)");

  Data_settings.n_bar = Data.length;
  if (Data_settings.n_bar < 100) {
    alert("Ошибка!\n недостаточное количество баров: " + Data_settings.n_bar);
    return false;
  }
  Data_settings.cur_bar = Data_settings.n_bar - 1;

  Models = [];
  Models2 = [];
  ResAJAX = data;
  if (typeof data["Models"] !== "undefined")
    for (let model_ind in data["Models"]) {
      // для  каждой модели создаёт переменную, model_ind - это счётчик
      ind = Number(model_ind.substr(1, 100)); // берёт первые 100 цифр номера бара каждой модели
      Models[ind] = data["Models"][model_ind]; // создает в Models подмассив [ind] номеров баров каждой модели (max - 100 цифр в номере)
    }
  if (typeof data["Models2"] !== "undefined")
    for (let model_ind in data["Models2"]) {
      ind = Number(model_ind.substr(1, 100));
      Models2[ind] = data["Models2"][model_ind];
    }

  algorithmCalculated = 2;
  setAlg_num(2);
  Alg2Show = 2;
  if (ConsoleLoggingOn) console.log(Models);
  if (ConsoleLoggingOn) console.log(Models2);

  if (ResAJAX["info"]["modelId"] !== "undefined") {
    //запрашивали модель по id
    let modelId = Number(ResAJAX["info"]["modelId"]);
    let AlgNum = Number(ResAJAX["info"]["AlgNum"]);
    let Models_ = AlgNum == 1 ? Models : Models2;
    for (let ind1 in Models_)
      for (let ind2 in Models_[ind1]) {
        if (Number(Models_[ind1][ind2].id) == modelId) {
          //alert("ind1=" + ind1 + " ind2=" + ind2);
          Data_settings["activeBar"] = Number(ind1); // активный бар - для выделения модели, на бар t1 которой установили курсов (или еще каким-то способом выбрали)
          Data_settings["activeBarModelsNum"] = Number(ind2); // номер модели (J На активном баре? J) (начиная с 0) которую нужно показывать
          Data_settings["activeBarModelsNums"] = Models_[ind1].length;
          $(
            '#select-name option[value="' + ResAJAX["info"]["Instrument"] + '"]'
          ).prop("selected", true);
          // $('#select-period option[value="'+ResAJAX['info']['Period']+'"]').prop('selected', true); // J Добавил
          $("#active-bar span").text(Data_settings.activeBar);
          $("#showAlg" + AlgNum).trigger("click"); //switchAlg2show(AlgNum);
          break;
        }
      }
  } else {
    Data_settings["activeBarModelsNum"] = 0;
    if (
      (typeof Models[Data_settings.activeBar] !== "undefined" &&
        Alg2Show == 1) ||
      (typeof Models2[Data_settings.activeBar] !== "undefined" && Alg2Show == 2)
    )
      Data_settings["activeBarModelsNums"] =
        Alg2Show == 1
          ? Models[Data_settings.activeBar].length
          : Models2[Data_settings.activeBar].length;
    else Data_settings["activeBarModelsNums"] = 0;
  }

  // Обновляем scroll-bar после загрузки данных
  updateScrollBar();

  $("#model-info").html("");

  drawGraph();

  return true;
}

function get_candles(type) {
  if (ConsoleLoggingOn) console.log("Запущена функциия get_candles:", type);
  //   alert($('#select-interval').val()+" "+$('#select-pair').val());
  ResAJAX = false;
  $(".next-prev-model-btns").css("display", "none");
  Models = [];
  Models2 = [];
  $("body").css({ cursor: "wait" });
  $("#get-data-btn").css({ cursor: "wait" });
  $("#get-data-btn2").css({ cursor: "wait" });
  setAlg_num(0);
  Alg2Show = 0;
  $("#model-info").html("");

  let post_data = {
    type: "forex",
    pair: $("#select-pair").val(),
    interval: $("#select-interval").val(),
    limit: BAR_LIMIT,
  };
  if (type == "saves") {
    var nBars_ = Number($("#nBars4get_candles").val());
    if (typeof nBars_ !== "number" || nBars_ == 0) nBars_ = BAR_LIMIT;

    var dandt_ = $("#lastBar4get_candles").val();

    post_data = {
      type: "saves",
      filename: $("#select-chart").val(),
      limit: nBars_,
      lastBar: dandt_,
    };
  }
  console.log("Ajax - запрос отправлен с параметрами:", post_data);

  if (ConsoleLoggingOn) console.log(post_data);
  let startTime = new Date(); // J Creates a new Date object.
  loaderOn();

  // // ! Отладка
  // fetch("get_candles.php")
  //   .then((response) => response.json())
  //   .then((data) => {
  //     console.log("Received data (before AJAX):", data); // Посмотрим, что приходит
  //   })
  //   .catch((error) => console.error("Error fetching get_candles.php:", error));

  // var request = $.ajax({
  $.ajax({
    url: "get_candles.php",
    type: "POST",
    timeout: 10000, // Выдаёт ошибку, если запрос не возвращается через 10 секунд
    data: post_data, //{ pair: $('#select-pair').val()  , interval : $('#select-interval').val(), limit: BAR_LIMIT },
    dataType: "json",
  })
    .done(function (data, textStatus, jqXHR) {
      $("#info-timing").text("" + (new Date() - startTime) / 1000 + " сек.");
      data.data = 0;
      if (ConsoleLoggingOn) console.log(data);
      parse_data(data);
      $("body").css({ cursor: "default" });
      $("#get-data-btn").css({ cursor: "default" });
      $("#get-data-btn2").css({ cursor: "default" });
      loaderOff();
      if (ConsoleLoggingOn) console.log("!!! request.statusText :");
      if (ConsoleLoggingOn) console.log(request);
    })
    .fail(function (jqXHR, textStatus, errorThrown) {
      $("body").css({ cursor: "default" });
      $("#get-data-btn").css({ cursor: "default" });
      $("#get-data-btn2").css({ cursor: "default" });
      $("#info-timing").text("" + (new Date() - startTime) / 1000 + " сек.");
      loaderOff();
      alert("error - AJAX запрос");
      console.log("Ошибка запроса jqXHR: ", jqXHR);
    });
}
let lastBarTime = null; // * Глобальная переменная для хранения временной метки последнего бара в функции get_candles
let firstBarTime = null; // * Объявляем глобальную переменную firstBarTime
function get_more_candles(type, _firstBarTime, direction) {
  if (ConsoleLoggingOn)
    console.log("Начало выполнения функции get_more_candles");
  if (ConsoleLoggingOn) console.log("Тип запроса:", type);
  if (ConsoleLoggingOn)
    console.log("Временная метка переданная в функцию:", _firstBarTime);
  if (ConsoleLoggingOn) console.log("Направление:", direction);

  // Определяем временную метку в зависимости от направления
  let _lastBarTime;
  if (direction === "prev") {
    _lastBarTime = _firstBarTime;
    if (ConsoleLoggingOn)
      console.log(
        "Присвоение lastBarTime исходного значение _firstBarTime",
        _lastBarTime
      );
  } else if (direction === "next") {
    // Смещаем lastBarTime на определенное количество баров вперед
    let nBarsOffset = Number($("#nBars4get_fragment").val()) || 10; // Значение по умолчанию 10, если поле пустое
    _lastBarTime = lastBarTime + nBarsOffset * BAR_DURATION; // Предполагаем, что BAR_DURATION - это длительность одного бара в миллисекундах
    if (ConsoleLoggingOn)
      console.log("Смещение lastBarTime на", nBarsOffset, "баров вперед");
  }

  if (_lastBarTime) {
    lastBarTime = _lastBarTime;
  } else {
    if (ConsoleLoggingOn) console.error("Временная метка не установлена");
    return false;
  }

  if (ConsoleLoggingOn)
    console.log("Временные метки для запроса:", {
      lastBarTime: lastBarTime,
      currentFirstBarTime: firstBarTime,
    });

  ResAJAX = false;
  $(".next-prev-model-btns").css("display", "none");
  Models = [];
  Models2 = [];
  $("body").css({ cursor: "wait" });
  $("#get-data-btn").css({ cursor: "wait" });
  $("#get-data-btn2").css({ cursor: "wait" });
  setAlg_num(0);
  Alg2Show = 0;
  $("#model-info").html("");

  let post_data = {
    type: "forex",
    pair: $("#select-pair").val(),
    interval: $("#select-interval").val(),
    limit: BAR_LIMIT,
  };

  if (type == "saves") {
    // Проверяем, передан ли firstBarTime
    let nBars_ = Number($("#nBars4get_candles").val()) || BAR_LIMIT;
    post_data = {
      type: "saves",
      filename: $("#select-chart").val(),
      limit: nBars_,
      lastBar: lastBarTime, // Передаем значение  lastBarTime, как lastBar
    };
  }

  if (ConsoleLoggingOn)
    console.log("Ajax - запрос будет отправлен с параметрами:", post_data);

  let startTime = new Date(); // Creates a new Date object.
  loaderOn();

  // // ! Отладка
  // fetch("get_candles.php")
  //   .then((response) => response.json())
  //   .then((data) => {
  //     console.log("Received data (before AJAX):", data); // Посмотрим, что приходит
  //   })
  //   .catch((error) => console.error("Error fetching get_candles.php:", error));

  // var request = $.ajax({
  $.ajax({
    url: "get_candles.php",
    type: "POST",
    timeout: 10000, // Выдаёт ошибку, если запрос не возвращается через 10 секунд
    data: post_data, //{ pair: $('#select-pair').val()  , interval : $('#select-interval').val(), limit: BAR_LIMIT },
    dataType: "json",
  })
    .done(function (data) {
      $("#info-timing").text("" + (new Date() - startTime) / 1000 + " сек.");
      if (ConsoleLoggingOn) console.log("Данные получены успешно:", data);
      loaderOff();

      // Обрабатываем полученные данные
      if (parse_data(data)) {
        if (ConsoleLoggingOn)
          console.log(
            "Обновленные временные метки после parse_data firstBarTime:",
            {
              firstBarTime,
              lastBarTime,
            }
          );
        if (ConsoleLoggingOn)
          console.log("Загружено баров:", data.result.length);
      } else {
        console.error("Ошибка при обработке данных");
      }
      // Завершаем загрузку
      $("body").css({ cursor: "default" });
      $("#get-data-btn").css({ cursor: "default" });
      $("#get-data-btn2").css({ cursor: "default" });
      loaderOff();
    })
    .fail(function (jqXHR, textStatus, errorThrown) {
      $("body").css({ cursor: "default" });
      $("#get-data-btn").css({ cursor: "default" });
      $("#get-data-btn2").css({ cursor: "default" });
      $("#info-timing").text("" + (new Date() - startTime) / 1000 + " сек.");
      loaderOff();
      alert("error - AJAX запрос. Ошибка при загрузке данных: " + textStatus);
      console.error("Ошибка запроса:", jqXHR);
    });
}

function parse_data(data) {
  if (ConsoleLoggingOn) console.log("Начало выполнения функции parse_data"); // Лог для начала функции
  var el, ot, ct;

  Data = data.result;

  // Рассчитываем BAR_DURATION как среднюю разницу между open_time и close_time
  let totalDuration = 0;
  for (let i = 0; i < data.result.length; i++) {
    const bar = data.result[i];
    totalDuration += bar.close_time - bar.open_time;
  }
  BAR_DURATION = Math.floor(totalDuration / data.result.length);
  if (ConsoleLoggingOn) console.log("Рассчитанное BAR_DURATION:", BAR_DURATION);

  firstBarTime = data.result[0]?.open_time || null;
  lastBarTime = data.result[data.result.length - 1]?.open_time || null;

  Data_settings.n_bar = Data.length;
  // Data_settings.n_bar = data["result"].length;
  if (Data_settings.n_bar < 5) {
    console.error("Error: insufficient bars", data); // Лог для ошибки
    alert("Ошибка!\n insufficient bars: " + Data_settings.n_bar);
    return false;
  }
  Data_settings.cur_bar = Data_settings.n_bar - 1;

  if (ConsoleLoggingOn) console.log("Первый бар данных:", data.result[0]); // Лог для просмотра первого бара
  if (ConsoleLoggingOn)
    console.log("Последний бар данных:", data.result[data.result.length - 1]); // Лог для просмотра последнего бара
  if (ConsoleLoggingOn)
    console.log("Значение firstBarTime после обновления:", firstBarTime);
  if (ConsoleLoggingOn)
    console.log("Значение lastBarTime после обновления:", lastBarTime);

  // Обновляем scroll-bar после загрузки данных
  updateScrollBar();

  drawGraph();
  return true;
}

document
  .getElementById("scroll-bar")
  .addEventListener("input", function (event) {
    const scrollValue = parseFloat(event.target.value); // Значение скролл-бара в процентах

    // Рассчитываем cur_bar на основе значения скролл-бара
    const maxScroll = Data_settings.n_bar - Data.barsOnDesk; // Максимальное количество баров для прокрутки
    const newCurBar =
      Math.round((scrollValue / 100) * maxScroll) + (Data.barsOnDesk - 1);

    // Обновляем cur_bar
    Data_settings.cur_bar = Math.max(
      0,
      Math.min(newCurBar, Data_settings.n_bar - 1)
    );

    // Перерисовываем график
    drawGraph();
  });

function drawGraph() {
  // отрисовка графика баров + сигналы по Graph_settings , Data_settings
  if (ConsoleLoggingOn)
    console.log(`Drawing graph with activeBar=${Data_settings.activeBar}`);
  var i,
    max_v = 0,
    min_v = 10000000000;
  selectedOnly = false;
  if ($("#chk-active-only").is(":checked")) selectedOnly = true;
  var fieldWidth =
    Graph_settings.width - Graph_settings.left - Graph_settings.right;
  var fieldHeight =
    Graph_settings.height - Graph_settings.top - Graph_settings.bottom;
  var step = Graph_settings.scale[Data_settings.scale].step;
  var bar_width = Graph_settings.scale[Data_settings.scale].width;
  // Очистка холста перед перерисовкой
  graph_context.clearRect(0, 0, Graph_settings.width, Graph_settings.height);
  graph_context.strokeStyle = Graph_settings.color.fieldBorder;
  graph_context.strokeRect(
    Graph_settings.left,
    Graph_settings.top - 5,
    fieldWidth,
    fieldHeight + 10
  );

  // сколько баров влезает на холст
  // var n_bars = Math.round((fieldWidth - bar_width) / step);
  var n_bars = Math.round((fieldWidth - bar_width) / step) + 1;
  Data.barsOnDesk = n_bars;

  //отпределение максимального и минимального значения для текущего графика
  var startBarIndex = Math.max(Data_settings.cur_bar - n_bars + 1, 0);
  var endBarIndex = Math.min(Data_settings.cur_bar, Data.length - 1);

  for (i = startBarIndex; i <= endBarIndex; i++) {
    if (Data[i].high > max_v) max_v = Data[i].high;
    if (Data[i].low < min_v) min_v = Data[i].low;
  }

  var min__ = Number($("#min_v_text").val());
  var max__ = Number($("#max_v_text").val());

  if (
    isNaN(min__) ||
    isNaN(max__) ||
    !$("#min_max_price_check").is(":checked")
  ) {
    $("#max_v_text").val(max_v);
    $("#min_v_text").val(min_v);
    Data_settings.max_v = max_v;
    Data_settings.min_v = min_v;
  } else {
    min_v = min__;
    max_v = max__;
    Data_settings.max_v = max_v;
    Data_settings.min_v = min_v;
  }

  // отрисовка баров
  var X_right = Graph_settings.width - Graph_settings.right;
  Data_settings.X_right = X_right;

  if (ConsoleLoggingOn)
    console.log(`
    Calculated X_right=${X_right},
    Graph width=${Graph_settings.width},
    Graph right margin=${Graph_settings.right},
    Bar width=${bar_width},
    Step=${step}
  `);

  for (
    i = startBarIndex,
    ind = endBarIndex - startBarIndex;
    i <= endBarIndex;
    i++, ind--
  ) {
    var isUp = Data[i].open - Data[i].close < 0 ? true : false;

    // вертикальная линия, указывающая на активний бар
    if (i == Data_settings.activeBar)
      fillRect_ep(
        graph_context,
        X_right - ind * step - bar_width / 2,
        Graph_settings.top,
        bar_width,
        fieldHeight,
        "#ddd"
      );

    if (isUp) {
      graph_context.fillStyle = Graph_settings.color.up;
      fillRect_ep(
        graph_context,
        X_right - ind * step - bar_width / 2,
        Graph_settings.top +
          ((max_v - Data[i].close) / (max_v - min_v)) * fieldHeight,
        bar_width,
        ((Data[i].close - Data[i].open) / (max_v - min_v)) * fieldHeight,
        Graph_settings.color.up
      );
      line_ep(
        graph_context,
        X_right - ind * step,
        Graph_settings.top +
          ((max_v - Data[i].high) / (max_v - min_v)) * fieldHeight,
        X_right - ind * step,
        Graph_settings.top +
          ((max_v - Data[i].close) / (max_v - min_v)) * fieldHeight,
        Graph_settings.color.upLine
      );
      line_ep(
        graph_context,
        X_right - ind * step,
        Graph_settings.top +
          ((max_v - Data[i].low) / (max_v - min_v)) * fieldHeight,
        X_right - ind * step,
        Graph_settings.top +
          ((max_v - Data[i].open) / (max_v - min_v)) * fieldHeight,
        Graph_settings.color.upLine
      );
    } else {
      graph_context.fillStyle = Graph_settings.color.down;
      fillRect_ep(
        graph_context,
        X_right - ind * step - bar_width / 2,
        Graph_settings.top +
          ((max_v - Data[i].open) / (max_v - min_v)) * fieldHeight,
        bar_width,
        ((Data[i].open - Data[i].close) / (max_v - min_v)) * fieldHeight,
        Graph_settings.color.down
      );
      line_ep(
        graph_context,
        X_right - ind * step,
        Graph_settings.top +
          ((max_v - Data[i].high) / (max_v - min_v)) * fieldHeight,
        X_right - ind * step,
        Graph_settings.top +
          ((max_v - Data[i].open) / (max_v - min_v)) * fieldHeight,
        Graph_settings.color.downLine
      );
      line_ep(
        graph_context,
        X_right - ind * step,
        Graph_settings.top +
          ((max_v - Data[i].low) / (max_v - min_v)) * fieldHeight,
        X_right - ind * step,
        Graph_settings.top +
          ((max_v - Data[i].close) / (max_v - min_v)) * fieldHeight,
        Graph_settings.color.downLine
      );
    }
    if (Data[i].high == Data[i].low) {
      fillRect_ep(
        graph_context,
        X_right - ind * step - bar_width / 2,
        Graph_settings.top +
          ((max_v - Data[i].open) / (max_v - min_v)) * fieldHeight,
        bar_width,
        1,
        Graph_settings.color.up
      );
    }
    // if (i === Data_settings.cur_bar - n_bars + 1) {
    if (i === endBarIndex) {
      // Обновляем только для первого бара в видимой области
      $("#last-price").text(Data[Data_settings.cur_bar].close);
      $("#last-price").css({
        left: Graph_settings.width - Graph_settings.right,
        top:
          Graph_settings.top +
          ((max_v - Data[Data_settings.cur_bar].close) / (max_v - min_v)) *
            fieldHeight -
          8,
        background:
          Data[Data_settings.cur_bar].open <= Data[Data_settings.cur_bar].close
            ? Graph_settings.color.up
            : Graph_settings.color.down,
      });
    }
  }
  // Обновление значения max для scroll-bar
  document.getElementById("scroll-bar").max = Math.max(Data.length - n_bars, 0);

  // Обновление положения scroll-bar после отрисовки графика
  updateScrollBar();

  drawModels(graph_context, X_right, step, min_v, max_v, fieldHeight, n_bars);
}

// Функция, обрабатывающая событие
function wheel(event) {
  if (!graph_position) return; // Проверяем, есть ли данные о положении графика
  var xpos = event.pageX - graph_position.left;
  var ypos = event.pageY - graph_position.top;

  // Убедимся, что курсор находится в пределах графика
  if (
    xpos < Graph_settings.left ||
    xpos > Graph_settings.width - Graph_settings.right ||
    ypos < Graph_settings.top ||
    ypos > Graph_settings.height - Graph_settings.bottom
  ) {
    return; // Прерываем обработчик, если курсор за пределами графика
  }
  var graph_position;
  var delta; // Направление колёсика мыши
  var delta; // Направление колёсика мыши
  if (!isLoaderOn) {
    event = event || window.event;
    // Opera и IE работают со свойством wheelDelta
    if (event.wheelDelta) {
      // В Opera и IE
      delta = event.wheelDelta / 120;
      // В Опере значение wheelDelta такое же, но с противоположным знаком
      if (window.opera) delta = -delta; // Дополнительно для Opera
    } else if (event.detail) {
      // Для Gecko
      delta = -event.detail / 3;
    }

    graph_position = $("#graph").offset();
    var xpos = event.pageX - graph_position.left;
    var ypos = event.pageY - graph_position.top;
    if (
      ypos >= Graph_settings.top &&
      ypos <= Graph_settings.height - Graph_settings.bottom &&
      xpos >= Graph_settings.left &&
      xpos <= Graph_settings.width - Graph_settings.right &&
      Data_settings.n_bar > 0
    ) {
      // Запрещаем обработку события браузером по умолчанию
      if (event.preventDefault) event.preventDefault();
      event.returnValue = false;

      var old_scale = Data_settings.scale;
      if (delta < 0 && Data_settings.scale < Graph_settings.max_scale)
        Data_settings.scale++;
      if (delta > 0 && Data_settings.scale > 0) Data_settings.scale--;
      if (old_scale !== Data_settings.scale) drawGraph();
    }
  }
  //   alert(delta); // Выводим направление колёсика мыши
}

function debug_on_off(event) {
  debugMode = !debugMode;
  if (debugMode) {
    $("#service").css({ display: "block" });
    $("#debug").css({ display: "block" });
    $("#bar-info").css({ display: "block" });
    $("#dop-info").css({ display: "block" });
  } else {
    $("#service").css({ display: "none" });
    $("#debug").css({ display: "none" });
    $("#bar-info").css({ display: "none" });
    $("#dop-info").css({ display: "none" });
  }
  if (event.preventDefault) event.preventDefault();
}

function build_models(algorithm_num) {
  // по AJAX обращаемся к процедуре расчета моделей

  if (algorithm_num == 2 && Alg_num == 0) {
    alert("Необходимо, чтобы вначале отработал Алгоритм_1");
    return;
  }
  const MIN_BAR_CNT = 100;
  let ind;
  let selected = $("#form-mode input[type='radio']:checked");
  let mode = ("" + selected[0].id).substr(3, 100);
  if (Data_settings["n_bar"] === 0) {
    alert("Необходимо сперва получить свечной график!");
    return false;
  }
  if (whatIsCalculatingNow > 0) {
    //лишний блок, в целом, повторное нажатие во время выполнения блокируется лоадером, оставлено на всякий случай
    alert(
      "Производится расчет - поиск моделей по Алгоритму " +
        whatIsCalculatingNow +
        "\nДождитесь окончания предыдущего расчета."
    );
    return false;
  }
  if (Data_settings["n_bar"] > MIN_BAR_CNT) {
    // вызов процедуры расчета моделей
    let startTime = new Date();
    if (ConsoleLoggingOn)
      console.log(
        new Date() +
          " нажали " +
          algorithm_num +
          " wic= " +
          whatIsCalculatingNow
      );
    whatIsCalculatingNow = algorithm_num;
    let log_ = 0;
    if ($("#chk-log").is(":checked")) log_ = 1;
    let paramArr_ = {
      mode: mode,
      selectedBar: Data_settings["activeBar"],
      log: log_,
    };

    if (ConsoleLoggingOn) console.log(location);
    $("body").css({ cursor: "wait" });
    $("#build-btn").css({ cursor: "wait" });
    loaderOn(); //блокировка экрана, показ вращающегося лоадера
    let url_ = "build_models_A" + algorithm_num + ".php"; // location.host === "gleb-egorov.ru" ? "http://209.97.130.151/build_models_A1.php": "build_models_A1.php";
    let Models_Alg1 = JSON.stringify([]);
    if (algorithm_num == 2) Models_Alg1 = JSON.stringify(ResAJAX["Models"]);
    ResAJAX = false;

    var request = $.ajax({
      url: url_,
      type: "POST",
      timeout: 60000,
      data: {
        Chart: JSON.stringify(Data),
        paramArr: paramArr_,
        Models1: Models_Alg1,
      },
      dataType: "json",
    })
      .done(function (data, textStatus, jqXHR) {
        $("body").css({ cursor: "default" });
        $("#build-btn").css({ cursor: "default" });
        $("#info-timing").text("" + (new Date() - startTime) / 1000 + " сек.");
        ResAJAX = data;

        Models = [];
        Models2 = [];
        if (typeof ResAJAX["Models"] !== "undefined")
          for (let model_ind in ResAJAX["Models"]) {
            ind = Number(model_ind.substr(1, 100));
            Models[ind] = ResAJAX["Models"][model_ind];
          }
        if (typeof ResAJAX["Models2"] !== "undefined")
          for (let model_ind in ResAJAX["Models2"]) {
            ind = Number(model_ind.substr(1, 100));
            Models2[ind] = ResAJAX["Models2"][model_ind];
          }

        setAlg_num(ResAJAX["Algorithm_num"]);
        if (typeof ResAJAX["States"] == "undefined") {
          if (ConsoleLoggingOn) console.log(ResAJAX);
          alert("Ошибка AJAX - пустой результат");
          return false;
        }
        if (
          typeof ResAJAX["Chart"] !== "undefined" &&
          ResAJAX["Chart"].length > 500
        )
          ResAJAX["Chart"] = ResAJAX["Chart"].length; // не используется - chart сейчас не пересылается вроде

        Data_settings["activeBarModelsNum"] = 0;
        if (
          (typeof Models[Data_settings.activeBar] !== "undefined" &&
            Alg2Show == 1) ||
          (typeof Models2[Data_settings.activeBar] !== "undefined" &&
            Alg2Show == 2)
        )
          Data_settings["activeBarModelsNums"] =
            Alg2Show == 1
              ? Models[Data_settings.activeBar].length
              : Models2[Data_settings.activeBar].length;
        else Data_settings["activeBarModelsNums"] = 0;
        $("#model-info").html(""); //  $("#model-info").html("<hr>" + modelInfo()); // не понятно, зачем это тут было... очистил

        drawGraph();
        whatIsCalculatingNow = 0;
        algorithmCalculated = ResAJAX["Algorithm_num"];
        loaderOff();
        if (ConsoleLoggingOn) console.log(data);

        $("#debug").html("<pre>" + JSON2html(ResAJAX) + "</pre>");
        $("#info-log").html("(" + JSON2htmlStrCnt + " стр.)");
      })
      .fail(function (jqXHR, textStatus, errorThrown) {
        $("body").css({ cursor: "default" });
        $("#build-btn").css({ cursor: "default" });
        $("#info-timing").text("" + (new Date() - startTime) / 1000 + " сек.");
        alert("error - AJAX request");
        console.log("fail -  jqXHR: ");
        console.log(jqXHR);
        whatIsCalculatingNow = 0;
        loaderOff();
        //algorithmCalculated=0;
      });

    return true;
  }
  alert(
    "Для расчета необходимо как минимум " +
      MIN_BAR_CNT +
      " баров, на чарте только " +
      Data_settings["n_bar"]
  );
  return false;
}

function changeSource(n) {
  //alert($("#ssource-switch").prop("checked"));
  if (n == "saves") {
    $("#source-saves").css("display", "block");
    $("#source-forex").css("display", "none");
    $("#source-mysql").css("display", "none");
  }
  if (n == "forex") {
    $("#source-saves").css("display", "none");
    $("#source-forex").css("display", "block");
    $("#source-mysql").css("display", "none");
  }
  if (n == "mysql") {
    $("#source-saves").css("display", "none");
    $("#source-forex").css("display", "none");
    $("#source-mysql").css("display", "block");
  }
}
function switchAlg2show(num) {
  Alg2Show = num;
  drawGraph();
}
function switchModels(direction) {
  let modelsArr = Models;
  if (Alg2Show == 2) modelsArr = Models2;
  let curBar = Data_settings["activeBar"];
  let i;
  if (curBar == 0) curBar = Data_settings["n_bar"];
  //alert(curBar);
  let old_activeBar = curBar;
  let newBarFound = false;
  if (direction === "switch") {
    //Цикличное переключение моделей для выбранной t1
    Data_settings["activeBarModelsNum"]++;
    if (
      Data_settings["activeBarModelsNum"] >=
      Data_settings["activeBarModelsNums"]
    )
      Data_settings["activeBarModelsNum"] = 0;
    drawGraph();
    //$("#model-info").html("<hr>" + modelInfo());

    return true;
  }
  if (direction === "prev") {
    for (i = curBar - 1; i >= 0; i--) {
      if (typeof modelsArr[i] !== "undefined") {
        newBarFound = i;
        break;
      }
    }
  } else {
    for (i = curBar + 1; i <= Data_settings["n_bar"] - 1; i++) {
      if (typeof modelsArr[i] !== "undefined") {
        newBarFound = i;
        break;
      }
    }
  }
  if (newBarFound) {
    Data_settings["activeBar"] = i;
    Data_settings["activeBarModelsNum"] = 0;
    Data_settings["activeBarModelsNums"] = modelsArr[i].length;
    drawGraph();
    //  $("#model-info").html("<hr>" + modelInfo());
    //        $('#switch-model-btn').prop("disabled",(Data_settings['activeBarModelsNums']<2));
    $("#active-bar span").text(Data_settings.activeBar);
    return true;
  }
  return false;
}

function changeChkActiveMOdelsOnly() {
  drawGraph();
}

/* The above JavaScript code is adding an event listener to the document that waits for the DOM content
to be fully loaded. Once the DOM content is loaded, it selects an element with the id
'canvas-wrapper' and adds a wheel event listener to it. */

document.addEventListener("DOMContentLoaded", function () {
  const canvasWrapper = document.getElementById("canvas-wrapper");
  let isMouseInsideCanvas = false;

  // Добавляем обработчики для определения, находится ли мышь над элементом canvas
  canvasWrapper.addEventListener("mouseenter", function (event) {
    isMouseInsideCanvas = true;
  });

  canvasWrapper.addEventListener("mouseleave", function (event) {
    isMouseInsideCanvas = false;
  });

  // Функция-обработчик события колесика мыши
  function handleWheelEvent(event) {
    if (!isLoaderOn && isMouseInsideCanvas) {
      const rect = canvasWrapper.getBoundingClientRect();
      const xpos = event.clientX - rect.left;
      const ypos = event.clientY - rect.top;

      // Проверка, находится ли курсор внутри области графика (Graph_settings)
      if (
        ypos >= Graph_settings.top &&
        ypos <= Graph_settings.height - Graph_settings.bottom &&
        xpos >= Graph_settings.left &&
        xpos <= Graph_settings.width - Graph_settings.right &&
        Data_settings.n_bar > 0
      ) {
        event.preventDefault(); // Предотвращает прокрутку страницы только если курсор внутри графика

        let delta; // Направление колёсика мыши
        if (event.wheelDelta) {
          // В Opera и IE
          delta = event.wheelDelta / 120;
          // В Опере значение wheelDelta такое же, но с противоположным знаком
          if (window.opera) delta = -delta; // Дополнительно для Opera
        } else if (event.detail) {
          // Для Gecko
          delta = -event.detail / 3;
        }

        var old_scale = Data_settings.scale;
        if (delta < 0 && Data_settings.scale < Graph_settings.max_scale) {
          Data_settings.scale++;
        }
        if (delta > 0 && Data_settings.scale > 0) {
          Data_settings.scale--;
        }
        if (old_scale !== Data_settings.scale) {
          drawGraph();
        }
      } else {
        if (ConsoleLoggingOn)
          console.log("Cursor outside the chart or no data"); // Отладочный лог
      }
    }
  }

  // Добавляем один слушатель для события wheel с опцией { passive: false }
  canvasWrapper.addEventListener("wheel", handleWheelEvent, { passive: false });
});
