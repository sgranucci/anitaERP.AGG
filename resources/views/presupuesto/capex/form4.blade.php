<div class="card form4" style="display: none">
        <div class="col-12 d-flex justify-content-end"> 
            <a href="{{route('lista_ordencompra_capex', ['formato' => 'PDF', 'capex_id' => $data->id ?? 0])}}" class="btn btn-sm bg-danger">
                <i class="fas fa-file-pdf"></i> Pdf
            </a>
            <a href="{{route('lista_ordencompra_capex', ['formato' => 'EXCEL', 'capex_id' => $data->id ?? 0])}}" class="btn btn-sm bg-success">
                <i class="fas fa-file-excel"></i> Excel
            </a>
            <a href="{{route('lista_ordencompra_capex', ['formato' => 'CSV', 'capex_id' => $data->id ?? 0])}}" class="btn btn-sm bg-warning">
                <i class="fas fa-file-csv"></i> Csv
            </a>
        </div>
    <div class="card-body">
        <table class="table" id="capex-ordencompra-table">
            <thead>
                <tr><strong>Ordenes de Compra</strong></tr>
                <tr>
                    <th style="width: 10%;">Fecha OC</th>
                    <th style="width: 10%;">Nro. de OC</th>
                    <th>Proveedor</th>
                    <th style="width: 5%;">Mes</th>
                    <th style="width: 8%;">Moneda</th>
                    <th style="width: 10%;">Cotización</th>
                    <th style="width: 10%;">Monto</th>
                    <th>Detalle</th>
                </tr>
            </thead>
            <tbody id="tbody-capex-ordencompra-table" class="container-ordencompra">
            </tbody>
        </table>
    </div>
</div>

