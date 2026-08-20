$(function () {
    if (typeof activa_eventos_consultacliente === 'function') {
        activa_eventos_consultacliente();
    }
    if (typeof activa_eventos_consultatransporte === 'function') {
        activa_eventos_consultatransporte();
    }
    if (typeof activa_eventos_consultacamion === 'function') {
        activa_eventos_consultacamion();
    }
});
