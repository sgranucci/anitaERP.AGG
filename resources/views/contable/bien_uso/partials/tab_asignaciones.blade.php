@if (! empty($inventarioActual) || ! empty($historial) || ($transferenciasPendientes ?? collect())->isNotEmpty() || ($transferenciasPendientesSalida ?? collect())->isNotEmpty())
    <div class="mb-3">
        <h5 class="mb-2">Stock asignado actualmente</h5>
        @if (! empty($inventarioActual))
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>SKU</th>
                            <th>Art&iacute;culo</th>
                            <th class="text-right">Cantidad</th>
                            <th>&Uacute;ltimo mov.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($inventarioActual as $fila)
                            <tr>
                                <td>{{ $fila['sku'] }}</td>
                                <td>{{ $fila['descripcion'] }}</td>
                                <td class="text-right">{{ number_format($fila['cantidad'], 4, ',', '.') }}</td>
                                <td>{{ $fila['ultima_fecha'] ? \Carbon\Carbon::parse($fila['ultima_fecha'])->format('d/m/Y') : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted mb-0">Sin art&iacute;culos asignados en este momento.</p>
        @endif
    </div>

    @if (($transferenciasPendientesSalida ?? collect())->isNotEmpty())
        <div class="mb-3">
            <h5 class="mb-2">Desasignaciones pendientes desde este bien</h5>
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead style="background:#fff3cd;color:#17202A;">
                        <tr>
                            <th>C&oacute;digo</th>
                            <th>Fecha</th>
                            <th>Dep&oacute;sito destino</th>
                            <th>&Iacute;tems</th>
                            <th>Remitente</th>
                            <th>Destinatario</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transferenciasPendientesSalida as $t)
                            <tr>
                                <td>{{ $t->codigo }}</td>
                                <td>{{ $t->fecha?->format('d/m/Y') }}</td>
                                <td>{{ optional($t->depositoDestino)->nombre }}</td>
                                <td>{{ $t->articulos->count() }}</td>
                                <td>{{ optional($t->usuarioOrigen)->nombre ?? '—' }}</td>
                                <td>{{ optional($t->usuarioDestino)->nombre ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if (($transferenciasPendientes ?? collect())->isNotEmpty())
        <div class="mb-3">
            <h5 class="mb-2">Transferencias pendientes hacia este bien</h5>
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead style="background:#fff3cd;color:#17202A;">
                        <tr>
                            <th>C&oacute;digo</th>
                            <th>Fecha</th>
                            <th>Origen</th>
                            <th>&Iacute;tems</th>
                            <th>Remitente</th>
                            <th>Destinatario</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transferenciasPendientes as $t)
                            <tr>
                                <td>{{ $t->codigo }}</td>
                                <td>{{ $t->fecha?->format('d/m/Y') }}</td>
                                <td>{{ optional($t->depositoOrigen)->nombre }}</td>
                                <td>{{ $t->articulos->count() }}</td>
                                <td>{{ optional($t->usuarioOrigen)->nombre ?? '—' }}</td>
                                <td>{{ optional($t->usuarioDestino)->nombre ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div>
        <h5 class="mb-2">Historial de asignaciones y desasignaciones</h5>
        <p class="text-muted small">
            Incluye movimientos con cantidad positiva (asignaci&oacute;n) y negativa (desasignaci&oacute;n / movimiento inverso).
        </p>
        @if (($historial ?? collect())->isNotEmpty())
            <div class="table-responsive">
                <table class="table table-sm table-striped table-bordered" id="tabla-historial-bien-uso">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Fecha</th>
                            <th>Efecto</th>
                            <th>SKU</th>
                            <th>Art&iacute;culo</th>
                            <th class="text-right">Cantidad</th>
                            <th>Transferencia</th>
                            <th>Mov. stock</th>
                            <th>Concepto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($historial as $row)
                            @php $cantidad = (float) ($row->cantidad ?? 0); @endphp
                            <tr>
                                <td>{{ $row->fecha ? \Carbon\Carbon::parse($row->fecha)->format('d/m/Y') : '' }}</td>
                                <td>
                                    @if ($cantidad >= 0)
                                        <span class="badge badge-success">Asignaci&oacute;n</span>
                                    @else
                                        <span class="badge badge-warning">Desasignaci&oacute;n</span>
                                    @endif
                                </td>
                                <td>{{ $row->sku }}</td>
                                <td>{{ $row->articulo_descripcion }}</td>
                                <td class="text-right">{{ number_format(abs($cantidad), 4, ',', '.') }}</td>
                                <td>{{ $row->transferencia_codigo ?? '—' }}</td>
                                <td>{{ $row->movimiento_codigo ?? '—' }}</td>
                                <td>{{ $row->concepto }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted mb-0">A&uacute;n no hay movimientos registrados para este bien.</p>
        @endif
    </div>
@else
    <p class="text-muted mb-0">Sin asignaciones ni historial de movimientos para este bien.</p>
@endif
