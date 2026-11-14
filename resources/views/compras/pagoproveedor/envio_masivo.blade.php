@extends("theme.$theme.layout")

@section('titulo')
    Envío de OP a proveedores
@endsection

@section('scripts')
<script>
(function () {
    function checks() {
        return document.querySelectorAll('.op-envio-check');
    }

    function conteo() {
        var lista = checks();
        var n = document.querySelectorAll('.op-envio-check:checked').length;
        var el = document.getElementById('op-envio-conteo');
        if (el) {
            el.textContent = String(n);
        }
        var todas = document.getElementById('op-envio-todas');
        if (todas) {
            todas.checked = lista.length > 0 && n === lista.length;
            todas.indeterminate = n > 0 && n < lista.length;
        }
    }

    function aplicarDefaultPendientes() {
        // Al cargar: tildar solo pendientes (data-ya-enviada=0). El tilde global opera sobre todas.
        checks().forEach(function (el) {
            el.checked = el.getAttribute('data-ya-enviada') === '0';
        });
        conteo();
    }

    var todas = document.getElementById('op-envio-todas');
    if (todas) {
        todas.addEventListener('change', function () {
            var marcar = todas.checked;
            checks().forEach(function (el) {
                el.checked = marcar;
            });
            conteo();
        });
    }
    checks().forEach(function (el) {
        el.addEventListener('change', conteo);
    });

    var form = document.getElementById('form-envio-op-masivo');
    var overlay = document.getElementById('pagoproveedor-envio-masivo-overlay');
    function mostrarOverlay() {
        if (!overlay) {
            return;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.style.pointerEvents = 'all';
        overlay.setAttribute('aria-hidden', 'false');
    }
    function ocultarOverlay() {
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = 'none';
        overlay.style.pointerEvents = 'none';
        overlay.setAttribute('aria-hidden', 'true');
    }
    ocultarOverlay();
    if (form) {
        form.addEventListener('submit', function (e) {
            if (document.querySelectorAll('.op-envio-check:checked').length === 0) {
                e.preventDefault();
                window.alert('Seleccione al menos una orden de pago.');
                return;
            }
            mostrarOverlay();
        });
    }
    window.addEventListener('pageshow', ocultarOverlay);
    aplicarDefaultPendientes();
})();
</script>
@endsection

@section('contenido')
@php
    $filas = $filas ?? [];
    $filtros = $filtros ?? [];
    $resultado = session('resultado_envio', []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if (!empty($error))
            <div class="alert alert-danger">{{ $error }}</div>
        @endif

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Envío masivo de órdenes de pago</h3>
            </div>
            <form method="get" action="{{ route('pagoproveedor_envio_masivo') }}" class="mb-0">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Arma los correos de las OP de <strong>transferencia</strong> o <strong>cheque</strong> de la fecha elegida.
                        En transferencia busca el comprobante en las transferencias persistidas de Interbanking
                        (mismo importe y CBU o CUIT del proveedor), aunque la OP no tenga el id cargado.
                        Por defecto se tildan las <strong>pendientes con email</strong> (aún sin envío).
                        Las ya enviadas van destildadas (se pueden marcar a mano para reenviar).
                        Sin email en el proveedor no se pueden tildar: hay que cargar el mail en el ABM del proveedor.
                        El tilde de cabecera marca / desmarca <strong>todas</strong> las que tienen email.
                        El envío corre en segundo plano (como CAEA) y el resumen llega por mail.
                    </p>
                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $filtros['empresa_id'] ?? '',
                        'label' => 'Empresa',
                        'col_label' => 'col-lg-2 control-label text-right',
                        'col_input' => 'col-lg-4',
                        'required' => true,
                    ])
                    <div class="form-group row">
                        <label for="fecha_desde" class="col-lg-2 control-label text-right requerido">Fecha OP desde</label>
                        <div class="col-lg-2">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control" required
                                   value="{{ $filtros['fecha_desde'] ?? '' }}">
                        </div>
                        <label for="fecha_hasta" class="col-lg-2 control-label text-right requerido">Fecha OP hasta</label>
                        <div class="col-lg-2">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control" required
                                   value="{{ $filtros['fecha_hasta'] ?? '' }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="medio" class="col-lg-2 control-label text-right">Medio</label>
                        <div class="col-lg-4">
                            <select name="medio" id="medio" class="form-control">
                                <option value="ambas" @selected(($filtros['medio'] ?? 'ambas') === 'ambas')>Transferencia y cheque</option>
                                <option value="transferencia" @selected(($filtros['medio'] ?? '') === 'transferencia')>Solo transferencia</option>
                                <option value="cheque" @selected(($filtros['medio'] ?? '') === 'cheque')>Solo cheque</option>
                            </select>
                        </div>
                        <div class="col-lg-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        @if (!empty($resultado))
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Resultado del envío</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Orden de pago</th>
                                <th>Resultado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($resultado as $item)
                                <tr>
                                    <td>{{ $item['etiqueta'] ?? '' }}</td>
                                    <td>
                                        @if (!empty($item['ok']))
                                            <span class="badge badge-success">Enviada</span>
                                        @else
                                            <span class="badge badge-danger">No enviada</span>
                                        @endif
                                        {{ $item['mensaje'] ?? '' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if (!empty($consultado))
            @include('includes.proceso_overlay_aviso', [
                'overlayId' => 'pagoproveedor-envio-masivo-overlay',
                'tituloId' => 'pagoproveedor-envio-masivo-overlay-titulo',
                'subtituloId' => 'pagoproveedor-envio-masivo-overlay-subtitulo',
                'titulo' => 'Encolando envío de OP…',
                'subtitulo' => 'El proceso corre en segundo plano. Al terminar recibirás un mail.',
            ])
            <form method="post" action="{{ route('pagoproveedor_envio_masivo_enviar') }}" id="form-envio-op-masivo"
                  data-mensaje-grabacion="Encolando envío a proveedores…">
                @csrf
                <input type="hidden" name="empresa_id" value="{{ (int) ($filtros['empresa_id'] ?? 0) }}">
                <input type="hidden" name="fecha_desde" value="{{ $filtros['fecha_desde'] ?? '' }}">
                <input type="hidden" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] ?? '' }}">
                <input type="hidden" name="medio" value="{{ $filtros['medio'] ?? 'ambas' }}">

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0">{{ count($filas) }} orden(es) de pago</h3>
                        <span class="small text-muted">Seleccionadas: <strong id="op-envio-conteo">0</strong></span>
                    </div>
                    <div class="card-body p-0">
                        @if ($filas === [])
                            <p class="text-muted p-3 mb-0">No hay OP de transferencia ni de cheque para esa empresa y fecha.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm table-hover table-bordered mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width:36px">
                                                <input type="checkbox" id="op-envio-todas" title="Marcar / desmarcar todas las que tienen email">
                                            </th>
                                            <th>OP</th>
                                            <th>Fecha</th>
                                            <th>Proveedor</th>
                                            <th>Email</th>
                                            <th>Medio</th>
                                            <th class="text-right">Neto</th>
                                            <th>Transferencia</th>
                                            <th>Asociación</th>
                                            <th>Mail</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($filas as $fila)
                                            <tr>
                                                <td>
                                                    @if ($fila['tiene_email'])
                                                        <input type="checkbox" class="op-envio-check" name="ids[]" value="{{ $fila['id'] }}"
                                                               data-ya-enviada="{{ !empty($fila['ya_enviada']) ? '1' : '0' }}"
                                                               title="{{ !empty($fila['ya_enviada']) ? 'Ya enviada: marcá solo si querés reenviar' : 'Pendiente de envío' }}">
                                                    @else
                                                        <input type="checkbox" disabled title="Sin email de proveedor: cargalo en el ABM del proveedor">
                                                    @endif
                                                </td>
                                                <td>
                                                    @if (can('editar-pagoproveedor', false))
                                                        <a href="{{ route('editar_pagoproveedor', $fila['id']) }}" target="_blank" rel="noopener">{{ $fila['etiqueta'] }}</a>
                                                    @else
                                                        {{ $fila['etiqueta'] }}
                                                    @endif
                                                    <div class="small text-muted">{{ $fila['estado_op'] }}</div>
                                                    @if (!empty($fila['advertencia_estado']))
                                                        <div class="small text-warning">{{ $fila['advertencia_estado'] }}</div>
                                                    @endif
                                                </td>
                                                <td>{{ $fila['fecha'] }}</td>
                                                <td>{{ $fila['proveedor'] }}</td>
                                                <td class="small">
                                                    @if ($fila['tiene_email'])
                                                        {{ $fila['email'] }}
                                                    @else
                                                        <span class="text-danger">Sin email</span>
                                                    @endif
                                                </td>
                                                <td>{{ $fila['medio_etiqueta'] }}</td>
                                                <td class="text-right">{{ number_format((float) $fila['importe'], 2, ',', '.') }}</td>
                                                <td class="small">
                                                    @if (!empty($fila['transferencia_nro']))
                                                        Nº {{ $fila['transferencia_nro'] }}
                                                        @if ($fila['transferencia_fecha'] !== '')
                                                            <br>{{ $fila['transferencia_fecha'] }}
                                                        @endif
                                                        @if ($fila['transferencia_importe'] !== null)
                                                            <br>{{ number_format((float) $fila['transferencia_importe'], 2, ',', '.') }}
                                                        @endif
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="badge badge-{{ $fila['asociacion_badge'] }}">{{ $fila['asociacion_etiqueta'] }}</span>
                                                    <div class="small text-muted mt-1">{{ $fila['asociacion_detalle'] }}</div>
                                                </td>
                                                <td class="small">
                                                    @if (empty($fila['tiene_email']))
                                                        <span class="badge badge-danger">Sin email</span>
                                                        <div class="text-muted mt-1">Cargá el mail en el proveedor.</div>
                                                    @elseif (!empty($fila['ya_enviada']))
                                                        <span class="badge badge-info">Ya enviada</span>
                                                        <div class="text-muted mt-1">Destildada; marcá para reenviar.</div>
                                                    @else
                                                        <span class="badge badge-success">Pendiente</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                    @if ($filas !== [])
                        <div class="card-body border-top">
                            <div class="form-group mb-2">
                                <label for="mensaje">Mensaje adicional <span class="text-muted">(opcional, va en todos los correos)</span></label>
                                <textarea name="mensaje" id="mensaje" class="form-control" rows="2" maxlength="4000"></textarea>
                            </div>
                            <button type="submit" class="btn btn-success">
                                <i class="fa fa-envelope"></i> Enviar seleccionadas
                            </button>
                            <span class="text-muted small ml-2">
                                Queda en cola; el resultado te llega por mail (no hace falta dejar la pantalla abierta).
                            </span>
                        </div>
                    @endif
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
