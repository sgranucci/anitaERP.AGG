<?php

namespace App\Support\Contable\ReporteDefinible;

use App\Models\Contable\ReporteContableEliRegla;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Eliminaciones intercompany en consolidación: anula códigos (todas o por pareja de empresas).
 */
class ReporteDefinibleEliminacionSupport
{
    public const AMBITO_TODAS = 'todas';

    public const AMBITO_PAREJA = 'pareja';

    /**
     * Reglas activas expandidas para filtrar movimientos.
     *
     * @return list<array{codigos: array<int, true>, ambito: string, empresa_a_id: int|null, empresa_b_id: int|null}>
     */
    public function reglasActivas(?int $reporteId): array
    {
        $q = ReporteContableEliRegla::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('id');

        if ($reporteId && $reporteId > 0) {
            $q->where(function ($w) use ($reporteId) {
                $w->whereNull('reporte_contable_id')
                    ->orWhere('reporte_contable_id', $reporteId);
            });
        } else {
            $q->whereNull('reporte_contable_id');
        }

        $rango = app(ReporteDefinibleCuentaRangoSupport::class);
        $out = [];
        foreach ($q->get() as $regla) {
            $hasta = $regla->codigo_hasta !== null ? (int) $regla->codigo_hasta : null;
            /** @var array<int, true> $codigos */
            $codigos = [];
            foreach ($rango->expandirCodigo((int) $regla->codigo_desde, $hasta) as $codigo) {
                $codigos[$codigo] = true;
            }
            if ($codigos === []) {
                continue;
            }
            $ambito = (string) ($regla->ambito ?: self::AMBITO_TODAS);
            if (! in_array($ambito, [self::AMBITO_TODAS, self::AMBITO_PAREJA], true)) {
                $ambito = self::AMBITO_TODAS;
            }
            $out[] = [
                'codigos' => $codigos,
                'ambito' => $ambito,
                'empresa_a_id' => $regla->empresa_a_id ? (int) $regla->empresa_a_id : null,
                'empresa_b_id' => $regla->empresa_b_id ? (int) $regla->empresa_b_id : null,
            ];
        }

        return $out;
    }

    /**
     * @deprecated usar reglasActivas + filtrarMovimientos
     *
     * @return array<int, true>
     */
    public function mapaCodigosEliminar(?int $reporteId): array
    {
        /** @var array<int, true> $out */
        $out = [];
        foreach ($this->reglasActivas($reporteId) as $r) {
            if ($r['ambito'] !== self::AMBITO_TODAS) {
                continue;
            }
            foreach (array_keys($r['codigos']) as $c) {
                $out[(int) $c] = true;
            }
        }

        return $out;
    }

    public function tieneReglasPareja(?int $reporteId): bool
    {
        foreach ($this->reglasActivas($reporteId) as $r) {
            if ($r['ambito'] === self::AMBITO_PAREJA) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{codigo: int, ccosto: int, monto: float, fecha: string, empresa_id?: int}>  $movimientos
     * @param  list<array{codigos: array<int, true>, ambito: string, empresa_a_id: int|null, empresa_b_id: int|null}>|array<int, true>  $reglasOMapa
     * @return list<array{codigo: int, ccosto: int, monto: float, fecha: string, empresa_id?: int}>
     */
    public function filtrarMovimientos(array $movimientos, array $reglasOMapa): array
    {
        if ($reglasOMapa === []) {
            return $movimientos;
        }

        // Compat: mapa plano codigo => true
        $esMapaPlano = ! isset($reglasOMapa[0]) && ! array_is_list($reglasOMapa);
        if ($esMapaPlano || (isset($reglasOMapa[0]) && ! is_array($reglasOMapa[0] ?? null) && ! isset($reglasOMapa[0]['codigos']))) {
            // Detectar lista de ints como keys
            $first = reset($reglasOMapa);
            if ($first === true || $first === 1 || $first === null) {
                $out = [];
                foreach ($movimientos as $mov) {
                    if (isset($reglasOMapa[(int) $mov['codigo']])) {
                        continue;
                    }
                    $out[] = $mov;
                }

                return $out;
            }
        }

        $reglas = $reglasOMapa;
        if ($reglas === []) {
            return $movimientos;
        }

        $out = [];
        foreach ($movimientos as $mov) {
            $codigo = (int) $mov['codigo'];
            $emp = (int) ($mov['empresa_id'] ?? 0);
            $eliminar = false;
            foreach ($reglas as $r) {
                if (! isset($r['codigos'][$codigo])) {
                    continue;
                }
                if (($r['ambito'] ?? self::AMBITO_TODAS) === self::AMBITO_PAREJA) {
                    $a = (int) ($r['empresa_a_id'] ?? 0);
                    $b = (int) ($r['empresa_b_id'] ?? 0);
                    if ($emp > 0 && ($emp === $a || $emp === $b)) {
                        $eliminar = true;
                        break;
                    }
                    continue;
                }
                $eliminar = true;
                break;
            }
            if (! $eliminar) {
                $out[] = $mov;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, float>  $porCodigo
     * @param  array<int, true>|list<array{codigos: array<int, true>, ambito: string}>  $reglasOMapa
     * @return array<int, float>
     */
    public function filtrarMapaCodigo(array $porCodigo, array $reglasOMapa): array
    {
        if ($reglasOMapa === []) {
            return $porCodigo;
        }
        $mapa = $this->mapaDesdeReglasOMapa($reglasOMapa);
        foreach (array_keys($mapa) as $codigo) {
            unset($porCodigo[$codigo]);
        }

        return $porCodigo;
    }

    /**
     * @param  array<int, true>|list<array{codigos: array<int, true>}>  $reglasOMapa
     * @return array<int, true>
     */
    private function mapaDesdeReglasOMapa(array $reglasOMapa): array
    {
        if ($reglasOMapa === []) {
            return [];
        }
        $first = reset($reglasOMapa);
        if (is_array($first) && isset($first['codigos'])) {
            /** @var array<int, true> $out */
            $out = [];
            foreach ($reglasOMapa as $r) {
                if (($r['ambito'] ?? self::AMBITO_TODAS) === self::AMBITO_PAREJA) {
                    continue; // mapa consolidado no puede aplicar pareja
                }
                foreach (array_keys($r['codigos']) as $c) {
                    $out[(int) $c] = true;
                }
            }

            return $out;
        }

        /** @var array<int, true> $reglasOMapa */

        return $reglasOMapa;
    }

    public function debeAplicar(array $filtros, array $empresaIds): bool
    {
        if (count($empresaIds) < 2) {
            return false;
        }

        return (bool) ($filtros['consolidar_empresas'] ?? true);
    }

    /**
     * @return Collection<int, ReporteContableEliRegla>
     */
    public function listarParaInforme(int $reporteId): Collection
    {
        return ReporteContableEliRegla::query()
            ->where(function ($w) use ($reporteId) {
                $w->whereNull('reporte_contable_id')
                    ->orWhere('reporte_contable_id', $reporteId);
            })
            ->orderByRaw('(reporte_contable_id IS NULL) DESC')
            ->orderBy('orden')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function crearRegla(int $reporteId, array $data): ReporteContableEliRegla
    {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        $desde = (int) ($data['codigo_desde'] ?? 0);
        $hasta = isset($data['codigo_hasta']) && (int) $data['codigo_hasta'] > 0
            ? (int) $data['codigo_hasta']
            : null;
        if ($nombre === '' || $desde <= 0) {
            throw ValidationException::withMessages(['nombre' => 'Nombre y código desde son obligatorios.']);
        }
        if ($hasta !== null && $hasta < $desde) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        $ambito = (string) ($data['ambito'] ?? self::AMBITO_TODAS);
        if (! in_array($ambito, [self::AMBITO_TODAS, self::AMBITO_PAREJA], true)) {
            $ambito = self::AMBITO_TODAS;
        }
        $empA = $ambito === self::AMBITO_PAREJA ? max(0, (int) ($data['empresa_a_id'] ?? 0)) : null;
        $empB = $ambito === self::AMBITO_PAREJA ? max(0, (int) ($data['empresa_b_id'] ?? 0)) : null;
        if ($ambito === self::AMBITO_PAREJA && ($empA <= 0 || $empB <= 0 || $empA === $empB)) {
            throw ValidationException::withMessages(['ambito' => 'Ámbito pareja requiere dos empresas distintas.']);
        }

        return ReporteContableEliRegla::query()->create([
            'reporte_contable_id' => $reporteId,
            'nombre' => $nombre,
            'codigo_desde' => $desde,
            'codigo_hasta' => $hasta,
            'activo' => ! array_key_exists('activo', $data) || (bool) $data['activo'],
            'orden' => (int) ($data['orden'] ?? 0),
            'ambito' => $ambito,
            'empresa_a_id' => $empA ?: null,
            'empresa_b_id' => $empB ?: null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function actualizarRegla(ReporteContableEliRegla $regla, array $data): ReporteContableEliRegla
    {
        if ($regla->reporte_contable_id === null) {
            throw ValidationException::withMessages(['regla' => 'Las reglas globales no se editan desde el informe.']);
        }
        if (array_key_exists('nombre', $data)) {
            $regla->nombre = trim((string) $data['nombre']);
        }
        if (array_key_exists('codigo_desde', $data)) {
            $regla->codigo_desde = (int) $data['codigo_desde'];
        }
        if (array_key_exists('codigo_hasta', $data)) {
            $hasta = (int) ($data['codigo_hasta'] ?? 0);
            $regla->codigo_hasta = $hasta > 0 ? $hasta : null;
        }
        if (array_key_exists('activo', $data)) {
            $regla->activo = (bool) $data['activo'];
        }
        if (array_key_exists('orden', $data)) {
            $regla->orden = (int) $data['orden'];
        }
        if (array_key_exists('ambito', $data)) {
            $ambito = (string) $data['ambito'];
            $regla->ambito = in_array($ambito, [self::AMBITO_TODAS, self::AMBITO_PAREJA], true)
                ? $ambito
                : self::AMBITO_TODAS;
        }
        if (array_key_exists('empresa_a_id', $data)) {
            $regla->empresa_a_id = (int) $data['empresa_a_id'] ?: null;
        }
        if (array_key_exists('empresa_b_id', $data)) {
            $regla->empresa_b_id = (int) $data['empresa_b_id'] ?: null;
        }
        if ($regla->codigo_hasta !== null && $regla->codigo_hasta < $regla->codigo_desde) {
            $tmp = $regla->codigo_desde;
            $regla->codigo_desde = $regla->codigo_hasta;
            $regla->codigo_hasta = $tmp;
        }
        $regla->save();

        return $regla;
    }

    public function eliminarRegla(ReporteContableEliRegla $regla): void
    {
        if ($regla->reporte_contable_id === null) {
            throw ValidationException::withMessages(['regla' => 'No se pueden borrar reglas globales desde el informe.']);
        }
        $regla->delete();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function payloadUi(int $reporteId): array
    {
        $rango = app(ReporteDefinibleCuentaRangoSupport::class);
        $out = [];
        foreach ($this->listarParaInforme($reporteId) as $r) {
            $hasta = $r->codigo_hasta !== null ? (int) $r->codigo_hasta : null;
            $out[] = [
                'id' => (int) $r->id,
                'nombre' => (string) $r->nombre,
                'codigo_desde' => (int) $r->codigo_desde,
                'codigo_hasta' => $hasta,
                'codigo_fmt' => $rango->etiqueta((int) $r->codigo_desde, $hasta),
                'activo' => (bool) $r->activo,
                'orden' => (int) $r->orden,
                'es_global' => $r->reporte_contable_id === null,
                'ambito' => (string) ($r->ambito ?: self::AMBITO_TODAS),
                'empresa_a_id' => $r->empresa_a_id ? (int) $r->empresa_a_id : null,
                'empresa_b_id' => $r->empresa_b_id ? (int) $r->empresa_b_id : null,
            ];
        }

        return $out;
    }
}
