
        $(function () {
            $('#agrega_renglon').on('click', agregaRenglon);
            $(document).on('click', '.eliminar', borraRenglon);
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


