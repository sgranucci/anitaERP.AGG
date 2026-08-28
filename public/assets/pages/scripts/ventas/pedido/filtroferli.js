            $(function () {
                $('#btn_advanced_filter').click(function () {
                    $('#advanced_filter_modal').modal('show');
                })

                $(".filter-combo").change(function () {
                    var n = $(this).val();
                    var p = $(this).parents('.row-filter-combo');
                    var type_data = $(this).attr('data-type');
                    var filter_value = p.find('.filter-value');

                    p.find('.between-group').hide();
                    p.find('.between-group').find('input').prop('disabled', true);
                    filter_value.val('').show().focus();
                    switch (n) {
                        default:
                            filter_value.removeAttr('placeholder').val('').prop('disabled', true);
                            p.find('.between-group').find('input').prop('disabled', true);
                            break;
                        case 'like':
                        case 'not like':
                            filter_value.attr('placeholder', 'Ej.: : Lorem ipsum').prop('disabled', false);
                            break;
                        case 'asc':
                            filter_value.prop('disabled', true).attr('placeholder', 'Ordenar Ascendentemente');
                            break;
                        case 'desc':
                            filter_value.prop('disabled', true).attr('placeholder', 'Ordenar Descendentemente');
                            break;
                        case '=':
                            filter_value.prop('disabled', false).attr('placeholder', 'Ej.: : Lorem ipsum');
                            break;
                        case '>=':
                            filter_value.prop('disabled', false).attr('placeholder', 'Ej.: : 1000');
                            break;
                        case '<=':
                            filter_value.prop('disabled', false).attr('placeholder', 'Ej.: : 1000');
                            break;
                        case '>':
                            filter_value.prop('disabled', false).attr('placeholder', 'Ej.: : 1000');
                            break;
                        case '<':
                            filter_value.prop('disabled', false).attr('placeholder', 'Ej.: : 1000');
                            break;
                        case '!=':
                            filter_value.prop('disabled', false).attr('placeholder', 'Ej.: : Lorem ipsum');
                            break;
                        case 'in':
                            filter_value.prop('disabled', false).attr('placeholder', 'Ej.: :  Lorem, Ipsum, Dolor Sit');
                            break;
                        case 'not in':
                            filter_value.prop('disabled', false).attr('placeholder', 'Ej.: :  Lorem, Ipsum, Dolor Sit');
                            break;
                        case 'between':
                            filter_value.val('').hide();
                            p.find('.between-group input').prop('disabled', false);
                            p.find('.between-group').show().focus();
                            p.find('.filter-value-between').prop('disabled', false);
                            break;
                    }
                })

                /* Remueve disabled cuando recarga la pagina y el valor es ingresado */
                $(".filter-value").each(function () {
                    var v = $(this).val();
                    if (v != '') $(this).prop('disabled', false);
                })

            })
