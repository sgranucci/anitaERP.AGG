$(document).ready(function(){
    let row_number = 1;

    $("#add_row").click(function(e){
      	e.preventDefault();
      	let new_row_number = row_number - 1;
      	$('#modulo' + row_number).html($('#modulo' + new_row_number).html()).find('td:first-child');
      	$('#modulos_table').append('<tr id="modulo' + (row_number + 1) + '"></tr>');
      	row_number++;
    });

    $("#delete_row").click(function(e){
      	e.preventDefault();
      	if(row_number > 1){
        	$("#modulo" + (row_number - 1)).html('');
        	row_number--;
      	}
    });
});
