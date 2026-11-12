<?php

namespace App\Services\Contable;

use App\Models\Caja\Cuentacaja;
use App\Services\Caja\InterbankingMovimientoPersistenciaService;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaExcelReferenciaSupport;
use RuntimeException;

class ConciliacionBancariaExtractoExcelImportService
{
    public function __construct(
        private readonly InterbankingMovimientoPersistenciaService $persistencia,
    ) {}

    /**
     * @return array{
     *   ok: bool,
     *   account_number: string,
     *   filas: int,
     *   cuentacaja_id: int|null,
     *   cuenta_interbanking_asignada: bool,
     *   archivo: string,
     *   error: string|null
     * }
     */
    public function importar(
        string $ruta,
        int $empresaId,
        ?int $cuentacajaId = null,
        ?string $codigoCuentacaja = null,
        bool $asignarInterbankingSiVacio = true,
        bool $omitirValidacionCierre = true,
    ): array {
        $extracto = ConciliacionBancariaExcelReferenciaSupport::leerExtracto($ruta);
        $cuenta = $this->resolverCuentacaja($empresaId, $cuentacajaId, $codigoCuentacaja, $ruta);

        $asignada = false;
        if ($asignarInterbankingSiVacio) {
            $ibActual = trim((string) ($cuenta->cuenta_interbanking ?? ''));
            if ($ibActual === '') {
                $cuenta->cuenta_interbanking = $extracto['account_number'];
                $cuenta->save();
                $asignada = true;
            } elseif ($ibActual !== $extracto['account_number']) {
                // Gerli ya tiene IB canónico; se importa igual contra la cuenta configurada.
                // Ex Itaú: si ya hay IB distinto, no pisamos.
            }
        }

        $accountNumber = trim((string) ($cuenta->cuenta_interbanking ?? ''));
        if ($accountNumber === '') {
            $accountNumber = $extracto['account_number'];
        }

        $filas = $this->persistencia->persistirLote(
            $empresaId,
            (string) $extracto['bank_number'],
            $accountNumber,
            (string) $extracto['account_type'],
            'ARS',
            'excel_contaduria',
            $extracto['movimientos'],
            now(),
            $omitirValidacionCierre,
        );

        return [
            'ok' => true,
            'account_number' => $accountNumber,
            'filas' => $filas,
            'cuentacaja_id' => (int) $cuenta->id,
            'cuenta_interbanking_asignada' => $asignada,
            'archivo' => (string) $extracto['archivo'],
            'error' => null,
        ];
    }

    private function resolverCuentacaja(
        int $empresaId,
        ?int $cuentacajaId,
        ?string $codigoCuentacaja,
        string $ruta,
    ): Cuentacaja {
        if ($cuentacajaId !== null && $cuentacajaId > 0) {
            $cuenta = Cuentacaja::query()->find($cuentacajaId);
            if (! $cuenta || ! $cuenta->perteneceAEmpresa($empresaId)) {
                throw new RuntimeException('La cuenta de caja no pertenece a la empresa indicada.');
            }

            return $cuenta;
        }

        $codigo = trim((string) ($codigoCuentacaja ?? ''));
        if ($codigo === '') {
            $codigo = (string) (ConciliacionBancariaExcelReferenciaSupport::codigoCuentacajaDesdeNombreArchivo($ruta) ?? '');
        }
        if ($codigo === '') {
            throw new RuntimeException('No se pudo resolver el código de cuenta de caja del Excel.');
        }

        $cuenta = Cuentacaja::query()
            ->paraEmpresa($empresaId)
            ->where('codigo', ltrim($codigo, '0'))
            ->first();

        if (! $cuenta) {
            throw new RuntimeException('No existe cuentacaja código '.$codigo.' para empresa '.$empresaId);
        }

        return $cuenta;
    }
}
