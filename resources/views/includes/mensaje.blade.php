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
            @foreach (session("errores") as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
