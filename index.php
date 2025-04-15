<!DOCTYPE html>
<html>

<head>
    <meta charset='UTF-8'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/mobile.css">
    <!-- Подключение стилей для кастомной полосы прокрутки -->
    <link rel="stylesheet" href="css/scrollbar-custom.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            /* Typography */
            --font-sans: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
            --font-mono: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        }
        
        body {
            font-family: var(--font-sans);
            margin: 0;
            padding: 0;
            background-color: var(--background-main);
            color: var(--text-secondary);
            line-height: 1.5;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23e6ecf5' fill-opacity='0.4'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            font-size: 15px;
        }

        .grid-container {
            display: grid;
            grid-template-columns: minmax(280px, 320px) 1fr;
            grid-template-rows: auto auto 1fr;
            grid-template-areas: 
                "header header"
                "controls controls"
                "params maincontent";
            min-height: 100vh;
            width: 100vw;
            max-width: 100vw;
            gap: 8px;
            position: relative;
            padding: 0;
            margin: 0;
        }

        .controls-area {
            grid-area: controls;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 12px;
            margin: 8px 8px 4px 8px;
        }

        @media screen and (max-width: 1200px) {
            .grid-container {
                grid-template-columns: 280px 1fr;
            }
        }

        @media screen and (max-width: 768px) {
            .grid-container {
                grid-template-columns: 1fr;
                grid-template-areas: 
                    "header"
                    "controls"
                    "params"
                    "maincontent";
                gap: 8px;
            }
            
            .controls-area {
                grid-template-columns: 1fr;
                margin: 8px;
            }

            .params-area {
                border-right: none;
                border-bottom: 1px solid var(--border-color);
                margin: 0 8px;
            }
            
            .maincontent-area {
                max-width: calc(100vw - 16px);
                margin: 0 8px 8px 8px;
            }
        }

        .header {
            width: 100%;
            min-height: 90px;
            background-color: var(--background-content);
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            margin: 0;
            padding: 0;
        }
        
        .header .container_header {
            max-width: 1600px;
            margin: 0 auto;
            padding: 0 25px !important;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 90px;
            box-sizing: border-box;
            width: 100%;
        }
        
        .logo {
            display: flex;
            align-items: center;
            padding-left: 0 !important;
            margin-left: 0 !important;
        }
        
        .logo img {
            height: 75px;
            width: auto;
        }
        
        .nav-right {
            display: flex;
            font-size: 14px;
            line-height: 16px;
            color: var(--text-primary);
            list-style: none;
            padding: 0;
            margin: 0;
            height: 100%;
        }
        
        .nav-right li {
            display: flex;
            align-items: center;
            height: 100%;
            position: relative;
        }
        
        .nav-right li a {
            text-decoration: none;
            color: var(--text-primary);
            padding: 0 20px;
            display: flex;
            align-items: center;
            height: 100%;
            position: relative;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .nav-right li:last-child a {
            padding-right: 0 !important;
        }
        
        .nav-right li a i {
            margin-right: 8px;
            font-size: 16px;
        }
        
        .nav-right li a:hover {
            color: var(--brand-dark-blue-hover);
            background-color: rgba(232, 234, 246, 0.4);
        }
        
        .nav-right li a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background-color: var(--brand-dark-blue);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .nav-right li a:hover::after {
            transform: scaleX(1);
        }
        
        .nav-right li.active a {
            color: var(--brand-dark-blue);
            font-weight: 600;
        }
        
        .nav-right li.active a::after {
            transform: scaleX(1);
        }

        .header-area {
            grid-area: header;
        }

        .params-area {
            grid-area: params;
            padding: 16px;
            border-right: none;
            background-color: var(--background-content);
            overflow-y: auto;
            height: 100%;
            min-height: 100%;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-md);
            border-radius: 8px;
            margin: 0 8px 8px 8px;
        }

        .datasource-area {
            grid-area: datasource;
            padding: 16px;
            background-color: var(--background-content);
            border-radius: 8px;
            box-shadow: var(--shadow-md);
        }

        .maincontent-area {
            grid-area: maincontent;
            padding: 16px;
            display: flex;
            flex-direction: column;
            background-color: var(--background-content);
            border-radius: 8px;
            box-shadow: var(--shadow-md);
            margin: 0 8px 8px 0;
            max-width: calc(100vw - 8px - 320px - 8px);
            width: 100%;
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
            border-radius: 6px;
            min-height: 400px;
            position: relative;
            overflow: hidden;
            background-color: rgba(255, 255, 255, 0.8);
        }
        
        .sticky-buttons {
            position: sticky;
            top: 16px;
            z-index: 900;
            background-color: var(--background-content);
            padding: 16px;
            margin-bottom: 16px;
            box-shadow: var(--shadow-sm);
            border-radius: 6px;
            border: 1px solid var(--border-light);
        }
        
        .build-btn {
            margin-bottom: 10px;
            width: 100%;
            padding: 14px;
            background-color: var(--brand-dark-blue);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.25s;
            font-size: 14px;
            font-weight: 600;
            box-shadow: var(--shadow-sm);
            text-transform: uppercase;
            letter-spacing: 0.03em;
            font-family: var(--font-sans);
        }
        
        .build-btn:hover {
            background-color: var(--brand-dark-blue-hover);
            transform: translateY(-2px);
            box-shadow: 0 3px 5px rgba(26, 35, 126, 0.3);
        }
        
        .log-btn {
            margin-top: 12px;
            background-color: var(--background-alt);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            padding: 12px;
            cursor: pointer;
            width: 100%;
            border-radius: 6px;
            transition: all 0.25s;
            font-size: 14px;
            font-weight: 500;
            letter-spacing: 0.01em;
            font-family: var(--font-sans);
        }
        
        .log-btn:hover {
            background-color: var(--brand-blue-light-bg);
            border-color: var(--brand-dark-blue);
            transform: translateY(-1px);
        }
        
        .params-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            background-color: var(--background-content);
            font-size: 14px;
        }
        
        .params-table td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-light);
            color: var(--text-secondary);
            font-weight: 450;
            letter-spacing: 0.01em;
        }
        
        .params-table tr:first-child td {
            font-weight: 500;
            color: var(--text-primary);
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
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            width: 18px;
            height: 18px;
            border: 2px solid var(--border-color);
            margin-right: 8px;
            position: relative;
            cursor: pointer;
            outline: none;
            transition: all 0.2s ease;
            vertical-align: middle;
            background-color: white;
        }

        input[type="radio"] {
            border-radius: 50%;
        }

        input[type="checkbox"] {
            border-radius: 4px;
        }

        input[type="radio"]:checked, input[type="checkbox"]:checked {
            border-color: var(--brand-dark-blue);
            background-color: var(--brand-dark-blue);
        }

        input[type="radio"]:checked::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: white;
        }

        input[type="checkbox"]:checked::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 12px;
            line-height: 1;
        }

        input[type="radio"]:hover, input[type="checkbox"]:hover {
            border-color: var(--brand-dark-blue-hover);
        }

        input[type="radio"] + label, input[type="checkbox"] + label {
            cursor: pointer;
            font-size: 14px;
            font-weight: 450;
            color: var(--text-secondary);
            vertical-align: middle;
            margin-right: 16px;
            letter-spacing: 0.01em;
        }

        /* Ensure label alignment with custom inputs */
        .form-inline label,
        form label {
            display: inline-flex;
            align-items: center;
            margin-right: 12px;
            font-size: 14px;
        }
        
        select, input[type="text"] {
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            margin: 5px 0;
            font-size: 14px;
            font-family: var(--font-sans);
            background-color: var(--background-content);
            color: var(--text-secondary);
            font-weight: 450;
            letter-spacing: 0.01em;
            box-shadow: var(--shadow-sm);
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
            border-radius: 6px;
            padding: 10px 14px;
            cursor: pointer;
            transition: all 0.25s;
            font-size: 14px;
            font-weight: 500;
            letter-spacing: 0.01em;
            font-family: var(--font-sans);
            box-shadow: var(--shadow-sm);
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
            padding: 12px;
            background-color: var(--background-alt);
            border-radius: 8px;
            border: 1px solid var(--border-light);
        }
        
        #source-switch h3, #showSwitch h3 {
            font-size: 15px;
            color: var(--text-primary);
            margin: 0 0 8px 0;
            font-weight: 600;
            letter-spacing: -0.01em;
            font-family: var(--font-sans);
        }
        
        #form-source input[type="radio"], 
        #alg-switch input[type="radio"] {
            margin-right: 4px;
        }
        
        /* Apply consistent font to labels/text within source/show switch */
        #form-source, #alg-switch {
            font-family: var(--font-sans);
            font-size: 14px;
            font-weight: 450;
            color: var(--text-secondary);
            letter-spacing: 0.01em;
        }
        
        #form-source input[type="radio"] + label, /* Targeting text nodes after radio */
        #alg-switch input[type="radio"] + label, /* Targeting text nodes after radio */
        #form-source span, 
        #alg-switch span {
            vertical-align: middle;
            margin-right: 12px;
            cursor: pointer;
        }
        
        /* Specific override for the mysql label span if needed */
        #rb-mysql-label {
            vertical-align: middle;
            margin-left: -4px;
        }
        
        #active-bar {
            position: sticky;
            top: 100px;
            z-index: 890;
            background-color: var(--brand-blue-light-bg);
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 600;
            color: var(--text-primary);
            border: 1px solid rgba(26, 35, 126, 0.1);
            box-shadow: var(--shadow-sm);
            letter-spacing: 0.01em;
            font-size: 15px;
        }
        
        hr {
            border: none;
            border-top: 1px solid var(--border-light);
            margin: 12px 0;
        }
        
        .scroll-bar-container {
            width: 100%;
            padding: 10px 0;
        }
        
        #scroll-bar {
            width: 100%;
            height: 12px;
            accent-color: var(--brand-dark-blue);
        }
        
        #bar-info, #debug {
            margin-top: 12px;
            padding: 12px;
            background-color: var(--background-alt);
            border-radius: 6px;
            font-size: 13px;
            border: 1px solid var(--border-light);
        }
        
        .dop-info {
            display: flex;
            align-items: center;
            margin-top: 12px;
            padding: 12px;
            background-color: var(--background-alt);
            border-radius: 6px;
            border: 1px solid var(--border-light);
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
            padding: 18px;
            background-color: var(--background-alt);
            border-radius: 8px;
            border: 1px solid var(--border-light);
            margin-top: 8px;
        }
        
        @media screen and (max-width: 991px) {
            .header .container_header {
                padding: 0 15px;
                flex-direction: column;
                height: auto;
                padding-top: 15px;
                padding-bottom: 15px;
            }
            
            .logo img {
                height: 60px;
            }
            
            .nav-right {
                margin: 15px 0 5px;
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .nav-right li {
                height: 40px;
            }
            
            .nav-right li a {
                padding: 0 12px;
                font-size: 13px;
            }
            
            .nav-right li a i {
                margin-right: 5px;
                font-size: 14px;
            }
            
            .logo {
                margin: 0;
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
            margin-bottom: 14px;
        }
        
        .form-group label {
            display: inline-block;
            margin-right: 8px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .button-group {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        
        .content-description {
            margin-bottom: 16px;
            padding: 14px 18px;
            background-color: var(--brand-blue-light-bg);
            border-radius: 8px;
            font-size: 14px;
            border-left: 4px solid var(--brand-dark-blue);
            line-height: 1.5;
            color: var(--text-primary);
            letter-spacing: 0.01em;
        }
        
        .content-description strong {
            font-weight: 600;
        }
        
        .error-message {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-left: 4px solid #dc3545;
            padding: 12px 16px;
            margin: 12px 0;
            border-radius: 6px;
        }
        
        .warning-message {
            color: #856404;
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            border-left: 4px solid #ffc107;
            padding: 12px 16px;
            margin: 12px 0;
            border-radius: 6px;
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
            margin: 0 0 12px 0;
            font-weight: 600;
            letter-spacing: -0.01em;
        }
        
        /* Crucial style overrides to ensure consistent header spacing */
        body .header .container_header {
            padding-left: 25px !important;
            padding-right: 25px !important;
        }
        
        body .header .logo {
            margin-left: 0 !important;
            padding-left: 0 !important;
        }
        
        body .header .nav-right li:last-child a {
            padding-right: 0 !important;
        }
        
        /* Fixed header styles */
        .fixed-header {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
        }
        
        .header-spacer {
            height: 90px;
            width: 100%;
        }
        
        /* Mobile adjustments */
        @media screen and (max-width: 768px) {
            .sticky-buttons {
                top: 150px;
            }
            
            #active-bar {
                top: 210px;
            }
        }
        
        /* Control layout styling */
        .control-panel {
            background-color: var(--background-content);
            border-radius: 8px;
            padding: 16px;
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
        }
        
        .datasource-panel {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .navigation-panel {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        /* Chart controls styling */
        .chart-controls {
            margin-bottom: 10px;
        }
        
        .chart-navigation-panel {
            padding: 8px 12px;
        }
        
        @media screen and (max-width: 1024px) {
            .chart-navigation-panel > div {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            
            .chart-navigation-panel > div > div {
                width: 100%;
            }
            
            .chart-navigation-panel .form-inline {
                justify-content: flex-start;
            }
        }
        
        /* Form styling */
        .form-inline {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
        }
        
        .form-inline .form-group {
            display: flex;
            align-items: center;
            margin-bottom: 0;
        }
        
        .form-inline label {
            margin-right: 6px;
            white-space: nowrap;
        }
        
        .form-inline input[type="text"] {
            width: auto;
        }
        
        #data-source-container {
            margin-bottom: 12px;
        }
        
        #source-forex, #source-saves, #source-mysql {
            padding: 12px;
            background-color: var(--background-alt);
            border-radius: 6px;
            border: 1px solid var(--border-light);
        }
        
        /* Mobile adjustments for inline forms */
        @media screen and (max-width: 480px) {
            .form-inline {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .form-inline .form-group {
                margin-bottom: 8px;
                width: 100%;
            }
        }

        /* Graph/canvas elements styling */
        canvas#graph {
            width: 100%;
            height: 100%;
            display: block;
        }

        /* Styling for Calculation Mode Block */
        #calc-mode-container {
            padding: 12px;
            background-color: var(--background-alt);
            border-radius: 8px;
            border: 1px solid var(--border-light);
            margin-top: 12px; /* Add some space above */
        }
        
        #calc-mode-container h3 {
            font-size: 15px;
            color: var(--text-primary);
            margin: 0 0 8px 0;
            font-weight: 600;
            letter-spacing: -0.01em;
            font-family: var(--font-sans);
        }
        
        #form-mode {
            font-family: var(--font-sans);
            font-size: 14px;
            font-weight: 450;
            color: var(--text-secondary);
            letter-spacing: 0.01em;
        }
        
        #form-mode .mode-option {
            display: block; /* Each option on a new line */
            margin-bottom: 6px; /* Space between options */
            display: flex; /* Align input and text */
            align-items: center;
        }
        
        #form-mode input[type="radio"] {
            margin-right: 4px; /* Space between radio and text */
            flex-shrink: 0; /* Prevent radio from shrinking */
        }
        
        #form-mode .mode-option span { /* Target the text part */
            vertical-align: middle;
            cursor: pointer; /* Make text clickable like a label */
        }
    </style>
</head>

<!-- <body onmousemove='body_m_move(event)' onmouseup='body_m_up(event)' onmousedown='body_m_down(event)'> -->

<div class="grid-container">
    <!-- HEADER AREA -->
    <?php include 'includes/header.php'; ?>
    
    <!-- CONTROLS AREA - Unified control elements for better horizontal organization -->
    <div class="controls-area">
        <!-- Data Source Controls -->
        <div class="control-panel datasource-panel">
            <div id="source-switch">
                <h3>Data source:</h3>
                <form id="form-source">
                    <input id="rb-forex" type="radio" name="source" value="1" onclick="changeSource('forex')"> FOREX data (Finam) online</input>
                    <input id="rb-saves" type="radio" name="source" value="2" checked onclick="changeSource('saves')"> Alpari saved charts</input>
                    <input id="rb-mysql" type="radio" name="source" value="2" onclick="changeSource('mysql')"><span id="rb-mysql-label"> Models and charts from MySql DB</span></input>
                </form>
            </div>
            <div id="showSwitch" style="margin-top: 8px;">
                <h3>Show models:</h3>
                <form id="alg-switch">
                    <input id="showAlg1" type="radio" name="AlgshowSwith" value="Alg1" onclick="switchAlg2show(1)">Algorythm_1</input>
                    <input id="showAlg2" type="radio" name="AlgshowSwith" value="Alg2" onclick="switchAlg2show(2)">Algorythm_2</input>
                </form>
            </div>
        </div>
    </div>
    
    <!-- PARAMS AREA -->
    <div class="params-area">
        <div id="right-block" oncontextmenu="debug_on_off(event)">
            <div id="calc-mode-container">
                 <h3>Calculation Mode:</h3>
                 <form id="form-mode">
                    <div class="mode-option">
                        <input id="rb-mode1" type="radio" name="calc-mode" value="mode1"><span>show all models</span>
                    </div>
                    <div class="mode-option">
                        <input id="rb-mode2" type="radio" name="calc-mode" value="mode2" checked><span>find last (low + high)</span>
                    </div>
                    <div class="mode-option">
                        <input id="rb-mode3" type="radio" name="calc-mode" value="mode3"><span>selected bar as t.1</span>
                    </div>
                </form>
            </div>
           
            <table class="params-table" style="margin-top: 0;"> <!-- Remove margin if container added above -->
                <!-- Removed the previous table row containing the form -->
            </table>
            
            <!-- Calculation buttons -->
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

    <!-- MAIN CONTENT AREA -->
    <div class="maincontent-area">
        <div class="desk">
            <div id="data-source-container" style="margin-bottom: 6px;">
                <div id="source-forex" style="padding: 10px;">
                    <div class="form-group" style="margin-bottom: 6px;">
                        <label>Timeframe:</label>
                        <select id="select-interval" name="interval" size="1" style="padding: 6px 8px; margin: 2px 0;">
                            <option value="test1" selected>test1</option>
                            <option value="test2">test2</option>
                            <-- filled in by the script in Ready-->
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 6px;">
                        <label>Trading tool:</label>
                        <select id="select-pair" name="pair" size="1" style="padding: 6px 8px; margin: 2px 0;">
                            <option value="BTCUSDT" selected>BTC_USDT</option>
                            <option value="ETHUSDT">ETH_USDT</option>
                            <-- filled in by the script in Ready-->
                        </select>
                    </div>

                    <button id="get-data-btn" onclick="get_candles('forex')" style="padding: 6px 10px;">Request candlestick chart FOREX</button>
                </div>
                <div id="source-saves" style="padding: 10px;">
                    <?php
                    $filelist = glob("saved_charts/*.csv");
                    sort($filelist);
                    if (!$filelist) echo "No saved charts";
                    else {
                        echo '<div class="form-group" style="margin-bottom: 6px;">';
                        echo '<select id="select-chart" name="chart" size="1" style="padding: 6px 8px; margin: 2px 0;">\n';
                        $i = 0;
                        foreach ($filelist as $filename) {
                            $i++;
                            $pos = strpos($filename, "/");
                            $fn = substr($filename, $pos + 1);
                            echo '<option value="' . $fn . '" ' . (($i == 1) ? 'selected' : '') . '>' . $fn . '</option>\n';
                        }
                        echo "</select>\n";
                        echo '</div>';
                        
                        echo '<div class="form-inline" style="margin-bottom: 4px; gap: 6px;">';
                        echo '<div class="form-group" style="margin-right: 6px; margin-bottom: 0;">';
                        echo '<label id="lastBar4get_candles_label">Last bar:</label>';
                        echo '<input id="lastBar4get_candles" type="text" style="padding: 6px 8px; margin: 2px 0;">';
                        echo '</div>';
                        
                        echo '<div class="form-group" style="margin-bottom: 0;">';
                        echo '<label id="nBars4get_candles_label">Bars qty:</label>';
                        echo '<input id="nBars4get_candles" type="text" value="1000" size="5" style="padding: 6px 8px; margin: 2px 0;">';
                        echo '</div>';
                        echo '</div>';
                        
                        echo '<button id="get-data-btn2" onclick="get_candles(\'saves\')" style="padding: 6px 10px;">Request Alpari MT saved charts</button>';

                        echo '<div id="showPages" class="form-inline" style="margin-top: 6px; gap: 6px;">';
                        echo '<button onclick="get_more_candles(\'saves\', firstBarTime, \'prev\')" style="padding: 6px 10px;">Prev Period</button>';
                        echo '<button onclick="get_more_candles(\'saves\', firstBarTime, \'next\')" style="padding: 6px 10px;">Next Period</button>';
                        echo '</div>';
                    }
                    ?>
                </div>
                <div id="source-mysql" style="padding: 10px;">
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
                                    echo '<div class="form-group" style="margin-bottom: 6px;">';
                                    echo '<select id="select-name" name="name" size="1" style="padding: 6px 8px; margin: 2px 0;">';
                                    while ($Rec = $result->fetch_assoc()) {
                                        echo '<option value="' . htmlspecialchars($Rec['name']) . '" selected>' . htmlspecialchars($Rec['name']) . '</option>';
                                    }
                                    echo '</select>';
                                    echo '</div>';
                                    
                                    echo '<div class="form-inline" style="margin-bottom: 4px; gap: 6px;">';
                                    echo '<div class="form-group" style="margin-right: 6px; margin-bottom: 0;">';
                                    echo '<label id="lastBar4get_fragment_label">Last bar:</label>';
                                    echo '<input id="lastBar4get_fragment" type="text" style="padding: 6px 8px; margin: 2px 0;">';
                                    echo '</div>';
                                    
                                    echo '<div class="form-group" style="margin-bottom: 0;">';
                                    echo '<label id="modelId4get_fragment_label">Models Id:</label>';
                                    echo '<input id="modelId4get_fragment" type="text" style="padding: 6px 8px; margin: 2px 0;">';
                                    echo '</div>';
                                    echo '</div>';
                                    
                                    echo '<div class="form-group" style="margin-bottom: 6px;">';
                                    echo '<label id="nBars4get_fragment_label">Bars qty:</label>';
                                    echo '<input id="nBars4get_fragment" type="text" value="1000" size="5" style="padding: 6px 8px; margin: 2px 0;">';
                                    echo '</div>';
                                    
                                    echo '<button id="get-data-btn3" onclick="get_fragment()" style="padding: 6px 10px;">Request fragment from DB</button>';
                                    
                                    echo '<div id="showLevels" class="form-group" style="margin-top: 6px;">';
                                    echo '   <label>Show levels:</label>';
                                    echo '<form id="lvl-switch" class="form-inline" style="gap: 6px;">';
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
            
            <!-- Chart Navigation Controls - Moved here directly above the chart -->
            <div class="chart-controls" style="margin-bottom: 6px;">
                <div class="control-panel chart-navigation-panel" style="padding: 10px;">
                    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px;">
                        <div id="active-bar" style="margin-bottom: 0; padding: 6px 10px; flex-shrink: 0;">Chosen bar: <span>0</span></div>
                        
                        <div class="form-inline" style="margin-bottom: 0; margin-right: 0;">
                            <button onclick="switchModels('prev')"><strong>Previous t.1</strong></button>
                            <button id="switch-model-btn" disabled onclick="switchModels('switch')"><strong>Switch models</strong></button>
                            <button onclick="switchModels('next')"><strong>Next t.1</strong></button>
                        </div>
                        
                        <div class="form-inline" style="margin-bottom: 0; display: flex; align-items: center; gap: 4px;">
                            <input id="min_max_price_check" type="checkbox" name="min_max" onclick="drawGraph()">
                            <label id="min_v_label">min:</label>
                            <input id="min_v_text" type="text" size="7px" style="padding: 6px 8px;">
                            <label id="max_v_label">max:</label>
                            <input id="max_v_text" type="text" size="7px" style="padding: 6px 8px;">
                        </div>
                        
                        <div style="margin-bottom: 0; display: flex; align-items: center;">
                            <input id="chk-active-only" type="checkbox" name="source" value="active_only" checked onclick="changeChkActiveMOdelsOnly()"> 
                            <label for="chk-active-only">For unselected models, show only t.1</label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="content-description" style="margin-bottom: 8px; padding: 8px 12px;">
                <strong>To move chart:</strong> drag it with mouse <strong>Scale:</strong> Mouse wheel
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
<?php include 'includes/footer.php'; ?>

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
    function resizeCanvas() {
        const canvas = document.getElementById('graph');
        const container = document.getElementById('canvas-wrapper');
        
        if(canvas && container) {
            // Force container to take full width
            container.style.width = '100%';
            // Set canvas dimensions to match container size
            canvas.width = container.clientWidth;
            canvas.height = container.clientHeight || 400; // Default height if container height is 0
            
            // Redraw graph if the function exists
            if(typeof drawGraph === 'function') {
                drawGraph();
            }
        }
    }
    
    // Call resize on load and when window size changes
    window.addEventListener('resize', resizeCanvas);
    window.addEventListener('load', resizeCanvas);
</script>
</body>

</html>