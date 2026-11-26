@extends("theme.$theme.layout")
@section('titulo')
    Solicitud de pago SUSS por empresa
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Solicitud de pago SUSS por empresa</h3>
                <div class="card-tools">
                    <a href="{{ route('suss_config') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver a configuración
                    </a>
                </div>
            </div>

            <form method="POST" action="{{ route('guardar_suss_config_empresas') }}" id="form-suss-config-empresas">
                @csrf
                @method('PUT')
                <div class="card-body table-responsive p-0">
                    <table class="table table-sm table-bordered mb-0">
                        <thead style="background-color:#85C1E9;color:#17202A;">
                            <tr>
                                <th style="min-width:260px;">Campo</th>
                                @foreach ($empresas as $emp)
                                    <th class="text-center">{{ $emp->nombre }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $filas = [
                                    'sigla' => ['Sigla (detalle de la solicitud)', 'text', 'Ej.: BSA. Vacío = sigla derivada del nombre.'],
                                    'proveedor_codigo' => ['Proveedor (código)', 'text', 'Código de proveedor, p. ej. 1299 AFIP.'],
                                    'sector_codigo' => ['Sector (código)', 'text', 'Código de sector de solicitud, p. ej. 1 Impuestos.'],
                                    'forma_codigo' => ['Forma de pago (código)', 'text', 'Código de forma de pago, p. ej. 4 Interbanking.'],
                                    'moneda_id' => ['Moneda (id)', 'text', 'Id de moneda. 1 = PESOS.'],
                                    'concepto_codigo' => ['Concepto (código)', 'text', 'Código de concepto, p. ej. 24 AFIP- RETENCIONES SUSS.'],
                                    'centrocosto_codigo' => ['Centro de costo (código)', 'text', 'Código de centro de costo, p. ej. 97.'],
                                    'cuenta_debe_codigo' => ['Cuenta Debe (código)', 'text', 'Cuenta de retenciones SUSS de la empresa, p. ej. 214010015.'],
                                    'cuenta_haber_codigo' => ['Cuenta Haber (código)', 'text', 'Cuenta bancaria, p. ej. 111050009 Banco Macro.'],
                                ];
                            @endphp

                            @foreach ($filas as $campo => [$rotulo, $tipo, $ayuda])
                                <tr>
                                    <td>
                                        <strong>{{ $rotulo }}</strong>
                                        <div class="small text-muted">{{ $ayuda }}</div>
                                    </td>
                                    @foreach ($empresas as $emp)
                                        @php
                                            $cfg = $configs[$emp->id] ?? null;
                                            $valorDefault = match ($campo) {
                                                'sigla' => $cfg->sigla ?? '',
                                                'proveedor_codigo' => $cfg->proveedor->codigo ?? '',
                                                'sector_codigo' => $cfg->sectorSolicitudpago->codigo ?? '',
                                                'forma_codigo' => $cfg->formapagosol->codigo ?? '',
                                                'moneda_id' => $cfg->moneda_id ?? '',
                                                'concepto_codigo' => $cfg->conceptoSolicitudpago->codigo ?? '',
                                                'centrocosto_codigo' => $cfg->centrocosto->codigo ?? '',
                                                'cuenta_debe_codigo' => $cfg->cuentacontableSuss->codigo ?? '',
                                                'cuenta_haber_codigo' => $cfg->cuentacontableBanco->codigo ?? '',
                                                default => '',
                                            };
                                        @endphp
                                        <td>
                                            <input type="{{ $tipo }}" class="form-control form-control-sm"
                                                name="empresas[{{ $emp->id }}][{{ $campo }}]"
                                                value="{{ old('empresas.'.$emp->id.'.'.$campo, $valorDefault) }}"
                                                maxlength="20" autocomplete="off">
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach

                            <tr>
                                <td>
                                    <strong>Exportar régimen 748 como 754</strong>
                                    <div class="small text-muted">Transitorio hasta el alta en SIRE. Afecta el F2004 y el listado.</div>
                                </td>
                                @foreach ($empresas as $emp)
                                    @php
                                        $cfg = $configs[$emp->id] ?? null;
                                        $marcado = old('empresas.'.$emp->id.'.exportar_748', $cfg ? ($cfg->exportar_regimen_748_como_754 ? '1' : '') : '1');
                                    @endphp
                                    <td class="text-center">
                                        <input type="checkbox" name="empresas[{{ $emp->id }}][exportar_748]" value="1"
                                            @checked((string) $marcado === '1')>
                                    </td>
                                @endforeach
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> Guardar
                    </button>
                    <a href="{{ route('suss_config') }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
