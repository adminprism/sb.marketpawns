- 2025-08-28
  - Trade Emulator: улучшено сообщение об ошибке недостатка баров в `calc_setups.php` (включает инструмент и совет по загрузке истории).
  - UI: в `js/trade_emulator.js` alert теперь показывает первый текст ошибки из ответа сервера.
# CHANGELOG

## [Unreleased]
- Added Trade Variant Builder to `trade_emulator.html` allowing construction of custom setups (targets P6/P6"/auxP6/auxP6' × rev/reach/over) with all parameters (CancelLevel, InitStopLoss, Aim1, Trigger1/2, AlternateTrigger1, Trailing1/2, Actual) and Simple/Advanced condition builders.
- `js/trade_emulator.js`: added client logic for adding custom setups, passing them as `custom_setups` during CALC, and saving presets.
- `calc_setups.php`: new `mode=SAVE_SETUPS` (writes `user_setups.json`), LIST now merges saved presets into `$setups`, CALC optionally merges posted `custom_setups` prior to simulation.
- `developmentlog/TRADE_EMULATOR_USER_GUIDE.md`: updated with builder usage and new API endpoints.

- Trade Variant Builder: added range mode for `InitStopLoss` with from/to/step inputs.
- Added right-side "Builder Preview" panel to show live JSON of the constructed setup.
- Realigned layout: `Setup Details` placed on the same row as `Available Setups`.
- Improved responsiveness of builder groups; ensured `CancelLevel` no longer overlaps `Variant`.
- JS: `renderBuilderPreview`, `collectBuilderSetup`, `syncSL`, `syncAim`, `syncTrigger`, `syncTrailing` extended to support `InitStopLoss` ranges and live preview updates.

- **Interface Expansion**: Doubled horizontal width of the interface for better usability:
  - `css/style.css`: Increased max-width from 1200px→2400px, 1400px→2800px, 1600px→3200px; added min-width: 2000px to body and .emulator-main
  - `trade_emulator.html`: Added min-width: 2000px to body; expanded grid column minimums from 260px→520px, 80px→160px, 110px→220px, 120px→240px, 70px→140px
  - Increased gaps between elements from 6px→12px, 10px→20px, 12px→24px for better spacing
  - Changed .emulator-main from grid layout to block layout for full width utilization

- Setup cards layout: fixed grid item jump on hover by pre-allocating left border width (transparent) and limiting transitions to non-size properties.

## [Existing]
- Trade emulator, reports API, and controls remain backward compatible.
