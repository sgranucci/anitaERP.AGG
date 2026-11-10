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
                <h3 class="card-title mb-0"><i class="fa fa-notes-medical"></i> Libreta sanitaria</h3>
            </div>
            <div class="card-body pb-2">
                <div class="form-group row">
                    <div class="col-lg-4"></div>
                    <div class="col-lg-8">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" name="debe_libreta_sanitaria" id="debe_libreta_sanitaria" value="1"
                                   {{ old('debe_libreta_sanitaria', $data->debe_libreta_sanitaria ?? false) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="debe_libreta_sanitaria">Debe libreta sanitaria</label>
                        </div>
                    </div>
                </div>
                <div class="form-group row mb-0">
                    <label for="fecha_vencimiento_libreta_sanitaria" class="col-lg-4 control-label">Vencimiento</label>
                    <div class="col-lg-5">
                        <input type="date" name="fecha_vencimiento_libreta_sanitaria" id="fecha_vencimiento_libreta_sanitaria" class="form-control"
                               value="{{ old('fecha_vencimiento_libreta_sanitaria', optional($data->fecha_vencimiento_libreta_sanitaria ?? null)->format('Y-m-d')) }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-outline card-info mb-3">
            <div class="card-header py-2">
                <h3 class="card-title mb-0"><i class="fa fa-sitemap"></i> Puesto y organización</h3>
            </div>
            <div class="card-body pb-2">
                @php
                    $catIdLab = (int) old('categoria_id', $data->categoria_id ?? 0);
                    $catLab = isset($data) && $data->relationLoaded('categoria') && (int) ($data->categoria->id ?? 0) === $catIdLab
                        ? $data->categoria
                        : ($catIdLab > 0 ? \App\Models\Sueldos\Categoria_Sueldos::query()->find($catIdLab) : null);
                    if ($catLab === null && isset($data) && (int) ($data->categoria_id ?? 0) === $catIdLab && $data->categoria) {
                        $catLab = $data->categoria;
                    }

                    $agIdLab = (int) old('agrupamiento_id', $data->agrupamiento_id ?? 0);
                    $agLab = $agIdLab > 0
                        ? ((isset($data) && (int) ($data->agrupamiento_id ?? 0) === $agIdLab && $data->agrupamiento)
                            ? $data->agrupamiento
                            : \App\Models\Sueldos\Agrupamiento_Sueldos::query()->find($agIdLab))
                        : null;

                    $lugIdLab = (int) old('lugartrabajo_id', $data->lugartrabajo_id ?? 0);
                    $lugLab = $lugIdLab > 0
                        ? ((isset($data) && (int) ($data->lugartrabajo_id ?? 0) === $lugIdLab && $data->lugartrabajo)
                            ? $data->lugartrabajo
                            : \App\Models\Sueldos\Lugartrabajo_Sueldos::query()->find($lugIdLab))
                        : null;

                    $ccIdLab = (int) old('centrocosto_id', $data->centrocosto_id ?? 0);
                    $ccLab = $ccIdLab > 0
                        ? ((isset($data) && (int) ($data->centrocosto_id ?? 0) === $ccIdLab && $data->centrocosto)
                            ? $data->centrocosto
                            : \App\Models\Contable\Centrocosto::query()->find($ccIdLab))
                        : null;

                    $vacIdLab = (int) old('vacacion_id', $data->vacacion_id ?? 0);
                    $vacLab = $vacIdLab > 0
                        ? ((isset($data) && (int) ($data->vacacion_id ?? 0) === $vacIdLab && $data->vacacion)
                            ? $data->vacacion
                            : \App\Models\Sueldos\Vacacion_Sueldos::query()->find($vacIdLab))
                        : null;

                    $artIdLab = (int) old('art_id', $data->art_id ?? 0);
                    $artLab = $artIdLab > 0
                        ? ((isset($data) && (int) ($data->art_id ?? 0) === $artIdLab && $data->art)
                            ? $data->art
                            : \App\Models\Sueldos\Art_Sueldos::query()->find($artIdLab))
                        : null;
                @endphp

                @include('sueldos.partials.campo_consulta_maestro_sueldos', [
                    'maestro' => 'categoria',
                    'registroId' => $catLab->id ?? $catIdLab ?: '',
                    'codigo' => $catLab->codigo ?? '',
                    'descripcion' => $catLab->descripcion ?? '',
                    'label' => 'Categoría',
                    'inputName' => 'categoria_id',
                    'inputId' => 'categoria_id',
                    'col_label' => 'col-lg-4',
                    'col_input' => 'col-lg-8',
                    'siguiente' => '#agrupamiento_id_codigo',
                    'extraAttrs' => ['origen-bases' => $catLab->origen_bases ?? ''],
                ])
                @include('sueldos.partials.campo_consulta_maestro_sueldos', [
                    'maestro' => 'agrupamiento',
                    'registroId' => $agLab->id ?? $agIdLab ?: '',
                    'codigo' => $agLab->codigo ?? '',
                    'descripcion' => $agLab->descripcion ?? '',
                    'label' => 'Agrupamiento',
                    'inputName' => 'agrupamiento_id',
                    'inputId' => 'agrupamiento_id',
                    'col_label' => 'col-lg-4',
                    'col_input' => 'col-lg-8',
                    'siguiente' => '#lugartrabajo_id_codigo',
                ])
                @include('sueldos.partials.campo_consulta_maestro_sueldos', [
                    'maestro' => 'lugartrabajo',
                    'registroId' => $lugLab->id ?? $lugIdLab ?: '',
                    'codigo' => $lugLab->codigo ?? '',
                    'descripcion' => $lugLab->nombre ?? '',
                    'label' => 'Lugar de trabajo',
                    'inputName' => 'lugartrabajo_id',
                    'inputId' => 'lugartrabajo_id',
                    'col_label' => 'col-lg-4',
                    'col_input' => 'col-lg-8',
                    'siguiente' => '#centrocosto_id_codigo',
                ])
                @include('contable.partials.campo_consulta_centrocosto', [
                    'prefix' => 'empleado_lab',
                    'layout' => 'form_row',
                    'centrocostoId' => $ccLab->id ?? $ccIdLab ?: '',
                    'codigo' => $ccLab->codigo ?? '',
                    'descripcion' => $ccLab->nombre ?? '',
                    'label' => 'Centro de costo',
                    'inputName' => 'centrocosto_id',
                    'inputId' => 'centrocosto_id',
                    'required' => false,
                    'col_label' => 'col-lg-4',
                    'col_input' => 'col-lg-8',
                    'siguiente' => '#vacacion_id_codigo',
                ])
                @include('sueldos.partials.campo_consulta_maestro_sueldos', [
                    'maestro' => 'vacacion',
                    'registroId' => $vacLab->id ?? $vacIdLab ?: '',
                    'codigo' => $vacLab->codigo ?? '',
                    'descripcion' => $vacLab->descripcion ?? '',
                    'label' => 'Vacaciones',
                    'inputName' => 'vacacion_id',
                    'inputId' => 'vacacion_id',
                    'col_label' => 'col-lg-4',
                    'col_input' => 'col-lg-8',
                    'siguiente' => '#art_id_codigo',
                ])
                @include('sueldos.partials.campo_consulta_maestro_sueldos', [
                    'maestro' => 'art',
                    'registroId' => $artLab->id ?? $artIdLab ?: '',
                    'codigo' => $artLab->codigo ?? '',
                    'descripcion' => $artLab->nombre ?? '',
                    'label' => 'ART',
                    'inputName' => 'art_id',
                    'inputId' => 'art_id',
                    'col_label' => 'col-lg-4',
                    'col_input' => 'col-lg-8',
                    'siguiente' => '#a_cargo_de',
                ])
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
                @php
                    $bancoCodigoEmp = old('banco_codigo', $data->banco_codigo ?? '');
                    $bancoModelEmp = null;
                    if ($bancoCodigoEmp !== null && (string) $bancoCodigoEmp !== '') {
                        $bancoModelEmp = app(\App\Repositories\Caja\BancoRepositoryInterface::class)
                            ->findPorCodigo($bancoCodigoEmp);
                    }
                @endphp
                <input type="hidden" name="banco_codigo" id="empleado_banco_codigo"
                       value="{{ $bancoCodigoEmp }}">
                @include('caja.partials.campo_consulta_banco', [
                    'bancoId' => $bancoModelEmp->id ?? '',
                    'codigo' => $bancoModelEmp->codigo ?? $bancoCodigoEmp,
                    'descripcion' => $bancoModelEmp->nombre ?? '',
                    'label' => 'Banco',
                    'inputName' => 'banco_consulta_id',
                    'inputId' => 'empleado_banco_id',
                    'layout' => 'form_row',
                    'required' => false,
                    'col_label' => 'col-lg-4',
                    'col_input' => 'col-lg-8',
                    'siguiente' => '#mano_obra',
                ])
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
            Período vacío = aplica a todos los períodos. Sin filas se usa la situación SIJP del legajo desde el día 1.
        </p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th style="width:70px;">Nro</th>
                        <th style="width:180px;">Período</th>
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
                                @php
                                    $revPer = (int) ($rev['periodo'] ?? 0);
                                    $revPeriodoMes = $revPer >= 200001
                                        ? sprintf('%04d-%02d', intdiv($revPer, 100), $revPer % 100)
                                        : (string) ($rev['periodo'] ?? '');
                                    if ($revPeriodoMes !== '' && ! preg_match('/^\d{4}-\d{2}$/', $revPeriodoMes)) {
                                        $revPeriodoMes = '';
                                    }
                                @endphp
                                <input type="month" name="lsd_revista[{{ $i }}][periodo]" class="form-control form-control-sm"
                                       min="2000-01" max="2099-12" title="Vacío = todos los períodos"
                                       value="{{ $revPeriodoMes }}">
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
