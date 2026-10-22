@php
    $retActivaOld = old('retencion_activa');
    if ($retActivaOld !== null) {
        $retActiva = (string) $retActivaOld === '1' || $retActivaOld === 1 || $retActivaOld === true;
    } else {
        $retActiva = (bool) ($data->retencion_activa ?? false);
    }
    $retModo = old('retencion_modo', $data->retencion_modo ?? 'monto');
    if (! in_array($retModo, ['monto', 'tasa_iva'], true)) {
        $retModo = 'monto';
    }
    $retNeto = old('retencion_neto_gravado', $data->retencion_neto_gravado ?? '');
    $retTasaRaw = old('retencion_tasa_iva', $data->retencion_tasa_iva ?? null);
    $retTasaNum = ($retTasaRaw !== null && $retTasaRaw !== '') ? (float) $retTasaRaw : null;
    $retIva = isset($data) ? (float) ($data->retencion_importe_iva ?? 0) : 0;
    $retLineas = isset($data) ? ($data->retenciones ?? collect()) : collect();
    $soloConsultaRet = ! empty($soloConsulta);
    $esAnticipadaRet = \App\Support\Solicitudpago\SolicitudpagoTratamientos::esAnticipada(
        old('tratamiento', $data->tratamiento ?? '')
    );
    $tasasIva = $impuesto_query ?? \App\Models\Configuracion\Impuesto::soloNacionales()->orderBy('valor')->orderBy('nombre')->get();
    $codigosGanancia = $retencionganancia_query ?? \App\Models\Compras\Retencionganancia::query()->orderBy('codigo')->orderBy('nombre')->get(['id', 'codigo', 'nombre']);
    $codigosIva = $retencioniva_query ?? \App\Models\Compras\Retencioniva::query()->orderBy('codigo')->orderBy('nombre')->get(['id', 'codigo', 'nombre']);
    $codigosSuss = $retencionsuss_query ?? \App\Models\Compras\Retencionsuss::query()->orderBy('codigo')->orderBy('nombre')->get(['id', 'codigo', 'nombre']);
    $proveedorIdRet = (int) old('proveedor_id', isset($data) ? ($data->proveedor_id ?? 0) : 0);
    $proveedorRet = null;
    if (isset($data) && is_object($data) && method_exists($data, 'relationLoaded') && $data->relationLoaded('proveedores')) {
        $proveedorRet = $data->proveedores;
    }
    if ($proveedorRet === null && $proveedorIdRet > 0) {
        $proveedorRet = \App\Models\Compras\Proveedor::query()->find($proveedorIdRet);
    }
    $codigoElegido = function (string $campo, string $tipo, string $columna) use ($retLineas, $proveedorRet): int {
        $posteado = old($campo);
        if ($posteado !== null) {
            return (int) $posteado;
        }
        $fila = $retLineas->first(function ($linea) use ($tipo, $columna) {
            return (string) $linea->tiporetencion === $tipo && (int) ($linea->{$columna} ?? 0) > 0;
        });
        if ($fila) {
            return (int) $fila->{$columna};
        }

        return (int) ($proveedorRet->{$columna} ?? 0);
    };
    $gananciaElegida = $codigoElegido('retencionganancia_id', 'G', 'retencionganancia_id');
    $ivaElegida = $codigoElegido('retencioniva_id', 'I', 'retencioniva_id');
    $sussElegida = $codigoElegido('retencionsuss_id', 'S', 'retencionsuss_id');
    $etiquetaCodigo = function ($fila): string {
        $codigo = trim((string) ($fila->codigo ?? ''));
        $nombre = trim((string) ($fila->nombre ?? ''));
        if ($codigo !== '' && ($nombre === '' || ! str_contains($nombre, $codigo))) {
            return $codigo.' — '.$nombre;
        }

        return $nombre !== '' ? $nombre : ($codigo !== '' ? $codigo : (string) $fila->id);
    };
@endphp
<div class="card card-outline card-info mt-3 mb-0 {{ $esAnticipadaRet ? '' : 'd-none' }}" id="sp-retenciones"
     data-url="{{ route('calcular_retenciones_solicitudpago') }}"
     data-codigos-url="{{ route('codigos_retencion_proveedor_solicitudpago') }}"
     data-consulta="{{ $soloConsultaRet ? '1' : '0' }}">
    <div class="card-header py-2">
        <h3 class="card-title mb-0">Retenciones de la OPA</h3>
    </div>
    <div class="card-body">
        <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="retencion_activa" name="retencion_activa" value="1"
                   @checked($retActiva) @disabled($soloConsultaRet)>
            <label class="custom-control-label" for="retencion_activa">Calcular retenciones al pagar esta solicitud</label>
        </div>
        <p class="text-muted small mt-2 mb-0">
            Opcional. Al guardar, la OPA transfiere el monto menos las retenciones
            e imputa el resto a anticipos a proveedores.
        </p>

        <div id="sp-retenciones-cuerpo" class="{{ $retActiva ? '' : 'd-none' }} border-top mt-3 pt-3">
            <div class="form-group row mb-2">
                <label class="col-lg-3 col-form-label">Neto gravado</label>
                <div class="col-lg-9 pt-1">
                    <div class="custom-control custom-radio">
                        <input type="radio" id="retencion_modo_monto" name="retencion_modo" value="monto" class="custom-control-input"
                               @checked($retModo === 'monto') @disabled($soloConsultaRet)>
                        <label class="custom-control-label" for="retencion_modo_monto">Por monto</label>
                    </div>
                    <div class="custom-control custom-radio">
                        <input type="radio" id="retencion_modo_tasa" name="retencion_modo" value="tasa_iva" class="custom-control-input"
                               @checked($retModo === 'tasa_iva') @disabled($soloConsultaRet)>
                        <label class="custom-control-label" for="retencion_modo_tasa">Por tasa de IVA, despejada del monto de la solicitud</label>
                    </div>
                </div>
            </div>

            <div class="form-group row mb-2 {{ $retModo === 'monto' ? '' : 'd-none' }}" id="sp-retencion-neto-wrap">
                <label for="retencion_neto_gravado" class="col-lg-3 col-form-label">Importe</label>
                <div class="col-lg-4">
                    <input type="text" inputmode="decimal" name="retencion_neto_gravado" id="retencion_neto_gravado"
                           class="form-control text-right js-monto-ar" value="{{ $retNeto }}" @disabled($soloConsultaRet)>
                </div>
            </div>

            <div class="form-group row mb-2 {{ $retModo === 'tasa_iva' ? '' : 'd-none' }}" id="sp-retencion-tasa-wrap">
                <label for="retencion_tasa_iva" class="col-lg-3 col-form-label">Tasa de IVA</label>
                <div class="col-lg-5">
                    <select name="retencion_tasa_iva" id="retencion_tasa_iva" class="form-control" @disabled($soloConsultaRet)>
                        <option value="">Elegir del archivo de tasas</option>
                        @foreach ($tasasIva as $tasaIva)
                            @php
                                $valorTasa = number_format((float) $tasaIva->valor, 4, '.', '');
                                $elegida = $retTasaNum !== null && abs((float) $tasaIva->valor - $retTasaNum) < 0.0001;
                            @endphp
                            <option value="{{ $valorTasa }}" data-valor="{{ $valorTasa }}" @selected($elegida)>
                                {{ $tasaIva->nombre }}
                            </option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">El monto de la solicitud incluye esta tasa.</small>
                </div>
            </div>

            <div class="form-group row mb-2 {{ $retModo === 'tasa_iva' ? '' : 'd-none' }}" id="sp-retencion-neto-calculado">
                <label for="sp-retencion-neto-calculado-valor" class="col-lg-3 col-form-label">Neto gravado</label>
                <div class="col-lg-4">
                    <input type="text" id="sp-retencion-neto-calculado-valor" class="form-control text-right" readonly tabindex="-1"
                           value="{{ $retModo === 'tasa_iva' && $retNeto !== '' && $retNeto !== null ? $retNeto : '' }}">
                    <small class="form-text text-muted" id="sp-retencion-neto-calculado-iva"></small>
                </div>
            </div>

            <div class="form-group row mb-2">
                <label class="col-lg-3 col-form-label">Códigos de retención</label>
                <div class="col-lg-9">
                    <p class="text-muted small mb-2">
                        Salen del proveedor. Confirmalos: el cálculo usa el código que quede elegido.
                        Si lo dejás vacío, usa el del proveedor.
                    </p>
                    <div class="form-row">
                        <div class="form-group col-md-4 mb-2 mb-md-0">
                            <label for="retencionganancia_id" class="small mb-1">Ganancias</label>
                            <select name="retencionganancia_id" id="retencionganancia_id" class="form-control" @disabled($soloConsultaRet)>
                                <option value="">El del proveedor</option>
                                @foreach ($codigosGanancia as $codigo)
                                    <option value="{{ $codigo->id }}" @selected($gananciaElegida === (int) $codigo->id)>{{ $etiquetaCodigo($codigo) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4 mb-2 mb-md-0">
                            <label for="retencioniva_id" class="small mb-1">IVA</label>
                            <select name="retencioniva_id" id="retencioniva_id" class="form-control" @disabled($soloConsultaRet)>
                                <option value="">El del proveedor</option>
                                @foreach ($codigosIva as $codigo)
                                    <option value="{{ $codigo->id }}" @selected($ivaElegida === (int) $codigo->id)>{{ $etiquetaCodigo($codigo) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4 mb-0">
                            <label for="retencionsuss_id" class="small mb-1">SUSS</label>
                            <select name="retencionsuss_id" id="retencionsuss_id" class="form-control" @disabled($soloConsultaRet)>
                                <option value="">El del proveedor</option>
                                @foreach ($codigosSuss as $codigo)
                                    <option value="{{ $codigo->id }}" @selected($sussElegida === (int) $codigo->id)>{{ $etiquetaCodigo($codigo) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group row mb-0">
                <div class="col-lg-9 offset-lg-3">
                    @if (! $soloConsultaRet)
                        <button type="button" class="btn btn-outline-primary btn-sm" id="sp-retenciones-calcular">
                            <i class="fa fa-calculator"></i> Calcular
                        </button>
                    @endif
                </div>
            </div>

            <div id="sp-retenciones-error" class="alert alert-danger py-2 mt-3 d-none mb-0"></div>

            <div id="sp-retenciones-resultado" class="mt-3">
                @if ($retActiva && $retLineas->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-2">
                            <thead class="thead-light">
                                <tr>
                                    <th>Concepto</th>
                                    <th>Régimen</th>
                                    <th class="text-right">Base</th>
                                    <th class="text-right">Alícuota %</th>
                                    <th class="text-right">Importe</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($retLineas as $ret)
                                    <tr>
                                        <td>{{ $ret->etiquetaTipo() }}</td>
                                        <td>{{ $ret->codigo_regimen ?: '—' }}</td>
                                        <td class="text-right">{{ number_format((float) $ret->base_calculo, 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) $ret->alicuota, 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) $ret->importe, 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="font-weight-bold">
                                    <td colspan="4" class="text-right">Total retenciones</td>
                                    <td class="text-right">{{ number_format((float) $retLineas->sum('importe'), 2, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p class="mb-0 small">
                        Neto gravado: <strong>{{ number_format((float) ($data->retencion_neto_gravado ?? 0), 2, ',', '.') }}</strong>.
                        IVA: <strong>{{ number_format($retIva, 2, ',', '.') }}</strong>.
                        A transferir:
                        <strong>{{ number_format(max(0, (float) ($data->monto ?? 0) - (float) $retLineas->sum('importe')), 2, ',', '.') }}</strong>.
                    </p>
                @elseif ($retActiva)
                    <p class="text-muted small mb-0">Con esta base no corresponde practicar retención.</p>
                @endif
            </div>
        </div>
    </div>
</div>
