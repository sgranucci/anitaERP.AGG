<div class="card form6" style="display: none">
    <h3>Comprobantes</h3>
    <div class="card-body">
        <table class="table" id="ordenventa-comprobante-table">
            <thead>
                <tr>
                    <th style="width: 10%;">ID</th>
                    <th style="width: 15%;">Comprobante</th>
                    <th style="width: 10%;">Fecha</th>
                    <th style="width: 10%;">Fecha de Vto.</th>
                    <th style="width: 8%;">Moneda</th>
                    <th style="width: 15%; text-align: right;">Monto total</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tbody-ordenventa-comprobante-table" class="container-comprobante">
            </tbody>
        </table>
        @include('ordenventa.ordenventa.template6')
    </div>
</div>

