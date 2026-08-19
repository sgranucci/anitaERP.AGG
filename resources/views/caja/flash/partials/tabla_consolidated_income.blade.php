@php
    use App\Support\Caja\Flash\FlashCajaLFlashFormatoSupport as F;
    $excel = !empty($modo_excel);
    $fn = $excel ? [F::class, 'nExcel'] : [F::class, 'n'];
    $fp = $excel ? [F::class, 'pctExcel'] : [F::class, 'pct'];
    $budget = $reporte['budget_mes'] ?? [];
    $filas = $reporte['filas_diarias'] ?? [];
    $mostrarAcciones = !empty($mostrar_acciones) && empty($modo_excel) && empty($modo_pdf);
    $pantalla = empty($modo_excel) && empty($modo_pdf);
@endphp

@if(!empty($budget))
<p class="flash-budget-meta" style="font-size: 8px; margin: 4px 0 8px;">
    Budget pos: {{ F::entero($budget['budget_pos'] ?? 0) }}
    &mdash; Total: {{ $fn($budget['budget_total'] ?? 0) }}
    &mdash; Electronic: {{ $fn($budget['budget_electronic'] ?? 0) }}
    &mdash; Bingo: {{ $fn($budget['budget_bingo'] ?? 0) }}
    &mdash; F&amp;B: {{ $fn($budget['budget_ayb'] ?? 0) }}
    &mdash; Parking: {{ $fn($budget['budget_estac'] ?? 0) }}
    @if(isset($reporte['through_day']))
        &mdash; Through day: {{ $reporte['through_day'] }}
    @endif
    @if(isset($reporte['con_season']))
        &mdash; Season index: {{ !empty($reporte['con_season']) ? 'Sí' : 'No' }}
    @endif
</p>
@endif

@if ($pantalla)
<div class="tabla-ancha-grilla tabla-ancha--doble-cabecera" data-tabla-ancha style="--tabla-ancha-c1: 4.2rem; --tabla-ancha-c2: 5.6rem;">
    <div class="tabla-ancha-scroll-top" hidden>
        <div class="tabla-ancha-scroll-top-inner"></div>
    </div>
    <div class="tabla-ancha-wrap">
@else
<div class="table-responsive" style="overflow-x: auto;">
@endif
<table class="data flash-lflash-table{{ $pantalla ? ' tabla-ancha' : '' }}" style="{{ $pantalla ? '' : 'width:100%; border-collapse:collapse; ' }}font-size:7px;">
    <thead>
        <tr style="background:#85C1E9;color:#17202A;">
            <th rowspan="2" class="{{ $pantalla ? 'col-fija-1' : '' }}">Day</th>
            <th rowspan="2" class="{{ $pantalla ? 'col-fija-2' : '' }}">Fecha</th>
            <th rowspan="2">Custom</th>
            <th colspan="8">SLOTS</th>
            <th colspan="8">ELECTRONIC ROULETTE</th>
            <th colspan="2">Win elec.</th>
            <th colspan="3">Win OL vs Fin.</th>
            <th colspan="4">BINGO</th>
            <th rowspan="2">Gaming</th>
            <th colspan="2">F&amp;B</th>
            <th colspan="2">PARKING</th>
            <th rowspan="2">Otros</th>
            <th colspan="2">Net Revenues</th>
            <th colspan="4">Pos / Customers</th>
            <th colspan="5">vs Season (*)</th>
            <th colspan="5">Sin seasonality</th>
            <th colspan="2">Vehicles</th>
            @if($mostrarAcciones)
                <th rowspan="2"></th>
            @endif
        </tr>
        <tr style="background:#85C1E9;color:#17202A;">
            <th>Units</th><th>Coin in</th><th>Drop</th><th>OL Win</th><th>%Coin</th><th>%Drop</th><th>/Cust</th><th>/Unit</th>
            <th>Seats</th><th>Coin in</th><th>Drop</th><th>OL Win</th><th>%Coin</th><th>%Drop</th><th>/Cust</th><th>/Seat</th>
            <th>/Stand</th><th>Pos</th>
            <th>Online</th><th>Financial</th><th>Diff</th>
            <th>Cards</th><th>Sales</th><th>Win</th><th>/Cust</th>
            <th>Sales</th><th>/Cust</th>
            <th>Sales</th><th>/Cust</th>
            <th>Total</th><th>/Cust</th>
            <th>Pos OL</th><th>vs Budg</th><th>Cust Budg</th><th>Dev%</th>
            <th>Total</th><th>Elec</th><th>Bingo</th><th>F&amp;B</th><th>Park</th>
            <th>Total</th><th>Elec</th><th>Bingo</th><th>F&amp;B</th><th>Park</th>
            <th>Qty</th><th>Budget</th>
        </tr>
    </thead>
    <tbody>
        @foreach($filas as $dia)
            @include('caja.flash.partials.fila_lflash', ['m' => $dia, 'fn' => $fn, 'fp' => $fp, 'mostrarAcciones' => $mostrarAcciones, 'esTotal' => false, 'congelarColumnas' => $pantalla])
        @endforeach

        @if(!empty($reporte['total_final']) && ($reporte['cantidad_dias'] ?? count($filas)) > 0)
            @include('caja.flash.partials.fila_lflash', ['m' => $reporte['total_final'], 'fn' => $fn, 'fp' => $fp, 'mostrarAcciones' => false, 'esTotal' => true, 'congelarColumnas' => $pantalla])
        @endif
        @if(!empty($reporte['mtd_average']) && ($reporte['cantidad_dias'] ?? 0) > 0)
            @include('caja.flash.partials.fila_lflash', ['m' => $reporte['mtd_average'], 'fn' => $fn, 'fp' => $fp, 'mostrarAcciones' => false, 'esTotal' => true, 'congelarColumnas' => $pantalla])
        @endif
        @if(!empty($reporte['mtd_resta_season']) && ($reporte['cantidad_dias'] ?? 0) > 0)
            @include('caja.flash.partials.fila_lflash_resta', [
                'restaSeason' => $reporte['mtd_resta_season'],
                'restaBudget' => $reporte['mtd_resta_budget'] ?? [],
                'fn' => $fn,
                'colspanIzq' => 40,
            ])
        @endif
    </tbody>
</table>
@if ($pantalla)
    </div>
</div>
@else
</div>
@endif

@php
    $compMes = $reporte['comparativo_mes_ant'] ?? null;
    $compAnio = $reporte['comparativo_anio_ant'] ?? null;
@endphp

@if(!empty($compMes) && ($compMes['cantidad_dias'] ?? 0) > 0)
    <h3 style="font-size:10px;margin:14px 0 4px;color:#1A5276;">
        Comparativo mes anterior ({{ $compMes['periodo_label'] ?? '' }})
    </h3>
    <div class="table-responsive" style="overflow-x: auto;">
    <table class="data flash-lflash-table" style="width:100%; border-collapse:collapse; font-size:7px;">
        <thead>
            <tr style="background:#85C1E9;color:#17202A;">
                <th>Etiqueta</th>
                <th>Custom</th>
                <th>Slot OL</th>
                <th>Rul OL</th>
                <th>Win OL</th>
                <th>Bingo Win</th>
                <th>Gaming</th>
                <th>F&amp;B</th>
                <th>Parking</th>
                <th>Otros</th>
                <th>Revenues</th>
                <th>vs Season Tot</th>
                <th>vs Budg Tot</th>
            </tr>
        </thead>
        <tbody>
            @include('caja.flash.partials.fila_lflash_resumen', ['m' => $compMes['total_final'] ?? [], 'fn' => $fn, 'fp' => $fp])
            @include('caja.flash.partials.fila_lflash_resumen', ['m' => $compMes['mtd_average'] ?? [], 'fn' => $fn, 'fp' => $fp])
            @if(!empty($compMes['mtd_resta_season']))
            <tr>
                <td colspan="11" class="text-right"><em>Dev. MTD vs season / budget</em></td>
                <td class="text-right">{{ $fn($compMes['mtd_resta_season']['total'] ?? 0) }}</td>
                <td class="text-right">{{ $fn($compMes['mtd_resta_budget']['total'] ?? 0) }}</td>
            </tr>
            @endif
        </tbody>
    </table>
    </div>
@endif

@if(!empty($compAnio) && ($compAnio['cantidad_dias'] ?? 0) > 0)
    <h3 style="font-size:10px;margin:14px 0 4px;color:#1A5276;">
        Comparativo igual per&iacute;odo a&ntilde;o anterior ({{ $compAnio['periodo_label'] ?? '' }})
    </h3>
    <div class="table-responsive" style="overflow-x: auto;">
    <table class="data flash-lflash-table" style="width:100%; border-collapse:collapse; font-size:7px;">
        <thead>
            <tr style="background:#85C1E9;color:#17202A;">
                <th>Etiqueta</th>
                <th>Custom</th>
                <th>Slot OL</th>
                <th>Rul OL</th>
                <th>Win OL</th>
                <th>Bingo Win</th>
                <th>Gaming</th>
                <th>F&amp;B</th>
                <th>Parking</th>
                <th>Otros</th>
                <th>Revenues</th>
                <th>vs Season Tot</th>
                <th>vs Budg Tot</th>
            </tr>
        </thead>
        <tbody>
            @include('caja.flash.partials.fila_lflash_resumen', ['m' => $compAnio['total_final'] ?? [], 'fn' => $fn, 'fp' => $fp])
            @include('caja.flash.partials.fila_lflash_resumen', ['m' => $compAnio['mtd_average'] ?? [], 'fn' => $fn, 'fp' => $fp])
            @if(!empty($compAnio['mtd_resta_season']))
            <tr>
                <td colspan="11" class="text-right"><em>Dev. MTD vs season / budget</em></td>
                <td class="text-right">{{ $fn($compAnio['mtd_resta_season']['total'] ?? 0) }}</td>
                <td class="text-right">{{ $fn($compAnio['mtd_resta_budget']['total'] ?? 0) }}</td>
            </tr>
            @endif
        </tbody>
    </table>
    </div>
@endif
