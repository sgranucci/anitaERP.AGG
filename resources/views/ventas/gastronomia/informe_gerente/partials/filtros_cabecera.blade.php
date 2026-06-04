@php
    $empresasDisponibles = collect($empresa_query ?? []);
    $empresaUnica = $empresasDisponibles->count() === 1;
    $empresaUnicaRegistro = $empresaUnica ? $empresasDisponibles->first() : null;
    $empresaIdValor = (int) ($empresa_id ?? ($empresaUnicaRegistro?->id ?? 0));
@endphp
<div class="card card-outline card-secondary mb-4">
    <div class="card-body py-3">
        <form method="get" action="{{ route('gastronomia_informe_gerente') }}" id="form-informe-gerente">
            <div class="row align-items-end">
                <div class="form-group col-md-4 col-lg-3 mb-2 mb-md-0">
                    <label for="empresa_id" class="requerido mb-1">Empresa</label>
                    @if ($empresaUnica && $empresaUnicaRegistro)
                        <input type="hidden" name="empresa_id" id="empresa_id" value="{{ $empresaUnicaRegistro->id }}"/>
                        <input type="text" class="form-control" readonly value="{{ $empresaUnicaRegistro->nombre }}"/>
                    @else
                        <select name="empresa_id" id="empresa_id" class="form-control" required>
                            <option value="">— Seleccionar —</option>
                            @foreach ($empresasDisponibles as $emp)
                                <option value="{{ $emp->id }}" @selected($empresaIdValor === (int) $emp->id)>{{ $emp->nombre }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
                <div class="form-group col-md-3 col-lg-2 mb-2 mb-md-0">
                    <label for="fecha_jornada" class="requerido mb-1">Fecha de jornada</label>
                    <input type="date" name="fecha_jornada" id="fecha_jornada" class="form-control"
                           value="{{ $fecha_jornada }}" required/>
                </div>
                @if ($jornadas->isNotEmpty())
                    <div class="form-group col-md-5 col-lg-4 mb-2 mb-md-0">
                        <label for="jornada_historial" class="mb-1">Atajo: jornada del historial</label>
                        <select id="jornada_historial" class="form-control" title="Copia la fecha al campo de arriba; no envía el formulario solo">
                            <option value="">— Elegir jornada cerrada/abierta —</option>
                            @foreach ($jornadas as $j)
                                <option value="{{ $j->fecha_jornada?->format('Y-m-d') }}"
                                    @selected($fecha_jornada === $j->fecha_jornada?->format('Y-m-d'))>
                                    #{{ $j->numero }} · {{ $j->fecha_jornada?->format('d/m/Y') }} ({{ $j->estado }})
                                </option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">
                            Solo completa la fecha; después pulse «Generar informe».
                        </small>
                    </div>
                @endif
                <div class="form-group col-md-4 col-lg-3 mb-0">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fa fa-bar-chart"></i> Generar informe
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
