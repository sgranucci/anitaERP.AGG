@extends("theme.$theme.layout")
@section('titulo')
    Nuevo cumplimiento &mdash; requisici&oacute;n de compra
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if (!empty($pdfToken))
            <div class="alert alert-success">
                Cumplimiento grabado.
                <a href="{{ route('pdf_cumplir_requisicion_compra', ['token' => $pdfToken]) }}" class="btn btn-sm btn-outline-dark ml-2" target="_blank" rel="noopener">
                    <i class="fa fa-file-pdf"></i> Imprimir comprobante PDF
                </a>
                <a href="{{ route('cumplir_requisicion_compra') }}" class="btn btn-sm btn-outline-info ml-2">Ir al listado</a>
            </div>
        @endif
        @if (!empty($errorCarga))
            <div class="alert alert-warning">{{ $errorCarga }}</div>
        @endif

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Nuevo cumplimiento de requisici&oacute;n de compra</h3>
                <div class="card-tools">
                    <a href="{{ route('cumplir_requisicion_compra') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('grabar_cumplir_requisicion_compra') }}" method="POST" id="form-cumple-requisicion-compra" autocomplete="off">
                @csrf
                <input type="hidden" name="requisicion_id" id="requisicion_id" value="{{ old('requisicion_id', $requisicion->id ?? '') }}">
                <input type="hidden" id="empresa_id" value="{{ $requisicion->empresa_id ?? '' }}">

                <div class="card-body">
                    <div class="form-group row">
                        <label class="col-lg-2 col-form-label requerido">Requisici&oacute;n</label>
                        <div class="col-lg-6">
                            <div class="input-group">
                                <input type="text" class="form-control" id="requisicion_display" readonly
                                    value="{{ $requisicion ? ('#'.$requisicion->numerorequisicion.' — id '.$requisicion->id) : '' }}"
                                    placeholder="Use la lupa para buscar requisici&oacute;n aprobada">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-primary" id="btn-consulta-requisicion-cumple" title="Buscar requisici&oacute;n">
                                        <i class="fa fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="bloque-cabecera-requisicion" class="{{ $requisicion ? '' : 'd-none' }}">
                        <div class="row mb-3" id="fila-resumen-cabecera">
                            <div class="col-md-3">
                                <strong>Estado:</strong> <span id="cabecera-estado">{{ $requisicion->estado ?? '' }}</span>
                            </div>
                            <div class="col-md-3">
                                <strong>Fecha:</strong> <span id="cabecera-fecha">{{ $requisicion && $requisicion->fecha ? \Carbon\Carbon::parse($requisicion->fecha)->format('d/m/Y') : '' }}</span>
                            </div>
                            <div class="col-md-3">
                                <strong>Empresa:</strong> <span id="cabecera-empresa">{{ $requisicion->empresas->nombre ?? '' }}</span>
                            </div>
                            <div class="col-md-3">
                                <strong>Centro costo:</strong> <span id="cabecera-centrocosto">{{ $requisicion ? trim(($requisicion->centrocostos->codigo ?? '').' '.($requisicion->centrocostos->nombre ?? '')) : '' }}</span>
                            </div>
                        </div>

                        <div class="card card-outline card-secondary">
                            <div class="card-body py-2">
                                <p class="text-muted small mb-2">Dep&oacute;sitos de la transferencia que genera el cumplimiento:</p>
                                @include('stock.partials.campo_consulta_deposito', [
                                    'prefix' => 'origen',
                                    'label' => 'Depósito origen',
                                    'layout' => 'form_row',
                                    'inputName' => 'deposito_origen_id',
                                    'inputId' => 'deposito_origen_id',
                                    'depositoId' => old('deposito_origen_id', ''),
                                    'codigo' => old('deposito_origen_codigo', ''),
                                    'descripcion' => old('deposito_origen_descripcion', ''),
                                    'col_label' => 'col-lg-3 col-form-label',
                                    'col_input' => 'col-lg-6',
                                ])
                                @include('stock.partials.campo_consulta_deposito', [
                                    'prefix' => 'destino',
                                    'label' => 'Depósito destino',
                                    'layout' => 'form_row',
                                    'inputName' => 'deposito_destino_id',
                                    'inputId' => 'deposito_destino_id',
                                    'depositoId' => old('deposito_destino_id', ''),
                                    'codigo' => old('deposito_destino_codigo', ''),
                                    'descripcion' => old('deposito_destino_descripcion', ''),
                                    'col_label' => 'col-lg-3 col-form-label',
                                    'col_input' => 'col-lg-6',
                                ])
                            </div>
                        </div>

                        <div class="d-flex justify-content-end align-items-center mb-2" id="toolbar-lineas-cumple">
                            <button type="button" class="btn btn-sm btn-outline-success" id="btn-precargar-pendientes-cumple" title="Completa cada l&iacute;nea con su cantidad pendiente" {{ ($requisicion && $lineas->isNotEmpty()) ? '' : 'disabled' }}>
                                <i class="fa fa-check-double"></i> Precargar todo pendiente
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="tabla-lineas-cumple">
                                <thead style="background-color:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>Req.</th>
                                        <th>Art&iacute;culo</th>
                                        <th>Descripci&oacute;n</th>
                                        <th class="col-saldo-orig text-right" title="Saldo en dep&oacute;sito origen">Saldo orig.</th>
                                        <th class="text-right">Cant.</th>
                                        <th class="text-right">Entregada</th>
                                        <th class="text-right">Pend.</th>
                                        <th class="text-right" style="width:120px;">Cant. cumple</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-lineas-cumple">
                                    @if ($requisicion && $lineas->isNotEmpty())
                                        @foreach ($lineas as $idx => $linea)
                                            @php
                                                $entregada = (float) ($linea->cantidadentregada ?? 0);
                                                $pendiente = (float) $linea->cantidad - $entregada;
                                            @endphp
                                            <tr class="fila-cumple-linea" data-linea-id="{{ $linea->id }}" data-requisicion-id="{{ $requisicion->id }}" data-articulo-id="{{ $linea->articulo_id }}">
                                                <td>#{{ $requisicion->numerorequisicion }}</td>
                                                @include('compras.cumplir_requisicion_compra.partials.celda_articulo_linea', [
                                                    'linea' => $linea,
                                                    'idx' => $idx,
                                                    'puedeCambiarArticulo' => $puedeCambiarArticulo ?? false,
                                                ])
                                                <td class="descripcion-articulo-celda">{{ $linea->articulos->descripcion ?? $linea->detalle }}</td>
                                                @include('stock.movimientostock.partials.fila_saldo_origen')
                                                <td class="text-right">{{ number_format((float) $linea->cantidad, 2, '.', '') }}</td>
                                                <td class="text-right">{{ number_format($entregada, 2, '.', '') }}</td>
                                                <td class="text-right pendiente-cell">{{ number_format($pendiente, 2, '.', '') }}</td>
                                                <td>
                                                    <input type="hidden" name="lineas[{{ $idx }}][requisicion_articulo_id]" value="{{ $linea->id }}">
                                                    <input type="number" step="0.01" min="0" name="lineas[{{ $idx }}][cantidad_entrega]" class="form-control form-control-sm input-cantidad-entrega text-right" data-pendiente="{{ number_format($pendiente, 4, '.', '') }}">
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endif
                                </tbody>
                            </table>
                        </div>

                        <div class="form-group row mt-3">
                            <label for="leyenda" class="col-lg-2 col-form-label">Leyenda</label>
                            <div class="col-lg-8">
                                <textarea name="leyenda" id="leyenda" class="form-control" rows="3" placeholder="Leyendas ...">{{ old('leyenda') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-primary" id="btn-grabar-cumple" {{ $requisicion ? '' : 'disabled' }}>
                        <i class="fa fa-save"></i> Grabar cumplimiento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('compras.cumplir_requisicion_compra.partials.modales')
@include('includes.stock.modalconsultadeposito')
@if (!empty($puedeCambiarArticulo))
@include('includes.stock.modalconsultaarticulo')
@endif
@endsection

@section('scripts')
<script>
    window.cumpleRequisicionCompraConfig = {
        urlConsulta: @json(route('consulta_requisicion_compra_cumple')),
        urlDatos: @json(url('compras/cumplir-requisicion-compra/datos')),
        urlCrear: @json(route('crear_cumplir_requisicion_compra')),
        urlSaldoOrigen: @json(route('cumplir_requisicion_compra_saldo_articulo')),
        puedeCambiarArticulo: @json($puedeCambiarArticulo ?? false),
    };
</script>
<script src="{{ asset('assets/pages/scripts/stock/depmae/consulta.js') }}"></script>
@if (!empty($puedeCambiarArticulo))
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/compras/cumplir_requisicion_compra/form-articulo-cambio.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/cumplir_requisicion_compra/form-articulo-cambio.js')) ?: time() }}"></script>
@endif
<script src="{{ asset('assets/pages/scripts/compras/cumplir_requisicion_compra/form-saldo-origen.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/cumplir_requisicion_compra/form-saldo-origen.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/compras/cumplir_requisicion_compra/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/cumplir_requisicion_compra/form.js')) ?: time() }}"></script>
@endsection
