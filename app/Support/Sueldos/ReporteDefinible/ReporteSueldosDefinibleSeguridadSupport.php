<?php

namespace App\Support\Sueldos\ReporteDefinible;

use App\Models\Sueldos\ReporteSueldosDefinible;
use App\Models\Sueldos\ReporteSueldosDefinibleColumna;
use App\Repositories\Configuracion\EmpresaRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Seguridad central: empresa autorizada, liquidación, proyección PII, deny-by-default sensibles.
 */
final class ReporteSueldosDefinibleSeguridadSupport
{
    /** Campos empleado considerados PII (códigos help_4). */
    public const CAMPOS_PII = [4, 14, 15, 19, 27, 28];

    public const ABILITY_REPORTS_READ = 'reports:read';

    public const ABILITY_DATASETS_READ = 'datasets:read';

    public const ABILITY_EXECUTIONS_CREATE = 'executions:create';

    public const ABILITY_PIVOTS_RUN = 'pivots:run';

    public const ABILITY_PII_READ = 'pii:read';

    public const ABILITY_WEBHOOKS_MANAGE = 'webhooks:manage';

    public function __construct(private EmpresaRepository $empresaRepository) {}

    /**
     * @return list<int>|null null = acceso a todas las empresas
     */
    public function empresaIdsAutorizadas(?int $usuarioId = null): ?array
    {
        $ids = $this->empresaRepository->traeEmpresasAsignadas();
        if ($ids === [] || $ids === null) {
            return null;
        }

        return array_values(array_map('intval', $ids));
    }

    public function empresaAutorizada(?int $empresaId, ?int $usuarioId = null): bool
    {
        if ($empresaId === null || $empresaId <= 0) {
            return true;
        }
        $autorizadas = $this->empresaIdsAutorizadas($usuarioId);
        if ($autorizadas === null) {
            return true;
        }

        return in_array((int) $empresaId, $autorizadas, true);
    }

    public function assertEmpresaAutorizada(?int $empresaId): void
    {
        if (! $this->empresaAutorizada($empresaId)) {
            Log::warning('rsd.seguridad.empresa_denegada', [
                'usuario_id' => Auth::id(),
                'empresa_id' => $empresaId,
            ]);
            abort(403, 'Empresa no autorizada para este usuario.');
        }
    }

    public function puedeLeerPii(?object $usuario = null): bool
    {
        $usuario = $usuario ?? Auth::user();
        if ($usuario && method_exists($usuario, 'tokenCan') && $usuario->currentAccessToken()) {
            return $usuario->tokenCan(self::ABILITY_PII_READ);
        }

        return can('ver-pii-reporte-sueldos-definible', false)
            || can('actualizar-reporte-sueldos-definible', false);
    }

    public function puedeIncluirConfidencial(ReporteSueldosDefinible $reporte, ?object $usuario = null): bool
    {
        if (! (bool) ($reporte->incluye_confidencial ?? false)) {
            return false;
        }

        return can('ver-confidencial-reporte-sueldos-definible', false)
            || can('actualizar-reporte-sueldos-definible', false);
    }

    /**
     * Quita o enmascara columnas/valores PII según permiso.
     *
     * @param  array{columnas?:list<array<string,mixed>>,filas?:list<array<string,mixed>>,totales?:array,meta?:array}  $resultado
     * @return array{columnas:list<array<string,mixed>>,filas:list<array<string,mixed>>,totales:array,meta:array}
     */
    public function proyectarResultado(array $resultado, ReporteSueldosDefinible $reporte, ?object $usuario = null): array
    {
        if ($this->puedeLeerPii($usuario)) {
            return $resultado;
        }

        $columnasPiiNro = [];
        foreach ($reporte->columnas ?? [] as $col) {
            /** @var ReporteSueldosDefinibleColumna $col */
            if ($col->contenido === ReporteSueldosDefinibleSupport::CONTENIDO_CAMPO_EMPLEADO
                && in_array((int) $col->campo_empleado, self::CAMPOS_PII, true)) {
                $columnasPiiNro[] = (int) $col->nro_columna;
            }
        }
        if ($columnasPiiNro === []) {
            return $resultado;
        }

        $filas = [];
        foreach ((array) ($resultado['filas'] ?? []) as $fila) {
            foreach ($columnasPiiNro as $nro) {
                $fila['c'.$nro] = '***';
            }
            $filas[] = $fila;
        }
        $meta = (array) ($resultado['meta'] ?? []);
        $meta['pii_enmascarado'] = true;
        $meta['columnas_pii'] = $columnasPiiNro;

        Log::info('rsd.seguridad.pii_enmascarado', [
            'usuario_id' => Auth::id(),
            'reporte_id' => $reporte->id,
            'columnas' => $columnasPiiNro,
        ]);

        return [
            'columnas' => array_values((array) ($resultado['columnas'] ?? [])),
            'filas' => $filas,
            'totales' => (array) ($resultado['totales'] ?? []),
            'meta' => $meta,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function normalizarFiltrosAutorizados(array $filtros): array
    {
        $empresaId = isset($filtros['empresa_id']) ? (int) $filtros['empresa_id'] : null;
        $this->assertEmpresaAutorizada($empresaId > 0 ? $empresaId : null);

        $autorizadas = $this->empresaIdsAutorizadas();
        if ($autorizadas !== null && ($empresaId === null || $empresaId <= 0) && count($autorizadas) === 1) {
            $filtros['empresa_id'] = $autorizadas[0];
        }

        return $filtros;
    }

    /**
     * @return list<string>
     */
    public static function abilitiesCatalogo(): array
    {
        return [
            self::ABILITY_REPORTS_READ,
            self::ABILITY_DATASETS_READ,
            self::ABILITY_EXECUTIONS_CREATE,
            self::ABILITY_PIVOTS_RUN,
            self::ABILITY_PII_READ,
            self::ABILITY_WEBHOOKS_MANAGE,
        ];
    }
}
