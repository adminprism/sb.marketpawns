# Руководство по эмулятору торгов (Trade Emulator)

## Обзор
Эмулятор торгов прогоняет выбранные сетапы по историческим данным и формирует отчеты (JSON + графики PnL). UI — `trade_emulator.html`, клиент — `js/trade_emulator.js`, сервер — `calc_setups.php`.

## Требования окружения
- PHP 7.4+ с расширением GD (для JPG-графиков через imagejpeg)
- MySQL (см. `login_log.php`), корректно настроенные доступы
- Заполненные таблицы БД: `charts`, `chart_names`, `models`, `size_and_levels`, `nn_answers`
- Права записи: каталог `Reports/` и файл `___calc_setup_progress.txt` в корне
- Рекомендуется заранее рассчитать модели (`build_models_A1.php`/`build_models_A2.php`)

## Где находится функционал
- UI: `trade_emulator.html`
- JS-логика: `js/trade_emulator.js`, вспомогательные функции: `js/functions.js`
- Backend API: `calc_setups.php`
- Подключение к БД и логирование: `login_log.php`

## Работа через UI
1. Откройте `trade_emulator.html` в браузере (на том же домене, где доступен `calc_setups.php`).
2. При загрузке отправляется `mode=LIST`, подтягиваются инструменты и сетапы. Отметьте нужные сетапы, при необходимости выберите инструмент.
3. Нажмите «Выполнить эмуляцию торгов по выбранным сетапам».
4. Во время расчета отображается индикатор и периодически обновляется статус (правый клик по заголовку включает/выключает debug).
5. По завершении появятся ссылки на JSON-отчеты и кнопки показа графиков PnL (в пипсах и процентах).

## Прямые API вызовы (без UI)
URL: `calc_setups.php`, метод: POST

1) Получить список сетапов и инструментов:
- Параметры: `mode=LIST`
- Ответ: `answer` (массив сетапов), `tools` (инструменты), `PIC_WIDTH/PIC_HEIGHT`

2) Текущий прогресс:
- Параметры: `mode=PROGRESS`
- Ответ: строка статуса из `___calc_setup_progress.txt`

3) Запустить расчет:
- Параметры: `mode=CALC`, `setupIDs=0,2,3`, опционально `tool=EURUSD240`
- Ответ: JSON, поле `answer.out_files` — карта «метка → путь к файлу .json» (также создаются `.jpg` и `_proc.jpg`)

Примеры cURL:
```bash
curl -X POST -d "mode=LIST" https://<host>/calc_setups.php
curl -X POST -d "mode=PROGRESS" https://<host>/calc_setups.php
curl -X POST -d "mode=CALC&setupIDs=0,2,3&tool=EURUSD240" https://<host>/calc_setups.php
```

## Выходные данные
- Папка `Reports/<YYYY-MM-DD HH_mm_ss>/` на каждый запуск:
  - `*.json` — отчеты по комбинации `tag_G1`
  - `*.jpg` и `*_proc.jpg` — графики накопленного PnL (пипсы и проценты)
  - `TTLs/` — промежуточные большие ветки результатов
- Структура JSON включает:
  - `curSetup`, `setupParams`
  - Счетчики: `ALL_CNT`, `TRADE_CNT`, `CANCEL_CNT`, `AIM_CNT`, `SL*_CNT`
  - PnL: `PROFIT`, `LOSS`, `PROFIT_proc`, `LOSS_proc`
  - Сводку `Отчет[1..11]` и массив сделок `PNLs`

## Как выполняется расчет (кратко)
- Загружаются данные: `charts`, `models`, `size_and_levels`, `nn_answers`
- Сетапы (`$setups` в `calc_setups.php`) задают условия `condition1/2`, тип торговли (`rev/reach/over`) и уровни (`CancelLevel`, `InitStopLoss`, `Aim1`, `Trigger1/2`, `Trailing1/2`, `Actual`)
- Для подходящих моделей вызывается `tradeEmulation(...)`: симуляция, переносы стопов, фиксация результатов
- Итоги копятся в `$TTLs`, затем формируются отчеты и графики

## Диагностика
- БД/SQL: проверьте `login_log.php` и наличие данных в таблицах
- Пустой список инструментов: заполните `models` и `chart_names`
- Ошибки записи: права на `Reports/` и `___calc_setup_progress.txt`
- Нет графиков: включите расширение GD в PHP
- Прогресс не обновляется: проверьте права на файл и отсутствие ошибок PHP
- Большие объемы: увеличьте `memory_limit` и `max_execution_time`; при необходимости ограничьте запуск одним `tool` и малым набором сетапов

## Полезные фрагменты кода (ссылки)
- Точка входа UI и URL бэкенда (в HTML):
```10:14:trade_emulator.html
    url_="calc_setups.php" // имя PHP файла, в который летят AJAX запросы
```
- Вызов CALC (в JS):
```85:95:js/trade_emulator.js
        var request = $.ajax({
            url: url_,
            type: "POST",
            timeout: 0,
            data: {
                mode: "CALC",
                tool: $("#select-name").val(),
                setupIDs: setupIDs.substr(1,999)
            },
            dataType: "json",
        })
```
- Прогресс (в JS):
```133:146:js/trade_emulator.js
    var request = $.ajax({
      url: url_, type: "POST", timeout: 500,
      data: { mode: "PROGRESS" }, dataType: "json",
    })
```
- Обработка PROGRESS (в PHP):
```251:260:calc_setups.php
if(isset($PARAM['mode'])&&strtoupper($PARAM['mode'])=='PROGRESS'){
    $out=file_get_contents(PROGRESS_FILENAME);
    if($out)$res['answer']=$out; else $res['answer']="ERROR! Ошибка чтения файла ".PROGRESS_FILENAME;
    $res['answer']=$out;
    unset($res['Error']);
    die();
}
```
- Список сетапов (в PHP):
```279:287:calc_setups.php
if(isset($PARAM['mode'])&&strtoupper($PARAM['mode'])=='LIST'){
    $res['answer']=$setups;
    $res['tools']=$toolsList;
    $res['PIC_WIDTH']=PIC_WIDTH;
    $res['PIC_HEIGHT']=PIC_HEIGHT;
    writeProgress("Прочитан список сетапов. Количество: ".count($setups));
    unset($res['Error']);
    die();
}
```
- Создание каталога отчетов (в PHP):
```296:306:calc_setups.php
if(!is_dir('Reports'))mkdir('Reports');
$dir_name='Reports/'.date("Y-m-d H_i_s");
$tmp=mkdir($dir_name);
$tmp=mkdir($dir_name."/TTLs");
```
- Формирование списка `out_files` (в PHP):
```676:681:calc_setups.php
function writeReports($dir_name){
    ksort($TTLs);
    $ttt=microtime(true);
    $cnt=0;
    foreach($TTLs as $tag=>$pv1){ /* ... */ }
}
```
```806:809:calc_setups.php
$res['answer']['out_files'][$cnt.") ".$tag."_"."$G1|trades:".($pv2['TRADE_CNT']??0).", pnl: ".$pv2['PROFIT']."-".$pv2['LOSS']."=   ".($pv2['PROFIT']-$pv2['LOSS'])." "]=$fileName;
if($cnt % 10 ==0)writeProgress("Генерация и запись отчетов: $cnt");
```
- Подпись функции симуляции:
```814:841:calc_setups.php
function tradeEmulation($setup,$model,$size_and_levels,$curSetup,$check2=false,$isAggressive=false){
    // ...
}
```

— Обновляйте документ при изменении логики эмулятора, API или UI.
