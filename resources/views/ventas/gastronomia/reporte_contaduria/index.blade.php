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
                                Un zip con canje (500, 1500 y 1501), invitaciones (20, 30, 40, 50, 60, 70, 120 y 140)
                                y consumo personal (staff 80-105, managers y venta de comedor).
                                Cada c&oacute;digo queda abierto por alimento, bebida y cigarrillo.
                            </p>
                        </div>
                    </div>

                    <hr>

                    <div class="form-group row">
                        <label for="fecha_cierre" class="col-lg-2 control-label text-right pr-2 requerido">Fecha de cierre</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_cierre" id="fecha_cierre" class="form-control" value="{{ $fecha_cierre ?? '' }}" required>
                        </div>
                        <label for="depositos" class="col-lg-2 control-label text-right pr-2 requerido">Dep&oacute;sitos</label>
                        <div class="col-lg-3">
                            <input type="text" name="depositos" id="depositos" class="form-control" value="{{ $depositos ?? '1, 8' }}" required>
                            <small class="form-text text-muted">C&oacute;digos separados por coma. Por defecto 1 y 8.</small>
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
                                &Uacute;ltimo recuento toma la cantidad contada de los recuentos cerrados de la fecha m&aacute;s reciente de cada dep&oacute;sito, hasta esa fecha. Si ese d&iacute;a hay varios, entran todos. El Excel dice cu&aacute;l us&oacute;.
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
                                Otro Excel: subtotal por rubro y cada dep&oacute;sito abierto en solapa
                                (alimento, bebidas, cigarrillos, descartables, farmacia, librer&iacute;a y limpieza),
                                valorizado al precio de &uacute;ltima compra. El encabezado indica si sali&oacute; del saldo vigente o del &uacute;ltimo recuento.
                            </p>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
