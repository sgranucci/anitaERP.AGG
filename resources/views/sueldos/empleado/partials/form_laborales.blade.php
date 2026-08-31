{{-- Solapa Laborales: layout dos columnas con bloques temáticos --}}
<div class="row">
    {{-- ===================== Columna izquierda ===================== --}}
    <div class="col-lg-6">
        <div class="card card-outline card-info mb-3">
            <div class="card-header py-2">
                <h3 class="card-title mb-0"><i class="fa fa-calendar-check"></i> Ingreso y antigüedad</h3>
            </div>
            <div class="card-body pb-2">
                <div class="form-group row">
                    <label for="fecha_ingreso" class="col-lg-4 control-label">Fecha ingreso</label>
                    <div class="col-lg-5">
                        <input type="date" name="fecha_ingreso" id="fecha_ingreso" class="form-control"
                               value="{{ old('fecha_ingreso', optional($data->fecha_ingreso ?? null)->format('Y-m-d') ?? now()->format('Y-m-d')) }}">
                    </div>
                </div>
                <div class="form-group row mb-0">
                    <label for="antiguedad_anterior" class="col-lg-4 control-label">Antig. anterior</label>
                    <div class="col-lg-5">
                        <input type="text" name="antiguedad_anterior" id="antiguedad_anterior" class="form-control" maxlength="12"
                               placeholder="aa-mm-dd" value="{{ old('antiguedad_anterior', $data->antiguedad_anterior ?? '') }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-outline card-info mb-3">
            <div class="card-header py-2">
                <h3 class="card-title mb-0"><i class="fa fa-sitemap"></i> Puesto y organización</h3>
            </div>
            <div class="card-body pb-2">
                <div class="form-group row">
                    <label for="categoria_id" class="col-lg-4 control-label">Categoría</label>
                    <div class="col-lg-8">
                        <select name="categoria_id" id="categoria_id" class="form-control">
                            <option value="">—</option>
                            @foreach ($categorias ?? [] as $cat)
                                <option value="{{ $cat->id }}"
                                    data-origen="{{ $cat->origen_bases ?? 'T' }}"
                                    {{ (int) old('categoria_id', $data->categoria_id ?? 0) === (int) $cat->id ? 'selected' : '' }}>
                                    {{ $cat->codigo }} — {{ $cat->descripcion }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="agrupamiento_id" class="col-lg-4 control-label">Agrupamiento</label>
                    <div class="col-lg-8">
                        <select name="agrupamiento_id" id="agrupamiento_id" class="form-control">
                            <option value="">—</option>
                            @foreach ($agrupamientos ?? [] as $ag)
                                <option value="{{ $ag->id }}" {{ (int) old('agrupamiento_id', $data->agrupamiento_id ?? 0) === (int) $ag->id ? 'selected' : '' }}>
                                    {{ $ag->codigo }} — {{ $ag->descripcion }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="lugartrabajo_id" class="col-lg-4 control-label">Lugar de trabajo</label>
                    <div class="col-lg-8">
                        <select name="lugartrabajo_id" id="lugartrabajo_id" class="form-control">
                            <option value="">—</option>
                            @foreach ($lugares ?? [] as $lug)
                                <option value="{{ $lug->id }}" {{ (int) old('lugartrabajo_id', $data->lugartrabajo_id ?? 0) === (int) $lug->id ? 'selected' : '' }}>
                                    {{ $lug->codigo }} — {{ $lug->descripcion ?? $lug->nombre ?? '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="centrocosto_id" class="col-lg-4 control-label">Centro de costo</label>
                    <div class="col-lg-8">
                        <select name="centrocosto_id" id="centrocosto_id" class="form-control">
                            <option value="">—</option>
                            @foreach ($centrocostos ?? [] as $cc)
                                <option value="{{ $cc->id }}" {{ (int) old('centrocosto_id', $data->centrocosto_id ?? 0) === (int) $cc->id ? 'selected' : '' }}>
                                    {{ $cc->codigo }} — {{ $cc->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="vacacion_id" class="col-lg-4 control-label">Vacaciones</label>
                    <div class="col-lg-8">
                        <select name="vacacion_id" id="vacacion_id" class="form-control">
                            <option value="">—</option>
                            @foreach ($vacaciones ?? [] as $vac)
                                <option value="{{ $vac->id }}" {{ (int) old('vacacion_id', $data->vacacion_id ?? 0) === (int) $vac->id ? 'selected' : '' }}>
                                    {{ $vac->codigo }} — {{ $vac->descripcion }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group row mb-0">
                    <label for="art_id" class="col-lg-4 control-label">ART</label>
                    <div class="col-lg-8">
                        <select name="art_id" id="art_id" class="form-control">
                            <option value="">—</option>
                            @foreach ($arts ?? [] as $art)
                                <option value="{{ $art->id }}" {{ (int) old('art_id', $data->art_id ?? 0) === (int) $art->id ? 'selected' : '' }}>
                                    {{ $art->codigo }} — {{ $art->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-outline card-info mb-3 mb-lg-0">
            <div class="card-header py-2">
                <h3 class="card-title mb-0"><i class="fa fa-user-tie"></i> Supervisión</h3>
            </div>
            <div class="card-body pb-2">
                <div class="form-group row">
                    <label for="a_cargo_de" class="col-lg-4 control-label">A cargo de</label>
                    <div class="col-lg-8">
                        <input type="text" name="a_cargo_de" id="a_cargo_de" class="form-control" maxlength="80"
                               value="{{ old('a_cargo_de', $data->a_cargo_de ?? '') }}">
                    </div>
                </div>
                <div class="form-group row mb-0">
                    <label for="puesto_jefe" class="col-lg-4 control-label">Puesto jefe</label>
                    <div class="col-lg-8">
                        <input type="text" name="puesto_jefe" id="puesto_jefe" class="form-control" maxlength="80"
                               value="{{ old('puesto_jefe', $data->puesto_jefe ?? '') }}">
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Columna derecha ===================== --}}
    <div class="col-lg-6">
        <div class="card card-outline card-info mb-3">
            <div class="card-header py-2">
                <h3 class="card-title mb-0"><i class="fa fa-money-bill-wave"></i> Remuneración</h3>
            </div>
            <div class="card-body pb-2">
                <div class="form-group row">
                    <label for="sueldo_basico" class="col-lg-4 control-label">Sueldo básico</label>
                    <div class="col-lg-5">
                        <input type="number" step="0.0001" name="sueldo_basico" id="sueldo_basico" class="form-control text-right"
                               value="{{ old('sueldo_basico', $data->sueldo_basico ?? '') }}">
                    </div>
                </div>
                <div class="form-group row">
                    <label for="jornal_dia" class="col-lg-4 control-label">Jornal día</label>
                    <div class="col-lg-5">
                        <input type="number" step="0.0001" name="jornal_dia" id="jornal_dia" class="form-control text-right"
                               value="{{ old('jornal_dia', $data->jornal_dia ?? '') }}">
                    </div>
                </div>
                <div class="form-group row">
                    <label for="jornal_hora" class="col-lg-4 control-label">Jornal hora</label>
                    <div class="col-lg-5">
                        <input type="number" step="0.0001" name="jornal_hora" id="jornal_hora" class="form-control text-right"
                               value="{{ old('jornal_hora', $data->jornal_hora ?? '') }}">
                    </div>
                </div>
                <div class="form-group row mb-0">
                    <label for="codigo_liquidacion" class="col-lg-4 control-label">Cód. liquidación</label>
                    <div class="col-lg-5">
                        <input type="text" name="codigo_liquidacion" id="codigo_liquidacion" class="form-control" maxlength="20"
                               value="{{ old('codigo_liquidacion', $data->codigo_liquidacion ?? '') }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-outline card-info mb-3">
            <div class="card-header py-2">
                <h3 class="card-title mb-0"><i class="fa fa-university"></i> Datos bancarios</h3>
            </div>
            <div class="card-body pb-2">
                <div class="form-group row">
                    <label for="cbu" class="col-lg-4 control-label">CBU</label>
                    <div class="col-lg-8">
                        <input type="text" name="cbu" id="cbu" class="form-control" maxlength="30"
                               value="{{ old('cbu', $data->cbu ?? '') }}">
                    </div>
                </div>
                <div class="form-group row">
                    <label for="cuenta_bancaria" class="col-lg-4 control-label">Cuenta bancaria</label>
                    <div class="col-lg-8">
                        <input type="text" name="cuenta_bancaria" id="cuenta_bancaria" class="form-control" maxlength="30"
                               value="{{ old('cuenta_bancaria', $data->cuenta_bancaria ?? '') }}">
                    </div>
                </div>
                <div class="form-group row mb-0">
                    <label for="banco_codigo" class="col-lg-4 control-label">Cód. banco</label>
                    <div class="col-lg-4">
                        <input type="number" name="banco_codigo" id="banco_codigo" class="form-control"
                               value="{{ old('banco_codigo', $data->banco_codigo ?? '') }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-outline card-info mb-0">
            <div class="card-header py-2">
                <h3 class="card-title mb-0"><i class="fa fa-balance-scale"></i> Clasificación y SIJP</h3>
            </div>
            <div class="card-body pb-2">
                <div class="form-group row">
                    <label for="mano_obra" class="col-lg-4 control-label">Mano de obra</label>
                    <div class="col-lg-5">
                        <select name="mano_obra" id="mano_obra" class="form-control">
                            <option value="">—</option>
                            <option value="D" {{ old('mano_obra', $data->mano_obra ?? '') === 'D' ? 'selected' : '' }}>Directa</option>
                            <option value="I" {{ old('mano_obra', $data->mano_obra ?? '') === 'I' ? 'selected' : '' }}>Indirecta</option>
                            <option value="N" {{ old('mano_obra', $data->mano_obra ?? '') === 'N' ? 'selected' : '' }}>No computable</option>
                        </select>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="personal_contratado" class="col-lg-4 control-label">Personal</label>
                    <div class="col-lg-5">
                        <select name="personal_contratado" id="personal_contratado" class="form-control">
                            <option value="">—</option>
                            <option value="N" {{ old('personal_contratado', $data->personal_contratado ?? '') === 'N' ? 'selected' : '' }}>Propio</option>
                            <option value="S" {{ old('personal_contratado', $data->personal_contratado ?? '') === 'S' ? 'selected' : '' }}>Contratado</option>
                        </select>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="situacion_sijp" class="col-lg-4 control-label">Situación SIJP</label>
                    <div class="col-lg-3">
                        <input type="text" name="situacion_sijp" id="situacion_sijp" class="form-control" maxlength="4"
                               value="{{ old('situacion_sijp', $data->situacion_sijp ?? '') }}">
                    </div>
                    <label for="condicion_sijp" class="col-lg-2 control-label px-1">Condición</label>
                    <div class="col-lg-3">
                        <input type="text" name="condicion_sijp" id="condicion_sijp" class="form-control" maxlength="4"
                               value="{{ old('condicion_sijp', $data->condicion_sijp ?? '') }}">
                    </div>
                </div>
                <div class="form-group row">
                    <label for="modalidad_sijp" class="col-lg-4 control-label text-right pr-2">Modalidad SIJP</label>
                    <div class="col-lg-3">
                        <input type="text" name="modalidad_sijp" id="modalidad_sijp" class="form-control" maxlength="6"
                               value="{{ old('modalidad_sijp', $data->modalidad_sijp ?? '') }}">
                    </div>
                </div>
                <div class="form-group row">
                    <label for="actividad_sijp" class="col-lg-4 control-label text-right pr-2">Actividad AFIP</label>
                    <div class="col-lg-3">
                        <input type="text" name="actividad_sijp" id="actividad_sijp" class="form-control" maxlength="3"
                               value="{{ old('actividad_sijp', $data->actividad_sijp ?? '') }}">
                    </div>
                    <label for="localidad_afip" class="col-lg-2 control-label text-right pr-2">Loc.</label>
                    <div class="col-lg-3">
                        <input type="text" name="localidad_afip" id="localidad_afip" class="form-control" maxlength="2"
                               value="{{ old('localidad_afip', $data->localidad_afip ?? '') }}">
                    </div>
                </div>
                <div class="form-group row">
                    <label for="siniestrado_sijp" class="col-lg-4 control-label text-right pr-2">Siniestrado</label>
                    <div class="col-lg-2">
                        <input type="text" name="siniestrado_sijp" id="siniestrado_sijp" class="form-control" maxlength="4"
                               value="{{ old('siniestrado_sijp', $data->siniestrado_sijp ?? '') }}">
                    </div>
                    <label for="marca_reduccion_sijp" class="col-lg-3 control-label text-right pr-2">Reducción</label>
                    <div class="col-lg-3">
                        <input type="text" name="marca_reduccion_sijp" id="marca_reduccion_sijp" class="form-control" maxlength="1"
                               value="{{ old('marca_reduccion_sijp', $data->marca_reduccion_sijp ?? '') }}">
                    </div>
                </div>
                <div class="form-group row">
                    <label for="tipo_empresa_sijp" class="col-lg-4 control-label text-right pr-2">Tipo empresa SIJP</label>
                    <div class="col-lg-2">
                        <input type="text" name="tipo_empresa_sijp" id="tipo_empresa_sijp" class="form-control" maxlength="1"
                               value="{{ old('tipo_empresa_sijp', $data->tipo_empresa_sijp ?? '') }}">
                    </div>
                    <label for="cuit_agencia_eventual" class="col-lg-3 control-label text-right pr-2">CUIT agencia</label>
                    <div class="col-lg-3">
                        <input type="text" name="cuit_agencia_eventual" id="cuit_agencia_eventual" class="form-control" maxlength="13"
                               value="{{ old('cuit_agencia_eventual', $data->cuit_agencia_eventual ?? '') }}"
                               placeholder="Si modalidad 102">
                    </div>
                </div>
                <div class="form-group row mb-0">
                    <div class="col-lg-4"></div>
                    <div class="col-lg-8">
                        <div class="custom-control custom-checkbox">
                            <input type="hidden" name="lsd_legajo_principal" value="0">
                            <input type="checkbox" class="custom-control-input" name="lsd_legajo_principal" id="lsd_legajo_principal" value="1"
                                   {{ old('lsd_legajo_principal', $data->lsd_legajo_principal ?? true) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="lsd_legajo_principal">Legajo principal LSD (un 04 por CUIL)</label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="hidden" name="lsd_cct" value="0">
                            <input type="checkbox" class="custom-control-input" name="lsd_cct" id="lsd_cct" value="1"
                                   {{ old('lsd_cct', $data->lsd_cct ?? false) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="lsd_cct">Convenio colectivo (CCT)</label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="hidden" name="lsd_scvo" value="0">
                            <input type="checkbox" class="custom-control-input" name="lsd_scvo" id="lsd_scvo" value="1"
                                   {{ old('lsd_scvo', $data->lsd_scvo ?? true) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="lsd_scvo">SCVO (seguro colectivo de vida)</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@php
    $revistasForm = old('lsd_revista');
    if (! is_array($revistasForm)) {
        $revistasForm = [];
        foreach ((isset($data) ? $data->revistasLsd : collect()) as $rev) {
            $revistasForm[] = [
                'id' => $rev->id,
                'periodo' => $rev->periodo,
                'situacion' => $rev->situacion,
                'dia_inicio' => $rev->dia_inicio,
            ];
        }
    }
    while (count($revistasForm) < 3) {
        $revistasForm[] = ['id' => '', 'periodo' => '', 'situacion' => '', 'dia_inicio' => ''];
    }
    $revistasForm = array_slice($revistasForm, 0, 3);
@endphp
<div class="card card-outline card-info mt-3 mb-0">
    <div class="card-header py-2">
        <h3 class="card-title mb-0"><i class="fa fa-exchange-alt"></i> Situación de revista intramés (LSD, hasta 3)</h3>
    </div>
    <div class="card-body pb-2">
        <p class="text-muted small mb-2">
            Si el trabajador cambia de situación en el mes (egreso, licencia, reingreso), cargue código AFIP y día de inicio.
            Período AAAAMM vacío = aplica a todos los períodos. Sin filas se usa la situación SIJP del legajo desde el día 1.
        </p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th style="width:70px;">Nro</th>
                        <th style="width:140px;">Período AAAAMM</th>
                        <th>Situación AFIP</th>
                        <th style="width:120px;">Día inicio</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($revistasForm as $i => $rev)
                        <tr>
                            <td class="text-center align-middle">
                                {{ $i + 1 }}
                                <input type="hidden" name="lsd_revista[{{ $i }}][id]" value="{{ $rev['id'] ?? '' }}">
                            </td>
                            <td>
                                <input type="number" name="lsd_revista[{{ $i }}][periodo]" class="form-control form-control-sm"
                                       min="200001" max="209912" placeholder="Todos"
                                       value="{{ $rev['periodo'] ?? '' }}">
                            </td>
                            <td>
                                <input type="text" name="lsd_revista[{{ $i }}][situacion]" class="form-control form-control-sm"
                                       maxlength="2" placeholder="01"
                                       value="{{ $rev['situacion'] ?? '' }}">
                            </td>
                            <td>
                                <input type="number" name="lsd_revista[{{ $i }}][dia_inicio]" class="form-control form-control-sm"
                                       min="1" max="31" placeholder="1"
                                       value="{{ $rev['dia_inicio'] ?? '' }}">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
