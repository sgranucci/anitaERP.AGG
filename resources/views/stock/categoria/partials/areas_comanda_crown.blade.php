@php
    $areasComandaPorEmpresa = $area_query ?? [];
    $empresasDisponibles = collect($empresa_query ?? []);
    $defaultEmpresaId = (int) (config('cliente.EMPRESA_DEFAULT_ID') ?? 0);

    $filasOld = old('area_comanda_ids');
    $filasEmpresaOld = old('area_comanda_empresa_ids');

    if (is_array($filasOld)) {
        $filasAreas = [];
        foreach ($filasOld as $i => $areaId) {
            $filasAreas[] = [
                'empresa_id' => (int) ($filasEmpresaOld[$i] ?? 0),
                'area_id' => (int) $areaId,
            ];
        }
    } elseif (isset($data) && $data && $data->categoriaAreasComanda) {
        $filasAreas = $data->categoriaAreasComanda
            ->map(fn ($f) => [
                'empresa_id' => (int) optional($f->areaComanda)->empresa_id,
                'area_id' => (int) $f->area_comanda_gastronomia_id,
            ])->all();
    } else {
        $filasAreas = [];
    }

    if ($filasAreas === []) {
        $filasAreas = [
            ['empresa_id' => 0, 'area_id' => 0],
        ];
    }
@endphp

<ul class="nav nav-tabs" id="tabs-categoria" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" id="tab-general-link" data-toggle="tab" href="#tab-general" role="tab">
            <i class="fa fa-info-circle"></i> Datos generales
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-areas-link" data-toggle="tab" href="#tab-areas" role="tab">
            <i class="fa fa-clipboard-list"></i> Áreas de comanda
            <span class="badge badge-secondary" id="badge-cant-areas">{{ collect($filasAreas)->filter(fn ($f) => ($f['area_id'] ?? 0) > 0)->count() }}</span>
        </a>
    </li>
</ul>

<div class="tab-content pt-3">
    <div class="tab-pane fade show active" id="tab-general" role="tabpanel">
        @include('stock.categoria.form')
    </div>

    <div class="tab-pane fade" id="tab-areas" role="tabpanel">
        <p class="text-muted small mb-2">
            Crown: igual que Anita <code>stkagr.stka_area</code>. Los artículos de esta categoría
            generan comanda en el área indicada (ALIMENTOS, BEBIDAS, KIOSCO, POSTRES…).
        </p>

        <div class="table-responsive">
            <table class="table table-bordered table-sm" id="tabla-areas-comanda"
                   data-areas-por-empresa='@json($areasComandaPorEmpresa, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)'
                   data-empresa-default-id="{{ $defaultEmpresaId }}">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th style="width: 40%;">Empresa</th>
                        <th style="width: 55%;">Área de comanda</th>
                        <th style="width: 5%;" class="text-center"></th>
                    </tr>
                </thead>
                <tbody id="tbody-areas-comanda">
                    @foreach ($filasAreas as $fila)
                        @php
                            $filaEmpresaId = (int) ($fila['empresa_id'] ?? 0);
                            $filaAreaId = (int) ($fila['area_id'] ?? 0);
                            $areasFila = collect($areasComandaPorEmpresa[$filaEmpresaId] ?? []);
                        @endphp
                        <tr class="fila-area-comanda">
                            <td class="p-1 align-middle">
                                <select class="form-control form-control-sm js-select-empresa-area" name="area_comanda_empresa_ids[]">
                                    <option value="">-- Elija empresa --</option>
                                    @foreach ($empresasDisponibles as $empresa)
                                        <option value="{{ $empresa->id }}" {{ $filaEmpresaId === (int) $empresa->id ? 'selected' : '' }}>
                                            {{ $empresa->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="p-1 align-middle">
                                <select class="form-control form-control-sm js-select-area-comanda" name="area_comanda_ids[]" data-selected="{{ $filaAreaId }}">
                                    <option value="">-- Elija área --</option>
                                    @foreach ($areasFila as $area)
                                        <option value="{{ $area['id'] }}" {{ $filaAreaId === (int) $area['id'] ? 'selected' : '' }}>
                                            {{ $area['codigo'] }} - {{ $area['nombre'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="p-1 align-middle text-center">
                                <button type="button" class="btn btn-sm btn-outline-danger js-eliminar-fila-area" title="Quitar línea">
                                    <i class="fa fa-times"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <template id="template-fila-area-comanda">
            <tr class="fila-area-comanda">
                <td class="p-1 align-middle">
                    <select class="form-control form-control-sm js-select-empresa-area" name="area_comanda_empresa_ids[]">
                        <option value="">-- Elija empresa --</option>
                        @foreach ($empresasDisponibles as $empresa)
                            <option value="{{ $empresa->id }}">{{ $empresa->nombre }}</option>
                        @endforeach
                    </select>
                </td>
                <td class="p-1 align-middle">
                    <select class="form-control form-control-sm js-select-area-comanda" name="area_comanda_ids[]" data-selected="">
                        <option value="">-- Elija área --</option>
                    </select>
                </td>
                <td class="p-1 align-middle text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger js-eliminar-fila-area" title="Quitar línea">
                        <i class="fa fa-times"></i>
                    </button>
                </td>
            </tr>
        </template>

        <button type="button" class="btn btn-sm btn-secondary mt-2" id="js-agregar-fila-area">
            <i class="fa fa-plus"></i> Agregar área
        </button>
    </div>
</div>
