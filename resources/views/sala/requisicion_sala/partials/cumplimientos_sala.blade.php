@if (!empty($cumplimientos_sala) && can('cumplir-requisicion-sala', false))
<div class="card card-outline card-success mb-3">
    <div class="card-header py-2">
        <h3 class="card-title mb-0">Cumplimientos de sala registrados</h3>
        <div class="card-tools">
            <a href="{{ route('cumplir_requisicion_sala', ['requisicion_sala_id' => $data->id]) }}" class="btn btn-outline-success btn-sm" data-modo-consulta-omitir="1">
                Ver todos
            </a>
        </div>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-sm table-striped mb-0">
            <thead>
                <tr>
                    <th>N&ordm;</th>
                    <th>Fecha</th>
                    <th>Usuario</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($cumplimientos_sala as $cumple)
                <tr>
                    <td>{{ $cumple->numero }}</td>
                    <td>{{ optional($cumple->fecha)->format('d/m/Y H:i') }}</td>
                    <td>{{ $cumple->usuario?->nombre ?? '' }}</td>
                    <td>
                        @if ($cumple->estaActivo())
                            <span class="badge badge-success">ACTIVO</span>
                        @else
                            <span class="badge badge-secondary">REVERTIDO</span>
                        @endif
                    </td>
                    <td class="text-nowrap">
                        <a href="{{ route('consultar_cumplir_requisicion_sala', ['id' => $cumple->id]) }}" class="btn btn-xs btn-outline-primary" data-modo-consulta-omitir="1">Ver</a>
                        <a href="{{ route('imprimir_pdf_cumplir_requisicion_sala', ['id' => $cumple->id]) }}" class="btn btn-xs btn-outline-danger" target="_blank" rel="noopener" data-modo-consulta-omitir="1">PDF</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
