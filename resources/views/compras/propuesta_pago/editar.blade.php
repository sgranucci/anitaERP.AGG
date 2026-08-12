@extends("theme.$theme.layout")
@section('titulo')
    Editar propuesta de pagos
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    use App\Support\Compras\PropuestaPagoModoSupport;
    $estado = (string) ($data->estado ?? '');
    $editable = in_array($estado, \App\Models\Compras\PropuestaPago::estadosEditables(), true);
    $instrumentosEditables = $editable || $estado === 'AUTORIZADA';
    $puedeEnviar = can('enviar-aprobacion-propuesta-pago', false) && in_array($estado, ['BORRADOR', 'RECHAZADA'], true);
    $puedeEjecutar = can('ejecutar-propuesta-pago', false) && $estado === 'AUTORIZADA';
    $puedeReabrir = can('actualizar-propuesta-pago', false) && $estado === 'AUTORIZADA';
    $puedeReabrirParcial = can('actualizar-propuesta-pago', false) && $estado === 'EJECUTADA_PARCIAL';
    $puedeDelta = can('crear-propuesta-pago', false) && in_array($estado, ['AUTORIZADA', 'EJECUTADA', 'EJECUTADA_PARCIAL'], true);
    $puedeMarcarEnviado = can('ejecutar-propuesta-pago', false)
        && in_array($estado, ['EJECUTADA', 'EJECUTADA_PARCIAL'], true)
        && ! empty($lote_bancario)
        && in_array((string) ($lote_bancario->estado ?? ''), ['BORRADOR', 'EXPORTADO'], true);
    $puedeBorrar = can('borrar-propuesta-pago', false) && in_array($estado, ['BORRADOR', 'RECHAZADA', 'ANULADA'], true);
    $modoCfg = PropuestaPagoModoSupport::config((int) ($data->empresa_id ?? 0));
    $exigeArbol = (bool) $modoCfg->exige_arbol_aprobacion;
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    Propuesta #{{ $data->id }} — {{ $estado }} — Total {{ number_format((float)$data->monto_total, 2, ',', '.') }}
                    <span class="badge badge-{{ $exigeArbol ? 'primary' : 'secondary' }} ml-1">
                        {{ $exigeArbol ? 'Premium' : 'Light' }}
                    </span>
                </h3>
                <div class="card-tools">
                    @if (can('editar-configuracion-propuesta-pago', false))
                        <a href="{{ route('configuracion_propuesta_pago', ['empresa_id' => $data->empresa_id]) }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fa fa-cogs"></i> Config
                        </a>
                    @endif
                    <a href="{{ route('imprimir_propuesta_pago', $data->id) }}" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
                        <i class="fa fa-file-pdf"></i> PDF
                    </a>
                    @include('includes.compras.boton-manual-propuesta-pago')
                    <a href="{{ route('auditoria_propuesta_pago', $data->id) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-shield-alt"></i> Auditoría
                    </a>
                    <a href="{{ route('clearing_bancario', ['empresa_id' => $data->empresa_id]) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-balance-scale"></i> Clearing
                    </a>
                    <a href="{{ route('tesoreria_cockpit') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-tachometer-alt"></i> Cockpit
                    </a>
                    <a href="{{ route('propuesta_pago') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver
                    </a>
                </div>
            </div>
            <form action="{{ route('actualizar_propuesta_pago', $data->id) }}" method="POST" id="form-propuesta-pago" class="form-horizontal" autocomplete="off">
                @csrf
                @method('PUT')
                <div class="card-body">
                    @include('compras.propuesta_pago.form')
                    @if ($editable)
                        <div class="form-group row">
                            <div class="col-lg-9 offset-lg-3">
                                <label class="mb-0">
                                    <input type="checkbox" name="rearmar_lineas" value="1"> Rearmar líneas desde deuda (reemplaza la grilla)
                                </label>
                            </div>
                        </div>
                    @endif
                </div>
            </form>
            <div class="card-footer">
                @if (($editable || $instrumentosEditables) && can('actualizar-propuesta-pago', false))
                    <button type="submit" form="form-propuesta-pago" class="btn btn-success">
                        <i class="fa fa-save"></i> {{ $editable ? 'Actualizar' : 'Guardar caja / cuenta' }}
                    </button>
                @endif
                @if ($puedeEnviar)
                    <form action="{{ route('enviar_aprobacion_propuesta_pago', $data->id) }}" method="POST" class="d-inline"
                          onsubmit="return confirm('{{ $exigeArbol ? '¿Enviar al árbol de aprobación del lote?' : '¿Autorizar propuesta (modo light, sin árbol)?' }}');">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-share"></i>
                            {{ $exigeArbol ? 'Enviar a aprobación' : 'Autorizar (light)' }}
                        </button>
                    </form>
                @endif
                @if ($puedeEjecutar || in_array($estado, ['EJECUTADA', 'EJECUTADA_PARCIAL'], true))
                    @if ($puedeEjecutar)
                    <form action="{{ route('ejecutar_propuesta_pago', $data->id) }}" method="POST" class="d-inline"
                          onsubmit="return confirm('¿Ejecutar y generar órdenes de pago por proveedor?');">
                        @csrf
                        <button type="submit" class="btn btn-warning"><i class="fa fa-play"></i> Ejecutar → OP</button>
                    </form>
                    <form action="{{ route('conciliar_bridge_propuesta_pago', $data->id) }}" method="POST" class="d-inline"
                          onsubmit="return confirm('¿Intentar conciliar OP del lote con Interbanking?');">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary"><i class="fa fa-university"></i> Conciliar IB</button>
                    </form>
                    @endif
                    <form action="{{ route('generar_lote_bancario_propuesta_pago', $data->id) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-success"><i class="fa fa-file-csv"></i> Generar lote bancario</button>
                    </form>
                    <a href="{{ route('exportar_lote_bancario_propuesta_pago', $data->id) }}" class="btn btn-success">
                        <i class="fa fa-download"></i> Exportar archivo bancario
                    </a>
                    @if ($puedeMarcarEnviado)
                    <form action="{{ route('marcar_lote_enviado_propuesta_pago', $data->id) }}" method="POST" class="d-inline"
                          onsubmit="return confirm('¿Marcar lote como ENVIADO al banco? Bloquea las OP del archivo.');">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger"><i class="fa fa-lock"></i> Marcar enviado banco</button>
                    </form>
                    @endif
                @endif
                @if ($puedeReabrir)
                    <form action="{{ route('reabrir_propuesta_pago', $data->id) }}" method="POST" class="d-inline"
                          onsubmit="return confirm('¿Reabrir a borrador (re-propuesta)?');">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary"><i class="fa fa-undo"></i> Reabrir</button>
                    </form>
                @endif
                @if ($puedeReabrirParcial)
                    <form action="{{ route('reabrir_parcial_propuesta_pago', $data->id) }}" method="POST" class="d-inline"
                          onsubmit="return confirm('¿Reabrir parcial (pendientes a AUTORIZADA)? Las OP ya enviadas al banco quedan bloqueadas.');">
                        @csrf
                        <button type="submit" class="btn btn-outline-warning"><i class="fa fa-undo"></i> Reabrir parcial</button>
                    </form>
                @endif
                @if ($puedeDelta)
                    <form action="{{ route('clonar_delta_propuesta_pago', $data->id) }}" method="POST" class="d-inline"
                          onsubmit="return confirm('¿Crear propuesta delta con líneas pendientes/excluidas?');">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary"><i class="fa fa-copy"></i> Propuesta delta</button>
                    </form>
                @endif
                @if ($puedeBorrar)
                    <form action="{{ route('eliminar_propuesta_pago', $data->id) }}" method="POST" class="d-inline"
                          onsubmit="return confirm('¿Eliminar propuesta?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger"><i class="fa fa-times-circle"></i> Eliminar</button>
                    </form>
                @endif
            </div>
            @if ($data->estados && $data->estados->count())
                <div class="card-body border-top">
                    <h5>Historia de estados</h5>
                    <ul class="mb-0">
                        @foreach($data->estados->sortByDesc('id') as $est)
                            <li>
                                {{ optional($est->fecha)->format('d/m/Y H:i') }} —
                                <strong>{{ $est->estado }}</strong>
                                {{ $est->observacion }}
                                ({{ $est->usuarios->nombre ?? '' }})
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if (! empty($lote_bancario) || (! empty($lotes_bancarios) && $lotes_bancarios->count()))
                <div class="card-body border-top">
                    <h5>Lote bancario / auditoría</h5>
                    @if (! empty($lote_bancario))
                        <p class="mb-2">
                            Vigente: <strong>#{{ $lote_bancario->id }}</strong>
                            — {{ $lote_bancario->estado }}
                            — {{ $lote_bancario->cantidad_lineas }} líneas
                            — neto {{ number_format((float)$lote_bancario->monto_total, 2, ',', '.') }}
                            @if ($lote_bancario->archivo_nombre)
                                — archivo {{ $lote_bancario->archivo_nombre }}
                                ({{ optional($lote_bancario->exportado_at)->format('d/m/Y H:i') }})
                            @endif
                        </p>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>CUIT</th><th>CBU</th><th>Alias</th><th class="text-right">Neto</th>
                                        <th>Referencia</th><th>Proveedor</th><th>OP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($lote_bancario->lineas as $lb)
                                        <tr>
                                            <td>{{ $lb->cuit }}</td>
                                            <td>{{ $lb->cbu }}</td>
                                            <td>{{ $lb->alias_cbu }}</td>
                                            <td class="text-right">{{ number_format((float)$lb->monto_neto, 2, ',', '.') }}</td>
                                            <td>{{ $lb->referencia_op }}</td>
                                            <td>{{ $lb->proveedor_nombre }}</td>
                                            <td>
                                                @if ($lb->pagoproveedor_id)
                                                    <a class="text-primary" href="{{ route('editar_pagoproveedor', $lb->pagoproveedor_id) }}" target="_blank" rel="noopener">#{{ $lb->pagoproveedor_id }}</a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                    @if (! empty($lotes_bancarios) && $lotes_bancarios->count() > 1)
                        <p class="small text-muted mb-0">Histórico: {{ $lotes_bancarios->pluck('id')->implode(', ') }}</p>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
