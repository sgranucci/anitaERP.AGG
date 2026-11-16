<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Entidad del modelo semántico de Consulta Inteligente.
 * Cada una envuelve un listado workbench existente (QBE, columnas, permisos).
 */
interface EntidadSemantica
{
    public function clave(): string;

    public function nombre(): string;

    public function descripcion(): string;

    public function icono(): string;

    /** Módulo de negocio para chips del catálogo (Caja, Compras, …). */
    public function modulo(): string;

    /** Color de acento CSS (#2471A3, …). */
    public function color(): string;

    public function permisoListar(): string;

    /** Nombre de ruta del index workbench nativo. */
    public function rutaNativa(): string;

    public function recurso(): string;

    /**
     * @return array<string, array{label: string, type?: string, column?: string}>
     */
    public function camposQbe(): array;

    /**
     * @return list<string>
     */
    public function columnasVisibles(): array;

    /**
     * @return array<string, string>
     */
    public function etiquetasColumnas(): array;

    public function valorCelda(object $row, string $key): string;

    /**
     * @return array<string, mixed>
     */
    public function resolverFiltros(Request $request): array;

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function paraQueryString(array $filtros): array;

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function consultar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection;

    public function urlDetalle(object $row): ?string;

    /**
     * Atajos de consulta (toma de decisiones).
     *
     * @return list<array{clave: string, titulo: string, descripcion: string, query: array<string, mixed>}>
     */
    public function plantillas(): array;
}
