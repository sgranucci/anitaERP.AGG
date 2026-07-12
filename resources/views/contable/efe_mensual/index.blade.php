@extends("theme.$theme.layout")
@section('titulo')
    Estado de flujo mensual (EFE)
@endsection

@section('scripts')
<script>
(function () {
    var form = document.getElementById('form-efe-mensual');
    if (form) {
        form.addEventListener('submit', function () {
            var btn = document.getElementById('btn-consultar');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Procesando… (puede tardar varios minutos)';
            }
        });
    }
})();
</script>
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Estado de flujo mensual (EFE)</h3>
                <div class="card-tools">
                    <a href="{{ route('efe_mensual') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('efe_mensual') }}" id="form-efe-mensual" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Genera el Excel de flujo de fondos mensual reutilizando el motor y la auditoría del
                        <strong>Mayor por concepto</strong> (una lectura Anita del mes; lookups previos al mes
                        van al bridge on-demand). La solapa <em>Datos</em> y el resumen por concepto se
                        calculan sobre ese motor; las demás solapas conservan la plantilla y sus fórmulas.
                    </p>

                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $filtros['empresa_id'] ?? null,
                        'required' => true,
                        'col_label' => 'col-lg-2',
                        'col_input' => 'col-lg-4',
                    ])

                    <div class="form-group row">
                        <label class="col-lg-2 control-label requerido">Mes / Año</label>
                        <div class="col-lg-9">
                            <div class="row">
                                <div class="col-md-3">
                                    <select name="mes" class="form-control">
                                        @for ($m = 1; $m <= 12; $m++)
                                            <option value="{{ $m }}" @selected((int) ($filtros['mes'] ?? $mes_actual) === $m)>
                                                {{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}
                                            </option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <input type="number" name="anio" class="form-control" min="2000" max="2100"
                                        value="{{ $filtros['anio'] ?? $anio_actual }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-lg-2 control-label requerido">Moneda reporte</label>
                        <div class="col-lg-4">
                            <select name="moneda_id" class="form-control">
                                @foreach ($moneda_query as $mon)
                                    <option value="{{ $mon->id }}" @selected((int) ($filtros['moneda_id'] ?? 1) === (int) $mon->id)>
                                        {{ $mon->nombre }} ({{ $mon->abreviatura }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <label class="col-lg-2 control-label"></label>
                        <div class="col-lg-9">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="solo_moneda_origen" id="solo_moneda_origen" value="1"
                                    @checked(! empty($filtros['solo_moneda_origen']))>
                                <label class="form-check-label" for="solo_moneda_origen">Solo moneda origen</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <input type="hidden" name="consultar" value="1">
                    <button type="submit" class="btn btn-primary" id="btn-consultar">
                        <i class="fa fa-search"></i> Consultar
                    </button>
                </div>
            </form>
        </div>

        @if ($consultado)
            <div class="card card-outline card-secondary">
                <div class="card-header">
                    <h3 class="card-title">
                        Preview — Resumen de pagos por concepto
                        @if ($periodo_texto !== '')
                            <small class="text-muted">({{ $periodo_texto }})</small>
                        @endif
                    </h3>
                    <div class="card-tools" id="efe-exportar">
                        @php
                            $paramsExport = array_filter($filtrosQuery ?? [], fn ($v) => $v !== null && $v !== '');
                            $suffixExport = count($paramsExport) ? '?'.http_build_query($paramsExport) : '';
                        @endphp
                        <a href="{{ route('listar_efe_mensual', ['formato' => 'EXCEL']).$suffixExport }}" class="btn btn-app bg-success">
                            <i class="fas fa-file-excel"></i> Excel EFE
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    @if (! empty($errores_bridge))
                        <div class="alert alert-warning m-3 mb-0">
                            <strong>Avisos del bridge Anita:</strong>
                            <ul class="mb-0 pl-3">
                                @foreach (array_slice($errores_bridge, 0, 10) as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($totales)
                        <div class="px-3 py-2 border-bottom small text-muted">
                            Líneas en Datos: {{ number_format((int) ($totales['lineas_datos'] ?? 0), 0, ',', '.') }}
                            · Pagos: {{ number_format((float) ($totales['total_pagos'] ?? 0), 2, ',', '.') }}
                            · Cobros: {{ number_format((float) ($totales['total_cobros'] ?? 0), 2, ',', '.') }}
                            · Neto resumen: {{ number_format((float) ($totales['neto_resumen'] ?? 0), 2, ',', '.') }}
                            @if (isset($totales['auditoria_asientos_analizados']))
                                · Auditoría mayor:
                                @if (! empty($totales['auditoria_cuadra']))
                                    <span class="badge badge-success">cuadra</span>
                                @else
                                    <span class="badge badge-warning">
                                        {{ (int) ($totales['auditoria_asientos_descuadrados'] ?? 0) }} asientos Δ
                                    </span>
                                @endif
                                ({{ (int) ($totales['auditoria_asientos_analizados'] ?? 0) }} analizados)
                            @endif
                        </div>
                    @endif

                    @include('contable.mayor_concepto.partials.conciliacion_asientos_panel', [
                        'auditoria_panel' => $auditoria_panel ?? null,
                    ])

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0" id="tabla-paginada">
                            <thead>
                                <tr style="background-color: #85C1E9; color: #17202A;">
                                    <th>Concepto</th>
                                    <th>Nombre</th>
                                    <th class="text-right">Líneas</th>
                                    <th class="text-right">Pagos</th>
                                    <th class="text-right">Cobros</th>
                                    <th class="text-right">Neto EFE</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse (($filas_preview ?? collect()) as $fila)
                                    <tr>
                                        <td>{{ $fila['concepto_id'] ?? '' }}</td>
                                        <td>{{ $fila['concepto_nombre'] ?? '' }}</td>
                                        <td class="text-right">{{ (int) ($fila['cantidad_lineas'] ?? 0) }}</td>
                                        <td class="text-right">
                                            @if ((float) ($fila['pagos'] ?? 0) !== 0.0)
                                                {{ number_format((float) $fila['pagos'], 2, ',', '.') }}
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if ((float) ($fila['cobros'] ?? 0) !== 0.0)
                                                {{ number_format((float) $fila['cobros'], 2, ',', '.') }}
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if ((float) ($fila['neto'] ?? 0) !== 0.0)
                                                {{ number_format((float) $fila['neto'], 2, ',', '.') }}
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">Sin movimientos para el período.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if ($filas_preview instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                    <div class="card-footer clearfix">
                        {{ $filas_preview->appends($filtrosQuery ?? [])->links() }}
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection
