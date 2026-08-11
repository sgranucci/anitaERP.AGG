@php
    $tabsArticuloActiva = $tabsArticuloActiva ?? 'datos';
    // Slugs reales: formula-articulo (singular, menu fórmulas). Plural legacy no existe en BD.
    $mostrarFormula = can('editar-formula-articulo', false)
        || can('actualizar-formula-articulo', false)
        || can('listar-formula-articulo', false)
        || can('editar-formula-articulos', false)
        || can('actualizar-formula-articulos', false)
        || can('editar-articulos', false)
        || can('actualizar-articulos', false);
    $mostrarCompras = can('editar-compras-articulos', false) || can('actualizar-compras-articulos', false);
    $mostrarContable = can('editar-contabilidad-articulos', false)
        || can('actualizar-contabilidad-articulos', false)
        || can('editar-articulos', false)
        || can('actualizar-articulos', false);
    $mostrarPartesUnicas = !empty($mostrarPartesUnicas) || (isset($producto) && (string) ($producto->numeroparte ?? '0') === '1');
@endphp
@include('includes.tabs-activas-estilos')
<div class="tabs-activas px-3 pt-2">
    <ul class="nav nav-tabs" id="tabs-articulo" role="tablist">
        <li class="nav-item">
            <a class="nav-link {{ $tabsArticuloActiva === 'datos' ? 'active' : '' }}" href="#" id="botonform1" role="tab">
                <i class="fa fa-info-circle"></i> Datos principales
            </a>
        </li>
        @if ($mostrarFormula)
        <li class="nav-item">
            <a class="nav-link {{ $tabsArticuloActiva === 'formulas' ? 'active' : '' }}" href="#" id="botonform2" role="tab">
                <i class="fa fa-flask"></i> Fórmulas
            </a>
        </li>
        @endif
        @if ($mostrarCompras)
        <li class="nav-item">
            <a class="nav-link {{ $tabsArticuloActiva === 'compras' ? 'active' : '' }}" href="#" id="botonform3" role="tab">
                <i class="fa fa-shopping-cart"></i> Compras
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tabsArticuloActiva === 'proveedores' ? 'active' : '' }}" href="#" id="botonform8" role="tab">
                <i class="fa fa-truck"></i> Proveedores
            </a>
        </li>
        @endif
        @if ($mostrarContable)
        <li class="nav-item">
            <a class="nav-link {{ $tabsArticuloActiva === 'contable' ? 'active' : '' }}" href="#" id="botonform4" role="tab">
                <i class="fa fa-book"></i> Datos contables
            </a>
        </li>
        @endif
        <li class="nav-item">
            <a class="nav-link {{ $tabsArticuloActiva === 'leyendas' ? 'active' : '' }}" href="#" id="botonform5" role="tab">
                <i class="fa fa-comment"></i> Leyendas
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tabsArticuloActiva === 'archivos' ? 'active' : '' }}" href="#" id="botonform6" role="tab">
                <i class="fa fa-paperclip"></i> Archivos asociados
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tabsArticuloActiva === 'historia' ? 'active' : '' }}" href="#" id="botonform7" role="tab">
                <i class="fa fa-history"></i> Historia
            </a>
        </li>
        @if ($mostrarPartesUnicas)
        <li class="nav-item">
            <a class="nav-link {{ $tabsArticuloActiva === 'partes' ? 'active' : '' }}" href="#" id="botonform9" role="tab">
                <i class="fa fa-barcode"></i> Partes únicas
                @if (!empty($partesUnicasTotal))
                    <span class="badge badge-info">{{ $partesUnicasTotal }}</span>
                @endif
            </a>
        </li>
        @endif
    </ul>
</div>
