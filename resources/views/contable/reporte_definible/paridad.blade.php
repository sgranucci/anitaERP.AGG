@extends("theme.$theme.layout")
@section('titulo')
    Paridad Anita — reporte definible
@endsection

@section('styles')
<style>
.rd-par-importe { font-variant-numeric: tabular-nums; white-space: nowrap; text-align: right; }
.rd-par-dif { font-weight: 700; }
.rd-par-ok { color: #1E8449; }
.rd-par-mal { color: #922B21; }
.rd-par-cuentas td { font-size: 12px; color: #566573; background: #fbfcfc; }
</style>
@endsection

@section('contenido')
@php
    $resumen = $resultado['resumen'] ?? [];
    $parametros = $resultado['parametros'] ?? [];
    $stats = $resultado['stats'] ?? [];
    $filas = $resultado['filas'] ?? [];
    if (!empty($solo_diferencias)) {
        $filas = array_values(array_filter($filas, fn ($f) => empty($f['cuadra']) || empty($f['cuadra_motor'])));
    }
    $qs = array_filter($filtrosQuery ?? [], fn ($v) => $v !== null && $v !== '');
    $verdad = $parametros['verdad'] ?? [];
    $qsExport = http_build_query($qs);
    $qsExport = $qsExport !== '' ? '?'.$qsExport : '';
@endphp
<div class="row">
    <div class="col-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    Paridad Anita
                    @if ($reporte)
                        · {{ $reporte->codigo }} {{ $reporte->nombre }}
                    @endif
                </h3>
                <div class="card-tools">
                    @if ($reporte)
                        <a href="{{ route('listar_paridad_reporte_definible', ['id' => $reporte->id, 'formato' => 'PDF']).$qsExport }}"
                           class="btn btn-outline-danger btn-sm" title="Exportar a PDF">
                            <i class="fas fa-file-pdf"></i> Pdf
                        </a>
                        <a href="{{ route('listar_paridad_reporte_definible', ['id' => $reporte->id, 'formato' => 'EXCEL']).$qsExport }}"
                           class="btn btn-outline-success btn-sm" title="Exportar a Excel">
                            <i class="fas fa-file-excel"></i> Excel
                        </a>
                        <a href="{{ route('listar_paridad_reporte_definible', ['id' => $reporte->id, 'formato' => 'CSV']).$qsExport }}"
                           class="btn btn-outline-warning btn-sm" title="Exportar a CSV">
                            <i class="fas fa-file-csv"></i> Csv
                        </a>
                    @endif
                    <a href="{{ route('ejecutar_reporte_definible', array_merge(['id' => $reporte->id ?? null], $qs)) }}"
                       class="btn btn-outline-info btn-sm">
                        <i class="fas fa-reply-all"></i> Volver al informe
                    </a>
                </div>
            </div>
            <div class="card-body">
                @if (!$reporte)
                    <div class="alert alert-danger mb-0">
                        <ul class="mb-0">
                            @foreach ($resultado['advertencias'] ?? [] as $adv)
                                <li>{{ $adv }}</li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <div class="alert {{ (!empty($resumen['cuadra']) && !empty($resumen['cuadra_motor'])) ? 'alert-success' : 'alert-danger' }}">
                        @if (!empty($resumen['cuadra']))
                            <strong>Paridad OK.</strong>
                            Los {{ (int) ($resumen['rubros'] ?? 0) }} rubros coinciden con Anita
                            (tolerancia {{ number_format((float) ($parametros['tolerancia'] ?? 0), 2, ',', '.') }}).
                        @else
                            <strong>Hay diferencias.</strong>
                            {{ (int) ($resumen['con_diferencia'] ?? 0) }} de {{ (int) ($resumen['rubros'] ?? 0) }} rubros
                            no coinciden · suma |dif| {{ number_format((float) ($resumen['suma_abs_diferencia'] ?? 0), 2, ',', '.') }}
                            @if (!empty($resumen['peor']))
                                · peor {{ $resumen['peor']['codigo'] }} {{ $resumen['peor']['nombre'] }}
                                ({{ number_format((float) $resumen['peor']['diferencia'], 2, ',', '.') }})
                            @endif
                        @endif
                        @if (empty($resumen['cuadra_motor']))
                            <div class="mt-1">
                                <strong>Atención:</strong> el informe impreso (fuente
                                <code>{{ $resumen['fuente_impreso'] ?? '' }}</code>) no coincide con el recálculo por asientos
                                en {{ (int) ($resumen['con_diferencia_motor'] ?? 0) }} rubro(s)
                                @if (!empty($resumen['peor_motor']))
                                    · peor {{ $resumen['peor_motor']['codigo'] }} {{ $resumen['peor_motor']['nombre'] }}
                                    ({{ number_format((float) $resumen['peor_motor']['diferencia'], 2, ',', '.') }})
                                @endif
                                . Es un desvío del snapshot de sumas y saldos, no de la definición.
                            </div>
                        @endif
                    </div>

                    <div class="row mb-2">
                        <div class="col-md-8">
                            <div class="small text-muted">
                                Empresas {{ implode(', ', $parametros['empresa_ids'] ?? []) }} ·
                                {{ $parametros['fecha_desde'] ?? '' }} → {{ $parametros['fecha_hasta'] ?? '' }} ·
                                base {{ $parametros['base_saldo'] ?? '' }} ·
                                {{ (int) ($parametros['cuentas'] ?? 0) }} cuentas ·
                                asientos {{ $parametros['modo_inclusion_asientos'] ?? '' }}
                            </div>
                            @if (!empty($verdad))
                                <div class="small">
                                    Fuente de verdad del período:
                                    <span class="badge {{ ($verdad['fuente'] ?? '') === 'erp' ? 'badge-primary' : 'badge-secondary' }}">
                                        {{ $verdad['etiqueta'] ?? '' }}
                                    </span>
                                    <span class="text-muted">{{ $verdad['detalle'] ?? '' }}</span>
                                </div>
                            @endif
                            <div class="small text-muted">
                                Movimientos: anitaERP {{ number_format((int) ($stats['movimientos_erp'] ?? 0), 0, ',', '.') }} ·
                                Anita {{ number_format((int) ($stats['movimientos_anita'] ?? 0), 0, ',', '.') }}
                                (ctamov {{ number_format((int) ($stats['ctamov_filas'] ?? 0), 0, ',', '.') }},
                                subdiario {{ number_format((int) ($stats['subdiario_filas'] ?? 0), 0, ',', '.') }})
                            </div>
                        </div>
                        <div class="col-md-4 text-md-right">
                            <form method="get" action="{{ route('paridad_anita_reporte_definible', ['id' => $reporte->id]) }}" class="form-inline justify-content-md-end">
                                @foreach ($qs as $k => $v)
                                    @if (is_array($v))
                                        @foreach ($v as $vv)
                                            <input type="hidden" name="{{ $k }}[]" value="{{ $vv }}">
                                        @endforeach
                                    @else
                                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                                    @endif
                                @endforeach
                                <label class="mr-1 small">Tolerancia</label>
                                <input type="number" step="0.01" min="0" name="tolerancia" class="form-control form-control-sm mr-2"
                                       style="width:90px" value="{{ (float) ($parametros['tolerancia'] ?? 0.05) }}">
                                <div class="form-check mr-2">
                                    <input type="checkbox" class="form-check-input" id="rd_par_solo_dif" name="solo_diferencias" value="1"
                                           {{ !empty($solo_diferencias) ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="rd_par_solo_dif">Solo diferencias</label>
                                </div>
                                <button type="submit" class="btn btn-info btn-sm"><i class="fas fa-search"></i> Recalcular</button>
                            </form>
                        </div>
                    </div>

                    @if (!empty($resultado['advertencias']))
                        <div class="alert alert-warning">
                            <ul class="mb-0">
                                @foreach ($resultado['advertencias'] as $adv)
                                    <li>{{ $adv }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (!empty($resultado['cuentas_fuera_plan']))
                        <div class="card card-outline card-danger">
                            <div class="card-header py-2">
                                <h3 class="card-title">
                                    Cuentas con movimiento en Anita que no existen en el plan ERP
                                    ({{ count($resultado['cuentas_fuera_plan']) }})
                                </h3>
                            </div>
                            <div class="card-body p-2">
                                <table class="table table-sm table-bordered mb-1">
                                    <thead style="background:#85C1E9;color:#17202A;">
                                        <tr>
                                            <th style="width:160px;">Cuenta</th>
                                            <th class="text-right" style="width:180px;">Movimiento Anita</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($resultado['cuentas_fuera_plan'] as $cuenta)
                                            <tr>
                                                <td>{{ $cuenta['codigo_fmt'] }}</td>
                                                <td class="rd-par-importe">{{ number_format((float) $cuenta['anita'], 2, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                <div class="small text-muted">
                                    Están asignadas al informe y Anita las imputó, pero no figuran como cuenta imputable
                                    (<code>tipocuenta=1</code>) en el plan del ERP: hay que darlas de alta para poder igualar.
                                </div>
                            </div>
                        </div>
                    @endif

                    <table class="table table-sm table-bordered" id="tabla-paridad-rd">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th style="width:70px;">Línea</th>
                                <th>Rubro</th>
                                <th style="width:140px;" class="text-right" title="Valor tal como sale impreso el informe">Informe</th>
                                <th style="width:140px;" class="text-right" title="Recalculado sobre asiento_movimiento">Asientos ERP</th>
                                <th style="width:140px;" class="text-right">Anita</th>
                                <th style="width:130px;" class="text-right" title="Informe vs asientos ERP">Dif. motor</th>
                                <th style="width:140px;" class="text-right" title="Asientos ERP vs Anita">Dif. Anita</th>
                                <th style="width:80px;" class="text-right">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($filas as $fila)
                                <tr class="{{ (empty($fila['cuadra']) || empty($fila['cuadra_motor'])) ? 'table-warning' : '' }}">
                                    <td>{{ $fila['codigo'] }}</td>
                                    <td>
                                        <span style="display:inline-block;width:{{ max(0, ((int) $fila['nivel']) - 1) * 14 }}px;"></span>
                                        {{ $fila['nombre'] }}
                                    </td>
                                    <td class="rd-par-importe">
                                        {{ $fila['impreso'] !== null ? number_format((float) $fila['impreso'], 2, ',', '.') : '' }}
                                    </td>
                                    <td class="rd-par-importe">{{ number_format((float) $fila['erp'], 2, ',', '.') }}</td>
                                    <td class="rd-par-importe">{{ number_format((float) $fila['anita'], 2, ',', '.') }}</td>
                                    <td class="rd-par-importe rd-par-dif {{ empty($fila['cuadra_motor']) ? 'rd-par-mal' : 'rd-par-ok' }}">
                                        {{ empty($fila['cuadra_motor']) ? number_format((float) $fila['diferencia_motor'], 2, ',', '.') : '—' }}
                                    </td>
                                    <td class="rd-par-importe rd-par-dif {{ empty($fila['cuadra']) ? 'rd-par-mal' : 'rd-par-ok' }}">
                                        {{ empty($fila['cuadra']) ? number_format((float) $fila['diferencia'], 2, ',', '.') : '—' }}
                                    </td>
                                    <td class="rd-par-importe">
                                        {{ (empty($fila['cuadra']) && $fila['diferencia_pct'] !== null) ? number_format((float) $fila['diferencia_pct'], 2, ',', '.') : '' }}
                                    </td>
                                </tr>
                                @foreach ($fila['cuentas'] ?? [] as $cuenta)
                                    <tr class="rd-par-cuentas">
                                        <td></td>
                                        <td>Cuenta {{ $cuenta['codigo_fmt'] }}</td>
                                        <td></td>
                                        <td class="rd-par-importe">{{ number_format((float) $cuenta['erp'], 2, ',', '.') }}</td>
                                        <td class="rd-par-importe">{{ number_format((float) $cuenta['anita'], 2, ',', '.') }}</td>
                                        <td></td>
                                        <td class="rd-par-importe">{{ number_format((float) $cuenta['diferencia'], 2, ',', '.') }}</td>
                                        <td></td>
                                    </tr>
                                @endforeach
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted">Sin filas para mostrar.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="small text-muted">
                        anitaERP calcula sobre <code>asiento_movimiento</code>; Anita sobre <code>ctamov</code> + <code>subdiario</code>
                        (convención D/H de l-mayor). Ambos brazos usan el mismo árbol, asignaciones, signos y fórmulas del informe:
                        una diferencia indica datos faltantes en una de las dos fuentes, no una definición distinta.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
