@php
    use App\Models\Contable\Centrocosto;
    use App\Support\Logistica\ArticuloCatalogoLogisticaSupport;
    $uiCatalogoLogistica = ArticuloCatalogoLogisticaSupport::uiActiva();
    $datosCatalogo = $uiCatalogoLogistica
        ? ArticuloCatalogoLogisticaSupport::datosParaFormulario($producto ?? null)
        : ['ficha' => null, 'categoria' => null, 'centros' => [], 'habilitaciones' => []];
    $fichaCatalogo = $datosCatalogo['ficha'];
    $publicableCatalogo = old('articulo_catalogo_logistica_sync')
        ? (bool) old('acl_publicable')
        : (bool) ($fichaCatalogo->publicable ?? false);
    $favoritoCatalogo = old('articulo_catalogo_logistica_sync')
        ? (bool) old('acl_favorito')
        : (bool) ($fichaCatalogo->favorito ?? false);
    if (old('articulo_catalogo_logistica_sync')) {
        $categoriaCatalogoId = (int) old('acl_categoria_id', 0);
        $categoriaCatalogo = $categoriaCatalogoId > 0
            ? \App\Models\Logistica\LogisticaCatalogoCategoria::query()->find($categoriaCatalogoId)
            : null;
    } else {
        $categoriaCatalogo = $datosCatalogo['categoria'];
        $categoriaCatalogoId = (int) ($categoriaCatalogo->id ?? 0);
    }
    $centrosCatalogo = [];
    if (is_array(old('acl_cc_id'))) {
        foreach (old('acl_cc_id') as $ccId) {
            $cc = Centrocosto::query()->find((int) $ccId);
            if ($cc) {
                $centrosCatalogo[] = ['id' => (int) $cc->id, 'codigo' => $cc->codigo, 'nombre' => $cc->nombre];
            }
        }
    } else {
        $centrosCatalogo = $datosCatalogo['centros'];
    }
    $habsCatalogo = [];
    if (is_array(old('acl_hab_alcance'))) {
        foreach (old('acl_hab_alcance') as $i => $alcance) {
            $habsCatalogo[] = [
                'alcance' => $alcance,
                'rol_id' => (int) old('acl_hab_rol_id.'.$i, 0),
                'rol_nombre' => (string) old('acl_hab_rol_nombre.'.$i, ''),
                'usuario_id' => (int) old('acl_hab_usuario_id.'.$i, 0),
                'usuario_codigo' => (string) old('acl_hab_usuario_codigo.'.$i, ''),
                'usuario_nombre' => (string) old('acl_hab_usuario_nombre.'.$i, ''),
                'habilitado' => (string) old('acl_hab_habilitado.'.$i, '1'),
            ];
        }
    } else {
        $habsCatalogo = $datosCatalogo['habilitaciones'];
    }
@endphp
@if ($uiCatalogoLogistica)
<div id="tab11" class="form11 tab-content" style="display: none">
    <input type="hidden" name="articulo_catalogo_logistica_sync" value="1">
    <div class="card card-outline card-info">
        <div class="card-body">
            <p class="text-muted small">
                Publicá este artículo en el catálogo de pedidos de logística.
                Sin centros de costo, cualquier centro puede pedirlo.
                La visibilidad de ítem pisa la del rol.
            </p>
            <div class="form-group row">
                <label class="col-lg-4 control-label text-right pr-2">Publicar</label>
                <div class="col-lg-8">
                    <input type="hidden" name="acl_publicable" value="0">
                    <div class="custom-control custom-checkbox mt-2">
                        <input type="checkbox" class="custom-control-input" id="acl_publicable" name="acl_publicable" value="1" @checked($publicableCatalogo)>
                        <label class="custom-control-label" for="acl_publicable">Visible en solicitudes de insumos</label>
                    </div>
                </div>
            </div>
            <div class="form-group row">
                <label class="col-lg-4 control-label text-right pr-2">Favorito</label>
                <div class="col-lg-8">
                    <input type="hidden" name="acl_favorito" value="0">
                    <div class="custom-control custom-checkbox mt-2">
                        <input type="checkbox" class="custom-control-input" id="acl_favorito" name="acl_favorito" value="1" @checked($favoritoCatalogo)>
                        <label class="custom-control-label" for="acl_favorito">Mostrar arriba en el catálogo</label>
                    </div>
                </div>
            </div>
            <div class="form-group row tm-catalogo-categoria-campo">
                <label class="col-lg-4 control-label text-right pr-2">Categoría de catálogo</label>
                <div class="col-lg-8">
                    <div class="d-flex flex-nowrap align-items-center" style="gap: 4px;">
                        <input type="hidden" name="acl_categoria_id" id="acl_categoria_id" class="catalogo-categoria-id" value="{{ $categoriaCatalogoId ?: '' }}">
                        <button type="button" class="btn-accion-tabla consultacatalogocategoria" title="Consulta categorías (F1)">
                            <i class="fa fa-search text-primary"></i>
                        </button>
                        <input type="text" class="form-control catalogo-categoria-codigo" id="acl_categoria_codigo" value="{{ $categoriaCatalogo->codigo ?? '' }}" placeholder="Cód." autocomplete="off" style="width: 6rem;">
                        <input type="text" class="form-control catalogo-categoria-nombre" id="acl_categoria_nombre" value="{{ $categoriaCatalogo->nombre ?? '' }}" placeholder="Descripción" readonly>
                    </div>
                </div>
            </div>
            <h5 class="mt-3">Centros de costo que pueden pedirlo</h5>
            <p class="text-muted small">Grilla vacía = todos los centros.</p>
            <div id="acl-cc-lista">
                @foreach ($centrosCatalogo as $idx => $cc)
                    @include('contable.partials.campo_consulta_centrocosto', [
                        'prefix' => 'aclcc'.$idx,
                        'label' => 'Centro',
                        'layout' => 'form_row',
                        'inputName' => 'acl_cc_id[]',
                        'inputId' => 'acl_cc_'.$idx,
                        'centrocostoId' => $cc['id'],
                        'codigo' => $cc['codigo'],
                        'descripcion' => $cc['nombre'],
                        'required' => false,
                        'col_label' => 'col-lg-4 control-label text-right pr-2',
                        'col_input' => 'col-lg-8',
                    ])
                @endforeach
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm mb-3" id="acl-agregar-cc">
                <i class="fa fa-plus"></i> Agregar centro de costo
            </button>
            <h5>Quién ve este ítem</h5>
            <p class="text-muted small">Si no hay filas, manda la habilitación del rol o del usuario en la configuración. Una fila de acá pisa esa regla para este artículo.</p>
            <table class="table table-sm table-bordered">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th>Alcance</th>
                        <th>Rol o usuario</th>
                        <th>Habilitado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="acl-hab-body">
                    @foreach ($habsCatalogo as $i => $hab)
                        @include('stock.articulo.partials.fila_catalogo_habilitacion', ['hab' => $hab, 'i' => $i])
                    @endforeach
                </tbody>
            </table>
            <button type="button" class="btn btn-outline-primary btn-sm" id="acl-agregar-hab">
                <i class="fa fa-plus"></i> Agregar habilitación
            </button>
        </div>
    </div>
</div>
<template id="acl-template-cc">
    @include('contable.partials.campo_consulta_centrocosto', [
        'prefix' => 'aclcc__IDX__',
        'label' => 'Centro',
        'layout' => 'form_row',
        'inputName' => 'acl_cc_id[]',
        'inputId' => 'acl_cc___IDX__',
        'centrocostoId' => '',
        'codigo' => '',
        'descripcion' => '',
        'required' => false,
        'col_label' => 'col-lg-4 control-label text-right pr-2',
        'col_input' => 'col-lg-8',
    ])
</template>
<template id="acl-template-hab">
    @include('stock.articulo.partials.fila_catalogo_habilitacion', ['hab' => ['alcance' => 'rol', 'rol_id' => 0, 'rol_nombre' => '', 'usuario_id' => 0, 'usuario_codigo' => '', 'usuario_nombre' => '', 'habilitado' => '1'], 'i' => '__IDX__'])
</template>
@endif
