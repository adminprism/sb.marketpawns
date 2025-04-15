<?php
// Общие настройки сайта
$site_title = "Market Pawns Sandbox";
$site_description = "Tools and resources for Market Pawns models";

// Базовые пути
$base_url = "/"; // Базовый URL сайта
$base_path = realpath(dirname(__FILE__) . '/../'); // Корневая директория сайта

// Функция для генерации относительных путей к стилям и скриптам
function asset_url($path) {
    global $base_url;
    return $base_url . ltrim($path, '/');
}

// Функция для определения текущей страницы
function is_current_page($page) {
    $current_page = basename($_SERVER['PHP_SELF']);
    return ($current_page == $page);
}

// Функция для проверки, находимся ли мы в указанной директории
function is_in_directory($dir) {
    $current_dir = dirname($_SERVER['PHP_SELF']);
    return (strpos($current_dir, $dir) !== false);
}
?> 