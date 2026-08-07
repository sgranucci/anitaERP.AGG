<?php

namespace App\Support\Compras;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Filtros del reporte de contratos / OC abiertas por vencer.
 */
final class ContratoVencimientoReporteFiltros
{
    public const ALERTA_TODOS = '';

    public const ALERTA_POR_VENCER = 'POR_VENCER';

    public const ALERTA_PREAVISO = 'PREAVISO';

    public const ALERTA_CONSUMO = 'CONSUMO';

    public const ALERTA_VENCIDO = 'VENCIDO';

    public const ALERTA_SIN_VIGENCIA = 'SIN_VIGENCIA';

    /** @var array<string, string> */
    public const OPCIONES_ALERTA = [
        self::ALERTA_TODOS => 'Todos',
        self::ALERTA_POR_VENCER => 'Por vencer (dentro del horizonte)',
        self::ALERTA_PREAVISO => 'Con preaviso de no renovación pendiente',
        self::ALERTA_CONSUMO => 'Consumo del tope en zona de alerta',
        self::ALERTA_VENCIDO => 'Vencidos',
        self::ALERTA_SIN_VIGENCIA => 'Sin fecha de vigencia cargada',
    ];

    public const HORIZONTE_DEFAULT = 90;

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'empresa_ids' => [],
            'tipo_alerta' => self::ALERTA_TODOS,
            'dias_horizonte' => self::HORIZONTE_DEFAULT,
            'proveedor' => '',
            'responsable_id' => 0,
            'solo_sin_responsable' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $empresaIds = collect((array) $request->input('empresa_ids', []))
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $tipoAlerta = strtoupper(trim((string) $request->input('tipo_alerta', '')));
        if (! array_key_exists($tipoAlerta, self::OPCIONES_ALERTA)) {
            $tipoAlerta = self::ALERTA_TODOS;
        }

        $horizonte = $request->has('dias_horizonte')
            ? (int) $request->input('dias_horizonte')
            : self::HORIZONTE_DEFAULT;

        return [
            'empresa_ids' => $empresaIds,
            'tipo_alerta' => $tipoAlerta,
            'dias_horizonte' => max(0, min(1095, $horizonte)),
            'proveedor' => trim((string) $request->input('proveedor', '')),
            'responsable_id' => (int) $request->input('responsable_id', 0),
            'solo_sin_responsable' => $request->boolean('solo_sin_responsable'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $query = ['consultar' => 1];

        if (($filtros['empresa_ids'] ?? []) !== []) {
            $query['empresa_ids'] = $filtros['empresa_ids'];
        }
        if (($filtros['tipo_alerta'] ?? '') !== '') {
            $query['tipo_alerta'] = $filtros['tipo_alerta'];
        }
        $query['dias_horizonte'] = (int) ($filtros['dias_horizonte'] ?? self::HORIZONTE_DEFAULT);
        if (($filtros['proveedor'] ?? '') !== '') {
            $query['proveedor'] = $filtros['proveedor'];
        }
        if ((int) ($filtros['responsable_id'] ?? 0) > 0) {
            $query['responsable_id'] = (int) $filtros['responsable_id'];
        }
        if (! empty($filtros['solo_sin_responsable'])) {
            $query['solo_sin_responsable'] = 1;
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return ($filtros['empresa_ids'] ?? []) !== [];
    }

    /**
     * Filtrado en memoria sobre los contratos ya calculados por el support.
     *
     * @param  list<array<string, mixed>>  $contratos
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    public static function aplicar(array $contratos, array $filtros): array
    {
        $empresaIds = $filtros['empresa_ids'] ?? [];
        $tipoAlerta = (string) ($filtros['tipo_alerta'] ?? self::ALERTA_TODOS);
        $horizonte = (int) ($filtros['dias_horizonte'] ?? self::HORIZONTE_DEFAULT);
        $proveedor = mb_strtoupper((string) ($filtros['proveedor'] ?? ''));
        $responsableId = (int) ($filtros['responsable_id'] ?? 0);
        $soloSinResponsable = (bool) ($filtros['solo_sin_responsable'] ?? false);
        $umbralesConsumo = OrdencompraContratoVencimientoSupport::umbralesConsumo();
        $umbralConsumoMinimo = $umbralesConsumo === [] ? 80 : min($umbralesConsumo);

        $filtrados = [];
        foreach ($contratos as $contrato) {
            if ($empresaIds !== [] && ! in_array((int) $contrato['empresa_id'], $empresaIds, true)) {
                continue;
            }
            if ($proveedor !== '' && ! str_contains(mb_strtoupper((string) $contrato['proveedor']), $proveedor)) {
                continue;
            }
            if ($responsableId > 0 && (int) $contrato['responsable_id'] !== $responsableId) {
                continue;
            }
            if ($soloSinResponsable && (int) $contrato['responsable_id'] > 0) {
                continue;
            }
            if (! self::coincideAlerta($contrato, $tipoAlerta, $horizonte, $umbralConsumoMinimo)) {
                continue;
            }

            $filtrados[] = $contrato;
        }

        return $filtrados;
    }

    /**
     * @param  array<string, mixed>  $contrato
     */
    private static function coincideAlerta(array $contrato, string $tipoAlerta, int $horizonte, int $umbralConsumo): bool
    {
        $tieneVigencia = $contrato['vigencia_hasta'] instanceof Carbon;
        $dias = (int) $contrato['dias_para_vencer'];
        $vencido = $tieneVigencia && $dias < 0;
        $dentroHorizonte = $tieneVigencia && $dias >= 0 && ($horizonte === 0 || $dias <= $horizonte);
        $conPreaviso = $contrato['fecha_limite_preaviso'] instanceof Carbon
            && (int) $contrato['dias_para_preaviso'] <= $horizonte;
        $consumoAlto = (float) $contrato['monto_tope'] > 0
            && (float) $contrato['porcentaje_consumido'] >= $umbralConsumo;

        return match ($tipoAlerta) {
            self::ALERTA_POR_VENCER => $dentroHorizonte,
            self::ALERTA_PREAVISO => $conPreaviso,
            self::ALERTA_CONSUMO => $consumoAlto,
            self::ALERTA_VENCIDO => $vencido,
            self::ALERTA_SIN_VIGENCIA => ! $tieneVigencia,
            default => $dentroHorizonte || $vencido || $conPreaviso || $consumoAlto || ! $tieneVigencia,
        };
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     */
    public static function subtitulo(array $filtros, $empresaQuery): string
    {
        $partes = [];

        $empresaIds = $filtros['empresa_ids'] ?? [];
        if ($empresaIds !== []) {
            $nombres = collect($empresaQuery)
                ->filter(static fn ($e) => in_array((int) $e->id, $empresaIds, true))
                ->pluck('nombre')
                ->implode(', ');
            if ($nombres !== '') {
                $partes[] = 'Empresas: '.$nombres;
            }
        }

        $partes[] = 'Alerta: '.(self::OPCIONES_ALERTA[$filtros['tipo_alerta'] ?? ''] ?? 'Todos');
        $partes[] = 'Horizonte: '.((int) ($filtros['dias_horizonte'] ?? self::HORIZONTE_DEFAULT)).' días';

        if (($filtros['proveedor'] ?? '') !== '') {
            $partes[] = 'Proveedor contiene: '.$filtros['proveedor'];
        }
        if (! empty($filtros['solo_sin_responsable'])) {
            $partes[] = 'Solo sin responsable asignado';
        }

        return implode(' · ', $partes);
    }
}
