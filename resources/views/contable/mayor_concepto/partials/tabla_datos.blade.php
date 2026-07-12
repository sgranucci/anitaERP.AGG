@php
    $formatearMonto = static function ($valor) {
        if ($valor === null || $valor === '' || (float) $valor === 0.0) {
            return '';
        }

        return number_format((float) $valor, 2, ',', '.');
    };
    $mostrarEmpresa = $multiempresa ?? false;
    $colSpanTotales = $mostrarEmpresa ? 16 : 15;
    $colSpanVacio = $mostrarEmpresa ? 18 : 17;
@endphp
<thead>
    <tr>
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
        <th>Emisor</th>
        <th>CUIT</th>
        <th>Descripción mov.</th>
        <th>Mon</th>
        <th class="text-right">Cotiz.</th>
        <th class="text-right">Debe</th>
        <th class="text-right">Haber</th>
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
                    Total cuenta {{ $fila['cuenta_codigo'] ?? '' }}
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
                    Total concepto {{ $fila['concepto_id'] ?? '' }}
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
                    @if (($puede_ver_concepto ?? false) && (int) ($fila['concepto_id'] ?? 0) > 0)
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
                    @if (($puede_ver_cuenta ?? false) && (int) ($fila['cuentacontable_id'] ?? 0) > 0)
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
                    @if (($puede_ver_asiento ?? false) && (int) ($fila['asiento_id'] ?? 0) > 0)
                        <a href="{{ route('editar_asiento', ['id' => $fila['asiento_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                           target="_blank" rel="noopener" class="text-primary">
                            {{ $fila['nro_asiento'] ?? '' }}
                        </a>
                    @else
                        {{ $fila['nro_asiento'] ?? '' }}
                    @endif
                </td>
                <td>{{ $fila['tipo_comp'] ?? '' }}</td>
                <td>{{ $fila['comprobante'] ?? '' }}</td>
                <td>{{ $fila['cheque'] ?? '' }}</td>
                <td>{{ (int) ($fila['nro_oc'] ?? 0) > 0 ? $fila['nro_oc'] : '' }}</td>
                <td>{{ $fila['emisor'] ?? '' }}</td>
                <td>{{ $fila['cuit'] ?? '' }}</td>
                <td>{{ $fila['descripcion'] ?? '' }}</td>
                <td>{{ $fila['moneda_abrev'] ?? '' }}</td>
                <td class="text-right">
                    @if (! empty($fila['cotizacion']))
                        {{ number_format((float) $fila['cotizacion'], 4, ',', '.') }}
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
