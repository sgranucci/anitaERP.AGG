@extends("theme.$theme.layout")

@section('titulo')
    Conversión de fichas
@endsection

@section('scripts')
    <script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@include('includes.mensaje')
@include('includes.form-error')
<div class="row">
    <div class="col-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Conversión del día (poder público)</h3>
                <div class="card-tools">
                    <a href="{{ route('tesoreria_crown_valor', $sala ? ['sala_id' => $sala->id] : []) }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Valores
                    </a>
                </div>
            </div>
            <div class="card-body border-bottom py-2">
                <form method="get" action="{{ route('conversion_tesoreria_crown_valor') }}" class="form-inline">
                    <label class="mr-2 mb-0" for="fecha">Fecha</label>
                    <input type="date" name="fecha" id="fecha" class="form-control form-control-sm mr-2" value="{{ $fecha }}" required>
                    <button type="submit" class="btn btn-sm btn-info">Consultar</button>
                </form>
                <p class="small text-muted mb-0 mt-2">
                    El catálogo de denominaciones se administra en <strong>Valores</strong> (tipos FVAL / FCOL).
                    Acá solo se carga la <strong>conversión final</strong> del día para el poder público.
                    Si falta una ficha, sincronizá/importá el catálogo Anita o creá el valor en Valores.
                </p>
            </div>
            <form method="POST" action="{{ route('guardar_conversion_tesoreria_crown_valor') }}" data-enter-campos="1">
                @csrf
                <input type="hidden" name="fecha" value="{{ $fecha }}">
                <div class="card-body table-responsive p-0">
                    <table class="table table-sm table-bordered mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Código</th>
                                <th>Serie</th>
                                <th>Color</th>
                                <th class="text-right">Valor</th>
                                <th class="text-right" style="width:140px;">Conv. final</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($catalogo as $ficha)
                                @php $codigo = (int) $ficha->codigo; @endphp
                                <tr>
                                    <td>{{ $codigo }}</td>
                                    <td>{{ $ficha->serie }}</td>
                                    <td>{{ $ficha->desc_color }}</td>
                                    <td class="text-right">{{ number_format((float) $ficha->valor, 2, ',', '.') }}</td>
                                    <td>
                                        <input type="number" name="conv_final[{{ $codigo }}]" class="form-control form-control-sm text-right"
                                               step="1" min="0" inputmode="numeric"
                                               value="{{ old('conv_final.'.$codigo, (int) ($conversiones[$codigo] ?? 0)) }}"
                                               {{ can('actualizar-tesoreria-crown-valor', false) ? '' : 'readonly' }}>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">
                                        No hay catálogo de fichas para conversión. Corré
                                        <code>tesoreria-crown:importar-fichas-anita</code>
                                        o sincronizá Valores desde Anita.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($catalogo->isNotEmpty() && can('actualizar-tesoreria-crown-valor', false))
                    <div class="card-footer text-right">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa fa-save"></i> Guardar conversión
                        </button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</div>
@endsection
