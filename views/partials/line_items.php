<?php
/** @var array<int,array<string,mixed>> $items */
$rows = $items ?? [];
if ($rows === []) {
    $rows = [['description' => '', 'quantity' => 1, 'unit' => 'Stk', 'unit_price_cents' => 0]];
}
?>
<?php $catalog = (new \Nova\Models\CatalogItemRepository())->active(); ?>
<?php if ($catalog !== []): ?>
    <div class="field" style="max-width:440px; margin-bottom:12px;">
        <label>Aus Leistungskatalog einfügen</label>
        <select onchange="novaCatalogAdd(this)">
            <option value="">– Position wählen –</option>
            <?php foreach ($catalog as $ci): ?>
                <option value="<?= e($ci['name']) ?>" data-unit="<?= e($ci['unit']) ?>" data-price="<?= e(amount((int) $ci['unit_price_cents'])) ?>">
                    <?= e($ci['name']) ?> · <?= money((int) $ci['unit_price_cents']) ?>/<?= e($ci['unit']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
<?php endif; ?>
<div class="line-items" id="line-items">
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:48%">Beschreibung</th>
                <th style="width:12%">Menge</th>
                <th style="width:12%">Einheit</th>
                <th style="width:18%">Einzelpreis (€)</th>
                <th style="width:10%"></th>
            </tr>
        </thead>
        <tbody id="items-body">
        <?php foreach ($rows as $it): ?>
            <tr class="item-row">
                <td><input type="text" name="item_description[]" value="<?= e($it['description']) ?>" placeholder="Leistung / Position"></td>
                <td><input type="text" name="item_quantity[]" value="<?= e(rtrim(rtrim(number_format((float) $it['quantity'], 2, ',', ''), '0'), ',')) ?>" inputmode="decimal" class="ta-right"></td>
                <td><input type="text" name="item_unit[]" value="<?= e($it['unit']) ?>" list="nova-units"></td>
                <td><input type="text" name="item_unit_price[]" value="<?= e(amount((int) $it['unit_price_cents'])) ?>" inputmode="decimal" class="ta-right"></td>
                <td><button type="button" class="btn btn-secondary btn-sm" onclick="novaRemoveRow(this)">✕</button></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <button type="button" class="btn btn-secondary btn-sm" onclick="novaAddRow()">+ Position</button>
</div>

<datalist id="nova-units">
    <?php foreach (['Stk', 'Std', 'Tag', 'Pauschal', 'km', 'kg', 'm', 'm²', 'l', 'Monat', 'Jahr', '%'] as $u): ?>
        <option value="<?= e($u) ?>"></option>
    <?php endforeach; ?>
</datalist>

<template id="item-row-template">
    <tr class="item-row">
        <td><input type="text" name="item_description[]" placeholder="Leistung / Position"></td>
        <td><input type="text" name="item_quantity[]" value="1" inputmode="decimal" class="ta-right"></td>
        <td><input type="text" name="item_unit[]" value="Stk" list="nova-units"></td>
        <td><input type="text" name="item_unit_price[]" value="0,00" inputmode="decimal" class="ta-right"></td>
        <td><button type="button" class="btn btn-secondary btn-sm" onclick="novaRemoveRow(this)">✕</button></td>
    </tr>
</template>
