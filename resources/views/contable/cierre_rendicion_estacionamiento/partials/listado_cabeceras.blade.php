@php
    $vistaPorTurno = ! empty($vistaPorTurno);
    $columnasMedios = $columnasMedios ?? [];
@endphp
@if ($vistaPorTurno)
<tr>
    <th>ID</th>
    <th>Ticket</th>
    <th>Fecha jornada</th>
    <th>Empresa</th>
    <th>Punto venta</th>
    <th>Turno</th>
    <th>Fecha rend.</th>
    <th>Estado</th>
    <th>Asiento</th>
    <th class="num" style="text-align:right;">Venta neta</th>
    <th class="num" style="text-align:right;">NC</th>
    <th class="num" style="text-align:right;">Venta total</th>
    <th class="num" style="text-align:right;">Invitaciones</th>
    @foreach ($columnasMedios as $medioCol)
        <th class="num" style="text-align:right;" title="{{ $medioCol['label'] ?? '' }}">{{ $medioCol['label_descripcion'] ?? $medioCol['nombre'] ?? $medioCol['label'] ?? '' }}</th>
    @endforeach
    <th class="num" style="text-align:right;">Total cobrado</th>
</tr>
@else
<tr>
    <th>Fecha jornada</th>
    <th>Empresa</th>
    <th>Punto venta</th>
    <th class="num" style="text-align:right;">Rend.</th>
    <th class="num" style="text-align:right;">Venta neta</th>
    <th class="num" style="text-align:right;">NC</th>
    <th class="num" style="text-align:right;">Venta total</th>
    <th class="num" style="text-align:right;">Invitaciones</th>
    @foreach ($columnasMedios as $medioCol)
        <th class="num" style="text-align:right;" title="{{ $medioCol['label'] ?? '' }}">{{ $medioCol['label_descripcion'] ?? $medioCol['nombre'] ?? $medioCol['label'] ?? '' }}</th>
    @endforeach
    <th class="num" style="text-align:right;">Total cobrado</th>
    <th>Estado</th>
    <th>Asiento</th>
</tr>
@endif
