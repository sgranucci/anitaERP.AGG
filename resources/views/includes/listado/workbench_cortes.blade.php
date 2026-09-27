{{-- Pack C: panel de cortes / totales por agrupación (universo filtrado).
     Mismo control que mayores e IVA: botón con texto + chevron que rota. --}}
@php
    $cortes = $cortes ?? ['activo' => false];
    $mostrarCortes = ! empty($cortes['activo']) && ! empty($cortes['filas']);
@endphp
@if ($mostrarCortes)
    <div class="lw-cortes card card-outline card-info mb-2" id="lw-cortes-panel">
        <div class="card-header py-2 px-3 d-flex flex-wrap align-items-center justify-content-between">
            <button type="button" class="btn btn-sm lw-cortes-toggle" id="btn-lw-cortes"
                    data-toggle="collapse" data-target="#lw-cortes-body"
                    aria-expanded="true" aria-controls="lw-cortes-body"
                    title="Ocultar cortes">
                <i class="fa fa-chevron-down lw-cortes-ico lw-cortes-ico-abierto" aria-hidden="true"></i>
                <i class="fa fa-chevron-right lw-cortes-ico lw-cortes-ico-cerrado" aria-hidden="true"></i>
                Cortes
            </button>
            <div class="d-flex flex-wrap align-items-center mt-1 mt-md-0" style="gap:0.35rem;">
                <span class="badge badge-info">
                    {{ (int) ($cortes['grupos_nivel0'] ?? 0) }} grupos
                    · {{ number_format((int) ($cortes['total'] ?? 0), 0, ',', '.') }} registros
                    @foreach (($cortes['medidas'] ?? []) as $medidaCorte)
                        @php $sumaMedida = (float) (($cortes['sumas_total'] ?? [])[$medidaCorte['key']] ?? 0); @endphp
                        · {{ $medidaCorte['label'] }} {{ number_format($sumaMedida, 2, ',', '.') }}
                    @endforeach
                </span>
                @if (! empty($cortes['truncado']))
                    <span class="badge badge-warning" title="Se listan los primeros {{ \App\Support\Listado\ListadoCortesSupport::MAX_FILAS }} cortes">truncado</span>
                @endif
            </div>
        </div>
        <div class="collapse show" id="lw-cortes-body">
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 180px;">
                    <table class="table table-sm table-striped mb-0 lw-cortes-table">
                        <thead style="background:#85C1E9;color:#17202A;position:sticky;top:0;z-index:1;">
                            <tr>
                                <th style="width:40px;">Niv</th>
                                <th>Grupo</th>
                                <th>Valor</th>
                                <th class="text-right" style="width:90px;">Cantidad</th>
                                @foreach (($cortes['medidas'] ?? []) as $medidaCorte)
                                    <th class="text-right" style="width:110px;">{{ $medidaCorte['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($cortes['filas'] as $filaCorte)
                                <tr class="lw-cortes-nivel-{{ (int) ($filaCorte['nivel'] ?? 0) }}">
                                    <td class="text-muted">{{ (int) ($filaCorte['nivel'] ?? 0) + 1 }}</td>
                                    <td>{{ $filaCorte['label'] ?? '' }}</td>
                                    <td style="padding-left: {{ 0.5 + ((int) ($filaCorte['nivel'] ?? 0)) * 1.1 }}rem;">
                                        {{ $filaCorte['valor'] ?? '' }}
                                    </td>
                                    <td class="text-right font-weight-bold">
                                        {{ number_format((int) ($filaCorte['count'] ?? 0), 0, ',', '.') }}
                                    </td>
                                    @foreach (($cortes['medidas'] ?? []) as $medidaCorte)
                                        <td class="text-right">
                                            {{ number_format((float) (($filaCorte['sumas'] ?? [])[$medidaCorte['key']] ?? 0), 2, ',', '.') }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var panel = document.getElementById('lw-cortes-body');
            var btn = document.getElementById('btn-lw-cortes');
            if (!panel || !btn || typeof jQuery === 'undefined') {
                return;
            }
            jQuery(panel).on('shown.bs.collapse hidden.bs.collapse', function (e) {
                if (e.target !== panel) {
                    return;
                }
                var abierto = e.type === 'shown';
                btn.title = abierto ? 'Ocultar cortes' : 'Mostrar cortes';
                btn.setAttribute('aria-expanded', abierto ? 'true' : 'false');
            });
        });
    </script>
@endif
