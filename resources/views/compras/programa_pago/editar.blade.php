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
    .pp-barra .form-control-sm { min-width: 110px; }
    .pp-barra .pp-btns .btn { margin: 0 0.2rem 0.25rem 0; }
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
    tieneTransf: @json((bool) ($data->incluye_transf ?? true))
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

                            <div class="d-flex flex-wrap align-items-end pp-btns">
                                <div class="mr-2 mb-1">
                                    <label class="small d-block mb-0">Poner todo en</label>
                                    <select id="pp-mes-todo" class="form-control form-control-sm"></select>
                                </div>
                                <div class="mr-2 mb-1">
                                    <label class="small d-block mb-0">50/50 mes A</label>
                                    <select id="pp-mes-a" class="form-control form-control-sm"></select>
                                </div>
                                <div class="mr-2 mb-1">
                                    <label class="small d-block mb-0">50/50 mes B</label>
                                    <select id="pp-mes-b" class="form-control form-control-sm"></select>
                                </div>
                                <div class="mb-1">
                                    @if ($data->incluye_transf)
                                        <button type="button" class="btn btn-sm btn-warning" id="pp-btn-asistente-transf" title="Todo el saldo a TRANSF y pasa al siguiente">
                                            TRANSF y siguiente
                                        </button>
                                    @endif
                                    <button type="button" class="btn btn-sm btn-info" id="pp-btn-asistente-mes" title="Todo el saldo al mes elegido y pasa al siguiente">
                                        Mes y siguiente
                                    </button>
                                    <button type="button" class="btn btn-sm btn-primary" id="pp-btn-asistente-5050" title="50% / 50% entre A y B y pasa al siguiente">
                                        50/50 y siguiente
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="pp-btn-asistente-saltar">
                                        Siguiente sin marcar
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" id="pp-btn-limpiar" title="Limpiar montos del proveedor activo">
                                        Limpiar
                                    </button>
                                </div>
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
                                        <th class="text-right">{{ $col['etiqueta'] }}</th>
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
@endsection
