
        $(function () {
            habilitaGuia();

            $('#agrega_renglon').on('click', agregaRenglon);
            $(document).on('click', '.eliminar', borraRenglon);
            $(document).on('change', '#tipoguia', habilitaGuia);
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

            $("#tbody-tabla .iiitem").each(function() {
                $(this).val(item++);
            });
        }

        function habilitaGuia(){
            var tipoguia = $('#tipoguia').val();

            if (tipoguia != 'R')
            {
                $('#divcarnetguia').show();
            }
            else
            {
                $('#divcarnetguia').hide();
            }
        }

