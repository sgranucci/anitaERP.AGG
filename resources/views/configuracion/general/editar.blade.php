@extends("theme.$theme.layout")
@section('titulo')
Configuración general del sistema
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-sliders"></i> Configuración general del sistema</h3>
            </div>
            <form action="{{ route('actualizar_configuracion_general') }}" id="form-general" class="form-horizontal" method="POST" autocomplete="off">
                @csrf
                @method('put')
                <div class="card-body">
                    <div class="alert alert-info py-2">
                        Estos valores mandan sobre los defaults de <code>config/</code> y <code>.env</code>.
                        Se usan en facturación (FCE MiPyME), POS y Libro IVA Digital.
                    </div>

                    @foreach ($grupos as $nombreGrupo => $parametros)
                        <h5 class="mt-2 mb-3">{{ $nombreGrupo }}</h5>
                        @foreach ($parametros as $parametro)
                            @php
                                $idCampo = 'param_'.$parametro['clave'];
                                $step = $parametro['tipo'] === 'entero' ? '1' : '0.01';
                            @endphp
                            <div class="form-group row">
                                <label for="{{ $idCampo }}" class="col-lg-4 control-label text-right pr-2">{{ $parametro['etiqueta'] }}</label>
                                <div class="col-lg-4">
                                    <input type="number"
                                        min="0"
                                        step="{{ $step }}"
                                        class="form-control"
                                        name="parametros[{{ $parametro['clave'] }}]"
                                        id="{{ $idCampo }}"
                                        value="{{ old('parametros.'.$parametro['clave'], $parametro['valor']) }}"
                                        required>
                                    <small class="form-text text-muted">{{ $parametro['ayuda'] }}</small>
                                </div>
                            </div>
                        @endforeach
                    @endforeach
                </div>
                <div class="card-footer">
                    @include('includes.boton-form-editar')
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
