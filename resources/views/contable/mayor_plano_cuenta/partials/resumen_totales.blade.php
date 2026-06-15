@if (! empty($resumen))
    <div class="px-3 py-2 border-bottom">
        <h6 class="mb-2 font-weight-bold">Totales por cuenta</h6>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" style="font-size: 0.8rem;">
                <thead>
                    <tr style="background-color: #85C1E9; color: #17202A;">
                        <th>Cuenta</th>
                        <th>Nombre</th>
                        <th class="text-right">Saldo inicial</th>
                        <th class="text-right">Debe</th>
                        <th class="text-right">Haber</th>
                        <th class="text-right">Líneas</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($resumen as $row)
                        <tr>
                            <td>{{ $row['cuenta_codigo'] ?? '' }}</td>
                            <td>{{ $row['cuenta_nombre'] ?? '' }}</td>
                            <td class="text-right">{{ number_format((float) ($row['saldo_inicial'] ?? 0), 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) ($row['total_debe'] ?? 0), 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) ($row['total_haber'] ?? 0), 2, ',', '.') }}</td>
                            <td class="text-right">{{ (int) ($row['cantidad_lineas'] ?? 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
