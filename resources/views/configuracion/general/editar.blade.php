@extends("theme.$theme.layout")
@section('titulo')
Configuración general del sistema
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/configuracion/general/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/configuracion/general/form.js')) ?: time() }}" type="text/javascript"></script>
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
                            @if ($parametro['tipo'] === 'cuentacaja')
                                @php
                                    $cuentaIdOld = old('parametros.'.$parametro['clave'], $parametro['valor']);
                                    $cuenta = \App\Support\Configuracion\ParametroSistemaSupport::cuentaParaFormulario((int) $cuentaIdOld);
                                @endphp
                                @include('caja.partials.campo_consulta_cuentacaja', [
                                    'prefix' => 'fce_cbu',
                                    'layout' => 'form_row',
                                    'label' => $parametro['etiqueta'],
                                    'inputName' => 'parametros['.$parametro['clave'].']',
                                    'inputId' => 'param_'.$parametro['clave'],
                                    'cuentacajaId' => $cuentaIdOld,
                                    'codigo' => $cuenta['codigo'] ?? '',
                                    'nombre' => $cuenta['nombre'] ?? '',
                                    'col_label' => 'col-lg-4 control-label text-right pr-2',
                                    'col_input' => 'col-lg-7',
                                    'required' => false,
                                    'ayuda' => $parametro['ayuda'],
                                ])
                                <div class="form-group row">
                                    <label for="fce_cbu_preview" class="col-lg-4 control-label text-right pr-2">CBU emisor</label>
                                    <div class="col-lg-7">
                                        <input type="text" id="fce_cbu_preview" class="form-control" readonly
                                            value="{{ $cuenta['cbu'] ?? '' }}"
                                            placeholder="Se completa al elegir la cuenta">
                                        <small class="form-text text-muted">ARCA FCE dato adicional 21. Si la cuenta no tiene CBU, se usa tesmae 00000032.</small>
                                    </div>
                                </div>
                            @else
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
                            @endif
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
@include('includes.caja.modalconsultacuentacaja')
@endsection
