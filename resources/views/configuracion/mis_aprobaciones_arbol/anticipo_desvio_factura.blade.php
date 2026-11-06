@extends("theme.$theme.layout")
@section('titulo', 'Desvío de anticipo')

@section('contenido')
<div class="container-fluid">
    <div class="card card-primary">
        <div class="card-header">
            <h3 class="card-title">Factura de anticipo fuera de tolerancia</h3>
            <div class="card-tools">
                <a href="{{ $urlBandeja }}" class="btn btn-outline-info btn-sm">
                    <i class="fa fa-reply-all"></i> Mis aprobaciones
                </a>
            </div>
        </div>
        <div class="card-body">
            @include('includes.form-error')
            @include('includes.mensaje')

            @php
                $factura = $comprobante;
            @endphp
            <p>
                El importe de la factura de anticipo no coincide con el % contractual de la OC.
                Si liberás, se contabiliza. Si rechazás, queda en borrador.
            </p>

            <table class="table table-sm table-bordered">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th>Comprobante</th>
                        <th>Dato</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Factura {{ $factura->letra }} {{ $factura->sucursal }}-{{ $factura->numerocomprobante }}</td>
                        <td>
                            {{ optional($factura->proveedores)->nombre }}
                            · Total {{ number_format((float) $factura->total, 2, ',', '.') }}
                            · {{ optional($factura->empresas)->nombre }}
                        </td>
                        <td>
                            <a class="text-primary" href="{{ $url_factura }}" target="_blank" rel="noopener">Abrir</a>
                        </td>
                    </tr>
                    @if ($url_oc)
                        <tr>
                            <td>Orden de compra {{ optional($factura->ordencompras)->numeroordencompra }}</td>
                            <td>
                                % anticipo {{ number_format((float) (optional($fila)->pct_anticipo_oc ?? optional($factura->ordencompras)->pct_anticipo ?? 0), 2, ',', '.') }}%
                            </td>
                            <td>
                                <a class="text-primary" href="{{ $url_oc }}" target="_blank" rel="noopener">Abrir</a>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>

            @if ($fila)
                <h5>Desvío</h5>
                <table class="table table-sm table-bordered">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="text-right">Total OC</th>
                            <th class="text-right">Esperado</th>
                            <th class="text-right">Ingresado</th>
                            <th class="text-right">Desvío %</th>
                            <th class="text-right">Tolerancia %</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="text-right">{{ number_format((float) $fila->total_oc, 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) $fila->importe_esperado, 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) $fila->importe_ingresado, 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) $fila->desvio_pct, 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) $fila->tolerancia_pct, 2, ',', '.') }}</td>
                            <td>{{ $fila->estado }}</td>
                        </tr>
                    </tbody>
                </table>
            @endif
        </div>
        @if ($pendiente)
            <div class="card-footer">
                <form action="{{ $urlAprobar }}" method="POST" class="d-inline" data-enter-campos="1"
                      onsubmit="return confirm('Se contabiliza la factura.');">
                    @csrf
                    <div class="form-group mb-2">
                        <label for="motivo_aprobar" class="control-label">Motivo de la liberación</label>
                        <input type="text" name="motivo" id="motivo_aprobar" class="form-control form-control-sm" required maxlength="500"
                               placeholder="Motivo obligatorio">
                    </div>
                    <button type="submit" class="btn btn-success btn-sm">Liberar y contabilizar</button>
                </form>
                <form action="{{ $urlRechazar }}" method="POST" class="d-inline mt-2" data-enter-campos="1"
                      onsubmit="return confirm('La factura queda en borrador.');">
                    @csrf
                    <div class="form-group mb-2">
                        <label for="motivo_rechazar" class="control-label">Motivo del rechazo</label>
                        <input type="text" name="motivo" id="motivo_rechazar" class="form-control form-control-sm" required maxlength="500"
                               placeholder="Motivo obligatorio">
                    </div>
                    <button type="submit" class="btn btn-outline-danger btn-sm">Rechazar</button>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection
