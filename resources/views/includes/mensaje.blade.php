@if (session("mensaje"))
    <div class="alert alert-success alert-dismissible" data-auto-dismiss="3000">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
        <h4><i class="icon fa fa-check"></i> Mensaje sistema Anita ERP</h4>
        <ul>
            <li>{{ session("mensaje") }}</li>
        </ul>
    </div>
@endif
@if (session("errores"))
    <div class="alert alert-danger">
        <ul>
            @foreach (\Illuminate\Support\Arr::wrap(session("errores")) as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
@if (session("advertencias"))
    <div class="alert alert-warning alert-dismissible">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
        <h4><i class="icon fa fa-warning"></i> Advertencia — replicación Anita</h4>
        <ul class="mb-0">
            @foreach (\Illuminate\Support\Arr::wrap(session("advertencias")) as $aviso)
                <li>{{ $aviso }}</li>
            @endforeach
        </ul>
    </div>
@endif
