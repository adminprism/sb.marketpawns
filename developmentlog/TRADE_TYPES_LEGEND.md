# Trade Types Legend (Легенда типов трейда)

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

## Complete Trade Types

### Reversal Trades (Торговля на разворот)
**Strategy**: Trade expecting price to reverse from the 6th point level

- **P6rev** - Reversal from primary 6th point
- **P6"rev** - Reversal from alternative primary 6th point  
- **auxP6rev** - Reversal from auxiliary 6th point
- **auxP6'rev** - Reversal from alternative auxiliary 6th point

**Key characteristics:**
- Entry: When price approaches the calculated 6th point level
- Direction: Opposite to the approach direction (reversal)
- Stop Loss: Typically set beyond the 6th point level
- Take Profit: Set at predetermined percentage from model size
- Special rules: Trade is cancelled if t5 bar already reached the approach level

### Reaching Trades (Торговля на достижение)
**Strategy**: Trade expecting price to reach the 6th point level

- **P6reach** - Trade to reach primary 6th point
- **P6"reach** - Trade to reach alternative primary 6th point
- **auxP6reach** - Trade to reach auxiliary 6th point  
- **auxP6'reach** - Trade to reach alternative auxiliary 6th point

**Key characteristics:**
- Entry: After model formation, trading towards the 6th point
- Direction: Towards the calculated 6th point
- Stop Loss: Can be set as t5 level or percentage from model
- Take Profit: Near the 6th point level (within approach/breakdown range)
- Special rules: Stop loss set within approach and breakdown levels

### Breakout Trades (Торговля на пробой)
**Strategy**: Trade expecting price to break through the 6th point level

- **P6over** - Breakout through primary 6th point
- **P6"over** - Breakout through alternative primary 6th point
- **auxP6over** - Breakout through auxiliary 6th point
- **auxP6'over** - Breakout through alternative auxiliary 6th point

**Key characteristics:**
- Entry: When price breaks through the 6th point level
- Direction: Continuation beyond the 6th point
- Stop Loss: Set above the 6th point level
- Take Profit: Set at significant distance beyond breakout level
- Special rules: No AlternateTrigger1 used for breakout trades

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
- t5 exclusion rules (for reversal trades)

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
'Trailing1' => 'AMrealP6'
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

## Notes
- Percentage values are relative to model size
- Negative percentages indicate direction opposite to model orientation
- Multiple values (e.g., "10%, 20%, 5%") create parameter ranges for testing
- Special values like 't5' and 'AMrealP6' reference specific model points
- All trades use the same basic state machine but with different entry/exit logic
