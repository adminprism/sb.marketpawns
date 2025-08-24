<?php
// Подключаем конфигурационный файл, если он еще не подключен
if (!function_exists('asset_url')) {
    include_once dirname(__FILE__) . '/config.php';
}

// Определяем активные разделы на основе URL
$is_home = is_current_page('index.php');
$is_infobase = is_in_directory('/infobase');
?>
<header class="header fixed-header <?php echo $is_home ? 'header-area' : ''; ?>">
    <div class="container_header" style="padding-left: 25px !important; padding-right: 25px !important; box-sizing: border-box; width: 100%; max-width: 1600px;">
        <a class="logo" href="<?php echo $base_url; ?>index.php" style="margin-left: 0 !important; padding-left: 0 !important;">
            <img src="<?php echo $base_url; ?>public/images/SANDBOX.png" alt="<?php echo $site_title; ?>" />
        </a>

        <ul class="nav-right">
            <li class="<?php echo $is_home ? 'active' : ''; ?>">
                <a href="<?php echo $base_url; ?>index.php"><i class="fas fa-home"></i>Home</a>
            </li>
            <li class="<?php echo $is_infobase ? 'active' : ''; ?>">
                <a href="<?php echo $base_url; ?>infobase/tables.php"><i class="fas fa-database"></i>Infobase</a>
            </li>
            <li>
                <a href="<?php echo $base_url; ?>trade_emulator.html"><i class="fas fa-play-circle"></i>Trade Emulator</a>
            </li>
            <li><a href="https://marketpawns.com"><i class="fas fa-chess-pawn"></i>Marketpawns</a></li>
            <li><a href="https://wiki.marketpawns.com/index.php?title=Main_Page"><i class="fas fa-book"></i>Wiki</a></li>
            <li><a href="https://github.com/adminprism/Sandbox" target="_blank" style="padding-right: 0 !important;"><i class="fab fa-github"></i>GitHub</a></li>
        </ul>
    </div>
</header>
<div class="header-spacer"></div> 