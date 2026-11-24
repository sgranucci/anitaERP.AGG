@php
    use App\Support\Listado\ListadoGrillaConfigSupport;
    use App\Support\Stock\ArticuloListadoColumnas;

    $cfg = $cfg ?? null;
    $alinea = is_array($cfg) ? ($cfg['alinea'] ?? 'izquierda') : (in_array($key, ['saldo', 'precio_proveedor'], true) ? 'derecha' : 'izquierda');
    $cls = trim(ListadoGrillaConfigSupport::claseAlineacion($alinea).' lw-col');
    $titleTexto = ArticuloListadoColumnas::valorCelda($articulo, $key);
@endphp
@switch ($key)
    @case('sku')
        <td class="{{ $cls }}" @if ($titleTexto !== '') title="{{ $titleTexto }}" @endif>
            <span class="lw-celda-texto">
                {{ $articulo->codigoarticulo ?? '' }}
                @if (! empty($articulo->fl_precio_promedio_transferencia))
                    <span class="badge badge-info ml-1" title="Artículo TITO (transferencia contable)">TITO</span>
                @endif
            </span>
        </td>
        @break
    @case('canal')
        @if (\App\Support\Stock\ArticuloListadoFiltros::filtroCanalActivo())
            <td class="{{ $cls }}">
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
            <td class="{{ $cls }}" title="{{ $articulo->nombreempresa ?: 'Todas' }}">
                <span class="lw-celda-texto"><small>{{ $articulo->nombreempresa ?: 'Todas' }}</small></span>
            </td>
        @endif
        @break
    @case('saldo')
        <td class="{{ $cls }} text-right">
            @if (isset($saldosDeposito[$articulo->id]))
                {{ number_format($saldosDeposito[$articulo->id], 2, ',', '.') }}
            @endif
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
                <span class="lw-celda-texto">{{ $articulo->estado }}</span>
            @endif
        </td>
        @break
    @case('proveedor_art')
        <td class="{{ $cls }}" @if ($titleTexto !== '') title="{{ $titleTexto }}" @endif>
            @include('includes.listado.celda_enlace', [
                'filaEnlace' => $articulo,
                'metaEnlace' => ArticuloListadoColumnas::catalogoActivo()[$key] ?? [],
                'textoEnlace' => $articulo->proveedor_art ?? '',
            ])
        </td>
        @break
    @case('precio_proveedor')
        <td class="{{ $cls }} text-right" @if ($titleTexto !== '') title="{{ $titleTexto }}" @endif>
            <span class="lw-celda-texto">{{ $titleTexto }}</span>
        </td>
        @break
    @default
        <td class="{{ $cls }}" @if ($titleTexto !== '') title="{{ $titleTexto }}" @endif>
            <span class="lw-celda-texto">{{ $titleTexto }}</span>
        </td>
@endswitch
