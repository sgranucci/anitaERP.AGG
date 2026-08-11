@extends("theme.$theme.layout")
@section('titulo')
Trazabilidad Surmar
@endsection

@section('scripts')
<style>
    .traz-surmar thead th { background:#85C1E9; color:#17202A; }
    .traz-evento { border-left: 3px solid #85C1E9; padding: .5rem .75rem; margin-bottom: .5rem; background: #f8fbfc; }
    .traz-evento .meta { font-size: .8rem; color: #566573; }
    .traz-cadena li { margin-bottom: .35rem; }
</style>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}" type="text/javascript"></script>
<script>
(function ($) {
    $(function () {
        if (typeof window.activa_eventos_consultaarticulo === 'function') {
            window.activa_eventos_consultaarticulo();
        }
        $('#form-traz-surmar').on('submit', function () {
            if (!$('#etiqueta_id').val() && !$('#articulo_id').val() && !$.trim($('#lote').val())
                && !$.trim($('#codigo').val()) && !$('#anita_nro_interno').val()) {
                alert('Ingresá un código/escaneo, ID, n.interno Anita, o artículo y/o lote.');
                return false;
            }
            $('#articulo_sku_hidden').val($('#codigoarticulo').val() || '');
            $('#articulo_desc_hidden').val($('#descripcionarticulo').val() || '');
        });
    });
})(jQuery);
</script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-sitemap"></i> Trazabilidad Surmar</h3>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Consultá etiquetas <strong>viejas Anita</strong> o <strong>nuevas anitaERP</strong>:
                    escaneo (ID ERP / <code>nint-nap</code> / <code>sku-nint-nap</code>), nro. interno Anita, o artículo + lote.
                </p>
                <form method="get" action="{{ route('trazabilidad_surmar') }}" id="form-traz-surmar" class="mb-4">
                    <input type="hidden" name="consultar" value="1">
                    <input type="hidden" id="empresa_id" value="3">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-3">
                            <label class="control-label" for="codigo">Escanear / código</label>
                            <input type="text" name="codigo" id="codigo" class="form-control" value="{{ $codigo ?? '' }}" placeholder="ID o sku-nint-nap">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="control-label" for="etiqueta_id">ID etiqueta</label>
                            <input type="number" min="1" name="etiqueta_id" id="etiqueta_id" class="form-control" value="{{ $etiqueta_id }}" placeholder="Ej. 125">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="control-label" for="anita_nro_interno">Anita n.interno</label>
                            <input type="number" min="1" name="anita_nro_interno" id="anita_nro_interno" class="form-control" value="{{ $anita_nro_interno ?? '' }}">
                        </div>
                        <div class="form-group col-md-1">
                            <label class="control-label" for="anita_nro_apertura">Nap</label>
                            <input type="number" min="1" name="anita_nro_apertura" id="anita_nro_apertura" class="form-control" value="{{ $anita_nro_apertura ?? '' }}">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="control-label" for="lote">Lote</label>
                            <input type="text" name="lote" id="lote" class="form-control" value="{{ $lote }}" maxlength="30" placeholder="Lote">
                        </div>
                        <div class="form-group col-md-2">
                            <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-search"></i> Consultar</button>
                        </div>
                    </div>
                    <div class="form-row align-items-end mt-2">
                        <div class="form-group col-md-6 mb-0">
                            <label class="control-label">Artículo (opcional)</label>
                            <div class="input-group">
                                <input type="hidden" name="articulo_id" id="articulo_id" class="articulo_id" value="{{ $articulo_id }}">
                                <input type="hidden" name="articulo_sku" id="articulo_sku_hidden" value="{{ $articulo_sku }}">
                                <input type="hidden" name="articulo_desc" id="articulo_desc_hidden" value="{{ $articulo_desc }}">
                                <input type="text" id="codigoarticulo" class="form-control codigoarticulo" placeholder="SKU" style="max-width:7rem;" value="{{ $articulo_sku }}">
                                <input type="text" id="descripcionarticulo" class="form-control descripcionarticulo" placeholder="Descripción" readonly value="{{ $articulo_desc }}">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary consultaarticulo" title="Consultar artículos"><i class="fa fa-search"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                @if ($consultar)
                    @if ($etiquetas->isEmpty())
                        <div class="alert alert-warning mb-0">No se encontraron etiquetas con esos criterios.</div>
                    @elseif ($historial === null)
                        <h5 class="mb-2">Etiquetas encontradas ({{ $etiquetas->count() }})</h5>
                        <div class="table-responsive traz-surmar">
                            <table class="table table-sm table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>ID ERP</th>
                                        <th>Anita nint/nap</th>
                                        <th>SKU</th>
                                        <th>Descripción</th>
                                        <th>Lote</th>
                                        <th>Neto</th>
                                        <th>Estado</th>
                                        <th>Origen</th>
                                        <th>Emisión</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($etiquetas as $e)
                                        <tr>
                                            <td>{{ $e->id }}</td>
                                            <td>
                                                @if ($e->anita_nro_interno)
                                                    {{ $e->anita_nro_interno }}/{{ $e->anita_nro_apertura }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td>{{ $e->articulos->sku ?? '' }}</td>
                                            <td>{{ $e->descripcion_snapshot ?: ($e->articulos->descripcion ?? '') }}</td>
                                            <td>{{ $e->lote_proveedor }}</td>
                                            <td class="text-right">{{ number_format((float) $e->peso_neto, 2, ',', '.') }}</td>
                                            <td>{{ $e->estado }}</td>
                                            <td>{{ $e->origen_tipo }}</td>
                                            <td>{{ optional($e->fecha_emision)->format('d/m/Y') }} {{ $e->hora_emision }}</td>
                                            <td>
                                                <a class="btn btn-sm btn-outline-primary" href="{{ route('trazabilidad_surmar', ['etiqueta_id' => $e->id, 'consultar' => 1]) }}">
                                                    Historial
                                                </a>
                                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('movimientostock_etiqueta_surmar_zpl', $e->id) }}" target="_blank" rel="noopener">
                                                    ZPL
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        @php
                            $etiq = $historial['etiqueta'];
                            $art = $etiq->articulos;
                        @endphp
                        <div class="row">
                            <div class="col-lg-4">
                                <div class="card card-outline card-info mb-3">
                                    <div class="card-header py-2"><strong>Etiqueta #{{ $etiq->id }}</strong></div>
                                    <div class="card-body p-2">
                                        <dl class="row mb-0 small">
                                            <dt class="col-5">Artículo</dt>
                                            <dd class="col-7">{{ $art->sku ?? '' }} — {{ $etiq->descripcion_snapshot ?: ($art->descripcion ?? '') }}</dd>
                                            <dt class="col-5">Lote</dt>
                                            <dd class="col-7">{{ $etiq->lote_proveedor ?: '—' }}</dd>
                                            <dt class="col-5">Vto</dt>
                                            <dd class="col-7">{{ optional($etiq->fecha_vto)->format('d/m/Y') ?: '—' }}</dd>
                                            <dt class="col-5">Pesos</dt>
                                            <dd class="col-7">B {{ number_format((float)$etiq->peso_bruto,2,',','.') }} / N {{ number_format((float)$etiq->peso_neto,2,',','.') }}</dd>
                                            <dt class="col-5">Piezas</dt>
                                            <dd class="col-7">{{ number_format((float)$etiq->cant_pieza,2,',','.') }}</dd>
                                            <dt class="col-5">Estado</dt>
                                            <dd class="col-7">{{ $etiq->estado }}</dd>
                                            <dt class="col-5">Depósito</dt>
                                            <dd class="col-7">{{ $etiq->depositos->nombre ?? '—' }}</dd>
                                            <dt class="col-5">Origen</dt>
                                            <dd class="col-7">{{ $etiq->origen_tipo }} #{{ $etiq->origen_id }}</dd>
                                        </dl>
                                        @if (can('imprimir-etiqueta-recepcion-surmar', false))
                                            <a class="btn btn-sm btn-outline-secondary mt-2" target="_blank" rel="noopener"
                                               href="{{ route('imprimir_etiqueta_surmar', $etiq->id) }}">
                                                <i class="fa fa-print"></i> ZPL
                                            </a>
                                        @endif
                                    </div>
                                </div>

                                <div class="card card-outline card-secondary mb-3">
                                    <div class="card-header py-2"><strong>Cadena hasta COM</strong></div>
                                    <div class="card-body p-2">
                                        @if (empty($historial['cadena_origen']))
                                            <span class="text-muted small">Sin cadena.</span>
                                        @else
                                            <ol class="traz-cadena pl-3 mb-0 small">
                                                @foreach ($historial['cadena_origen'] as $nodo)
                                                    <li>
                                                        <a href="{{ route('trazabilidad_surmar', ['etiqueta_id' => $nodo['etiqueta_id'], 'consultar' => 1]) }}">
                                                            #{{ $nodo['etiqueta_id'] }}
                                                        </a>
                                                        · {{ $nodo['origen_tipo'] }}
                                                        · {{ $nodo['sku'] }}
                                                        · lote {{ $nodo['lote_proveedor'] ?: '—' }}
                                                        · {{ number_format($nodo['peso_neto'], 2, ',', '.') }} kg
                                                    </li>
                                                @endforeach
                                            </ol>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-8">
                                <h5>Historial</h5>
                                @forelse ($historial['eventos'] as $ev)
                                    <div class="traz-evento">
                                        <div class="meta">
                                            {{ $ev['fecha'] ?? '—' }}
                                            @if (!empty($ev['hora']))
                                                · {{ $ev['hora'] }}
                                            @endif
                                            · {{ $ev['tipo'] }}
                                        </div>
                                        <div><strong>{{ $ev['titulo'] }}</strong></div>
                                        <div class="small">{{ $ev['detalle'] ?? '' }}</div>
                                        @if (!empty($ev['ref']['url']))
                                            <a class="small" href="{{ $ev['ref']['url'] }}" target="_blank" rel="noopener">{{ $ev['ref']['label'] }}</a>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-muted">Sin eventos.</p>
                                @endforelse
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
@include('includes.stock.modalconsultaarticulo')
@endsection
