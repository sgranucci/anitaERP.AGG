@extends("theme.$theme.layout")
@section('titulo')
    Programa de pagos #{{ $data->id }}
@endsection

@section('styles')
<style>
    /* Solo scroll horizontal de la grilla; el vertical es el de la página */
    .pp-matriz-wrap {
        overflow-x: auto;
        overflow-y: visible;
        -webkit-overflow-scrolling: touch;
        border: 1px solid #dee2e6;
        border-radius: 4px;
    }
    .pp-matriz {
        margin-bottom: 0;
        min-width: 900px;
    }
    .pp-matriz thead th {
        white-space: nowrap;
        background: #85C1E9;
        color: #17202A;
    }
    .pp-matriz .pp-col-proveedor { min-width: 220px; background: #fff; }
    .pp-matriz .pp-monto { width: 100px; text-align: right; }
    .pp-matriz .pp-obs { min-width: 120px; }
    .pp-matriz tfoot td { font-weight: 600; background: #f8f9fa; }
    .pp-matriz tr.pp-fila-activa > td { background: #D6EAF8 !important; }
    .pp-matriz tr.pp-fila-pendiente .pp-col-proveedor { border-left: 4px solid #95a5a6; }
    .pp-matriz tr.pp-fila-parcial .pp-col-proveedor { border-left: 4px solid #f39c12; }
    .pp-matriz tr.pp-fila-completo .pp-col-proveedor { border-left: 4px solid #27ae60; }
    .pp-matriz tr.pp-fila-activa .pp-col-proveedor { background: #D6EAF8; }
    .pp-barra {
        background: #f4f9fc;
        border: 1px solid #aed6f1;
        border-radius: 4px;
        padding: 0.6rem 0.75rem;
        margin-bottom: 0.75rem;
    }
    .pp-barra .pp-nombre-activo { font-weight: 600; font-size: 1rem; }
    .pp-barra .pp-btns .btn { margin: 0 0.2rem 0.25rem 0; }
    .pp-meses-chips .btn {
        margin: 0 0.25rem 0.25rem 0;
        min-width: 5.5rem;
    }
    .pp-meses-chips .btn.pp-chip-on {
        background: #2471A3;
        border-color: #2471A3;
        color: #fff;
        font-weight: 600;
    }
    .pp-prorrateo-preview {
        font-size: 0.85rem;
        color: #1B4F72;
        background: #D6EAF8;
        border-radius: 3px;
        padding: 0.25rem 0.5rem;
        display: inline-block;
    }
    .pp-matriz thead th.pp-th-seleccionada {
        box-shadow: inset 0 -3px 0 #2471A3;
    }
</style>
@endsection

@section('scripts')
@php
    $ppColumnasJs = collect($matriz['columnas'] ?? [])->map(function ($c) {
        return [
            'clave' => $c['clave'],
            'etiqueta' => $c['etiqueta'],
            'anio_mes' => $c['anio_mes'] ?? null,
        ];
    })->values()->all();
@endphp
<script>
window.programaPagoCfg = {
    columnas: @json($ppColumnasJs),
    tieneTransf: @json((bool) ($data->incluye_transf ?? true)),
    urlAsignarCheques: @json(route('asignar_cheques_programa_pago', $data->id)),
    mostrarResultadoAsignacion: @json(session()->has('pp_asignacion_cheques'))
};
</script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/compras/programa_pago/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/programa_pago/form.js')) ?: time() }}"></script>
@endsection

@section('contenido')
@php
    $columnas = $matriz['columnas'] ?? [];
    $filas = $matriz['filas'] ?? [];
    $totales = $matriz['totales_asignacion'] ?? [];
    $cheques = $matriz['cheques']['por_clave'] ?? [];
    $diferencia = $matriz['diferencia'] ?? [];
    $chequesAsignados = $matriz['cheques_asignados'] ?? [];
    $resultadoAsignacion = session('pp_asignacion_cheques');
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    {{ $data->titulo ?: 'Programa #'.$data->id }}
                    <small class="ml-2">{{ $data->estado }} · {{ optional($data->fecha_base)->format('d/m/Y') }} · {{ $data->empresas->nombre ?? '' }}</small>
                </h3>
                <div class="card-tools">
                    <a href="{{ route('programa_pago') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>

            <form id="form-programa-pago" method="POST" action="{{ route('actualizar_programa_pago', $data->id) }}">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <div class="mb-2">
                        <a href="{{ route('exportar_matriz_programa_pago', ['id' => $data->id, 'formato' => 'PDF']) }}"
                           class="btn btn-app bg-danger" target="_blank" rel="noopener">
                            <i class="fas fa-file-pdf"></i> Pdf
                        </a>
                        <a href="{{ route('exportar_matriz_programa_pago', ['id' => $data->id, 'formato' => 'EXCEL']) }}"
                           class="btn btn-app bg-success">
                            <i class="fas fa-file-excel"></i> Excel
                        </a>
                        <a href="{{ route('exportar_matriz_programa_pago', ['id' => $data->id, 'formato' => 'CSV']) }}"
                           class="btn btn-app bg-warning">
                            <i class="fas fa-file-csv"></i> Csv
                        </a>
                    </div>
                    <div class="form-row mb-2">
                        <div class="form-group col-md-4 mb-2">
                            <label class="small">Título</label>
                            <input type="text" name="titulo" class="form-control form-control-sm" maxlength="120"
                                   value="{{ old('titulo', $data->titulo) }}" @disabled(! $puedeEditar)>
                        </div>
                        <div class="form-group col-md-8 mb-2">
                            <label class="small">Detalle</label>
                            <input type="text" name="detalle" class="form-control form-control-sm" maxlength="2000"
                                   value="{{ old('detalle', $data->detalle) }}" @disabled(! $puedeEditar)>
                        </div>
                    </div>

                    @if ($puedeEditar)
                        <div class="pp-barra" id="pp-barra-herramientas">
                            <div class="d-flex flex-wrap align-items-center mb-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary mr-1" id="pp-btn-prev" title="Anterior">
                                    <i class="fa fa-chevron-left"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary mr-2" id="pp-btn-next" title="Siguiente">
                                    <i class="fa fa-chevron-right"></i>
                                </button>
                                <div class="mr-3">
                                    <span class="text-muted small" id="pp-asistente-pos">—</span>
                                    <div class="pp-nombre-activo" id="pp-asistente-proveedor">—</div>
                                    <div class="small text-muted">
                                        Saldo <strong id="pp-asistente-saldo">—</strong>
                                        · Cargado <strong id="pp-asistente-asignado">—</strong>
                                        · Resta <strong id="pp-asistente-resto">—</strong>
                                    </div>
                                </div>
                                <div class="ml-auto small text-muted" id="pp-progreso-texto">—</div>
                            </div>

                            <div class="mb-2">
                                <div class="small text-muted mb-1">
                                    Marcá los meses (o TRANSF) donde va el pago de este proveedor:
                                </div>
                                <div class="pp-meses-chips" id="pp-meses-chips"></div>
                                <div class="mt-1">
                                    <span class="pp-prorrateo-preview" id="pp-prorrateo-preview">Seleccioná uno o más períodos</span>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap align-items-center pp-btns mb-1">
                                <button type="button" class="btn btn-sm btn-primary" id="pp-btn-partir-igual"
                                        title="Reparte el saldo en partes iguales entre los períodos marcados">
                                    Partir igual
                                </button>
                                <button type="button" class="btn btn-sm btn-info" id="pp-btn-todo-seleccion"
                                        title="Pone todo el saldo en el único período marcado (si hay más de uno, usa el primero)">
                                    Todo en marcado
                                </button>
                                <button type="button" class="btn btn-sm btn-success" id="pp-btn-partir-y-seguir"
                                        title="Partir igual y pasar al siguiente pendiente">
                                    Partir y siguiente
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="pp-btn-limpiar-chips"
                                        title="Desmarcar períodos">
                                    Desmarcar meses
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" id="pp-btn-limpiar"
                                        title="Limpiar montos del proveedor activo">
                                    Limpiar montos
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="pp-btn-asistente-saltar">
                                    Siguiente sin marcar
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-dark" id="pp-btn-asignar-cheques-activo"
                                        title="Asigna CHT diferidos en cartera al proveedor activo (usa montos ya guardados, ±30%)">
                                    <i class="fa fa-money-check"></i> Cheques (este)
                                </button>
                                <button type="button" class="btn btn-sm btn-dark" id="pp-btn-asignar-cheques-todos"
                                        title="Asigna CHT diferidos a todos los proveedores ya programados (montos guardados, ±30%)">
                                    <i class="fa fa-money-check-alt"></i> Cheques (todos programados)
                                </button>
                            </div>

                            <div class="small text-muted mt-1">
                                Asignar cheques usa los montos <strong>guardados</strong> (no los del formulario sin Guardar). Tolerancia ±30%.
                            </div>

                            <div class="d-flex flex-wrap align-items-center mt-2">
                                <div class="custom-control custom-checkbox mr-3">
                                    <input type="checkbox" class="custom-control-input" id="pp-solo-pendientes">
                                    <label class="custom-control-label" for="pp-solo-pendientes">Ocultar ya marcados (completos)</label>
                                </div>
                                <div class="progress flex-grow-1" style="height: 14px; min-width: 120px; max-width: 280px;">
                                    <div id="pp-progreso-barra" class="progress-bar bg-success" role="progressbar" style="width:0%">0%</div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="pp-matriz-wrap table-responsive">
                        <table class="table table-sm table-bordered table-hover pp-matriz mb-0" id="tabla-programa-pago">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th class="pp-col-proveedor">Proveedor</th>
                                    <th class="text-right">Saldo</th>
                                    @foreach($columnas as $col)
                                        <th class="text-right" data-clave="{{ $col['clave'] }}" title="Clic para marcar/desmarcar en el prorrateo" style="cursor:pointer;">
                                            {{ $col['etiqueta'] }}
                                        </th>
                                    @endforeach
                                    <th class="text-right">Total</th>
                                    <th>Obs.</th>
                                    @if ($puedeEditar)
                                        <th style="width:36px;"></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody id="tbody-programa-pago">
                                @foreach($filas as $idx => $fila)
                                    <tr data-linea-id="{{ $fila['id'] }}">
                                        <td class="pp-col-proveedor">
                                            <input type="hidden" name="lineas[{{ $idx }}][id]" value="{{ $fila['id'] }}">
                                            <input type="hidden" name="lineas[{{ $idx }}][proveedor_id]" value="{{ $fila['proveedor_id'] }}">
                                            <input type="hidden" name="lineas[{{ $idx }}][saldo_adeudado]" value="{{ $fila['saldo_adeudado'] }}">
                                            <span class="badge badge-secondary pp-badge-estado">Pendiente</span>
                                            <span class="pp-nombre-proveedor"><strong>{{ $fila['codigo'] }}</strong> {{ $fila['nombre'] }}</span>
                                        </td>
                                        <td class="text-right text-nowrap">{{ number_format($fila['saldo_adeudado'], 2, ',', '.') }}</td>
                                        @foreach($columnas as $col)
                                            <td>
                                                <input type="number" step="0.01" class="form-control form-control-sm pp-monto pp-asig"
                                                       name="lineas[{{ $idx }}][asignaciones][{{ $col['clave'] }}]"
                                                       value="{{ $fila['asignaciones'][$col['clave']] ?? 0 }}"
                                                       data-clave="{{ $col['clave'] }}"
                                                       @disabled(! $puedeEditar)>
                                            </td>
                                        @endforeach
                                        <td class="text-right text-nowrap pp-total-fila">{{ number_format($fila['total_fila'], 2, ',', '.') }}</td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm pp-obs"
                                                   name="lineas[{{ $idx }}][observacion]"
                                                   value="{{ $fila['observacion'] }}"
                                                   @disabled(! $puedeEditar)>
                                        </td>
                                        @if ($puedeEditar)
                                            <td class="text-center">
                                                <button type="button" class="btn-accion-tabla pp-quitar-fila" title="Quitar">
                                                    <i class="fa fa-times-circle text-danger"></i>
                                                </button>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td class="pp-col-proveedor text-right">Totales programa</td>
                                    <td class="text-right" id="pp-total-saldo">{{ number_format($matriz['total_saldo'] ?? 0, 2, ',', '.') }}</td>
                                    @foreach($columnas as $col)
                                        <td class="text-right pp-total-col" data-clave="{{ $col['clave'] }}">
                                            {{ number_format($totales[$col['clave']] ?? 0, 2, ',', '.') }}
                                        </td>
                                    @endforeach
                                    <td class="text-right" id="pp-total-programa">{{ number_format($matriz['total_programa'] ?? 0, 2, ',', '.') }}</td>
                                    <td @if($puedeEditar) colspan="2" @endif></td>
                                </tr>
                                <tr>
                                    <td class="pp-col-proveedor text-right">Cheques en cartera</td>
                                    <td></td>
                                    @foreach($columnas as $col)
                                        <td class="text-right">
                                            @if ($col['anio_mes'] ?? null)
                                                {{ number_format($cheques[$col['clave']] ?? 0, 2, ',', '.') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="text-right">{{ number_format($matriz['cheques']['total'] ?? 0, 2, ',', '.') }}</td>
                                    <td @if($puedeEditar) colspan="2" @endif></td>
                                </tr>
                                <tr>
                                    <td class="pp-col-proveedor text-right">Diferencia (CHT − pagos)</td>
                                    <td></td>
                                    @foreach($columnas as $col)
                                        @php $dif = (float) ($diferencia[$col['clave']] ?? 0); @endphp
                                        <td class="text-right {{ $dif < 0 ? 'text-danger' : '' }}">
                                            {{ number_format($dif, 2, ',', '.') }}
                                        </td>
                                    @endforeach
                                    <td></td>
                                    <td @if($puedeEditar) colspan="2" @endif></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </form>

            <div class="card-footer d-flex flex-wrap align-items-center">
                @if ($puedeEditar)
                    <button type="submit" form="form-programa-pago" class="btn btn-success mr-2">
                        <i class="fa fa-save"></i> Guardar
                    </button>
                    <form action="{{ route('refrescar_saldos_programa_pago', $data->id) }}" method="POST" class="d-inline mr-2">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary" onclick="return confirm('¿Actualizar saldos y agregar proveedores nuevos con deuda?');">
                            <i class="fa fa-sync"></i> Refrescar saldos
                        </button>
                    </form>
                    <form action="{{ route('cerrar_programa_pago', $data->id) }}" method="POST" class="d-inline mr-2">
                        @csrf
                        <button type="submit" class="btn btn-outline-warning" onclick="return confirm('¿Cerrar el programa? Quedará solo lectura.');">
                            <i class="fa fa-lock"></i> Cerrar
                        </button>
                    </form>
                    <form id="form-asignar-cheques-pp" method="POST" action="{{ route('asignar_cheques_programa_pago', $data->id) }}" class="d-none">
                        @csrf
                        <input type="hidden" name="linea_id" id="pp-asignar-linea-id" value="">
                    </form>
                @elseif (can('actualizar-programa-pago', false) && $data->estado === 'CERRADO')
                    <form action="{{ route('reabrir_programa_pago', $data->id) }}" method="POST" class="d-inline mr-2">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">
                            <i class="fa fa-unlock"></i> Reabrir
                        </button>
                    </form>
                @endif
            </div>
        </div>

        @if (count($chequesAsignados) > 0)
            <div class="card card-outline card-info mt-3">
                <div class="card-header">
                    <h3 class="card-title">Cheques asignados ({{ count($chequesAsignados) }})</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Mes</th>
                                    <th>Proveedor</th>
                                    <th>Nº interno</th>
                                    <th>Nº cheque</th>
                                    <th>Fecha pago</th>
                                    <th>Banco</th>
                                    <th class="text-right">Monto cheque</th>
                                    <th class="text-right">Programado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($chequesAsignados as $asigCh)
                                    <tr>
                                        <td>{{ $asigCh['clave'] }}</td>
                                        <td>{{ $asigCh['proveedor'] }}</td>
                                        <td>{{ $asigCh['nro_interno_anita'] ?? '—' }}</td>
                                        <td>{{ $asigCh['numerocheque'] }}</td>
                                        <td>{{ $asigCh['fechapago'] ? \Carbon\Carbon::parse($asigCh['fechapago'])->format('d/m/Y') : '' }}</td>
                                        <td>{{ $asigCh['banco'] }}</td>
                                        <td class="text-right">{{ number_format($asigCh['monto'], 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format($asigCh['monto_programado'], 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        @if ($puedeEditar)
            <div class="card card-outline card-info mt-3">
                <div class="card-header">
                    <h3 class="card-title">Agregar proveedor</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('agregar_proveedor_programa_pago', $data->id) }}" class="form-horizontal" id="form-agregar-proveedor-pp">
                        @csrf
                        <input type="hidden" name="empresa_id" id="empresa_id" value="{{ $data->empresa_id }}">
                        <div class="form-group row">
                            <label for="codigoproveedor" class="col-lg-3 control-label text-right pr-2 requerido">Proveedor</label>
                            <div class="col-lg-7">
                                <div class="input-group">
                                    <input type="hidden" name="proveedor_id" id="proveedor_id" class="proveedor_id" value="">
                                    <input type="text" class="form-control codigoproveedor" id="codigoproveedor" name="codigoproveedor"
                                           placeholder="Código" autocomplete="off" style="max-width:110px;">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-info consultaproveedor" title="Consultar (F1)"><i class="fa fa-search"></i></button>
                                    </div>
                                    <input type="text" class="form-control nombreproveedor" id="nombreproveedor" readonly
                                           placeholder="Descripción" autocomplete="off">
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-lg-3 control-label text-right pr-2">Observación</label>
                            <div class="col-lg-7">
                                <input type="text" name="observacion" class="form-control" maxlength="500">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fa fa-plus"></i> Agregar
                        </button>
                    </form>
                </div>
            </div>
            @include('includes.compras.modalconsultaproveedor')
        @endif
    </div>
</div>

@if (is_array($resultadoAsignacion))
    @php
        $ppAsignados = $resultadoAsignacion['asignados'] ?? [];
        $ppDiscrepancias = $resultadoAsignacion['discrepancias'] ?? [];
        $ppResumen = $resultadoAsignacion['resumen'] ?? [];
    @endphp
    <div class="modal fade" id="ppAsignacionChequesModal" tabindex="-1" role="dialog" aria-labelledby="ppAsignacionChequesTitulo" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ppAsignacionChequesTitulo">Resultado asignación de cheques</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">
                        Cheques asignados: <strong>{{ (int) ($ppResumen['asignados'] ?? 0) }}</strong>
                        · Monto: <strong>{{ number_format((float) ($ppResumen['monto_asignado'] ?? 0), 2, ',', '.') }}</strong>
                        · Discrepancias: <strong class="{{ ((int) ($ppResumen['discrepancias'] ?? 0)) > 0 ? 'text-danger' : '' }}">{{ (int) ($ppResumen['discrepancias'] ?? 0) }}</strong>
                        @if ((float) ($ppResumen['monto_sin_cubrir'] ?? 0) > 0)
                            · Sin cubrir: <strong class="text-danger">{{ number_format((float) $ppResumen['monto_sin_cubrir'], 2, ',', '.') }}</strong>
                        @endif
                    </p>
                    <p class="small text-muted">Tolerancia permitida: ±30% entre programado y suma de cheques del mes.</p>

                    @if (count($ppDiscrepancias) > 0)
                        <h6 class="text-danger">Discrepancias</h6>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>Proveedor</th>
                                        <th>Mes</th>
                                        <th class="text-right">Programado</th>
                                        <th class="text-right">Asignado</th>
                                        <th>Detalle</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($ppDiscrepancias as $disc)
                                        <tr>
                                            <td>{{ $disc['proveedor'] ?? '' }}</td>
                                            <td>{{ $disc['clave'] ?? '' }}</td>
                                            <td class="text-right">{{ number_format((float) ($disc['monto_programado'] ?? 0), 2, ',', '.') }}</td>
                                            <td class="text-right">{{ number_format((float) ($disc['monto_asignado'] ?? 0), 2, ',', '.') }}</td>
                                            <td>{{ $disc['detalle'] ?? '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if (count($ppAsignados) > 0)
                        <h6>Cheques asignados en esta corrida</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>Proveedor</th>
                                        <th>Mes</th>
                                        <th>Nº int.</th>
                                        <th>Nº cheque</th>
                                        <th>Fecha</th>
                                        <th class="text-right">Monto</th>
                                        <th class="text-right">Programado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($ppAsignados as $asig)
                                        <tr>
                                            <td>{{ $asig['proveedor'] ?? '' }}</td>
                                            <td>{{ $asig['clave'] ?? '' }}</td>
                                            <td>{{ $asig['nro_interno_anita'] ?? '—' }}</td>
                                            <td>{{ $asig['numerocheque'] ?? '' }}</td>
                                            <td>{{ ! empty($asig['fechapago']) ? \Carbon\Carbon::parse($asig['fechapago'])->format('d/m/Y') : '' }}</td>
                                            <td class="text-right">{{ number_format((float) ($asig['monto'] ?? 0), 2, ',', '.') }}</td>
                                            <td class="text-right">{{ number_format((float) ($asig['monto_programado'] ?? 0), 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection
