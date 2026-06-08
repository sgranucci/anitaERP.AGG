<?php

namespace App\Contracts\Configuracion;

/**
 * Provee datos de un evento concreto para el servicio transversal de avisos.
 */
interface ModuloAvisoHandlerInterface
{
    /**
     * Valores para filtrar destinatarios (empresa, CC, etc.).
     *
     * @return array{empresa_id?: int|null, centrocosto_id?: int|null}
     */
    public function contextoFiltro(int $entityId): array;

    /**
     * Placeholders disponibles en asunto/cuerpo ({numero}, {solicitante}, …).
     *
     * @return array<string, string>
     */
    public function placeholders(int $entityId): array;

    public function linkConsulta(int $entityId): ?string;

    /**
     * @return array{contenido: string, nombre: string}|null
     */
    public function generarPdf(int $entityId): ?array;
}
