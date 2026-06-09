@php
    $nivel = (int) ($item['nivel'] ?? 0);
    $url = $item['url'] ?? '';
    $esActivo = $url !== '' ? getMenuActivo($url) : '';
    $tieneSubmenu = ! empty($item['submenu']);
    $ramaActiva = $tieneSubmenu && menuItemEsActivoOAncestro($item);
    $esPadreAbierto = $ramaActiva && $esActivo !== 'active';
    $claseLink = trim($esActivo . ($esPadreAbierto ? ' menu-parent-open' : ''));
    $icono = $item['icono'] ?? 'fa-circle';
@endphp

@if (! $tieneSubmenu)
    <li class="nav-item nav-menu-level-{{ $nivel }}">
        <a href="{{ url($url) }}" class="nav-link {{ $claseLink }}">
            @if ($nivel === 0)
                <i class="nav-icon fa {{ $icono }}"></i>
            @else
                <i class="nav-icon fas fa-circle nav-icon-dot"></i>
            @endif
            <p>{{ $item['nombre'] }}</p>
        </a>
    </li>
@else
    <li class="nav-item has-treeview nav-menu-level-{{ $nivel }}{{ $ramaActiva ? ' menu-open' : '' }}">
        <a href="javascript:;" class="nav-link {{ $claseLink }}">
            @if ($nivel === 0)
                <i class="nav-icon fa {{ $icono }}"></i>
            @else
                <i class="nav-icon fa {{ $icono }}"></i>
            @endif
            <p>
                {{ $item['nombre'] }}
                <i class="right fas fa-angle-left"></i>
            </p>
        </a>
        <ul class="nav nav-treeview">
            @foreach ($item['submenu'] as $submenu)
                @include("theme.$theme.menu-item", ['item' => $submenu])
            @endforeach
        </ul>
    </li>
@endif
