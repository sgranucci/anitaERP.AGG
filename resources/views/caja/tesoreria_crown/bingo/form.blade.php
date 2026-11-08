@extends("theme.$theme.layout")

@section('titulo')
    {{ $edicion ? 'Editar bingo' : 'Nuevo bingo' }}
@endsection

@section('scripts')
    <script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
    <script>
    (function () {
        function numero(id) {
            var el = document.getElementById(id);
            if (!el) { return 0; }
            var texto = String(el.value || '').trim();
            if (texto.indexOf(',') >= 0 && texto.indexOf('.') >= 0) {
                texto = texto.replace(/\./g, '').replace(',', '.');
            } else {
                texto = texto.replace(',', '.');
            }
            var valor = parseFloat(texto);
            return isNaN(valor) ? 0 : valor;
        }
        function recalcular() {
            var r = numero('tot_carton') - numero('tot_premio') - numero('tot_rse');
            var el = document.getElementById('resultado');
            if (el) {
                el.value = r.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        }
        document.querySelectorAll('.js-bingo').forEach(function (el) {
            el.addEventListener('input', recalcular);
        });
        recalcular();
    })();
    </script>
@endsection

@section('contenido')
@include('includes.mensaje')
@include('includes.form-error')
<div class="row">
    <div class="col-lg-8">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title mb-0">{{ $edicion ? 'Editar' : 'Cargar' }} bingo</h3>
                <div class="card-tools">
                    <a href="{{ route('tesoreria_crown_bingo', ['sala_id' => $sala->id]) }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver
                    </a>
                </div>
            </div>
            <form method="POST" action="{{ route('guardar_tesoreria_crown_bingo') }}" class="form-horizontal" data-enter-campos="1">
                @csrf
                <input type="hidden" name="id" value="{{ $edicion ? $fila->id : '' }}">
                <input type="hidden" name="sala_id" value="{{ $sala->id }}">
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        {{ $sala->codigo }} — {{ $sala->nombre }}. Igual que a-movbingo: resultado = cartones − premios − RSE.
                    </p>
                    <div class="form-group row">
                        <label for="fecha" class="col-lg-4 control-label text-right pr-2">Fecha</label>
                        <div class="col-lg-4">
                            <input type="date" name="fecha" id="fecha" class="form-control form-control-sm" required value="{{ old('fecha', optional($fila->fecha)->format('Y-m-d')) }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="hora" class="col-lg-4 control-label text-right pr-2">Hora</label>
                        <div class="col-lg-3">
                            <input type="text" name="hora" id="hora" class="form-control form-control-sm" maxlength="5" placeholder="HH:MM" value="{{ old('hora', $fila->hora) }}">
                        </div>
                    </div>
                    @foreach ([
                        'tot_carton' => 'Total cartones vendidos',
                        'tot_premio' => 'Total pago de premios',
                        'tot_rse' => 'Total pago RSE',
                    ] as $campo => $etiqueta)
                        <div class="form-group row">
                            <label for="{{ $campo }}" class="col-lg-4 control-label text-right pr-2">{{ $etiqueta }}</label>
                            <div class="col-lg-4">
                                <input type="text" inputmode="decimal" name="{{ $campo }}" id="{{ $campo }}" class="form-control form-control-sm text-right js-bingo" required value="{{ old($campo, number_format((float) ($fila->{$campo} ?? 0), 2, ',', '.')) }}">
                            </div>
                        </div>
                    @endforeach
                    <div class="form-group row">
                        <label for="resultado" class="col-lg-4 control-label text-right pr-2">Resultado</label>
                        <div class="col-lg-4">
                            <input type="text" id="resultado" class="form-control form-control-sm text-right" readonly tabindex="-1" value="{{ number_format((float) ($fila->resultado ?? 0), 2, ',', '.') }}">
                        </div>
                    </div>
                </div>
                <div class="card-footer text-right">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa fa-save"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
