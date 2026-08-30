<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\ApiAnita;
use App\Models\Ventas\Concepto_Venta;
use App\Models\Ventas\Tipotransaccion;
use Illuminate\Support\Facades\Log;

/**
 * Alta de NC/ND de Anita (t_comp) que faltan para facturar, con concepto default.
 * No pisa tipos existentes: solo crea o completa concepto_venta_id vacío.
 */
class TipotransaccionNcNdAnitaImportService
{
    /** Activos en Anita y usados para facturar NC/ND. Sin internos inactivos (NCI/NDI). */
    private const CLAVES = [
        'NCA', 'NCP', 'NCR',
        'NDA', 'NDB', 'NDE', 'NDJ', 'NDP', 'NDR', 'NDT',
    ];

    /**
     * @return array{
     *     en_anita: int,
     *     crear: int,
     *     completar_concepto: int,
     *     ya_ok: int,
     *     omitidos: int,
     *     errores: list<string>,
     *     detalle: list<array<string, mixed>>
     * }
     */
    public function analizar(): array
    {
        return $this->procesar(false);
    }

    /**
     * @return array{
     *     en_anita: int,
     *     crear: int,
     *     completar_concepto: int,
     *     ya_ok: int,
     *     omitidos: int,
     *     errores: list<string>,
     *     detalle: list<array<string, mixed>>
     * }
     */
    public function ejecutar(): array
    {
        return $this->procesar(true);
    }

    /**
     * @return array{
     *     en_anita: int,
     *     crear: int,
     *     completar_concepto: int,
     *     ya_ok: int,
     *     omitidos: int,
     *     errores: list<string>,
     *     detalle: list<array<string, mixed>>
     * }
     */
    private function procesar(bool $persistir): array
    {
        $ret = [
            'en_anita' => 0,
            'crear' => 0,
            'completar_concepto' => 0,
            'ya_ok' => 0,
            'omitidos' => 0,
            'errores' => [],
            'detalle' => [],
        ];

        $filas = $this->listarNcNdAnita();
        $conceptos = Concepto_Venta::query()
            ->whereNotNull('codigo_anita')
            ->get()
            ->keyBy(fn (Concepto_Venta $c) => (int) $c->codigo_anita);

        foreach (self::CLAVES as $clave) {
            $fila = $filas[$clave] ?? null;
            if ($fila === null) {
                $ret['omitidos']++;
                $ret['detalle'][] = [
                    'abreviatura' => $clave,
                    'nombre' => '',
                    'afip' => '',
                    'anita' => '',
                    'concepto' => '',
                    'accion' => 'no está en Anita',
                ];
                continue;
            }

            $ret['en_anita']++;
            $estadoAnita = strtoupper(trim((string) ($fila['tcomp_estado'] ?? '')));
            if ($estadoAnita === 'I') {
                $ret['omitidos']++;
                $ret['detalle'][] = [
                    'abreviatura' => $clave,
                    'nombre' => trim((string) ($fila['tcomp_desc'] ?? '')),
                    'afip' => '',
                    'anita' => (int) ($fila['tcomp_concepto'] ?? 0) ?: '',
                    'concepto' => '',
                    'accion' => 'inactivo en Anita',
                ];
                continue;
            }

            $esNc = str_starts_with($clave, 'NC');
            $codigoAnita = $this->resolverCodigoConceptoAnita($clave, $fila, $esNc);
            $concepto = $codigoAnita > 0 ? ($conceptos[$codigoAnita] ?? null) : null;
            $nombre = $this->nombreDesdeAnita($fila, $clave);
            $codigoAfip = $this->codigoAfipAlmacenado($clave, $fila, $esNc);

            $existente = Tipotransaccion::withTrashed()
                ->where('abreviatura', $clave)
                ->first();

            if ($existente !== null) {
                $yaConcepto = (int) ($existente->concepto_venta_id ?? 0);
                if ($existente->trashed()) {
                    $ret['crear']++;
                    $ret['detalle'][] = [
                        'abreviatura' => $clave,
                        'nombre' => $nombre,
                        'afip' => $codigoAfip,
                        'anita' => $codigoAnita ?: '',
                        'concepto' => $concepto?->codigo ?? '',
                        'accion' => $persistir ? 'restaurado' : 'a restaurar',
                    ];
                    if ($persistir) {
                        $existente->restore();
                        $this->aplicarCampos($existente, $clave, $nombre, $codigoAfip, $esNc, $concepto?->id);
                    }
                    continue;
                }
                if ($yaConcepto > 0) {
                    $ret['ya_ok']++;
                    $ret['detalle'][] = [
                        'abreviatura' => $clave,
                        'nombre' => $existente->nombre,
                        'afip' => $existente->codigo,
                        'anita' => $codigoAnita ?: '',
                        'concepto' => $yaConcepto,
                        'accion' => 'ya estaba',
                    ];
                    continue;
                }
                $ret['completar_concepto']++;
                $ret['detalle'][] = [
                    'abreviatura' => $clave,
                    'nombre' => $existente->nombre,
                    'afip' => $existente->codigo,
                    'anita' => $codigoAnita ?: '',
                    'concepto' => $concepto?->codigo ?? '',
                    'accion' => $persistir ? 'concepto asignado' : 'a completar concepto',
                ];
                if ($persistir && $concepto !== null) {
                    $existente->concepto_venta_id = $concepto->id;
                    $existente->save();
                }
                continue;
            }

            $ret['crear']++;
            $ret['detalle'][] = [
                'abreviatura' => $clave,
                'nombre' => $nombre,
                'afip' => $codigoAfip,
                'anita' => $codigoAnita ?: '',
                'concepto' => $concepto?->codigo ?? '',
                'accion' => $persistir ? 'creado' : 'a crear',
            ];

            if ($persistir) {
                $this->aplicarCampos(new Tipotransaccion(), $clave, $nombre, $codigoAfip, $esNc, $concepto?->id);
            }
        }

        return $ret;
    }

    private function aplicarCampos(
        Tipotransaccion $tipo,
        string $clave,
        string $nombre,
        string $codigoAfip,
        bool $esNc,
        ?int $conceptoId,
    ): void {
        $tipo->nombre = $nombre;
        $tipo->abreviatura = $clave;
        $tipo->codigo = $codigoAfip;
        $tipo->operacion = $esNc ? 'C' : 'V';
        $tipo->operacionstock = $esNc ? 'E' : 'S';
        $tipo->signo = $esNc ? 'R' : 'S';
        $tipo->estado = 'A';
        $tipo->concepto_venta_id = $conceptoId;
        $tipo->save();
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function resolverCodigoConceptoAnita(string $clave, array $fila, bool $esNc): int
    {
        $anita = (int) ($fila['tcomp_concepto'] ?? 0);
        if ($anita > 0) {
            return $anita;
        }

        return $esNc ? 1 : 2;
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function codigoAfipAlmacenado(string $clave, array $fila, bool $esNc): string
    {
        $mipyme = strtoupper(trim((string) ($fila['tcomp_tipo_oper'] ?? ''))) === 'A';
        if ($mipyme) {
            return $esNc ? '203' : '202';
        }

        return $esNc ? '003' : '002';
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function nombreDesdeAnita(array $fila, string $clave): string
    {
        $nombre = trim((string) ($fila['tcomp_desc'] ?? ''));
        if ($nombre === '') {
            $nombre = $clave;
        }
        if (mb_strlen($nombre) > 255) {
            $nombre = mb_substr($nombre, 0, 255);
        }

        $existe = Tipotransaccion::withTrashed()
            ->where('nombre', $nombre)
            ->where('abreviatura', '!=', $clave)
            ->exists();
        if ($existe) {
            $nombre = $clave.' — '.$nombre;
        }

        return $nombre;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function listarNcNdAnita(): array
    {
        try {
            $api = new ApiAnita();
            $raw = $api->apiCall([
                'acc' => 'list',
                'sistema' => 'ventas',
                'tabla' => 't_comp',
                'campos' => 'tcomp_clave,tcomp_desc,tcomp_oper,tcomp_concepto,tcomp_estado,tcomp_tipo_comp,tcomp_tipo_oper',
                'orderBy' => 'tcomp_clave',
            ]);
            $error = ApiAnita::extraerMensajeError($raw);
            if ($error !== null) {
                throw new \RuntimeException($error);
            }
            $filas = json_decode((string) $raw, true);
            if (! is_array($filas)) {
                return [];
            }
            $map = [];
            foreach ($filas as $fila) {
                $clave = strtoupper(trim((string) ($fila['tcomp_clave'] ?? '')));
                if ($clave !== '' && (str_starts_with($clave, 'NC') || str_starts_with($clave, 'ND'))) {
                    $map[$clave] = $fila;
                }
            }

            return $map;
        } catch (\Throwable $e) {
            Log::warning('tipotransaccion NC/ND Anita: t_comp falló', ['e' => $e->getMessage()]);
            throw $e;
        }
    }
}
