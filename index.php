<!DOCTYPE html>
<html>

<head>
    <meta charset='UTF-8'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/mobile.css">
    <!-- Подключение стилей для кастомной полосы прокрутки -->
    <link rel="stylesheet" href="css/scrollbar-custom.css">
    <!-- <script src="http://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js"></script> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <!-- <script src='js/moving_box_css.js'></script> -->

    <script>
        //скрипт для передачи параметров для запуска страницы
        ver = "1.0";
    </script>
    <title>sandbox</title>
    <style>
        :root {
            --brand-dark-blue: #1A237E;
            --brand-dark-blue-hover: #283593;
            --brand-gold: #C4A66A;
            --brand-gold-highlight: #FFF8E1;
            --brand-blue-light-bg: #E8EAF6;
            --text-primary: #1A237E;
            --text-secondary: #4A5568;
            --text-light: #64748b;
            --border-color: #E2E8F0;
            --border-light: #edf2f7;
            --background-main: #f8fafc;
            --background-alt: #f0f5fa;
            --background-content: #ffffff;
        }
        
        body {
            font-family: "Inter", -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--background-main);
            color: var(--text-secondary);
            line-height: 1.5;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23e6ecf5' fill-opacity='0.4'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .grid-container {
            display: grid;
            grid-template-columns: 375px 1fr;
            grid-template-rows: auto auto 1fr;
            grid-template-areas: 
                "header header"
                "params datasource"
                "params maincontent";
            min-height: 100vh;
            gap: 10px;
            position: relative;
        }

        @media screen and (max-width: 768px) {
            .grid-container {
                grid-template-columns: 1fr;
                grid-template-areas: 
                    "header"
                    "datasource"
                    "params"
                    "maincontent";
            }

            .params-area {
                border-right: none;
                border-bottom: 1px solid var(--border-color);
            }
        }

        .header {
            width: 100%;
            min-height: 60px;
            background-color: var(--background-content);
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .header .container_header {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            margin-left: 0px;
            padding-top: 5px;
            padding-bottom: 5px;
        }
        
        .nav-right {
            display: flex;
            font-size: 12px;
            line-height: 14px;
            color: var(--text-primary);
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .nav-right li {
            padding-top: 5px;
            padding-right: 15px;
            list-style-type: none;
        }
        
        .nav-right li:not(:first-child) {
            padding-left: 15px;
            padding-bottom: 5px;
        }
        
        .nav-right li:not(:last-child) {
            border-right: 1px solid var(--border-color);
        }
        
        .nav-right li:hover {
            font-weight: 500;
            font-size-adjust: 0.6;
            color: var(--brand-dark-blue-hover);
        }
        
        .nav-right li a {
            text-decoration: none;
            color: var(--text-primary);
        }
        
        .nav-right li a:hover {
            color: var(--brand-dark-blue-hover);
        }

        .header-area {
            grid-area: header;
        }

        .params-area {
            grid-area: params;
            padding: 10px;
            border-right: 1px solid var(--border-color);
            background-color: var(--background-content);
            overflow-y: auto;
            height: 100%;
            min-height: 100%;
            display: flex;
            flex-direction: column;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border-radius: 8px;
        }

        .datasource-area {
            grid-area: datasource;
            padding: 10px;
            background-color: var(--background-content);
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .maincontent-area {
            grid-area: maincontent;
            padding: 10px;
            display: flex;
            flex-direction: column;
            background-color: var(--background-content);
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .desk {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        #canvas-wrapper {
            width: 100%;
            flex-grow: 1;
            border: 1px solid var(--border-light);
            border-radius: 4px;
        }
        
        .sticky-buttons {
            position: sticky;
            top: 10px;
            background-color: var(--background-content);
            padding: 10px 0;
            z-index: 10;
            border-bottom: 1px solid var(--border-light);
        }
        
        .build-btn {
            margin-bottom: 5px;
            width: 100%;
            padding: 10px;
            background-color: var(--brand-dark-blue);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.25s;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 1px 3px rgba(26, 35, 126, 0.25);
        }
        
        .build-btn:hover {
            background-color: var(--brand-dark-blue-hover);
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(26, 35, 126, 0.3);
        }
        
        .log-btn {
            margin-top: 10px;
            background-color: var(--background-alt);
            border: 1px solid var(--border-color);
            padding: 8px 10px;
            cursor: pointer;
            width: 100%;
            border-radius: 5px;
            transition: all 0.25s;
            font-size: 14px;
        }
        
        .log-btn:hover {
            background-color: var(--brand-blue-light-bg);
            border-color: var(--brand-dark-blue);
        }
        
        .params-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            background-color: var(--background-content);
        }
        
        .params-table td {
            padding: 8px;
            border-bottom: 1px solid var(--border-light);
        }
        
        .params-table tr:nth-child(even) {
            background-color: var(--background-alt);
        }
        
        #right-block {
            display: flex;
            flex-direction: column;
            height: 100%;
            min-height: 100%;
            flex-grow: 1;
        }
        
        #model-info {
            flex-grow: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }
        
        #model-info-table {
            flex-grow: 1;
        }
        
        input[type="radio"], input[type="checkbox"] {
            margin-right: 5px;
        }
        
        select, input[type="text"] {
            padding: 8px 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            margin: 5px 0;
            font-size: 14px;
        }
        
        select:focus, input[type="text"]:focus {
            outline: none;
            border-color: var(--brand-dark-blue);
            box-shadow: 0 0 0 3px rgba(26, 35, 126, 0.15);
        }
        
        button {
            background-color: var(--brand-dark-blue);
            color: white;
            border: none;
            border-radius: 5px;
            padding: 8px 12px;
            cursor: pointer;
            transition: all 0.25s;
            font-size: 14px;
        }
        
        button:hover {
            background-color: var(--brand-dark-blue-hover);
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(26, 35, 126, 0.3);
        }
        
        .next-prev-model-btns {
            margin: 10px 0;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        #source-switch, #showSwitch {
            margin-bottom: 10px;
            padding: 10px;
            background-color: var(--background-alt);
            border-radius: 4px;
        }
        
        #active-bar {
            font-weight: bold;
            color: var(--text-primary);
            padding: 10px;
            border-radius: 4px;
            background-color: var(--brand-blue-light-bg);
            margin-bottom: 10px;
        }
        
        hr {
            border: none;
            border-top: 1px solid var(--border-light);
            margin: 10px 0;
        }
        
        .scroll-bar-container {
            width: 100%;
            padding: 10px 0;
        }
        
        #scroll-bar {
            width: 100%;
            height: 10px;
        }
        
        #bar-info, #debug {
            margin-top: 10px;
            padding: 10px;
            background-color: var(--background-alt);
            border-radius: 4px;
            font-size: 13px;
        }
        
        .dop-info {
            display: flex;
            align-items: center;
            margin-top: 10px;
            padding: 10px;
            background-color: var(--background-alt);
            border-radius: 4px;
        }
        
        .parent_wait {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            visibility: hidden;
        }
        
        .block_wait {
            background-color: var(--background-content);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        #loader-text {
            font-weight: bold;
            text-align: center;
            margin: 0;
        }
        
        /* Custom styling for canvas and visualizations */
        #graph-cover, #line-g, #line-v, #last-price, #point-price, #from-to-text, #candle-info {
            position: absolute;
            pointer-events: none;
        }
        
        #source-forex, #source-saves, #source-mysql {
            padding: 10px;
            background-color: var(--background-alt);
            border-radius: 4px;
            margin-top: 5px;
        }
        
        @media screen and (max-width: 991px) {
            .header .container_header {
                padding: 0 15px;
                flex-direction: column;
                align-items: center;
            }
            
            .nav-right {
                margin: 10px 0;
                width: 100%;
                justify-content: center;
            }
            
            .logo {
                margin: 10px 0;
            }
            
            .logo img {
                max-width: 150px;
                height: auto;
            }
            
            button, .build-btn, .log-btn {
                font-size: 13px;
                padding: 7px 10px;
            }
            
            .next-prev-model-btns {
                flex-direction: column;
                align-items: stretch;
            }
        }
        
        /* Additional CSS for new components */
        .form-group {
            margin-bottom: 10px;
        }
        
        .form-group label {
            display: inline-block;
            margin-right: 5px;
            font-size: 14px;
        }
        
        .button-group {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        
        .content-description {
            margin-bottom: 15px;
            padding: 10px;
            background-color: var(--brand-blue-light-bg);
            border-radius: 4px;
            font-size: 14px;
        }
        
        .error-message {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 12px 16px;
            margin: 10px 0;
            border-radius: 4px;
        }
        
        .warning-message {
            color: #856404;
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            padding: 12px 16px;
            margin: 10px 0;
            border-radius: 4px;
        }
        
        .footer {
            background-color: var(--background-content);
            border-top: 1px solid var(--border-color);
            padding: 15px 0;
            text-align: center;
            font-size: 12px;
            color: var(--text-light);
            margin-top: auto;
        }
        
        .footer .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        h3 {
            font-size: 16px;
            color: var(--text-primary);
            margin: 0 0 10px 0;
            font-weight: 600;
        }
    </style>
</head>

<!-- <body onmousemove='body_m_move(event)' onmouseup='body_m_up(event)' onmousedown='body_m_down(event)'> -->

<div class="grid-container">
    <!-- HEADER AREA -->
    <header class="header header-area">
        <div class="container_header">
            <a class="logo" href="#">
                <img src="/public/images/SANDBOX.png" alt="Sandbox" />
            </a>

            <ul class="nav-right">
                <li><a href="index.php">Home</a></li>
                <li><a href="/infobase/tables.php">Infobase</a></li>
                <li><a href="https://marketpawns.com">Marketpawns</a></li>
                <li><a href="https://wiki.marketpawns.com/index.php?title=Main_Page">Wiki</a></li>
                <li><a href="https://github.com/adminprism/Sandbox" target="_blank">GitHub</a></li>
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
    <div class="params-area">
        <div id="right-block" oncontextmenu="debug_on_off(event)">
            <div id="active-bar">Chosen bar: <span>0</span></div>
            <hr />
            
            <table class="params-table">
                <tr>
                    <td colspan="2">
                        <form id="form-mode">
                            <input id="rb-mode1" type="radio" name="calc-mode" value="mode1">show all models</input><br>
                            <input id="rb-mode2" type="radio" name="calc-mode" value="mode2" checked>find last (low + high)</input><br>
                            <input id="rb-mode3" type="radio" name="calc-mode" value="mode3">selected bar as t.1</input><br>
                        </form>
                    </td>
                </tr>
            </table>
            
            <!-- Закрепленный блок с кнопками -->
            <div class="sticky-buttons">
                <button class="build-btn" onclick="build_models(1)">Calculate Algorythm_1 models</button>
                <button class="build-btn" onclick="build_models(2)">Calculate Algorythm_2 models</button>
                <button class="log-btn" onclick="openDebugLog()">Open Log</button>
            </div>
            
            <div id="model-info"> 
                <!-- Контент для информации о модели будет отображаться в виде таблицы -->
                <table class="params-table" id="model-info-table">
                    <!-- Данные будут добавлены динамически JavaScript'ом -->
                </table>
            </div>
        </div>
    </div>

    <!-- DATA SOURCE AREA -->
    <div class="datasource-area">
        <div id="source-switch">
            <h3>Data source:</h3>
            <form id="form-source">
                <input id="rb-forex" type="radio" name="source" value="1" onclick="changeSource('forex')"> FOREX data (Finam) online</input>
                <input id="rb-saves" type="radio" name="source" value="2" checked onclick="changeSource('saves')"> Alpari saved charts</input>
                <input id="rb-mysql" type="radio" name="source" value="2" onclick="changeSource('mysql')"><span id="rb-mysql-label"> Models and charts from MySql DB</span></input>
            </form>
            <input id="chk-active-only" type="checkbox" name="source" value="active_only" checked onclick="changeChkActiveMOdelsOnly()"> For unselected models, show only t.1</input>
        </div>
        <hr />
        <div id="source-forex">
            <div class="form-group">
                <label>Timeframe:</label>
                <select id="select-interval" name="interval" size="1">
                    <option value="test1" selected>test1</option>
                    <option value="test2">test2</option>
                    <-- filled in by the script in Ready-->
                </select>
            </div>
            <div class="form-group">
                <label>Trading tool:</label>
                <select id="select-pair" name="pair" size="1">
                    <option value="BTCUSDT" selected>BTC_USDT</option>
                    <option value="ETHUSDT">ETH_USDT</option>
                    <-- filled in by the script in Ready-->
                </select>
            </div>

            <button id="get-data-btn" onclick="get_candles('forex')">Request candlestick chart FOREX</button>
        </div>
        <div id="source-saves">
            <?php
            $filelist = glob("saved_charts/*.csv");
            sort($filelist);
            if (!$filelist) echo "No saved charts";
            else {
                echo '<div class="form-group">';
                echo '<select id="select-chart" name="chart" size="1"  >\n';
                $i = 0;
                foreach ($filelist as $filename) {
                    $i++;
                    $pos = strpos($filename, "/");
                    $fn = substr($filename, $pos + 1);
                    echo '<option value="' . $fn . '" ' . (($i == 1) ? 'selected' : '') . '>' . $fn . '</option>\n';
                }
                echo "</select>\n";
                echo '</div>';
                
                echo '<div class="form-group">';
                echo '<label id="lastBar4get_candles_label">Data/time of the last bar:</label>';
                echo '<input id="lastBar4get_candles" type="text">';
                echo '</div>';
                
                echo '<div class="form-group">';
                echo '<label id="nBars4get_candles_label">Bars quantity:</label>';
                echo '<input id="nBars4get_candles" type="text" value="1000" size="5">';
                echo '</div>';
                
                echo '<button id="get-data-btn2" onclick="get_candles(\'saves\')">Request Alpari MT saved charts</button>';

                echo '<div id="showPages" class="button-group">';
                echo '<button onclick="get_more_candles(\'saves\', firstBarTime, \'prev\')">Prev Period</button>';
                echo '<button onclick="get_more_candles(\'saves\', firstBarTime, \'next\')">Next Period</button>';
                echo '</div>';
            }
            ?>
        </div>
        <div id="source-mysql">
            <?php
            define("WRITE_LOG", 0);
            require_once 'login_4js.php';
            
            if (isset($Last_Error) && $Last_Error == "") {
                if (!$connection) {
                    echo "<div class='error-message'>Database connection failed: " . mysqli_connect_error() . "</div>";
                } elseif (!function_exists('queryMysql')) {
                    echo "<div class='error-message'>Функция queryMysql() не найдена!</div>";
                } else {
                    try {
                        $sql = "SELECT name FROM chart_names ORDER BY name";
                        $result = $connection->query($sql);

                        if (!$result) {
                            throw new Exception("SQL ERROR: " . $connection->error);
                        }

                        if (!$result || $result->num_rows == 0) {
                            echo "<div class='warning-message'>No data in DB</div>";
                        } else {
                            echo '<div class="form-group">';
                            echo '<select id="select-name" name="name" size="1">';
                            while ($Rec = $result->fetch_assoc()) {
                                echo '<option value="' . htmlspecialchars($Rec['name']) . '" selected>' . htmlspecialchars($Rec['name']) . '</option>';
                            }
                            echo '</select>';
                            echo '</div>';
                            
                            echo '<div class="form-group">';
                            echo '<label id="lastBar4get_fragment_label">Data/time of the last bar:</label>';
                            echo '<input id="lastBar4get_fragment" type="text">';
                            echo '</div>';
                            
                            echo '<div class="form-group">';
                            echo '<label id="modelId4get_fragment_label">Models Id:</label>';
                            echo '<input id="modelId4get_fragment" type="text">';
                            echo '</div>';
                            
                            echo '<div class="form-group">';
                            echo '<label id="nBars4get_fragment_label">Bars quantity:</label>';
                            echo '<input id="nBars4get_fragment" type="text" value="1000" size="5">';
                            echo '</div>';
                            
                            echo '<button id="get-data-btn3" onclick="get_fragment()">Request fragment from DB</button>';
                            
                            echo '<div id="showLevels" class="form-group">';
                            echo '   <label>Show levels:</label>';
                            echo '<form id="lvl-switch">';
                            echo '<input id="showAim0" type="radio" name="AimshowSwith" value="none" onclick="drawGraph()">No</input>';
                            echo '<input id="showAim1" type="radio" name="AimshowSwith" value="P6aims" onclick="drawGraph()">P6aims</input>';
                            echo '<input id="showAim2" type="radio" name="AimshowSwith" value=\'P6aims' . '"' . '\' onclick="drawGraph()">P6aims"</input>';
                            echo '<input id="showAim3" type="radio" name="AimshowSwith" value="auxP6aims" onclick="drawGraph()">auxP6aims</input>';
                            echo '<input id="showAim4" type="radio" name="AimshowSwith" value="auxP6aims\'" onclick="drawGraph()">auxP6aims\'</input>';
                            echo '</form>';
                            echo '</div>';
                        }
                    } catch (Exception $e) {
                        echo "<div class='error-message'>Exception: " . $e->getMessage() . "</div>";
                    }
                }
            } else {
                echo "<div class='error-message'>Database connection error: " . (isset($Last_Error) ? $Last_Error : "Unknown error") . "</div>";
            }
            ?>
        </div>
    </div>

    <!-- MAIN CONTENT AREA -->
    <div class="maincontent-area">
        <div class="desk">
            <div class="content-description">
                <strong>To move chart:</strong> drag it with mouse <strong>Scale:</strong> Mouse wheel
            </div>
            <div class="next-prev-model-btns">
                <button onclick="switchModels('prev')"><strong>previous t.1</strong></button> 
                <button id="switch-model-btn" disabled onclick="switchModels('switch')"><strong>switch models</strong></button>
                <button onclick="switchModels('next')"><strong>next t.1</strong></button>
                <div class="form-group">
                    <input id="min_max_price_check" type="checkbox" name="min_max" onclick="drawGraph()">
                    <label id="min_v_label">min price:</label>
                    <input id="min_v_text" type="text" size="7px">
                    <label id="max_v_label">max price:</label>
                    <input id="max_v_text" type="text" size="7px">
                </div>
            </div>
            <div id="showSwitch">
                <h3>Show models:</h3>
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
            <div class="scroll-bar-container">
                <input type="range" id="scroll-bar" min="0" max="1000" value="0" oninput="handleScrollBarChange(event)" step="1">
            </div>
            <div id="bar-info">bar-info</div>

            <div class="dop-info">
                <input id="chk-log" class="chk-log" type="checkbox" name="show-detailed-log" onclick="toggleDetailedLog()">
                <label for="chk-log">Show detailed log</label>
                <span id="info-timing"></span>
                <span id="info-log"></span>
            </div>

            <div id="service"></div>
            <div id="debug">
                debug
            </div>
        </div>
    </div>
</div>

<!-- Loading animation overlay -->
<div class="parent_wait">
    <div class="block_wait">
        <p id="loader-text">0.0</p>
    </div>
</div>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <p>© <?php echo date('Y'); ?> Market Pawns. All rights reserved.</p>
    </div>
</footer>

<script src="js/functions.js"></script>
<!-- Подключение обработчика полосы прокрутки перед main.js -->
<script src="js/scroll-handler.js"></script>
<script src="js/scroll-optimizer.js"></script>
<script src="js/landscape-optimizer.js"></script>
<script src="js/main.js"></script>
<script src="js/drawModels.js"></script>
<script>
    // Дополнительный JavaScript для отображения параметров модели в таблице
    document.addEventListener("DOMContentLoaded", function() {
        // Функция для перехвата данных и отображения их в таблице
        const originalModelInfoInnerHTML = Object.getOwnPropertyDescriptor(
            Object.getPrototypeOf(document.getElementById('model-info')), 
            'innerHTML'
        );
        
        if(originalModelInfoInnerHTML) {
            Object.defineProperty(document.getElementById('model-info'), 'innerHTML', {
                set: function(html) {
                    // Если HTML содержит структурированные данные (предполагаем)
                    if(html.includes(':')) {
                        let tableHTML = '';
                        // Разбиваем HTML по строкам и обрабатываем каждую строку как параметр
                        const lines = html.split('<br>');
                        lines.forEach(line => {
                            if(line.trim()) {
                                const parts = line.split(':');
                                if(parts.length >= 2) {
                                    const paramName = parts[0].trim();
                                    const paramValue = parts.slice(1).join(':').trim();
                                    tableHTML += `<tr><td>${paramName}</td><td>${paramValue}</td></tr>`;
                                } else {
                                    tableHTML += `<tr><td colspan="2">${line}</td></tr>`;
                                }
                            }
                        });
                        
                        document.getElementById('model-info-table').innerHTML = tableHTML;
                    } else {
                        // Если это не структурированные данные, просто отображаем их
                        originalModelInfoInnerHTML.set.call(this, html);
                    }
                },
                get: function() {
                    return originalModelInfoInnerHTML.get.call(this);
                }
            });
        }
        
        // Initialize radio buttons based on URL parameters or defaults
        initializeInterface();
    });
    
    // Функция для открытия debug-лога
    function openDebugLog() {
        // Имитируем клик правой кнопкой мыши по области Chosen bar
        const event = new MouseEvent('contextmenu', {
            bubbles: true,
            cancelable: true,
            button: 2  // правая кнопка мыши
        });
        
        // Вызываем функцию debug_on_off, если она существует, иначе просто показываем/скрываем debug-div
        if (typeof debug_on_off === 'function') {
            // Передаем фейковый event с preventDefault для имитации правого клика
            const fakeEvent = { preventDefault: function() {} };
            debug_on_off(fakeEvent);
        } else {
            // Альтернативный вариант - просто переключаем видимость debug-div
            const debugDiv = document.getElementById('debug');
            if (debugDiv) {
                debugDiv.style.display = debugDiv.style.display === 'none' ? 'block' : 'none';
            }
        }
    }
    
    // Функция для инициализации интерфейса при загрузке страницы
    function initializeInterface() {
        // Show appropriate data source section based on radio selection
        const sourceRadios = document.querySelectorAll('[name="source"]');
        for(let radio of sourceRadios) {
            if(radio.checked) {
                changeSource(radio.getAttribute('onclick').match(/'([^']+)'/)[1]);
                break;
            }
        }
        
        // Set default algorithm radio button
        const algRadio = document.getElementById('showAlg1');
        if(algRadio) {
            algRadio.checked = true;
            if(typeof switchAlg2show === 'function') {
                switchAlg2show(1);
            }
        }
        
        // Adjust canvas size to container
        resizeCanvas();
    }
    
    // Resize canvas on window resize
    window.addEventListener('resize', resizeCanvas);
    
    function resizeCanvas() {
        const canvas = document.getElementById('graph');
        const container = document.getElementById('canvas-wrapper');
        
        if(canvas && container) {
            canvas.width = container.clientWidth;
            canvas.height = container.clientHeight || 400; // Default height if container height is 0
            
            // Redraw graph if the function exists
            if(typeof drawGraph === 'function') {
                drawGraph();
            }
        }
    }
</script>
</body>

</html>