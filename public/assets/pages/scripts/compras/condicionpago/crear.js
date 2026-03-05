
        $(function () {
            $('#agrega_renglon').on('click', agregaRenglon);
            $(document).on('click', '.eliminar', borraRenglon);
            $(document).on('change', '.tipoplazo', habilitaFecha);
        });

        function agregaRenglon(){
            event.preventDefault();
            var renglon = $('#template-renglon').html();

            $("#tbody-tabla").append(renglon);
            actualizaRenglones();
        }

        function borraRenglon() {
            event.preventDefault();
            $(this).parents('tr').remove();
            actualizaRenglones();
        }

        function actualizaRenglones() {
            var item = 1;

            $("#tbody-tabla .iicuota").each(function() {
                $(this).val(item++);
            });
        }

        function habilitaFecha(){
            var tipoplazo = $(this).val();
            let fechavencimiento = $(this).closest('.item-cuota').find('.fechavencimiento');
            let plazo = $(this).closest('.item-cuota').find('.plazo');

            if (tipoplazo == 'F')
                fechavencimiento.prop("readonly", false);
            else
                fechavencimiento.prop("readonly", true);

            if (tipoplazo == 'D')
                plazo.prop("readonly", false);
            else
                plazo.prop("readonly", true);
        }

