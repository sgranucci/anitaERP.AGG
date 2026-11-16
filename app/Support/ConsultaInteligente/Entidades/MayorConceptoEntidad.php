<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente\Entidades;

use App\Support\ConsultaInteligente\EntidadSemantica;
use App\Support\ConsultaInteligente\EntidadSemanticaPuenteReporte;
use App\Support\Contable\MayorConceptoListadoFiltros;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

/**
 * Puente al mayor por concepto (reporte Contable). No ejecuta el motor en CI.
 */
final class MayorConceptoEntidad implements EntidadSemantica, EntidadSemanticaPuenteReporte
{
    public function clave(): string
    {
        return 'mayor_concepto';
    }

    public function nombre(): string
    {
        return 'Mayor por concepto';
    }

    public function descripcion(): string
    {
        return 'Mayor contable por concepto: período, empresa y detalle. Se abre en el reporte nativo.';
    }

    public function icono(): string
    {
        return 'fa-book';
    }

    public function modulo(): string
    {
        return 'Contable';
    }

    public function color(): string
    {
        return '#1A365D';
    }

    public function permisoListar(): string
    {
        return 'listar-mayor-concepto';
    }

    public function rutaNativa(): string
    {
        return 'mayor_concepto';
    }

    public function recurso(): string
    {
        return 'contable.mayor_concepto';
    }

    public function camposQbe(): array
    {
        return [
            'filtro_concepto' => ['label' => 'Concepto', 'type' => 'texto'],
            'filtro_cuenta' => ['label' => 'Cuenta', 'type' => 'texto'],
            'filtro_texto' => ['label' => 'Texto libre', 'type' => 'texto'],
            'filtro_comprobante' => ['label' => 'Comprobante', 'type' => 'texto'],
        ];
    }

    public function columnasVisibles(): array
    {
        return ['filtro_concepto', 'filtro_cuenta', 'mes', 'anio'];
    }

    public function etiquetasColumnas(): array
    {
        return [
            'filtro_concepto' => 'Concepto',
            'filtro_cuenta' => 'Cuenta',
            'mes' => 'Mes',
            'anio' => 'Año',
        ];
    }

    public function valorCelda(object $row, string $key): string
    {
        return e((string) ($row->{$key} ?? ''));
    }

    public function resolverFiltros(Request $request): array
    {
        return MayorConceptoListadoFiltros::resolverDesdeRequest($request);
    }

    public function paraQueryString(array $filtros): array
    {
        return MayorConceptoListadoFiltros::paraQueryString($filtros);
    }

    public function consultar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        // El mayor no se materializa acá: el explorador redirige al reporte nativo.
        if ($paginar) {
            return new Paginator([], 0, 10);
        }

        return collect();
    }

    public function urlDetalle(object $row): ?string
    {
        return null;
    }

    public function plantillas(): array
    {
        $mes = (int) date('n');
        $anio = (int) date('Y');

        return [
            [
                'clave' => 'mes_actual',
                'titulo' => 'Mayor concepto · mes actual',
                'descripcion' => 'Abrir el mayor del mes en curso',
                'query' => [
                    'consultar' => 1,
                    'modo_periodo' => 'mes',
                    'mes' => $mes,
                    'anio' => $anio,
                ],
            ],
            [
                'clave' => 'mes_anterior',
                'titulo' => 'Mayor concepto · mes anterior',
                'descripcion' => 'Abrir el mayor del mes previo',
                'query' => [
                    'consultar' => 1,
                    'modo_periodo' => 'mes',
                    'mes' => $mes === 1 ? 12 : $mes - 1,
                    'anio' => $mes === 1 ? $anio - 1 : $anio,
                ],
            ],
        ];
    }

    public function urlReporte(array $filtros): string
    {
        $q = MayorConceptoListadoFiltros::paraQueryString($filtros);
        $q['consultar'] = 1;

        return route('mayor_concepto').'?'.http_build_query($q);
    }

    public function ayudaPuente(): string
    {
        return 'El mayor por concepto es un reporte pesado. Elegí período y abrí el reporte Contable completo (misma sesión y permisos).';
    }
}
