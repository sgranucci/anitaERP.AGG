@extends("theme.$theme.layout")

@section('titulo')
    Bingo Crown
@endsection

@section('contenido')
@include('includes.mensaje')
<div class="row">
    <div class="col-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title mb-0">Bingo (a-movbingo)</h3>
                <div class="card-tools">
                    @if ($sala && can('crear-tesoreria-crown-bingo', false))
                        <a href="{{ route('crear_tesoreria_crown_bingo', ['sala_id' => $sala->id]) }}" class="btn btn-light btn-sm">
                            <i class="fa fa-plus"></i> Nuevo
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Cartones − premios − RSE = resultado. La carga del día se hace en la <strong>jornada</strong> (panel Bingo).
                    Acá está el histórico. Entra al Anexo II y a la línea Bingo del Anexo I.
                </p>
                @include('caja.tesoreria_crown.partials.sala_chips', [
                    'ruta' => 'tesoreria_crown_bingo',
                    'exportRuta' => 'listar_tesoreria_crown_bingo',
                    'exportQueryparams' => $filtrosQuery ?? [],
                ])
                <div class="table-responsive mt-2">
                    <table id="tabla-paginada" class="table table-sm table-bordered table-hover mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Fecha</th>
                                <th class="text-right">Cartones</th>
                                <th class="text-right">Premios</th>
                                <th class="text-right">RSE</th>
                                <th class="text-right">Resultado</th>
                                <th class="width80"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($filas as $fila)
                                <tr>
                                    <td>
                                        @php
                                            $claveJ = (int) $fila->sala_id.'|'.$fila->fecha->format('Y-m-d');
                                            $jornadaId = $jornadasPorFecha[$claveJ] ?? null;
                                        @endphp
                                        @if ($jornadaId)
                                            <a class="text-primary" href="{{ route('ver_tesoreria_crown_jornada', $jornadaId) }}?panel=bingo">
                                                {{ $fila->fecha?->format('d/m/Y') }}
                                            </a>
                                        @else
                                            {{ $fila->fecha?->format('d/m/Y') }}
                                        @endif
                                    </td>
                                    <td class="text-right">{{ number_format((float) $fila->tot_carton, 2, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format((float) $fila->tot_premio, 2, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format((float) $fila->tot_rse, 2, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format((float) $fila->resultado, 2, ',', '.') }}</td>
                                    <td class="text-nowrap">
                                        @if (can('editar-tesoreria-crown-bingo', false))
                                            <a href="{{ route('editar_tesoreria_crown_bingo', ['id' => $fila->id]) }}" class="btn btn-accion-tabla" title="Editar">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        @endif
                                        @if (can('eliminar-tesoreria-crown-bingo', false))
                                            <form action="{{ route('eliminar_tesoreria_crown_bingo', $fila->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar bingo del {{ $fila->fecha?->format('d/m/Y') }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-accion-tabla" title="Eliminar">
                                                    <i class="fa fa-times-circle text-danger"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Sin movimientos de bingo.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-2">{{ $filas->links() }}</div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
    <script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/listado.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/listado.js')) }}"></script>
@endsection
