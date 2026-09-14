@extends("theme.$theme.layout")
@section('titulo')
    Configuraci&oacute;n COT ARBA
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-10 offset-lg-1">
        @include('includes.mensaje')
        @include('includes.form-error')

        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Configuraci&oacute;n COT electr&oacute;nico ARBA</h3>
                <div class="card-tools">
                    <span class="badge badge-{{ $ambiente === 'prod' ? 'danger' : 'warning' }}">
                        Ambiente {{ strtoupper($ambiente) }}
                    </span>
                </div>
            </div>

            <form method="post" action="{{ route('actualizar_cot_configuracion') }}" id="form-cot-configuracion">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Define c&oacute;mo opera la pantalla <strong>COT electr&oacute;nico ARBA</strong>.
                        Ambos modos reutilizan el mismo env&iacute;o a ARBA y el hist&oacute;rico de sesiones.
                    </p>

                    @foreach ($opciones as $op)
                        <div class="custom-control custom-radio mb-3">
                            <input type="radio" id="modo_{{ $op['valor'] }}" name="modo"
                                class="custom-control-input" value="{{ $op['valor'] }}"
                                {{ $modo === $op['valor'] ? 'checked' : '' }} required>
                            <label class="custom-control-label" for="modo_{{ $op['valor'] }}">
                                <strong>{{ $op['etiqueta'] }}</strong>
                            </label>
                            <div class="small text-muted ml-4 pl-1">{{ $op['ayuda'] }}</div>
                        </div>
                    @endforeach

                    <div class="alert alert-info mb-0">
                        Credenciales y domicilio de origen se configuran en
                        <code>.env</code> (<code>ARBA_COT_*</code>). Use
                        <em>Probar conexi&oacute;n ARBA</em> en la pantalla operativa.
                    </div>
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-success">
                        <i class="fa fa-save"></i> Guardar
                    </button>
                    <a href="{{ route('cot_electronico') }}" class="btn btn-outline-info btn-sm ml-2">
                        <i class="fa fa-truck"></i> Ir al proceso COT
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
