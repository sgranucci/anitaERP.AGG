@extends("theme.$theme.layout")
@section('titulo')
    Asiento amortizaciones / revalúo
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'bien-uso-asiento-overlay',
    'tituloId' => 'bien-uso-asiento-titulo',
    'subtituloId' => 'bien-uso-asiento-subtitulo',
    'titulo' => 'Procesando…',
    'subtitulo' => 'Generando preview o asiento. No cierre la página.',
])
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if (!empty($error))
            <div class="alert alert-danger">{{ $error }}</div>
        @endif
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Asiento de amortizaciones / revalúo (Anita p-asibie)</h3>
            </div>
            <form method="get" action="{{ route('bien_uso_asiento') }}" id="form-bien-uso-asiento" class="mb-0" data-enter-campos="1">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body">
                    @php $colLabel = 'col-lg-2 control-label text-right pr-2'; $colInput = 'col-lg-4'; @endphp

                    <div class="form-group row">
                        <label for="empresa_id" class="{{ $colLabel }} requerido">Empresa</label>
                        <div class="{{ $colInput }}">
                            @include('includes.form-empresa-asignada-control', [
                                'empresa_query' => $empresa_query,
                                'empresa_id' => $filtros['empresa_id'] ?? null,
                                'solo_lectura' => false,
                            ])
                        </div>
                        <label for="tipo_operacion" class="{{ $colLabel }} requerido">Operación</label>
                        <div class="{{ $colInput }}">
                            <select name="tipo_operacion" id="tipo_operacion" class="form-control" required>
                                @foreach ($tipos as $valor => $nombre)
                                    <option value="{{ $valor }}" @selected(($filtros['tipo_operacion'] ?? '') === $valor)>{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="ejercicio" class="{{ $colLabel }} requerido">Ejercicio</label>
                        <div class="{{ $colInput }}">
                            <input type="number" name="ejercicio" id="ejercicio" class="form-control" required
                                   min="1990" max="2100" value="{{ $filtros['ejercicio'] ?? date('Y') }}">
                        </div>
                        <label for="fecha_asiento" class="{{ $colLabel }} requerido">Fecha asiento</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_asiento" id="fecha_asiento" class="form-control" required
                                   value="{{ $filtros['fecha_asiento'] ?? date('Y-m-d') }}">
                        </div>
                    </div>

                    @include('contable.partials.campo_consulta_bien_uso', [
                        'prefix' => 'desde',
                        'label' => 'Bien desde',
                        'inputId' => 'bien_uso_desde_id',
                        'codigoInputName' => 'codigo_bien_desde',
                        'bienId' => $bienDesde['id'] ?? '',
                        'codigo' => $bienDesde['codigo'] ?? ($filtros['codigo_bien_desde'] ?? ''),
                        'descripcion' => $bienDesde['descripcion'] ?? '',
                        'siguiente' => '#bien_uso_hasta_id_codigo',
                        'col_label' => $colLabel,
                        'col_input' => 'col-lg-10',
                        'ayuda' => 'Vacío = desde el primero. F1 / lupa consulta; Enter pasa a «hasta».',
                    ])
                    @include('contable.partials.campo_consulta_bien_uso', [
                        'prefix' => 'hasta',
                        'label' => 'Bien hasta',
                        'inputId' => 'bien_uso_hasta_id',
                        'codigoInputName' => 'codigo_bien_hasta',
                        'bienId' => $bienHasta['id'] ?? '',
                        'codigo' => $bienHasta['codigo'] ?? ($filtros['codigo_bien_hasta'] ?? ''),
                        'descripcion' => $bienHasta['descripcion'] ?? '',
                        'siguiente' => '#btn-previsualizar-bien-uso-asiento',
                        'col_label' => $colLabel,
                        'col_input' => 'col-lg-10',
                        'ayuda' => 'Vacío = hasta el último.',
                    ])

                    <p class="small text-muted">
                        Amortización usa la cuenta global <strong>Gasto de amortizaciones</strong>
                        (Contable → Cuentas automáticas). Antes conviene correr el informe de revalúo
                        con «Grabar» para alimentar amortizaciones / revalúos del ejercicio.
                    </p>

                    <button type="submit" class="btn btn-primary" id="btn-previsualizar-bien-uso-asiento">
                        <i class="fa fa-search"></i> Previsualizar
                    </button>
                </div>
            </form>

            @include('includes.contable.modalconsultabienuso')

            @if (!empty($consultado) && $preview)
                @php
                    $cantDebe = collect($preview['lineas'])->where('d_h', 'D')->count();
                    $cantHaber = collect($preview['lineas'])->where('d_h', 'H')->count();
                    $totalDebeTxt = number_format($preview['total_debe'], 2, ',', '.');
                    $totalHaberTxt = number_format($preview['total_haber'], 2, ',', '.');
                @endphp
                <div class="card-body border-top">
                    <p class="mb-2">
                        Bienes procesados: <strong>{{ $preview['bienes_procesados'] }}</strong>
                        · Líneas {{ count($preview['lineas']) }}
                        ({{ $cantDebe }} Debe / {{ $cantHaber }} Haber)
                        <span class="text-muted small ml-1">
                            · Totales Debe {{ $totalDebeTxt }} / Haber {{ $totalHaberTxt }}
                        </span>
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0" style="max-width:1100px;table-layout:fixed;">
                            <thead>
                                <tr style="background:#85C1E9;color:#17202A;">
                                    <th style="width:7.5rem;">Código</th>
                                    <th style="width:14rem;">Cuenta</th>
                                    <th>C. costo</th>
                                    <th class="text-right" style="width:8.5rem;">Debe</th>
                                    <th class="text-right" style="width:8.5rem;">Haber</th>
                                </tr>
                                <tr class="small" style="background:#EAF2F8;color:#5D6D7E;">
                                    <th colspan="3" class="text-right font-weight-normal py-1">Totales</th>
                                    <th class="text-right font-weight-normal py-1">{{ $totalDebeTxt }}</th>
                                    <th class="text-right font-weight-normal py-1">{{ $totalHaberTxt }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($preview['lineas'] as $lin)
                                    <tr>
                                        <td>{{ $lin['codigo_cuenta'] ?? $lin['cuentacontable_id'] }}</td>
                                        <td>{{ $lin['nombre_cuenta'] ?? '—' }}</td>
                                        <td>
                                            @if (!empty($lin['codigo_centrocosto']))
                                                {{ $lin['codigo_centrocosto'] }}
                                                @if (!empty($lin['nombre_centrocosto']))
                                                    <span class="text-muted">{{ $lin['nombre_centrocosto'] }}</span>
                                                @endif
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if (($lin['d_h'] ?? '') === 'D')
                                                {{ number_format($lin['importe'], 2, ',', '.') }}
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if (($lin['d_h'] ?? '') === 'H')
                                                {{ number_format($lin['importe'], 2, ',', '.') }}
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-muted">Sin líneas.</td></tr>
                                @endforelse
                            </tbody>
                            @if (count($preview['lineas']) > 0)
                                <tfoot>
                                    <tr style="background:#D6EAF8;color:#17202A;font-weight:600;">
                                        <td colspan="3" class="text-right">Totales</td>
                                        <td class="text-right">{{ $totalDebeTxt }}</td>
                                        <td class="text-right">{{ $totalHaberTxt }}</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                    @if (can('ejecutar-bien-uso-asiento', false) && count($preview['lineas']) > 0)
                        <form method="post" action="{{ route('confirmar_bien_uso_asiento') }}"
                              onsubmit="return confirm('¿Generar asiento definitivo?');">
                            @csrf
                            @foreach ($filtros as $k => $v)
                                @if ($v !== null && $v !== '')
                                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                                @endif
                            @endforeach
                            <button type="submit" class="btn btn-success">
                                <i class="fa fa-check"></i> Generar asiento definitivo
                            </button>
                        </form>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
<script src="{{ asset('assets/pages/scripts/contable/bien_uso/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/bien_uso/consulta.js')) }}"></script>
<script>
(function () {
    var form = document.getElementById('form-bien-uso-asiento');
    var overlay = document.getElementById('bien-uso-asiento-overlay');
    if (form && overlay) {
        form.addEventListener('submit', function () {
            overlay.classList.remove('d-none');
            overlay.style.display = 'flex';
        });
    }
    window.addEventListener('pageshow', function () {
        if (overlay) { overlay.classList.add('d-none'); overlay.style.display = ''; }
    });
    if (typeof activa_eventos_consultabienuso === 'function') {
        activa_eventos_consultabienuso();
    }
})();
</script>
@endsection
