@php
    $formatearMonto = static function ($valor) {
        if ($valor === null || $valor === '' || (float) $valor === 0.0) {
            return '';
        }

        return number_format((float) $valor, 2, ',', '.');
    };
    $agrupacion = $agrupacion_resumen ?? 'concepto_cuenta';
    $resumenConcepto = $resumen ?? [];
    $resumenCuenta = $resumen_por_cuenta ?? [];
@endphp
@if (! empty($resumenConcepto) || ! empty($resumenCuenta))
    <div class="px-3 py-2 border-bottom">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="collapse"
                data-target="#panel-resumen-mayor-concepto" aria-expanded="false"
                id="btn-toggle-resumen-mayor-concepto">
                <i class="fa fa-chevron-right"></i> Totales resumen
            </button>
            <div class="btn-group btn-group-sm mt-1 mt-md-0" role="group">
                <button type="button"
                    class="btn {{ $agrupacion === 'concepto_cuenta' ? 'btn-primary' : 'btn-outline-primary' }}"
                    onclick="cambiarAgrupacionResumen('concepto_cuenta')">
                    Por concepto → cuenta
                </button>
                <button type="button"
                    class="btn {{ $agrupacion === 'cuenta_concepto' ? 'btn-primary' : 'btn-outline-primary' }}"
                    onclick="cambiarAgrupacionResumen('cuenta_concepto')">
                    Por cuenta → concepto
                </button>
            </div>
        </div>

        <div class="collapse" id="panel-resumen-mayor-concepto">
            <div class="table-responsive resumen-mayor-concepto-tabla"
                id="resumen-tabla-concepto-cuenta"
                style="{{ $agrupacion === 'cuenta_concepto' ? 'display:none;' : '' }}">
                <table class="table table-sm table-bordered mb-0" style="font-size: 0.8rem;">
                    <thead>
                        <tr style="background-color: #d6eaf8;">
                            <th>Concepto</th>
                            <th>Nombre concepto</th>
                            <th>Cuenta</th>
                            <th>Descripción cuenta</th>
                            <th class="text-right">Líneas</th>
                            <th class="text-right">Debe</th>
                            <th class="text-right">Haber</th>
                        </tr>
                    </thead>
                    <tbody>
                        @include('contable.mayor_concepto.partials.resumen_filas', [
                            'resumen' => $resumenConcepto,
                            'agrupacion_resumen' => 'concepto_cuenta',
                            'formatearMonto' => $formatearMonto,
                            'mostrar_enlaces' => true,
                            'puede_ver_cuenta' => $puede_ver_cuenta ?? false,
                            'puede_ver_concepto' => $puede_ver_concepto ?? false,
                        ])
                    </tbody>
                </table>
            </div>
            <div class="table-responsive resumen-mayor-concepto-tabla"
                id="resumen-tabla-cuenta-concepto"
                style="{{ $agrupacion === 'cuenta_concepto' ? '' : 'display:none;' }}">
                <table class="table table-sm table-bordered mb-0" style="font-size: 0.8rem;">
                    <thead>
                        <tr style="background-color: #d6eaf8;">
                            <th>Cuenta</th>
                            <th>Descripción cuenta</th>
                            <th>Concepto</th>
                            <th>Nombre concepto</th>
                            <th class="text-right">Líneas</th>
                            <th class="text-right">Debe</th>
                            <th class="text-right">Haber</th>
                        </tr>
                    </thead>
                    <tbody>
                        @include('contable.mayor_concepto.partials.resumen_filas', [
                            'resumen' => $resumenCuenta,
                            'agrupacion_resumen' => 'cuenta_concepto',
                            'formatearMonto' => $formatearMonto,
                            'mostrar_enlaces' => true,
                            'puede_ver_cuenta' => $puede_ver_cuenta ?? false,
                            'puede_ver_concepto' => $puede_ver_concepto ?? false,
                        ])
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
        (function () {
            if (typeof jQuery === 'undefined') {
                return;
            }
            var KEY = 'mayor_concepto_resumen_abierto';
            var $panel = jQuery('#panel-resumen-mayor-concepto');
            var $btn = jQuery('#btn-toggle-resumen-mayor-concepto');
            if ($panel.length === 0 || $btn.length === 0) {
                return;
            }
            // Por defecto colapsado; solo reabrir si el usuario lo dejó abierto en esta pestaña.
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
