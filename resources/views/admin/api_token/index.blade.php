@extends("theme.$theme.layout")
@section('titulo')
    Tokens API (Sanctum)
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Tokens técnicos — {{ $usuario->nombre }}</h3>
            </div>
            <div class="card-body">
                <form method="get" class="form-inline mb-3">
                    <label class="mr-2">Usuario</label>
                    <select name="usuario_id" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                        @foreach ($usuarios as $u)
                            <option value="{{ $u->id }}" {{ (int) $usuario->id === (int) $u->id ? 'selected' : '' }}>
                                {{ $u->nombre }} ({{ $u->usuario }})
                            </option>
                        @endforeach
                    </select>
                </form>

                <form method="post" action="{{ route('crear_api_token_usuario') }}" class="mb-4">
                    @csrf
                    <input type="hidden" name="usuario_id" value="{{ $usuario->id }}">
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label class="small">Nombre</label>
                            <input type="text" name="name" class="form-control form-control-sm" required maxlength="80" placeholder="integracion-bi">
                        </div>
                        <div class="form-group col-md-7">
                            <label class="small">Abilities</label>
                            <div>
                                @foreach ($abilities as $ab)
                                    <div class="custom-control custom-checkbox custom-control-inline">
                                        <input type="checkbox" class="custom-control-input" id="ab_{{ md5($ab) }}"
                                               name="abilities[]" value="{{ $ab }}"
                                               {{ in_array($ab, ['reports:read','datasets:read'], true) ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="ab_{{ md5($ab) }}">{{ $ab }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="form-group col-md-2 align-self-end">
                            <button type="submit" class="btn btn-primary btn-sm btn-block">Emitir token</button>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered" id="tabla-paginada">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Abilities</th>
                                <th>Último uso</th>
                                <th>Creado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($tokens as $t)
                                <tr>
                                    <td>{{ $t->id }}</td>
                                    <td>{{ $t->name }}</td>
                                    <td><code>{{ implode(', ', $t->abilities ?? []) }}</code></td>
                                    <td>{{ $t->last_used_at }}</td>
                                    <td>{{ $t->created_at }}</td>
                                    <td>
                                        <form method="post" action="{{ route('revocar_api_token_usuario', ['tokenId' => $t->id]) }}"
                                              onsubmit="return confirm('¿Revocar token?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-accion-tabla" title="Revocar">
                                                <i class="fa fa-times-circle text-danger"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-muted text-center">Sin tokens</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
