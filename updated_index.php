<!DOCTYPE html>
<html>

<head>
    <meta charset='UTF-8'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src='js/moving_box_css.js'></script>
    <link rel="stylesheet" href="css/style.css">
    <!-- <script src="http://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js"></script> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

    <script>
        //скрипт для передачи параметров для запуска страницы
        ver = "1.0";
    </script>
    <title>Тест - Алгоритм_2 </title>
</head>

<body onmousemove='body_m_move(event)' onmouseup='body_m_up(event)' onmousedown='body_m_down(event)'>
    <div class="window">
        <div id='mB1' class='moving-box'>
            <p class='moving-box-title'>Информация</p>
            <div class='moving-box-content'>
                <div id="debugPopUp">
                    <div class='popup-element' id="debugPopUp-top">

                        <input id="popUpText" type="text" required="">
                        <button onclick="show_model_for_key()">Показать модель</button>
                        <button onclick="show_resAJAX_info('info')">AJAX info</button>

                    </div>
                    <div class='popup-element' id="debugPopUp-main">
                        (пусто)
                    </div>
                    <!-- <div class='popup-element' id="debugPopUp-bottom"></div> -->
                </div>
            </div>
        </div>
        <div id="source-switch">Источник данных:&nbsp&nbsp&nbsp
            <form id="form-source">
                <input id="rb-forex" type="radio" name="source" value="1" onclick="changeSource('forex')"> Данные ФОРЕКС(Финам) online&nbsp&nbsp</input>
                <input id="rb-saves" type="radio" name="source" value="2" checked onclick="changeSource('saves')"> Сохраненные чарты MT Alpari&nbsp&nbsp&nbsp</input>
                <input id="rb-mysql" type="radio" name="source" value="2" onclick="changeSource('mysql')"><span id="rb-mysql-label"> Модели и график из БД MySql&nbsp&nbsp&nbsp&nbsp&nbsp</span></input>
            </form>
            <input id="chk-active-only" type="checkbox" name="source" value="active_only" checked onclick="changeChkActiveMOdelsOnly()"> У невыбранных моделей показывать только т1</input>
        </div>
        <hr />
        <div id="source-forex">
            Интервал:
            <select id="select-interval" name="interval" size="1">
                <option value="test1" selected>test1</option>
                <option value="test2">test2</option>
                <-- заполняется скриптом в Ready-->
            </select>
            Торговый инструмент:
            <select id="select-pair" name="pair" size="1">
                <option value="BTCUSDT" selected>BTC_USDT</option>
                <option value="ETHUSDT">ETH_USDT</option>
                <-- заполняется скриптом в Ready-->
            </select>

            <button id="get-data-btn" onclick="get_candles('forex')">Запросить свечной график FOREX</button>
            <br>
        </div>
        <div id="source-saves">
            <?php
            $filelist = glob("saved_charts/*.csv");
            sort($filelist);
            if (!$filelist) echo "Нет сохраненных чартов.";
            else {
                echo '<select id="select-chart" name="chart" size="1"  >\n';
                $i = 0;
                foreach ($filelist as $filename) {
                    $i++;
                    $pos = strpos($filename, "/");
                    $fn = substr($filename, $pos + 1);
                    echo '<option value="' . $fn . '" ' . (($i == 1) ? 'selected' : '') . '>' . $fn . '</option>\n';
                    //echo $fn . " и его размер: " . filesize($filename) . " байт <br>";
                }
                echo "</select>\n";
                echo '<label id="lastBar4get_candles_label">&nbspДата/время последнего бара:&nbsp</label><input id="lastBar4get_candles" type="text">';
                echo '<label id="nBars4get_candles_label">&nbspКоличество баров:&nbsp</label><input id="nBars4get_candles" type="text" value="1000" size="5">';
                echo "&nbsp";
                echo '<button id="get-data-btn2" onclick="get_candles(\'saves\')">&nbspЗагрузить сохраненный чарт Alpari MT&nbsp</button>';
            }
            ?>
        </div>
        <div id="source-mysql">
            <?php
            define("WRITE_LOG", 0);
            require_once 'login_4js.php';
            //echo $connection->error;
            if ($Last_Error == "") {
                $result = queryMysql("select name from chart_names order by name");
                if (!$result || $result->num_rows == 0) echo "ERROR! Записи не найдены в БД";
                else {
                    echo '<select id="select-name" name="name" size="1">';
                    while ($Rec = $result->fetch_assoc()) {
                        //echo $Rec['name']."<br>";
                        echo '<option value="' . $Rec['name'] . '" selected>' . $Rec['name'] . '</option>';
                    }
                    echo '</select>';
                    //echo '<script>$("#rb-mysql-env").css("display", "none");</script>';
                    echo '<label id="lastBar4get_fragment_label">&nbspДата/время последнего бара:&nbsp</label><input id="lastBar4get_fragment" type="text">';
                    echo '<label id="modelId4get_fragment_label">&nbspId модели:&nbsp</label><input id="modelId4get_fragment" type="text">';
                    echo '<label id="nBars4get_fragment_label">&nbspКоличество баров:&nbsp</label><input id="nBars4get_fragment" type="text" value="1000" size="5">';
                    echo "&nbsp";
                    echo '<button id="get-data-btn3" onclick="get_fragment()">&nbspЗагрузить фрагмент из БД&nbsp</button>';
                    echo '<div id="showLevels">';
                    echo '   Показ уровней:';
                    echo '<form id="lvl-switch">';
                    echo '<input id="showAim0" type="radio" name="AimshowSwith" value="none" onclick="drawGraph()">Нет</input>';
                    echo  '<input id="showAim1" type="radio" name="AimshowSwith" value="P6aims" onclick="drawGraph()">P6aims</input>';
                    echo  '<input id="showAim2" type="radio" name="AimshowSwith" value=\'P6aims' . '"' . '\' onclick="drawGraph()">P6aims"</input>';
                    echo  '<input id="showAim3" type="radio" name="AimshowSwith" value="auxP6aims" onclick="drawGraph()">auxP6aims</input>';
                    echo  '<input id="showAim4" type="radio" name="AimshowSwith" value="auxP6aims\'" onclick="drawGraph()">auxP6aims\'</input>';
                    echo '</form>';
                    echo '</div>';
                }
            } else echo $Last_Error;
            ?>
        </div>

        <hr />
        <div class="desk">
            <span><strong>Сдвиг графика:</strong> перетаскивание мышью, <strong>Масштаб:</strong> колесико мыши</span>
            <div class="next-prev-model-btns">
                <button onclick="switchModels('prev')"><strong>&nbspпредыдущая т.1 <--&nbsp< /strong> </button> <button id="switch-model-btn" disabled onclick="switchModels('switch')"><strong>&nbspперекл. модели&nbsp</strong></button>
                <button onclick="switchModels('next')"><strong>&nbsp--> следующая т.1&nbsp</strong></button>
                <input id="min_max_price_check" type="checkbox" name="min_max" onclick="drawGraph()">
                <label id="min_v_label">&nbspмин.цена:&nbsp</label><input id="min_v_text" type="text" size="7px">
                <label id="max_v_label">&nbspмакс.цена:&nbsp</label><input id="max_v_text" type="text" size="7px">

            </div>
            <div id="showSwitch">
                Показывать модели:
                <form id="alg-switch">
                    <input id="showAlg1" type="radio" name="AlgshowSwith" value="Alg1" onclick="switchAlg2show(1)">Алгоритм_1</input>
                    <input id="showAlg2" type="radio" name="AlgshowSwith" value="Alg2" onclick="switchAlg2show(2)">Алгоритм_2</input>
                </form>
            </div>


            <div id="canvas-wrapper">
                <!-- контент canvas-wrapper формируется скриптом-->
                <canvas id='graph' width='100' height='100'></canvas>
                <div id="graph-cover"></div>
                <div id="line-g"></div>
                <div id="line-v"></div>
                <div id="last-price">last price</div>
                <div id="point-price">point price</div>
                <div id="from-to-text"></div>
                <div id="candle-info">candle-info</div>
            </div>
            <div id="right-block" oncontextmenu="debug_on_off(event)">
                <div id="active-bar">Выбранный бар: <span>0</span>

                </div>
                <hr />
                <form id="form-mode">
                    <input id="rb-mode1" type="radio" name="calc-mode" value="mode1">показывать все модели</input><br>
                    <input id="rb-mode2" type="radio" name="calc-mode" value="mode2" checked>поиск последних (low + high)</input><br>
                    <input id="rb-mode3" type="radio" name="calc-mode" value="mode3">заданный бар в качестве т1</input><br>
                </form>

                <!-- <input id='all-models-checkbox' type='checkbox' name='all_models' value='no' > Показывать все модели, а не только последние -->

                <button class="build-btn" onclick="build_models(1)">Расчет моделей по Алгоритму_1</button>
                <button class="build-btn" onclick="build_models(2)">Расчет моделей по Алгоритму_2</button>
                <div id="model-info">

                </div>
            </div>

            <div id="bar-info">bar-info</div>
            <div id="dop-info">
                <input id="chk-log" type="checkbox" name="log" value="chk-log"">  передавать подробный лог&nbsp&nbsp&nbsp&nbsp&nbsp</input>
                    <span id=" info-timing"></span>
                <span id="info-log"></span>
            </div>


        </div>
    </div>
    <div id="service"></div>
    <div id="debug">
        debug
    </div>

    <div class="parent_wait">
        <div class="block_wait">
            <p id="loader-text">0.0</p>
        </div>
    </div>





    <script src="js/functions.js"></script>
    <script src="js/main.js"></script>
</body>

</html>