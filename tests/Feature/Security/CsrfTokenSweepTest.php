<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Account;
use App\Models\Debt;
use App\Models\SavingsGoal;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Todo formulario que escribe debe llevar su token CSRF (CLAUDE.md §6).
 *
 * Este barrido existe porque **ningún otro test puede detectarlo**: Laravel
 * desactiva la verificación CSRF cuando corre la suite, así que un formulario
 * sin `@csrf` pasa todos los tests y luego falla con 419 en el navegador. Es
 * a la vez un fallo de seguridad y una funcionalidad rota.
 *
 * Encontró exactamente eso en «Crear hogar»: el parcial `households/_form`
 * no incluye el token y `create.blade.php` no lo añadía (`edit.blade.php` sí).
 *
 * Ojo al comprobarlo a mano: casi toda página autenticada contiene el
 * formulario de cerrar sesión, que sí lleva token. Buscar `name="_token"` en
 * el HTML completo da un falso positivo; hay que mirar dentro de cada
 * formulario, que es lo que hace este test.
 */
class CsrfTokenSweepTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string> páginas con formularios de escritura
     */
    private function paginasConFormularios(User $user): array
    {
        $householdId = $user->households()->first()->id;
        $account = Account::factory()->create(['household_id' => $householdId]);
        $debt = Debt::factory()->create(['household_id' => $householdId, 'status' => 'active']);
        $goal = SavingsGoal::factory()->create(['household_id' => $householdId, 'status' => 'active']);

        return [
            '/hogares/crear',
            "/hogares/{$householdId}/editar",
            "/hogares/{$householdId}",
            '/cuentas/crear',
            "/cuentas/{$account->id}/edit",
            "/cuentas/{$account->id}",
            '/gastos/crear',
            '/ingresos/crear',
            '/transferencias/crear',
            '/presupuestos/crear',
            '/categorias',
            '/deudas/registrar',
            "/deudas/{$debt->id}",
            '/metas/registrar',
            "/metas/{$goal->id}",
            '/recurrentes',
            '/ingresos-esperados',
            '/recordatorios',
            '/perfil',
        ];
    }

    public function test_todo_formulario_de_escritura_lleva_su_token(): void
    {
        $user = User::factory()->create();
        app(HouseholdService::class)->createHousehold($user->id, 'Hogar');

        $sinToken = [];

        foreach ($this->paginasConFormularios($user) as $uri) {
            $respuesta = $this->actingAs($user)->get($uri);

            if ($respuesta->getStatusCode() !== 200) {
                continue;
            }

            foreach ($this->formulariosDeEscritura((string) $respuesta->getContent()) as $formulario) {
                if (! str_contains($formulario['cuerpo'], 'name="_token"')) {
                    $sinToken[] = "{$uri} → form action=\"{$formulario['action']}\"";
                }
            }
        }

        $this->assertSame(
            [],
            $sinToken,
            "Formularios POST sin @csrf (darían 419 en el navegador):\n  ".implode("\n  ", $sinToken)."\n",
        );
    }

    /**
     * Formularios con method POST del HTML, con su action y su cuerpo.
     *
     * @return list<array{action: string, cuerpo: string}>
     */
    private function formulariosDeEscritura(string $html): array
    {
        preg_match_all('#<form\b([^>]*)>(.*?)</form>#s', $html, $coincidencias, PREG_SET_ORDER);

        $formularios = [];

        foreach ($coincidencias as $coincidencia) {
            [, $atributos, $cuerpo] = $coincidencia;

            if (! preg_match('#method=["\']?post#i', $atributos)) {
                continue;
            }

            preg_match('#action=["\']([^"\']*)["\']#', $atributos, $action);

            $formularios[] = ['action' => $action[1] ?? '(sin action)', 'cuerpo' => $cuerpo];
        }

        return $formularios;
    }
}
