@php
    $empresaIdValor = (int) ($empresa_id ?? 0);
    $fechaDesdeValor = $fecha_desde ?? ($fecha_jornada ?? '');
    $fechaHastaValor = $fecha_hasta ?? ($fecha_jornada ?? '');
@endphp
<div class="card card-outline card-secondary mb-4">
    <div class="card-body py-3">
        <form method="get" action="{{ route('gastronomia_informe_gerente') }}" id="form-informe-gerente">
            <div class="row align-items-end">
                <div class="form-group col-md-4 col-lg-3 mb-2 mb-md-0">
                    <label for="empresa_id" class="requerido mb-1">Empresa</label>
                    @include('includes.form-empresa-asignada-control', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $empresaIdValor,
                        'required' => true,
                        'opcion_vacia' => '— Seleccionar —',
                    ])
                </div>
                <div class="form-group col-md-3 col-lg-2 mb-2 mb-md-0">
                    <label for="fecha_desde" class="requerido mb-1">Desde (jornada)</label>
                    <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                           value="{{ $fechaDesdeValor }}" required/>
                </div>
                <div class="form-group col-md-3 col-lg-2 mb-2 mb-md-0">
                    <label for="fecha_hasta" class="requerido mb-1">Hasta (jornada)</label>
                    <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                           value="{{ $fechaHastaValor }}" required/>
                </div>
                @if ($jornadas->isNotEmpty())
                    <div class="form-group col-md-5 col-lg-3 mb-2 mb-md-0">
                        <label for="jornada_historial" class="mb-1">Atajo: jornada del historial</label>
                        <select id="jornada_historial" class="form-control" title="Copia la fecha a Desde y Hasta; no envía el formulario solo">
                            <option value="">— Elegir jornada cerrada/abierta —</option>
                            @foreach ($jornadas as $j)
                                @php
                                    $fechaHist = $j->fecha_jornada?->format('Y-m-d');
                                @endphp
                                <option value="{{ $fechaHist }}"
                                    @selected($fechaDesdeValor === $fechaHist && $fechaHastaValor === $fechaHist)>
                                    #{{ $j->numero }} · {{ $j->fecha_jornada?->format('d/m/Y') }} ({{ $j->estado }})
                                </option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">
                            Completa desde y hasta con esa fecha; después pulse «Generar informe».
                        </small>
                    </div>
                @endif
                <div class="form-group col-md-4 col-lg-2 mb-0">
                    <button type="submit" name="refrescar_cache" value="1" class="btn btn-primary btn-block"
                            title="Recalcula el informe (invalida la cache del período)">
                        <i class="fa fa-bar-chart"></i> Generar informe
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
