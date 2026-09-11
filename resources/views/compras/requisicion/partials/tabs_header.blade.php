@include('includes.tabs-activas-estilos')
<div class="tabs-activas px-2 pt-2">
    <ul class="nav nav-tabs flex-wrap" role="tablist">
        <li class="nav-item">
            <button type="button" id="botonform1" class="nav-link active rq-tab-solapa" role="tab">
                <i class="fa fa-info-circle"></i> Datos principales
            </button>
        </li>
        @if (isset($data) && $data)
            <li class="nav-item">
                <button type="button" id="botonform3" class="nav-link rq-tab-solapa" role="tab">
                    <i class="fa fa-history"></i> Historia
                </button>
            </li>
        @endif
        <li class="nav-item">
            <button type="button" id="botonform4" class="nav-link rq-tab-solapa" role="tab">
                <i class="fa fa-paperclip"></i> Archivos
            </button>
        </li>
        @if (isset($data) && $data)
            <li class="nav-item">
                <button type="button" id="botonform5" class="nav-link rq-tab-solapa" role="tab">
                    <i class="fa fa-sitemap"></i> Árbol aprobación
                </button>
            </li>
            <li class="nav-item" @if (!empty($es_provisorio)) style="display:none;" @endif>
                <button type="button" id="boton-solapa-presupuesto-requisicion" class="nav-link rq-tab-solapa" role="tab">
                    <i class="fa fa-list-alt"></i> Presupuestos
                </button>
            </li>
        @endif
    </ul>
</div>
