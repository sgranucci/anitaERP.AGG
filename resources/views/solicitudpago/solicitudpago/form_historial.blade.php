@if (!isset($data) || !$data->estados || $data->estados->isEmpty())
    <p class="text-muted">Sin movimientos de estado a&uacute;n.</p>
@else
    <div class="table-responsive">
        <table class="table table-sm table-bordered table-striped">
            <thead class="thead-light">
                <tr>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Usuario</th>
                    <th>Estado ant.</th>
                    <th>Estado act.</th>
                    <th>Leyenda</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data->estados as $est)
                    <tr>
                        <td>{{ optional($est->fecha)->format('d/m/Y') }}</td>
                        <td>{{ $est->hora }}</td>
                        <td>{{ optional($est->usuarios)->nombre ?? '—' }}</td>
                        <td>{{ $est->estado_anterior ?? '—' }}</td>
                        <td>{{ $est->estado_actual }}</td>
                        <td>{{ $est->leyenda }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
