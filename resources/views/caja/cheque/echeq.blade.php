@extends("theme.$theme.layout")
@section('titulo')
    eCheq
@endsection

@section('scripts')
@if ($puede_sync ?? false)
<script>
(function ($) {
    $(document).on('click', '.btn-echeq-sync', function (e) {
        e.preventDefault();
        var id = $(this).data('cheque-id');
        var url = @json(url('caja/cheque/:id/echeq-sync')).replace(':id', id);
        $.ajax({
            url: url,
            method: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Accept': 'application/json'
            },
            data: { _token: $('meta[name="csrf-token"]').attr('content') }
        }).done(function (resp) {
            if (!resp || resp.mensaje !== 'ok') {
                alert((resp && resp.error) ? resp.error : 'No se pudo sincronizar.');
                return;
            }
            var msg = 'Estado: ' + (resp.data && resp.data.estado ? resp.data.estado : '');
            if (resp.data && resp.data.mensaje) {
                msg += '\n' + resp.data.mensaje;
            }
            alert(msg);
            window.location.reload();
        }).fail(function (xhr) {
            alert((xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Error eCheq.');
        });
    });
})(jQuery);
</script>
@endif
@endsection

@section('contenido')
@php
    $puedeEditarCheque = can('editar-cheque', false);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">eCheq — provider {{ $provider }}</h3>
                <div class="card-tools">
                    <a href="{{ route('cheque') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver a cheques
                    </a>
                </div>
            </div>
            <div class="card-body">
                @if (!($habilitado ?? true))
                    <div class="alert alert-warning">eCheq deshabilitado en config.</div>
                @endif
                <p class="small text-muted">
                    La API bancaria depende del banco de cada empresa. Hoy corre el provider
                    <strong>{{ $provider }}</strong> (sin API externa). Cuando Ferli defina el banco,
                    se agrega el driver (Galicia/Santander/…) sin cambiar esta pantalla.
                </p>
                <form method="get" class="mb-3 form-inline">
                    @if (($empresa_query ?? collect())->count() > 1)
                        <select name="empresa_id" class="form-control form-control-sm mr-2">
                            <option value="">Todas las empresas</option>
                            @foreach ($empresa_query as $emp)
                                <option value="{{ $emp->id }}" @selected((int)($empresa_id ?? 0) === (int)$emp->id)>{{ $emp->nombre }}</option>
                            @endforeach
                        </select>
                    @endif
                    <button class="btn btn-info btn-sm" type="submit">Filtrar</button>
                </form>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped" id="tabla-paginada">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>ID</th>
                                <th>Origen</th>
                                <th>Nro / eCheq</th>
                                <th>Pago</th>
                                <th class="text-right">Monto</th>
                                <th>Banco</th>
                                <th>Cliente</th>
                                <th>Estado eCheq</th>
                                <th>Sync</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($filas as $f)
                                <tr>
                                    <td>
                                        @if ($puedeEditarCheque)
                                            <a class="text-primary" href="{{ route('editar_cheque', $f['id']) }}" target="_blank" rel="noopener">{{ $f['id'] }}</a>
                                        @else
                                            {{ $f['id'] }}
                                        @endif
                                    </td>
                                    <td>{{ $f['origen'] === 'E' ? 'CHP' : 'CHT' }}</td>
                                    <td>{{ $f['nro_echeq'] ?: $f['numerocheque'] }}</td>
                                    <td>{{ $f['fechapago'] }}</td>
                                    <td class="text-right">{{ number_format($f['monto'], 2, ',', '.') }} {{ $f['moneda'] }}</td>
                                    <td>{{ $f['banco'] }}</td>
                                    <td>{{ $f['cliente'] }}</td>
                                    <td>{{ $f['echeq_estado'] ?: '—' }}</td>
                                    <td class="small">{{ $f['echeq_sync_at'] ?: '—' }}</td>
                                    <td>
                                        @if ($puede_sync ?? false)
                                            <button type="button" class="btn-accion-tabla btn-echeq-sync" data-cheque-id="{{ $f['id'] }}" title="Sincronizar estado">
                                                <i class="fa fa-refresh text-primary"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="text-center text-muted">Sin eCheqs pendientes.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
