@extends("theme.$theme.layout")

@section('titulo')
    Envío de OP a proveedores
@endsection

@section('scripts')
<script>
(function () {
    function conteo() {
        var n = document.querySelectorAll('.op-envio-check:checked').length;
        var el = document.getElementById('op-envio-conteo');
        if (el) {
            el.textContent = String(n);
        }
        var todas = document.getElementById('op-envio-todas');
        if (todas) {
            var habilitados = document.querySelectorAll('.op-envio-check');
            var marcados = document.querySelectorAll('.op-envio-check:checked');
            todas.checked = habilitados.length > 0 && habilitados.length === marcados.length;
        }
    }

    var todas = document.getElementById('op-envio-todas');
    if (todas) {
        todas.addEventListener('change', function () {
            document.querySelectorAll('.op-envio-check').forEach(function (el) {
                el.checked = todas.checked;
            });
            conteo();
        });
    }
    document.querySelectorAll('.op-envio-check').forEach(function (el) {
        el.addEventListener('change', conteo);
    });

    var form = document.getElementById('form-envio-op-masivo');
    if (form) {
        form.addEventListener('submit', function (e) {
            if (document.querySelectorAll('.op-envio-check:checked').length === 0) {
                e.preventDefault();
                window.alert('Seleccione al menos una orden de pago.');
            }
        });
    }
    conteo();
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
                        Destilde las que no quiera enviar.
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
            <form method="post" action="{{ route('pagoproveedor_envio_masivo_enviar') }}" id="form-envio-op-masivo"
                  data-mensaje-grabacion="Enviando correos a proveedores…">
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
                                                <input type="checkbox" id="op-envio-todas" title="Marcar todas las que se pueden enviar">
                                            </th>
                                            <th>OP</th>
                                            <th>Fecha</th>
                                            <th>Proveedor</th>
                                            <th>Email</th>
                                            <th>Medio</th>
                                            <th class="text-right">Neto</th>
                                            <th>Transferencia</th>
                                            <th>Asociación</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($filas as $fila)
                                            <tr>
                                                <td>
                                                    @if ($fila['tiene_email'])
                                                        <input type="checkbox" class="op-envio-check" name="ids[]" value="{{ $fila['id'] }}"
                                                               @checked($fila['seleccionada'])>
                                                    @else
                                                        <input type="checkbox" disabled title="Sin email de proveedor">
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
                        </div>
                    @endif
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
