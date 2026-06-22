@extends("theme.$theme.layout")

@section('titulo')
    Transferencias pendientes
@endsection

@section('contenido')
<div class="row">
    <div class="col-12">
        @include('includes.mensaje')
        <div class="card card-warning">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Transferencias pendientes de recepción</h3>
                <a href="{{ route('transferencia_mercaderia') }}" class="btn btn-outline-info btn-sm">
                    <i class="fa fa-exchange"></i> Nueva transferencia
                </a>
            </div>
            <div class="card-body table-responsive p-0">
                @if (count($pendientes) === 0)
                    <p class="p-3 text-muted mb-0">No hay transferencias pendientes de aprobación.</p>
                @else
                    <table class="table table-striped table-hover mb-0" id="tabla-paginada">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Código</th>
                                <th>Fecha</th>
                                <th>Origen</th>
                                <th>Destino</th>
                                <th>Ítems</th>
                                <th>Remitente</th>
                                <th>Destinatario</th>
                                <th class="text-nowrap">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pendientes as $t)
                                <tr>
                                    <td><strong>{{ $t->codigo }}</strong></td>
                                    <td>{{ $t->fecha?->format('d/m/Y') }}</td>
                                    <td>
                                        @if ($t->bien_uso_origen_id)
                                            {{ \App\Support\Stock\TransferenciaBienUsoSupport::etiquetaBien($t->bienUsoOrigen) }}
                                        @else
                                            {{ optional($t->depositoOrigen)->nombre }}
                                        @endif
                                    </td>
                                    <td>
                                        @if ($t->bien_uso_destino_id)
                                            {{ \App\Support\Stock\TransferenciaBienUsoSupport::etiquetaBien($t->bienUsoDestino) }}
                                        @else
                                            {{ optional($t->depositoDestino)->nombre }}
                                        @endif
                                    </td>
                                    <td>{{ $t->articulos->count() }}</td>
                                    <td>{{ optional($t->usuarioOrigen)->nombre ?? '—' }}</td>
                                    <td>{{ optional($t->usuarioDestino)->nombre ?? '—' }}</td>
                                    <td class="text-nowrap">
                                        @if (can('aprobar-transferencia-mercaderia', false))
                                            <button type="button" class="btn btn-success btn-sm tm-aprobar" data-id="{{ $t->id }}">Aprobar</button>
                                            <button type="button" class="btn btn-outline-danger btn-sm tm-rechazar" data-id="{{ $t->id }}">Rechazar</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function ($) {
    'use strict';
    var csrf = $('meta[name="csrf-token"]').attr('content');

    $('.tm-aprobar').on('click', function () {
        var id = $(this).data('id');
        if (!confirm('¿Confirma la recepción de esta transferencia?')) return;
        $.post('{{ url('stock/transferencia-mercaderia') }}/' + id + '/aprobar', { _token: csrf })
            .done(function (r) { alert(r.mensaje || 'Listo'); location.reload(); })
            .fail(function (x) { alert((x.responseJSON && x.responseJSON.mensaje) || 'Error'); });
    });

    $('.tm-rechazar').on('click', function () {
        var id = $(this).data('id');
        var motivo = prompt('Motivo del rechazo (opcional):', '');
        if (motivo === null) return;
        $.post('{{ url('stock/transferencia-mercaderia') }}/' + id + '/rechazar', { _token: csrf, motivo: motivo })
            .done(function (r) { alert(r.mensaje || 'Listo'); location.reload(); })
            .fail(function (x) { alert((x.responseJSON && x.responseJSON.mensaje) || 'Error'); });
    });
})(jQuery);
</script>
@endsection
