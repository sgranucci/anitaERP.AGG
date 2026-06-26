<?php

namespace App\Repositories\Contable;

use App\Models\Contable\Asiento;

interface AsientoRepositoryInterface extends RepositoryInterface
{

    public function sincronizarConAnita();

    /**
     * Reemplaza líneas ctamov en Anita (delete + insert) sin tocar el registro asiento en MySQL.
     *
     * @param  array<string, mixed>  $data  Debe incluir numeroasiento, empresa_id, fecha, cuentacontable_ids, debes, haberes, etc.
     */
    public function sincronizarCtamovAnita(array $data): void;

    /**
     * Arma el payload de movimientos para sincronizar Anita desde un asiento ya grabado.
     *
     * @return array<string, mixed>
     */
    public function armarPayloadAnitaDesdeModelo(Asiento $asiento): array;

    /**
     * Elimina líneas ctamov en Anita por empresa + número de asiento (rollback compensatorio).
     */
    public function eliminarCtamovAnitaPorNumero(int $empresaId, string $numeroAsiento): void;

    /**
     * Elimina todas las líneas ctamov en Anita de un comprobante (tipo/letra/sucursal/nro).
     */
    public function eliminarCtamovAnitaPorComprobante(
        int $empresaId,
        string $tipo,
        string $letra,
        int $sucursal,
        int $nro,
    ): void;

    public function leeAsientoPorClave($id, $clave);
}

