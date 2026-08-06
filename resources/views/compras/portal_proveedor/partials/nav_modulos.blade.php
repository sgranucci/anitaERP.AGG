@php
    $moduloActivo = $moduloActivo ?? 'facturas';
    $qid = ['proveedor_id' => $proveedorId];
@endphp
<ul class="nav portal-nav-modulos" role="tablist">
    <li class="nav-item">
        <a class="nav-link {{ $moduloActivo === 'ordenes' ? 'active' : '' }}"
           href="{{ route('portal_proveedores_ordenes', $qid) }}">
            <i class="fa fa-shopping-cart"></i> Órdenes de compra
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $moduloActivo === 'facturas' ? 'active' : '' }}"
           href="{{ route('portal_proveedores', $qid) }}">
            <i class="fa fa-file-text-o"></i> Facturas
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $moduloActivo === 'pagos' ? 'active' : '' }}"
           href="{{ route('portal_proveedores_pagos', $qid) }}">
            <i class="fa fa-money"></i> Pagos
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $moduloActivo === 'retenciones' ? 'active' : '' }}"
           href="{{ route('portal_proveedores_retenciones', $qid) }}">
            <i class="fa fa-percent"></i> Retenciones
        </a>
    </li>
</ul>
