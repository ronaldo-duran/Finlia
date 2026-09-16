<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TourStatus;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Guías de pantalla: qué le toca ver a cada usuario y qué ya vio (ADR-0045).
 *
 * El contenido vive en config/tours.php; aquí solo está la decisión. No toca
 * la capa HTTP (ADR-0010): recibe el usuario y el nombre de ruta como datos,
 * así que la futura API móvil (Épica 14) puede reusarlo tal cual.
 */
class TourService
{
    /**
     * El registro completo, en el orden en que está escrito en config.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return config('tours', []);
    }

    /**
     * Una guía por su clave, o null si no existe.
     *
     * Es la validación de la clave que llega por petición: el registro es una
     * lista cerrada, igual que el enum de los acuses (ADR-0024). Una clave
     * inventada es un 404, no una fila basura en la tabla.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * La guía que corresponde a un nombre de ruta ('debts.index' → 'deudas').
     *
     * Cada guía declara nombres EXACTOS (o una lista, si varias rutas pintan la
     * misma pantalla). Los comodines se probaron y salió mal: 'debts.*' casaba
     * también con el detalle de una deuda, donde no existe ninguno de los
     * anclajes del listado, así que allí la guía se reducía a un paso huérfano
     * describiendo otra pantalla — y de paso la daba por vista, con lo que la
     * de verdad no volvía a salir.
     */
    public function keyForRoute(?string $routeName): ?string
    {
        if ($routeName === null) {
            return null;
        }

        foreach ($this->all() as $key => $guide) {
            if (Str::is($guide['route'], $routeName)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Lo que hay que mandarle al navegador para una guía, o null si la clave
     * no existe.
     *
     * Van SIEMPRE todos los pasos, junto con la versión que el usuario ya vio:
     * el filtrado por versión lo hace el navegador, porque depende de si la
     * guía arranca sola (solo los pasos nuevos) o la pidió la persona desde el
     * menú (la guía completa). Mandar dos listas distintas sería el mismo dato
     * dos veces.
     *
     * @return array<string, mixed>|null
     */
    public function payloadFor(User $user, string $key): ?array
    {
        $guide = $this->find($key);

        if ($guide === null) {
            return null;
        }

        return [
            'key' => $key,
            'title' => $guide['title'],
            'version' => (int) $guide['version'],
            'seen' => $this->versionSeen($user, $key),
            'steps' => array_map(
                fn (array $step): array => [
                    'since' => (int) ($step['since'] ?? 1),
                    'anchor' => $step['anchor'] ?? null,
                    'title' => $step['title'],
                    'body' => $step['body'],
                    'list' => array_values($step['list'] ?? []),
                ],
                $this->publishedSteps($guide),
            ),
        ];
    }

    /**
     * ¿Debe esta guía arrancar sola para este usuario?
     *
     * Tres condiciones, y las tres tienen que darse: la guía se declara
     * automática, el usuario no apagó las guías, y le queda algún paso por
     * ver. El tope de «una por sesión» NO se decide aquí — es de la petición,
     * y vive en ShareActiveTour.
     */
    public function shouldAutoStart(User $user, string $key): bool
    {
        $guide = $this->find($key);

        if ($guide === null || ($guide['auto'] ?? true) !== true || ! $user->tours_enabled) {
            return false;
        }

        return $this->pendingStepCount($user, $key) > 0;
    }

    /**
     * Cuántos pasos le quedan por ver a este usuario en esta guía.
     *
     * Cuenta los pasos, no compara versiones: si una guía sube de versión sin
     * añadir pasos (por ejemplo, por descuido al corregir un texto), no hay
     * nada que enseñar y no debe reaparecer.
     */
    public function pendingStepCount(User $user, string $key): int
    {
        $guide = $this->find($key);

        if ($guide === null) {
            return 0;
        }

        $seen = $this->versionSeen($user, $key);

        return count(array_filter(
            $this->publishedSteps($guide),
            fn (array $step): bool => (int) ($step['since'] ?? 1) > $seen,
        ));
    }

    /**
     * Los pasos que ya están publicados: los que nacieron en esta versión o
     * antes.
     *
     * Un paso con `since` mayor que la versión de la guía está escrito pero
     * sin publicar, y no lo ve nadie hasta que se sube la versión. Así se
     * puede dejar redactada la guía de la próxima entrega sin que se escape
     * antes de tiempo — y un `since` puesto de más no se cuela en producción.
     *
     * @param  array<string, mixed>  $guide
     * @return list<array<string, mixed>>
     */
    private function publishedSteps(array $guide): array
    {
        $version = (int) $guide['version'];

        return array_values(array_filter(
            $guide['steps'],
            fn (array $step): bool => (int) ($step['since'] ?? 1) <= $version,
        ));
    }

    /** Versión de la guía que el usuario ya vio (0 = nunca la ha visto). */
    public function versionSeen(User $user, string $key): int
    {
        return (int) ($this->seenVersions($user)[$key] ?? 0);
    }

    /**
     * Da la guía por vista en su versión ACTUAL.
     *
     * La versión sale del registro, nunca de la petición: si la mandara el
     * navegador, cualquiera podría declararse al día con una versión inventada
     * y no volver a recibir novedades nunca.
     */
    public function markSeen(User $user, string $key, TourStatus $status): void
    {
        $guide = $this->find($key);

        if ($guide === null) {
            return;
        }

        $user->tours()->updateOrCreate(
            ['key' => $key],
            [
                'version' => (int) $guide['version'],
                'status' => $status,
                'seen_at' => now(),
            ],
        );

        $user->unsetRelation('tours');
    }

    /**
     * Borra el progreso: las guías vuelven a aparecer solas desde cero.
     *
     * No toca `tours_enabled` — encender el interruptor y volver a verlas
     * desde el principio son dos deseos distintos, y el perfil los ofrece por
     * separado.
     */
    public function reset(User $user): void
    {
        $user->tours()->delete();
        $user->unsetRelation('tours');
    }

    /**
     * El catálogo para /perfil: cada guía con su estado y a dónde ir a verla.
     *
     * @return list<array<string, mixed>>
     */
    public function catalog(User $user): array
    {
        $seen = $this->seenVersions($user);

        return array_values(array_map(function (array $guide, string $key) use ($user, $seen): array {
            $version = (int) ($seen[$key] ?? 0);

            return [
                'key' => $key,
                'title' => $guide['title'],
                'icon' => $guide['icon'],
                'summary' => $guide['summary'],
                // Nombre de ruta, no URL: montarla es de la vista, y así el
                // Service sigue sin saber nada de HTTP (ADR-0010). Es null en
                // las guías de pantallas que necesitan un id (el detalle de una
                // deuda): esas no se pueden enlazar, y por eso traen la pista.
                'link' => $guide['link'],
                'link_hint' => $guide['link_hint'] ?? null,
                'seen' => $version > 0,
                'pending' => $this->pendingStepCount($user, $key),
            ];
        }, $this->all(), array_keys($this->all())));
    }

    /**
     * Versiones vistas por clave, en una sola consulta.
     *
     * `loadMissing` y no `tours()->get()`: el catálogo del perfil pregunta por
     * las diez guías seguidas, y sin esto serían diez consultas idénticas.
     *
     * @return array<string, int>
     */
    private function seenVersions(User $user): array
    {
        $user->loadMissing('tours');

        return $user->tours
            ->pluck('version', 'key')
            ->map(fn ($version): int => (int) $version)
            ->all();
    }
}
