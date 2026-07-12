<?php

namespace App\Services\Caja\Bingo;

use App\Models\Caja\Bingo\JornadaBingo;
use App\Models\Caja\Bingo\TurnoOperativoBingo;
use App\Repositories\Caja\Bingo\JornadaBingoRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class JornadaBingoService
{
    public function __construct(
        private readonly JornadaBingoRepositoryInterface $jornadaRepository,
    ) {}

    public function jornadaAbierta(int $empresaId): ?JornadaBingo
    {
        if ($empresaId <= 0) {
            return null;
        }

        return $this->jornadaRepository->jornadaAbiertaPorEmpresa($empresaId);
    }

    public function exigirJornadaAbierta(int $empresaId): JornadaBingo
    {
        $jornada = $this->jornadaAbierta($empresaId);
        if ($jornada === null) {
            throw new InvalidArgumentException(
                'No hay jornada abierta para esta empresa. Abra la jornada en Caja → Bingo → Jornada.'
            );
        }

        return $jornada;
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
            'fecha_jornada_minima_abrir' => $abierta !== null ? null : $fechasApertura['minima'],
            'fecha_jornada_maxima_abrir' => $hoy,
            'fecha_jornada_sugerida_abrir' => $abierta !== null && $abierta->fecha_jornada !== null
                ? $abierta->fecha_jornada->format('Y-m-d')
                : $fechasApertura['sugerida'],
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
            'turnos_habilitados' => $abierta !== null
                ? $this->detalleTurnosHabilitadosJornada($empresaId, $abierta)
                : [],
            'nota_politica_turnos' => BingoTurnoOperativoService::requiereHabilitacionTurno()
                ? 'Cada turno habilitado en la jornada debe cerrarse antes de cerrar la jornada.'
                : null,
        ];
    }

    public function validarApertura(int $empresaId, string $fechaJornada): string
    {
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Debe seleccionar una empresa antes de abrir la jornada.');
        }

        if (trim($fechaJornada) === '') {
            throw new InvalidArgumentException('Debe indicar la fecha de jornada (día de turno).');
        }

        $fecha = $this->normalizarFecha($fechaJornada, 'fecha de jornada');
        $fechaFmt = $this->formatearFechaHumana($fecha);
        $hoy = Carbon::today()->format('Y-m-d');

        if ($fecha > $hoy) {
            throw new InvalidArgumentException(
                'No puede abrir la jornada del '.$fechaFmt.': la fecha no puede ser posterior a hoy.'
            );
        }

        $abierta = $this->jornadaAbierta($empresaId);
        if ($abierta !== null) {
            throw new InvalidArgumentException(
                'No puede abrir la jornada del '.$fechaFmt.': '.$this->describirJornadaAbierta($abierta)
            );
        }

        $ultima = $this->jornadaRepository->ultimaJornadaPorEmpresa($empresaId);
        if ($ultima !== null) {
            $ultimaFecha = $ultima->fecha_jornada->format('Y-m-d');
            if ($fecha <= $ultimaFecha) {
                throw new InvalidArgumentException(
                    'No puede abrir la jornada del '.$fechaFmt.': la última jornada registrada es del '
                    .$this->formatearFechaHumana($ultimaFecha).'. Solo puede abrir fechas posteriores.'
                );
            }
        }

        return $fecha;
    }

    public function abrir(int $empresaId, string $fechaJornada, ?string $observacion = null): JornadaBingo
    {
        $fecha = $this->validarApertura($empresaId, $fechaJornada);

        if (Auth::id() === null) {
            throw new InvalidArgumentException('No hay usuario autenticado.');
        }

        return DB::transaction(function () use ($empresaId, $fecha, $observacion) {
            $existente = JornadaBingo::query()
                ->with(['usuarioApertura', 'empresa'])
                ->where('empresa_id', $empresaId)
                ->where('estado', JornadaBingo::ESTADO_ABIERTA)
                ->lockForUpdate()
                ->first();

            if ($existente !== null) {
                throw new InvalidArgumentException(
                    'Ya hay una jornada abierta: '.$this->describirJornadaAbierta($existente)
                );
            }

            return $this->jornadaRepository->create([
                'empresa_id' => $empresaId,
                'fecha_jornada' => $fecha,
                'estado' => JornadaBingo::ESTADO_ABIERTA,
                'usuario_apertura_id' => Auth::id(),
                'apertura_en' => now(),
                'observacion_apertura' => $this->limpiarObservacion($observacion),
            ]);
        });
    }

    public function cerrar(int $empresaId, ?string $observacion = null): JornadaBingo
    {
        $jornada = $this->exigirJornadaAbierta($empresaId);
        $errores = $this->erroresAntesDeCerrar($empresaId);
        if ($errores !== []) {
            throw new InvalidArgumentException(implode(' ', $errores));
        }

        $this->jornadaRepository->update([
            'estado' => JornadaBingo::ESTADO_CERRADA,
            'usuario_cierre_id' => Auth::id(),
            'cierre_en' => now(),
            'observacion_cierre' => $this->limpiarObservacion($observacion),
        ], (int) $jornada->id);

        return $this->jornadaRepository->findOrFail((int) $jornada->id);
    }

    /**
     * @return list<string>
     */
    public function erroresAntesDeCerrar(int $empresaId): array
    {
        if (! BingoTurnoOperativoService::requiereHabilitacionTurno()) {
            return [];
        }

        $habilitados = $this->detalleTurnosHabilitadosJornada($empresaId);
        if ($habilitados === []) {
            return [];
        }

        $detalle = collect($habilitados)
            ->map(fn (array $t) => $t['identificador_pc'].' ('.$t['turno_nombre'].')')
            ->implode(', ');

        return [
            'Hay '.count($habilitados).' turno(s) habilitado(s) sin cerrar: '.$detalle.'. '
            .'Cierre cada turno antes de cerrar la jornada.',
        ];
    }

    /**
     * @return list<array{turno_operativo_id:int, identificador_pc:string, turno_nombre:string, habilitacion_en:string}>
     */
    public function detalleTurnosHabilitadosJornada(int $empresaId, ?JornadaBingo $jornada = null): array
    {
        $jornada ??= $this->jornadaAbierta($empresaId);
        if ($jornada === null) {
            return [];
        }

        return TurnoOperativoBingo::query()
            ->with('turno')
            ->where('empresa_id', $empresaId)
            ->where('jornada_bingo_id', (int) $jornada->id)
            ->where('estado', TurnoOperativoBingo::ESTADO_HABILITADO)
            ->orderBy('identificador_pc')
            ->get()
            ->map(fn (TurnoOperativoBingo $t) => [
                'turno_operativo_id' => (int) $t->id,
                'identificador_pc' => (string) $t->identificador_pc,
                'turno_nombre' => (string) ($t->turno?->nombre ?? ''),
                'habilitacion_en' => $t->habilitacion_en?->format('d/m/Y H:i') ?? '',
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{puede_eliminar:bool, motivo_no_eliminar:?string, turnos_operativos:int}
     */
    public function resumenEliminacion(JornadaBingo $jornada): array
    {
        $turnos = TurnoOperativoBingo::query()
            ->where('jornada_bingo_id', (int) $jornada->id)
            ->count();

        if ($this->existeJornadaPosterior((int) $jornada->empresa_id, $jornada)) {
            return [
                'puede_eliminar' => false,
                'motivo_no_eliminar' => 'No se puede eliminar: ya existe una jornada posterior para esta empresa.',
                'turnos_operativos' => $turnos,
            ];
        }

        if ($turnos > 0) {
            return [
                'puede_eliminar' => false,
                'motivo_no_eliminar' => 'No se puede eliminar: la jornada tiene '.$turnos.' turno(s) operativo(s).',
                'turnos_operativos' => $turnos,
            ];
        }

        return [
            'puede_eliminar' => true,
            'motivo_no_eliminar' => null,
            'turnos_operativos' => 0,
        ];
    }

    /**
     * @return array{puede_anular:bool, motivo_no_anular:?string, jornada_id:int, texto_confirmacion:string}
     */
    public function resumenAnulacionCierre(JornadaBingo $jornada): array
    {
        $errores = $this->erroresAntesDeAnularCierre($jornada);

        return [
            'puede_anular' => $errores === [],
            'motivo_no_anular' => $errores !== [] ? implode(' ', $errores) : null,
            'jornada_id' => (int) $jornada->id,
            'texto_confirmacion' => 'ANULAR-JORNADA-'.(int) $jornada->id,
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
        if ($ultima === null || $ultima->estado !== JornadaBingo::ESTADO_CERRADA) {
            return null;
        }

        $resumen = $this->resumenAnulacionCierre($ultima);

        return $resumen['puede_anular'] ? $resumen : null;
    }

    /**
     * @return list<string>
     */
    public function erroresAntesDeAnularCierre(JornadaBingo $jornada): array
    {
        if ($jornada->estado !== JornadaBingo::ESTADO_CERRADA || $jornada->cierre_en === null) {
            return ['La jornada no está cerrada.'];
        }

        if ($this->jornadaAbierta((int) $jornada->empresa_id) !== null) {
            return ['Hay una jornada abierta para esta empresa.'];
        }

        if ($this->existeJornadaPosterior((int) $jornada->empresa_id, $jornada)) {
            return ['Ya existe una jornada posterior para esta empresa.'];
        }

        $ultima = $this->jornadaRepository->ultimaJornadaPorEmpresa((int) $jornada->empresa_id);
        if ($ultima !== null && (int) $ultima->id !== (int) $jornada->id) {
            return ['Solo puede anular el cierre de la última jornada cerrada.'];
        }

        return [];
    }

    public function anularCierre(int $jornadaId, string $motivo): JornadaBingo
    {
        $jornada = $this->jornadaRepository->findOrFail($jornadaId);
        $errores = $this->erroresAntesDeAnularCierre($jornada);
        if ($errores !== []) {
            throw new InvalidArgumentException(implode(' ', $errores));
        }

        if (Auth::id() === null) {
            throw new InvalidArgumentException('No hay usuario autenticado.');
        }

        $nota = '[Anulación cierre jornada '.now()->format('Y-m-d H:i').'] '
            .($this->limpiarObservacion($motivo) ?? '(sin detalle)');

        return DB::transaction(function () use ($jornada, $nota) {
            $obsApertura = trim((string) $jornada->observacion_apertura);
            $obsApertura = $obsApertura === '' ? $nota : $obsApertura."\n".$nota;

            $this->jornadaRepository->update([
                'estado' => JornadaBingo::ESTADO_ABIERTA,
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
                $resumen['motivo_no_eliminar'] ?? 'No se puede eliminar la jornada.'
            );
        }

        DB::transaction(fn () => $this->jornadaRepository->delete((int) $jornada->id));
    }

    public static function mensajeDesdeExcepcion(Throwable $e): string
    {
        if ($e instanceof InvalidArgumentException) {
            return $e->getMessage();
        }

        return 'Error al procesar la jornada: '.trim($e->getMessage());
    }

    private function existeJornadaPosterior(int $empresaId, JornadaBingo $jornada): bool
    {
        if ($empresaId <= 0 || $jornada->fecha_jornada === null) {
            return false;
        }

        return JornadaBingo::query()
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

    /**
     * @return array{minima:?string, sugerida:string}
     */
    private function fechasSugeridasApertura(?JornadaBingo $ultimaJornada): array
    {
        $hoy = Carbon::today()->format('Y-m-d');
        $minima = null;

        if ($ultimaJornada?->fecha_jornada !== null) {
            $minima = $ultimaJornada->fecha_jornada->copy()->addDay()->format('Y-m-d');
        }

        return ['minima' => $minima, 'sugerida' => $hoy];
    }

    private function describirJornadaAbierta(JornadaBingo $jornada): string
    {
        $jornada->loadMissing(['usuarioApertura', 'empresa']);
        $fecha = $jornada->fecha_jornada
            ? $this->formatearFechaHumana($jornada->fecha_jornada->format('Y-m-d'))
            : '(sin fecha)';

        return 'ya hay una jornada abierta del '.$fecha.' (id '.$jornada->id.').';
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
            return Carbon::parse($fecha)->format('Y-m-d');
        } catch (Throwable) {
            throw new InvalidArgumentException('La '.$etiqueta.' no es válida.');
        }
    }

    private function limpiarObservacion(?string $observacion): ?string
    {
        $txt = trim((string) $observacion);

        return $txt === '' ? null : mb_substr($txt, 0, 2000);
    }
}
