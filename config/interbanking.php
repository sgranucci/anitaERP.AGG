<?php

/**
 * Credenciales Interbanking (OAuth client_credentials + customer_id por índice de empresa).
 * Todas las consultas GET (saldos, movimientos, transferencias) usan el mismo token por empresa
 * (`storage/app/tokeninterbanking_{empresa_id}.json`), renovación preventiva por vigencia y reintento ante HTTP 401.
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
 *
 * Transferencias / comprobantes (consulta vía Anita, proxy a la API de Interbanking):
 * - Ruta HTTP: GET `{APP_URL}/caja/interbanking/transferencias` (nombre de ruta: `interbanking_transferencias`).
 * - Permiso: `ver-transferencias-cuenta-interbanking`. El usuario debe pertenecer a `empresa_id`.
 * - Query string (snake_case):
 *   - `empresa_id` (obligatorio): token OAuth y `customer-id`.
 *   - `date_since`, `date_until` (opcional): `Y-m-d` (máx. 60 días por consulta en la API).
 *   - Filtros opcionales débito: `debit_account_number`, `debit_account_type` (`CC`|`CA`), `debit_bank_number` (3 dígitos), `debit_currency` (`ARS`|`USD`).
 *   - Filtros opcionales crédito: `credit_account_number`, `credit_account_type`, `credit_bank_number`, `credit_currency`.
 *   - `limit` (opcional, 1–500), `page` (opcional, desde 0).
 * - Respuesta JSON: `ok`, `general_data`, `transfers`, `error`.
 * - Desde PHP: `app(InterbankingService::class)->leeTransferencias($empresaId, ['date_since' => '2026-01-01', ...])`.
 * - API externa: `GET https://api-gw.interbanking.com.ar/api/prod/v1/transfers/vouchers` (Transferencias v1.09).
 * - Cada consulta exitosa persiste/actualiza filas en `interbanking_transferencia` (dedupe por hash).
 *
 * Transferencias persistidas (listado + sincronización masiva):
 * - Listado: GET `{APP_URL}/caja/interbanking/transferencias-persistidas` — permiso `listar-interbanking-transferencias-persistidas`.
 * - Sincronizar: POST `{APP_URL}/caja/interbanking/transferencias-persistidas/sincronizar` — permiso `sincronizar-interbanking-transferencias`.
 * - Automático (scheduler): `php artisan interbanking:persistir-transferencias`
 *   - Lunes a viernes (no feriados): cada hora, ventana 14 días incluyendo hoy.
 *   - Sábados, domingos y feriados: una vez al día (08:00), ventana 60 días incluyendo hoy.
 *   - Feriados: tabla `feriado` (configuración). Requiere `php artisan schedule:run` en cron.
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
