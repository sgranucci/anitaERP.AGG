@extends("theme.$theme.layout")
@section('titulo')
    Cumplimiento #{{ $cumplimiento->numero }}
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    Cumplimiento N&ordm; {{ $cumplimiento->numero }}
                    @if ($cumplimiento->estaActivo())
                        <span class="badge badge-success ml-2">ACTIVO</span>
                    @else
                        <span class="badge badge-secondary ml-2">REVERTIDO</span>
                    @endif
                </h3>
                <div class="card-tools">
                    <a href="{{ route('imprimir_pdf_cumplir_requisicion_sala', ['id' => $cumplimiento->id]) }}" class="btn btn-outline-danger btn-sm" target="_blank" rel="noopener">
                        <i class="fa fa-file-pdf-o"></i> Imprimir PDF
                    </a>
                    <a href="{{ route('cumplir_requisicion_sala') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3"><strong>Fecha:</strong> {{ optional($cumplimiento->fecha)->format('d/m/Y H:i') }}</div>
                    <div class="col-md-3"><strong>Usuario:</strong> {{ $cumplimiento->usuario?->nombre ?? '' }}</div>
                    <div class="col-md-3"><strong>Empresa:</strong> {{ $cumplimiento->empresa?->nombre ?? '—' }}</div>
                    <div class="col-md-3"><strong>ID:</strong> {{ $cumplimiento->id }}</div>
                </div>

                @if (! $cumplimiento->estaActivo())
                <div class="alert alert-warning">
                    <strong>Revertido</strong>
                    el {{ optional($cumplimiento->revertido_en)->format('d/m/Y H:i') ?? '—' }}
                    por {{ $cumplimiento->revertidoPor?->nombre ?? '—' }}.
                    @if ($cumplimiento->observacion_reversion)
                        <br><span class="small">{{ $cumplimiento->observacion_reversion }}</span>
                    @endif
                </div>
                @endif

                <p><strong>Requisiciones involucradas:</strong>
                    @foreach ($requisiciones as $req)
                        <a href="{{ route('editar_requisicion_sala', ['id' => $req->id]) }}" class="text-primary" target="_blank" rel="noopener">#{{ $req->numerorequisicion }}</a>@if (!$loop->last), @endif
                    @endforeach
                </p>

                @if ($cumplimiento->estaActivo())
                <form action="{{ route('actualizar_cumplir_requisicion_sala', ['id' => $cumplimiento->id]) }}" method="POST" class="mb-4">
                    @csrf
                    @method('PUT')
                    <div class="form-group">
                        <label for="leyenda">Leyenda / observaciones del comprobante</label>
                        <textarea name="leyenda" id="leyenda" class="form-control" rows="3" maxlength="2000">{{ old('leyenda', $cumplimiento->leyenda) }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Guardar leyenda</button>
                </form>
                @elseif (filled($cumplimiento->leyenda))
                <p><strong>Leyenda:</strong></p>
                <p class="text-muted" style="white-space: pre-wrap;">{{ $cumplimiento->leyenda }}</p>
                @endif

                <h5 class="mt-3">L&iacute;neas del cumplimiento</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>Req.</th>
                                <th>Art&iacute;culo</th>
                                <th class="text-right">Entrega</th>
                                <th>UID</th>
                                <th>NPU</th>
                                <th>Estado l&iacute;nea</th>
                                <th>Motivo parcial</th>
                                <th>Remito</th>
                                <th>Responsable</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($cumplimiento->articulos as $linea)
                            <tr>
                                <td>#{{ $linea->requisicionSala?->numerorequisicion ?? $linea->requisicion_sala_id }}</td>
                                <td>{{ $linea->articulo?->sku ?? '' }} — {{ $linea->articulo?->descripcion ?? '' }}</td>
                                <td class="text-right">{{ number_format((float) $linea->cantidad_entrega, 2, ',', '.') }}</td>
                                <td>{{ $linea->uid }}</td>
                                <td>{{ $linea->numeroparte }}</td>
                                <td>{{ \App\Models\Sala\RequisicionSalaArticulo::estadoLineaNombrePorValor((string) ($linea->estado_linea ?? '')) }}</td>
                                <td>{{ \App\Models\Sala\RequisicionSalaArticulo::estadoParcialNombrePorValor((string) ($linea->estadoparcial ?? '')) }}</td>
                                <td>{{ $linea->numeroremito }}</td>
                                <td>{{ $linea->nombreresponsable }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($cumplimiento->transferencias->isNotEmpty())
                <h5 class="mt-3">Transferencias de stock</h5>
                <ul class="list-unstyled mb-0">
                    @foreach ($cumplimiento->transferencias as $pivot)
                        @php $tm = $pivot->transferenciaMercaderia; @endphp
                        @if ($tm)
                        <li class="mb-1">
                            <strong>{{ $tm->codigo }}</strong> (id {{ $tm->id }})
                            — {{ $tm->depositoOrigen?->codigo }} &rarr; {{ $tm->depositoDestino?->codigo }}
                            @if (can('listar-movimientos-de-stock', false))
                            <a href="{{ route('transferencia_movimientostock_com_pdf', ['id' => $tm->id, 'inline' => 1]) }}" class="text-primary ml-1" target="_blank" rel="noopener">PDF TM</a>
                            @endif
                        </li>
                        @endif
                    @endforeach
                </ul>
                @endif

                @if ($cumplimiento->estaActivo())
                <hr>
                <h5 class="text-danger">Revertir cumplimiento</h5>
                <p class="small text-muted">Anula el cumplimiento, revierte las transferencias confirmadas y restaura las l&iacute;neas de la requisici&oacute;n al estado previo a este evento.</p>
                <form action="{{ route('revertir_cumplir_requisicion_sala', ['id' => $cumplimiento->id]) }}" method="POST"
                    onsubmit="return confirm('¿Confirma revertir este cumplimiento? Se revertirán transferencias y entregas.');">
                    @csrf
                    <div class="form-group">
                        <label for="observacion_reversion">Motivo de reversi&oacute;n</label>
                        <input type="text" name="observacion_reversion" id="observacion_reversion" class="form-control" maxlength="500" required>
                    </div>
                    <button type="submit" class="btn btn-danger btn-sm"><i class="fa fa-undo"></i> Revertir cumplimiento</button>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
