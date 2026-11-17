@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $excel = ! empty($excel);
    $pdf = ! empty($pdf);
    $filasVista = $filas ?? [];
    if ($filasVista instanceof \Illuminate\Pagination\LengthAwarePaginator) {
        $filasVista = $filasVista->items();
    }
    $puedeRecepcion = ! $excel && ! $pdf && ($puede_ver_recepcion ?? false);
    $puedeArticulo = ! $excel && ! $pdf && ($puede_ver_articulo ?? false);
    $puedeProveedor = ! $excel && ! $pdf && ($puede_ver_proveedor ?? false);
    $colspan = 18;
    // Excel: 4 decimales en cant/coef (cabeceras legibles); 2 en precios.
    $fmtCant = function ($n) use ($excel) {
        if ($n === null || $n === '') {
            return '';
        }
        if ($excel) {
            return number_format((float) $n, 4, '.', '');
        }

        return number_format((float) $n, 6, ',', '.');
    };
    $fmt2 = function ($n) use ($excel) {
        if ($n === null || $n === '') {
            return '';
        }
        if ($excel) {
            return number_format((float) $n, 2, '.', '');
        }

        return number_format((float) $n, 2, ',', '.');
    };
    $logosCabecera = $pdf
        ? EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect($filasVista)->map(fn ($f) => (object) [
            'nombreempresa' => is_array($f) ? ($f['nombreempresa'] ?? '') : '',
        ]))
        : [];
    $totalFilas = (int) ($totales['lineas'] ?? collect($filasVista)->where('tipo_fila', 'dato')->count());
    $tablaAncha = ! $excel && ! $pdf && ! empty($tabla_ancha);
    $claseTabla = $pdf
        ? 'data'
        : ('table table-sm table-bordered mb-0 rcc-tabla'.($tablaAncha ? ' tabla-ancha' : ''));
@endphp
<table class="{{ $claseTabla }}" @if (! $excel && ! $pdf) id="tabla-paginada" @endif>
    @if ($excel)
        @if (! empty($reservarFilaLogoExcel))
            <tr>
                <td colspan="{{ $colspan }}" style="height: 52px;"></td>
            </tr>
        @endif
        <tr>
            <td colspan="{{ $colspan }}"><strong>{{ $titulo ?? 'Control conversión recepción' }}</strong></td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}">Generado {{ date('d/m/Y H:i') }}</td>
        </tr>
        @if (trim((string) ($subtitulo ?? '')) !== '')
            <tr>
                <td colspan="{{ $colspan }}">{{ $subtitulo }}</td>
            </tr>
        @endif
        <tr>
            <td colspan="{{ $colspan }}">
                Líneas: {{ $totalFilas }}
                · Con insumo {{ (int) ($totales['con_insumo'] ?? 0) }}
                · TRA {{ (int) ($totales['convertidas_tra'] ?? 0) }}
                · Teóricas {{ (int) ($totales['teoricas'] ?? 0) }}
            </td>
        </tr>
    @endif
    <thead>
        @if ($pdf)
            @include('includes.reportes.pdf_thead_cabecera', [
                'titulo' => $titulo ?? 'Control conversión recepción',
                'subtitulo' => $subtitulo ?? '',
                'colspan' => $colspan,
                'logosCabecera' => $logosCabecera,
                'totalFilas' => $totalFilas,
            ])
        @endif
        <tr class="columnas" style="background:#85C1E9;color:#17202A;">
            <th>Fecha</th>
            <th>Empresa</th>
            <th>Proveedor</th>
            <th>Tipo</th>
            <th>Nro</th>
            <th>SKU compra</th>
            <th>Descripción</th>
            <th class="text-right">Cant.</th>
            <th>UM compra</th>
            <th class="text-right">Coef.</th>
            <th class="text-right">Precio</th>
            <th>UM stock</th>
            <th>SKU insumo</th>
            <th>Insumo</th>
            <th class="text-right">Cant.insumo</th>
            <th>UM insumo</th>
            <th class="text-right">Coef.ins.</th>
            <th class="text-right">Costo conv.</th>
        </tr>
    </thead>
    <tbody>
    @forelse ($filasVista as $fila)
        @php
            $tipoFila = is_array($fila) ? ($fila['tipo_fila'] ?? 'dato') : 'dato';
        @endphp
        @if ($tipoFila === 'header_empresa')
            <tr class="rcc-header-empresa">
                <td colspan="{{ $colspan }}">{{ is_array($fila) ? ($fila['etiqueta'] ?? $fila['nombreempresa'] ?? '') : '' }}</td>
            </tr>
        @else
            <tr @if (($fila['origen_insumo'] ?? '') === 'teorico') class="table-warning" @endif>
                <td>{{ $fila['fecha_fmt'] ?? '' }}</td>
                <td>{{ $fila['nombreempresa'] ?? '' }}</td>
                <td>
                    @php
                        $provTxt = trim(($fila['codigo_proveedor'] ?? '').' '.($fila['nombreproveedor'] ?? ''));
                    @endphp
                    @if ($puedeProveedor && ! empty($fila['proveedor_id']))
                        <a class="text-primary" target="_blank" rel="noopener"
                            href="{{ route('editar_proveedor', [
                                'id' => $fila['proveedor_id'],
                                'origen' => 'modal_consulta',
                                'vista' => 'consulta',
                            ]) }}">{{ $provTxt }}</a>
                    @else
                        {{ $provTxt }}
                    @endif
                </td>
                <td>{{ $fila['tipo'] ?? '' }}</td>
                <td>
                    @if ($puedeRecepcion && ! empty($fila['recepcion_id']))
                        <a class="text-primary" target="_blank" rel="noopener"
                            href="{{ route('editar_recepcion_proveedor', [
                                'id' => $fila['recepcion_id'],
                                'origen' => 'modal_consulta',
                                'vista' => 'consulta',
                            ]) }}">{{ $fila['numerorecepcion'] ?? '' }}</a>
                    @else
                        {{ $fila['numerorecepcion'] ?? '' }}
                    @endif
                </td>
                <td>
                    @if ($puedeArticulo && ! empty($fila['articulo_id']))
                        <a class="text-primary" target="_blank" rel="noopener"
                            href="{{ route('editar_articulo', [
                                'id' => $fila['articulo_id'],
                                'origen' => 'modal_consulta',
                                'vista' => 'consulta',
                            ]) }}">{{ $fila['sku_compra'] ?? '' }}</a>
                    @else
                        {{ $fila['sku_compra'] ?? '' }}
                    @endif
                </td>
                <td>{{ $fila['descripcion_compra'] ?? '' }}</td>
                <td class="text-right">{{ $fmtCant($fila['cantidad'] ?? null) }}</td>
                <td>{{ $fila['um_compra'] ?? '' }}</td>
                <td class="text-right">{{ $fmtCant($fila['coeficienteconversion'] ?? null) }}</td>
                <td class="text-right">{{ $fmt2($fila['precio'] ?? null) }}</td>
                <td>{{ $fila['um_stock'] ?? '' }}</td>
                <td>
                    @if ($puedeArticulo && ! empty($fila['articulo_stock_id']))
                        <a class="text-primary" target="_blank" rel="noopener"
                            href="{{ route('editar_articulo', [
                                'id' => $fila['articulo_stock_id'],
                                'origen' => 'modal_consulta',
                                'vista' => 'consulta',
                            ]) }}">{{ $fila['sku_insumo'] ?? '' }}</a>
                    @else
                        {{ $fila['sku_insumo'] ?? '' }}
                    @endif
                </td>
                <td>
                    @if ($excel)
                        {{ trim((string) ($fila['descripcion_insumo'] ?? '')) }}
                        @if (($fila['origen_insumo_etiqueta'] ?? '') !== '')
                            {{ ' · '.($fila['origen_insumo_etiqueta'] ?? '') }}
                        @endif
                    @else
                        {{ $fila['descripcion_insumo'] ?? '' }}
                        @if (($fila['origen_insumo_etiqueta'] ?? '') !== '')
                            <small class="text-muted d-block">{{ $fila['origen_insumo_etiqueta'] }}</small>
                        @endif
                    @endif
                </td>
                <td class="text-right">{{ $fmtCant($fila['cantidad_insumo'] ?? null) }}</td>
                <td>{{ $fila['um_insumo'] ?? '' }}</td>
                <td class="text-right">{{ $fmtCant($fila['coef_insumo'] ?? null) }}</td>
                <td class="text-right">{{ $fmt2($fila['precio_insumo'] ?? null) }}</td>
            </tr>
        @endif
    @empty
        <tr>
            <td colspan="{{ $colspan }}" class="text-center text-muted">Sin líneas para el filtro.</td>
        </tr>
    @endforelse
    </tbody>
</table>
