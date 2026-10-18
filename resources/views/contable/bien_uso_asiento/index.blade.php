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
            <form method="get" action="{{ route('bien_uso_asiento') }}" id="form-bien-uso-asiento" class="mb-0">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body">
                    @php $colLabel = 'col-lg-2 control-label text-right pr-2'; $colInput = 'col-lg-4'; @endphp

                    <div class="form-group row">
                        <label for="empresa_id" class="{{ $colLabel }} requerido">Empresa</label>
                        <div class="{{ $colInput }}">
                            @include('includes.form-empresa-asignada', [
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

                    <div class="form-group row">
                        <label for="codigo_bien_desde" class="{{ $colLabel }}">Bien desde</label>
                        <div class="col-lg-2">
                            <input type="number" name="codigo_bien_desde" class="form-control"
                                   value="{{ $filtros['codigo_bien_desde'] ?? '' }}">
                        </div>
                        <label for="codigo_bien_hasta" class="col-lg-2 control-label text-right pr-2">hasta</label>
                        <div class="col-lg-2">
                            <input type="number" name="codigo_bien_hasta" class="form-control"
                                   value="{{ $filtros['codigo_bien_hasta'] ?? '' }}">
                        </div>
                    </div>

                    <p class="small text-muted">
                        Amortización usa la cuenta global <strong>Gasto de amortizaciones</strong>
                        (Contable → Cuentas automáticas). Antes conviene correr el informe de revalúo
                        con «Grabar» para alimentar amortizaciones / revalúos del ejercicio.
                    </p>

                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-search"></i> Previsualizar
                    </button>
                </div>
            </form>

            @if (!empty($consultado) && $preview)
                <div class="card-body border-top">
                    <p class="mb-2">
                        Bienes procesados: <strong>{{ $preview['bienes_procesados'] }}</strong>
                        · Debe {{ number_format($preview['total_debe'], 2, ',', '.') }}
                        · Haber {{ number_format($preview['total_haber'], 2, ',', '.') }}
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Cuenta ID</th>
                                    <th>C. costo</th>
                                    <th>D/H</th>
                                    <th class="text-right">Importe</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($preview['lineas'] as $lin)
                                    <tr>
                                        <td>{{ $lin['cuentacontable_id'] }}</td>
                                        <td>{{ $lin['centrocosto_id'] ?? '—' }}</td>
                                        <td>{{ $lin['d_h'] }}</td>
                                        <td class="text-right">{{ number_format($lin['importe'], 2, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-muted">Sin líneas.</td></tr>
                                @endforelse
                            </tbody>
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
})();
</script>
@endsection
