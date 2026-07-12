@if (($cambios_articulo ?? collect())->isNotEmpty())
    <div class="alert alert-warning mt-3 mb-3" id="bloque-historia-cambios-articulo">
        <h6 class="alert-heading mb-2">
            <i class="fa fa-history"></i> Historial de cambios de art&iacute;culo (cumplimiento)
        </h6>
        <p class="small mb-2 text-muted mb-2">
            Cada cambio al cumplir queda registrado. La l&iacute;nea de la requisici&oacute;n refleja el art&iacute;culo vigente; aqu&iacute; se conserva el art&iacute;culo anterior.
        </p>
        <div class="table-responsive mb-0">
            <table class="table table-sm table-bordered bg-white mb-0">
                <thead style="background-color:#85C1E9;color:#17202A;">
                    <tr>
                        <th>Fecha</th>
                        <th>L&iacute;nea req.</th>
                        <th>Art&iacute;culo anterior</th>
                        <th>Art&iacute;culo nuevo</th>
                        <th>Usuario</th>
                        <th>Cumplimiento</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cambios_articulo as $cambio)
                        <tr>
                            <td class="text-nowrap">{{ optional($cambio->created_at)->format('d/m/Y H:i') }}</td>
                            <td>#{{ $cambio->requisicion_articulo_id }}</td>
                            <td>
                                {{ $cambio->articuloAnterior?->sku ?? $cambio->articulo_id_anterior }}
                                @if ($cambio->articuloAnterior?->descripcion)
                                    <br><span class="small text-muted">{{ $cambio->articuloAnterior->descripcion }}</span>
                                @endif
                            </td>
                            <td>
                                {{ $cambio->articuloNuevo?->sku ?? $cambio->articulo_id_nuevo }}
                                @if ($cambio->articuloNuevo?->descripcion)
                                    <br><span class="small text-muted">{{ $cambio->articuloNuevo->descripcion }}</span>
                                @endif
                            </td>
                            <td>{{ $cambio->usuario?->nombre ?? '—' }}</td>
                            <td>
                                @if ($cambio->cumplimiento)
                                    <a href="{{ route('consultar_cumplir_requisicion_compra', ['id' => $cambio->cumplimiento->id]) }}" class="text-primary" target="_blank" rel="noopener">
                                        #{{ $cambio->cumplimiento->numero }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
