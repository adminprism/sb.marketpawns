# Trade Types Legend (Легенда типов трейда)

## Quick Reference
- P6rev / P6"rev / auxP6rev / auxP6'rev:
  - Entry: on approach to P6 (after t5 exclusion rules). AlternateTrigger1 available if real P6 is reached.
  - SL: InitStopLoss from AM level (percent), trailing by Trigger1/2 (or AMrealP6).
  - TP: Aim1, enforced not on the approach bar.
  - Cancel: price hits CancelLevel, time exceeds Actual, chart end, or t5 exclusion.
- P6reach / P6"reach / auxP6reach / auxP6'reach:
  - Entry: after t4 confirmed; for P6"reach respects t5" exclusion.
  - SL: t5 or percent from t4. Trailing by Trigger1/2 when closer than current SL.
  - TP: Aim1 (>= target level), not on the same bar as confirmation.
  - Cancel: CancelLevel/time/chart end.
- P6over / P6"over / auxP6over / auxP6'over:
  - Entry: classic — on P6 breakdown; aggressive — on t4 if condition2 is true (open_bar=t4). t5/t5" exclusion applies.
  - SL: t5 or percent; reference is t4 for aggressive, P6 breakdown for classic.
  - TP: Aim1 beyond open_level; for aggressive if condition2 fails — exit on close of the open bar.
  - Cancel: CancelLevel/time/chart end.
- P6disrupt / P6"disrupt / auxP6disrupt / auxP6'disrupt:
  - Entry: on approach to P6 (same trigger as rev), but trade direction is breakout/continuation (long), open_level = approach level.
  - SL: t5 or percent from AM; triggers/trailling like over-long; no AlternateTrigger1; no triggers/TP on the same bar as approach.
  - TP: Aim1 above open_level.
  - Cancel: CancelLevel/time/chart end; t5/t5" exclusion like rev.

## Overview
This document explains the different trade types implemented in the trade emulator based on the actual code analysis from `calc_setups.php`.

## Trade Type Structure

Trade types follow the pattern: `[Target][Variant]`

### Targets (Цели)
- **P6** - Primary 6th point (основная 6-я точка)
- **P6"** - Alternative primary 6th point (альтернативная основная 6-я точка)
- **auxP6** - Auxiliary 6th point (вспомогательная 6-я точка)
- **auxP6'** - Alternative auxiliary 6th point (альтернативная вспомогательная 6-я точка)

### Variants (Варианты)
- **rev** - Reversal trade (торговля на разворот)
- **reach** - Reaching trade (торговля на достижение)
- **over** - Breakout trade (торговля на пробой)
- **disrupt** - Approach-entry + breakout-direction (подход + направление пробоя)

## Disrupt Details
- Purpose: combine early entry benefit of approach (rev) with continuation expectation (over).
- Entry mechanics: waits for approach level to be reached (after t5 exclusions), opens long at `appr_level`.
- Restrictions: triggers and Aim1 must not fire on the same bar as the approach; `AMrealP6` trailing is not used.
- SL logic: `t5` or percent from AM; Trigger1/2 move SL only if the new SL is closer than current SL.

## Trade Execution Logic

### Common Parameters
- **CancelLevel**: Level at which trade is cancelled (typically 85% of model size)
- **InitStopLoss**: Initial stop loss level (percentage or t5 level)
- **Aim1**: Primary target level (percentage from model size)
- **Trigger1/2**: Levels for stop loss adjustment
- **Trailing1/2**: New stop loss levels after trigger activation
- **AlternateTrigger1**: Alternative trigger when real 6th point is reached (reversal trades only)
- **Actual**: Time limit multiplier for trade validity (typically 200%)

### Level Definitions (from code constants)
- **LVL_BREAKDOWN**: -6% (breakdown level)
- **LVL_APPROACH**: 6% (approach level)  
- **LVL_BUMPER1**: 20% (initial bumper level)
- **LVL_BUMPER2**: 15% (bumper level after reaching 6th point)

### Trade States
1. **State 0**: Waiting for approach level
2. **State 1**: Approach level reached, trade active
3. **State 99**: Trade closed (profit/loss/cancellation)

### Cancellation Conditions
- Price reaches cancel level
- Time limit exceeded (Actual parameter)
- Chart data ends
- t5 exclusion rules (where applicable)

## Model Types Compatibility
- **EAM models**: Compatible with P6 and P6" targets
- **AM models**: Compatible with P6 and auxP6 targets  
- **EM models**: Compatible with auxP6 targets
- **DBM models**: Compatible with auxP6 targets
- **Combined models** (AM/DBM, EM/DBM): Compatible with auxP6 targets

## Usage Examples

### Example 1: P6rev Setup
```php
'trade type' => 'P6rev',
'CancelLevel' => '85%',
'InitStopLoss' => '-6%',
'Aim1' => '30%',
'Trigger1' => '15%',
'AlternateTrigger1' => '10%',
'eling1' => 'AMrealP6'
```

### Example 2: P6reach Setup  
```php
'trade type' => 'P6reach',
'CancelLevel' => '85%', 
'InitStopLoss' => 't5',
'Aim1' => '-6%, 6%, 6%',
'Trigger1' => '55%, 60%, 5%'
```

### Example 3: P6over Setup
```php
'trade type' => 'P6over',
'CancelLevel' => '85%',
'InitStopLoss' => '10%, 20%, 5%',
'Aim1' => '-50%, -30%, 5%',
'Trigger1' => '-20%, -15%, 5%'
```

### Example 4: P6disrupt Setup
```php
'trade type' => 'P6disrupt',
'CancelLevel' => '85%',
'InitStopLoss' => 't5',
'Aim1' => '30%',
'Trigger1' => '15%',
'AlternateTrigger1' => '',
'Trailing1' => '10%'
```

## Notes
- Percentage values are relative to model size
- Negative percentages indicate direction opposite to model orientation
- Multiple values (e.g., "10%, 20%, 5%") create parameter ranges for testing
- Special values like 't5' and 'AMrealP6' reference specific model points
- All trades use the same basic state machine but with different entry/exit logic

