@extends("theme.$theme.layout")

@section('titulo')
    Envío transferencias Interbanking
@endsection

@section('contenido')
@php
    $colLabel = 'col-lg-3 control-label text-right pr-2';
    $colInput = 'col-lg-3';
    $empresasDisponibles = collect($empresa_query ?? []);
    $filas = $preview['filas'] ?? [];
    $omitidas = $preview['omitidas'] ?? [];
    $fuente = $fuente ?? 'proveedores';
@endphp

<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if (session('mensaje_error'))
            <div class="alert alert-danger">{{ session('mensaje_error') }}</div>
        @endif

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Envío on-line de transferencias (Interbanking)</h3>
                <div class="card-tools">
                    <a href="{{ route('interbanking_envio_transferencias') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                    <a href="{{ route('interbanking_archivo_pago') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-file-alt"></i> Archivo ASCII
                    </a>
                </div>
            </div>

            <div class="card-body pb-2">
                <p class="text-muted small mb-3">
                    Preview de transferencias a CBU, selección individual o global, y envío por API.
                    Anti-duplicado: un pago / operación ya enviado con éxito no se vuelve a mandar.
                    Solo ERP (OP + I/E) y CSV de retiro juego (destino Interbanking).
                </p>

                @include('includes.tabs-activas-estilos')
                <div class="tabs-activas mb-3">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link {{ $fuente === 'proveedores' ? 'active' : '' }}"
                               href="{{ route('interbanking_envio_transferencias', ['fuente' => 'proveedores']) }}">
                                Proveedores / I-E
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $fuente === 'solicitud_retiro' ? 'active' : '' }}"
                               href="{{ route('interbanking_envio_transferencias', ['fuente' => 'solicitud_retiro']) }}">
                                Juego online (CSV retiro)
                            </a>
                        </li>
                    </ul>
                </div>

                @if ($fuente === 'proveedores')
                    <form method="get" action="{{ route('interbanking_envio_transferencias') }}" id="form-ib-envio-prov" class="mb-0">
                        <input type="hidden" name="consultar" value="1">
                        <input type="hidden" name="fuente" value="proveedores">
                        <div class="form-group row">
                            <label for="empresa_id" class="{{ $colLabel }} requerido">Empresa</label>
                            <div class="{{ $colInput }}">
                                @include('includes.form-empresa-asignada-control', [
                                    'empresa_query' => $empresa_query,
                                    'empresa_id' => $filtros['empresa_id'] ?? null,
                                    'required' => true,
                                    'permite_vacio' => $empresasDisponibles->count() > 1,
                                    'opcion_vacia' => 'Seleccione…',
                                ])
                            </div>
                            <label class="{{ $colLabel }} requerido">Cuenta de caja</label>
                            <div class="{{ $colInput }}">
                                <input type="hidden" name="cuentacaja_id" id="cuentacaja_id"
                                       value="{{ (int) ($filtros['cuentacaja_id'] ?? 0) ?: '' }}">
                                <input type="hidden" name="cbu_origen" id="cbu_origen"
                                       value="{{ $filtros['cbu_origen'] ?? '' }}">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="codigo_cuentacaja" autocomplete="off"
                                           value="{{ $cuenta_origen->codigo ?? '' }}" placeholder="Código">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary consultacuentacaja" title="F1">
                                            <i class="fa fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <input type="text" class="form-control form-control-sm mt-1" id="nombre_cuentacaja" readonly
                                       value="{{ $cuenta_origen->nombre ?? '' }}">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="{{ $colLabel }} requerido">Fecha desde</label>
                            <div class="{{ $colInput }}">
                                <input type="date" name="fecha_desde" class="form-control"
                                       value="{{ $filtros['fecha_desde'] ?? '' }}" required>
                            </div>
                            <label class="{{ $colLabel }} requerido">Fecha hasta</label>
                            <div class="{{ $colInput }}">
                                <input type="date" name="fecha_hasta" class="form-control"
                                       value="{{ $filtros['fecha_hasta'] ?? '' }}" required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="{{ $colLabel }}">Tipo OP</label>
                            <div class="{{ $colInput }}">
                                <select name="tipo_op" class="form-control">
                                    @foreach (['OPP' => 'OPP', 'OPA' => 'OPA', '0' => 'Todas OP*'] as $val => $lab)
                                        <option value="{{ $val }}" @selected(($filtros['tipo_op'] ?? 'OPP') == $val)>{{ $lab }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="{{ $colInput }} offset-lg-3">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fa fa-search"></i> Consultar preview
                                </button>
                            </div>
                        </div>
                    </form>
                @else
                    <form method="post" action="{{ route('interbanking_envio_transferencias_preview_retiro') }}"
                          enctype="multipart/form-data" id="form-ib-envio-retiro" class="mb-0">
                        @csrf
                        <input type="hidden" name="fuente" value="solicitud_retiro">
                        <div class="form-group row">
                            <label for="empresa_id" class="{{ $colLabel }} requerido">Empresa</label>
                            <div class="{{ $colInput }}">
                                @include('includes.form-empresa-asignada-control', [
                                    'empresa_query' => $empresa_query,
                                    'empresa_id' => $filtros['empresa_id'] ?? null,
                                    'required' => true,
                                    'permite_vacio' => $empresasDisponibles->count() > 1,
                                    'opcion_vacia' => 'Seleccione…',
                                ])
                            </div>
                            <label class="{{ $colLabel }} requerido">Cuenta de caja</label>
                            <div class="{{ $colInput }}">
                                <input type="hidden" name="cuentacaja_id" id="cuentacaja_id"
                                       value="{{ (int) ($filtros['cuentacaja_id'] ?? 0) ?: '' }}">
                                <input type="hidden" name="cbu_origen" id="cbu_origen"
                                       value="{{ $filtros['cbu_origen'] ?? '' }}">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="codigo_cuentacaja" autocomplete="off"
                                           value="{{ $cuenta_origen->codigo ?? '' }}" placeholder="Código">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary consultacuentacaja" title="F1">
                                            <i class="fa fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <input type="text" class="form-control form-control-sm mt-1" id="nombre_cuentacaja" readonly
                                       value="{{ $cuenta_origen->nombre ?? '' }}">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="{{ $colLabel }} requerido">Archivo CSV</label>
                            <div class="col-lg-6">
                                <input type="file" name="archivo_csv" class="form-control" accept=".csv,text/csv,text/plain" required>
                                <small class="text-muted">Mismo CSV de solicitud de retiro. Solo filas destino Interbanking (no CVU/Francés).</small>
                            </div>
                            <div class="col-lg-3">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fa fa-upload"></i> Cargar preview
                                </button>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
        </div>

        @if (! empty($consultado))
            @if (empty($preview['ok']))
                <div class="alert alert-warning">{{ $preview['mensaje'] ?? 'Sin datos.' }}</div>
            @else
                <div class="card card-outline card-info">
                    <div class="card-header d-flex flex-wrap align-items-center">
                        <strong class="mr-3">
                            {{ (int) ($preview['cantidad'] ?? 0) }} fila(s)
                            · seleccionables {{ (int) ($preview['seleccionables'] ?? 0) }}
                            · total pendiente $ {{ number_format((float) ($preview['total_importe'] ?? 0), 2, ',', '.') }}
                        </strong>
                        <div class="ml-auto">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="ib-envio-sel-todas">Seleccionar todas</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="ib-envio-sel-ninguna">Ninguna</button>
                            <button type="button" class="btn btn-success btn-sm" id="ib-envio-enviar" form="form-ib-envio-enviar">
                                <i class="fa fa-paper-plane"></i> Enviar seleccionadas
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <form method="post" action="{{ route('interbanking_envio_transferencias_enviar') }}" id="form-ib-envio-enviar">
                            @csrf
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0" id="tabla-ib-envio">
                                    <thead style="background:#85C1E9;color:#17202A;">
                                        <tr>
                                            <th style="width:36px;">
                                                <input type="checkbox" id="ib-envio-check-all" title="Seleccionar todas pendientes">
                                            </th>
                                            <th>Origen</th>
                                            <th>Referencia</th>
                                            <th>Beneficiario</th>
                                            <th>CBU</th>
                                            <th class="text-right">Importe</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($filas as $fila)
                                            @php
                                                $ya = ! empty($fila['ya_enviado']);
                                                $key = (string) ($fila['selection_key'] ?? '');
                                            @endphp
                                            <tr class="{{ $ya ? 'table-secondary' : '' }}">
                                                <td>
                                                    @if (! $ya && $key !== '')
                                                        <input type="checkbox" class="ib-envio-check" name="selection_keys[]"
                                                               value="{{ $key }}" data-importe="{{ (float) ($fila['importe'] ?? 0) }}">
                                                    @endif
                                                </td>
                                                <td>{{ $fila['origen'] ?? '' }}</td>
                                                <td>{{ $fila['referencia'] ?? '' }}</td>
                                                <td>{{ $fila['beneficiario'] ?? $fila['proveedor_nombre'] ?? '' }}</td>
                                                <td class="text-monospace">{{ $fila['cbu'] ?? '' }}</td>
                                                <td class="text-right">{{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
                                                <td>
                                                    @if ($ya)
                                                        <span class="badge badge-success">Ya enviado</span>
                                                    @else
                                                        <span class="badge badge-info">Pendiente</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted">Sin filas para enviar.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer small">
                        Seleccionadas: <strong id="ib-envio-count">0</strong>
                        · Importe: <strong id="ib-envio-importe">0,00</strong>
                    </div>
                </div>

                @if (! empty($omitidas))
                    <div class="alert alert-secondary mt-2 small">
                        Omitidas en preview: {{ count($omitidas) }}
                    </div>
                @endif
            @endif
        @endif
    </div>
</div>

@include('includes.caja.modalconsultacuentacaja')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'ib-envio-overlay',
    'tituloId' => 'ib-envio-overlay-titulo',
    'subtituloId' => 'ib-envio-overlay-sub',
    'titulo' => 'Enviando transferencias…',
    'subtitulo' => 'Puede demorar según la cantidad. No cierre la página.',
])
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/caja/interbanking/archivo_pago.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/interbanking/archivo_pago.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/caja/interbanking/envio_transferencias.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/interbanking/envio_transferencias.js')) }}"></script>
@endsection
