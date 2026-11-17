@php
    use App\Support\Contable\MayorFuenteConsultaSupport;
    $fuenteMayor = MayorFuenteConsultaSupport::normalizarModo($filtros['fuente_mayor'] ?? null);
    $cuentasMeta = $cuentas_como_debajo_limite_meta ?? [];
    if ($cuentasMeta === []) {
        $codigos = \App\Support\Contable\MayorConceptoListadoFiltros::cuentasComoDebajoLimite($filtros ?? []);
        $cuentasMeta = collect($codigos)->map(fn ($c) => [
            'codigo' => (int) $c,
            'codigo_fmt' => \App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport::formatearCodigoCuenta((int) $c),
            'nombre' => '',
        ])->all();
    }
    $limite = (int) config('contable.mayor_concepto.limite_caja_banco', 112010008);
@endphp
@if ($fuenteMayor === MayorFuenteConsultaSupport::MODO_ERP)
    <div class="px-3 py-2 border-bottom">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-0">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="collapse"
                data-target="#panel-cuentas-debajo-limite-datos" aria-expanded="false"
                id="btn-toggle-cuentas-debajo-limite-datos">
                <i class="fa fa-chevron-right"></i> Cuentas como debajo del límite
                @if ($cuentasMeta !== [])
                    <span class="badge badge-secondary ml-1">{{ count($cuentasMeta) }}</span>
                @endif
            </button>
            @if ($cuentasMeta === [])
                <span class="small text-muted">Ninguna cuenta forzada (límite {{ number_format($limite, 0, ',', '.') }})</span>
            @endif
        </div>
        <div class="collapse mt-2" id="panel-cuentas-debajo-limite-datos">
            @if ($cuentasMeta === [])
                <p class="small text-muted mb-0">
                    No hay cuentas fuera del límite tratadas como patas.
                    Cargalas en Reglas ERP (grilla + consulta F1) y volvé a consultar; se recuerdan por usuario.
                </p>
            @else
                <p class="small text-muted mb-2">
                    Estas cuentas (fuera del límite {{ number_format($limite, 0, ',', '.') }})
                    se tomaron como debajo del límite en el motor ERP:
                </p>
                <div class="table-responsive" style="max-height: 220px; overflow-y: auto;">
                    <table class="table table-sm table-bordered mb-0" style="font-size: 0.8rem;">
                        <thead style="background:#d6eaf8;">
                            <tr>
                                <th style="width: 130px;">Código</th>
                                <th>Cuenta contable</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($cuentasMeta as $cta)
                                <tr>
                                    <td><code>{{ $cta['codigo_fmt'] ?? ($cta['codigo'] ?? '') }}</code></td>
                                    <td>{{ $cta['nombre'] !== '' ? $cta['nombre'] : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
    <script>
        (function () {
            if (typeof jQuery === 'undefined') {
                return;
            }
            var KEY = 'mayor_concepto_cuentas_debajo_limite_abierto';
            var $panel = jQuery('#panel-cuentas-debajo-limite-datos');
            var $btn = jQuery('#btn-toggle-cuentas-debajo-limite-datos');
            if ($panel.length === 0 || $btn.length === 0) {
                return;
            }
            if (window.sessionStorage && sessionStorage.getItem(KEY) === '1') {
                $panel.addClass('show');
                $btn.attr('aria-expanded', 'true');
                $btn.find('i').removeClass('fa-chevron-right').addClass('fa-chevron-down');
            }
            $panel.on('show.bs.collapse', function () {
                $btn.find('i').removeClass('fa-chevron-right').addClass('fa-chevron-down');
                $btn.attr('aria-expanded', 'true');
                try { sessionStorage.setItem(KEY, '1'); } catch (e) {}
            }).on('hide.bs.collapse', function () {
                $btn.find('i').removeClass('fa-chevron-down').addClass('fa-chevron-right');
                $btn.attr('aria-expanded', 'false');
                try { sessionStorage.setItem(KEY, '0'); } catch (e) {}
            });
        })();
    </script>
@endif
