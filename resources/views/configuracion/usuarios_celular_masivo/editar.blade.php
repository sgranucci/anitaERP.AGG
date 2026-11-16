@extends("theme.$theme.layout")
@section('titulo')
Celulares masivo
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-10">
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title mb-0">Carga masiva de celulares (WhatsApp)</h3>
            </div>
            <form method="post" action="{{ route('actualizar_usuarios_celular_masivo') }}" data-enter-campos="1">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <p class="text-muted">
                        Una línea por usuario: <code>login;54911xxxxxxx</code> o <code>email;54911xxxxxxx</code>
                        (también coma o tab). Líneas con <code>#</code> se ignoran.
                    </p>
                    <div class="form-group">
                        <label for="lineas">Listado</label>
                        <textarea name="lineas" id="lineas" class="form-control" rows="16" required
                            placeholder="# ejemplo&#10;jlopez;5491122334455&#10;maria@empresa.com;5491199887766">{{ old('lineas') }}</textarea>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary btn-sm">Aplicar</button>
                    <a href="{{ route('consultar_empresa_whatsapp') }}" class="btn btn-outline-info btn-sm">Cuentas WA</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
