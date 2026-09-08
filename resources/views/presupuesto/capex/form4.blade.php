<div class="card card-outline card-info mb-0">
    <div class="card-header py-2 d-flex align-items-center justify-content-between flex-wrap">
        <h3 class="card-title mb-0"><i class="fa fa-shopping-cart"></i> Órdenes de Compra</h3>
        <div class="card-tools d-flex flex-nowrap">
            <a href="{{route('lista_ordencompra_capex', ['formato' => 'PDF', 'capex_id' => $data->id ?? 0])}}" class="btn btn-sm bg-danger mr-1" title="Exportar PDF">
                <i class="fas fa-file-pdf"></i> Pdf
            </a>
            <a href="{{route('lista_ordencompra_capex', ['formato' => 'EXCEL', 'capex_id' => $data->id ?? 0])}}" class="btn btn-sm bg-success mr-1" title="Exportar Excel">
                <i class="fas fa-file-excel"></i> Excel
            </a>
            <a href="{{route('lista_ordencompra_capex', ['formato' => 'CSV', 'capex_id' => $data->id ?? 0])}}" class="btn btn-sm bg-warning" title="Exportar CSV">
                <i class="fas fa-file-csv"></i> Csv
            </a>
        </div>
    </div>
    <div class="card-body p-2">
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" id="capex-ordencompra-table">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th style="width: 10%;">Fecha OC</th>
                        <th style="width: 10%;">Nro. de OC</th>
                        <th>Proveedor</th>
                        <th style="width: 5%;">Mes</th>
                        <th style="width: 8%;">Moneda</th>
                        <th style="width: 10%;">Cotización</th>
                        <th style="width: 10%;">Monto</th>
                        <th>Detalle</th>
                        <th style="width: 70px;" class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbody-capex-ordencompra-table" class="container-ordencompra">
                </tbody>
            </table>
        </div>
    </div>
</div>
