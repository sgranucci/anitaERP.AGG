<?php

namespace App\Services\Uif;

use App\Models\Uif\Cliente_Archivo_Uif;
use App\Models\Uif\Cliente_Premio_Uif;
use App\Models\Uif\Cliente_Riesgo_Uif;
use App\Models\Uif\Cliente_Uif;
use App\Support\Uif\ClienteUifOrigenPcSupport;
use Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class ClienteUifUnificarService
{
    /**
     * @return array{
     *   ok: bool,
     *   errores: list<string>,
     *   advertencias: list<string>,
     *   cross_origen: bool,
     *   conservar: array<string, mixed>|null,
     *   absorber: array<string, mixed>|null,
     *   premios: list<array<string, mixed>>,
     *   archivos: list<array<string, mixed>>,
     *   riesgos: list<array<string, mixed>>,
     *   riesgos_conflicto: list<array<string, mixed>>,
     *   premios_conflicto: list<array<string, mixed>>,
     *   copiara_inroclienteid: int|null,
     *   resumen: array<string, int>
     * }
     */
    public function preview(int $conservarId, int $absorberId): array
    {
        try {
            [$conservar, $absorber] = $this->cargarYValidar($conservarId, $absorberId);
        } catch (InvalidArgumentException $e) {
            return $this->respuestaVacia(false, [$e->getMessage()]);
        }

        return $this->armarPreview($conservar, $absorber);
    }

    /**
     * @return array{ok: bool, mensaje: string, conservar_id: int, errores: list<string>}
     */
    public function ejecutar(int $conservarId, int $absorberId): array
    {
        $preview = $this->preview($conservarId, $absorberId);
        if (! $preview['ok']) {
            return [
                'ok' => false,
                'mensaje' => implode(' ', $preview['errores']),
                'conservar_id' => $conservarId,
                'errores' => $preview['errores'],
            ];
        }

        try {
            DB::transaction(function () use ($conservarId, $absorberId, $preview) {
                /** @var Cliente_Uif $conservar */
                $conservar = Cliente_Uif::query()->lockForUpdate()->findOrFail($conservarId);
                /** @var Cliente_Uif $absorber */
                $absorber = Cliente_Uif::query()->lockForUpdate()->findOrFail($absorberId);

                $this->assertValidacionBasica($conservar, $absorber);

                $mismoOrigen = $this->mismoOrigen($conservar, $absorber);

                $periodosConservar = Cliente_Riesgo_Uif::query()
                    ->where('cliente_uif_id', $conservar->id)
                    ->pluck('periodo')
                    ->map(fn ($p) => (string) $p)
                    ->all();

                $riesgosConflicto = Cliente_Riesgo_Uif::query()
                    ->where('cliente_uif_id', $absorber->id)
                    ->whereIn('periodo', $periodosConservar)
                    ->get();
                foreach ($riesgosConflicto as $riesgo) {
                    $riesgo->delete();
                }

                // Conflicto Anita premio: mismo inro + misma sala (IDs se repiten entre bridges).
                $paresConservar = Cliente_Premio_Uif::query()
                    ->where('cliente_uif_id', $conservar->id)
                    ->whereNotNull('anita_inropremioid')
                    ->get(['anita_inropremioid', 'sala_id'])
                    ->map(fn ($p) => ((int) $p->anita_inropremioid).':'.((int) ($p->sala_id ?? 0)))
                    ->all();

                $premiosAbsorber = Cliente_Premio_Uif::query()
                    ->where('cliente_uif_id', $absorber->id)
                    ->get();
                foreach ($premiosAbsorber as $premio) {
                    $anitaId = (int) ($premio->anita_inropremioid ?? 0);
                    if ($anitaId > 0) {
                        $clave = $anitaId.':'.((int) ($premio->sala_id ?? 0));
                        if (in_array($clave, $paresConservar, true)) {
                            $premio->delete();

                            continue;
                        }
                    }
                    $premio->cliente_uif_id = $conservar->id;
                    $premio->save();
                }

                Cliente_Archivo_Uif::query()
                    ->where('cliente_uif_id', $absorber->id)
                    ->update(['cliente_uif_id' => $conservar->id]);

                Cliente_Riesgo_Uif::query()
                    ->where('cliente_uif_id', $absorber->id)
                    ->update(['cliente_uif_id' => $conservar->id]);

                // Solo copiar inro dentro del mismo origen (bridges distintos no son intercambiables).
                if ($mismoOrigen) {
                    $inroAbsorber = (int) ($absorber->inroclienteid ?? 0);
                    $inroConservar = (int) ($conservar->inroclienteid ?? 0);
                    if ($inroConservar <= 0 && $inroAbsorber > 0) {
                        $absorber->inroclienteid = null;
                        $absorber->save();
                        $conservar->inroclienteid = $inroAbsorber;
                        $conservar->save();
                    }
                }

                $absorber->forceDelete();

                Log::info('Cliente UIF unificado', [
                    'conservar_id' => $conservar->id,
                    'absorber_id' => $absorberId,
                    'cross_origen' => ! $mismoOrigen,
                    'usuario_id' => Auth::id(),
                    'resumen' => $preview['resumen'],
                    'copiara_inroclienteid' => $preview['copiara_inroclienteid'],
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Error unificando cliente UIF', [
                'conservar_id' => $conservarId,
                'absorber_id' => $absorberId,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException(
                'No se pudo unificar: '.$e->getMessage(),
                0,
                $e
            );
        }

        return [
            'ok' => true,
            'mensaje' => 'Clientes unificados. La ficha absorbida fue eliminada. Los premios conservan su sala.',
            'conservar_id' => $conservarId,
            'errores' => [],
        ];
    }

    /**
     * @return array{0: Cliente_Uif, 1: Cliente_Uif}
     */
    private function cargarYValidar(int $conservarId, int $absorberId): array
    {
        if ($conservarId <= 0 || $absorberId <= 0) {
            throw new InvalidArgumentException('Debe indicar los dos clientes (conservar y absorber).');
        }
        if ($conservarId === $absorberId) {
            throw new InvalidArgumentException('No se puede unificar un cliente consigo mismo.');
        }

        $with = [
            'tipodocumentos:id,nombre',
            'cliente_premios_uif' => fn ($q) => $q
                ->with(['juegos_uif:id,nombre', 'salas:id,nombre'])
                ->orderByDesc('fechaentrega')
                ->orderByDesc('id'),
            'cliente_archivos_uif',
            'cliente_riesgos_uif',
        ];

        $conservar = Cliente_Uif::query()->with($with)->find($conservarId);
        $absorber = Cliente_Uif::query()->with($with)->find($absorberId);

        if ($conservar === null) {
            throw new InvalidArgumentException("Cliente a conservar #{$conservarId} no encontrado.");
        }
        if ($absorber === null) {
            throw new InvalidArgumentException("Cliente a absorber #{$absorberId} no encontrado.");
        }

        $this->assertValidacionBasica($conservar, $absorber);

        return [$conservar, $absorber];
    }

    private function assertValidacionBasica(Cliente_Uif $conservar, Cliente_Uif $absorber): void
    {
        $origenC = strtolower(trim((string) ($conservar->anita_origen ?? '')));
        $origenA = strtolower(trim((string) ($absorber->anita_origen ?? '')));
        if ($origenC === '' || $origenA === '') {
            throw new InvalidArgumentException('Ambos clientes deben tener origen Anita (BSA/KSA/RSA).');
        }
    }

    private function mismoOrigen(Cliente_Uif $conservar, Cliente_Uif $absorber): bool
    {
        return strtolower(trim((string) ($conservar->anita_origen ?? '')))
            === strtolower(trim((string) ($absorber->anita_origen ?? '')));
    }

    /**
     * @return array{
     *   ok: bool,
     *   errores: list<string>,
     *   advertencias: list<string>,
     *   cross_origen: bool,
     *   conservar: array<string, mixed>|null,
     *   absorber: array<string, mixed>|null,
     *   premios: list<array<string, mixed>>,
     *   archivos: list<array<string, mixed>>,
     *   riesgos: list<array<string, mixed>>,
     *   riesgos_conflicto: list<array<string, mixed>>,
     *   premios_conflicto: list<array<string, mixed>>,
     *   copiara_inroclienteid: int|null,
     *   resumen: array<string, int>
     * }
     */
    private function armarPreview(Cliente_Uif $conservar, Cliente_Uif $absorber): array
    {
        $mismoOrigen = $this->mismoOrigen($conservar, $absorber);
        $advertencias = [];
        if (! $mismoOrigen) {
            $advertencias[] = 'Orígenes distintos: los premios conservan su sala de origen. '
                .'La ficha queda con origen '.ClienteUifOrigenPcSupport::labelOrigen((string) $conservar->anita_origen)
                .'. El sync Anita del origen absorbido puede recrear esa ficha.';
        }

        $periodosConservar = $conservar->cliente_riesgos_uif
            ->pluck('periodo')
            ->map(fn ($p) => (string) $p)
            ->all();

        $riesgosMover = [];
        $riesgosConflicto = [];
        foreach ($absorber->cliente_riesgos_uif as $riesgo) {
            $fila = [
                'id' => (int) $riesgo->id,
                'periodo' => (string) ($riesgo->periodo ?? ''),
                'riesgo' => $riesgo->riesgo,
                'inusualidad_uif_id' => (int) ($riesgo->inusualidad_uif_id ?? 0),
            ];
            if (in_array((string) ($riesgo->periodo ?? ''), $periodosConservar, true)) {
                $riesgosConflicto[] = $fila;
            } else {
                $riesgosMover[] = $fila;
            }
        }

        $paresConservar = $conservar->cliente_premios_uif
            ->filter(fn ($p) => (int) ($p->anita_inropremioid ?? 0) > 0)
            ->map(fn ($p) => ((int) $p->anita_inropremioid).':'.((int) ($p->sala_id ?? 0)))
            ->all();

        $premiosMover = [];
        $premiosConflicto = [];
        foreach ($absorber->cliente_premios_uif as $premio) {
            $fila = $this->mapPremio($premio);
            $anitaId = (int) ($premio->anita_inropremioid ?? 0);
            $clave = $anitaId.':'.((int) ($premio->sala_id ?? 0));
            if ($anitaId > 0 && in_array($clave, $paresConservar, true)) {
                $premiosConflicto[] = $fila;
            } else {
                $premiosMover[] = $fila;
            }
        }

        $archivos = $absorber->cliente_archivos_uif->map(fn ($a) => [
            'id' => (int) $a->id,
            'nombrearchivo' => (string) ($a->nombrearchivo ?? ''),
        ])->values()->all();

        $copiaraInro = null;
        if ($mismoOrigen) {
            $inroAbsorber = (int) ($absorber->inroclienteid ?? 0);
            $inroConservar = (int) ($conservar->inroclienteid ?? 0);
            if ($inroConservar <= 0 && $inroAbsorber > 0) {
                $copiaraInro = $inroAbsorber;
            }
        }

        return [
            'ok' => true,
            'errores' => [],
            'advertencias' => $advertencias,
            'cross_origen' => ! $mismoOrigen,
            'conservar' => $this->mapCliente($conservar),
            'absorber' => $this->mapCliente($absorber),
            'premios' => $premiosMover,
            'archivos' => $archivos,
            'riesgos' => $riesgosMover,
            'riesgos_conflicto' => $riesgosConflicto,
            'premios_conflicto' => $premiosConflicto,
            'copiara_inroclienteid' => $copiaraInro,
            'resumen' => [
                'premios_mover' => count($premiosMover),
                'premios_conflicto' => count($premiosConflicto),
                'archivos_mover' => count($archivos),
                'riesgos_mover' => count($riesgosMover),
                'riesgos_conflicto' => count($riesgosConflicto),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCliente(Cliente_Uif $c): array
    {
        $ultimo = $c->cliente_premios_uif->first();

        return [
            'id' => (int) $c->id,
            'nombre' => (string) ($c->nombre ?? ''),
            'numerodocumento' => (string) ($c->numerodocumento ?? ''),
            'tipodocumento' => (string) ($c->tipodocumentos->nombre ?? 'DNI'),
            'anita_origen' => (string) ($c->anita_origen ?? ''),
            'origen_label' => ClienteUifOrigenPcSupport::labelOrigen((string) ($c->anita_origen ?? '')),
            'domicilio' => (string) ($c->domicilio ?? ''),
            'telefono' => (string) ($c->telefono ?? ''),
            'email' => (string) ($c->email ?? ''),
            'estado' => (string) ($c->estado ?? ''),
            'inroclienteid' => $c->inroclienteid !== null ? (int) $c->inroclienteid : null,
            'premios_count' => $c->cliente_premios_uif->count(),
            'archivos_count' => $c->cliente_archivos_uif->count(),
            'riesgos_count' => $c->cliente_riesgos_uif->count(),
            'ultimo_premio' => $ultimo ? $this->mapPremio($ultimo) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPremio(Cliente_Premio_Uif $p): array
    {
        $fecha = $p->fechaentrega;
        $fechaTxt = $fecha ? $fecha->format('d/m/Y H:i') : '';

        return [
            'id' => (int) $p->id,
            'fechaentrega' => $fechaTxt,
            'monto' => (float) ($p->monto ?? 0),
            'monto_fmt' => number_format((float) ($p->monto ?? 0), 2, ',', '.'),
            'detalle' => (string) ($p->detalle ?? ''),
            'numerotito' => (string) ($p->numerotito ?? ''),
            'anita_inropremioid' => $p->anita_inropremioid !== null ? (int) $p->anita_inropremioid : null,
            'sala_id' => $p->sala_id !== null ? (int) $p->sala_id : null,
            'sala' => (string) (optional($p->salas)->nombre ?? ''),
            'juego' => (string) (optional($p->juegos_uif)->nombre ?? ''),
        ];
    }

    /**
     * @param  list<string>  $errores
     * @return array{
     *   ok: bool,
     *   errores: list<string>,
     *   advertencias: list<string>,
     *   cross_origen: bool,
     *   conservar: null,
     *   absorber: null,
     *   premios: list<empty>,
     *   archivos: list<empty>,
     *   riesgos: list<empty>,
     *   riesgos_conflicto: list<empty>,
     *   premios_conflicto: list<empty>,
     *   copiara_inroclienteid: null,
     *   resumen: array<string, int>
     * }
     */
    private function respuestaVacia(bool $ok, array $errores): array
    {
        return [
            'ok' => $ok,
            'errores' => $errores,
            'advertencias' => [],
            'cross_origen' => false,
            'conservar' => null,
            'absorber' => null,
            'premios' => [],
            'archivos' => [],
            'riesgos' => [],
            'riesgos_conflicto' => [],
            'premios_conflicto' => [],
            'copiara_inroclienteid' => null,
            'resumen' => [
                'premios_mover' => 0,
                'premios_conflicto' => 0,
                'archivos_mover' => 0,
                'riesgos_mover' => 0,
                'riesgos_conflicto' => 0,
            ],
        ];
    }
}
