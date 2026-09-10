@include('includes.tabs-activas-estilos')
<div class="tabs-activas px-2 pt-2">
    <ul class="nav nav-tabs flex-wrap" role="tablist">
        <li class="nav-item">
            <button type="button" id="oc-boton-principal" class="nav-link active oc-tab-solapa" role="tab">
                <i class="fa fa-info-circle"></i> Datos principales
            </button>
        </li>
        <li class="nav-item">
            <button type="button" id="oc-boton-articulos" class="nav-link oc-tab-solapa" role="tab">
                <i class="fa fa-cubes"></i> Artículos
            </button>
        </li>
        <li class="nav-item">
            <button type="button" id="oc-boton-comprobantes" class="nav-link oc-tab-solapa" role="tab">
                <i class="fa fa-file-text-o"></i> Comprobantes a venir
            </button>
        </li>
        <li class="nav-item">
            <button type="button" id="oc-boton-archivos" class="nav-link oc-tab-solapa" role="tab">
                <i class="fa fa-paperclip"></i> Archivos
            </button>
        </li>
        @if (isset($data) && $data)
            <li class="nav-item">
                <button type="button" id="oc-boton-historia-legajo" class="nav-link oc-tab-solapa" role="tab">
                    <i class="fa fa-folder-open"></i> Historia legajo
                </button>
            </li>
            <li class="nav-item">
                <button type="button" id="oc-boton-historia-estados" class="nav-link oc-tab-solapa" role="tab">
                    <i class="fa fa-list-alt"></i> Historia estados
                </button>
            </li>
            <li class="nav-item">
                <button type="button" id="oc-boton-recepciones" class="nav-link oc-tab-solapa" role="tab">
                    <i class="fa fa-truck"></i> Recepciones
                </button>
            </li>
            <li class="nav-item">
                <button type="button" id="oc-boton-historia-precios" class="nav-link oc-tab-solapa" role="tab">
                    <i class="fa fa-history"></i> Historia precios
                </button>
            </li>
            <li class="nav-item">
                <button type="button" id="oc-boton-arbol" class="nav-link oc-tab-solapa" role="tab">
                    <i class="fa fa-sitemap"></i> Árbol aprobación
                </button>
            </li>
            @if (!empty($mostrar_solapa_ingresos))
                <li class="nav-item">
                    <button type="button" id="oc-boton-ingresos" class="nav-link oc-tab-solapa" role="tab">
                        <i class="fa fa-id-badge"></i> Ingresos
                        <span class="badge badge-light ingreso-solapa-badge-count">{{ ($tickets_ingreso ?? collect())->count() }}</span>
                    </button>
                </li>
            @endif
        @endif
    </ul>
</div>
