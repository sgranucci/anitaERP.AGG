@extends("theme.$theme.layout")

@section('titulo')
    Reporte para Contaduría
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Reporte para Contadur&iacute;a</h3>
            </div>
            <form method="get" action="{{ route('gastronomia_reporte_contaduria') }}" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Descuentos de gastronom&iacute;a del mes, con las solapas que pide contadur&iacute;a,
                        y existencias finales de inventario en un Excel aparte.
                    </p>

                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $empresa_id ?? null,
                        'required' => true,
                        'col_label' => 'col-lg-2 control-label text-right pr-2',
                        'col_input' => 'col-lg-4',
                    ])

                    @php
                        $mesesPeriodo = [
                            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
                        ];
                        $anioMin = 2015;
                        $anioMax = (int) date('Y') + 1;
                        $mesSeleccionado = (int) ($mes_num ?? 0);
                        $anioSeleccionado = (int) ($anio ?? 0);
                    @endphp
                    <div class="form-group row">
                        <label class="col-lg-2 control-label text-right pr-2 requerido" for="mes">Mes / A&ntilde;o</label>
                        <div class="col-lg-6">
                            <div class="row">
                                <div class="col-md-7">
                                    <select name="mes" id="mes" class="form-control" required title="Mes" aria-label="Mes del per&iacute;odo">
                                        @foreach ($mesesPeriodo as $num => $nombre)
                                            <option value="{{ $num }}" @selected($mesSeleccionado === $num)>
                                                {{ str_pad((string) $num, 2, '0', STR_PAD_LEFT) }} — {{ $nombre }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <select name="anio" id="anio" class="form-control" required title="A&ntilde;o" aria-label="A&ntilde;o del per&iacute;odo">
                                        @for ($y = $anioMax; $y >= $anioMin; $y--)
                                            <option value="{{ $y }}" @selected($anioSeleccionado === $y)>{{ $y }}</option>
                                        @endfor
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row mb-4">
                        <div class="col-lg-8 offset-lg-2">
                            <button type="submit"
                                class="btn btn-success"
                                formaction="{{ route('gastronomia_reporte_contaduria_descuentos') }}">
                                <i class="fa fa-file-excel-o"></i> Descargar descuentos
                            </button>
                            <p class="text-muted small mb-0 mt-2">
                                Un zip con canje (500, 1500 y 1501), invitaciones (20, 30, 40, 50, 60, 70, 120 y 140,
                                una solapa por c&oacute;digo y un subtotal que lista todos, en blanco si no hubo consumo)
                                y consumo personal. Canje e invitaciones no cambian.
                                El consumo personal toma la factura con descuento 10. La car&aacute;tula lista el staff 80-105
                                (factura m&aacute;s vianda del centro, en blanco si no hubo consumo), mandos medios y directorio
                                (solo su factura, con el centro de costo del legajo en rojo) y el comedor.
                                El detalle del staff junta la factura y la vianda en columnas. Mandos y directorio van en solapas aparte.
                            </p>
                        </div>
                    </div>

                    <hr>

                    <div class="form-group row">
                        <label for="fecha_cierre" class="col-lg-2 control-label text-right pr-2 requerido">Fecha de cierre</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_cierre" id="fecha_cierre" class="form-control" value="{{ $fecha_cierre ?? '' }}" required>
                        </div>
                        <label for="depositos" class="col-lg-2 control-label text-right pr-2">Dep&oacute;sitos</label>
                        <div class="col-lg-3">
                            <input type="text" name="depositos" id="depositos" class="form-control" value="{{ $depositos ?? '1, 8' }}">
                            <small class="form-text text-muted">C&oacute;digos separados por coma. Vac&iacute;o = todos los dep&oacute;sitos de la empresa. Biyemas suele ser 1, 8. En Kandiko y Rebisco, dejalo vac&iacute;o para que entre todo el inventario.</small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="origen_inventario" class="col-lg-2 control-label text-right pr-2 requerido">Origen</label>
                        <div class="col-lg-5">
                            <select name="origen_inventario" id="origen_inventario" class="form-control">
                                <option value="recuento" @selected(($origen_inventario ?? 'recuento') === 'recuento')>&Uacute;ltimo recuento de cada dep&oacute;sito</option>
                                <option value="saldo" @selected(($origen_inventario ?? '') === 'saldo')>Saldo vigente</option>
                            </select>
                            <small class="form-text text-muted">
                                Saldo vigente es la existencia del sistema a la fecha de cierre.
                                Recuento: los cerrados de ese d&iacute;a. Si no hay, los del d&iacute;a siguiente
                                (el 30/09/2026 toma el 1/10, RC-000160, y no un recuento posterior como el RC-000180 del 20/10).
                                Si ese d&iacute;a hay varios, entran todos. El Excel dice cu&aacute;l us&oacute;.
                            </small>
                        </div>
                    </div>

                    <div class="form-group row mb-2">
                        <div class="col-lg-8 offset-lg-2">
                            <button type="submit"
                                class="btn btn-primary"
                                formaction="{{ route('gastronomia_reporte_contaduria_inventario') }}">
                                <i class="fa fa-file-excel-o"></i> Descargar inventario
                            </button>
                            <p class="text-muted small mb-0 mt-2">
                                Un zip: un Excel de totales (sector y costo por dep&oacute;sito) y un Excel por dep&oacute;sito.
                                Cada dep&oacute;sito se abre en solapas por rubro (alimentos, bebidas, descartables, cigarrillos, limpieza y, si hay stock, farmacia, librer&iacute;a y otros).
                                Cada solapa indica el n&uacute;mero de recuento (RC-000000), igual que el listado de contadur&iacute;a.
                            </p>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
