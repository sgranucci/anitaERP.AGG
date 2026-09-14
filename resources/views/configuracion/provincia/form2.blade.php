@php
    // DECIMAL(22,6) pinta 6.000000; step=0.01 falla en Firefox (6 % 0.01 ≠ 0).
    $fmtNumeroInput = static function ($valor, int $decimales = 6): string {
        if ($valor === null || $valor === '') {
            return '';
        }
        $texto = number_format((float) $valor, $decimales, '.', '');
        $texto = rtrim(rtrim($texto, '0'), '.');

        return $texto === '' ? '0' : $texto;
    };
    $tasasForm = [];
    $oldCondiciones = old('condicioniibb_ids');
    if (is_array($oldCondiciones)) {
        $n = count($oldCondiciones);
        for ($i = 0; $i < $n; $i++) {
            $tasasForm[] = (object) [
                'condicioniibb_id' => old('condicioniibb_ids.'.$i),
                'tasa' => old('tasas.'.$i),
                'minimoneto' => old('minimonetos.'.$i),
                'minimopercepcion' => old('minimopercepciones.'.$i),
                'creousuario_id' => old('creousuario_tasa_ids.'.$i),
            ];
        }
    } else {
        $tasasForm = (($data ?? null)?->provincia_tasaiibbs ?? collect())->all();
    }
    $prioridadAlicuota = \App\Support\Configuracion\PercepcionIibbPrioridadAlicuotaSupport::normalizar(
        old(
            'prioridad_alicuota_percepcion',
            ($data ?? null)?->prioridad_alicuota_percepcion
                ?? \App\Support\Configuracion\PercepcionIibbPrioridadAlicuotaSupport::defaultParaJurisdiccion(
                    isset($data) && $data->jurisdiccion !== null && $data->jurisdiccion !== ''
                        ? (int) $data->jurisdiccion
                        : null
                )
        )
    );
    $prioridadPadronDescarte = \App\Support\Configuracion\PercepcionIibbPrioridadAlicuotaSupport::PADRON_DESCARTE;
    $prioridadDescartePadron = \App\Support\Configuracion\PercepcionIibbPrioridadAlicuotaSupport::DESCARTE_PADRON;
@endphp
<div class="card card-outline card-info mb-0">
    <div class="card-header py-2">
        <strong>Tasas por condición IIBB</strong>
    </div>
    <div class="card-body p-2">
        <div class="form-group row mb-3">
            <label class="col-lg-3 control-label text-right pr-2">Prioridad alícuota percepción</label>
            <div class="col-lg-9">
                <div class="custom-control custom-radio">
                    <input type="radio" id="prioridad_padron_descarte" name="prioridad_alicuota_percepcion"
                        class="custom-control-input"
                        value="{{ $prioridadPadronDescarte }}"
                        {{ $prioridadAlicuota === $prioridadPadronDescarte ? 'checked' : '' }}>
                    <label class="custom-control-label" for="prioridad_padron_descarte">
                        Padrón → tasa de descarte
                    </label>
                </div>
                <div class="custom-control custom-radio">
                    <input type="radio" id="prioridad_descarte_padron" name="prioridad_alicuota_percepcion"
                        class="custom-control-input"
                        value="{{ $prioridadDescartePadron }}"
                        {{ $prioridadAlicuota === $prioridadDescartePadron ? 'checked' : '' }}>
                    <label class="custom-control-label" for="prioridad_descarte_padron">
                        Tasa de descarte → padrón
                    </label>
                </div>
                <div class="text-muted small mt-1">
                    Define qué alícuota manda cuando hay padrón y tasa de descarte.
                    Con padrón (CABA, ARBA, Córdoba, etc.) el default es padrón primero.
                    Misiones / provincias sin padrón en Anita: descarte primero (estas tasas).
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-2" id="tasaiibb-table">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th>Condición IIBB</th>
                        <th>Tasa</th>
                        <th>Mínimo Neto</th>
                        <th>Mínimo Percepción</th>
                        <th class="text-center" style="width: 3rem;"></th>
                    </tr>
                </thead>
                <tbody id="tbody-tasaiibb-table">
                    @foreach ($tasasForm as $tasa)
                        <tr class="item-tasaiibb">
                            <td>
                                <select name="condicioniibb_ids[]" data-placeholder="Condición IIBB" class="condicioniibb_id form-control" data-fouc>
                                    <option value="">-- Seleccionar --</option>
                                    @foreach($condicioniibb_query as $value)
                                        <option value="{{ $value->id }}"
                                            @if ((int) $value->id === (int) ($tasa->condicioniibb_id ?? 0))
                                                selected
                                            @endif
                                        >{{ $value->nombre }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="number" name="tasas[]" min="0" max="100" step="any" value="{{ $fmtNumeroInput($tasa->tasa ?? '') }}" class="form-control tasa" placeholder="Tasa de percepción por defecto">
                            </td>
                            <td>
                                <input type="number" name="minimonetos[]" step="any" value="{{ $fmtNumeroInput($tasa->minimoneto ?? '', 2) }}" class="form-control minimoneto" placeholder="Mínimo neto sujeto a percepción">
                            </td>
                            <td>
                                <input type="number" name="minimopercepciones[]" step="any" value="{{ $fmtNumeroInput($tasa->minimopercepcion ?? '') }}" class="form-control minimopercepcion" placeholder="Monto mínimo de percepción">
                            </td>
                            <td class="text-center">
                                <button type="button" title="Elimina esta línea" class="btn-accion-tabla eliminar_tasaiibb tooltipsC">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
                                <input type="hidden" name="creousuario_tasa_ids[]" class="form-control creousuario_tasa_id" value="{{ $tasa->creousuario_id ?? '' }}"/>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @include('configuracion.provincia.template2')
        <div class="d-flex justify-content-end">
            <button type="button" id="agrega_renglon_tasaiibb" class="btn btn-sm btn-outline-primary">
                <i class="fa fa-plus"></i> Agrega renglón
            </button>
        </div>
    </div>
</div>
