<?php

/**
 * Credenciales Interbanking (OAuth client_credentials + customer_id por índice de empresa).
 *
 * Movimientos (consulta vía Anita, proxy a la API de Interbanking):
 * - Ruta HTTP: GET `{APP_URL}/caja/interbanking/movimientos` (nombre de ruta: `interbanking_movimientos`).
 * - Permiso: `ver-movimientos-cuenta-interbanking`. El usuario debe pertenecer a `empresa_id`.
 * - Query string (todos los nombres en snake_case como en el request de Laravel):
 *   - `empresa_id` (obligatorio): id de empresa; define token OAuth y `customer-id` hacia Interbanking.
 *   - `account_number` (obligatorio): número de cuenta SIB.
 *   - `bank_number` (obligatorio): código BCRA 3 dígitos (ej. `011`).
 *   - `movement_type` (obligatorio): `dia` | `diferidos` | `anteriores` | `zughus`.
 *   - `account_type` (opcional): `CC` (defecto) | `CA`.
 *   - `currency` (opcional): `ARS` (defecto) | `USD`.
 *   - `date_since`, `date_until` (opcional): `Y-m-d`, útiles sobre todo para `anteriores` / `zughus`.
 *   - `limit` (opcional, 1–500), `page` (opcional, desde 0).
 * - Respuesta JSON: `ok`, `general_data`, `movements_detail`, `error`.
 * - Desde PHP: `app(InterbankingService::class)->leeMovimientos($empresaId, $accountNumber, $movementType, ['bank_number' => '011', ...])`.
 * - API externa llamada por el servicio: `GET https://api-gw.interbanking.com.ar/api/prod/v2/accounts/{account-number}/movements/{movement-type}` (OpenAPI Movimientos).
 *
 * Movimientos persistidos (base local + sincronización desde la API):
 * - Listado: GET `{APP_URL}/caja/interbanking/movimientos-persistidos` — permiso `listar-interbanking-movimientos-persistidos`.
 * - Sincronizar: POST `{APP_URL}/caja/interbanking/movimientos-persistidos/sincronizar` — permiso `sincronizar-interbanking-movimientos`.
 */

// Constantes de arbol de aprobacion

switch (strtoupper(config('app.empresa'))) {
    case 'AGG':
        return [
            'client_id' => ['Qvj6P92Oi9Oyq1GGrCRftf4yXcheLmiigHUB', '6UyawCF9sxlI07NIjbUJded2333ULKtGiDUW', 'ohLciTIWzAgaNui7XbRH1wznR50PqepBYfhp'],
            'client_secret' => ['IKIybgzcoOIteljkZJBvl9YER2ihPBeq06ms', 'Rf13czG6uDolPtN60dSZHZUldaPk7MGICL36', 'QCOOkdzAzwUgLB1esv5XmDCrlG7DSrjJVoMF'],
            'customer_id' => ['X36888A', 'X36688A', 'C25656A'],
        ];
        break;
}
