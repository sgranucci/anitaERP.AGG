@extends("theme.$theme.layout")
@section('titulo')
    {{ ! empty($modo_edicion) ? 'Editar rendición bingo' : 'Cargar rendición bingo' }}
@endsection

@section("scripts")
<style>
    .bingo-rend-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 991px) { .bingo-rend-grid { grid-template-columns: 1fr; } }
    .bingo-rend-panel thead th { background: #85C1E9; color: #17202A; }
    .bingo-rend-recaudacion-banner {
        background: #eaf2f8;
        border-bottom: 1px solid #d5e3ef;
        padding: 0.85rem 1rem;
        text-align: center;
    }
    .bingo-rend-recaudacion-banner .bingo-rend-recaudacion-valor {
        font-size: 1.35rem;
        font-weight: 700;
        color: #17202A;
    }
    .bingo-carton-row-anulado { opacity: 0.45; text-decoration: line-through; }
    .bingo-rend-concepto-auto .js-concepto-monto-auto {
        display: inline-block;
        min-width: 7rem;
        padding: 0.2rem 0.45rem;
        background: #f4f6f7;
        border-radius: 0.2rem;
    }
    .bingo-rend-concepto-manual .js-monto-manual { max-width: 9rem; margin-left: auto; }
    .bingo-rend-concepto-saldo td {
        background: #eafaf1;
        font-weight: 700;
    }
    .bingo-rend-concepto-saldo .js-concepto-saldo-rendicion {
        display: inline-block;
        min-width: 7rem;
        padding: 0.2rem 0.45rem;
        font-size: 1.05rem;
    }
</style>
<script src="{{ asset('assets/pages/scripts/caja/bingo/rendicion_cargar.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/bingo/rendicion_cargar.js')) }}"></script>
@endsection

@section('contenido')
<div class="row" id="bingo-rendicion-app"
     data-api-calcular="{{ route('bingo_rendicion_api_calcular') }}"
     data-api-guardar="{{ route('bingo_rendicion_api_guardar') }}"
     data-api-guardar-borrador="{{ route('bingo_rendicion_api_guardar_borrador') }}"
     data-url-habilitacion="{{ route('bingo_habilitacion_turno', ['empresa_id' => $empresa_id]) }}"
     data-url-cierres="{{ route('bingo_cierres_turno', ['empresa_id' => $empresa_id]) }}"
     data-csrf="{{ csrf_token() }}"
     data-empresa-id="{{ (int) $empresa_id }}"
     data-turno-id="{{ (int) ($turno_id ?? 0) }}"
     data-modo-edicion="{{ ! empty($modo_edicion) ? '1' : '0' }}">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="card card-info mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    @if (! empty($modo_edicion))
                        Editar rendición — turno #{{ (int) ($turno_id ?? 0) }} · <code>{{ $identificador_pc }}</code>
                    @else
                        Rendición de turno — terminal <code>{{ $identificador_pc }}</code>
                    @endif
                </h3>
                <div class="card-tools">
                    @if (! empty($modo_edicion))
                        <a href="{{ route('bingo_cierres_turno', ['empresa_id' => $empresa_id]) }}" class="btn btn-outline-secondary btn-sm">Consulta rendiciones</a>
                    @else
                        <a href="{{ route('bingo_habilitacion_turno', ['empresa_id' => $empresa_id]) }}" class="btn btn-outline-secondary btn-sm">Habilitación</a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                @if (empty($modo_edicion))
                    <form method="get" action="{{ route('bingo_rendicion_cargar') }}" class="form-inline mb-3">
                        @include('includes.listado.filtro_empresa_asignada_inline', [
                            'empresa_query' => $empresa_query,
                            'empresa_id' => $empresa_id,
                            'required' => true,
                            'permite_todas' => false,
                            'select_class' => 'js-auto-consultar-empresa',
                        ])
                    </form>
                @endif

                @if ($error)
                    <div class="alert alert-warning">{{ $error }}</div>
                @elseif ($datos)
                    @php
                        $turno = $datos['turno'];
                        $lineasPorConcepto = collect($datos['calculo']['lineas_concepto'] ?? [])->keyBy('concepto_id');
                    @endphp
                    <div class="alert alert-info py-2">
                        <strong>{{ $turno->turno?->nombre }}</strong>
                        · Jornada {{ optional($turno->jornada?->fecha_jornada)->format('d/m/Y') }}
                        · {{ $turno->usuarioHabilitado?->nombre }}
                        @if (! empty($modo_edicion))
                            <span class="badge badge-warning ml-2">Pendiente de presentar en caja</span>
                        @endif
                        @if (! empty($datos['tiene_borrador']))
                            <span class="badge badge-primary ml-2" id="badge-borrador-rendicion">Borrador guardado</span>
                        @else
                            <span class="badge badge-primary ml-2 d-none" id="badge-borrador-rendicion">Borrador guardado</span>
                        @endif
                        @if ($datos['rendicion_presentada'])
                            <span class="badge badge-success ml-2">Ya presentada en caja</span>
                        @endif
                    </div>

                    <div class="bingo-rend-grid">
                        <div class="card">
                            <div class="card-header"><strong>Cartones vendidos</strong></div>
                            <div class="card-body p-0 table-responsive">
                                <table class="table table-sm table-bordered mb-0 bingo-rend-panel" id="tabla-cartones-rendicion">
                                    <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Descripción</th>
                                            <th class="text-right">Precio</th>
                                            <th class="text-right" style="width:90px;">Cant.</th>
                                            <th class="text-center" style="width:70px;">Anular</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($datos['cartones'] as $idx => $c)
                                            <tr class="js-carton-row {{ ! empty($c['anulado']) ? 'bingo-carton-row-anulado' : '' }}"
                                                data-carton-id="{{ (int) $c['carton_id'] }}"
                                                data-precio="{{ (float) $c['precio_unitario'] }}">
                                                <td>{{ $c['codigo'] }}</td>
                                                <td>{{ $c['nombre'] }}</td>
                                                <td class="text-right">${{ number_format((float) $c['precio_unitario'], 2, ',', '.') }}</td>
                                                <td>
                                                    <input type="number" min="0" step="1" class="form-control form-control-sm text-right js-carton-cantidad"
                                                           value="{{ (int) $c['cantidad'] }}" {{ ! empty($c['anulado']) ? 'disabled' : '' }}>
                                                </td>
                                                <td class="text-center">
                                                    <input type="checkbox" class="js-carton-anular" {{ ! empty($c['anulado']) ? 'checked' : '' }}>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header"><strong>Conceptos de rendición</strong></div>
                            <div class="bingo-rend-recaudacion-banner">
                                <span class="text-muted d-block small">Total recaudación (base de cálculo)</span>
                                <span class="bingo-rend-recaudacion-valor" id="lbl-recaudacion">
                                    ${{ number_format((float) ($datos['calculo']['total_cartones'] ?? 0), 2, ',', '.') }}
                                </span>
                            </div>
                            <div class="card-body p-0 table-responsive">
                                <table class="table table-sm table-bordered mb-0 bingo-rend-panel" id="tabla-conceptos-rendicion">
                                    <thead>
                                        <tr>
                                            <th>Concepto</th>
                                            <th class="text-right" style="width:80px;">%</th>
                                            <th class="text-right" style="width:140px;">Monto</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-conceptos-rendicion">
                                        @foreach ($datos['conceptos'] as $concepto)
                                            @php
                                                $conceptoId = (int) $concepto['id'];
                                                $linea = $lineasPorConcepto->get($conceptoId, []);
                                                $esSaldo = ! empty($concepto['es_saldo_rendicion']);
                                                $esManual = ! $esSaldo && (($concepto['base_calculo'] ?? '') === \App\Models\Caja\Bingo\BingoConceptoRendicion::BASE_MANUAL);
                                                $signo = $esSaldo ? '' : (($concepto['signo'] ?? '') === '+' ? '+' : '−');
                                                $pct = $concepto['porcentaje'] ?? ($linea['porcentaje'] ?? null);
                                                $montoLinea = (float) ($linea['monto'] ?? 0);
                                            @endphp
                                            <tr class="js-concepto-row {{ $esSaldo ? 'bingo-rend-concepto-saldo' : ($esManual ? 'bingo-rend-concepto-manual' : 'bingo-rend-concepto-auto') }}"
                                                data-concepto-id="{{ $conceptoId }}"
                                                data-es-manual="{{ $esManual ? '1' : '0' }}"
                                                data-es-saldo="{{ $esSaldo ? '1' : '0' }}">
                                                <td>
                                                    @if ($signo !== '')
                                                        {{ $signo }}
                                                    @endif
                                                    {{ $concepto['detalle'] ?? '' }}
                                                </td>
                                                <td class="text-right js-concepto-pct">
                                                    @if (! $esSaldo && $pct !== null && (float) $pct > 0)
                                                        {{ number_format((float) $pct, 2, ',', '.') }}
                                                    @endif
                                                </td>
                                                <td class="text-right">
                                                    @if ($esSaldo)
                                                        <span class="js-concepto-saldo-rendicion">${{ number_format($montoLinea, 2, ',', '.') }}</span>
                                                    @elseif ($esManual)
                                                        <input type="number" step="0.01" min="0"
                                                               class="form-control form-control-sm text-right js-monto-manual"
                                                               data-concepto-id="{{ $conceptoId }}"
                                                               value="{{ number_format((float) ($concepto['monto_manual'] ?? 0), 2, '.', '') }}">
                                                    @else
                                                        <span class="js-concepto-monto-auto">${{ number_format($montoLinea, 2, ',', '.') }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    @if (! $datos['rendicion_presentada'])
                        <div class="border rounded p-3 mt-3 bg-light">
                            <div class="form-group mb-3">
                                <label for="rend_observacion">Observación</label>
                                <textarea class="form-control" id="rend_observacion" rows="2" maxlength="200">{{ $datos['observacion_cierre'] ?? '' }}</textarea>
                            </div>
                            @if (empty($modo_edicion))
                                <button type="button" class="btn btn-primary mr-2" id="btn-guardar-borrador-rendicion-bingo">
                                    <i class="fa fa-save"></i>
                                    Guardar borrador
                                </button>
                            @endif
                            <button type="button" class="btn btn-success" id="btn-guardar-rendicion-bingo">
                                <i class="fa fa-lock"></i>
                                @if (! empty($modo_edicion))
                                    Guardar cambios
                                @else
                                    Cerrar turno con rendición
                                @endif
                            </button>
                            <p class="text-muted small mt-2 mb-0">
                                @if (empty($modo_edicion))
                                    <strong>Guardar borrador</strong> deja el turno abierto para seguir cargando.
                                    <strong>Cerrar turno con rendición</strong> finaliza el turno (la jornada no se cierra).
                                    Después presente en Caja &rarr; Rendiciones bingo.
                                @else
                                    Presente la rendición después en Caja &rarr; Rendiciones bingo.
                                @endif
                            </p>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
