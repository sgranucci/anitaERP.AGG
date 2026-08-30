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

        <div class="card card-outline card-info mt-3">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-balance-scale"></i> Agentes IIBB por empresa</h3>
            </div>
            <form action="{{ route('actualizar_agentes_iibb') }}" id="form-agentes-iibb" class="form-horizontal" method="POST" autocomplete="off">
                @csrf
                @method('put')
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Tilde en qu&eacute; jurisdicciones cada empresa jur&iacute;dica percibe (ventas) o retiene (compras).
                        Las al&iacute;cuotas y m&iacute;nimos se cargan en el ABM de provincia: son del fisco, no de la empresa.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered" id="tabla-agentes-iibb">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th rowspan="2" class="align-middle">Jur.</th>
                                    <th rowspan="2" class="align-middle">Provincia</th>
                                    @foreach ($empresasIibb as $empresa)
                                        <th colspan="2" class="text-center">{{ $empresa->nombre }}</th>
                                    @endforeach
                                </tr>
                                <tr>
                                    @foreach ($empresasIibb as $empresa)
                                        <th class="text-center">Percibe</th>
                                        <th class="text-center">Retiene</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($matrizIibb as $fila)
                                    <tr>
                                        <td>{{ $fila['jurisdiccion'] }}</td>
                                        <td>{{ $fila['nombre'] }}</td>
                                        @foreach ($empresasIibb as $empresa)
                                            @php
                                                $celda = $fila['empresas'][(int) $empresa->id] ?? ['percepcion' => false, 'retencion' => false];
                                                $base = 'agentes['.$empresa->id.']['.$fila['provincia_id'].']';
                                            @endphp
                                            <td class="text-center">
                                                <input type="hidden" name="{{ $base }}[percepcion]" value="0">
                                                <input type="checkbox" name="{{ $base }}[percepcion]" value="1"
                                                    {{ ! empty($celda['percepcion']) ? 'checked' : '' }}>
                                            </td>
                                            <td class="text-center">
                                                <input type="hidden" name="{{ $base }}[retencion]" value="0">
                                                <input type="checkbox" name="{{ $base }}[retencion]" value="1"
                                                    {{ ! empty($celda['retencion']) ? 'checked' : '' }}>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> Guardar agentes IIBB
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.caja.modalconsultacuentacaja')
@endsection
