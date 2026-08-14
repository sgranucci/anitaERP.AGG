@php
    $coleccion = $filas instanceof \Illuminate\Support\Collection || $filas instanceof \Illuminate\Contracts\Pagination\Paginator
        ? $filas
        : collect($filas);
@endphp
<div class="table-responsive">
    <table class="table table-sm table-bordered table-striped mb-0" id="tabla-paginada">
        <thead style="background:#85C1E9;color:#17202A;">
            <tr>
                <th>Legajo</th>
                <th>Empleado</th>
                <th>Ingreso</th>
                <th>Categor&iacute;a</th>
                <th>Agrupamiento</th>
                <th>Lugar</th>
                <th>Fecha</th>
                <th>Concepto</th>
                <th class="text-right">Debe</th>
                <th class="text-right">Haber</th>
                <th>Observaci&oacute;n</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($coleccion as $f)
                <tr class="{{ !empty($f['es_total']) ? 'font-weight-bold table-secondary' : '' }}">
                    <td>{{ $f['legajo'] }}</td>
                    <td>{{ $f['nombre'] }}</td>
                    <td>{{ $f['fecha_ingreso'] ?? '' }}</td>
                    <td>{{ $f['categoria'] ?? '' }}</td>
                    <td>{{ $f['agrupamiento'] ?? '' }}</td>
                    <td>{{ $f['lugar_trabajo'] ?? '' }}</td>
                    <td>{{ $f['fecha_fmt'] ?? ($f['fecha'] ?? '') }}</td>
                    <td>{{ $f['concepto'] ?? '' }}</td>
                    <td class="text-right">{{ ((float)($f['debe'] ?? 0)) > 0 ? number_format((float)$f['debe'], 2, ',', '.') : '' }}</td>
                    <td class="text-right">{{ ((float)($f['haber'] ?? 0)) > 0 ? number_format((float)$f['haber'], 2, ',', '.') : '' }}</td>
                    <td>{{ $f['observacion'] ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="11" class="text-center text-muted py-3">Sin movimientos.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
