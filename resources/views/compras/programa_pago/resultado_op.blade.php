@extends("theme.$theme.layout")
@section('titulo')
    Órdenes generadas
@endsection

@section('contenido')
@php
    $corrida = is_array($corrida ?? null) ? $corrida : null;
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')
        @if ($corrida)
            @if (! empty($corrida['errores']))
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($corrida['errores'] as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if (! empty($corrida['avisos']))
                <div class="alert alert-warning">
                    <ul class="mb-0">
                        @foreach ($corrida['avisos'] as $aviso)
                            <li>{{ $aviso }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if (! empty($corrida['generadas']))
                <div class="alert alert-success">
                    Se generaron {{ count($corrida['generadas']) }} orden(es) de pago.
                </div>
            @endif
        @endif

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    Órdenes del programa {{ $data->titulo ?: '#'.$data->id }}
                </h3>
                <div class="card-tools">
                    <a href="{{ route('editar_programa_pago', $data->id) }}" class="btn btn-outline-info btn-sm mr-1">
                        <i class="fa fa-reply-all"></i> Volver al programa
                    </a>
                    <a href="{{ route('pagoproveedor', ['empresa_id' => $data->empresa_id]) }}" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
                        Grilla de pagos
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0" id="tabla-paginada">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>OP</th>
                                <th>Fecha</th>
                                <th>Proveedor</th>
                                <th>Estado</th>
                                <th class="text-right">Monto</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($generadas as $fila)
                                @php
                                    $op = $fila->pagoproveedor;
                                    $prov = $fila->linea->proveedores ?? null;
                                @endphp
                                <tr>
                                    <td>
                                        @if ($op)
                                            {{ $op->tipocomprobante }} {{ $op->numerotransaccion }}
                                        @else
                                            #{{ $fila->pagoproveedor_id }}
                                        @endif
                                    </td>
                                    <td>{{ $op && $op->fecha ? \Carbon\Carbon::parse($op->fecha)->format('d/m/Y') : '' }}</td>
                                    <td>{{ $prov ? trim(($prov->codigo ?? '').' '.($prov->nombre ?? '')) : '' }}</td>
                                    <td>{{ $op->estado ?? '' }}</td>
                                    <td class="text-right">{{ $op ? number_format((float) $op->monto, 2, ',', '.') : '' }}</td>
                                    <td>
                                        <a class="btn-accion-tabla text-primary" target="_blank" rel="noopener" title="Abrir orden de pago"
                                           href="{{ route('editar_pagoproveedor', ['id' => $fila->pagoproveedor_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-muted">Todavía no se generaron órdenes desde este programa.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if ($corrida && ! empty($corrida['generadas']))
            <div class="card card-outline card-info">
                <div class="card-header">
                    <h3 class="card-title">Esta generación</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-bordered mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>OP</th>
                                <th>Proveedor</th>
                                <th class="text-right">Cheques</th>
                                <th class="text-right">Transferencia</th>
                                <th class="text-right">Anticipo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($corrida['generadas'] as $g)
                                <tr>
                                    <td>
                                        <a class="text-primary" target="_blank" rel="noopener"
                                           href="{{ route('editar_pagoproveedor', ['id' => $g['pagoproveedor_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">
                                            {{ $g['numero'] }}
                                        </a>
                                    </td>
                                    <td>{{ $g['proveedor'] }}</td>
                                    <td class="text-right">{{ number_format($g['cheques'], 2, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format($g['transferencia'], 2, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format($g['anticipo'], 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
