@extends("theme.$theme.layout")
@section('titulo')
    Deducciones Art. 30 Ganancias
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
@endsection

@php
    $mesesNombre = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">{{ $deduccion->codigo }} — {{ $deduccion->descripcion }}</h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_ganancia_deduccion_sueldos') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('actualizar_ganancia_deduccion_sueldos', ['codigo' => $codigo]) }}"
                  id="form-deduccion-valores" method="POST" autocomplete="off">
                @csrf @method('put')
                <div class="card-body">
                    <div class="form-group row">
                        <label for="anio" class="col-lg-2 col-form-label requerido">A&ntilde;o</label>
                        <div class="col-lg-3">
                            <form method="get" action="{{ route('editar_ganancia_deduccion_sueldos', ['codigo' => $codigo]) }}" id="form-cambiar-anio" class="mb-0">
                                <select name="anio" id="anio" class="form-control" onchange="document.getElementById('form-cambiar-anio').submit();">
                                    @foreach ($aniosDisponibles as $a)
                                        <option value="{{ $a }}" {{ (int) $anio === (int) $a ? 'selected' : '' }}>{{ $a }}</option>
                                    @endforeach
                                    @if (! $aniosDisponibles->contains((int) date('Y')))
                                        <option value="{{ date('Y') }}" {{ (int) $anio === (int) date('Y') ? 'selected' : '' }}>{{ date('Y') }}</option>
                                    @endif
                                </select>
                            </form>
                            <small class="form-text text-muted">Cambiar a&ntilde;o recarga la grilla.</small>
                        </div>
                    </div>
                    <input type="hidden" name="anio" value="{{ $anio }}"/>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead style="background-color:#85C1E9;color:#17202A;">
                                <tr>
                                    <th style="width:120px">Mes</th>
                                    <th>Valor acumulado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @for ($mes = 1; $mes <= 12; $mes++)
                                <tr>
                                    <td>{{ $mesesNombre[$mes] }}</td>
                                    <td>
                                        <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                               name="valores[{{ $mes }}]"
                                               value="{{ old('valores.'.$mes, $valoresPorMes[$mes] ?? '0.00') }}"/>
                                    </td>
                                </tr>
                                @endfor
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    @if (can('actualizar-ganancia-deduccion-sueldos', false))
                        <button type="submit" class="btn botonsubmit btn-success">
                            <i class="fa fa-save"></i> Guardar valores {{ $anio }}
                        </button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
