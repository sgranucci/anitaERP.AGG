<?php

namespace App\Services\Caja\Estacionamiento;

use App\Models\Caja\Estacionamiento\JornadaEstacionamiento;
use App\Models\Caja\Estacionamiento\VentaEstacionamientoEmision;
use App\Repositories\Caja\Estacionamiento\JornadaEstacionamientoRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Apertura/cierre de jornada operativa por empresa (estacionamiento).
 * fecha factura = día calendario real; fecha jornada = día de turno abierto.
 */
final class JornadaEstacionamientoService
{
    public function __construct(
        private readonly JornadaEstacionamientoRepositoryInterface $jornadaRepository,
    ) {
    }

    public function jornadaAbierta(int $empresaId): ?JornadaEstacionamiento
    {
        if ($empresaId <= 0) {
            return null;
        }

        return $this->jornadaRepository->jornadaAbiertaPorEmpresa($empresaId);
    }

    public function exigirJornadaAbierta(int $empresaId): JornadaEstacionamiento
    {
        $jornada = $this->jornadaAbierta($empresaId);
        if ($jornada === null) {
            throw new InvalidArgumentException(
                'No hay jornada abierta para esta empresa. Abra la jornada en Caja → Estacionamiento → Jornada.'
            );
        }

        return $jornada;
    }

    /**
     * @return array{fechafactura:string,fechajornada:string,jornada_id:int}
     */
    public function fechasParaEmision(int $empresaId): array
    {
        $jornada = $this->exigirJornadaAbierta($empresaId);

        return [
            'fechafactura' => Carbon::today()->format('Y-m-d'),
            'fechajornada' => $jornada->fecha_jornada->format('Y-m-d'),
            'jornada_id' => (int) $jornada->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function aplicarFechasAlPayload(array $payload, int $empresaId): array
    {
        $fechas = $this->fechasParaEmision($empresaId);
        $payload['fechafactura'] = $fechas['fechafactura'];
        $payload['fechajornada'] = $fechas['fechajornada'];
        $payload['jornada_estacionamiento_id'] = $fechas['jornada_id'];

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function estadoParaEmpresa(int $empresaId): array
    {
        $abierta = $this->jornadaAbierta($empresaId);
        $hoy = Carbon::today()->format('Y-m-d');

        $ultimaJornada = $this->jornadaRepository->ultimaJornadaPorEmpresa($empresaId);
        $fechasApertura = $this->fechasSugeridasApertura($ultimaJornada);

        return [
            'empresa_id' => $empresaId,
            'jornada_abierta' => $abierta !== null,
            'jornada_id' => $abierta?->id,
            'fecha_jornada' => $abierta?->fecha_jornada?->format('Y-m-d'),
            'fecha_jornada_fmt' => $abierta?->fecha_jornada
                ? $this->formatearFechaEncabezado($abierta->fecha_jornada->format('Y-m-d'))
                : null,
            'fecha_factura_hoy' => $hoy,
            'fecha_factura_hoy_fmt' => $this->formatearFechaEncabezado($hoy),
            'apertura_en' => $abierta?->apertura_en?->format('d/m/Y H:i'),
            'fecha_jornada_minima_abrir' => $fechasApertura['minima'],
            'fecha_jornada_sugerida_abrir' => $fechasApertura['sugerida'],
            'usuario_apertura' => $abierta?->usuarioApertura?->nombre,
            'observacion_apertura' => $abierta?->observacion_apertura,
            'puede_abrir' => $abierta === null,
            'puede_cerrar' => $abierta !== null,
            'motivo_no_puede_abrir' => $abierta !== null
                ? $this->describirJornadaAbierta($abierta)
                : null,
            'errores_cierre' => $abierta !== null
                ? $this->erroresAntesDeCerrar($empresaId)
                : [],
        ];
    }

    public function validarApertura(int $empresaId, string $fechaJornada): string
    {
        if ($empresaId <= 0) {
            throw new InvalidArgumentException(
                'Debe seleccionar una empresa antes de abrir la jornada.'
            );
        }

        if (trim($fechaJornada) === '') {
            throw new InvalidArgumentException(
                'Debe indicar la fecha de jornada (día de turno).'
            );
        }

        $fecha = $this->normalizarFecha($fechaJornada, 'fecha de jornada');
        $fechaFmt = $this->formatearFechaHumana($fecha);

        $abierta = $this->jornadaAbierta($empresaId);
        if ($abierta !== null) {
            throw new InvalidArgumentException(
                'No puede abrir la jornada del '.$fechaFmt.': '.$this->describirJornadaAbierta($abierta)
                .' Cierre esa jornada antes de abrir otra (aunque sea de otra fecha).'
            );
        }

        $ultima = $this->jornadaRepository->ultimaJornadaPorEmpresa($empresaId);
        if ($ultima !== null) {
            $ultimaFecha = $ultima->fecha_jornada->format('Y-m-d');
            if ($fecha <= $ultimaFecha) {
                $ultimaFmt = $this->formatearFechaHumana($ultimaFecha);
                $estadoUltima = $ultima->estado === JornadaEstacionamiento::ESTADO_ABIERTA
                    ? 'abierta'
                    : 'cerrada';

                throw new InvalidArgumentException(
                    'No puede abrir la jornada del '.$fechaFmt.': la última jornada registrada es del '
                    .$ultimaFmt.' ('.$estadoUltima.'). Solo puede abrir jornadas de fechas posteriores.'
                );
            }
        }

        return $fecha;
    }

    public function abrir(int $empresaId, string $fechaJornada, ?string $observacion = null): JornadaEstacionamiento
    {
        $fecha = $this->validarApertura($empresaId, $fechaJornada);

        if (Auth::id() === null) {
            throw new InvalidArgumentException(
                'No hay usuario autenticado. Vuelva a iniciar sesión e intente abrir la jornada.'
            );
        }

        try {
            return DB::transaction(function () use ($empresaId, $fecha, $observacion) {
                $existente = JornadaEstacionamiento::query()
                    ->with(['usuarioApertura', 'empresa'])
                    ->where('empresa_id', $empresaId)
                    ->where('estado', JornadaEstacionamiento::ESTADO_ABIERTA)
                    ->lockForUpdate()
                    ->first();

                if ($existente !== null) {
                    throw new InvalidArgumentException(
                        'No puede abrir la jornada del '.$this->formatearFechaHumana($fecha).': '
                        .$this->describirJornadaAbierta($existente)
                        .' (otro usuario pudo haberla abierto en este momento).'
                    );
                }

                return $this->jornadaRepository->create([
                    'empresa_id' => $empresaId,
                    'fecha_jornada' => $fecha,
                    'estado' => JornadaEstacionamiento::ESTADO_ABIERTA,
                    'usuario_apertura_id' => Auth::id(),
                    'apertura_en' => now(),
                    'observacion_apertura' => $this->limpiarObservacion($observacion),
                ]);
            });
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new InvalidArgumentException(self::mensajeDesdeExcepcion($e), 0, $e);
        }
    }

    public function cerrar(int $empresaId, ?string $observacion = null): JornadaEstacionamiento
    {
        $jornada = $this->exigirJornadaAbierta($empresaId);

        $errores = $this->erroresAntesDeCerrar($empresaId);
        if ($errores !== []) {
            throw new InvalidArgumentException(implode(' ', $errores));
        }

        $this->jornadaRepository->update([
            'estado' => JornadaEstacionamiento::ESTADO_CERRADA,
            'usuario_cierre_id' => Auth::id(),
            'cierre_en' => now(),
            'observacion_cierre' => $this->limpiarObservacion($observacion),
        ], (int) $jornada->id);

        return $this->jornadaRepository->findOrFail((int) $jornada->id);
    }

    /**
     * Bloqueantes para cerrar la jornada. Se ampliará cuando exista facturación de estacionamiento.
     *
     * @return list<string>
     */
    public function erroresAntesDeCerrar(int $empresaId): array
    {
        $ventas = $this->contarVentasEstacionamientoJornadaAbierta($empresaId);
        if ($ventas > 0) {
            return [
                'Hay '.$ventas.' comprobante(s) de estacionamiento pendiente(s) de cierre. '
                .'Complete la operación de facturación antes de cerrar la jornada.',
            ];
        }

        return [];
    }

    /**
     * @return array{
     *   puede_eliminar: bool,
     *   motivo_no_eliminar: ?string,
     *   ventas_estacionamiento: int
     * }
     */
    public function resumenEliminacion(JornadaEstacionamiento $jornada): array
    {
        $ventas = $this->contarVentasEstacionamientoJornada($jornada);

        if ($this->existeJornadaPosterior((int) $jornada->empresa_id, $jornada)) {
            return [
                'puede_eliminar' => false,
                'motivo_no_eliminar' => 'No se puede eliminar: ya existe una jornada posterior para esta empresa.',
                'ventas_estacionamiento' => $ventas,
            ];
        }

        if ($ventas > 0) {
            return [
                'puede_eliminar' => false,
                'motivo_no_eliminar' => 'No se puede eliminar: la jornada tiene '.$ventas.' comprobante(s) de estacionamiento.',
                'ventas_estacionamiento' => $ventas,
            ];
        }

        return [
            'puede_eliminar' => true,
            'motivo_no_eliminar' => null,
            'ventas_estacionamiento' => 0,
        ];
    }

    /**
     * @return array{
     *   puede_anular: bool,
     *   motivo_no_anular: ?string,
     *   jornada_id: int,
     *   texto_confirmacion: string,
     *   fecha_jornada_fmt: string,
     *   cierre_en_fmt: string,
     *   usuario_cierre: string
     * }
     */
    public function resumenAnulacionCierre(JornadaEstacionamiento $jornada): array
    {
        $jornada->loadMissing(['usuarioCierre']);
        $errores = $this->erroresAntesDeAnularCierre($jornada);
        $id = (int) $jornada->id;

        return [
            'puede_anular' => $errores === [],
            'motivo_no_anular' => $errores !== [] ? implode(' ', $errores) : null,
            'jornada_id' => $id,
            'texto_confirmacion' => 'ANULAR-JORNADA-'.$id,
            'fecha_jornada_fmt' => $jornada->fecha_jornada?->format('d/m/Y') ?? '',
            'cierre_en_fmt' => $jornada->cierre_en?->format('d/m/Y H:i') ?? '',
            'usuario_cierre' => (string) ($jornada->usuarioCierre?->nombre ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function cierreAnulableParaEmpresa(int $empresaId): ?array
    {
        if ($empresaId <= 0 || $this->jornadaAbierta($empresaId) !== null) {
            return null;
        }

        $ultima = $this->jornadaRepository->ultimaJornadaPorEmpresa($empresaId);
        if ($ultima === null || $ultima->estado !== JornadaEstacionamiento::ESTADO_CERRADA) {
            return null;
        }

        $resumen = $this->resumenAnulacionCierre($ultima);

        return $resumen['puede_anular'] ? $resumen : null;
    }

    /**
     * @return list<string>
     */
    public function erroresAntesDeAnularCierre(JornadaEstacionamiento $jornada): array
    {
        $errores = [];

        if ($jornada->estado !== JornadaEstacionamiento::ESTADO_CERRADA || $jornada->cierre_en === null) {
            $errores[] = 'La jornada no está cerrada.';

            return $errores;
        }

        if ($this->jornadaAbierta((int) $jornada->empresa_id) !== null) {
            $errores[] = 'Hay una jornada abierta para esta empresa. No puede anular el cierre mientras exista otra jornada activa.';
        }

        if ($this->existeJornadaPosterior((int) $jornada->empresa_id, $jornada)) {
            $errores[] = 'Ya existe una jornada posterior para esta empresa. No puede anular este cierre.';
        }

        $ultima = $this->jornadaRepository->ultimaJornadaPorEmpresa((int) $jornada->empresa_id);
        if ($ultima !== null && (int) $ultima->id !== (int) $jornada->id) {
            $errores[] = 'Solo puede anular el cierre de la última jornada cerrada de la empresa (más reciente: #'.$ultima->id.').';
        }

        return $errores;
    }

    public function anularCierre(int $jornadaId, string $motivo): JornadaEstacionamiento
    {
        $jornada = $this->jornadaRepository->findOrFail($jornadaId);
        $errores = $this->erroresAntesDeAnularCierre($jornada);
        if ($errores !== []) {
            throw new InvalidArgumentException(implode(' ', $errores));
        }

        if (Auth::id() === null) {
            throw new InvalidArgumentException('No hay usuario autenticado.');
        }

        $nota = '[Anulación cierre jornada '.now()->format('Y-m-d H:i')
            .' user #'.Auth::id().'] Motivo: '.($this->limpiarObservacion($motivo) ?? '(sin detalle)');

        return DB::transaction(function () use ($jornada, $nota) {
            $obsApertura = trim((string) $jornada->observacion_apertura);
            $obsApertura = $obsApertura === '' ? $nota : $obsApertura."\n".$nota;

            $this->jornadaRepository->update([
                'estado' => JornadaEstacionamiento::ESTADO_ABIERTA,
                'usuario_cierre_id' => null,
                'cierre_en' => null,
                'observacion_cierre' => null,
                'observacion_apertura' => mb_substr($obsApertura, 0, 2000),
            ], (int) $jornada->id);

            return $this->jornadaRepository->findOrFail((int) $jornada->id);
        });
    }

    public function eliminar(int $jornadaId): void
    {
        $jornada = $this->jornadaRepository->findOrFail($jornadaId);
        $resumen = $this->resumenEliminacion($jornada);

        if (! $resumen['puede_eliminar']) {
            throw new InvalidArgumentException(
                $resumen['motivo_no_eliminar'] ?? 'No se puede eliminar la jornada porque tiene movimientos.'
            );
        }

        try {
            DB::transaction(function () use ($jornada) {
                $this->jornadaRepository->delete((int) $jornada->id);
            });
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new InvalidArgumentException(self::mensajeDesdeExcepcion($e), 0, $e);
        }
    }

    public function exigirJornadaSiConfigurada(int $empresaId): void
    {
        if (! config('estacionamiento.jornada_obligatoria', true)) {
            return;
        }

        $this->exigirJornadaAbierta($empresaId);
    }

    public static function mensajeDesdeExcepcion(Throwable $e): string
    {
        if ($e instanceof InvalidArgumentException) {
            return $e->getMessage();
        }

        $prev = $e->getPrevious();
        if ($prev instanceof QueryException) {
            $sqlMsg = $prev->getMessage();

            if (str_contains($sqlMsg, 'foreign key constraint') || str_contains($sqlMsg, 'FOREIGN KEY')) {
                if (str_contains($sqlMsg, 'empresa_id')) {
                    return 'La empresa seleccionada no existe en el sistema o no puede usarse para jornadas.';
                }
                if (str_contains($sqlMsg, 'usuario_apertura_id')) {
                    return 'No se pudo asociar su usuario a la apertura. Cierre sesión, vuelva a entrar e intente de nuevo.';
                }

                return 'No se pudo grabar la jornada por un dato referenciado inválido (restricción de base de datos).';
            }

            if (str_contains($sqlMsg, 'Duplicate entry') || str_contains($sqlMsg, 'duplicate key')) {
                return 'Ya existe un registro de jornada con esos datos. Consulte el historial o cierre la jornada anterior.';
            }
        }

        $detalle = trim($e->getMessage());
        if ($detalle === '') {
            return 'Error inesperado al procesar la jornada. Revise el log del servidor o contacte soporte.';
        }

        return 'Error al procesar la jornada: '.$detalle;
    }

    private function contarVentasEstacionamientoJornadaAbierta(int $empresaId): int
    {
        $jornada = $this->jornadaAbierta($empresaId);
        if ($jornada === null) {
            return 0;
        }

        return $this->contarVentasEstacionamientoJornada($jornada);
    }

    private function contarVentasEstacionamientoJornada(JornadaEstacionamiento $jornada): int
    {
        return (int) VentaEstacionamientoEmision::query()
            ->where('jornada_estacionamiento_id', (int) $jornada->id)
            ->whereHas('venta', fn ($v) => $v->whereHas(
                'puntoventas',
                fn ($pv) => $pv->where('empresa_id', (int) $jornada->empresa_id),
            ))
            ->count();
    }

    private function existeJornadaPosterior(int $empresaId, JornadaEstacionamiento $jornada): bool
    {
        if ($empresaId <= 0 || $jornada->fecha_jornada === null) {
            return false;
        }

        return JornadaEstacionamiento::query()
            ->where('empresa_id', $empresaId)
            ->where('id', '!=', (int) $jornada->id)
            ->where(function ($q) use ($jornada) {
                $q->whereDate('fecha_jornada', '>', $jornada->fecha_jornada->format('Y-m-d'))
                    ->orWhere(function ($w) use ($jornada) {
                        $w->whereDate('fecha_jornada', $jornada->fecha_jornada->format('Y-m-d'))
                            ->where('id', '>', (int) $jornada->id);
                    });
            })
            ->exists();
    }

    private function fechasSugeridasApertura(?JornadaEstacionamiento $ultimaJornada): array
    {
        $hoy = Carbon::today()->format('Y-m-d');
        $minima = null;

        if ($ultimaJornada?->fecha_jornada !== null) {
            $minima = $ultimaJornada->fecha_jornada->copy()->addDay()->format('Y-m-d');
        }

        $sugerida = $minima !== null && $minima > $hoy ? $minima : $hoy;

        return [
            'minima' => $minima,
            'sugerida' => $sugerida,
        ];
    }

    private function describirJornadaAbierta(JornadaEstacionamiento $jornada): string
    {
        $jornada->loadMissing(['usuarioApertura', 'empresa']);

        $fecha = $jornada->fecha_jornada
            ? $this->formatearFechaHumana($jornada->fecha_jornada->format('Y-m-d'))
            : '(sin fecha)';
        $empresa = $jornada->empresa?->nombre ?? ('empresa #'.$jornada->empresa_id);
        $usuario = $jornada->usuarioApertura?->nombre ?? 'usuario desconocido';
        $cuando = $jornada->apertura_en?->format('d/m/Y H:i') ?? 'fecha no registrada';

        return 'ya hay una jornada abierta del '.$fecha.' ('.$empresa.', id '.$jornada->id
            .', abierta por '.$usuario.' el '.$cuando.').';
    }

    private function formatearFechaHumana(string $fechaYmd): string
    {
        try {
            return Carbon::parse($fechaYmd)->format('d/m/Y');
        } catch (Throwable) {
            return $fechaYmd;
        }
    }

    private function formatearFechaEncabezado(string $fechaYmd): string
    {
        try {
            return Carbon::parse($fechaYmd)->format('d-m-Y');
        } catch (Throwable) {
            return $fechaYmd;
        }
    }

    private function normalizarFecha(string $fecha, string $etiqueta): string
    {
        $fecha = trim($fecha);
        if ($fecha === '') {
            throw new InvalidArgumentException('La '.$etiqueta.' está vacía.');
        }

        try {
            $parsed = Carbon::parse($fecha);
        } catch (Throwable) {
            throw new InvalidArgumentException(
                'La '.$etiqueta.' "'.$fecha.'" no es válida. Use el selector de fecha (formato AAAA-MM-DD).'
            );
        }

        return $parsed->format('Y-m-d');
    }

    private function limpiarObservacion(?string $observacion): ?string
    {
        $txt = trim((string) $observacion);

        return $txt === '' ? null : mb_substr($txt, 0, 2000);
    }
}
