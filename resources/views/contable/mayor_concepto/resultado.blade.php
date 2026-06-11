@extends("theme.$theme.layout")
@section('titulo')
    Mayor por concepto — resultado
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        <div class="card card-outline card-info">
            <div class="card-header">
                <h3 class="card-title">Movimientos contables por concepto</h3>
                <div class="card-tools">
                    <a href="{{ route('mayor_concepto') }}" class="btn btn-sm btn-default">
                        <i class="fa fa-arrow-left"></i> Nueva consulta
                    </a>
                </div>
            </div>
            <div class="card-body">
                <p class="mb-1"><strong>Empresa:</strong> {{ $empresa->nombre ?? $empresa->id }}</p>
                <p class="mb-1"><strong>Período:</strong> {{ $periodo_texto }}</p>
                <p class="mb-1"><strong>Expresado en:</strong> {{ $moneda->nombre }} ({{ $moneda->abreviatura }})
                    @if ($solo_moneda_origen)
                        — solo moneda origen
                    @else
                        — todas las monedas convertidas
                    @endif
                </p>
                <p class="mb-3 text-muted">
                    {{ $resultado['stats']['subdiario_filas'] ?? 0 }} movimientos subdiario ·
                    {{ $resultado['stats']['auxpag_filas'] ?? 0 }} auxpag ·
                    {{ $resultado['stats']['operaciones_procesadas'] ?? 0 }} operaciones procesadas ·
                    {{ $resultado['totales']['lineas'] ?? 0 }} líneas imputadas
                </p>

                @if (!empty($resultado['errores_bridge']))
                    <div class="alert alert-warning">
                        <strong>Advertencias bridge Anita:</strong>
                        <ul class="mb-0">
                            @foreach ($resultado['errores_bridge'] as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @forelse ($resultado['secciones'] ?? [] as $seccion)
                    <h5 class="mt-4 mb-2 border-bottom pb-1">
                        Concepto: {{ $seccion['concepto_id'] }}
                        {{ $seccion['concepto_nombre'] }}
                    </h5>

                    @foreach ($seccion['cuentas'] as $cuentaBlock)
                        <p class="mb-1">
                            <strong>Cuenta:</strong>
                            {{ $cuentaBlock['cuenta_codigo'] }}
                            {{ $cuentaBlock['cuenta_nombre'] }}
                        </p>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-striped">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Cuenta</th>
                                        <th>Descripción</th>
                                        <th>Fecha</th>
                                        <th>N.Asi.</th>
                                        <th>Tip</th>
                                        <th>Comprobante</th>
                                        <th>Descripción mov.</th>
                                        <th>Mon</th>
                                        <th class="text-right">Debe</th>
                                        <th class="text-right">Haber</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($cuentaBlock['lineas'] as $ln)
                                        <tr>
                                            <td>{{ $ln['cuenta_codigo'] }}</td>
                                            <td>{{ $ln['cuenta_nombre'] }}</td>
                                            <td>{{ $ln['fecha_fmt'] }}</td>
                                            <td>{{ $ln['nro_asiento'] }}</td>
                                            <td>{{ $ln['tipo_comp'] }}</td>
                                            <td>{{ $ln['comprobante'] }}</td>
                                            <td>{{ $ln['descripcion'] }}</td>
                                            <td>{{ $ln['moneda_abrev'] }}</td>
                                            <td class="text-right">{{ number_format($ln['debe'], 2, ',', '.') }}</td>
                                            <td class="text-right">{{ number_format($ln['haber'], 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="font-weight-bold bg-light">
                                        <td colspan="8">Total cuenta {{ $cuentaBlock['cuenta_codigo'] }}</td>
                                        <td class="text-right">{{ number_format($cuentaBlock['total_debe'], 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format($cuentaBlock['total_haber'], 2, ',', '.') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    @endforeach
                @empty
                    <div class="alert alert-info">No se generaron imputaciones para el período seleccionado.</div>
                @endforelse

                <p class="mt-3">
                    <strong>Totales reporte:</strong>
                    Debe {{ number_format($resultado['totales']['debe'] ?? 0, 2, ',', '.') }}
                    · Haber {{ number_format($resultado['totales']['haber'] ?? 0, 2, ',', '.') }}
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
