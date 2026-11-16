<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente\Entidades;

use App\Support\ConsultaInteligente\EntidadSemantica;
use App\Support\ConsultaInteligente\EntidadSemanticaPuenteReporte;
use App\Support\Contable\MayorPlanoCuentaListadoFiltros;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

/**
 * Puente al mayor plano por cuenta. No ejecuta el motor en CI.
 */
final class MayorPlanoCuentaEntidad implements EntidadSemantica, EntidadSemanticaPuenteReporte
{
    public function clave(): string
    {
        return 'mayor_plano_cuenta';
    }

    public function nombre(): string
    {
        return 'Mayor plano por cuenta';
    }

    public function descripcion(): string
    {
        return 'Mayor analítico por cuenta (Anita + ERP). Se abre en el reporte nativo.';
    }

    public function icono(): string
    {
        return 'fa-balance-scale';
    }

    public function modulo(): string
    {
        return 'Contable';
    }

    public function color(): string
    {
        return '#154360';
    }

    public function permisoListar(): string
    {
        return 'listar-mayor-plano-cuenta';
    }

    public function rutaNativa(): string
    {
        return 'mayor_plano_cuenta';
    }

    public function recurso(): string
    {
        return 'contable.mayor_plano_cuenta';
    }

    public function camposQbe(): array
    {
        return [
            'cuenta_desde' => ['label' => 'Cuenta desde', 'type' => 'entero'],
            'cuenta_hasta' => ['label' => 'Cuenta hasta', 'type' => 'entero'],
            'cuentas' => ['label' => 'Cuentas (CSV)', 'type' => 'texto'],
        ];
    }

    public function columnasVisibles(): array
    {
        return ['cuenta_desde', 'cuenta_hasta', 'mes', 'anio'];
    }

    public function etiquetasColumnas(): array
    {
        return [
            'cuenta_desde' => 'Cuenta desde',
            'cuenta_hasta' => 'Cuenta hasta',
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
        return MayorPlanoCuentaListadoFiltros::resolverDesdeRequest($request);
    }

    public function paraQueryString(array $filtros): array
    {
        return MayorPlanoCuentaListadoFiltros::paraQueryString($filtros);
    }

    public function consultar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
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
                'titulo' => 'Mayor plano · mes actual',
                'descripcion' => 'Abrir el mayor plano del mes en curso',
                'query' => [
                    'consultar' => 1,
                    'modo_periodo' => 'mes',
                    'mes' => $mes,
                    'anio' => $anio,
                ],
            ],
        ];
    }

    public function urlReporte(array $filtros): string
    {
        $q = MayorPlanoCuentaListadoFiltros::paraQueryString($filtros);
        $q['consultar'] = 1;

        return route('mayor_plano_cuenta').'?'.http_build_query($q);
    }

    public function ayudaPuente(): string
    {
        return 'El mayor plano puede ser muy pesado (Anita + subdiario). Desde acá abrís el reporte Contable con el período elegido.';
    }
}
