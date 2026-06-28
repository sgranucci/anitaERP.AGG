@php
    $empresaIdValor = (int) ($empresa_id ?? ($filtros['empresa_id'] ?? 0));
    $fechaJornadaValor = (string) ($fecha_jornada ?? ($filtros['fecha_jornada'] ?? ''));
@endphp
<div class="card-body border-bottom bg-light py-3">
    <div class="row align-items-end">
        <div class="form-group col-md-4 col-lg-3 mb-2 mb-md-0">
            <label for="empresa_id" class="small font-weight-bold mb-1">Empresa</label>
            @include('includes.form-empresa-asignada-control', [
                'empresa_query' => $empresa_query ?? collect(),
                'empresa_id' => $empresaIdValor,
                'required' => true,
                'permite_vacio' => ($requiere_seleccion_empresa ?? false),
                'opcion_vacia' => '— Seleccionar —',
                'select_class' => 'form-control-sm',
            ])
        </div>
        <div class="form-group col-md-3 col-lg-2 mb-2 mb-md-0">
            <label for="fecha_jornada" class="small font-weight-bold mb-1">Jornada</label>
            <input type="date"
                   name="fecha_jornada"
                   id="fecha_jornada"
                   class="form-control form-control-sm"
                   value="{{ $fechaJornadaValor }}"
                   required/>
        </div>
        @if (($jornadas ?? collect())->isNotEmpty())
            <div class="form-group col-md-5 col-lg-3 mb-2 mb-md-0">
                <label for="jornada_historial" class="small mb-1">Otra jornada</label>
                <select id="jornada_historial" class="form-control form-control-sm"
                        title="Copia la fecha al campo Jornada">
                    <option value="">— Elegir del historial —</option>
                    @foreach ($jornadas as $j)
                        <option value="{{ $j->fecha_jornada?->format('Y-m-d') }}"
                            @selected($fechaJornadaValor === $j->fecha_jornada?->format('Y-m-d'))>
                            #{{ $j->id }} · {{ $j->fecha_jornada?->format('d/m/Y') }}
                            @if ($j->estado === 'abierta')
                                (abierta)
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="form-group col-md-auto mb-2 mb-md-0">
            <button type="submit" class="btn btn-primary btn-sm" id="btn-consultar-articulos-vendidos">
                <i class="fa fa-search"></i> Consultar
            </button>
        </div>
        @if ($puede_consultar ?? false)
            <div class="col-12 col-lg-auto mb-0 ml-lg-auto">
                <small class="text-muted">
                    Todas las terminales de la empresa en la jornada indicada.
                </small>
            </div>
        @endif
    </div>
</div>
