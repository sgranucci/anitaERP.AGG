@if ($item["submenu"] == [])
    <li class="nav-item">
        <a href="{{url($item['url'])}}" class="nav-link {{getMenuActivo($item["url"])}}">
            <i class="nav-icon fa {{$item["icono"]}}"></i>
            @switch($item['nivel'])
            @case(2)
                <p style="color: DodgerBlue;">
                @break
            @case(3)
                <p style="color: Green;">
                @break
            @default
                <p style="">
            @endswitch
                @for ($sp = 1; $sp < $item['nivel']; $sp++)
                    &nbsp
                @endfor
        	    {{ $item["nombre"] }}
            </p>
        </a>
    </li>
@else
    <li class="nav-item has-treeview">
        <a href="javascript:;" class="nav-link">
          <i class="nav-icon fa {{$item["icono"]}}"></i>
            @switch($item['nivel'])
            @case(2)
                <p style="color: DodgerBlue;">
                @break
            @case(3)
                <p style="color: Green;">
                @break
            @default
                <p style="">
            @endswitch
            @for ($sp = 1; $sp < $item['nivel']; $sp++)
                &nbsp
            @endfor
            {{ $item["nombre"] }}
            <i class="right fas fa-angle-left"></i>
          </p>
        </a>
        <ul class="nav nav-treeview">
            @foreach ($item["submenu"] as $submenu)
                @include("theme.$theme.menu-item", ["item" => $submenu, "submenu" => 1, "nivel" => $submenu['nivel']])
            @endforeach
        </ul>
    </li>
@endif
