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

## Новый модуль: Trade Variant Builder (конструктор вариантов трейда)
В панели Setup Configuration добавлен раздел "Trade Variant Builder", позволяющий собрать пользовательские сетапы на основе всех критериев, используемых в существующих вариантах трейда.

- Target × Variant:
  - Targets: `P6`, `P6"`, `auxP6`, `auxP6'`
  - Variants: `rev`, `reach`, `over`
- Параметры:
  - `CancelLevel` (по умолчанию 85%), `Actual` (по умолчанию 200%)
  - `InitStopLoss` (в процентах или `t5`)
  - `Aim1` (одиночное значение либо диапазон вида `from, to, step`)
  - Триггеры/трейлинг: `Trigger1/Trailing1`, `Trigger2/Trailing2`, `AlternateTrigger1` (только для `rev`, опционально), значения в `%`, `t5` или `AMrealP6`
- Условия:
  - Режим Simple: выпадающие поля для `G1`, `bad==0`, `section>N`, пороги `NN1/NN2/NN3_probability_1`
  - Режим Advanced: `condition1`/`condition2` как PHP-выражения (используются так же, как в текущих сетапах)
- Кнопки:
  - "Add to list" — добавить сетап в общий список (ниже в Available Setups)
  - "Save presets" — сохранить все добавленные пользовательские сетапы в файл `user_setups.json` (подхватываются в следующем `LIST`)

### Как это работает
- Добавленные сетапы формируются в точности по схеме, используемой в `calc_setups.php` (см. примеры в TRADE_TYPES_LEGEND.md).
- При запуске CALC клиент передает доп. поле `custom_setups` (JSON массива), сервер временно мёрджит эти сетапы в `$setups` и считает по указанным `setupIDs`.
- Сохраненные пресеты доступны на следующей загрузке страницы: `LIST` мёрджит содержимое `user_setups.json` к базовому массиву `$setups`.

## Прямые API вызовы (без UI)
URL: `calc_setups.php`, метод: POST

1) Получить список сетапов и инструментов:
- Параметры: `mode=LIST`
- Ответ: `answer` (массив сетапов), `tools` (инструменты), `PIC_WIDTH/PIC_HEIGHT`

2) Текущий прогресс:
- Параметры: `mode=PROGRESS`
- Ответ: строка статуса из `___calc_setup_progress.txt`

3) Запустить расчет:
- Параметры: `mode=CALC`, `setupIDs=0,2,3`, опционально `tool=EURUSD240`, опционально `custom_setups=[...]` (JSON)
- Ответ: JSON, поле `answer.out_files` — карта «метка → путь к файлу .json» (также создаются `.jpg` и `_proc.jpg`)

4) Сохранить пресеты пользовательских сетапов:
- Параметры: `mode=SAVE_SETUPS`, `setups=[...]` (JSON массив сетапов)
- Ответ: `{ answer: { saved: <кол-во> } }`

### Примеры cURL
```bash
curl -X POST -d "mode=LIST" https://<host>/calc_setups.php
curl -X POST -d "mode=PROGRESS" https://<host>/calc_setups.php
curl -X POST -d "mode=CALC&setupIDs=0,2,3&tool=EURUSD240" https://<host>/calc_setups.php
curl -X POST --data-urlencode "setups=$(cat presets.json)" -d "mode=SAVE_SETUPS" https://<host>/calc_setups.php
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
- Сетапы (`$setups` в `calc_setups.php`) задают условия `condition1/2`, тип торговли (`rev/reach/over`) и уровни (`CancelLevel`, `InitStopLoss`, `Aim1`, `Trigger1/2`, `AlternateTrigger1`, `Trailing1/2`, `Actual`)
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
```85:104:js/trade_emulator.js
        var request = $.ajax({
            url: url_,
            type: "POST",
            timeout: 0,
            data: {
                mode: "CALC",
                tool: $("#select-name").val(),
                setupIDs: setupIDs.substr(1,999),
                custom_setups: CustomSetups.length ? JSON.stringify(CustomSetups) : ''
            },
            dataType: "json",
        })
```
- Прогресс (в JS):
```143:164:js/trade_emulator.js
    var request = $.ajax({
      url: url_, type: "POST", timeout: 500,
      data: { mode: "PROGRESS" }, dataType: "json",
    })
```
- Обработка PROGRESS (в PHP):
```251:260:calc_setups.php
if(isset($PARAM['mode'])&&strtoupper($PARAM['mode'])=='PROGRESS'){
    $out=file_get_contents(PROGRESS_FILENAME);
    if($out)$res['answer']=$out; else $res['answer']="ERROR! Error reading file ".PROGRESS_FILENAME;
    $res['answer']=$out;
    unset($res['Error']);
    die();
}
```
- Список сетапов (в PHP):
```279:288:calc_setups.php
if(isset($PARAM['mode'])&&strtoupper($PARAM['mode'])=='LIST'){
    // Merge saved presets if present
    if(file_exists($USER_PRESETS_FILE)){
        $tmp = file_get_contents($USER_PRESETS_FILE);
        $usr = json_decode($tmp, true);
        if(is_array($usr)) $setups = array_merge($setups, $usr);
    }
    $res['answer']=$setups;
    $res['tools']=$toolsList;
    $res['PIC_WIDTH']=PIC_WIDTH;
    $res['PIC_HEIGHT']=PIC_HEIGHT;
    writeProgress("Setups list loaded. Count: ".count($setups));
    unset($res['Error']);
    die();
}
```
- Сохранение пресетов (в PHP):
```...:...:calc_setups.php
if(isset($PARAM['mode']) && strtoupper($PARAM['mode'])==='SAVE_SETUPS'){
    // expects POST 'setups' = JSON array
}
```

— Обновляйте документ при изменении логики, API или UI. 

