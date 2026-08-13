@php
    $formatearMonto = $formatearMonto ?? static function ($valor) {
        if ($valor === null || $valor === '' || (float) $valor === 0.0) {
            return '';
        }

        return number_format((float) $valor, 2, ',', '.');
    };
    $formatearCotizacion = $formatearCotizacion ?? static function ($valor) {
        if ($valor === null || $valor === '' || (float) $valor === 0.0) {
            return '';
        }

        return number_format((float) $valor, 4, ',', '.');
    };
    $mostrarEmpresa = $multiempresa ?? false;
    $colSpanTotales = $mostrarEmpresa ? 17 : 16;
    $colSpanVacio = $mostrarEmpresa ? 19 : 18;
    $puedeVerAsiento = (bool) ($puede_ver_asiento ?? false);
    $puedeVerCuenta = (bool) ($puede_ver_cuenta ?? false);
    $puedeVerConcepto = (bool) ($puede_ver_concepto ?? false);
    $puedeVerOc = (bool) ($puede_ver_ordencompra ?? false);
    $puedeVerCapex = (bool) ($puede_ver_capex ?? false);
@endphp
<thead>
    <tr style="background-color: #85C1E9; color: #17202A; font-weight: bold;">
        @if ($mostrarEmpresa)
            <th>Empr.</th>
        @endif
        <th>Concepto</th>
        <th>Nombre concepto</th>
        <th>Cuenta</th>
        <th>Descripción cuenta</th>
        <th>Fecha</th>
        <th>N.Asi.</th>
        <th>Tip</th>
        <th>Comprobante</th>
        <th>Cheque</th>
        <th>Nro.OC.</th>
        <th>Capex</th>
        <th>Emisor</th>
        <th>CUIT</th>
        <th>Descripción mov.</th>
        <th>Mon</th>
        <th style="text-align: right;">Cotiz.</th>
        <th style="text-align: right;">Debe</th>
        <th style="text-align: right;">Haber</th>
    </tr>
</thead>
<tbody>
    @forelse ($filas as $f)
        @php
            $fila = is_array($f) ? $f : (array) $f;
            $tipoFila = $fila['tipo_fila'] ?? 'detalle';
        @endphp
        @if ($tipoFila === 'header_empresa')
            <tr class="fila-header-empresa font-weight-bold" style="background-color: #d6eaf8;">
                <td colspan="{{ $colSpanVacio }}">
                    <i class="fa fa-building"></i>
                    Empresa: {{ $fila['nombreempresa'] ?? '' }}
                </td>
            </tr>
        @elseif ($tipoFila === 'total_cuenta')
            <tr class="fila-total-cuenta font-weight-bold" style="background-color: #e9ecef; border-top: 1px solid #adb5bd;">
                <td colspan="{{ $colSpanTotales }}" class="text-right">
                    Total cuenta
                    @if ($puedeVerCuenta && (int) ($fila['cuentacontable_id'] ?? 0) > 0)
                        <a href="{{ route('editar_cuentacontable', ['id' => $fila['cuentacontable_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                           target="_blank" rel="noopener" class="text-primary">
                            {{ $fila['cuenta_codigo'] ?? '' }}
                        </a>
                    @else
                        {{ $fila['cuenta_codigo'] ?? '' }}
                    @endif
                    @if (! empty($fila['cuenta_nombre']))
                        — {{ $fila['cuenta_nombre'] }}
                    @endif
                </td>
                <td class="text-right">{{ $formatearMonto($fila['debe'] ?? null) }}</td>
                <td class="text-right">{{ $formatearMonto($fila['haber'] ?? null) }}</td>
            </tr>
        @elseif ($tipoFila === 'total_concepto')
            <tr class="fila-total-concepto font-weight-bold" style="background-color: #ced4da; border-top: 2px solid #6c757d;">
                <td colspan="{{ $colSpanTotales }}" class="text-right">
                    Total concepto
                    @if ($puedeVerConcepto && (int) ($fila['concepto_id'] ?? 0) > 0)
                        <a href="{{ route('editar_conceptogasto', ['id' => $fila['concepto_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                           target="_blank" rel="noopener" class="text-primary">
                            {{ $fila['concepto_id'] }}
                        </a>
                    @else
                        {{ $fila['concepto_id'] ?? '' }}
                    @endif
                    @if (! empty($fila['concepto_nombre']))
                        — {{ $fila['concepto_nombre'] }}
                    @endif
                </td>
                <td class="text-right">{{ $formatearMonto($fila['debe'] ?? null) }}</td>
                <td class="text-right">{{ $formatearMonto($fila['haber'] ?? null) }}</td>
            </tr>
        @else
            <tr>
                @if ($mostrarEmpresa)
                    <td>{{ $fila['nombreempresa'] ?? '' }}</td>
                @endif
                <td>
                    @if ($puedeVerConcepto && (int) ($fila['concepto_id'] ?? 0) > 0)
                        <a href="{{ route('editar_conceptogasto', ['id' => $fila['concepto_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                           target="_blank" rel="noopener" class="text-primary">
                            {{ $fila['concepto_id'] }}
                        </a>
                    @else
                        {{ $fila['concepto_id'] ?? '' }}
                    @endif
                </td>
                <td>{{ $fila['concepto_nombre'] ?? '' }}</td>
                <td>
                    @if ($puedeVerCuenta && (int) ($fila['cuentacontable_id'] ?? 0) > 0)
                        <a href="{{ route('editar_cuentacontable', ['id' => $fila['cuentacontable_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                           target="_blank" rel="noopener" class="text-primary">
                            {{ $fila['cuenta_codigo'] ?? '' }}
                        </a>
                    @else
                        {{ $fila['cuenta_codigo'] ?? '' }}
                    @endif
                </td>
                <td>{{ $fila['cuenta_nombre'] ?? '' }}</td>
                <td>{{ $fila['fecha_fmt'] ?? '' }}</td>
                <td>
                    @if ($puedeVerAsiento && (int) ($fila['asiento_id'] ?? 0) > 0)
                        <a href="{{ route('editar_asiento', ['id' => $fila['asiento_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                           target="_blank" rel="noopener" class="text-primary" title="Consultar asiento">
                            {{ $fila['nro_asiento'] ?? '' }}
                        </a>
                    @else
                        {{ $fila['nro_asiento'] ?? '' }}
                    @endif
                </td>
                <td>{{ $fila['tipo_comp'] ?? '' }}</td>
                <td>{{ $fila['comprobante'] ?? '' }}</td>
                <td>{{ $fila['cheque'] ?? '' }}</td>
                <td>
                    @if ((int) ($fila['nro_oc'] ?? 0) > 0)
                        @if ($puedeVerOc && (int) ($fila['ordencompra_id'] ?? 0) > 0)
                            <a href="{{ route('editar_ordencompra', ['id' => $fila['ordencompra_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                               target="_blank" rel="noopener" class="text-primary" title="Consultar orden de compra">
                                {{ $fila['nro_oc'] }}
                            </a>
                        @else
                            {{ $fila['nro_oc'] }}
                        @endif
                    @endif
                </td>
                <td>
                    @php
                        $capexCodigo = trim((string) ($fila['capex_codigo'] ?? ''));
                        $capexId = (int) ($fila['capex_id'] ?? 0);
                    @endphp
                    @if ($capexCodigo !== '')
                        @if ($puedeVerCapex && $capexId > 0)
                            <a href="{{ route('editar_capex', ['id' => $capexId, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                               target="_blank" rel="noopener" class="text-primary" title="Consultar Capex">
                                {{ $capexCodigo }}
                            </a>
                        @else
                            {{ $capexCodigo }}
                        @endif
                    @endif
                </td>
                <td>{{ $fila['emisor'] ?? '' }}</td>
                <td>{{ $fila['cuit'] ?? '' }}</td>
                <td>{{ $fila['descripcion'] ?? '' }}</td>
                <td>{{ $fila['moneda_abrev'] ?? '' }}</td>
                <td class="text-right">
                    @if (! empty($fila['cotizacion']))
                        {{ $formatearCotizacion($fila['cotizacion']) }}
                    @endif
                </td>
                <td class="text-right">{{ $formatearMonto($fila['debe'] ?? null) }}</td>
                <td class="text-right">{{ $formatearMonto($fila['haber'] ?? null) }}</td>
            </tr>
        @endif
    @empty
        <tr>
            <td colspan="{{ $colSpanVacio }}" class="text-center text-muted py-4">
                No se generaron imputaciones para el período seleccionado.
            </td>
        </tr>
    @endforelse
</tbody>
