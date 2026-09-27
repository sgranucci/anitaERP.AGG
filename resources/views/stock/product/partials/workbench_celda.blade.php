@switch ($key)
    @case('sku')
        <td>{{ $articulo->stkm_articulo ?? '' }}</td>
        @break
    @case('descripcion')
        <td>{{ $articulo->stkm_desc ?? '' }}</td>
        @break
    @case('categoria')
        <td>{{ $articulo->stkm_agrupacion ?? '' }}</td>
        @break
    @case('marca')
        <td>{{ $articulo->stkm_marca ?? '' }}</td>
        @break
    @case('linea')
        <td>{{ $articulo->stkm_linea ?? '' }}</td>
        @break
    @case('canal')
        <td class="text-nowrap">
            @php
                $codigosCanal = $articulo->relationLoaded('canales')
                    ? $articulo->canales->pluck('codigo')->map(fn ($c) => strtoupper((string) $c))->all()
                    : [];
                $tieneFab = in_array('FABRICA', $codigosCanal, true);
                $tieneLoc = in_array('LOCAL', $codigosCanal, true);
            @endphp
            <span class="badge {{ $tieneFab ? 'badge-warning' : 'badge-light text-muted' }}" title="Canal fábrica">
                Fábrica
            </span>
            <span class="badge {{ $tieneLoc ? 'badge-info' : 'badge-light text-muted' }}" title="Canal local">
                Local
            </span>
        </td>
        @break
    @case('nofactura')
        <td>{{ \App\Support\Stock\ArticuloNofacturaSupport::etiqueta($articulo->nofactura) }}</td>
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
                {{ $articulo->estado ?? '' }}
            @endif
        </td>
        @break
    @case('fecha_alta')
        <td>{{ \App\Support\Listado\ListadoQbeSupport::formatearFecha((string) ($articulo->created_at ?? '')) }}</td>
        @break
    @case('fecha_modificacion')
        <td>{{ \App\Support\Listado\ListadoQbeSupport::formatearFecha((string) ($articulo->updated_at ?? '')) }}</td>
        @break
    @default
        <td></td>
@endswitch
