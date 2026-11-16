<?php

declare(strict_types=1);

namespace App\Support\Contable\ConciliacionBancaria;

use App\Models\Contable\ConciliacionBancariaResidualRegla;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Support\Facades\Schema;

final class ConciliacionBancariaResidualReglaSupport
{
    /**
     * @return list<array{
     *   id: int|null,
     *   clave: string,
     *   descripcion: string,
     *   patrones: string,
     *   dh: string,
     *   cuenta_codigo: string,
     *   concepto_ivacompra_codigo: string,
     *   tipo_documento: string,
     *   activo: bool,
     *   orden: int
     * }>
     */
    public static function paraFormulario(): array
    {
        if (! Schema::hasTable('conciliacion_bancaria_residual_regla')) {
            return [];
        }

        return ConciliacionBancariaResidualRegla::query()
            ->orderBy('orden')
            ->orderBy('id')
            ->get()
            ->map(fn (ConciliacionBancariaResidualRegla $r) => [
                'id' => (int) $r->id,
                'clave' => (string) $r->clave,
                'descripcion' => (string) $r->descripcion,
                'patrones' => (string) ($r->patrones ?? ''),
                'dh' => strtoupper(substr((string) ($r->dh ?? 'D'), 0, 1)) ?: 'D',
                'cuenta_codigo' => (string) ($r->cuenta_codigo ?? ''),
                'concepto_ivacompra_codigo' => (string) ($r->concepto_ivacompra_codigo ?? ''),
                'tipo_documento' => strtoupper((string) ($r->tipo_documento ?? 'EGR')),
                'activo' => (bool) $r->activo,
                'orden' => (int) $r->orden,
            ])
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public static function guardar(array $filas): void
    {
        if (! Schema::hasTable('conciliacion_bancaria_residual_regla')) {
            return;
        }

        $ids = [];
        $orden = 0;
        $clavesVistas = [];

        foreach ($filas as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $clave = strtolower(trim((string) ($raw['clave'] ?? '')));
            $clave = preg_replace('/[^a-z0-9_]+/', '_', $clave) ?: '';
            $clave = trim($clave, '_');
            if ($clave === '' || isset($clavesVistas[$clave])) {
                continue;
            }
            $descripcion = trim((string) ($raw['descripcion'] ?? ''));
            if ($descripcion === '') {
                continue;
            }
            $patrones = self::normalizarPatrones($raw['patrones'] ?? '');
            if ($patrones === null) {
                continue;
            }
            $dh = strtoupper(substr(trim((string) ($raw['dh'] ?? 'D')), 0, 1));
            if (! in_array($dh, ['D', 'H', ''], true)) {
                $dh = 'D';
            }
            $tipoDoc = strtoupper(trim((string) ($raw['tipo_documento'] ?? 'EGR')));
            if ($tipoDoc === '') {
                $tipoDoc = 'EGR';
            }
            $cuenta = trim((string) ($raw['cuenta_codigo'] ?? ''));
            $concepto = trim((string) ($raw['concepto_ivacompra_codigo'] ?? ''));
            $activo = ! empty($raw['activo']) && ! in_array((string) $raw['activo'], ['0', 'false', 'off'], true);
            $orden++;
            $clavesVistas[$clave] = true;

            $payload = [
                'clave' => mb_substr($clave, 0, 40),
                'descripcion' => mb_substr($descripcion, 0, 120),
                'patrones' => mb_substr($patrones, 0, 500),
                'dh' => $dh !== '' ? $dh : 'D',
                'cuenta_codigo' => $cuenta !== '' ? mb_substr($cuenta, 0, 20) : null,
                'concepto_ivacompra_codigo' => $concepto !== '' ? mb_substr($concepto, 0, 20) : null,
                'tipo_documento' => mb_substr($tipoDoc, 0, 10),
                'activo' => $activo,
                'orden' => $orden,
            ];

            $id = (int) ($raw['id'] ?? 0);
            if ($id > 0) {
                $modelo = ConciliacionBancariaResidualRegla::query()->find($id);
                if ($modelo) {
                    $modelo->fill($payload);
                    $modelo->save();
                    $ids[] = (int) $modelo->id;
                    continue;
                }
            }

            $existente = ConciliacionBancariaResidualRegla::query()->where('clave', $payload['clave'])->first();
            if ($existente) {
                $existente->fill($payload);
                $existente->save();
                $ids[] = (int) $existente->id;
                continue;
            }

            $nuevo = ConciliacionBancariaResidualRegla::query()->create($payload);
            $ids[] = (int) $nuevo->id;
        }

        $borrar = ConciliacionBancariaResidualRegla::query();
        if ($ids !== []) {
            $borrar->whereNotIn('id', $ids);
        }
        EloquentAuditDeleteSupport::each($borrar);
    }

    private static function normalizarPatrones(mixed $raw): ?string
    {
        $texto = is_array($raw) ? implode('|', $raw) : (string) $raw;
        $parts = preg_split('/[|;\n]+/', $texto) ?: [];
        $parts = array_values(array_filter(array_map(
            fn ($p) => strtoupper(trim((string) $p)),
            $parts
        ), fn ($p) => $p !== ''));

        return $parts === [] ? null : implode('|', $parts);
    }

    /**
     * @return list<ConciliacionBancariaResidualRegla>
     */
    public static function activas(): array
    {
        if (! Schema::hasTable('conciliacion_bancaria_residual_regla')) {
            return [];
        }

        return ConciliacionBancariaResidualRegla::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->get()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $movBanco
     */
    public static function resolver(array $movBanco): ?ConciliacionBancariaResidualRegla
    {
        $texto = mb_strtoupper(trim(
            (string) ($movBanco['code_description_ib'] ?? '').' '.
            (string) ($movBanco['code_description_bank'] ?? '').' '.
            (string) ($movBanco['concepto'] ?? '')
        ));
        $dh = strtoupper(substr((string) ($movBanco['debit_credit_type'] ?? $movBanco['dh'] ?? ''), 0, 1));

        foreach (self::activas() as $regla) {
            if ($regla->dh && $dh !== '' && strtoupper((string) $regla->dh) !== $dh) {
                continue;
            }
            $pats = array_filter(array_map('trim', explode('|', (string) ($regla->patrones ?? ''))));
            if ($pats === []) {
                continue;
            }
            foreach ($pats as $pat) {
                if ($pat !== '' && str_contains($texto, mb_strtoupper($pat))) {
                    return $regla;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $movimientosBanco
     * @return list<array<string, mixed>>
     */
    public static function clasificarPendientes(array $movimientosBanco): array
    {
        $out = [];
        foreach ($movimientosBanco as $mov) {
            $regla = self::resolver($mov);
            if ($regla === null) {
                continue;
            }
            $out[] = [
                'movimiento' => $mov,
                'regla' => [
                    'id' => (int) $regla->id,
                    'clave' => (string) $regla->clave,
                    'descripcion' => (string) $regla->descripcion,
                    'cuenta_codigo' => $regla->cuenta_codigo,
                    'concepto_ivacompra_codigo' => $regla->concepto_ivacompra_codigo,
                    'tipo_documento' => (string) $regla->tipo_documento,
                    'listo' => trim((string) ($regla->cuenta_codigo ?? '')) !== ''
                        || trim((string) ($regla->concepto_ivacompra_codigo ?? '')) !== '',
                ],
            ];
        }

        return $out;
    }
}
