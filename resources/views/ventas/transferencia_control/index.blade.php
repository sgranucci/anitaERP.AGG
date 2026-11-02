@extends("theme.$theme.layout")

@section('titulo')
    Control transferencias
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Control de importación — Transferencias Crown</h3>
            </div>
            <form method="get" action="{{ route('transferencia_control_crown') }}" data-enter-campos="1">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body pb-2">
                    <p class="text-muted mb-3">
                        Consultá el resumen de lo transferido (comprobantes, PV, centros, cuentas y link al mayor)
                        y las incongruencias: PV sin centro o sin cuentas, asientos desbalanceados, líneas sin CC y facturas sin asiento.
                    </p>
                    <div class="form-group row mb-2">
                        <label for="desde" class="col-lg-2 control-label text-right pr-2 requerido">Desde</label>
                        <div class="col-lg-2">
                            <input type="date" name="desde" id="desde" class="form-control" required value="{{ $filtros['desde'] }}">
                        </div>
                        <label for="hasta" class="col-lg-1 control-label text-right pr-2 requerido">Hasta</label>
                        <div class="col-lg-2">
                            <input type="date" name="hasta" id="hasta" class="form-control" required value="{{ $filtros['hasta'] }}">
                        </div>
                    </div>
                    <div class="form-group row mb-2">
                        <label for="alcance" class="col-lg-2 control-label text-right pr-2">Alcance</label>
                        <div class="col-lg-3">
                            <select name="alcance" id="alcance" class="form-control">
                                <option value="todo" @selected(($filtros['alcance'] ?? '') === 'todo')>Configuración + importación</option>
                                <option value="config" @selected(($filtros['alcance'] ?? '') === 'config')>Solo configuración</option>
                                <option value="importacion" @selected(($filtros['alcance'] ?? '') === 'importacion')>Solo importación</option>
                            </select>
                        </div>
                        <label for="origen" class="col-lg-1 control-label text-right pr-2">Origen</label>
                        <div class="col-lg-3">
                            <select name="origen" id="origen" class="form-control">
                                <option value="todos" @selected(($filtros['origen'] ?? '') === 'todos')>Todos</option>
                                <option value="gastro" @selected(($filtros['origen'] ?? '') === 'gastro')>Gastronomía</option>
                                <option value="arca" @selected(($filtros['origen'] ?? '') === 'arca')>ARCA / PXSOL</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa fa-search"></i> Consultar
                    </button>
                </div>
            </form>
        </div>

        @include('ventas.transferencia_control.partials.resultado_carga', [
            'resumenCarga' => $resumenCarga ?? null,
        ])

        @if (is_array($resultado))
            @include('ventas.transferencia_control.partials.resultado_pantalla', [
                'resultado' => $resultado,
                'filas' => $filasPagina,
                'paginador' => $filasPagina,
                'tipos' => $tipos,
                'titulo' => 'Incongruencias',
                'filtrosQuery' => $filtrosQuery,
                'mostrarExport' => true,
            ])
        @endif
    </div>
</div>
@endsection
