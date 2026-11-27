<div class="px-3 pb-3">
    <a class="small" data-toggle="collapse" href="#tablero-definiciones" role="button" aria-expanded="false">
        <i class="fa fa-info-circle"></i> C&oacute;mo se calcula cada n&uacute;mero
    </a>
    <div class="collapse mt-2" id="tablero-definiciones">
        <ul class="small mb-0">
            <li><strong>OC consideradas:</strong> todas las de fecha dentro del per&iacute;odo, salvo las suspendidas. Incluye las que est&aacute;n <em>pendientes de aprobaci&oacute;n</em> (columna &laquo;Cabecera OC&raquo; del detalle): hay OC que se reciben y facturan sin haber pasado por el circuito de aprobaci&oacute;n.</li>
            <li><strong>Estado:</strong> <em>Finalizada</em> = cumplida, cerrada o recibida por completo; <em>Entrega parcial</em> = con alguna recepci&oacute;n; <em>Pendiente</em> = sin recepci&oacute;n.</li>
            <li><strong>Vencimiento:</strong> solo para OC no finalizadas; <em>Vencida</em> si la fecha de entrega ya pas&oacute;.</li>
            <li><strong>COM:</strong> recepci&oacute;n de mercader&iacute;a confirmada asociada a la OC. No es la factura: la factura del proveedor se filtra aparte (&laquo;Factura de proveedor&raquo;).</li>
            <li><strong>Tiempos (d&iacute;as corridos):</strong>
                aprobaci&oacute;n de la requisici&oacute;n = alta &rarr; &uacute;ltima aprobaci&oacute;n del &aacute;rbol (no depende del comprador);
                gesti&oacute;n del comprador = &uacute;ltima aprobaci&oacute;n de la requisici&oacute;n &rarr; emisi&oacute;n de la OC (igual que &laquo;KPIs de Compras&raquo;);
                entrega = emisi&oacute;n de la OC &rarr; primera COM; ciclo = &uacute;ltima aprobaci&oacute;n &rarr; primera COM.
                La emisi&oacute;n es la fecha de ingreso en Anita (OC nacidas all&iacute;) o la primera aprobaci&oacute;n de la OC (OC del ERP; sin aprobaci&oacute;n, su alta).
                La fecha de la OC copia la de la requisici&oacute;n y no sirve para medir.
                Solo cuentan las OC cuya requisici&oacute;n tiene una aprobaci&oacute;n real registrada: las requisiciones migradas de Anita con la aprobaci&oacute;n fechada el d&iacute;a de la carga (todo lo anterior a mayo) no tienen aprobaci&oacute;n y quedan fuera, igual que los casos con diferencia negativa. Cada promedio indica su n.</li>
            <li><strong>Por d&iacute;a:</strong> documentos por d&iacute;a h&aacute;bil (lunes a viernes) hasta hoy. Las requisiciones se filtran solo por per&iacute;odo y empresa (urgente = tratamiento &laquo;Urgente&raquo;).</li>
            <li><strong>Monto $:</strong> cantidad &times; precio &times; cotizaci&oacute;n vigente a la fecha de la OC; no descuenta bonificaciones de l&iacute;nea.</li>
            <li><strong>Ahorros:</strong> l&iacute;neas de OC con ahorro cargado (precio anterior &rarr; precio nuevo, tipo de ahorro). El % es sobre el precio anterior de esas mismas l&iacute;neas. Ver solapa Ahorros.</li>
        </ul>
    </div>
</div>
