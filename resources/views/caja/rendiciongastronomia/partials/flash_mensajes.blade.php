@if (session('mensaje'))
    <div class="alert alert-success alert-dismissible" data-auto-dismiss="3000">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
        <h4><i class="icon fa fa-check"></i> Mensaje sistema Anita ERP</h4>
        <ul class="mb-0">
            <li>{{ session('mensaje') }}</li>
        </ul>
    </div>
@endif
@if (session('errores'))
    <div id="alerta-flash-errores-rendicion" class="alert alert-danger alert-dismissible">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
        <h4><i class="icon fa fa-ban"></i> No se pudo completar la operación</h4>
        <ul class="mb-0">
            @foreach (session('errores') as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
