<?php

namespace App\Repositories\Ventas;

interface ClienteRepositoryInterface extends RepositoryInterface
{

    public function sincronizarConAnita();
    public function existeClientePorCodigo(string $codigo): bool;
    public function replicarClienteEnAnitaPorCodigo(string $codigo): string;

    public function sincronizarAnitaDespuesDeGrabado(int $clienteId): void;
    public function actualizarDistribuidorIdDesdeAnita(): array;
    public function actualizarCobradorIdDesdeAnita(): array;
    public function actualizarCoeficienteIdDesdeAnita(bool $persistir = false): array;

    /**
     * Alinea cliente.estado con clim_estado_cli de Anita (Anita manda; no escribe Informix).
     *
     * @return array{en_anita:int, actualizados:int, omitidos:int, sin_cliente:int, errores:list<string>, ejemplos:list<array<string, mixed>>, por_cambio:array<string, int>}
     */
    public function actualizarEstadoDesdeAnita(bool $persistir = false): array;
    public function previewInsertClimaeSqlPorCodigo(string $codigo): string;
    public function updateEmiteNc($id);

    /**
     * El Bierzo: apaga Emite NC tras emitir una nota de crédito (ERP + Anita).
     */
    public function marcarNoEmiteNcTrasNotaCredito(int $id): void;

    public function findPorCodigo($codigo);
    public function actualizaPadronMipyme($modo);
    public function actualizaPadronMipymePorCuit($cuit, $modo);
    public function actualizaPadronMipymeDesdePadron(string $modo): int;

    /**
     * Pack C: cortes por agrupación (universo filtrado).
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function cortesCliente(array $filtros): array;

}

