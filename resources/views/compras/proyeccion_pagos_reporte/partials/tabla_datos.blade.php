@php
    use App\Support\Compras\ProyeccionPagosColumnasSupport as ColProy;

    $paraPdf = $para_pdf ?? false;
    $paraExcel = ! empty($para_excel);
    $columnasProy = $columnas ?? [];
    $colSpan = max(1, count($columnasProy));
    $queryConsulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
    $puedeVerProveedor = ! $paraPdf && ! $paraExcel && ($puede_ver_proveedor ?? false);
    $puedeVerComprobante = ! $paraPdf && ! $paraExcel && ($puede_ver_comprobante ?? false);
    $puedeVerOrdencompra = ! $paraPdf && ! $paraExcel && ($puede_ver_ordencompra ?? false);
    $puedeVerRequisicion = ! $paraPdf && ! $paraExcel && ($puede_ver_requisicion ?? false);
    $puedeVerConcepto = ! $paraPdf && ! $paraExcel && ($puede_ver_concepto ?? false);
    $puedeVerCuentacontable = ! $paraPdf && ! $paraExcel && ($puede_ver_cuentacontable ?? false);
    $envoltorioTabla = ! ($solo_filas ?? false);

    $numero = static function ($valor, int $decimales = 2) use ($paraExcel) {
        $valor = (float) $valor;
        if ($paraExcel) {
            return $valor;
        }

        return number_format($valor, $decimales, ',', '.');
    };

    $importe = static function ($valor) use ($numero, $paraExcel) {
        if (! $paraExcel && abs((float) $valor) < 0.005) {
            return '';
        }

        return $numero($valor, 2);
    };

    $fecha = static function ($valor) {
        if ($valor === null || trim((string) $valor) === '') {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($valor)->format('d/m/y');
        } catch (\Throwable) {
            return '';
        }
    };

    $alineacion = static function (array $columna): string {
        return in_array($columna['tipo'], [ColProy::TIPO_IMPORTE, ColProy::TIPO_ENTERO, ColProy::TIPO_RATIO], true)
            ? 'text-right'
            : ($columna['tipo'] === ColProy::TIPO_FECHA ? 'text-center' : '');
    };

    $celda = static function (array $columna, array $fila) use ($importe, $numero, $fecha, $puedeVerProveedor, $puedeVerComprobante, $puedeVerOrdencompra, $puedeVerRequisicion, $puedeVerConcepto, $puedeVerCuentacontable, $queryConsulta, $paraExcel) {
        $clave = $columna['clave'];
        $valor = $fila['valores'][$clave] ?? null;

        if ($columna['tipo'] === ColProy::TIPO_IMPORTE) {
            return $importe($valor);
        }

        if ($columna['tipo'] === ColProy::TIPO_RATIO) {
            return (float) $valor > 0 ? $numero($valor, 4) : '';
        }

        if ($columna['tipo'] === ColProy::TIPO_ENTERO) {
            if ($valor === null || $valor === '') {
                return '';
            }

            return $paraExcel ? (int) $valor : number_format((int) $valor, 0, ',', '.');
        }

        if ($columna['tipo'] === ColProy::TIPO_FECHA) {
            return $fecha($valor);
        }

        $texto = (string) ($valor ?? '');

        if ($texto === '') {
            return '';
        }

        if ($clave === 'proveedor_codigo' && $puedeVerProveedor && (int) ($fila['proveedor_id'] ?? 0) > 0) {
            return '<a href="'.route('editar_proveedor', array_merge(['id' => (int) $fila['proveedor_id']], $queryConsulta))
                .'" class="text-primary" target="_blank" rel="noopener">'.e($texto).'</a>';
        }

        if ($clave === 'comprobante' && $puedeVerComprobante && (int) ($fila['comprobante_proveedor_id'] ?? 0) > 0) {
            return '<a href="'.route('editar_comprobante_proveedor', array_merge(['id' => (int) $fila['comprobante_proveedor_id']], $queryConsulta))
                .'" class="text-primary" target="_blank" rel="noopener">'.e($texto).'</a>';
        }

        if ($clave === 'nro_referencia' && $puedeVerOrdencompra && (int) ($fila['ordencompra_id'] ?? 0) > 0) {
            return '<a href="'.route('editar_ordencompra', array_merge(['id' => (int) $fila['ordencompra_id']], $queryConsulta))
                .'" class="text-primary" target="_blank" rel="noopener">'.e($texto).'</a>';
        }

        if ($clave === 'requisicion' && $puedeVerRequisicion && (int) ($fila['requisicion_id'] ?? 0) > 0) {
            return '<a href="'.route('editar_requisicion', array_merge(['id' => (int) $fila['requisicion_id']], $queryConsulta))
                .'" class="text-primary" target="_blank" rel="noopener">'.e($texto).'</a>';
        }

        if (in_array($clave, ['concepto', 'detalle_concepto'], true) && (int) ($fila['conceptogasto_id'] ?? 0) > 0) {
            $origenes = [
                'pago' => 'Concepto del movimiento de caja del pago',
                'cuenta' => 'Concepto de la cuenta contable imputada en el comprobante',
                'asiento' => 'Concepto de la cuenta de mayor importe del asiento',
                'proveedor' => 'Concepto por defecto del proveedor',
            ];
            $titulo = $origenes[$fila['concepto_origen'] ?? ''] ?? '';

            if (! $puedeVerConcepto) {
                return '<span'.($titulo !== '' ? ' title="'.e($titulo).'"' : '').'>'.e($texto).'</span>';
            }

            return '<a href="'.route('editar_conceptogasto', array_merge(['id' => (int) $fila['conceptogasto_id']], $queryConsulta))
                .'" class="text-primary" target="_blank" rel="noopener"'
                .($titulo !== '' ? ' title="'.e($titulo).'"' : '').'>'.e($texto).'</a>';
        }

        if ($clave === 'cuenta_concepto' && $puedeVerCuentacontable && (int) ($fila['concepto_cuentacontable_id'] ?? 0) > 0) {
            return '<a href="'.route('editar_cuentacontable', array_merge(['id' => (int) $fila['concepto_cuentacontable_id']], $queryConsulta))
                .'" class="text-primary" target="_blank" rel="noopener">'.e($texto).'</a>';
        }

        return e($texto);
    };

    $primeraImporte = null;
    foreach ($columnasProy as $indice => $columna) {
        if ($columna['tipo'] === ColProy::TIPO_IMPORTE) {
            $primeraImporte = $indice;
            break;
        }
    }
    $colspanEtiqueta = $primeraImporte === null ? $colSpan : (int) $primeraImporte;
@endphp
@if ($envoltorioTabla && ! ($solo_body ?? false))
<thead @if (! $paraPdf) style="background:#85C1E9;color:#17202A;" @endif>
    <tr>
        @foreach ($columnasProy as $columna)
            <th class="{{ $alineacion($columna) }}"
                @if (! empty($columna['ayuda']) && ! $paraPdf) title="{{ $columna['ayuda'] }}" @endif>
                {{ $columna['etiqueta'] }}
            </th>
        @endforeach
    </tr>
</thead>
@elseif ($cabecera_en_filas ?? false)
    <tr>
        @foreach ($columnasProy as $columna)
            <th>{{ $columna['etiqueta'] }}</th>
        @endforeach
    </tr>
@endif
@if ($envoltorioTabla)
<tbody>
@endif
    @forelse ($filas as $fila)
        @php $tipo = $fila['tipo_fila'] ?? 'detalle'; @endphp
        @if ($tipo === 'header_empresa')
            <tr class="font-weight-bold" style="background-color:#d6eaf8;">
                <td colspan="{{ $colSpan }}" @if ($paraPdf) style="background-color:#d6eaf8;font-weight:bold;" @endif>
                    Empresa: {{ $fila['nombreempresa'] ?? $fila['etiqueta'] ?? '' }}
                </td>
            </tr>
        @elseif ($tipo === 'cabecera_grupo')
            <tr class="proy-grupo-cabecera {{ $paraPdf ? 'grupo' : 'font-weight-bold bg-light' }}"
                @if (! $paraPdf) data-grupo-id="{{ $fila['grupo_id'] ?? '' }}" style="cursor:pointer;" @endif>
                <td colspan="{{ $colSpan }}" @if ($paraPdf) style="background-color:#e9ecef;font-weight:bold;" @endif>
                    @if (! $paraPdf)
                        <i class="fa fa-chevron-down proy-grupo-icon mr-1"></i>
                    @endif
                    {{ $fila['etiqueta'] ?? '' }}
                </td>
            </tr>
        @elseif ($tipo === 'subtotal_grupo' || $tipo === 'total_general')
            @php
                $esTotal = $tipo === 'total_general';
                $fondo = $esTotal ? '#d6eaf8' : '#e9ecef';
            @endphp
            @if ($colspanEtiqueta === 0)
                <tr class="font-weight-bold" style="background-color:{{ $fondo }};">
                    <td colspan="{{ $colSpan }}"
                        @if ($paraPdf) style="background-color:{{ $fondo }};font-weight:bold;" @endif>
                        {{ $fila['etiqueta'] ?? '' }}
                    </td>
                </tr>
            @endif
            <tr class="{{ $esTotal ? 'proy-total-general' : 'proy-grupo-subtotal' }} font-weight-bold"
                style="background-color:{{ $fondo }};">
                @if ($colspanEtiqueta > 0)
                    <td colspan="{{ $colspanEtiqueta }}"
                        @if ($paraPdf) style="background-color:{{ $fondo }};font-weight:bold;" @endif>
                        {{ $fila['etiqueta'] ?? '' }}
                        @if ((int) ($fila['cantidad'] ?? 0) > 0)
                            <small>({{ (int) $fila['cantidad'] }})</small>
                        @endif
                    </td>
                @endif
                @foreach ($columnasProy as $indice => $columna)
                    @continue($indice < $colspanEtiqueta)
                    <td class="{{ $alineacion($columna) }}"
                        @if ($paraPdf) style="background-color:{{ $fondo }};font-weight:bold;" @endif>
                        @if ($columna['tipo'] === ColProy::TIPO_IMPORTE)
                            {{ $importe($fila['valores'][$columna['clave']] ?? 0) }}
                        @endif
                    </td>
                @endforeach
            </tr>
            @if (! $paraPdf && ! $paraExcel && ! $esTotal)
                <tr class="proy-grupo-spacer"><td colspan="{{ $colSpan }}">&nbsp;</td></tr>
            @endif
        @else
            <tr class="proy-grupo-detalle proy-grupo-{{ $fila['grupo_id'] ?? 0 }}">
                @foreach ($columnasProy as $columna)
                    <td class="{{ $alineacion($columna) }}">{!! $celda($columna, $fila) !!}</td>
                @endforeach
            </tr>
        @endif
    @empty
        <tr>
            <td colspan="{{ $colSpan }}" class="text-center text-muted py-4">
                Sin deuda de proveedores para los filtros indicados.
            </td>
        </tr>
    @endforelse
@if ($envoltorioTabla)
</tbody>
@endif
