@extends("theme.$theme.layout")
@section('titulo')
    Posición financiera
@endsection

@section('scripts')
<script>
(function () {
    var form = document.getElementById('form-posicion-financiera');
    if (form) {
        form.addEventListener('submit', function () {
            var btn = document.getElementById('btn-consultar');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Procesando…';
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
                <h3 class="card-title">Posición financiera</h3>
                <div class="card-tools">
                    <a href="{{ route('posicion_financiera') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('posicion_financiera') }}" id="form-posicion-financiera" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Resumen de posición financiera del mes (bingo, gastronomía, estacionamiento, máquinas, medios y egresos).
                        No incluye el Estado de flujo mensual (EFE) completo de Contable.
                    </p>

                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $filtros['empresa_id'] ?? null,
                        'required' => true,
                        'col_label' => 'col-lg-2',
                        'col_input' => 'col-lg-4',
                    ])

                    <div class="form-group row mb-0">
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
            @if (! empty($errores_bridge))
                <div class="alert alert-warning">
                    <strong>Avisos del bridge Anita:</strong>
                    <ul class="mb-0">
                        @foreach ($errores_bridge as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card card-outline card-secondary">
                <div class="card-header">
                    <h3 class="card-title">
                        Resultado
                        @if (($periodo_texto ?? '') !== '')
                            <small class="text-muted">({{ $periodo_texto }}{{ $empresa ? ' — '.$empresa->nombre : '' }})</small>
                        @endif
                    </h3>
                    <div class="card-tools">
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'listar_posicion_financiera',
                            'queryparams' => $filtrosQuery ?? [],
                        ])
                    </div>
                </div>
                <div class="card-body table-responsive p-0">
                    @php
                        $esTotal = static function (string $etiqueta): bool {
                            $e = mb_strtolower(trim($etiqueta));

                            return str_starts_with($e, 'total')
                                || in_array($e, ['saldo inicial', 'saldo final'], true);
                        };
                    @endphp
                    <table class="table table-sm table-bordered table-hover mb-0" id="tabla-paginada">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th style="width:70%;">Concepto</th>
                                <th class="text-right" style="width:30%;">Importe</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($filas as $fila)
                                @php
                                    $etiqueta = (string) ($fila['etiqueta'] ?? '');
                                    $valor = (float) ($fila['valor'] ?? 0);
                                    $resaltar = $esTotal($etiqueta);
                                @endphp
                                <tr @class(['font-weight-bold' => $resaltar, 'table-light' => $resaltar])>
                                    <td>{{ $etiqueta }}</td>
                                    <td class="text-right">{{ number_format($valor, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="text-center text-muted py-4">Sin datos para el período.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if (count($filas) > 0)
                    <div class="card-footer clearfix small text-muted">
                        {{ count($filas) }} conceptos
                        @if ($saldo_inicial !== null)
                            · Saldo inicial {{ number_format((float) $saldo_inicial, 2, ',', '.') }}
                        @endif
                        @if ($saldo_final !== null)
                            · Saldo final {{ number_format((float) $saldo_final, 2, ',', '.') }}
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection
