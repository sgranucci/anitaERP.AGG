@php
    $puedeCuenta = $puede_ver_cuentacaja ?? false;
    $puedeCheque = $puede_ver_cheque ?? false;
    $filasTabla = $filas ?? [];
@endphp
<table class="table table-bordered table-hover table-sm mb-0" id="tabla-cierre-caja-reporte" style="font-size: 0.85rem;">
    <thead style="background:#85C1E9;color:#17202A;">
        <tr>
            <th>Código</th>
            <th>Cuenta / Concepto</th>
            <th>Detalle</th>
            <th class="text-right">Saldo ant. / Imp.</th>
            <th class="text-right">Ingresos / Cobro</th>
            <th class="text-right">Egresos / Pago</th>
            <th class="text-right">Saldo actual</th>
        </tr>
    </thead>
    <tbody>
    @forelse ($filasTabla as $fila)
        @php
            $tipoFila = $fila['tipo_fila'] ?? 'dato';
            $seccion = $fila['seccion'] ?? '';
        @endphp
        @if ($tipoFila === 'seccion')
            <tr class="cierre-seccion">
                <td colspan="7">{{ $fila['titulo'] ?? '' }}</td>
            </tr>
        @elseif ($tipoFila === 'total')
            <tr class="cierre-total">
                @include('caja.cierre_caja_reporte.partials.fila_valores', ['fila' => $fila, 'seccion' => $seccion, 'esTotal' => true, 'puedeCuenta' => false, 'puedeCheque' => false])
            </tr>
        @else
            <tr>
                @include('caja.cierre_caja_reporte.partials.fila_valores', ['fila' => $fila, 'seccion' => $seccion, 'esTotal' => false, 'puedeCuenta' => $puedeCuenta, 'puedeCheque' => $puedeCheque])
            </tr>
        @endif
    @empty
        <tr>
            <td colspan="7" class="text-center text-muted py-4">Sin datos para los filtros aplicados.</td>
        </tr>
    @endforelse
    </tbody>
</table>
