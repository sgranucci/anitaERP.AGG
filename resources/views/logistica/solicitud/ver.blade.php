@extends("theme.$theme.layout")
@section('titulo')
    {{ $solicitud->numeroVisible() }}
@endsection
@section('scripts')
@if ($puedeGestionar && $solicitud->trabajo_tipo_id === null && in_array($solicitud->estado, ['enviada', 'aprobada'], true))
<script src="{{ asset('assets/pages/scripts/stock/depmae/consulta.js') }}"></script>
@endif
@endsection
@section('contenido')
<div class="row">
    <div class="col-lg-10">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">{{ $solicitud->numeroVisible() }} <span class="badge badge-light">{{ $solicitud->etiquetaEstado() }}</span></h3>
                <div class="card-tools">
                    <a href="{{ route('logistica_solicitud') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="card card-outline card-info">
                    <div class="card-header"><h3 class="card-title">Datos</h3></div>
                    <div class="card-body form-horizontal pb-2">
                        @include('logistica.solicitud.partials.dato', ['etiqueta' => 'Fecha', 'valor' => $solicitud->fecha?->format('d/m/Y')])
                        @include('logistica.solicitud.partials.dato', ['etiqueta' => 'Solicitante', 'valor' => $solicitud->usuario->nombre ?? ''])
                        @include('logistica.solicitud.partials.dato', ['etiqueta' => 'Centro de costo', 'valor' => trim(($solicitud->centrocosto->codigo ?? '').' '.($solicitud->centrocosto->nombre ?? ''))])
                        @include('logistica.solicitud.partials.dato', ['etiqueta' => 'Prioridad', 'valor' => $solicitud->prioridad])
                        @if ($solicitud->trabajoTipo)
                            @include('logistica.solicitud.partials.dato', ['etiqueta' => 'Trabajo', 'valor' => $solicitud->trabajoTipo->nombre])
                            @include('logistica.solicitud.partials.dato', ['etiqueta' => 'Responsable', 'valor' => $solicitud->responsable_snapshot ?: ($solicitud->trabajoTipo->responsable ?? '')])
                        @endif
                        @if ($solicitud->total_estimado > 0)
                            @include('logistica.solicitud.partials.dato', ['etiqueta' => 'Total estimado', 'valor' => '$ '.number_format($solicitud->total_estimado, 2, ',', '.')])
                        @endif
                        @if ($solicitud->ubicacionOrigen)
                            @include('logistica.solicitud.partials.dato', ['etiqueta' => 'Origen', 'valor' => $solicitud->ubicacionOrigen->nombre])
                        @endif
                        @if ($solicitud->ubicacionDestino)
                            @include('logistica.solicitud.partials.dato', ['etiqueta' => 'Destino', 'valor' => $solicitud->ubicacionDestino->nombre])
                        @endif
                        @if ($solicitud->cantidad)
                            @include('logistica.solicitud.partials.dato', ['etiqueta' => 'Cantidad', 'valor' => rtrim(rtrim(number_format((float) $solicitud->cantidad, 2, ',', '.'), '0'), ',')])
                        @endif
                        @if ($solicitud->fecha_tentativa)
                            @include('logistica.solicitud.partials.dato', ['etiqueta' => 'Fecha tentativa', 'valor' => $solicitud->fecha_tentativa->format('d/m/Y')])
                        @endif
                        @if ($solicitud->motivo)
                            @include('logistica.solicitud.partials.dato', ['etiqueta' => 'Motivo', 'valor' => $solicitud->motivo === 'destruccion' ? 'Destrucción' : 'Resguardo'])
                        @endif
                        @if ($solicitud->tipo_butaca)
                            @include('logistica.solicitud.partials.dato', ['etiqueta' => 'Butaca', 'valor' => ucfirst($solicitud->tipo_butaca)])
                        @endif
                        @if ($solicitud->uid_bien)
                            @include('logistica.solicitud.partials.dato', ['etiqueta' => 'UID', 'valor' => $solicitud->uid_bien])
                        @endif
                        @if ($solicitud->empresa)
                            @include('logistica.solicitud.partials.dato', ['etiqueta' => 'Empresa', 'valor' => $solicitud->empresa->nombre])
                        @endif
                        @if ($solicitud->ordencompra)
                            @include('logistica.solicitud.partials.dato', ['etiqueta' => 'Orden de compra', 'valor' => $solicitud->ordencompra->numeroordencompra.($solicitud->ordencompra->proveedores ? ' — '.$solicitud->ordencompra->proveedores->nombre : '')])
                        @endif
                        @if ($solicitud->direccion_retiro)
                            @include('logistica.solicitud.partials.dato', ['etiqueta' => 'Dirección de retiro', 'valor' => $solicitud->direccion_retiro])
                        @endif
                        @if ($solicitud->accesorios)
                            @include('logistica.solicitud.partials.dato', ['etiqueta' => 'Accesorios', 'valor' => $solicitud->accesorios_detalle])
                        @endif
                        @if ($solicitud->detalle)
                            @include('logistica.solicitud.partials.dato', ['etiqueta' => 'Detalle', 'valor' => $solicitud->detalle])
                        @endif
                        @if ($solicitud->modo_cumplimiento)
                            @php
                                $textoModo = match ($solicitud->modo_cumplimiento) {
                                    'deposito' => 'Salida de depósito '.($solicitud->deposito->nombre ?? ''),
                                    'transferencia' => 'Transferencia '.($solicitud->deposito->nombre ?? '').' → '.($solicitud->depositoDestino->nombre ?? ''),
                                    default => 'Compra',
                                };
                                $linkModo = match ($solicitud->modo_cumplimiento) {
                                    'deposito' => route('crear_movimientostock'),
                                    'transferencia' => route('transferencia_mercaderia'),
                                    default => route('crear_requisicion'),
                                };
                                $textoLink = match ($solicitud->modo_cumplimiento) {
                                    'deposito' => 'Cargar el movimiento de stock',
                                    'transferencia' => 'Cargar la transferencia',
                                    default => 'Cargar la requisición',
                                };
                            @endphp
                            @include('logistica.solicitud.partials.dato', ['etiqueta' => 'Cumplimiento', 'valor' => $textoModo])
                            <div class="form-group row mb-2">
                                <label class="col-lg-4 control-label text-right pr-2"></label>
                                <div class="col-lg-8">
                                    <a class="text-primary" target="_blank" rel="noopener" href="{{ $linkModo }}">{{ $textoLink }}</a>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                @if ($solicitud->items->isNotEmpty())
                    <div class="card card-outline card-info">
                        <div class="card-header"><h3 class="card-title">Ítems</h3></div>
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-sm table-striped table-bordered table-hover mb-0">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr><th>Artículo</th><th class="text-right">Cantidad</th><th class="text-right">Precio estimado</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($solicitud->items as $item)
                                        <tr>
                                            <td>{{ $item->articulo->sku ?? '' }} {{ $item->articulo->descripcion ?? '' }}</td>
                                            <td class="text-right">{{ rtrim(rtrim(number_format((float) $item->cantidad, 2, ',', '.'), '0'), ',') }}</td>
                                            <td class="text-right">$ {{ number_format((float) $item->precio_estimado, 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                @if ($solicitud->archivos->isNotEmpty())
                    <div class="card card-outline card-info">
                        <div class="card-header"><h3 class="card-title">Adjuntos</h3></div>
                        <div class="card-body p-0">
                            <table class="table table-sm table-striped table-bordered mb-0">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr><th>Archivo</th><th style="width:6rem;"></th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($solicitud->archivos as $archivo)
                                        <tr>
                                            <td>{{ $archivo->nombre }}</td>
                                            <td>
                                                <a class="text-primary" href="{{ route('descargar_archivo_logistica_solicitud', [$solicitud->id, $archivo->id]) }}">Descargar</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                @if ($puedeGestionar && ! in_array($solicitud->estado, ['rechazada', 'entregada', 'cerrada'], true))
                    <form method="POST" action="{{ route('gestionar_logistica_solicitud', $solicitud->id) }}" class="form-horizontal mt-2">
                        @csrf
                        @if ($solicitud->trabajo_tipo_id === null && in_array($solicitud->estado, ['enviada', 'aprobada'], true))
                            <div class="card card-outline card-info">
                                <div class="card-header"><strong>Cómo se cumple</strong></div>
                                <div class="card-body">
                                    <div class="form-group row">
                                        <label class="col-lg-4 control-label text-right pr-2">Modo</label>
                                        <div class="col-lg-4">
                                            <select name="modo_cumplimiento" class="form-control">
                                                <option value="deposito">Salida de depósito</option>
                                                <option value="transferencia">Transferencia</option>
                                                <option value="compra">Compra</option>
                                            </select>
                                        </div>
                                    </div>
                                    @include('stock.partials.campo_consulta_deposito', [
                                        'prefix' => 'sollogsalida',
                                        'layout' => 'form_row',
                                        'label' => 'Depósito de salida',
                                        'inputName' => 'deposito_id',
                                        'inputId' => 'sollog_deposito_id',
                                        'required' => false,
                                        'col_label' => 'col-lg-4 control-label text-right pr-2',
                                        'col_input' => 'col-lg-8',
                                    ])
                                    @include('stock.partials.campo_consulta_deposito', [
                                        'prefix' => 'sollogdestino',
                                        'layout' => 'form_row',
                                        'label' => 'Depósito destino',
                                        'inputName' => 'deposito_destino_id',
                                        'inputId' => 'sollog_deposito_destino_id',
                                        'required' => false,
                                        'col_label' => 'col-lg-4 control-label text-right pr-2',
                                        'col_input' => 'col-lg-8',
                                    ])
                                    <p class="text-muted mb-0">Al pasar a preparación queda registrado el modo. El movimiento, la transferencia o la requisición se cargan en su pantalla.</p>
                                </div>
                            </div>
                        @endif
                        <div class="mt-3">
                            @if ($solicitud->estado === 'pendiente_aprobacion')
                                <button class="btn btn-success" type="submit" name="accion" value="aprobar">Aprobar</button>
                                <button class="btn btn-outline-danger" type="submit" name="accion" value="rechazar">Rechazar</button>
                            @endif
                            @if (in_array($solicitud->estado, ['enviada', 'aprobada'], true))
                                <button class="btn btn-primary" type="submit" name="accion" value="preparar">Pasar a preparación</button>
                                <button class="btn btn-outline-danger" type="submit" name="accion" value="rechazar">Rechazar</button>
                            @endif
                            @if ($solicitud->estado === 'en_preparacion')
                                <button class="btn btn-success" type="submit" name="accion" value="entregar">
                                    {{ $solicitud->trabajo_tipo_id ? 'Cerrar' : 'Marcar entregada' }}
                                </button>
                            @endif
                        </div>
                    </form>
                    @if ($solicitud->trabajo_tipo_id === null && in_array($solicitud->estado, ['enviada', 'aprobada'], true))
                        @include('includes.stock.modalconsultadeposito')
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
