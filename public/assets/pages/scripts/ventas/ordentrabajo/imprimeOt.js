
    function toastOt(msg, type) {
        var t = type || 'info';
        if (window.toastr) {
            var opts =
                t === 'success'
                    ? { timeOut: 4500, progressBar: true }
                    : { timeOut: 9000, extendedTimeOut: 4000, closeButton: true, progressBar: true };
            toastr[t](msg, '', opts);
        } else {
            alert(msg);
        }
    }

    function imprimeOt(id) {
        if (!id || id == 0) {
            toastOt('No puede listar OT', 'warning');
            return false;
        }

        $.ajax({
            url: carpetaBase + '/ventas/emisionot-impresora/' + encodeURIComponent(id),
            method: 'GET',
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        })
            .done(function (data) {
                if (data && data.ok) {
                    toastOt(data.mensaje || 'Impresión exitosa.', 'success');
                    return;
                }
                toastOt((data && data.mensaje) || 'No se pudo imprimir la OT.', 'warning');
            })
            .fail(function (xhr) {
                var msg = 'No se pudo imprimir la OT.';
                if (xhr.responseJSON && xhr.responseJSON.mensaje) {
                    msg = xhr.responseJSON.mensaje;
                }
                toastOt(msg, 'warning');
            });

        return false;
    }

    function pdfOt(id) {
        if (!id || id == 0) {
            toastOt('No puede listar OT', 'warning');
            return false;
        }
        window.open(carpetaBase + '/ventas/emisionot-pdf/' + encodeURIComponent(id), '_blank');
        return false;
    }
