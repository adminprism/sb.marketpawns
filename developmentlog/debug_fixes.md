- 2025-08-28
  - Ошибка: всплывающее "Error in calc_setups.php" без деталей при расчёте на отдельных инструментах (напр. AUDCAD5).
  - Причина: сервер возвращал массив `Errors`, но alert не показывал текст; также при отсутствии достаточного количества баров сообщение было малоинформативным.
  - Исправление: 
    1) В `calc_setups.php` расширено сообщение об ошибке недостатка баров, включены `tool`/`name_id` и подсказка загрузить историю.
    2) В `js/trade_emulator.js` alert теперь включает первый элемент `Errors`.
  - Рекомендация: при проблемах с конкретным инструментом загрузить историю через `load_and_calc_dir_5m.php`/`fill_db4chart.php`.
# Debug fixes log

## 2025-08-26
- calc_setups.php: Added support for `custom_setups` merging and `SAVE_SETUPS` mode. LIST now merges `user_setups.json` presets.
- Note: During refactor, adjusted several `ALL_G1` aggregations to ensure they sum from the current G1 branch values. If mismatched totals are observed, verify these lines in CALC paths (SL/TP and counters) to avoid double counting or wrong branch references. 

- UI: Restored missing Trade Variant Builder section after layout refactor; added live preview and `InitStopLoss` range controls. Fixed layout so `Setup Details` sits beside `Available Setups`.
- JS: Guarded preview updates in `sync*` helpers to avoid runtime errors if elements are not yet present. Ensured `onVariantChange()` initializes preview and custom setups list.