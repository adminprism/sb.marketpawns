
function drawModels(
    graph_context,
    X_right,
    step,
    min_v,
    max_v,
    fieldHeight,
    n_bars
  ) {
    // отрисовка моделей
    graph_context.font = "11px Courier";
    let model_ = 0;
    let act_ind = "inactive";
    // 2021-06-28 : поменял местами очередность отрисовки - вначале модели Алгоритма 2, так как нужно сформировать массив связанных моделей для активной (выбранной) модели J - после изменения алгоритма очередность больше не важна
    // linkedModels = {}; // массив, где индексы- строки типа "p567-1"  а значение - наименование цвета (из массива Model_colors (IDprev_1 IDprev_2 IDinners)
    // delete linkedModels;
    if (Models2.length > 0 && Alg2Show === 2) {
      linkedModels = {}; // массив, где индексы- строки типа "p567-1"  а значение - наименование цвета (из массива Model_colors (IDprev_1 IDprev_2 IDinners)
      activeExists = false;
      for (let k in Models2) {
        for (let n in Models2[k]) {
          model_ = Models2[k][n]; // J k - это 1-ые точки ? А n - это номер модели (Data_settings.activeBarModelsNum)
          act_ind = "inactive";
          if (
            model_["t3"] == Data_settings.activeBar && // т.3 модели из массива совпадает с выбранным баром
            n == Data_settings.activeBarModelsNum // activeBarModelsNum - номер модели (начиная с 0) которую нужно показывать
          )
            act_ind = "active";
          if (
            minModelBar(model_) < Data_settings.cur_bar && // j cur_bar - текущий (правый) бар для показа
            maxModelBar(model_) > Data_settings.cur_bar - n_bars // j var n_bars = Math.round((fieldWidth - bar_width) / step) сколько баров влезает на холст; var fieldWidth =  Graph_settings.width - Graph_settings.left - Graph_settings.right; left: - область графика - отступ по оси Х (слева) right - область графика - отступ по оси Х (справа)
          ) {
            if (act_ind === "inactive")
              drawModel_Alg2(
                model_,
                X_right,
                step,
                min_v,
                max_v,
                fieldHeight,
                false
              );
            // X_right координата X самого правого бара
            else activeExists = k;
          }
        }
      }
      if (activeExists) {
        // если рассматривается выбранная модель
        drawModel_Alg2(
          Models2[activeExists][Data_settings.activeBarModelsNum],
          X_right,
          step,
          min_v,
          max_v,
          fieldHeight,
          true
        ); //отрисовка активной (выбранной) модели
  
        // формируем массив для отрисовки связанных моделей
        // вначале для IDprevs
        let n_digits = 2;
        if (Data_settings.n_bar > 100) n_digits = 3;
        if (Data_settings.n_bar > 1000) n_digits = 4; // число знаков в номере модели
        if (
          typeof Models2[activeExists][Data_settings.activeBarModelsNum][
            "IDprevs"
          ] !== "undefined"
        ) {
          for (let idp in Models2[activeExists][Data_settings.activeBarModelsNum][
            "IDprevs"
          ]) {
            let ind = Models2[activeExists][Data_settings.activeBarModelsNum][
              "IDprevs"
            ][idp].substr(4, n_digits + 2); // допущение, что больше 10 моделей на одной опорной точке быть не может (J получаем строку типа строки типа "p567-1")
            if (idp == 0) linkedModels[ind] = "IDprev_1";
            else linkedModels[ind] = "IDprev_2";
  
            //проверяем метку значмой предшествующей
            let markIDP = Models2[activeExists][Data_settings.activeBarModelsNum][
              "IDprevs"
            ][idp].substr(-8, 8); // разбиваем ключ IDprev, например если "m1-p891-1-894-2-IDPrev_1" - берем IDPrev_1
            if (markIDP == "IDprev_1") linkedModels[ind] = "IDprev_1";
          }
        }
        // затем дописываем для IDinners
        if (
          typeof Models2[activeExists][Data_settings.activeBarModelsNum][
            "IDinners"
          ] !== "undefined"
        ) {
          for (let idp in Models2[activeExists][Data_settings.activeBarModelsNum][
            "IDinners"
          ]) {
            let ind = Models2[activeExists][Data_settings.activeBarModelsNum][
              "IDinners"
            ][idp].substr(4, n_digits + 2); // допущение, что больше 10 моделей на одной опорной точке быть не может
            linkedModels[ind] = "IDinners";
            let markIDI = Models2[activeExists][Data_settings.activeBarModelsNum][
              "IDinners"
            ][idp].substr(-9, 9); // разбиваем ключ IDprev, например если "m1-p891-1-894-2-IDPrev_1" - берем IDPrev_1
            if (markIDI == "IDinner_1") linkedModels[ind] = "IDinner_1";
          }
        }
  
        //console.log(linkedModels);
        // далее по сформированному массиву отрисовываем модели разными цветами
        for (let ind in linkedModels) {
          //console.log("ind=",ind);
          let tmpArr = ind.split("-");
          //console.log("tmpArr=",tmpArr);
          //console.log("Models=",Models);
          console.log("Model1color=", Model_colors[linkedModels[ind]]);
          drawModel_Alg1(
            Models[tmpArr[0]][tmpArr[1]],
            X_right,
            step,
            min_v,
            max_v,
            fieldHeight,
            true,
            Model_colors[linkedModels[ind]]
          );
        }
        console.log(linkedModels);
      }
    }
  
    // console.log('перед алг.1 IDprevs= ' +  Models[activeExists][Data_settings.activeBarModelsNum]['IDprevs'])
    delete linkedModels;
    if (Models.length > 0 && Alg2Show === 1) {
      linkedModels = {};
      // console.log('пересоздали свежий IDprevs= ' +  Models[activeExists][Data_settings.activeBarModelsNum]['IDprevs'])
      activeExists = false;
      for (let k in Models) {
        for (let n in Models[k]) {
          model_ = Models[k][n]; // J k -это точки ? А n - это прикрепленные к ним свойства ? Где присваивается Data_settings.activeBarModelsNum ?
          act_ind = "inactive";
          if (
            model_["t1"] == Data_settings.activeBar && // т.1 модели из массива совпадает с выбранным баром
            n == Data_settings.activeBarModelsNum // activeBarModelsNum - номер модеди (начиная с 0) которую нужно показывать
          )
            act_ind = "active";
          if (
            minModelBar(model_) < Data_settings.cur_bar && // j cur_bar - текущий (правый) бар для показа
            maxModelBar(model_) > Data_settings.cur_bar - n_bars // j var n_bars = Math.round((fieldWidth - bar_width) / step) сколько баров влезает на холст; var fieldWidth =  Graph_settings.width - Graph_settings.left - Graph_settings.right; left: - область графика - отступ по оси Х (слева) right - область графика - отступ по оси Х (справа)
          ) {
            if (act_ind === "inactive")
              drawModel_Alg1(
                model_,
                X_right,
                step,
                min_v,
                max_v,
                fieldHeight,
                false
              );
            // X_right координата X самого правого бара
            else activeExists = k;
          }
        }
      }
  
      if (activeExists) {
        // если рассматривается выбранная модель
        drawModel_Alg1(
          Models[activeExists][Data_settings.activeBarModelsNum],
          X_right,
          step,
          min_v,
          max_v,
          fieldHeight,
          true
        ); //отрисовка активной (выбранной) модели
        console.log(
          "найдено IDprevs= " +
            Models[activeExists][Data_settings.activeBarModelsNum]["IDprevs"]
        );
        // формируем массив для отрисовки связанных моделей
        //отрисовка предшествующих для моделей Алгоритма 1
        let n_digits = 2;
        if (Data_settings.n_bar > 100) n_digits = 3;
        if (Data_settings.n_bar > 1000) n_digits = 4; // число знаков в номере модели
  
        if (
          typeof Models[activeExists][Data_settings.activeBarModelsNum][
            "IDprevs"
          ] !== "undefined"
        ) {
          for (let idp in Models[activeExists][Data_settings.activeBarModelsNum][
            "IDprevs"
          ]) {
            let ind = Models[activeExists][Data_settings.activeBarModelsNum][
              "IDprevs"
            ][idp].substr(4, n_digits + 2); // допущение, что больше 10 моделей на одной опорной точке быть не может
            if (idp == 0) linkedModels[ind] = "IDprev_1";
            else linkedModels[ind] = "IDprev_2";
  
            //проверяем метку значимой предшествующей
            let markIDP = Models[activeExists][Data_settings.activeBarModelsNum][
              "IDprevs"
            ][idp].substr(-8, 8); // разбиваем ключ IDprev, например если "m1-p891-1-894-2-IDPrev_1" - берем IDPrev_1
            if (markIDP == "IDprev_1") linkedModels[ind] = "IDprev_1";
          }
        }
  
        // // //   //console.log(linkedModels);
        // далее по сформированному массиву отрисовываем модели разными цветами
        for (let ind in linkedModels) {
          console.log("linkedModels[ind] " + linkedModels[ind]);
          //     console.log(n_digits);
          //     console.log("IDprevs= ", Models[activeExists][Data_settings.activeBarModelsNum]['IDprevs']);
          //     console.log("ind=", ind);
          let tmpArr = ind.split("-");
          //     console.log("tmpArr=", tmpArr);
          //     console.log("Models[tmpArr[0]]= ", Models[tmpArr[0]]);
          //     console.log("Models=", Models);
          console.log("Model1color=", Model_colors[linkedModels[ind]]);
          drawModel_Alg1(
            Models[tmpArr[0]][tmpArr[1]],
            X_right,
            step,
            min_v,
            max_v,
            fieldHeight,
            true,
            Model_colors[linkedModels[ind]]
          );
        }
  
        //   console.log(linkedModels);
        //  // Конец отрисовка предшествующих для моделей Алгоритма 1
      }
    }
  
    graph_context.clearRect(
      Graph_settings.width - Graph_settings.right + 1,
      0,
      Graph_settings.right,
      Graph_settings.height
    );
    graph_context.clearRect(0, 0, Graph_settings.left - 1, Graph_settings.height);
    graph_context.clearRect(0, 0, Graph_settings.width, Graph_settings.top - 6);
    graph_context.clearRect(
      0,
      Graph_settings.height - Graph_settings.bottom + 6,
      Graph_settings.width,
      Graph_settings.bottom
    );
    //    graph_context.strokeStyle = Graph_settings.color.fieldBorder;
    //    graph_context.strokeRect(Graph_settings.left, Graph_settings.top-5, fieldWidth, fieldHeight+10);
  
    $("#model-info").html("<hr>" + modelInfo());
    $("#popUpText").val(getModelNumString());
  }
  
  function drawModel_Alg1(
    model,
    X_right,
    step,
    min_v,
    max_v,
    fieldHeight,
    isActive,
    linked_color = false
  ) {
    // если linked_color задан, то значит мы рисуем связанную модель для модели Алгоритма 2
    // модель, коордианата самого правого бара, step, минимальная цена на графике, максимальная цена на графике, высота графика, выбранная ли модель?(?)
    // отрисовка точек
    //alert("Алгоритм: "+Alg_num);
    let act_ind = isActive ? "active" : "inactive";
    graph_context.fontcolor = "Black";
    if (isActive)
      drawModelLevelsAndSize(
        graph_context,
        X_right,
        step,
        min_v,
        max_v,
        fieldHeight,
        model
      );
    //    if (model['t1'] == Data_settings.activeBar&&n==Data_settings.activeBarModelsNum) act_ind = 'active';
    for (let i in PointsList)
      if (typeof model[PointsList[i]] !== "undefined") {
        // есть такая точка на модели
        if (PointsList[i] === "t1" || !selectedOnly || isActive) {
          let point_color = Model_colors[act_ind].point;
  
          // временная заглушка
          //if(model['v']=='low')point_color="rgba(0,155,0,0.5)";
          //else point_color="rgba(0,100,255,0.5)";
          graph_context.fillStyle = point_color;
          if (typeof Model_colors[act_ind][PointsList[i]] !== "undefined")
            point_color = Model_colors[act_ind][PointsList[i]]; //назначание цвета точки J A = обращаемся к model[act_ind], B =  pointlist[I], И обращаемся к a[b]
          if (linked_color) point_color = linked_color;
          let price_ = getModelPrice(model, PointsList[i]);
          graph_context.beginPath();
          let p_x =
            X_right - (Data_settings.cur_bar - model[PointsList[i]]) * step; // J  Получается, что это расстояние от точки до последнего бара на графике
          let p_y =
            Graph_settings.top +
            ((max_v - price_) / (max_v - min_v)) * fieldHeight;
          //graph_context.arc(p_x, p_y, 4, 0, 2 * Math.PI, false);
          graph_context.fillStyle = point_color;
          graph_context.fillRect(p_x - 3, p_y - 1, 6, 2);
          //graph_context.fill();
          graph_context.fillText(PointsList[i], p_x + 5, p_y + 2);
        }
      }
  
    let point_color = Model_colors[act_ind].point;
    if (linked_color) point_color = linked_color;
    // линии ЛТ и ЛЦ
    if (!selectedOnly || isActive || linked_color) {
      if (
        typeof model["param"] !== "undefined" &&
        typeof model["param"]["auxP6"] !== "undefined"
      ) {
        // есть auxP6
        if (typeof model["t3'мп"] !== "undefined")
          // когда есть т.3'мп
          drawModelLine(
            graph_context,
            X_right,
            step,
            min_v,
            max_v,
            fieldHeight,
            model,
            "t3'мп",
            "auxP6",
            point_color
          );
        else if (typeof model["t3'"] !== "undefined")
          // когда нет т.3'мп, но есть т.3'
          drawModelLine(
            graph_context,
            X_right,
            step,
            min_v,
            max_v,
            fieldHeight,
            model,
            "t3'",
            "auxP6",
            point_color
          );
        // всмпомогатлельная МП от т.3
        else
          drawModelLine(
            graph_context,
            X_right,
            step,
            min_v,
            max_v,
            fieldHeight,
            model,
            "t3",
            "auxP6",
            point_color
          );
        if (typeof model["t2'"] !== "undefined")
          drawModelLine(
            graph_context,
            X_right,
            step,
            min_v,
            max_v,
            fieldHeight,
            model,
            "t2'",
            "auxP6",
            point_color,
            true
          );
        else
          drawModelLine(
            graph_context,
            X_right,
            step,
            min_v,
            max_v,
            fieldHeight,
            model,
            "t2",
            "auxP6",
            point_color,
            true
          );
      }
      if (
        typeof model["param"] !== "undefined" &&
        typeof model["param"]["calcP6"] !== "undefined"
      ) {
        // есть auxP6
  
        drawModelLine(
          graph_context,
          X_right,
          step,
          min_v,
          max_v,
          fieldHeight,
          model,
          "t1",
          "calcP6",
          point_color
        );
        drawModelLine(
          graph_context,
          X_right,
          step,
          min_v,
          max_v,
          fieldHeight,
          model,
          "t4",
          "calcP6",
          point_color,
          true
        );
      }
      if (
        typeof model["param"] !== "undefined" &&
        typeof model["param"]["auxP6'"] !== "undefined"
      ) {
        // есть auxP6
        if (typeof model["t3'мп5'"] !== "undefined")
          drawModelLine(
            graph_context,
            X_right,
            step,
            min_v,
            max_v,
            fieldHeight,
            model,
            "t3'мп5'",
            "auxP6'",
            point_color
          );
        else if (typeof model["t3'мп"] !== "undefined")
          //if (typeof model["t3'мп"] !== "undefined")
          drawModelLine(
            graph_context,
            X_right,
            step,
            min_v,
            max_v,
            fieldHeight,
            model,
            "t3'мп",
            "auxP6'",
            point_color
          );
        else if (typeof model["t3'"] !== "undefined")
          drawModelLine(
            graph_context,
            X_right,
            step,
            min_v,
            max_v,
            fieldHeight,
            model,
            "t3'",
            "auxP6'",
            point_color
          );
        else
          drawModelLine(
            graph_context,
            X_right,
            step,
            min_v,
            max_v,
            fieldHeight,
            model,
            "t3",
            "auxP6'",
            point_color,
            true
          );
        drawModelLine(
          graph_context,
          X_right,
          step,
          min_v,
          max_v,
          fieldHeight,
          model,
          "t4",
          "auxP6'",
          point_color,
          true
        );
      }
      if (
        typeof model["param"] !== "undefined" &&
        typeof model["param"]["_cross_point"] !== "undefined"
      ) {
        // ЛЦ' и ЛТ сходятся
        if (typeof model["t2'"] !== "undefined")
          drawModelLine(
            graph_context,
            X_right,
            step,
            min_v,
            max_v,
            fieldHeight,
            model,
            "t2'",
            "_cross_point",
            point_color
          );
        else
          drawModelLine(
            graph_context,
            X_right,
            step,
            min_v,
            max_v,
            fieldHeight,
            model,
            "t2",
            "_cross_point",
            point_color
          );
        drawModelLine(
          graph_context,
          X_right,
          step,
          min_v,
          max_v,
          fieldHeight,
          model,
          "t1",
          "_cross_point",
          point_color
        );
      } else {
        // ЛЦ' и ЛТ рассходятся - рисуем в бесконечность
        if (
          typeof model["t1"] !== "undefined" &&
          typeof model["t3'"] !== "undefined"
        ) {
          drawModelLine(
            graph_context,
            X_right,
            step,
            min_v,
            max_v,
            fieldHeight,
            model,
            "t1",
            "t3'",
            point_color
          );
        } else if (
          typeof model["t1"] !== "undefined" &&
          typeof model["t3"] !== "undefined"
        ) {
          drawModelLine(
            graph_context,
            X_right,
            step,
            min_v,
            max_v,
            fieldHeight,
            model,
            "t1",
            "t3",
            point_color
          );
        }
        if (
          typeof model["t2'"] !== "undefined" &&
          typeof model["t4"] !== "undefined"
        ) {
          drawModelLine(
            graph_context,
            X_right,
            step,
            min_v,
            max_v,
            fieldHeight,
            model,
            "t2'",
            "t4",
            point_color
          );
        } else if (
          typeof model["t2"] !== "undefined" &&
          typeof model["t4"] !== "undefined"
        ) {
          drawModelLine(
            graph_context,
            X_right,
            step,
            min_v,
            max_v,
            fieldHeight,
            model,
            "t2",
            "t4",
            point_color
          );
        }
      }
    }
  }
  function drawModel_Alg2(
    model,
    X_right,
    step,
    min_v,
    max_v,
    fieldHeight,
    isActive
  ) {
    //  alert(" Модель 2");
    let act_ind = isActive ? "active" : "inactive";
    graph_context.fontcolor = "Black";
    if (isActive)
      drawModelLevelsAndSize(
        graph_context,
        X_right,
        step,
        min_v,
        max_v,
        fieldHeight,
        model
      );
    //    if (model['t1'] == Data_settings.activeBar&&n==Data_settings.activeBarModelsNum) act_ind = 'active';
    for (let i in PointsList)
      if (typeof model[PointsList[i]] !== "undefined") {
        // есть такая точка на модели
        if (PointsList[i] === "t3" || !selectedOnly || isActive) {
          let point_color = Model_colors[act_ind].point;
          // временная заглушка
          //if(model['v']=='low')point_color="rgba(0,155,0,0.5)";
          //else point_color="rgba(0,100,255,0.5)";
          graph_context.fillStyle = point_color;
          if (typeof Model_colors[act_ind][PointsList[i]] !== "undefined")
            point_color = Model_colors[act_ind][PointsList[i]]; //назначание цвета точки J A = обращаемся к model[act_ind], B =  pointlist[I], И обращаемся к a[b]
          let price_ = getModelPrice(model, PointsList[i]);
          graph_context.beginPath();
          let p_x =
            X_right - (Data_settings.cur_bar - model[PointsList[i]]) * step; // J  Получается, что это расстояние от точки до последнего бара на графике
          let p_y =
            Graph_settings.top +
            ((max_v - price_) / (max_v - min_v)) * fieldHeight;
          //graph_context.arc(p_x, p_y, 4, 0, 2 * Math.PI, false);
          graph_context.fillStyle = point_color;
          graph_context.fillRect(p_x - 3, p_y - 1, 6, 2);
          //graph_context.fill();
          graph_context.fillText(PointsList[i], p_x + 5, p_y + 2);
        }
      }
    let point_color = Model_colors[act_ind].point;
    // линии ЛТ и ЛЦ
    if (!selectedOnly || isActive) {
      // ЛТ от Т5
      if (
        typeof model["t5"] !== "undefined" &&
        typeof model["t3"] !== "undefined" &&
        typeof model["param"]["_cross_point"] !== "undefined"
      ) {
        if (typeof model["t3'"] !== "undefined") var t3_ = "t3'";
        else t3_ = "t3";
        drawModelLine(
          graph_context,
          X_right,
          step,
          min_v,
          max_v,
          fieldHeight,
          model,
          t3_,
          // "_cross_point",
          "calcP6",
          point_color,
          true
        );
      }
      // ЛТ от Т5"
      if (
        typeof model['t5"'] !== "undefined" &&
        typeof model["t3"] !== "undefined" &&
        typeof model["param"]["_cross_point2"] !== "undefined"
        // typeof model["param"]["calcP6\""] !== "undefined"
      ) {
        t3_ = "t3";
        if (typeof model["t3'"] !== "undefined") var t3_ = "t3'";
        if (typeof model['t3"'] !== "undefined") var t3_ = 't3"';
        drawModelLine(
          graph_context,
          X_right,
          step,
          min_v,
          max_v,
          fieldHeight,
          model,
          t3_,
          // "_cross_point2",
          'calcP6"',
          point_color,
          true
        );
      }
      // ЛЦ
      if (
        typeof model["t4"] !== "undefined" &&
        typeof model["param"]["_cross_point"] !== "undefined" &&
        typeof model["t2"] !== "undefined"
      ) {
        var cross_point_ = "_cross_point";
        if (
          typeof model["param"]["_cross_point2"] !== "undefined" &&
          model["param"]["_cross_point2"] > model["param"]["_cross_point"]
        )
          cross_point_ = "_cross_point2";
        var t2_ = "t2";
        drawModelLine(
          graph_context,
          X_right,
          step,
          min_v,
          max_v,
          fieldHeight,
          model,
          t2_,
          cross_point_,
          point_color,
          true
        );
      }
      // Пресуппозиция
      if (
        typeof model["Presupp"] !== "undefined" &&
        typeof model["Presupp"][0] !== "undefined"
      ) {
        // alert("Presupp!");
        drawModelLine(
          graph_context,
          X_right,
          step,
          min_v,
          max_v,
          fieldHeight,
          model,
          "t1p",
          "t3p",
          "black" //"rgba(255,255,0,0.7)"
        );
        drawModelLine(
          graph_context,
          X_right,
          step,
          min_v,
          max_v,
          fieldHeight,
          model,
          "t2p",
          "t4p",
          "black" //"rgba(255,255,0,0.7)"
        );
      }
    } //  if (!selectedOnly || isActive) {
  }
  function drawModelLevelsAndSize(
    graph_context,
    X_right,
    step,
    min_v,
    max_v,
    fieldHeight,
    model
  ) {
    if (typeof model["levels"] === "undefined") return false;
    var aimField = $('input[name="AimshowSwith"]:checked').val();
    if (typeof model["levels"][aimField] !== "undefined") {
      let y1 = model["levels"][aimField]["lvl_0"];
      let x1 = model["levels"][aimField]["bar_0"];
      //    alert("x1="+x1+" y1="+y1);
      x1 = X_right - (Data_settings.cur_bar - x1) * step;
      y1 = Graph_settings.top + ((max_v - y1) / (max_v - min_v)) * fieldHeight;
      //    alert("coord_x1="+x1+" coord_y1="+y1);
      let dx = model["levels"][aimField]["size_time"] * step;
      let dy = model["levels"][aimField]["size_level"];
      dy = (dy / (max_v - min_v)) * fieldHeight * -1;
      for (let lvl in model["levels"][aimField]) {
        if (lvl.substr(0, 3) == "lvl" && lvl !== "lvl_0") {
          let level = model["levels"][aimField][lvl];
          level =
            Graph_settings.top +
            ((max_v - level) / (max_v - min_v)) * fieldHeight;
          line_ep(
            graph_context,
            Graph_settings.left,
            level,
            Graph_settings.width - Graph_settings.right,
            level,
            Model_colors[lvl]
          );
        }
      }
      graph_context.strokeStyle = Model_colors["modelSize"];
      graph_context.strokeRect(x1, y1, dx, dy);
  
      //graph_context.fillText(aimField+" "+model['levels'][aimField]['lvl_0'], 100, 200);
    }
  }
  function drawModelLine(
    graph_context,
    X_right,
    step,
    min_v,
    max_v,
    fieldHeight,
    model,
    p1_name,
    p2_name,
    point_color,
    drawLevel = false
  ) {
    let x1, x2, y1, y2;
    var type_ = "Normal";
    if (p1_name.substr(p1_name.length - 1) === "p") {
      //alert("Presupp in drawModelLine ");
      type_ = "Presupp";
      x1 =
        X_right - (Data_settings.cur_bar - model["Presupp"][0][p1_name]) * step;
    } else x1 = X_right - (Data_settings.cur_bar - model[p1_name]) * step;
    price_ = getModelPrice(model, p1_name);
    y1 = Graph_settings.top + ((max_v - price_) / (max_v - min_v)) * fieldHeight;
    if (p2_name == "_cross_point" || p2_name == "_cross_point2") {
      x2 =
        X_right - (Data_settings.cur_bar - barCP(model["param"][p2_name])) * step; // расстояние от бара, содержащего кросспоинт до последнего бара графика
      y2 =
        Graph_settings.top +
        ((max_v - priceCP(model["param"][p2_name])) / (max_v - min_v)) * // то же самое по вертикали
          fieldHeight;
    } else {
      y2 =
        Graph_settings.top +
        ((max_v - getModelPrice(model, p2_name)) / (max_v - min_v)) * fieldHeight;
  
      // if (p2_name == "auxP6" || p2_name == "calcP6" || p2_name == "auxP6'") {
      if (
        p2_name == "auxP6" ||
        p2_name == "calcP6" ||
        p2_name == 'calcP6"' ||
        p2_name == "auxP6'"
      ) {
        x2 =
          X_right -
          (Data_settings.cur_bar - model["param"][p2_name + "t"]) * step;
        if (drawLevel) {
          graph_context.fillText(model["param"][p2_name], x2 + 1, y2 - 3);
          let level_from = X_right - (Data_settings.cur_bar - model["t4"]) * step;
          let level_to =
            Math.abs(model["param"][p2_name + "t"] - model["t1"]) * 3 +
            Number(model["param"][p2_name + "t"]);
          //console.log(barCP(model['param'][p2_name]));
          //alert(level_to);
          level_to = X_right - (Data_settings.cur_bar - level_to) * step;
          line_ep(graph_context, level_from, y2, level_to, y2, point_color);
        }
      } else {
        if (p2_name.substr(p2_name.length - 1) === "p")
          x2 =
            X_right -
            (Data_settings.cur_bar - model["Presupp"][0][p2_name]) * step;
        else x2 = X_right - (Data_settings.cur_bar - model[p2_name]) * step;
      }
    }
    //if(model['t3']==904)alert("p1_name="+p1_name+" p2_name="+p2_name+" x1="+x1+" y1="+y1+"  x2="+x2+" y2="+y2);
    if (type_ == "Presupp") {
      line_ep(graph_context, x1, y1, x2, y2, point_color);
  
      return;
    }
    // if(p2_name=="t6мп")console.log(x1,y1,x2,y2);
    if (
      (p1_name == "t1" && (p2_name == "t3" || p2_name == "t3'")) ||
      (p2_name == "t4" && (p1_name == "t2" || p1_name == "t2'"))
    ) {
      let x2_ = x1 + 2000;
      let y2_ = y1 + ((y2 - y1) / (x2 - x1)) * 2000;
      line_ep(graph_context, x1, y1, x2_, y2_, point_color);
    } else line_ep(graph_context, x1, y1, x2, y2, point_color);
  }
  
  function getModelPrice(model, name) {
    // if (name == "calcP6" || name == "auxP6" || name == "auxP6'")
    if (
      name == "calcP6" ||
      name == 'calcP6"' ||
      name == "auxP6" ||
      name == "auxP6'"
    )
      // j если имя точки равно одному из  указанных
      return model["param"][name]; // j возвращает параметр, соответсвующий имени, который содержит уровень т.6
    if (model["v"] == "low") {
      if (name == "3" || name == "t1") return Data[model["t1"]].low;
      if (name == "2") return Data[model["2"]].high;
      if (name == "t2") return Data[model["t2"]].high;
      if (name == "t2'") return Data[model["t2'"]].high;
      if (name == "t3") return Data[model["t3"]].low;
      if (name == "t3-") return Data[model["t3-"]].low;
      if (name == "t3'") return Data[model["t3'"]].low;
      if (name == "t3'мп") return Data[model["t3'мп"]].low;
      if (name == "t3'мп5'") return Data[model["t3'мп5'"]].low;
      if (name == "t4") return Data[model["t4"]].high;
      if (name == "t5") return Data[model["t5"]].low;
      if (name == "t5'") return Data[model["t5'"]].low;
      if (name == 't5"') return Data[model['t5"']].low;
      if (name == 't3"') return Data[model['t3"']].low;
      if (name == "A2Prev") return Data[model["A2Prev"]].high;
      if (name == "t1p") return Data[model["Presupp"][0]["t1p"]].high;
      if (name == "t3p") return Data[model["Presupp"][0]["t3p"]].high;
      if (name == "t2p") return Data[model["Presupp"][0]["t2p"]].low;
      if (name == "t4p") return Data[model["Presupp"][0]["t4p"]].low;
    } else {
      if (name == "3" || name == "t1") return Data[model["t1"]].high;
      if (name == "2") return Data[model["2"]].low;
      if (name == "t2") return Data[model["t2"]].low;
      if (name == "t2'") return Data[model["t2'"]].low;
      if (name == "t3") return Data[model["t3"]].high;
      if (name == "t3-") return Data[model["t3-"]].high;
      if (name == "t3'") return Data[model["t3'"]].high;
      if (name == "t3'мп") return Data[model["t3'мп"]].high;
      if (name == "t3'мп5'") return Data[model["t3'мп5'"]].high;
      if (name == "t4") return Data[model["t4"]].low;
      if (name == "t5") return Data[model["t5"]].high;
      if (name == "t5'") return Data[model["t5'"]].high;
      if (name == 't5"') return Data[model['t5"']].high;
      if (name == 't3"') return Data[model['t3"']].high;
      if (name == "A2Prev") return Data[model["A2Prev"]].low;
      if (name == "t1p") return Data[model["Presupp"][0]["t1p"]].low;
      if (name == "t3p") return Data[model["Presupp"][0]["t3p"]].low;
      if (name == "t2p") return Data[model["Presupp"][0]["t2p"]].high;
      if (name == "t4p") return Data[model["Presupp"][0]["t4p"]].high;
    }
    alert("getModelPrice - неопределенный ключ: " + name);
    return false;
  }
  
  function priceCP(str) {
    //    let t6=model[t6name];
    let pos1 = str.indexOf("(");
    let pos2 = str.indexOf(")");
    return str.substr(pos1 + 1, pos2 - pos1 - 1);
  }
  
  function barCP(str) {
    //let t6=model[t6name];
    let pos1 = str.indexOf("(");
    return str.substr(0, pos1 - 1);
  }
  
  function minModelBar(model) {
    // определение самого левого (меньшего) бара модели
    let min_ = 1000000;
    for (let i in PointsList) {
      if (typeof PointsList[i] !== "undefined")
        if (PointsList[i] !== "t6" && model[PointsList[i]] < min_)
          min_ = model[PointsList[i]];
    }
    return min_;
  }
  
  function maxModelBar(model) {
    // определение самого правого (большего) бара модели
    let max_ = -1000000;
    for (let i in PointsList) {
      if (typeof PointsList[i] !== "undefined")
        if (PointsList[i] !== "t6" && model[PointsList[i]] > max_)
          max_ = model[PointsList[i]];
    }
    return max_;
  }