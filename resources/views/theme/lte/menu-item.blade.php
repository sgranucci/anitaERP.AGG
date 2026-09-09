@php
    $nivel = (int) ($item['nivel'] ?? 0);
    $url = $item['url'] ?? '';
    $esActivo = $url !== '' ? getMenuActivo($url) : '';
    $tieneSubmenu = ! empty($item['submenu']);
    $ramaActiva = $tieneSubmenu && menuItemEsActivoOAncestro($item);
    $esPadreAbierto = $ramaActiva && $esActivo !== 'active';
    $claseLink = trim($esActivo . ($esPadreAbierto ? ' menu-parent-open' : ''));
    $icono = $item['icono'] ?? 'fa-circle';
    $esBandejaTicket = $url === 'ticket/bandeja';
    $mostrarBadgeBandeja = $esBandejaTicket && can('listar-bandeja-ticket', false);
@endphp

@if (! $tieneSubmenu)
    <li class="nav-item nav-menu-level-{{ $nivel }} anita-menu-leaf{{ $mostrarBadgeBandeja ? ' anita-menu-leaf-has-badge' : '' }}">
        <a href="{{ url($url) }}"
           class="nav-link {{ $claseLink }}{{ $mostrarBadgeBandeja ? ' js-bandeja-ticket-nav' : '' }}"
           @if ($mostrarBadgeBandeja)
               data-bandeja-ticket-contador
               data-contador-url="{{ urlAppCarpeta('ticket/bandeja/contador') }}"
               data-count="{{ (int) ($bandejaTicketCount ?? 0) }}"
           @endif
        >
            @if ($nivel === 0)
                <i class="nav-icon fa {{ $icono }}"></i>
            @else
                <i class="nav-icon fas fa-circle nav-icon-dot"></i>
            @endif
            <p>
                {{ $item['nombre'] }}
                @if ($mostrarBadgeBandeja)
                    <span class="badge badge-warning anita-menu-count-badge js-bandeja-ticket-badge{{ ($bandejaTicketCount ?? 0) > 0 ? '' : ' d-none' }}"
                          title="Tickets en cola sin asignar">{{ ($bandejaTicketCount ?? 0) > 99 ? '99+' : (int) ($bandejaTicketCount ?? 0) }}</span>
                @endif
            </p>
        </a>
        @auth
            @if ($url !== '')
                @php
                    $menuAnclado = in_array((int) $item['id'], $barraTareasMenuIds ?? [], true);
                @endphp
                <button type="button"
                    class="anita-menu-pin-btn{{ $menuAnclado ? ' is-pinned' : '' }}"
                    data-menu-id="{{ $item['id'] }}"
                    data-menu-nombre="{{ $item['nombre'] }}"
                    title="{{ $menuAnclado ? 'Quitar de la barra de tareas (clic para confirmar)' : 'Anclar en la barra de tareas (clic para confirmar). También: clic derecho sobre el programa.' }}"
                    aria-label="{{ $menuAnclado ? 'Desanclar de la barra de tareas' : 'Anclar en la barra de tareas' }}">
                    <i class="fas fa-thumbtack"></i>
                </button>
            @endif
        @endauth
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
