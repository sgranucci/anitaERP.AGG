@extends("theme.$theme.layout")
@section('titulo')
    Importar ahorros
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Importar ahorros desde Excel</h3>
                <div class="card-tools">
                    <a href="{{ route('importar_ahorro_compras_plantilla') }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-download"></i> Descargar plantilla
                    </a>
                </div>
            </div>
            <div class="card-body">
                <p class="mb-2">
                    Carga el ahorro de requisiciones u &oacute;rdenes de compra que ya existen (aprobadas, con OC, cerradas).
                    Cada fila es <b>una l&iacute;nea</b>: documento + n&uacute;mero + SKU. Antes de grabar se muestra una vista previa
                    y solo se guardan las filas v&aacute;lidas. Lo que se carga en una requisici&oacute;n se copia a sus &oacute;rdenes de compra, y al rev&eacute;s.
                </p>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr><th>Columna</th><th>Obligatoria</th><th>Qu&eacute; va</th></tr>
                        </thead>
                        <tbody>
                            <tr><td>Documento</td><td>S&iacute;</td><td><code>REQ</code> (requisici&oacute;n) u <code>OC</code> (orden de compra)</td></tr>
                            <tr><td>N&uacute;mero</td><td>S&iacute;</td><td>N&uacute;mero de la requisici&oacute;n u orden de compra</td></tr>
                            <tr><td>SKU</td><td>S&iacute;</td><td>SKU del art&iacute;culo. Debe estar una sola vez en el documento</td></tr>
                            <tr><td>Precio anterior</td><td>S&iacute;</td><td>Neto de IVA, por unidad</td></tr>
                            <tr><td>Moneda</td><td>No</td><td>Abreviatura de la moneda del precio anterior. Vac&iacute;o = la de la l&iacute;nea</td></tr>
                            <tr><td>Origen</td><td>No</td><td>{{ implode(' / ', $origenes) }}</td></tr>
                            <tr><td>Referencia</td><td>No</td><td>N.&ordm; de OC, presupuesto o lista que respalda el precio anterior</td></tr>
                            <tr><td>Tipo de ahorro</td><td>No</td><td>{{ count($tipos) ? implode(' / ', $tipos) : 'Todavía no hay tipos cargados' }}</td></tr>
                            <tr><td>Observaci&oacute;n</td><td>No</td><td>Texto libre</td></tr>
                            <tr><td>Empresa</td><td>No</td><td>Solo si el mismo n&uacute;mero existe en m&aacute;s de una empresa</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted">
                    Las columnas se reconocen por el t&iacute;tulo de la primera fila. M&aacute;ximo {{ $maxFilas }} filas por archivo.
                    El ahorro se calcula con el precio y la cantidad que tiene hoy la l&iacute;nea y la cotizaci&oacute;n BNA vigente a la fecha del documento.
                    Si la l&iacute;nea ya ten&iacute;a ahorro, se reemplaza y el cambio queda en el historial de auditor&iacute;a.
                </p>
                <form method="POST" action="{{ route('importar_ahorro_compras_previsualizar') }}" enctype="multipart/form-data" class="form-inline">
                    @csrf
                    <input type="file" name="archivo" class="form-control mr-2" accept=".xlsx,.xls,.csv" required>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Previsualizar</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
