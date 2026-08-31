<?php
use App\Support\Sueldos\ConceptoTipo;
use App\Support\Sueldos\RubroCostoLaboral;
?>
<div class="row">
    <div class="col-lg-6">
        <div class="form-group row">
            <label for="codigo" class="col-lg-4 col-form-label">C&oacute;digo</label>
            <div class="col-lg-4">
                @if (isset($data))
                    <input type="text" id="codigo" class="form-control" value="{{ $data->codigo }}" readonly/>
                @else
                    <input type="number" name="codigo" id="codigo" class="form-control" min="1"
                           value="{{ old('codigo') }}"
                           placeholder="Autom&aacute;tico si se deja vac&iacute;o"/>
                @endif
            </div>
        </div>
        <div class="form-group row">
            <label for="descripcion" class="col-lg-4 col-form-label requerido">Descripci&oacute;n</label>
            <div class="col-lg-8">
                <input type="text" name="descripcion" id="descripcion" class="form-control" maxlength="60" required
                       value="{{ old('descripcion', $data->descripcion ?? '') }}"/>
            </div>
        </div>
        <div class="form-group row">
            <label for="tipo" class="col-lg-4 col-form-label requerido">Tipo de concepto</label>
            <div class="col-lg-8">
                <select name="tipo" id="tipo" class="form-control" required>
                    @foreach (ConceptoTipo::TIPOS as $val => $label)
                        <option value="{{ $val }}" {{ old('tipo', $data->tipo ?? ConceptoTipo::TIPO_DEFAULT) === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="suma_a" class="col-lg-4 col-form-label">Base / acumulador</label>
            <div class="col-lg-8">
                <select name="suma_a" id="suma_a" class="form-control">
                    <option value="">— No acumula —</option>
                    @foreach (ConceptoTipo::BASES as $val => $label)
                        <option value="{{ $val }}" {{ old('suma_a', $data->suma_a ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <small class="form-text text-muted">Base sobre la que suma para c&aacute;lculos (bruto, descuentos, neto).</small>
            </div>
        </div>
        <div class="form-group row">
            <label for="momento" class="col-lg-4 col-form-label requerido">Momento de liquidaci&oacute;n</label>
            <div class="col-lg-8">
                <select name="momento" id="momento" class="form-control" required>
                    @foreach (ConceptoTipo::MOMENTOS as $val => $label)
                        <option value="{{ $val }}" {{ old('momento', $data->momento ?? ConceptoTipo::MOMENTO_DEFAULT) === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="form-group row">
            <label for="factor" class="col-lg-4 col-form-label">Factor</label>
            <div class="col-lg-4">
                <input type="number" step="0.0001" name="factor" id="factor" class="form-control"
                       value="{{ old('factor', $data->factor ?? '') }}"/>
            </div>
        </div>
        <div class="form-group row">
            <label for="mes_retroactivo" class="col-lg-4 col-form-label">Mes retroactivo</label>
            <div class="col-lg-4">
                <input type="number" name="mes_retroactivo" id="mes_retroactivo" class="form-control" min="-99" max="12"
                       value="{{ old('mes_retroactivo', $data->mes_retroactivo ?? 0) }}"/>
                <small class="form-text text-muted">0 = no es; -99 = variable.</small>
            </div>
        </div>
        @php
            $catalogoLsd = $catalogoLsd ?? [];
            $afipSel = old('concepto_afip', $data->concepto_afip ?? '');
            $flagsLsd = old('lsd_subsistemas', $data->lsd_subsistemas ?? []);
        @endphp
        <div class="form-group row">
            <label for="concepto_afip" class="col-lg-4 col-form-label text-right pr-2">Concepto AFIP (LSD)</label>
            <div class="col-lg-8">
                <select name="concepto_afip" id="concepto_afip" class="form-control">
                    <option value="">— Sin mapeo LSD —</option>
                    @foreach ($catalogoLsd as $cat)
                        <option value="{{ $cat['codigo'] }}" data-tipo="{{ $cat['tipo'] }}"
                            {{ (string) $afipSel === (string) $cat['codigo'] ? 'selected' : '' }}>
                            {{ $cat['codigo'] }} — {{ $cat['descripcion'] }}
                        </option>
                    @endforeach
                    @if ($afipSel !== '' && ! collect($catalogoLsd)->pluck('codigo')->contains($afipSel))
                        <option value="{{ $afipSel }}" selected>{{ $afipSel }} (rango libre / no catalogado)</option>
                    @endif
                </select>
                <small class="form-text text-muted">Catálogo oficial ARCA. Rangos libres (111000+, 121000+, etc.): escriba el código de 6 dígitos abajo.</small>
            </div>
        </div>
        <div class="form-group row">
            <label for="concepto_afip_libre" class="col-lg-4 col-form-label text-right pr-2">Código AFIP libre</label>
            <div class="col-lg-4">
                <input type="text" name="concepto_afip_libre" id="concepto_afip_libre" class="form-control" maxlength="6"
                       value="{{ old('concepto_afip_libre', '') }}" placeholder="Ej. 111001">
            </div>
        </div>
        <div class="form-group row">
            <label for="codigo_lsd_empleador" class="col-lg-4 col-form-label text-right pr-2">Cód. empleador LSD</label>
            <div class="col-lg-4">
                <input type="text" name="codigo_lsd_empleador" id="codigo_lsd_empleador" class="form-control" maxlength="10"
                       value="{{ old('codigo_lsd_empleador', $data->codigo_lsd_empleador ?? '') }}"
                       placeholder="Automático (código interno a 10)"/>
            </div>
        </div>
        <div class="form-group row">
            <div class="col-lg-4 col-form-label text-right pr-2">LSD</div>
            <div class="col-lg-8">
                <div class="custom-control custom-checkbox">
                    <input type="hidden" name="lsd_repetible" value="0">
                    <input type="checkbox" class="custom-control-input" name="lsd_repetible" id="lsd_repetible" value="1"
                           {{ old('lsd_repetible', $data->lsd_repetible ?? true) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="lsd_repetible">Repetible en la misma liquidación</label>
                </div>
            </div>
        </div>
        @php
            $flagsEdit = \App\Support\Sueldos\Lsd\LsdSubsistemaSupport::flagsEditables();
        @endphp
        <div class="form-group row">
            <label class="col-lg-4 col-form-label text-right pr-2">Subsistemas LSD</label>
            <div class="col-lg-8">
                <div class="row">
                    @foreach ($flagsEdit as $fl)
                        <div class="col-md-6">
                            <div class="custom-control custom-checkbox">
                                <input type="hidden" name="lsd_subsistemas[{{ $fl['clave'] }}]" value="0">
                                <input type="checkbox" class="custom-control-input" data-lsd-flag="1"
                                       name="lsd_subsistemas[{{ $fl['clave'] }}]" id="lsd_flag_{{ $fl['clave'] }}" value="1"
                                       {{ ! empty($flagsLsd[$fl['clave']]) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="lsd_flag_{{ $fl['clave'] }}">{{ $fl['etiqueta'] }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
                <small class="form-text text-muted">Remunerativo: todos en 1. Descuento: todos en 0. Se precargan al elegir el código AFIP.</small>
            </div>
        </div>
        @php
            $basesLsd = old('lsd_bases', $data->lsd_bases ?? []);
            $etiquetasBases = \App\Support\Sueldos\Lsd\LsdBases04Support::ETIQUETAS;
        @endphp
        <div class="form-group row">
            <label class="col-lg-4 col-form-label text-right pr-2">Bases registro 04</label>
            <div class="col-lg-8">
                <div class="row">
                    @foreach ($etiquetasBases as $clave => $eti)
                        <div class="col-md-6 mb-1">
                            <div class="form-row align-items-center">
                                <label class="col-7 col-form-label col-form-label-sm pr-1 mb-0" for="lsd_base_{{ $clave }}">{{ $eti }}</label>
                                <div class="col-5">
                                    <select name="lsd_bases[{{ $clave }}]" id="lsd_base_{{ $clave }}" class="form-control form-control-sm">
                                        <option value="0">—</option>
                                        <option value="1" {{ (int) ($basesLsd[$clave] ?? 0) === 1 ? 'selected' : '' }}>+1</option>
                                        <option value="-1" {{ (int) ($basesLsd[$clave] ?? 0) === -1 ? 'selected' : '' }}>−1</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <small class="form-text text-muted">
                    Suma importe (o cantidad en días/horas/adherentes) en esa columna del F.931.
                    Informativos Anita (1000, 3630, 1002, etc.) se mapean acá y no van al TXT de conceptos.
                </small>
            </div>
        </div>
        <div class="form-group row">
            <label for="rubro_costo_laboral" class="col-lg-4 col-form-label">Rubro costo laboral</label>
            <div class="col-lg-8">
                <select name="rubro_costo_laboral" id="rubro_costo_laboral" class="form-control">
                    <option value="">— (solo contribuci&oacute;n empleador) —</option>
                    @foreach (RubroCostoLaboral::ETIQUETAS as $val => $label)
                        <option value="{{ $val }}" {{ old('rubro_costo_laboral', $data->rubro_costo_laboral ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <small class="form-text text-muted">Anexo III / torta. Tipo <em>contribuci&oacute;n empleador</em>: no afecta bruto ni neto.</small>
            </div>
        </div>
        <div class="form-group row">
            <label for="unidad_medida" class="col-lg-4 col-form-label">Unidad medida</label>
            <div class="col-lg-4">
                <input type="text" name="unidad_medida" id="unidad_medida" class="form-control" maxlength="4"
                       value="{{ old('unidad_medida', $data->unidad_medida ?? '') }}"
                       placeholder="% D H …"/>
                <small class="form-text text-muted">Presentaci&oacute;n en recibo (LSD). No altera el c&aacute;lculo.</small>
            </div>
        </div>
        <div class="form-group row">
            <label for="orden" class="col-lg-4 col-form-label">Orden</label>
            <div class="col-lg-4">
                <input type="number" name="orden" id="orden" class="form-control" min="0"
                       value="{{ old('orden', $data->orden ?? 0) }}"/>
            </div>
        </div>
        <div class="form-group row">
            <div class="col-lg-4 col-form-label">Opciones</div>
            <div class="col-lg-8">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" name="va_recibo" id="va_recibo" value="1"
                           {{ old('va_recibo', $data->va_recibo ?? true) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="va_recibo">Va al recibo</label>
                </div>
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" name="activo" id="activo" value="1"
                           {{ old('activo', $data->activo ?? true) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="activo">Activo</label>
                </div>
            </div>
        </div>
    </div>
</div>

<hr>
<h5 class="mb-2">F&oacute;rmulas de c&aacute;lculo</h5>
<p class="text-muted small">Se eval&uacute;an con el parser de sueldos (variables como <code>SUELDO</code>, <code>ANTIG</code>, <code>CANTVAC</code>, <code>Bn</code>, <code>Iconcepto</code>). La <em>f&oacute;rmula</em> es el importe final; opcionalmente separe <em>cantidad</em> y <em>valor unitario</em>.</p>
<div class="form-group row">
    <label for="formula" class="col-lg-2 col-form-label">F&oacute;rmula (importe)</label>
    <div class="col-lg-10">
        <textarea name="formula" id="formula" class="form-control" rows="2" maxlength="2000">{{ old('formula', $data->formula ?? '') }}</textarea>
        <small class="form-text text-muted">En edici&oacute;n pod&eacute;s usar el <em>Debugger de f&oacute;rmulas</em> debajo del formulario.</small>
    </div>
</div>
<div class="form-group row">
    <label for="formula_cantidad" class="col-lg-2 col-form-label">F&oacute;rmula cantidad</label>
    <div class="col-lg-10">
        <textarea name="formula_cantidad" id="formula_cantidad" class="form-control" rows="2" maxlength="2000">{{ old('formula_cantidad', $data->formula_cantidad ?? '') }}</textarea>
    </div>
</div>
<div class="form-group row">
    <label for="formula_valor" class="col-lg-2 col-form-label">F&oacute;rmula valor</label>
    <div class="col-lg-10">
        <textarea name="formula_valor" id="formula_valor" class="form-control" rows="2" maxlength="2000">{{ old('formula_valor', $data->formula_valor ?? '') }}</textarea>
    </div>
</div>
<div class="form-group row">
    <label for="leyenda_recibo" class="col-lg-2 col-form-label">Leyenda recibo</label>
    <div class="col-lg-10">
        <textarea name="leyenda_recibo" id="leyenda_recibo" class="form-control" rows="2" maxlength="2000">{{ old('leyenda_recibo', $data->leyenda_recibo ?? '') }}</textarea>
    </div>
</div>

<hr>
<h5 class="mb-2">Override concepto &harr; acumuladores</h5>
<p class="text-muted small mb-2">
    Por defecto el concepto alimenta los acumuladores seg&uacute;n su <em>tipo</em>.
    Ac&aacute; puede <strong>incluir</strong> o <strong>excluir</strong> acumuladores concretos
    (ej. remunerativo que no entra en base SAC). Deje en &laquo;Autom&aacute;tico&raquo; si no hay excepci&oacute;n.
</p>
@php
    $ovOld = old('acumuladores_override', $overridesMap ?? []);
@endphp
@if (($acumuladores ?? collect())->isEmpty())
    <div class="alert alert-light border">No hay acumuladores activos. Cree acumuladores en <em>Sueldos &rarr; Acumuladores</em>.</div>
@else
<div class="table-responsive">
    <table class="table table-sm table-bordered">
        <thead style="background-color:#85C1E9;color:#17202A;">
            <tr>
                <th style="width:90px">C&oacute;digo</th>
                <th>Descripci&oacute;n</th>
                <th style="width:160px">Acci&oacute;n</th>
                <th style="width:110px">Signo</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($acumuladores as $ac)
                @php
                    $ov = $ovOld[$ac->id] ?? ['accion' => 'auto', 'signo' => 1];
                    $accion = $ov['accion'] ?? 'auto';
                    $signo = (int) ($ov['signo'] ?? 1);
                @endphp
                <tr>
                    <td><code>{{ $ac->codigo }}</code></td>
                    <td>{{ $ac->descripcion }}</td>
                    <td>
                        <select name="acumuladores_override[{{ $ac->id }}][accion]" class="form-control form-control-sm">
                            <option value="auto" {{ $accion === 'auto' ? 'selected' : '' }}>Autom&aacute;tico (por tipo)</option>
                            <option value="incluir" {{ $accion === 'incluir' ? 'selected' : '' }}>Incluir siempre</option>
                            <option value="excluir" {{ $accion === 'excluir' ? 'selected' : '' }}>Excluir siempre</option>
                        </select>
                    </td>
                    <td>
                        <select name="acumuladores_override[{{ $ac->id }}][signo]" class="form-control form-control-sm">
                            <option value="1" {{ $signo === 1 ? 'selected' : '' }}>+ Suma</option>
                            <option value="-1" {{ $signo === -1 ? 'selected' : '' }}>&minus; Resta</option>
                        </select>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
