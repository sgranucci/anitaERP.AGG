@extends("theme.$theme.layout")
@section('titulo')
    Cumplimiento requisici&oacute;n de compra N&ordm; {{ $cumplimiento->numero }}
@endsection

@section('contenido')
@php
    $activo = $cumplimiento->estado === \App\Models\Compras\CumplimientoRequisicionCompra::ESTADO_ACTIVO;
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    Cumplimiento N&ordm; {{ $cumplimiento->numero }}
                    @if ($activo)
                        <span class="badge badge-success">ACTIVO</span>
                    @else
                        <span class="badge badge-secondary">REVERTIDO</span>
                    @endif
                </h3>
                <div class="card-tools">
                    <a href="{{ route('imprimir_pdf_cumplir_requisicion_compra', ['id' => $cumplimiento->id]) }}" class="btn btn-outline-danger btn-sm" target="_blank" rel="noopener">
                        <i class="fa fa-file-pdf"></i> Imprimir PDF
                    </a>
                    <a href="{{ route('cumplir_requisicion_compra') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3"><strong>Fecha:</strong> {{ optional($cumplimiento->fecha)->format('d/m/Y H:i') }}</div>
                    <div class="col-md-3"><strong>Usuario:</strong> {{ $cumplimiento->usuario?->nombre ?? '' }}</div>
                    <div class="col-md-3"><strong>Empresa:</strong> {{ $cumplimiento->empresa?->nombre ?? '—' }}</div>
                    <div class="col-md-3"><strong>Requisiciones:</strong>
                        @foreach ($requisiciones as $req)
                            <span class="badge badge-light">#{{ $req->numerorequisicion }}</span>
                        @endforeach
                    </div>
                </div>

                @if (! $activo)
                    <div class="alert alert-warning">
                        Revertido por {{ $cumplimiento->revertidoPor?->nombre ?? '—' }}
                        el {{ optional($cumplimiento->revertido_en)->format('d/m/Y H:i') }}.
                        @if ($cumplimiento->observacion_reversion)
                            <br><em>{{ $cumplimiento->observacion_reversion }}</em>
                        @endif
                    </div>
                @endif

                <h5>Transferencias generadas</h5>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered">
                        <thead style="background-color:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Transferencia</th>
                                <th>Origen</th>
                                <th>Destino</th>
                                <th class="text-right">Acci&oacute;n</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($cumplimiento->transferencias as $pivot)
                                @php $tm = $pivot->transferenciaMercaderia; @endphp
                                @if ($tm)
                                    <tr>
                                        <td>{{ $tm->codigo ?: ('#'.$tm->id) }}</td>
                                        <td>{{ $tm->depositoOrigen?->codigo }} {{ $tm->depositoOrigen?->nombre }}</td>
                                        <td>{{ $tm->depositoDestino?->codigo }} {{ $tm->depositoDestino?->nombre }}</td>
                                        <td class="text-right">
                                            @if (can('listar-transferencia-mercaderia', false))
                                                <a href="{{ url('stock/transferencia-mercaderia/'.$tm->id.'/editar') }}" class="text-primary" target="_blank" rel="noopener">Ver</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">Sin transferencias asociadas</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @include('compras.requisicion.partials.cambios_articulo_historia', ['cambios_articulo' => $cambiosArticulo ?? collect()])

                <h5>L&iacute;neas cumplidas</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped">
                        <thead style="background-color:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Req.</th>
                                <th>Art&iacute;culo</th>
                                <th>Descripci&oacute;n</th>
                                <th>Cambio art.</th>
                                <th class="text-right">Entregada</th>
                                <th>Dep. origen</th>
                                <th>Dep. destino</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($cumplimiento->articulos as $linea)
                                <tr>
                                    <td>#{{ $linea->requisicion?->numerorequisicion ?? $linea->requisicion_id }}</td>
                                    <td>{{ $linea->articulo?->sku }}</td>
                                    <td>{{ $linea->articulo?->descripcion ?? $linea->detalle }}</td>
                                    <td>
                                        @if ((int) ($linea->articulo_id_original ?? 0) > 0 && (int) $linea->articulo_id_original !== (int) $linea->articulo_id)
                                            <span class="badge badge-warning" title="Art&iacute;culo anterior">
                                                {{ $linea->articuloOriginal?->sku ?? $linea->articulo_id_original }}
                                                &rarr; {{ $linea->articulo?->sku }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-right">{{ number_format((float) $linea->cantidad_entrega, 2, '.', '') }}</td>
                                    <td>{{ $linea->depositoOrigen?->codigo }} {{ $linea->depositoOrigen?->nombre }}</td>
                                    <td>{{ $linea->depositoDestino?->codigo }} {{ $linea->depositoDestino?->nombre }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($activo)
                    <hr>
                    <form action="{{ route('actualizar_cumplir_requisicion_compra', ['id' => $cumplimiento->id]) }}" method="POST" class="mb-3">
                        @csrf
                        @method('PUT')
                        <div class="form-group row">
                            <label for="leyenda" class="col-lg-2 col-form-label">Leyenda</label>
                            <div class="col-lg-8">
                                <textarea name="leyenda" id="leyenda" class="form-control" rows="2">{{ $cumplimiento->leyenda }}</textarea>
                            </div>
                            <div class="col-lg-2">
                                <button type="submit" class="btn btn-outline-primary btn-block"><i class="fa fa-save"></i> Guardar leyenda</button>
                            </div>
                        </div>
                    </form>

                    <form action="{{ route('revertir_cumplir_requisicion_compra', ['id' => $cumplimiento->id]) }}" method="POST"
                          onsubmit="return confirm('¿Revertir el cumplimiento? Se revertirán las transferencias confirmadas y se restaurará el pendiente de la requisición.');">
                        @csrf
                        <div class="form-group row">
                            <label for="observacion_reversion" class="col-lg-2 col-form-label">Motivo reversi&oacute;n</label>
                            <div class="col-lg-8">
                                <input type="text" name="observacion_reversion" id="observacion_reversion" class="form-control" maxlength="255" placeholder="Motivo (opcional)">
                            </div>
                            <div class="col-lg-2">
                                <button type="submit" class="btn btn-danger btn-block"><i class="fa fa-undo"></i> Revertir</button>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
