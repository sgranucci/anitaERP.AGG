@php
    use App\Support\Listado\ListadoGrillaConfigSupport;
    use App\Support\Stock\ArticuloFerliListadoColumnas;

    $cfg = $cfg ?? null;
    $alinea = is_array($cfg) ? ($cfg['alinea'] ?? 'izquierda') : 'izquierda';
    $cls = trim(ListadoGrillaConfigSupport::claseAlineacion($alinea).' lw-col');
    $titleTexto = ArticuloFerliListadoColumnas::valorCelda($articulo, $key);
@endphp
@switch ($key)
    @case('sku')
        <td class="{{ $cls }}" @if ($titleTexto !== '') title="{{ $titleTexto }}" @endif>
            <span class="lw-celda-texto">{{ $articulo->stkm_articulo ?? '' }}</span>
        </td>
        @break
    @case('descripcion')
        <td class="{{ $cls }}" @if ($titleTexto !== '') title="{{ $titleTexto }}" @endif>
            <span class="lw-celda-texto">{{ $articulo->stkm_desc ?? '' }}</span>
        </td>
        @break
    @case('categoria')
        <td class="{{ $cls }}" @if ($titleTexto !== '') title="{{ $titleTexto }}" @endif>
            <span class="lw-celda-texto">{{ $articulo->stkm_agrupacion ?? '' }}</span>
        </td>
        @break
    @case('marca')
        <td class="{{ $cls }}" @if ($titleTexto !== '') title="{{ $titleTexto }}" @endif>
            <span class="lw-celda-texto">{{ $articulo->stkm_marca ?? '' }}</span>
        </td>
        @break
    @case('linea')
        <td class="{{ $cls }}" @if ($titleTexto !== '') title="{{ $titleTexto }}" @endif>
            <span class="lw-celda-texto">{{ $articulo->stkm_linea ?? '' }}</span>
        </td>
        @break
    @case('canal')
        <td class="{{ $cls }} text-nowrap">
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
        <td class="{{ $cls }}">
            <span class="lw-celda-texto">{{ \App\Support\Stock\ArticuloNofacturaSupport::etiqueta($articulo->nofactura) }}</span>
        </td>
        @break
    @case('estado')
        <td class="{{ $cls }}">
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
                <span class="lw-celda-texto">{{ $articulo->estado ?? '' }}</span>
            @endif
        </td>
        @break
    @case('fecha_alta')
        <td class="{{ $cls }}">
            <span class="lw-celda-texto">{{ \App\Support\Listado\ListadoQbeSupport::formatearFecha((string) ($articulo->created_at ?? '')) }}</span>
        </td>
        @break
    @case('fecha_modificacion')
        <td class="{{ $cls }}">
            <span class="lw-celda-texto">{{ \App\Support\Listado\ListadoQbeSupport::formatearFecha((string) ($articulo->updated_at ?? '')) }}</span>
        </td>
        @break
    @default
        <td class="{{ $cls }}"></td>
@endswitch
