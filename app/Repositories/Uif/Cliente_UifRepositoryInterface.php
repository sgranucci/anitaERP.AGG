<?php

namespace App\Repositories\Uif;

use App\Models\Uif\Cliente_Uif;

interface Cliente_UifRepositoryInterface extends RepositoryInterface
{

    /**
     * @param  array<string, mixed>|string|null  $filtros
     */
    public function leeCliente_Uif($filtros, $flPaginando = null);

    /**
     * Indica si hay al menos un cliente UIF en base (consulta directa, sin filtros de listado/búsqueda).
     */
    public function hayRegistrosClienteUifLocales(): bool;

    /**
     * Registra en BD los adjuntos del cliente que ya estan en el montaje Anita (NOSIS, DDJJ, etc.).
     * No copia archivos si el storage esta en modo solo-referencia.
     */
    public function sincronizarArchivosAnitaSiCorresponde(Cliente_Uif $cliente): void;

    /**
     * HTML de filas para el modal de consulta de clientes UIF.
     *
     * @param  string|null  $consulta
     * @param  string|null  $anitaOrigen
     */
    public function consultaCliente_UifHtml($consulta = null, $anitaOrigen = null): string;

    /**
     * Resumen JSON para resolver cliente por ID.
     *
     * @return array<string, mixed>|null
     */
    public function findResumenParaConsulta(int $id): ?array;

    /**
     * Carga solo los últimos N premios para la solapa de la ficha (no se regraban al Actualizar).
     */
    public function cargarPremiosParaFicha(Cliente_Uif $cliente, ?int $limite = null): Cliente_Uif;

    /**
     * Página de premios para «Cargar más» en la ficha.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Uif\Cliente_Premio_Uif>
     */
    public function leePremiosFichaPagina(int $clienteUifId, int $offset = 0, ?int $limite = null);
}
