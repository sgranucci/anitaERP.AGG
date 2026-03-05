
function imprimirHtml(divName, divHide){

    $("#"+divHide).hide();
    $("#"+divName).show();
    window.print();

    $("#"+divHide).show();
}
