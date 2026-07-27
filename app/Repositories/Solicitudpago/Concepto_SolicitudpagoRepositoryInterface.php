<?php

namespace App\Repositories\Solicitudpago;

interface Concepto_SolicitudpagoRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function sincronizarConAnita(): array;

    public function findPorCodigo(int $codigo);

    /**
     * Conceptos activos para modal/consulta operativa, opcionalmente filtrados por sector.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Solicitudpago\Concepto_Solicitudpago>
     */
    public function listadoOperativoParaConsulta(?string $consulta = null, ?int $sectorId = null);

    /**
     * Resuelve un concepto activo por código, opcionalmente acotado al sector.
     */
    public function findOperativoPorCodigo(int $codigo, ?int $sectorId = null);

    /**
     * Plantilla de cuentas del concepto (base del asiento de la SP).
     *
     * @return list<array<string, mixed>>
     */
    public function cuentasTemplateParaSolicitud(int $conceptoId, ?int $empresaId = null): array;

    public function guardarCompleto(array $data, ?int $id = null);
}
