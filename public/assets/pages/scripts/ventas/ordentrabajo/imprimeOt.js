
    function imprimeOt(id) {
        var listarUri = "/anitaERP/public/ventas/crearemisionot";

        if (id == 0)
            alert("No puede listar OT");
        else
            $.post(listarUri, {_token: $('#csrf_token').val(), ordenestrabajo: id, tipoemision: "COMPLETA"}, function(data){ });
    }

