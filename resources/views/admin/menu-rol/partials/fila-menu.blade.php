<tr>
    <td class="{{ $nivel === 0 ? 'font-weight-bold' : 'pl-40' }}">
        <button type="button"
            class="btn btn-sm btn-outline-secondary mr-1 btn-permisos-menu"
            title="Permisos asociados a este menú"
            data-menu-id="{{ $item['id'] }}"
            data-menu-nombre="{{ e($item['nombre']) }}">
            <i class="fa fa-key"></i>
        </button>
        @if ($nivel === 0)
            <i class="fa fa-arrows-alt"></i>
        @else
            <i class="fa fa-arrow-right"></i>
        @endif
        {{ $item['nombre'] }}
    </td>
    @foreach ($rols as $id => $nombre)
        <td class="text-center">
            <input
                type="checkbox"
                class="menu_rol"
                name="menu_rol[]"
                data-menuid="{{ $item['id'] }}"
                value="{{ $id }}"
                {{ in_array($id, array_column($menusRols[$item['id']] ?? [], 'id')) ? 'checked' : '' }}>
        </td>
    @endforeach
</tr>
@foreach ($item['submenu'] ?? [] as $sub)
    @include('admin.menu-rol.partials.fila-menu', [
        'item' => $sub,
        'rols' => $rols,
        'menusRols' => $menusRols,
        'nivel' => $nivel + 1,
    ])
@endforeach
