//script para editar ordenes de trabajo

var tbl_medidas;
var medidas=[];
var cantidades=[];
var precios=[];
var talles_txt;
var medidas_txt;
var precios_txt;
var tallesid_txt;
var cantidadmodal_txt;
var flAnulacionItem = false;
var moduloElegido_id;
var precio;
var ptr_cantidad;
var pedido_combinacion_id;
var cliente_id;
var modulo_actual = 1;
var ordentrabajo_id;
var nombrecliente;
var itemFacturar;
var descuentoPie;
    
function sub()
{
    $('#form-general').submit();
}

$(function () {
    $('.editar').on('click', function (event) {
        ptr_cantidad = this;
        editarPedido(this);
    });
    $('.facturar').on('click', function (event) {
        ptr_cantidad = this;
        facturarPedido(this);
    });
    $('.botonempacar').on('click', function (event) {
        color = $(this).css('color');

        if (color == "rgb(255, 0, 0)")
            alert("No puede empacar una OT ya procesada");
        else
        {
            ptr_cantidad = this;
            empacarPedido(this);
        }
    });

    // Chequea color del boton de armado en funcion de tareas ya realizadas
    colorBotonEmpacar();

    estadoOt();
    tipoOt();
});

function editarPedido(item)
{
    pedido_combinacion_id = $(item).parents("tr").find(".id").val();

    cantidad = $(item).parents("tr").find(".pares").val();

    articulo_id = $(item).parents("tr").find(".articulo_id").val();
    descripcion_articulo = $(item).parents("tr").find(".articulo").val();
    modulo_id = $(item).parents("tr").find(".modulo_id").val();
    combinacion_id = $(item).parents("tr").find(".combinacion_id").val();
    nombre_combinacion = $(item).parents("tr").find(".nombre_combinacion").val();
    cliente_id = $(item).parents("tr").find(".cliente_id").val();

    // Lee tabla de medidas
    var val_medida = $(item).parents("tr").find(".medidas").val();

    medidas=[];
    cantidades=[];
    precios=[];

    if (val_medida != '')
    {
        var tbl_medidas = JSON.parse(val_medida);
        
        $.each(tbl_medidas, function(index,value){
            medidas.push(value.talle);
            cantidades.push(value.cantidad);
            precios.push(value.precio);
        });
    }
    
    completarTalles(modulo_id, 'cantidadesportalleedit');

    setTimeout(() => {
        $("#medidasModal").modal('show');
    }, 300);
}

function facturarPedido(item)
{
    itemFacturar = item;
    pedido_combinacion_id = $(item).parents("tr").find(".id").val();
    ordentrabajo_id = $('#ordentrabajo_id').val();
    descripcion_articulo = $(item).parents("tr").find(".articulo").val();
    nombre_combinacion = $(item).parents("tr").find(".nombre_combinacion").val();
    cantidad = $(item).parents("tr").find(".pares").val();

    articulo_id = $(item).parents("tr").find(".articulo_id").val();
    modulo_id = $(item).parents("tr").find(".modulo_id").val();
    combinacion_id = $(item).parents("tr").find(".combinacion_id").val();
    cliente_id = $(item).parents("tr").find(".cliente_id").val();
    nombrecliente = $(item).parents("tr").find(".cliente").val();
    estadocliente = $(item).parents("tr").find(".estadocliente").val();
    descuentoPie = $(item).parents("tr").find(".descuentopie").val();
    tiposuspensioncliente_id = $(item).parents("tr").find(".tiposuspensioncliente_id").val();
    nombretiposuspensioncliente = $(item).parents("tr").find(".nombretiposuspensioncliente").val();

    // No deja factura cliente stock
    if (cliente_id == CLIENTE_STOCK_ID)
    {
        alert("No puede facturar cliente STOCK");
        return;
    }
    // Debe chequear estado del cliente
    if (estadocliente > '0' && 
        (tiposuspensioncliente_id == PROFORMA ||
        tiposuspensioncliente_id == MOROSO ||
        tiposuspensioncliente_id == NO_FACTURAR
        ))
    {
        alert("No puede facturar cliente en estado "+nombretiposuspensioncliente);
        return;
    }

    // Debe chequear que no este facturada la orden de trabajo
    flFacturada = false;
    flTerminada = false;
    flEmpacada = true;
    $(".pedido_id").each(function(){
            if ($(this).val() == pedido_combinacion_id && $(this).parents("tr").find(".tarea_id").val() == 
                tareaFacturada)
                flFacturada = true;
            if (($(this).val() == pedido_combinacion_id || $(this).val() == '' || $(this).val() == null) && 
                ($(this).parents("tr").find(".tarea_id").val() == tareaEmpaque ||
                $(this).parents("tr").find(".tarea_id").val() == tareaTerminada ||
                $(this).parents("tr").find(".tarea_id").val() == tareaTerminadaStock))
            {
                if ($(this).parents("tr").find(".tarea_id").val() == tareaEmpaque &&
                    $(this).parents("tr").find(".hastafecha").val() === "")
                {
                    alert("tarea no empacada");
                    flEmpacada = false;
                }
                flTerminada = true;
            }
            
    });
    if (flFacturada)
    {
        alert("Orden ya facturada");
        return;
    }
    if (!flTerminada)
    {
        alert("Orden sin terminar")
        return;
    }
    if (!flEmpacada)
    {
        return;
    }
    // Lee tabla de medidas
    var val_medida = $(item).parents("tr").find(".medidas").val();

    medidas=[];
    cantidades=[];
    precios=[];

    if (val_medida != '')
    {
        var tbl_medidas = JSON.parse(val_medida);
        
        $.each(tbl_medidas, function(index,value){
            medidas.push(value.talle);
            cantidades.push(value.cantidad);
            precios.push(value.precio);
        });
    }
    
    completarTalles(modulo_id, 'cantidadesportallefact');

    setTimeout(() => {
        $("#facturarOrdenTrabajoModal").modal('show');
    }, 300);
}

// Carga modal de facturacion
$(document).on('shown.bs.modal', '#facturarOrdenTrabajoModal', function() {
    var modal = $(this);
    var ot = $('#codigoordentrabajo').val();
    let sel_puntoventa = JSON.parse(document.querySelector('#datosfactura').dataset.puntoventa);
    let sel_puntoventaremito = JSON.parse(document.querySelector('#datosfactura').dataset.puntoventa);
    let selectPuntoVenta = $('#puntoventa_id');
    let selectPuntoVentaRemito = $('#puntoventaremito_id');
    let puntoVentaDefault = $('#puntoventadefault_id').val();
    let puntoVentaRemitoDefault = $('#puntoventaremitodefault_id').val();
    let sel_tipotransaccion = JSON.parse(document.querySelector('#datosfactura').dataset.tipotransaccion);
    let selectTipoTransaccion = $('#tipotransaccion_id');
    let tipoTransaccionDefault = $('#tipotransacciondefault_id').val();
    
    if (document.querySelector('#datosfactura').dataset.incoterm !== '')
    {
        var sel_incoterm = JSON.parse(document.querySelector('#datosfactura').dataset.incoterm);
        var selectIncoterm = $('#incoterm_id');
    }
        
    if (document.querySelector('#datosfactura').dataset.formapago !== '')
    {
        var sel_formapago = JSON.parse(document.querySelector('#datosfactura').dataset.formapago);
        var selectFormapago = $('#formapago_id');
    }

    const tiempoTranscurrido = Date.now();
    const hoy = new Date(tiempoTranscurrido);

    modal.find('#fechafactura').val(hoy.toISOString().substring(0,10));
    modal.find('#nombrecliente').val(nombrecliente);
    modal.find('#descuentopie').val(descuentoPie);
    modal.find('.modal-title').text('Factura OT '+ot+' '+descripcion_articulo+' Combinacion '+nombre_combinacion);
    modal.find('#facturarMedidasModal').empty();
    modal.find('#facturarMedidasModal').append(talles_txt+medidas_txt+precios_txt+tallesid_txt);

    // Lee punto de venta si es de exportacion
    leePuntoVenta(puntoVentaDefault);
    
    // Arma select de tipos de transacciones
    selectTipoTransaccion.empty();
    selectTipoTransaccion.append('<option value="">-- Seleccionar tipo de transacción --</option>');
    $.each(sel_tipotransaccion, function(obj, item) {
        if (tipoTransaccionDefault == item.id)
            op = 'selected="selected"';
        else
            op = '';
        selectTipoTransaccion.append('<option value="' + item.id + '"'+op+'>' + item.abreviatura + '-' + item.nombre + '</option>');
    });

    // Arma select de puntos de venta
    selectPuntoVenta.empty();
    selectPuntoVenta.append('<option value="">-- Seleccionar punto de venta --</option>');
    $.each(sel_puntoventa, function(obj, item) {
        if (puntoVentaDefault == item.id)
            op = 'selected="selected"';
        else
            op = '';
        selectPuntoVenta.append('<option value="' + item.id + '"'+op+'>' + item.codigo + '-' + item.nombre + '</option>');
    });

    // Arma select de puntos de venta del remito
    selectPuntoVentaRemito.empty();
    selectPuntoVentaRemito.append('<option value="">-- Seleccionar punto de venta --</option>');
    $.each(sel_puntoventaremito, function(obj, item) {
        if (puntoVentaRemitoDefault == item.id)
            op = 'selected="selected"';
        else
            op = '';
        selectPuntoVentaRemito.append('<option value="' + item.id + '"'+op+'>' + item.codigo + '-' + item.nombre + '</option>');
    });

    // Arma select de incoterms
    if (document.querySelector('#datosfactura').dataset.incoterm !== '')
    {
        selectIncoterm.empty();
        selectIncoterm.append('<option value="">-- Seleccionar incoterm --</option>');
        $.each(sel_incoterm, function(obj, item) {
            selectIncoterm.append('<option value="' + item.id + '">' + item.nombre + '</option>');
        });
    }

    // Arma select de formas de pago
    if (document.querySelector('#datosfactura').dataset.formapago !== '')
    {
        selectFormapago.empty();
        selectFormapago.append('<option value="">-- Seleccionar forma de pago --</option>');
        $.each(sel_formapago, function(obj, item) {
            selectFormapago.append('<option value="' + item.id + '">' + item.nombre + '</option>');
        });
    }

    let _cant = 1;

    if (modulo_actual != 1)
        $("#facturarcantmodulo").val(modulo_actual);
    else
        $("#facturarcantmodulo").val(_cant);

    // Multiplica por la cantidad de modulos a cada cantidad por talle
    $("#facturarMedidasModal .cantidadesportallefact").each(function(index) {
        var cantidad = $(this).val();
            
        $(this).val(cantidad);
        
    });
    
    sumaPares('cantidadesportallefact');
    muestraTotalPares();
});

// Cierra modal medidas
$('#puntoventa_id').on('change', function () {
    let puntoventa_id = $('#puntoventa_id').val();

    // Lee punto de venta si es de exportacion
    leePuntoVenta(puntoventa_id);
});

function leePuntoVenta(puntoventa_id)
{
    var listarUri = "/anitaERP/public/ventas/chequeapuntoventa/"+puntoventa_id;

    $.get(listarUri, function(data){
        
        if (data.modofacturacion == 'E')
        {
            $('#div_formapago').show();
            $('#div_mercaderia').show();
            $('#div_incoterm').show();
            $('#div_leyendaexportacion').show();
        }
        else
        {
            $('#div_formapago').hide();
            $('#div_mercaderia').hide();
            $('#div_incoterm').hide();
            $('#div_leyendaexportacion').hide();
        }
    });
}


// Cierra modal medidas
$('#cierraFacturarOrdenTrabajoModal').on('click', function () {
});

// Acepta modal
$('#aceptaFacturarOrdenTrabajoModal').on('click', function () {
    // Factura el item
    var token = $('#csrf_token').val();
    var puntoventa_id = $('#puntoventa_id').val();
    var tipotransaccion_id = $('#tipotransaccion_id').val();
    var descuentopie = $('#descuentopie').val();
    var descuentoimportepie = $('#descuentoimportepie').val();
    var descuentolinea = $('#descuentolinea').val();
    var fechafactura = $('#fechafactura').val();
    var leyendafactura = $('#leyendafactura').val();
    var cantidadbulto = $('#cantidadbulto').val();
    var puntoventaremito_id = $('#puntoventaremito_id').val();
    var formapago_id = $('#formapago_id').val();
    var incoterm_id = $('#incoterm_id').val();
    var mercaderia = $('#mercaderia').val();
    var leyendaexportacion = $('#leyendaexportacion').val();
    let pedido_combinacion_ids = [];
    let ordentrabajo_ids = [];

    pedido_combinacion_ids.push(pedido_combinacion_id);
    ordentrabajo_ids.push(ordentrabajo_id);

    $.post("/anitaERP/public/ventas/facturarItemOt",
            {
                pedido_combinacion_id: pedido_combinacion_ids,
                ordentrabajo_id: ordentrabajo_ids,
                tipotransaccion_id: tipotransaccion_id,
                puntoventa_id: puntoventa_id,
                fechafactura: fechafactura,
                descuentopie: descuentopie,
                descuentoimportepie: descuentoimportepie,
                descuentolinea: descuentolinea,
                leyendafactura: leyendafactura,
                cantidadbulto: cantidadbulto,
                puntoventaremito_id: puntoventaremito_id,
                formapago_id: formapago_id,
                incoterm_id: incoterm_id,
                mercaderia: mercaderia,
                leyendaexportacion: leyendaexportacion,
                _token: token
            },
            function(data, status){
                if (data.error != '')
                    alert(data.error);
                else
                {
                    alert("Factura Número: " + data.factura + "\nEstado: " + status);
                    $("#facturarOrdenTrabajoModal").modal('hide');
                    $(itemFacturar).parents("tr").find(".facturar").css( "color", "red");
                    completarTareas(ordentrabajo_id);
                }
            });
});

function empacarPedido(item)
{
    var pedido_combinacion_id = $(item).parents("tr").find(".id").val();
    var ordentrabajo_id = $('#ordentrabajo_id').val();
    var codigoordentrabajo = $('#codigoordentrabajo').val();
    var cliente = $(item).parents("tr").find(".cliente").val();
    var nombretiposuspensioncliente = $(item).parents("tr").find(".nombretiposuspensioncliente").val();
    var pedido = $(item).parents("tr").find(".codigo").val();
    var articulo = $(item).parents("tr").find(".articulo").val();
    var sku = $(item).parents("tr").find(".sku").val();
    var combinacion = $(item).parents("tr").find(".nombre_combinacion").val();
    var medidas = $(item).parents("tr").find(".medidas").val();
    var pares = $(item).parents("tr").find(".pares").val();
    var operacion_id = 1; // Inicio de operacion

	// Graba la tarea de armado
    var token = $('#csrf_token').val();

    // Controla secuencia
    var listarUri = "/anitaERP/public/produccion/ctrlsecuencia/"+codigoordentrabajo+"/"+operacion_id+"/"+tareaEmpaque+"/"+pedido_combinacion_id;

    $.get(listarUri, function(data){
        if (data.resultado == '0')
        {
            alert('No tiene el armado iniciado');
            flError = true;
        }
        else
        {
            $.post("/anitaERP/public/produccion/empacarTarea",
            {
                pedido: pedido,
                pedido_combinacion_id: pedido_combinacion_id,
                ordentrabajo_id: ordentrabajo_id,
                codigoordentrabajo: codigoordentrabajo,
                cliente: cliente,
                pedido: pedido,
                articulo: articulo,
                sku: sku,
                combinacion: combinacion,
                medidas: medidas,
                pares: pares,
                nombretiposuspensioncliente: nombretiposuspensioncliente,
                _token: token
            },
            function(data, status){
                alert("Datos: " + data + "\nEstado: " + status);
                $(item).parents("tr").find(".botonempacar").css( "color", "red");
                completarTareas(ordentrabajo_id);
            });
        }
    });
}

$(".modulo").on('change', function() {
    modulo_id = $(this).parents("tr").find(".modulo").val();
    moduloElegido_id = modulo_id;
});

// Controla apertura modal de medidas
$('#medidasModal').on('show.bs.modal', function (event) {
      var modal = $(this);

      modal.find('.modal-title').text('Medidas item '+descripcion_articulo+' Combinacion '+nombre_combinacion+' Modulo '+nombre_modulo);
      modal.find('#medidasModal').empty();
      modal.find('#medidasModal').append(talles_txt+medidas_txt+precios_txt+tallesid_txt);
    sumaPares('cantidadesportalleedit');
    muestraTotalPares();
});

// Autofocus en modal de medidas
$(document).on('shown.bs.modal', '.modal', function() {
    // Si es modulo manual hace foco en cantidades 
    if (moduloElegido_id == 30)
        $(this).find('[autofocus]').focus();
    else
        $("#cantmodulo").focus();

    var _cant = 1;

    if (modulo_actual != 1)
        $("#cantmodulo").val(modulo_actual);
    else
        $("#cantmodulo").val(_cant);
    $("#cantmodulo").off('change');

    $("#cantmodulo").on('change', function () {

        // Multiplica por la cantidad de modulos a cada cantidad por talle
        $("#medidasModal .cantidadesportalleedit").each(function(index) {
            var cantidad = $(this).val();
            var cantmodulo = $("#cantmodulo").val();
            
            if (cantidad != '')
            {
                if (modulo_actual > 0 && modulo_actual != cantmodulo)
                {
                    var cantidad_base = parseFloat(cantidad) / modulo_actual;
                    var nueva_cantidad = cantidad_base * parseFloat(cantmodulo);
                }
                else	
                    var nueva_cantidad = parseFloat(cantidad)*parseFloat(cantmodulo);

                $(this).val(nueva_cantidad);
                sumaPares('cantidadesportalleedit');
                muestraTotalPares();
            }
        });

    });

});

// Cierra modal medidas
$('#cierraModal').on('click', function () {
});

// Acepta modal
$('#aceptaModal').on('click', function () {
    let jsonObject = new Array();
    let jsonMedidas = new Array();
    var totalPares;

    med = [];
    $(".medidasportalles").each(function() {
        med.push($(this).val());
    });
    talleid = [];
    $(".tallesid").each(function() {
        talleid.push($(this).val());
    });
    cant = [];
    totalPares = 0;
    $(".cantidadesportalleedit").each(function() {
        cant.push($(this).val());
        if ($(this).val() > 0)
            totalPares += parseFloat($(this).val());
    });
    prec = []
    $(".preciosportalles").each(function(){
        prec.push($(this).val());
    });

    let jsonTallesId = JSON.stringify(talleid); 

    asignaPrecio(articulo_id, jsonTallesId);
    
    // Asigna cantidad de pares
    $(ptr_cantidad).parents("tr").find(".pares").val(totalPares);

    off = 0;
    var flError = false;
    setTimeout(() => {
        for (let i in med) 
        {
            if (cant[i] == '')
                cant[i] = 0;
                
            jsonObject.push({
                medida: med[i],
                cantidad: cant[i],
                precio: dpr[i],
                listaprecio: dlp[i],
                incluyeimpuesto: dii[i],
                moneda: dmo[i],
                talle_id: talleid[i]
            });
            
            jsonMedidas.push({
                talle: talleid[i],
                cantidad: cant[i],
                precio: dpr[i],
            });
                            
            // Valida cantidades que tengan precio
            if (cant[i] > 0 && dpr[i] == 0)
            {
                flError = true;	  	
                // Pedido por gaby 27/6 porque todos los articulos de la expo no tienen precio
                //alert('Medida '+med[i]+' Cantidad '+cant[i]+' No tiene precio asignado');
            }
            if (dpr[i] > 0)
                off = i;		
        }

        let jsonString = JSON.stringify(jsonObject); 
        var ordentrabajo_id = $('#ordentrabajo_id').val();

        // Graba actualizacion del pedido
        var token = $('#csrf_token').val();
        $.post("/anitaERP/public/ventas/actualizarpedido",
                  {
                    pedido_combinacion_id: pedido_combinacion_id,
                    ordentrabajo_id: ordentrabajo_id,
                    cliente_id: cliente_id,
                    data: jsonString,
                    _token: token
                  },
                  function(data, status){
                    alert("Datos: " + data + "\nEstado: " + status);
                    
                    // Actualiza cantidades en medidas
                    let jsonStringMedidas = JSON.stringify(jsonMedidas);
                    $(ptr_cantidad).parents('tr').find('.medidas').val(jsonStringMedidas);
                  });
    }, 300);

    $('#medidasModal').modal('hide');

});

$('#medidasModal').on('hidden.bs.modal', function () {
    // Inicializa variables modal
    talles_txt = "";
    medidas_txt = "";
    precios_txt = "";
    tallesid_txt = "";
});

function asignaPrecio(Particulo_id, Ptalle_id)
{
    // Lee talles del modulo
    $.get('/anitaERP/public/stock/asignaprecio/'+Particulo_id+'/'+Ptalle_id, function(data){
           var prec = $.map(data, function(value, index){
               return [value];
           });
        dpr=[];
        dlp=[];
        dii=[];
        dmo=[];
        $.each(prec, function(index,value){
            dpr.push(value.precio);
            dlp.push(value.listaprecio_id);
            dii.push(value.incluyeimpuesto);
            dmo.push(value.moneda_id);
        });
    });
    setTimeout(() => {
        return(precio);
    }, 300);
}

function completarTalles(modulo_id, clasemedida)
{
    talles_txt = "";
    medidas_txt = "";
    precios_txt = "";
    tallesid_txt = "";
    nombre_modulo = "";

    // Lee talles del modulo
    $.get('/anitaERP/public/stock/leertalles/'+modulo_id, function(data){
        var flEncontro, flHayMedidas;

        var tall = $.map(data, function(value, index){
            return [value];
        });
        talles_txt = "<table class='table-bordered table-striped'><tr>";
        medidas_txt = "<tr>";
        precios_txt = "<tr>";
        tallesid_txt = "<tr>";

        // Arma variables modal
        cantidadmodal_txt = " autofocus ";
        $.each(tall, function(index,value){
            nombre_modulo = value.nombre;
            for (var t in value.talles) {
                flEncontro = false;
                flHayMedidas = false;
                for (let s in medidas) 
                {
                    flHayMedidas = true;
                    if (parseFloat(value.talles[t].id) === parseFloat(medidas[s]))
                    {
                        var cant = parseFloat(cantidades[s]);
                        var prec = parseFloat(precios[s]);

                        // Calcula modulo actual
                        if (value.talles[t].pivot.cantidad != 0) 
                            modulo_actual = cant / value.talles[t].pivot.cantidad;

                        agregaMedida(value.talles[t].nombre, cant, prec, value.talles[t].id,
                                clasemedida);
                        flEncontro = true;
                        break;
                    }
                }
                if (!flEncontro)
                {
                    if (flHayMedidas)
                        agregaMedida(value.talles[t].nombre, '', 0, value.talles[t].id,
                            clasemedida);
                    else
                        agregaMedida(value.talles[t].nombre, (value.talles[t].pivot.cantidad == 0 ? '' : value.talles[t].pivot.cantidad), 0, value.talles[t].id,
                            clasemedida);
                }
            }
        });
        talles_txt = talles_txt + "</tr>";
        medidas_txt = medidas_txt + "</tr>";
        precios_txt = precios_txt + "</tr>";
        tallesid_txt = tallesid_txt + "</tr>";
    });
}

function agregaMedida(Ptalle, Pcant, Pprec, Ptalle_id, Pclasemedida)
{
    talles_txt = talles_txt + "<th><input name='medidasportalles[]' class='medidasportalles' style='width:30px; text-align:center; background-color   : #D2D8DC;' type='text' readonly value='"+Ptalle+"'></input></th>";

    if (!flAnulacionItem)
        medidas_txt = medidas_txt + "<th><input name='cantidadesportalles[]' "+cantidadmodal_txt+" class='"+Pclasemedida+"' style='width:30px;' type='text' value='"+Pcant+"'></input></th>";
    else
        medidas_txt = medidas_txt + "<th><input name='cantidadesportallesa[]' "+cantidadmodal_txt+" class='"+Pclasemedida+"a' style='width:30px;' type='text' value='"+Pcant+"'></input></th>";

    precios_txt = precios_txt + "<th><input name='preciosportalles[]' class='preciosportalles' type='hidden' value='"+Pprec+"'></input></th>";
    tallesid_txt = tallesid_txt + "<th><input name='tallesid[]' class='tallesid' type='hidden' value='"+Ptalle_id+"'></input></th>";
    cantidadmodal_txt = "";
}

function sumaPares(clase)
{
    totPares = 0;

    $("."+clase).each(function() {
        if (parseFloat($(this).val()) >= 1 && parseFloat($(this).val()) <= 999999)
            totPares += parseFloat($(this).val());
    });
}

function muestraTotalPares()
{
    $("#totPares").val(totPares.toFixed(0));
    $("#facturartotpares").val(totPares.toFixed(0));
}

function TotalParesPedido()
{
    totPares = 0;

    $(".cantidad").each(function() {
        if (parseFloat($(this).val()) >= 1 && parseFloat($(this).val()) <= 999999)
            totPares += parseFloat($(this).val());
    });
    $("#totalparespedido").val(totPares.toFixed(0));
}

function completarTareas(ordentrabajo_id)
{
    tareas_txt = "<thead><tr><th style='width: 15%;'>Id</th><th style='width: 30%;'>Tarea</th><th style='width: 20%;'>Inicio</th><th style='width: 20%;'>Fin</th></tr></thead><tbody id='tbody-tarea'>";
    
    // Lee tareas
    $.get('/anitaERP/public/produccion/leerTareas/'+ordentrabajo_id, function(data){
        var tarea = $.map(data, function(value, index){
            return [value];
        });
        
        // Arma tabla de tareas
        $.each(tarea, function(index,value){
            tareas_txt = tareas_txt + "<tr class='item-tarea'>";
            tareas_txt = tareas_txt + "<td><input style='width: 100%;' class='pedido_id' type='text' value='"+value.pedido_combinacion_id+"' readonly></td>";
            tareas_txt = tareas_txt + "<td><input class='tarea_id' type='hidden' value='"+value.tareas.id+"'>";
            tareas_txt = tareas_txt + "<input style='width: 100%;' type='text' value='"+value.tareas.nombre+"' readonly></td>";
            tareas_txt = tareas_txt + "<td><input style='width: 100%;' type='date' class='desdefecha' name='desdefechas[]' value='"+value.desdefecha+"' readonly></td>";
            tareas_txt = tareas_txt + "<td><input style='width: 100%;' type='date' class='hastafecha' name='hastafechas[]' value='"+value.hastafecha+"' readonly></td>";
            tareas_txt = tareas_txt + "</tr>";
        });

        // Asigna al html
        $('#tareasordentrabajo-table').empty().append(tareas_txt);
    });
}

function colorBotonEmpacar()
{
    $(".botonempacar").each(function(){
            boton = this;
            pedido_combinacion_id = $(this).parents("tr").find(".id").val();

            // Busca en cada OT/pedido si tiene tarea de empaque
            $(".pedido_id").each(function(){
                if ($(this).val() == pedido_combinacion_id && $(this).parents("tr").find(".tarea_id").val() == tareaEmpaque)
                    $(boton).css("color", "red");
            })
        });
}

function estadoOt()
{
    nombreTarea = "PENDIENTE";

    // Busca en cada OT/pedido si tiene tarea de armado
    $(".tarea").each(function(){
        nombreTarea = $(this).val();
    });

    $("#estado").val(nombreTarea);
}

function tipoOt()
{
    var tipoOt = "CLIENTE";

    // Busca pedidos para ver si es boletas juntas
    var cantPedido = 0;
    $(".botonempacar").each(function(){
            cantPedido++;
    });

    // Si hay mas de un pedido en la misma OT es boletas juntas
    if (cantPedido > 1)
        tipoOt = "BOLETAS JUNTAS";

    // Chequea si es de stock con la 2da tarea como terminada
    var nroTarea = 0;
    $(".tarea_id").each(function(){
        nroTarea++;

        // Si la tarea es la 2da. y es la tarea terminada es de stock
        if (nroTarea == 2 && 
            ($(this).val() == tareaTerminada || $(this).val() == tareaTerminadaStock))
            tipoOt = tipoOt + " STOCK";

    });
    $("#tipoot").val(tipoOt);
}
