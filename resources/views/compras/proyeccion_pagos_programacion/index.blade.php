@extends("theme.$theme.layout")
@section('titulo')
    Programar pagos
@endsection

@section('scripts')
<script>
(function () {
    function marcadas() {
        return Array.prototype.slice.call(document.querySelectorAll('.pp-check:checked'));
    }
    function filaDe(chk) {
        return chk.closest('tr');
    }
    var todas = document.getElementById('pp-check-todas');
    if (todas) {
        todas.addEventListener('change', function () {
            document.querySelectorAll('.pp-check').forEach(function (c) {
                if (!c.disabled) c.checked = todas.checked;
            });
        });
    }
    function aplicarFecha() {
        var fecha = document.getElementById('pp-fecha-masiva');
        if (!fecha || !fecha.value) return;
        marcadas().forEach(function (chk) {
            var tr = filaDe(chk);
            var input = tr ? tr.querySelector('.pp-fecha') : null;
            var urg = tr ? tr.querySelector('.pp-urgente') : null;
            if (input && !input.disabled) input.value = fecha.value;
            if (urg) urg.checked = false;
        });
    }
    function copiarVencimiento() {
        marcadas().forEach(function (chk) {
            var tr = filaDe(chk);
            var input = tr ? tr.querySelector('.pp-fecha') : null;
            var urg = tr ? tr.querySelector('.pp-urgente') : null;
            if (input && !input.disabled && tr.dataset.vto) input.value = tr.dataset.vto;
            if (urg) urg.checked = false;
        });
    }
    function marcarUrgentes() {
        marcadas().forEach(function (chk) {
            var tr = filaDe(chk);
            if (!tr || tr.dataset.familia !== 'TRF') return;
            var urg = tr.querySelector('.pp-urgente');
            var input = tr.querySelector('.pp-fecha');
            if (urg && !urg.disabled) urg.checked = true;
            if (input) input.value = '';
        });
    }
    function quitarProgramacion() {
        marcadas().forEach(function (chk) {
            var tr = filaDe(chk);
            var input = tr ? tr.querySelector('.pp-fecha') : null;
            var urg = tr ? tr.querySelector('.pp-urgente') : null;
            if (input && !input.disabled) input.value = '';
            if (urg && !urg.disabled) urg.checked = false;
        });
    }
    var bFecha = document.getElementById('pp-aplicar-fecha');
    var bVto = document.getElementById('pp-copiar-vto');
    var bUrg = document.getElementById('pp-marcar-urgente');
    var bQuitar = document.getElementById('pp-quitar');
    if (bFecha) bFecha.addEventListener('click', aplicarFecha);
    if (bVto) bVto.addEventListener('click', copiarVencimiento);
    if (bUrg) bUrg.addEventListener('click', marcarUrgentes);
    if (bQuitar) bQuitar.addEventListener('click', quitarProgramacion);

    var overlay = document.getElementById('programar-pagos-overlay');
    function mostrar(titulo) {
        if (!overlay) return;
        var t = document.getElementById('programar-pagos-titulo');
        if (t && titulo) t.textContent = titulo;
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
    }
    function ocultar() {
        if (!overlay) return;
        overlay.classList.add('d-none');
        overlay.style.display = '';
    }
    document.querySelectorAll('a[href*="listar-programar-pagos"]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            mostrar('Exportando…');
            fetch(a.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) {
                    if (!res.ok) throw new Error('No se pudo exportar');
                    var nombre = 'programar_pagos';
                    var disp = res.headers.get('Content-Disposition') || '';
                    var m = /filename=\"?([^\";]+)\"?/i.exec(disp);
                    if (m) nombre = m[1];
                    return res.blob().then(function (blob) { return { blob: blob, nombre: nombre }; });
                })
                .then(function (pack) {
                    var url = URL.createObjectURL(pack.blob);
                    var link = document.createElement('a');
                    link.href = url;
                    link.download = pack.nombre;
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    URL.revokeObjectURL(url);
                    ocultar();
                })
                .catch(function () { ocultar(); });
        });
    });
    window.addEventListener('pageshow', ocultar);
})();
</script>
@endsection

@section('contenido')
@php
    $empresaScope = $filtros['empresa_scope'] ?? 'una';
    $empresaActual = (int) ($filtros['empresa_id'] ?? 0);
    $urlEmpresa = function ($id) use ($filtros) {
        $q = $filtros;
        unset($q['empresa_id'], $q['empresa_scope'], $q['empresa_todas']);
        if ($id === 'todas') {
            $q['empresa_todas'] = 1;
        } else {
            $q['empresa_id'] = $id;
        }
        return route('programar_pagos_proveedor', $q);
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
                        <a href="{{ route('crear_propuesta_pago', ['tipo_lote' => 'URGENTE_TRF']) }}" class="btn btn-outline-light btn-sm">Lote urgentes</a>
                        <a href="{{ route('crear_propuesta_pago', ['tipo_lote' => 'CHEQUE']) }}" class="btn btn-outline-light btn-sm">Lote cheques</a>
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
            <form method="get" action="{{ route('programar_pagos_proveedor') }}" class="mb-0">
                <input type="hidden" name="consultar" value="1">
                @if ($empresaScope === 'todas')
                    <input type="hidden" name="empresa_todas" value="1">
                @else
                    <input type="hidden" name="empresa_id" value="{{ $empresaActual }}">
                @endif
                <div class="card-body pb-2">
                    <p class="text-muted small mb-2">
                        Misma deuda que la proyección. Transferencias vencidas: marcar <strong>Urgente</strong> (entran al lote Urgentes).
                        Cheques: cargar <strong>Fecha de pago</strong> (entran al lote Cheques de ese día).
                    </p>
                    @if (empty($schema_ok))
                        <div class="alert alert-warning">Falta aplicar la migración de fecha de pago en la cuenta corriente.</div>
                    @endif
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
                            <input type="text" name="texto" class="form-control form-control-sm" value="{{ $filtros['texto'] ?? '' }}" placeholder="Proveedor o comprobante">
                        </div>
                        <div class="form-group col-md-2 d-flex align-items-end">
                            <label class="small mb-2">
                                <input type="checkbox" name="incluir_vencidas" value="1" @checked(!empty($filtros['incluir_vencidas']))>
                                Incluir vencidas
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Consultar</button>
                </div>
            </form>
            <div class="card-body border-top pt-2">
                <div class="d-flex flex-wrap align-items-center mb-2">
                    <span class="small text-muted mr-2">Urgentes del día</span>
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_programar_pagos',
                        'queryparams' => array_merge($filtrosQuery ?? [], ['informe' => 'urgentes']),
                        'variant' => 'compact',
                    ])
                    <span class="small text-muted ml-3 mr-2">Programados</span>
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_programar_pagos',
                        'queryparams' => array_merge($filtrosQuery ?? [], ['informe' => 'programados']),
                        'variant' => 'compact',
                    ])
                    <span class="ml-auto small">{{ count($filas) }} filas · {{ number_format($total, 2, ',', '.') }}</span>
                </div>
                @if (!empty($truncado))
                    <div class="alert alert-warning py-2">Se muestran las primeras {{ count($filas) }} deudas. Ajuste el filtro.</div>
                @endif
                <form method="post" action="{{ route('grabar_programar_pagos_proveedor') }}">
                    @csrf
                    @foreach (($filtrosQuery ?? []) as $k => $v)
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endforeach
                    <div class="form-inline mb-2">
                        <input type="date" id="pp-fecha-masiva" class="form-control form-control-sm mr-1" value="{{ $hoy }}">
                        <button type="button" class="btn btn-outline-primary btn-sm mr-1" id="pp-aplicar-fecha">Aplicar fecha</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm mr-1" id="pp-copiar-vto">Copiar vencimiento</button>
                        <button type="button" class="btn btn-outline-danger btn-sm mr-1" id="pp-marcar-urgente">Marcar urgente</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm mr-2" id="pp-quitar">Quitar programación</button>
                        <button type="submit" class="btn btn-success btn-sm"><i class="fa fa-save"></i> Grabar</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover" style="font-size:12px;">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th><input type="checkbox" id="pp-check-todas" title="Marcar página"></th>
                                    <th>Empr.</th>
                                    <th>N.Pro.</th>
                                    <th>Nombre</th>
                                    <th>Tip</th>
                                    <th>Comprobante</th>
                                    <th>F.Vto.</th>
                                    <th>M.Pago</th>
                                    <th>Detalle</th>
                                    <th>Leyenda</th>
                                    <th>Concepto</th>
                                    <th class="text-right">Adeudado</th>
                                    <th>Urgente</th>
                                    <th>Fecha de pago</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($filas as $fila)
                                    @php $bloqueada = (int) ($fila['propuesta_abierta_id'] ?? 0) > 0; @endphp
                                    <tr data-vto="{{ $fila['fecha_vto_iso'] }}" data-familia="{{ $fila['familia'] }}" class="{{ $bloqueada ? 'table-secondary' : '' }}">
                                        <td>
                                            <input type="checkbox" class="pp-check" {{ $bloqueada ? 'disabled' : '' }}>
                                        </td>
                                        <td>{{ $fila['empresa'] }}</td>
                                        <td>{{ $fila['proveedor_codigo'] }}</td>
                                        <td>{{ $fila['proveedor_nombre'] }}</td>
                                        <td>{{ $fila['tipo'] }}</td>
                                        <td class="text-nowrap">
                                            @if ($fila['comprobante_proveedor_id'] && can('editar-comprobante-proveedor', false))
                                                <a class="text-primary" target="_blank" rel="noopener" href="{{ route('editar_comprobante_proveedor', ['id' => $fila['comprobante_proveedor_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">{{ $fila['comprobante'] }}</a>
                                            @else
                                                {{ $fila['comprobante'] }}
                                            @endif
                                        </td>
                                        <td>{{ $fila['fecha_vto'] }}</td>
                                        <td><strong>{{ $fila['medio_pago'] }}</strong></td>
                                        <td>{{ $fila['detalle_pago'] }}</td>
                                        <td>{{ $fila['leyenda'] }}</td>
                                        <td>{{ $fila['detalle_concepto'] }}</td>
                                        <td class="text-right">{{ number_format((float) $fila['total_adeudado'], 2, ',', '.') }}</td>
                                        <td class="text-center">
                                            @if ($fila['familia'] === 'TRF')
                                                <input type="checkbox" class="pp-urgente" name="urgentes[{{ $fila['id'] }}]" value="1" @checked($fila['urgente']) {{ $bloqueada ? 'disabled' : '' }}>
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
                                    <tr><td colspan="14" class="text-center text-muted">Sin deudas para estos filtros.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
