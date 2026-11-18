@extends("theme.$theme.layout")
@section('titulo')
    Programar pagos
@endsection

@section('styles')
<style>
    .pp-resumen {
        position: sticky;
        top: 0;
        z-index: 20;
        background: #f8fafc;
        border: 1px solid #d0d7de;
        border-radius: .35rem;
        padding: .65rem .85rem;
        margin-bottom: .75rem;
        box-shadow: 0 1px 0 rgba(0,0,0,.04);
    }
    .pp-resumen .pp-metric {
        min-width: 7.5rem;
        padding: .35rem .55rem;
        border-radius: .3rem;
        background: #fff;
        border: 1px solid #e5e7eb;
    }
    .pp-resumen .pp-metric .pp-label { font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: .02em; }
    .pp-resumen .pp-metric .pp-valor { font-size: 13px; font-weight: 600; color: #111827; }
    .pp-resumen .pp-metric.pp-metric-sel { border-color: #93c5fd; background: #eff6ff; }
    .pp-resumen .pp-metric.pp-metric-urg { border-color: #fca5a5; background: #fef2f2; }
    .pp-resumen .pp-metric.pp-metric-fecha { border-color: #86efac; background: #f0fdf4; }
    .pp-resumen .pp-metric.pp-metric-prog { border-color: #c4b5fd; background: #f5f3ff; }
    .pp-chip-filtro {
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #334155;
        font-size: 12px;
        padding: .2rem .55rem;
        border-radius: 999px;
        cursor: pointer;
    }
    .pp-chip-filtro.active {
        background: #0ea5e9;
        border-color: #0284c7;
        color: #fff;
    }
    #pp-tabla { font-size: 12px; }
    #pp-tabla thead th {
        background: #85C1E9;
        color: #17202A;
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 5;
    }
    #pp-tabla tr.pp-vencido td:first-child { box-shadow: inset 3px 0 0 #dc2626; }
    #pp-tabla .pp-dias-vencido { color: #b91c1c; font-weight: 600; }
    #pp-tabla .pp-dias-avencer { color: #0369a1; }
    body:not(.pp-detalle-on) #pp-tabla .pp-col-detalle { display: none; }
    .pp-toolbar-ayuda { font-size: 12px; color: #64748b; }
    #pp-urgente-hint:not(.d-none) { display: block; }
</style>
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/proveedor/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/compras/proyeccion_pagos_programacion/programar.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/proyeccion_pagos_programacion/programar.js')) ?: time() }}"></script>
@endsection

@section('contenido')
@php
    $empresaScope = $filtros['empresa_scope'] ?? 'una';
    $empresaActual = (int) ($filtros['empresa_id'] ?? 0);
    $resumen = $resumen ?? \App\Support\Compras\ProyeccionPagosProgramacionSupport::resumenVacio();
    $urlEmpresa = function ($id) use ($filtros) {
        $q = $filtros;
        unset($q['empresa_id'], $q['empresa_scope'], $q['empresa_todas'], $q['proveedor_codigo'], $q['proveedor_nombre']);
        if ((int) ($q['proveedor_id'] ?? 0) > 0) {
            // conservar proveedor al cambiar empresa
        } else {
            unset($q['proveedor_id']);
        }
        if ($id === 'todas') {
            $q['empresa_todas'] = 1;
        } else {
            $q['empresa_id'] = $id;
        }
        if ((int) ($filtros['proveedor_id'] ?? 0) > 0) {
            $q['proveedor_id'] = (int) $filtros['proveedor_id'];
            if (($filtros['proveedor_codigo'] ?? '') !== '') {
                $q['codigoproveedor'] = $filtros['proveedor_codigo'];
            }
            if (($filtros['proveedor_nombre'] ?? '') !== '') {
                $q['nombreproveedor'] = $filtros['proveedor_nombre'];
            }
        }
        return route('programar_pagos_proveedor', array_filter($q, static fn ($v) => $v !== null && $v !== ''));
    };
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.proceso_overlay_aviso', [
            'overlayId' => 'programar-pagos-overlay',
            'tituloId' => 'programar-pagos-titulo',
            'subtituloId' => 'programar-pagos-subtitulo',
            'titulo' => 'Exportando…',
            'subtitulo' => 'Puede demorar. No cierre la página.',
        ])
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Programar pagos</h3>
                <div class="card-tools">
                    <a href="{{ route('reporte_proyeccion_pagos') }}" class="btn btn-outline-light btn-sm">Proyección</a>
                    @if (can('crear-propuesta-pago', false))
                        <a href="{{ route('crear_propuesta_pago', ['tipo_lote' => 'URGENTE_TRF']) }}" class="btn btn-outline-light btn-sm" title="Crea la propuesta con las TRF marcadas urgentes (después de Grabar)">Lote urgentes</a>
                        <a href="{{ route('crear_propuesta_pago', ['tipo_lote' => 'CHEQUE']) }}" class="btn btn-outline-light btn-sm" title="Crea la propuesta con cheques que tengan fecha de pago">Lote cheques</a>
                    @endif
                </div>
            </div>
            @if (\App\Support\Configuracion\EmpresaFiltroExternoSupport::mostrarChips())
                <div class="card-body py-2 border-bottom bg-white">
                    <div class="d-flex flex-wrap align-items-center">
                        <span class="text-muted small mr-2">Empresa</span>
                        @foreach ($empresa_query as $emp)
                            <a href="{{ $urlEmpresa($emp->id) }}" class="btn btn-sm mr-1 mb-1 {{ ($empresaScope !== 'todas' && $empresaActual === (int) $emp->id) ? 'btn-info' : 'btn-outline-info' }}">{{ $emp->nombre }}</a>
                        @endforeach
                        <a href="{{ $urlEmpresa('todas') }}" class="btn btn-sm mb-1 {{ $empresaScope === 'todas' ? 'btn-primary' : 'btn-outline-primary' }}">Todas mis empresas</a>
                    </div>
                </div>
            @endif
            <form method="get" action="{{ route('programar_pagos_proveedor') }}" class="mb-0" id="form-programar-pagos-filtro">
                <input type="hidden" name="consultar" value="1">
                @if ($empresaScope === 'todas')
                    <input type="hidden" name="empresa_todas" value="1">
                @else
                    <input type="hidden" name="empresa_id" value="{{ $empresaActual }}">
                @endif
                <div class="card-body pb-2">
                    <p class="text-muted small mb-2">
                        1) Filtrá (ideal por proveedor) · 2) Marcá filas y asigná <strong>Urgente</strong> (TRF) o <strong>Fecha de pago</strong> (cheques) ·
                        3) <strong>Grabar</strong> · 4) Recién ahí <strong>Lote urgentes / Lote cheques</strong> crea la propuesta.
                    </p>
                    @if (empty($schema_ok))
                        <div class="alert alert-warning">Falta aplicar la migración de fecha de pago en la cuenta corriente.</div>
                    @endif

                    @include('includes.compras.campo_proveedor_consulta', [
                        'proveedor_id' => ($filtros['proveedor_id'] ?? 0) > 0 ? $filtros['proveedor_id'] : '',
                        'codigo_proveedor' => $filtros['proveedor_codigo'] ?? '',
                        'nombre_proveedor' => $filtros['proveedor_nombre'] ?? '',
                        'col_label' => 'col-md-2 col-form-label',
                        'col_input' => 'col-md-10',
                        'requerido' => false,
                        'mostrar_ayuda' => true,
                    ])

                    <div class="form-row">
                        <div class="form-group col-md-2">
                            <label class="small">Vencimiento desde</label>
                            <input type="date" name="vencimiento_desde" class="form-control form-control-sm" value="{{ $filtros['vencimiento_desde'] }}">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small">Hasta</label>
                            <input type="date" name="vencimiento_hasta" class="form-control form-control-sm" value="{{ $filtros['vencimiento_hasta'] }}">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small">Medio</label>
                            <select name="medio" class="form-control form-control-sm">
                                <option value="" @selected(($filtros['medio'] ?? '') === '')>Todos</option>
                                <option value="TRF" @selected(($filtros['medio'] ?? '') === 'TRF')>Transf</option>
                                <option value="CHEQUE" @selected(($filtros['medio'] ?? '') === 'CHEQUE')>Cheque</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small">Programación</label>
                            <select name="asignacion" class="form-control form-control-sm">
                                <option value="todas" @selected(($filtros['asignacion'] ?? '') === 'todas')>Todas</option>
                                <option value="sin_fecha" @selected(($filtros['asignacion'] ?? '') === 'sin_fecha')>Sin programar</option>
                                <option value="con_fecha" @selected(($filtros['asignacion'] ?? '') === 'con_fecha')>Con fecha</option>
                                <option value="urgentes" @selected(($filtros['asignacion'] ?? '') === 'urgentes')>Urgentes</option>
                                <option value="vencidos" @selected(($filtros['asignacion'] ?? '') === 'vencidos')>Vencidos</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small">Texto</label>
                            <input type="text" name="texto" class="form-control form-control-sm" value="{{ $filtros['texto'] ?? '' }}" placeholder="Comprobante / leyenda">
                        </div>
                        <div class="form-group col-md-2 d-flex align-items-end">
                            <label class="small mb-2" title="Si está apagado, solo entra lo que vence entre Desde y Hasta">
                                <input type="checkbox" name="incluir_vencidas" value="1" @checked(!empty($filtros['incluir_vencidas']))>
                                Sumar vencidas &lt; Desde
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Consultar</button>
                    @if ((int) ($filtros['proveedor_id'] ?? 0) > 0)
                        @php
                            $qSinProv = $filtrosQuery ?? [];
                            unset($qSinProv['proveedor_id'], $qSinProv['codigoproveedor'], $qSinProv['nombreproveedor']);
                        @endphp
                        <a href="{{ route('programar_pagos_proveedor', $qSinProv) }}"
                           class="btn btn-outline-secondary btn-sm ml-1">Quitar proveedor</a>
                    @endif
                </div>
            </form>

            <div class="card-body border-top pt-2">
                <div class="pp-resumen" id="pp-resumen">
                    <div class="d-flex flex-wrap align-items-stretch" style="gap:.45rem;">
                        <div class="pp-metric">
                            <div class="pp-label">Listado</div>
                            <div class="pp-valor">{{ number_format((int) $resumen['filas'], 0, ',', '.') }} · {{ number_format((float) $resumen['total'], 2, ',', '.') }}</div>
                        </div>
                        <div class="pp-metric">
                            <div class="pp-label">Vencidos</div>
                            <div class="pp-valor text-danger">{{ (int) $resumen['vencidos_filas'] }} · {{ number_format((float) $resumen['vencidos_total'], 2, ',', '.') }}</div>
                        </div>
                        <div class="pp-metric">
                            <div class="pp-label">A vencer</div>
                            <div class="pp-valor text-info">{{ (int) $resumen['a_vencer_filas'] }} · {{ number_format((float) $resumen['a_vencer_total'], 2, ',', '.') }}</div>
                        </div>
                        <div class="pp-metric pp-metric-sel">
                            <div class="pp-label">Seleccionados</div>
                            <div class="pp-valor"><span id="pp-sel-n">0</span> · <span id="pp-sel-t">0,00</span></div>
                        </div>
                        <div class="pp-metric pp-metric-urg">
                            <div class="pp-label">Urgentes</div>
                            <div class="pp-valor"><span id="pp-urg-n">{{ (int) $resumen['urgentes_filas'] }}</span> · <span id="pp-urg-t">{{ number_format((float) $resumen['urgentes_total'], 2, ',', '.') }}</span></div>
                        </div>
                        <div class="pp-metric pp-metric-fecha">
                            <div class="pp-label">Con fecha</div>
                            <div class="pp-valor"><span id="pp-fecha-n">{{ (int) $resumen['programados_filas'] }}</span> · <span id="pp-fecha-t">{{ number_format((float) $resumen['programados_total'], 2, ',', '.') }}</span></div>
                        </div>
                        <div class="pp-metric pp-metric-prog">
                            <div class="pp-label">Programado (urg+fecha)</div>
                            <div class="pp-valor"><span id="pp-prog-n">0</span> · <span id="pp-prog-t">0,00</span></div>
                        </div>
                        @if ((int) ($resumen['bloqueadas_filas'] ?? 0) > 0)
                            <div class="pp-metric">
                                <div class="pp-label">En propuesta</div>
                                <div class="pp-valor text-muted">{{ (int) $resumen['bloqueadas_filas'] }} · {{ number_format((float) $resumen['bloqueadas_total'], 2, ',', '.') }}</div>
                            </div>
                        @endif
                    </div>
                    <div class="d-flex flex-wrap align-items-center mt-2" style="gap:.35rem;">
                        <span class="small text-muted mr-1">Ver en grilla:</span>
                        <button type="button" class="pp-chip-filtro active" data-filtro-vista="todas">Todas</button>
                        <button type="button" class="pp-chip-filtro" data-filtro-vista="vencidos">Vencidos</button>
                        <button type="button" class="pp-chip-filtro" data-filtro-vista="a_vencer">A vencer</button>
                        <button type="button" class="pp-chip-filtro" data-filtro-vista="sin_programar">Sin programar</button>
                        <button type="button" class="pp-chip-filtro" data-filtro-vista="urgentes">Urgentes</button>
                        <button type="button" class="pp-chip-filtro" data-filtro-vista="con_fecha">Con fecha</button>
                        <label class="small mb-0 ml-2">
                            <input type="checkbox" id="pp-toggle-detalle"> Mostrar detalle (leyenda / concepto / detalle pago)
                        </label>
                        <span id="pp-cambios-pendientes" class="badge badge-warning ml-auto d-none">Hay cambios sin grabar</span>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center mb-2">
                    <span class="small text-muted mr-2">Exportar urgentes del día</span>
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_programar_pagos_proveedor',
                        'queryparams' => array_merge($filtrosQuery ?? [], ['informe' => 'urgentes']),
                        'variant' => 'compact',
                    ])
                    <span class="small text-muted ml-3 mr-2">Exportar programados</span>
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_programar_pagos_proveedor',
                        'queryparams' => array_merge($filtrosQuery ?? [], ['informe' => 'programados']),
                        'variant' => 'compact',
                    ])
                </div>
                @if (!empty($truncado))
                    <div class="alert alert-warning py-2">Se muestran las primeras {{ count($filas) }} deudas. Ajuste el filtro (proveedor ayuda mucho).</div>
                @endif

                <form method="post" action="{{ route('grabar_programar_pagos_proveedor') }}" id="form-programar-pagos-grabar">
                    @csrf
                    @foreach (($filtrosQuery ?? []) as $k => $v)
                        @if ($v !== null && $v !== '')
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach

                    <div class="border rounded p-2 mb-2 bg-light">
                        <div class="form-inline flex-wrap" style="gap:.35rem;">
                            <span class="small font-weight-bold mr-1">Sobre seleccionados:</span>
                            <input type="date" id="pp-fecha-masiva" class="form-control form-control-sm" value="{{ $hoy }}" title="Fecha a aplicar a las filas marcadas">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="pp-aplicar-fecha" title="Pone esa fecha de pago y quita urgente">Aplicar fecha a todos</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="pp-copiar-vto" title="Usa la fecha de vencimiento de cada fila">Copiar vencimiento</button>
                            <button type="button" class="btn btn-outline-danger btn-sm" id="pp-marcar-urgente" title="Solo transferencias: entran al lote Urgentes">Marcar urgente (TRF)</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="pp-quitar">Quitar programación</button>
                            <button type="submit" class="btn btn-success btn-sm ml-1" id="pp-btn-grabar"><i class="fa fa-save"></i> Grabar</button>
                        </div>
                        <div class="pp-toolbar-ayuda mt-1">
                            Cheques → fecha de pago. Transferencias del día → urgente. Sin Grabar no se crea el lote.
                        </div>
                        <div id="pp-urgente-hint" class="alert alert-info py-1 px-2 mt-2 mb-0 small d-none"></div>
                    </div>

                    <div class="table-responsive" style="max-height: 70vh;">
                        <table class="table table-sm table-bordered table-hover mb-0" id="pp-tabla">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="pp-check-todas" title="Marcar visibles"></th>
                                    <th>Empr.</th>
                                    <th>N.Pro.</th>
                                    <th>Nombre</th>
                                    <th>Tip</th>
                                    <th>Comprobante</th>
                                    <th>F.Comp.</th>
                                    <th>F.Vto.</th>
                                    <th class="text-right">Días</th>
                                    <th>Condición</th>
                                    <th>M.Pago</th>
                                    <th>Mon.</th>
                                    <th>O.Compra</th>
                                    <th class="text-right">Adeudado</th>
                                    <th class="pp-col-detalle">Detalle pago</th>
                                    <th class="pp-col-detalle">Leyenda</th>
                                    <th class="pp-col-detalle">Concepto</th>
                                    <th>Urgente</th>
                                    <th>Fecha de pago</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($filas as $fila)
                                    @php
                                        $bloqueada = (int) ($fila['propuesta_abierta_id'] ?? 0) > 0;
                                        $esVencido = ($fila['estado_vto'] ?? '') === 'vencido';
                                        $dias = $fila['dias'];
                                    @endphp
                                    <tr
                                        data-id="{{ $fila['id'] }}"
                                        data-vto="{{ $fila['fecha_vto_iso'] }}"
                                        data-familia="{{ $fila['familia'] }}"
                                        data-importe="{{ $fila['total_adeudado'] }}"
                                        data-estado-vto="{{ $fila['estado_vto'] ?? 'a_vencer' }}"
                                        data-urgente-orig="{{ !empty($fila['urgente']) ? '1' : '0' }}"
                                        data-fecha-orig="{{ $fila['fecha_pago'] }}"
                                        class="{{ $bloqueada ? 'table-secondary' : '' }} {{ $esVencido ? 'pp-vencido' : '' }}"
                                        title="{{ trim(($fila['leyenda'] ?? '').' · '.($fila['detalle_concepto'] ?? '').' · '.($fila['detalle_pago'] ?? ''), ' ·') }}"
                                    >
                                        <td>
                                            <input type="checkbox" class="pp-check" {{ $bloqueada ? 'disabled' : '' }}>
                                        </td>
                                        <td>{{ $fila['empresa'] }}</td>
                                        <td>
                                            @if ((int) ($fila['proveedor_id'] ?? 0) > 0 && can('editar-proveedor', false))
                                                <a class="text-primary" target="_blank" rel="noopener"
                                                   href="{{ route('editar_proveedor', ['id' => $fila['proveedor_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">{{ $fila['proveedor_codigo'] }}</a>
                                            @else
                                                {{ $fila['proveedor_codigo'] }}
                                            @endif
                                        </td>
                                        <td>{{ $fila['proveedor_nombre'] }}</td>
                                        <td>{{ $fila['tipo'] }}</td>
                                        <td class="text-nowrap">
                                            @if ($fila['comprobante_proveedor_id'] && can('editar-comprobante-proveedor', false))
                                                <a class="text-primary" target="_blank" rel="noopener" href="{{ route('editar_comprobante_proveedor', ['id' => $fila['comprobante_proveedor_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">{{ $fila['comprobante'] }}</a>
                                            @else
                                                {{ $fila['comprobante'] }}
                                            @endif
                                        </td>
                                        <td class="text-nowrap">{{ $fila['fecha_comprobante'] }}</td>
                                        <td class="text-nowrap">{{ $fila['fecha_vto'] }}</td>
                                        <td class="text-right {{ $esVencido ? 'pp-dias-vencido' : 'pp-dias-avencer' }}">
                                            @if ($dias !== null)
                                                {{ $dias > 0 ? '+'.$dias : $dias }}
                                            @endif
                                        </td>
                                        <td>{{ $fila['condicion_pago'] }}</td>
                                        <td><strong>{{ $fila['medio_pago'] }}</strong></td>
                                        <td>{{ $fila['moneda'] }}</td>
                                        <td class="text-nowrap">
                                            @if ((int) ($fila['ordencompra_id'] ?? 0) > 0 && can('editar-ordencompra', false))
                                                <a class="text-primary" target="_blank" rel="noopener"
                                                   href="{{ route('editar_ordencompra', ['id' => $fila['ordencompra_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">{{ $fila['nro_refer'] }}</a>
                                            @else
                                                {{ $fila['nro_refer'] }}
                                            @endif
                                        </td>
                                        <td class="text-right text-nowrap">{{ number_format((float) $fila['total_adeudado'], 2, ',', '.') }}</td>
                                        <td class="pp-col-detalle">{{ $fila['detalle_pago'] }}</td>
                                        <td class="pp-col-detalle">{{ $fila['leyenda'] }}</td>
                                        <td class="pp-col-detalle">{{ $fila['detalle_concepto'] }}</td>
                                        <td class="text-center">
                                            @if ($fila['familia'] === 'TRF')
                                                <input type="checkbox" class="pp-urgente" name="urgentes[{{ $fila['id'] }}]" value="1" @checked($fila['urgente']) {{ $bloqueada ? 'disabled' : '' }}>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            <input type="date" class="form-control form-control-sm pp-fecha" name="fechas[{{ $fila['id'] }}]" value="{{ $fila['fecha_pago'] }}" {{ $bloqueada ? 'disabled' : '' }}>
                                            @if ($bloqueada)
                                                <div class="small text-muted">En propuesta #{{ $fila['propuesta_abierta_id'] }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="19" class="text-center text-muted">Sin deudas para estos filtros. Probá otro proveedor o ampliá el rango.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@include('includes.compras.modalconsultaproveedor')
@endsection
