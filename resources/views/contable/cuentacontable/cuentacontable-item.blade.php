@if ($item["subcuentacontable"] == [])
<li class="dd-item dd3-item" data-id="{{$item["id"]}}">
    <div class="dd-handle dd3-handle"></div>
    <div class="dd3-content {{$item["tipocuenta"] == "1" ? "font-weight-bold" : ""}}">
        <a href="{{route("editar_cuentacontable", ['id' => $item["id"]])}}">{{$item["nombre"] . " | Codigo -> " . $item["codigo"]}} -> <i style="font-size:20px;" class="fa fa-fw"></i></a>
        <a href="{{route('eliminar_cuentacontable', ['id' => $item["id"]])}}" class="eliminar-cuentacontable tooltipsC" title="Eliminar esta cuenta"><i class="text-danger fa fa-trash-o"></i></a>
    </div>
</li>
@else
<li class="dd-item dd3-item" data-id="{{$item["id"]}}">
    <div class="dd-handle dd3-handle"></div>
    <div class="dd3-content {{$item["tipocuenta"] == "1" ? "font-weight-bold" : ""}}">
        <a href="{{route("editar_cuentacontable", ['id' => $item["id"]])}}">{{ $item["nombre"] . " | Codigo -> " . $item["codigo"]}} -> <i style="font-size:20px;" class="fa fa-fw"></i></a>
        <a href="{{route('eliminar_cuentacontable', ['id' => $item["id"]])}}" class="eliminar-cuentacontable tooltipsC" title="Eliminar esta cuenta"><i class="text-danger fa fa-trash-o"></i></a>
    </div>
    <ol class="dd-list">
        @foreach ($item["subcuentacontable"] as $subcuentacontable)
        @include("contable.cuentacontable.cuentacontable-item",[ "item" => $subcuentacontable ])
        @endforeach
    </ol>
</li>
@endif
