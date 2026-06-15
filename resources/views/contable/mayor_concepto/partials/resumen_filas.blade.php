@php
    $formatearMonto = $formatearMonto ?? static function ($valor) {
        if ($valor === null || $valor === '' || (float) $valor === 0.0) {
            return '';
        }

        return number_format((float) $valor, 2, ',', '.');
    };
    $agrupacion = $agrupacion_resumen ?? 'concepto_cuenta';
    $mostrarEnlaces = $mostrar_enlaces ?? false;
    $colspanMedio = (int) ($colspan_medio ?? 0);
@endphp
@if ($agrupacion === 'cuenta_concepto')
    @foreach ($resumen as $secCuenta)
        @foreach ($secCuenta['conceptos'] as $concepto)
            <tr>
                <td>
                    @if ($mostrarEnlaces && ($puede_ver_cuenta ?? false) && (int) ($secCuenta['cuentacontable_id'] ?? 0) > 0)
                        <a href="{{ route('editar_cuentacontable', ['id' => $secCuenta['cuentacontable_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                           target="_blank" rel="noopener" class="text-primary">
                            {{ $secCuenta['cuenta_codigo'] }}
                        </a>
                    @else
                        {{ $secCuenta['cuenta_codigo'] }}
                    @endif
                </td>
                <td>{{ $secCuenta['cuenta_nombre'] }}</td>
                <td>
                    @if ($mostrarEnlaces && ($puede_ver_concepto ?? false) && (int) ($concepto['concepto_id'] ?? 0) > 0)
                        <a href="{{ route('editar_conceptogasto', ['id' => $concepto['concepto_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                           target="_blank" rel="noopener" class="text-primary">
                            {{ $concepto['concepto_id'] }}
                        </a>
                    @else
                        {{ $concepto['concepto_id'] }}
                    @endif
                </td>
                <td>{{ $concepto['concepto_nombre'] }}</td>
                <td class="text-right">{{ (int) ($concepto['cantidad_lineas'] ?? 0) }}</td>
                @if ($colspanMedio > 0)
                    <td colspan="{{ $colspanMedio }}"></td>
                @endif
                <td class="text-right">{{ $formatearMonto($concepto['total_debe'] ?? null) }}</td>
                <td class="text-right">{{ $formatearMonto($concepto['total_haber'] ?? null) }}</td>
            </tr>
        @endforeach
        <tr class="font-weight-bold" style="background-color: #ebf5fb;">
            <td>{{ $secCuenta['cuenta_codigo'] }}</td>
            <td>{{ $secCuenta['cuenta_nombre'] }}</td>
            <td colspan="2" class="text-right">Total cuenta</td>
            <td class="text-right">{{ (int) ($secCuenta['cantidad_lineas'] ?? 0) }}</td>
            @if ($colspanMedio > 0)
                <td colspan="{{ $colspanMedio }}"></td>
            @endif
            <td class="text-right">{{ $formatearMonto($secCuenta['total_debe'] ?? null) }}</td>
            <td class="text-right">{{ $formatearMonto($secCuenta['total_haber'] ?? null) }}</td>
        </tr>
    @endforeach
@else
    @foreach ($resumen as $seccion)
        @foreach ($seccion['cuentas'] as $cuenta)
            <tr>
                <td>
                    @if ($mostrarEnlaces && ($puede_ver_concepto ?? false) && (int) ($seccion['concepto_id'] ?? 0) > 0)
                        <a href="{{ route('editar_conceptogasto', ['id' => $seccion['concepto_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                           target="_blank" rel="noopener" class="text-primary">
                            {{ $seccion['concepto_id'] }}
                        </a>
                    @else
                        {{ $seccion['concepto_id'] }}
                    @endif
                </td>
                <td>{{ $seccion['concepto_nombre'] }}</td>
                <td>
                    @if ($mostrarEnlaces && ($puede_ver_cuenta ?? false) && (int) ($cuenta['cuentacontable_id'] ?? 0) > 0)
                        <a href="{{ route('editar_cuentacontable', ['id' => $cuenta['cuentacontable_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                           target="_blank" rel="noopener" class="text-primary">
                            {{ $cuenta['cuenta_codigo'] }}
                        </a>
                    @else
                        {{ $cuenta['cuenta_codigo'] }}
                    @endif
                </td>
                <td>{{ $cuenta['cuenta_nombre'] }}</td>
                <td class="text-right">{{ (int) ($cuenta['cantidad_lineas'] ?? 0) }}</td>
                @if ($colspanMedio > 0)
                    <td colspan="{{ $colspanMedio }}"></td>
                @endif
                <td class="text-right">{{ $formatearMonto($cuenta['total_debe'] ?? null) }}</td>
                <td class="text-right">{{ $formatearMonto($cuenta['total_haber'] ?? null) }}</td>
            </tr>
        @endforeach
        <tr class="font-weight-bold" style="background-color: #ebf5fb;">
            <td>{{ $seccion['concepto_id'] }}</td>
            <td>{{ $seccion['concepto_nombre'] }}</td>
            <td colspan="2" class="text-right">Total concepto</td>
            <td class="text-right">{{ (int) ($seccion['cantidad_lineas'] ?? 0) }}</td>
            @if ($colspanMedio > 0)
                <td colspan="{{ $colspanMedio }}"></td>
            @endif
            <td class="text-right">{{ $formatearMonto($seccion['total_debe'] ?? null) }}</td>
            <td class="text-right">{{ $formatearMonto($seccion['total_haber'] ?? null) }}</td>
        </tr>
    @endforeach
@endif
