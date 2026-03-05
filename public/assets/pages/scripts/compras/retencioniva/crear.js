
        $(function () {
            habilitaBase();

            $('#agrega_renglon').on('click', agregaRenglon);
            $(document).on('click', '.eliminar', borraRenglon);
            $(document).on('change', '#formacalculo', habilitaBase);
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

        function habilitaBase(){
            var formaCalculo = $('#formacalculo').val();

            if (formaCalculo == 'O')
            {
                $('#divbaseimponible').show();
                $('#divcantidadperiodoacumula').show();
                $('#divvalorunitario').show();
            }
            else
            {
                $('#divbaseimponible').hide();
                $('#divcantidadperiodoacumula').hide();
                $('#divvalorunitario').hide();
            }
        }

