@extends("theme.$theme.layout")
@section('titulo')
    Facturas por canal — {{ $origenEtiqueta }}
@endsection

@section('contenido')
    @php
        use App\Support\Compras\PrecargaComprobanteEstados;
        use App\Support\Compras\ComprobanteProveedorRetornoLegajoSupport;
        $periodoQuery = array_filter([
            'fecha_desde' => $periodo['fecha_desde'] ?? null,
            'fecha_hasta' => $periodo['fecha_hasta'] ?? null,
            'preset' => $periodo['preset'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');
    @endphp
    <div class="row">
        <div class="col-lg-12">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title">
                        Precargas — {{ $origenEtiqueta }}
                        <small class="text-muted">({{ $origen }})</small>
                    </h3>
                    <div class="card-tools">
                        <a href="{{ route('ai_decision_facturas_canal', $periodoQuery) }}"
                           class="btn btn-sm btn-outline-secondary">
                            <i class="fa fa-arrow-left"></i> Volver al reporte
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        {{ $coleccion->total() }} precarga(s)
                        · {{ \Carbon\Carbon::parse($periodo['fecha_desde'])->format('d/m/Y') }}
                        → {{ \Carbon\Carbon::parse($periodo['fecha_hasta'])->format('d/m/Y') }}
                    </p>

                    @if ($coleccion->isEmpty())
                        <div class="alert alert-info mb-0">No hay precargas para este origen en el período.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover mb-0">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>ID</th>
                                        <th>Alta ERP</th>
                                        <th>Empresa</th>
                                        <th>Proveedor</th>
                                        <th>Comprobante</th>
                                        <th>Fecha fac.</th>
                                        <th>OC</th>
                                        <th class="text-right">Total</th>
                                        <th>Estado</th>
                                        <th>Flags</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($coleccion as $fila)
                                        @php
                                            $tipo = trim((string) ($fila->abreviaturatipo ?? ''));
                                            $letra = trim((string) ($fila->letra ?? ''));
                                            $suc = (int) ($fila->sucursal ?? 0);
                                            $nro = (int) ($fila->numerocomprobante ?? 0);
                                            $comp = trim(sprintf(
                                                '%s %s %04d-%08d',
                                                $tipo !== '' ? $tipo : 'FAC',
                                                $letra !== '' ? $letra : '?',
                                                $suc,
                                                $nro
                                            ));
                                            $estado = (string) ($fila->estado ?? '');
                                            $cpId = (int) ($fila->comprobante_proveedor_id ?? 0);
                                            $tienePdf = filled($fila->rutaalmacenamiento ?? null);
                                            $paraRevisar = (int) ($fila->pararevisar ?? 0) === 1;
                                            $conError = trim((string) ($fila->marca_error ?? '')) !== '';
                                        @endphp
                                        <tr>
                                            <td>{{ $fila->id }}</td>
                                            <td class="text-nowrap">
                                                {{ $fila->created_at ? \Carbon\Carbon::parse($fila->created_at)->format('d/m/Y H:i') : '—' }}
                                            </td>
                                            <td>{{ $fila->nombreempresa ?? '—' }}</td>
                                            <td>{{ $fila->nombreproveedor ?? '—' }}</td>
                                            <td class="text-nowrap">{{ $comp }}</td>
                                            <td class="text-nowrap">
                                                {{ $fila->fechafactura ? \Carbon\Carbon::parse($fila->fechafactura)->format('d/m/Y') : '—' }}
                                            </td>
                                            <td>{{ $fila->numeroordencompra ?: '—' }}</td>
                                            <td class="text-right">{{ number_format((float) ($fila->total ?? 0), 2, ',', '.') }}</td>
                                            <td>
                                                <span class="badge badge-secondary">
                                                    {{ PrecargaComprobanteEstados::etiqueta($estado) !== $estado
                                                        ? PrecargaComprobanteEstados::etiqueta($estado)
                                                        : ($estado !== '' ? $estado : '—') }}
                                                </span>
                                            </td>
                                            <td>
                                                @if ($paraRevisar)
                                                    <span class="badge badge-warning">Revisar</span>
                                                @endif
                                                @if ($conError)
                                                    <span class="badge badge-danger" title="{{ $fila->marca_error }}">Error</span>
                                                @endif
                                                @if ($cpId > 0)
                                                    <span class="badge badge-success">CP #{{ $cpId }}</span>
                                                @endif
                                            </td>
                                            <td class="text-nowrap text-center">
                                                @if ($tienePdf && puedeVerPrecargaFacturaPdf())
                                                    <a href="{{ urlAppCarpeta('compras/precarga_comprobante_proveedor/'.$fila->id.'/factura-pdf?inline=1') }}"
                                                       class="btn btn-xs btn-outline-danger"
                                                       title="Ver PDF"
                                                       target="_blank"
                                                       rel="noopener noreferrer">
                                                        <i class="fa fa-file-pdf-o"></i>
                                                    </a>
                                                @endif
                                                @if (can('editar-precarga-proveedores', false) || can('listar-precarga-proveedores', false))
                                                    <a href="{{ route('editar_precarga_comprobante_proveedor', ['id' => $fila->id]) }}"
                                                       class="btn btn-xs btn-outline-primary"
                                                       title="Abrir precarga"
                                                       target="_blank"
                                                       rel="noopener noreferrer">
                                                        <i class="fa fa-edit"></i>
                                                    </a>
                                                @endif
                                                @if ($cpId > 0 && (can('editar-comprobante-proveedor', false) || can('listar-comprobante-proveedor', false)))
                                                    <a href="{{ route('editar_comprobante_proveedor', [
                                                            'id' => $cpId,
                                                            'origen' => ComprobanteProveedorRetornoLegajoSupport::ORIGEN_PRECARGA,
                                                        ]) }}"
                                                       class="btn btn-xs btn-outline-success"
                                                       title="Abrir comprobante #{{ $cpId }}"
                                                       target="_blank"
                                                       rel="noopener noreferrer">
                                                        <i class="fa fa-external-link"></i>
                                                    </a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            {{ $coleccion->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
