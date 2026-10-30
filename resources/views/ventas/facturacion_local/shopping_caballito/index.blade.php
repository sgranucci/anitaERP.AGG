@extends("theme.$theme.layout")

@section('titulo')
    Shopping Caballito
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')

        @if ($faltantes !== [] || $emails === [])
            <div class="alert alert-warning">
                <strong>Falta configuración para el envío de las {{ $hora }}.</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($faltantes as $falta)
                        <li>{{ $falta }}</li>
                    @endforeach
                    @if ($emails === [])
                        <li>Falta el correo destinatario.</li>
                    @endif
                </ul>
                <p class="mb-0 mt-2">
                    Se carga en
                    <a href="{{ route('facturacion_local_parametros') }}" class="text-primary">Parámetros Facturación Local</a>.
                    El número de local y el contrato los asigna el shopping; los del PDF de ejemplo no son los de Caballito.
                </p>
            </div>
        @elseif (! $habilitado)
            <div class="alert alert-warning mb-3">
                El envío automático está apagado. En Parámetros Facturación Local, el campo de habilitado tiene que ser 1.
            </div>
        @endif

        <div class="card card-info">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0"><i class="fa fa-file-text-o"></i> Envíos al shopping — Caballito</h3>
                <div class="card-tools ml-auto">
                    <a href="{{ route('facturacion_local_parametros') }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-cogs"></i> Parámetros
                    </a>
                </div>
            </div>
            <div class="card-body pb-2">
                <p class="text-muted small mb-3">
                    Todos los días a las {{ $hora }} se arma el archivo del día
                    (<code>TRANCOMP_DDMMAAAA.TXT</code>) con las ventas del local y se manda por correo.
                    Acá queda cada intento, con el archivo para descargarlo.
                </p>

                @if ($puedeEnviar)
                    <form method="POST" action="{{ route('facturacion_local_shopping_enviar') }}" class="form-horizontal mb-3">
                        @csrf
                        <div class="form-group row mb-2">
                            <label for="fecha" class="col-lg-2 control-label text-right pr-2">Día de ventas</label>
                            <div class="col-lg-3">
                                <input type="date" name="fecha" id="fecha" class="form-control" value="{{ old('fecha', $hoy) }}" required>
                            </div>
                            <div class="col-lg-4">
                                <div class="custom-control custom-checkbox mt-2">
                                    <input type="checkbox" class="custom-control-input" name="forzar" id="forzar" value="1">
                                    <label class="custom-control-label" for="forzar">Reenviar si ese día ya se mandó</label>
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-envelope"></i> Generar y enviar
                                </button>
                            </div>
                        </div>
                    </form>
                @endif

                <form method="GET" action="{{ route('facturacion_local_shopping_caballito') }}" class="form-horizontal">
                    <div class="form-group row">
                        <label for="fecha_desde" class="col-lg-2 control-label text-right pr-2">Desde</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control" value="{{ $filtros['fecha_desde'] ?? '' }}">
                        </div>
                        <label for="fecha_hasta" class="col-lg-2 control-label text-right pr-2">Hasta</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control" value="{{ $filtros['fecha_hasta'] ?? '' }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="estado" class="col-lg-2 control-label text-right pr-2">Estado</label>
                        <div class="col-lg-3">
                            <select name="estado" id="estado" class="form-control">
                                <option value="">Todos</option>
                                <option value="enviado" @selected(($filtros['estado'] ?? '') === 'enviado')>Enviado</option>
                                <option value="error" @selected(($filtros['estado'] ?? '') === 'error')>Error</option>
                            </select>
                        </div>
                        <div class="col-lg-3">
                            <button type="submit" class="btn btn-info">Filtrar</button>
                            <a href="{{ route('facturacion_local_shopping_caballito') }}" class="btn btn-outline-secondary">Limpiar</a>
                        </div>
                    </div>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover mb-0" id="tabla-paginada">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Cuándo</th>
                                <th>Día de ventas</th>
                                <th>Archivo</th>
                                <th class="text-right">Comprobantes</th>
                                <th class="text-right">Líneas</th>
                                <th>Estado</th>
                                <th>Destino</th>
                                <th>Detalle</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($envios as $envio)
                                <tr>
                                    <td class="text-nowrap">{{ $envio->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                                    <td class="text-nowrap">{{ $envio->fecha_venta?->format('d/m/Y') }}</td>
                                    <td class="text-nowrap">
                                        @if ($envio->ruta)
                                            <a href="{{ route('facturacion_local_shopping_descargar', ['id' => $envio->id]) }}" class="text-primary">
                                                {{ $envio->nombre_archivo }}
                                            </a>
                                        @else
                                            {{ $envio->nombre_archivo }}
                                        @endif
                                    </td>
                                    <td class="text-right">{{ $envio->comprobantes }}</td>
                                    <td class="text-right">{{ $envio->lineas }}</td>
                                    <td>
                                        @if ($envio->estado === 'enviado')
                                            <span class="badge badge-success">Enviado</span>
                                        @else
                                            <span class="badge badge-danger">Error</span>
                                        @endif
                                    </td>
                                    <td>{{ $envio->destinatarios }}</td>
                                    <td>{{ $envio->mensaje }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-3">Todavía no hay envíos.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($envios->total() > 0)
                <div class="card-footer d-flex flex-wrap justify-content-between align-items-center">
                    <span class="text-muted small">
                        {{ $envios->firstItem() }}–{{ $envios->lastItem() }} de {{ $envios->total() }}
                    </span>
                    {{ $envios->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
