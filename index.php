<!DOCTYPE html>
<html>

<div class="window">

    <head>
        <meta charset='UTF-8'>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="css/style.css">
        <!-- <script src="http://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js"></script> -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
        <!-- <script src='js/moving_box_css.js'></script> -->

        <script>
            //скрипт для передачи параметров для запуска страницы
            ver = "1.0";
        </script>
        <title>sandbox</title>
    </head>

    <!-- <body onmousemove='body_m_move(event)' onmouseup='body_m_up(event)' onmousedown='body_m_down(event)'> -->

        <header class="header">
            <div class="container_header">
                <a class="logo" href="#">
                    <!-- <img src="/public/images/logo.png" alt="" /> -->
                    <img src="/public/images/SANDBOX.png" alt="" />
                </a>

                <ul class="nav-right">
                    <li><a href="https://marketpawns.com">Marketpawns</a></li>
                    <li><a href="https://wiki.marketpawns.com/index.php?title=Main_Page">Wiki</a></li>
                    <!-- <li data-bs-toggle="modal" data-bs-target="#registerModal"><a href="#">Register</a></li>
                        <li data-bs-toggle="modal" data-bs-target="#signinModal"><a href="#">Sign in</a></li> -->
                </ul>
            </div>

        </header>
        <!-- <div id='mB1' class='moving-box'>
            <p class='moving-box-title'>Information</p>
            <div class='moving-box-content'>
                <div id="debugPopUp">
                    <div class='popup-element' id="debugPopUp-top">

                        <input id="popUpText" type="text" required="">
                        <button onclick="show_model_for_key()">Show models</button>
                        <button onclick="show_resAJAX_info('info')">AJAX info</button>

                    </div>
                    <div class='popup-element' id="debugPopUp-main">
                        (пусто)
                    </div>
                </div>
            </div>
        </div> -->
        <div id="source-switch">Data source:&nbsp&nbsp&nbsp
            <form id="form-source">
                <input id="rb-forex" type="radio" name="source" value="1" onclick="changeSource('forex')"> FOREX data (Finam) online&nbsp&nbsp</input>
                <input id="rb-saves" type="radio" name="source" value="2" checked onclick="changeSource('saves')"> Alpari saved charts&nbsp&nbsp&nbsp</input>
                <input id="rb-mysql" type="radio" name="source" value="2" onclick="changeSource('mysql')"><span id="rb-mysql-label"> Models and charts from MySql DB&nbsp&nbsp&nbsp&nbsp&nbsp</span></input>
            </form>
            <input id="chk-active-only" type="checkbox" name="source" value="active_only" checked onclick="changeChkActiveMOdelsOnly()"> For unselected models, show only t.1</input>
        </div>
        <hr />
        <div id="source-forex">
            Timeframe:
            <select id="select-interval" name="interval" size="1">
                <option value="test1" selected>test1</option>
                <option value="test2">test2</option>
                <-- filled in by the script in Ready-->
            </select>
            Trading tool:
            <select id="select-pair" name="pair" size="1">
                <option value="BTCUSDT" selected>BTC_USDT</option>
                <option value="ETHUSDT">ETH_USDT</option>
                <-- filled in by the script in Ready-->
            </select>

            <button id="get-data-btn" onclick="get_candles('forex')">Request candlestick chart FOREX</button>
            <br>
        </div>
        <div id="source-saves">
            <?php
            $filelist = glob("saved_charts/*.csv");
            sort($filelist);
            if (!$filelist) echo "No saved charts";
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
                echo '<label id="lastBar4get_candles_label">&nbsp Data/time of the last bar:&nbsp</label><input id="lastBar4get_candles" type="text">';
                echo '<label id="nBars4get_candles_label">&nbspBars quantity: &nbsp</label><input id="nBars4get_candles" type="text" value="1000" size="5">';
                echo "&nbsp";
                echo '<button id="get-data-btn2" onclick="get_candles(\'saves\')">&nbsp Request Alpari MT saved charts&nbsp</button>';

                echo '<div id="showPages">';
                echo '<button id="next-prev-page-btns" onclick="get_more_candles(\'saves\', firstBarTime, \'prev\')">&nbsp Prev Period &nbsp</button>';
                echo '<button id="next-prev-page-btns" onclick="get_more_candles(\'saves\', firstBarTime, \'next\')">&nbsp Next Period &nbsp</button>';
                echo '</div>';
            }
            ?>
        </div>
        <div id="source-mysql">
            <?php
            define("WRITE_LOG", 0);
            require_once 'login_4js.php';
            //echo $connection->error;
            // Проверка на ошибки подключения

            if (isset($Last_Error) && $Last_Error == "") {
                // if ($Last_Error == "") {

                if (!$connection) {
                    echo "❌ Database connection failed: " . mysqli_connect_error() . "<br>";
                } elseif (!function_exists('queryMysql')) {
                    echo "❌ Функция queryMysql() не найдена!<br>";
                } else {
                    // if (!$connection) {
                    //     die("Database connection failed: " . mysqli_connect_error());
                    // }

                    // if (!function_exists('queryMysql')) {
                    //     die("Функция queryMysql() не найдена!");
                    // }

                    var_dump($connection);

                    try {
                        // $result = queryMysql("select name from chart_names order by name");
                        $sql = "SELECT name FROM chart_names ORDER BY name";
                        $result = $connection->query($sql);

                        if (!$result) {
                            throw new Exception("SQL ERROR: " . $connection->error);
                            // die("SQL ERROR: " . $connection->error);
                        }

                        if (!$result || $result->num_rows == 0) {
                            echo "⚠️ WARNING: No data in DB<br>";
                            // echo "ERROR! No data in DB";
                        } else {
                            // else {
                            echo '<select id="select-name" name="name" size="1">';
                            while ($Rec = $result->fetch_assoc()) {
                                //echo $Rec['name']."<br>";
                                echo '<option value="' . htmlspecialchars($Rec['name']) . '" selected>' . htmlspecialchars($Rec['name']) . '</option>';
                                // echo '<option value="' . $Rec['name'] . '" selected>' . $Rec['name'] . '</option>';
                            }
                            echo '</select>';
                            //echo '<script>$("#rb-mysql-env").css("display", "none");</script>';
                            echo '<label id="lastBar4get_fragment_label">&nbspData/time of the last bar:&nbsp</label><input id="lastBar4get_fragment" type="text">';
                            echo '<label id="modelId4get_fragment_label">&nbspModels Id:&nbsp</label><input id="modelId4get_fragment" type="text">';
                            echo '<label id="nBars4get_fragment_label">&nbspBars quantity:&nbsp</label><input id="nBars4get_fragment" type="text" value="1000" size="5">';
                            echo "&nbsp";
                            echo '<button id="get-data-btn3" onclick="get_fragment()">&nbspRequest fragment from DB&nbsp</button>';
                            echo '<div id="showLevels">';
                            echo '   Show levels:';
                            echo '<form id="lvl-switch">';
                            echo '<input id="showAim0" type="radio" name="AimshowSwith" value="none" onclick="drawGraph()">No</input>';
                            echo  '<input id="showAim1" type="radio" name="AimshowSwith" value="P6aims" onclick="drawGraph()">P6aims</input>';
                            echo  '<input id="showAim2" type="radio" name="AimshowSwith" value=\'P6aims' . '"' . '\' onclick="drawGraph()">P6aims"</input>';
                            echo  '<input id="showAim3" type="radio" name="AimshowSwith" value="auxP6aims" onclick="drawGraph()">auxP6aims</input>';
                            echo  '<input id="showAim4" type="radio" name="AimshowSwith" value="auxP6aims\'" onclick="drawGraph()">auxP6aims\'</input>';
                            echo '</form>';
                            echo '</div>';
                        }
                    } catch (Exception $e) {
                        echo "⚠️ Exception: " . $e->getMessage() . "<br>";
                    }
                }
            } else {
                echo "❌ Database connection error: " . (isset($Last_Error) ? $Last_Error : "Unknown error") . "<br>";
            }
            // } else echo $Last_Error;
            ?>
        </div>
        <hr />
        <div class="desk">
            <span><strong>To move chart:</strong> drag it with mouse <strong>Scale: </strong> Mouse wheel </span>
            <div class="next-prev-model-btns">
                <button onclick="switchModels('prev')"><strong>&nbsp previous t.1 &nbsp </button> <button id="switch-model-btn" disabled onclick="switchModels('switch')"><strong>&nbsp switch models &nbsp</strong></button>
                <button onclick="switchModels('next')"><strong>&nbsp next t.1&nbsp</strong></button>
                <input id="min_max_price_check" type="checkbox" name="min_max" onclick="drawGraph()">
                <label id="min_v_label">&nbsp min price:&nbsp</label><input id="min_v_text" type="text" size="7px">
                <label id="max_v_label">&nbsp max price:&nbsp</label><input id="max_v_text" type="text" size="7px">

            </div>
            <div id="showSwitch">
                Show models:
                <form id="alg-switch">
                    <input id="showAlg1" type="radio" name="AlgshowSwith" value="Alg1" onclick="switchAlg2show(1)">Algorythm_1</input>
                    <input id="showAlg2" type="radio" name="AlgshowSwith" value="Alg2" onclick="switchAlg2show(2)">Algorythm_2</input>
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
            <div>
                <input type="range" id="scroll-bar" min="0" max="1000" value="0" oninput="handleScrollBarChange(event)" step="1">
            </div>
            <div id="right-block" oncontextmenu="debug_on_off(event)">
                <div id="active-bar">Chosen bar: <span>0</span></div>
                <hr />
                <form id="form-mode">
                    <input id="rb-mode1" type="radio" name="calc-mode" value="mode1">show all models</input><br>
                    <input id="rb-mode2" type="radio" name="calc-mode" value="mode2" checked>find last (low + high)</input><br>
                    <input id="rb-mode3" type="radio" name="calc-mode" value="mode3">selected bar as t.1</input><br>
                </form>

                <!-- <input id='all-models-checkbox' type='checkbox' name='all_models' value='no' > Показывать все модели, а не только последние -->

                <button class="build-btn" onclick="build_models(1)">Calculate Algorythm_1 models</button>
                <button class="build-btn" onclick="build_models(2)">Calculate Algorythm_2 models</button>
                <div id="model-info"> </div>
            </div>

            <div id="bar-info">bar-info</div>

            <div class="dop-info">
                <!-- Другие элементы внутри dop-info -->

                <input id="chk-log" class="chk-log" type="checkbox" name="show-detailed-log" onclick="toggleDetailedLog()">
                <label for="chk-log">Show detailed log</label>
                <!-- <div id="dop-info">
                <input id="chk-log" type="checkbox" name="log" value="chk-log"">  передавать подробный лог&nbsp&nbsp&nbsp&nbsp&nbsp</input> -->
                <span id=" info-timing"></span>
                <span id="info-log"></span>
            </div>


            <div id="service"></div>
            <div id="debug">
                debug
            </div>
        </div>
</div>

<div class="parent_wait">
    <div class="block_wait">
        <p id="loader-text">0.0</p>
    </div>
</div>





<script src="js/functions.js"></script>
<script src="js/main.js"></script>
<script src="js/drawModels.js"></script>
</body>

</html>