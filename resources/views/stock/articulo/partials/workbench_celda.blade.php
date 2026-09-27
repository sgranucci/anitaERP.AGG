@switch ($key)
    @case('sku')
        <td>
            {{ $articulo->codigoarticulo ?? '' }}
            @if (! empty($articulo->fl_precio_promedio_transferencia))
                <span class="badge badge-info ml-1" title="Artículo TITO (transferencia contable)">TITO</span>
            @endif
        </td>
        @break
    @case('codigobarra')
        <td>{{ $articulo->codigobarra ?? '' }}</td>
        @break
    @case('descripcion')
        <td>{{ $articulo->descripcion ?? '' }}</td>
        @break
    @case('unidadmedida')
        <td>{{ $articulo->nombreunidadmedida ?? '' }}</td>
        @break
    @case('categoria')
        <td>{{ $articulo->nombrecategoria ?? '' }}</td>
        @break
    @case('tipoarticulo')
        <td>{{ $articulo->nombretipoarticulo ?? '' }}</td>
        @break
    @case('usoarticulo')
        <td>{{ $articulo->nombreusoarticulo ?? '' }}</td>
        @break
    @case('canal')
        @if (\App\Support\Stock\ArticuloListadoFiltros::filtroCanalActivo())
            <td>
                @php
                    $nombresCanal = $articulo->relationLoaded('canales')
                        ? $articulo->canales->pluck('nombre')->filter()->implode(', ')
                        : '';
                @endphp
                @if ($nombresCanal !== '')
                    <span class="badge badge-warning">{{ $nombresCanal }}</span>
                @else
                    <span class="text-muted small">—</span>
                @endif
            </td>
        @endif
        @break
    @case('empresa')
        @if ($filtroEmpresaActivo)
            <td><small>{{ $articulo->nombreempresa ?: 'Todas' }}</small></td>
        @endif
        @break
    @case('numeroparte')
        <td>{{ $articulo->numeroparte ?? '' }}</td>
        @break
    @case('ubicacionparte')
        <td>{{ $articulo->ubicacionparte ?? '' }}</td>
        @break
    @case('saldo')
        <td class="text-right">
            @if (isset($saldosStkdep[$articulo->id]))
                {{ number_format($saldosStkdep[$articulo->id], 2, ',', '.') }}
            @endif
        </td>
        @break
    @case('nofactura')
        <td>{{ ($articulo->nofactura == '0' ? 'Facturable' : ($articulo->nofactura == '1' ? 'No facturable' : '')) }}</td>
        @break
    @case('estado')
        <td>
            @if (\App\Support\Stock\ArticuloEstadoCanalSupport::uiFerliActiva())
                @php
                    $ef = strtoupper((string) ($articulo->estado_fabrica ?? $articulo->estado ?? ''));
                    $el = strtoupper((string) ($articulo->estado_local ?? $articulo->estado ?? ''));
                @endphp
                <span class="badge {{ $ef === 'ACTIVO' ? 'badge-success' : 'badge-secondary' }}" title="Estado fábrica">
                    Fab {{ $ef === 'ACTIVO' ? 'A' : 'I' }}
                </span>
                <span class="badge {{ $el === 'ACTIVO' ? 'badge-info' : 'badge-secondary' }}" title="Estado local">
                    Loc {{ $el === 'ACTIVO' ? 'A' : 'I' }}
                </span>
            @else
                {{ $articulo->estado }}
            @endif
        </td>
        @break
    @case('fecha_alta')
        <td>{{ $articulo->created_at ? \Carbon\Carbon::parse($articulo->created_at)->format('d/m/Y') : '' }}</td>
        @break
    @case('fecha_modificacion')
        <td>{{ $articulo->updated_at ? \Carbon\Carbon::parse($articulo->updated_at)->format('d/m/Y') : '' }}</td>
        @break
@endswitch
