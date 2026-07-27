@php
    $submenus = $item['submenu'] ?? [];
    $esFilaModulo = $nivel === 0;
    $esCierreModulo = $submenus === [] && (($marcarCierreModulo ?? false) || $esFilaModulo);
    $menuId = (int) ($item['id'] ?? 0);
    $moduloIdActual = (int) ($moduloId ?? $menuId);
    $parentIdActual = (int) ($parentId ?? 0);
@endphp
<tr class="@if ($esFilaModulo) menu-rol-fila-modulo @endif @if ($esCierreModulo) menu-rol-fila-modulo-cierre @endif"
    data-menu-id="{{ $menuId }}"
    data-parent-id="{{ $parentIdActual }}"
    data-modulo-id="{{ $moduloIdActual }}"
    data-menu-nombre="{{ e(mb_strtolower($item['nombre'] ?? '')) }}">
    <td class="menu-rol-col-menu {{ $esFilaModulo ? 'menu-rol-celda-modulo' : 'pl-40' }}">
        <button type="button"
            class="btn btn-sm btn-outline-secondary mr-1 btn-permisos-menu"
            title="Permisos asociados a este menú"
            data-menu-id="{{ $menuId }}"
            data-menu-nombre="{{ e($item['nombre']) }}">
            <i class="fa fa-key"></i>
        </button>
        @if ($esFilaModulo)
            <i class="fa fa-arrows-alt"></i>
        @else
            <i class="fa fa-arrow-right"></i>
        @endif
        {{ $item['nombre'] }}
    </td>
    @foreach ($rols as $id => $nombre)
        <td class="text-center menu-rol-col-rol col-rol-{{ $id }}">
            <input
                type="checkbox"
                class="menu_rol"
                name="menu_rol[]"
                data-menuid="{{ $menuId }}"
                value="{{ $id }}"
                {{ in_array($id, array_column($menusRols[$menuId] ?? [], 'id')) ? 'checked' : '' }}>
        </td>
    @endforeach
</tr>
@foreach ($submenus as $sub)
    @include('admin.menu-rol.partials.fila-menu', [
        'item' => $sub,
        'rols' => $rols,
        'menusRols' => $menusRols,
        'nivel' => $nivel + 1,
        'moduloId' => $moduloIdActual,
        'parentId' => $menuId,
        'marcarCierreModulo' => ($marcarCierreModulo ?? true) && $loop->last,
    ])
@endforeach
