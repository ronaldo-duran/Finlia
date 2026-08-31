<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AcknowledgementController;
use App\Http\Controllers\ActiveHouseholdController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CreditCardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\DebtPaymentController;
use App\Http\Controllers\DebtRefinancingController;
use App\Http\Controllers\ExpectedIncomeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\HouseholdController;
use App\Http\Controllers\HouseholdInvitationController;
use App\Http\Controllers\HouseholdMemberController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MovementsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecurringExpenseController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SavingsGoalController;
use App\Http\Controllers\TermsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas web — Finlia
|--------------------------------------------------------------------------
| Auth nativa por sesiones. UI/URLs en español. Las rutas privadas se
| agrupan bajo middleware 'auth'.
*/

// Raíz: redirige según sesión.
Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
})->name('home');

// ---- Rutas públicas (solo invitados) ----
Route::middleware('guest')->group(function () {
    // Registro
    Route::get('registro', [RegisteredUserController::class, 'create'])
        ->name('register');
    Route::post('registro', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:5,1');

    // Inicio de sesión
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:5,1');

    // Recuperación de contraseña
    Route::get('recuperar-contrasena', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('recuperar-contrasena', [PasswordResetLinkController::class, 'store'])
        ->name('password.email')
        ->middleware('throttle:5,1');

    Route::get('restablecer-contrasena/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('restablecer-contrasena', [NewPasswordController::class, 'store'])
        ->name('password.update')
        ->middleware('throttle:5,1');
});

// ---- Baja del digest desde el correo (Épica 9, ADR-0028) ----
// URL firmada por usuario+hogar: funciona con o sin sesión (el click llega
// desde el buzón, no desde la app). La firma es la autorización: nadie puede
// forjar la baja de otro. GET = confirmación visible; POST = one-click
// RFC 8058 (Gmail/Yahoo). No lleva 'guest' a propósito: un usuario con
// sesión abierta también debe poder darse de baja desde su correo.
Route::get('recordatorios/correo/baja', [ReminderController::class, 'unsubscribe'])
    ->name('reminders.unsubscribe')
    ->middleware('signed');
Route::post('recordatorios/correo/baja', [ReminderController::class, 'unsubscribe'])
    ->middleware('signed');

// ---- Enlace de verificación del correo (Plan 01) ----
// Público + firmado: la firma es la autorización (patrón de la baja del
// digest). El click llega desde el buzón, con o sin sesión abierta — por
// eso no está tras 'auth'. El hash (sha1 del correo) es la otra mitad de
// la prueba; lo comprueba el controlador.
Route::get('verificar-correo/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->name('verification.verify')
    ->middleware(['signed', 'throttle:6,1']);

// ---- Confirmación del cambio de correo (Plan 02) ----
// Público con token aleatorio (hash sha256 en la base, patrón de las
// invitaciones): el click llega desde la bandeja NUEVA, sin sesión. El
// token ES la autorización — poseerlo equivale a controlar esa bandeja
// (mismo criterio que la verificación del registro). GET muta a propósito.
Route::get('confirmar-correo/{token}', [ProfileController::class, 'confirmEmail'])
    ->name('profile.email.confirm')
    ->middleware('throttle:6,1');

// ---- Términos y condiciones (Plan 03) ----
// Lectura pública de la vigente y del histórico por versión: es la
// referencia externa de qué aceptó cada usuario. El patrón de {version}
// ("YYYY-MM-vN") hace imposible que colisione con las URIs fijas de
// abajo, vengan en el orden que vengan.
Route::get('terminos', [TermsController::class, 'show'])->name('terms.show');
Route::get('terminos/{termsVersion}', [TermsController::class, 'version'])
    ->name('terms.version')
    ->where('termsVersion', '[0-9]{4}-[0-9]{2}-v[0-9]+');

// ---- Rutas privadas ----
// Nivel 1 (solo sesión): cerrar sesión y el flujo de verificación son lo
// ÚNICO alcanzable sin correo confirmado (Plan 01: bloqueo total hasta
// confirmar). Nivel 2½: aceptación de términos. Nivel 3: el resto de la
// app, ya con los términos vigentes aceptados (Plan 03).
Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    // Aviso "revisa tu correo" + reenvío con throttle por usuario.
    Route::get('verificar-correo', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');
    Route::post('verificar-correo/reenviar', [EmailVerificationController::class, 'resend'])
        ->name('verification.send')
        ->middleware('throttle:verification');
});

// Nivel 2½ (sesión + correo confirmado, PERO sin terms.current): aquí vive
// justamente el flujo de aceptación — con el middleware puesto sería un
// bucle de redirección. Aceptar y rechazar son del propio autenticado;
// no hay IDs en las URLs.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('terminos/aceptar', [TermsController::class, 'acceptForm'])
        ->name('terms.accept');
    Route::post('terminos/aceptar', [TermsController::class, 'accept'])
        ->name('terms.accept.store')
        ->middleware('throttle:10,1');
    // Landing de salida: no destruye nada por sí sola (Plan 03).
    Route::post('terminos/rechazar', [TermsController::class, 'reject'])
        ->name('terms.reject');
});

// Nivel 3 (sesión + verificado + términos vigentes aceptados): el resto de
// la app. Publicar una versión nueva devuelve a todos aquí — a la pantalla
// de aceptación — hasta que re-acepten.
Route::middleware(['auth', 'verified', 'terms.current'])->group(function () {
    Route::get('dashboard', DashboardController::class)
        ->name('dashboard');

    // ---- Perfil: nombre, contraseña y correo (Plan 02) ----
    // Preferencia del USUARIO, no del hogar: vive fuera del multi-tenant.
    // Solo alcanza al propio autenticado (UserPolicy), nunca por ID de URL.
    Route::get('perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('perfil/datos', [ProfileController::class, 'update'])->name('profile.update');
    // Re-autenticación (current_password) + revocación de otras sesiones.
    Route::put('perfil/contrasena', [ProfileController::class, 'updatePassword'])
        ->name('profile.password.update')
        ->middleware('throttle:6,1');
    // Dispara un correo a la bandeja nueva: mismo throttle del reenvío de
    // verificación (3/min por usuario).
    Route::put('perfil/correo', [ProfileController::class, 'updateEmail'])
        ->name('profile.email.update')
        ->middleware('throttle:verification');

    // ---- Hogares (Épica 2) ----
    Route::get('hogares', [HouseholdController::class, 'index'])->name('households.index');
    Route::get('hogares/crear', [HouseholdController::class, 'create'])->name('households.create');
    Route::post('hogares', [HouseholdController::class, 'store'])->name('households.store');
    Route::get('hogares/{household}', [HouseholdController::class, 'show'])->name('households.show');
    Route::get('hogares/{household}/editar', [HouseholdController::class, 'edit'])->name('households.edit');
    Route::put('hogares/{household}', [HouseholdController::class, 'update'])->name('households.update');
    Route::delete('hogares/{household}', [HouseholdController::class, 'destroy'])->name('households.destroy');
    Route::post('hogares/{household}/activar', ActiveHouseholdController::class)->name('households.activate');

    // Miembros (acciones dentro de un hogar)
    Route::delete('hogares/{household}/miembros/{user}', [HouseholdMemberController::class, 'destroy'])
        ->name('households.members.destroy');

    // Invitaciones: enviar / revocar
    Route::post('hogares/{household}/invitaciones', [HouseholdInvitationController::class, 'store'])
        ->name('households.invitations.store');
    Route::delete('hogares/{household}/invitaciones/{invitation}', [HouseholdInvitationController::class, 'destroy'])
        ->name('households.invitations.destroy');

    // Aceptar invitación por enlace (token hasheado en BD)
    Route::get('invitaciones/{token}', [InvitationController::class, 'show'])
        ->name('invitations.show')
        ->middleware('throttle:10,1');
    Route::post('invitaciones/{token}', [InvitationController::class, 'accept'])
        ->name('invitations.accept')
        ->middleware('throttle:10,1');

    // ---- Épica 3: cuentas, categorías, ingresos, gastos y movimientos ----
    // URI en español ('cuentas'), pero nombres de ruta 'accounts.*' para que
    // vistas/controladores/tests usen route('accounts.index') etc.
    Route::resource('cuentas', AccountController::class)
        ->parameters(['cuentas' => 'account'])
        ->names([
            'index' => 'accounts.index',
            'create' => 'accounts.create',
            'store' => 'accounts.store',
            'show' => 'accounts.show',
            'edit' => 'accounts.edit',
            'update' => 'accounts.update',
            'destroy' => 'accounts.destroy',
        ]);

    Route::get('categorias', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('categorias', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('categorias/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('categorias/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    // Altas y edición de gastos (el listado unificado vive en /movimientos).
    Route::get('gastos/crear', [ExpenseController::class, 'create'])->name('expenses.create');
    Route::post('gastos', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::get('gastos/{expense}/editar', [ExpenseController::class, 'edit'])->name('expenses.edit');
    Route::put('gastos/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
    Route::delete('gastos/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

    // Altas y edición de ingresos.
    Route::get('ingresos/crear', [IncomeController::class, 'create'])->name('incomes.create');
    Route::post('ingresos', [IncomeController::class, 'store'])->name('incomes.store');
    Route::get('ingresos/{income}/editar', [IncomeController::class, 'edit'])->name('incomes.edit');
    Route::put('ingresos/{income}', [IncomeController::class, 'update'])->name('incomes.update');
    Route::delete('ingresos/{income}', [IncomeController::class, 'destroy'])->name('incomes.destroy');

    // Vista unificada con filtros (ingresos + gastos).
    Route::get('movimientos', [MovementsController::class, 'index'])->name('movements.index');

    // ---- Épica 4: presupuestos y dinero disponible ----
    // URI en español ('presupuestos'), nombres de ruta 'budgets.*'.
    Route::resource('presupuestos', BudgetController::class)
        ->parameters(['presupuestos' => 'budget'])
        ->except(['show'])
        ->names([
            'index' => 'budgets.index',
            'create' => 'budgets.create',
            'store' => 'budgets.store',
            'edit' => 'budgets.edit',
            'update' => 'budgets.update',
            'destroy' => 'budgets.destroy',
        ]);

    // Ingresos mensuales esperados (entrada del cálculo de dinero disponible).
    Route::get('ingresos-esperados', [ExpectedIncomeController::class, 'index'])
        ->name('expected-incomes.index');
    Route::post('ingresos-esperados', [ExpectedIncomeController::class, 'store'])
        ->name('expected-incomes.store');
    Route::put('ingresos-esperados/{expectedIncome}', [ExpectedIncomeController::class, 'update'])
        ->name('expected-incomes.update');
    Route::delete('ingresos-esperados/{expectedIncome}', [ExpectedIncomeController::class, 'destroy'])
        ->name('expected-incomes.destroy');

    // ---- Épica 5: gastos recurrentes y obligaciones futuras ----
    Route::get('recurrentes', [RecurringExpenseController::class, 'index'])
        ->name('recurring-expenses.index');
    Route::post('recurrentes', [RecurringExpenseController::class, 'store'])
        ->name('recurring-expenses.store');
    Route::put('recurrentes/{recurringExpense}', [RecurringExpenseController::class, 'update'])
        ->name('recurring-expenses.update');
    Route::delete('recurrentes/{recurringExpense}', [RecurringExpenseController::class, 'destroy'])
        ->name('recurring-expenses.destroy');
    Route::post('recurrentes/{recurringExpense}/pagar', [RecurringExpenseController::class, 'markPaid'])
        ->name('recurring-expenses.mark-paid');

    // Avisos dados por leídos (ADR-0024). Sin {key} libre: se valida contra
    // el enum en el controlador.
    Route::post('avisos/{key}', [AcknowledgementController::class, 'store'])
        ->name('acknowledgements.store');

    // ---- Épica 6: deudas y tarjetas de crédito ----
    Route::get('deudas', [DebtController::class, 'index'])
        ->name('debts.index');
    Route::get('deudas/registrar', [DebtController::class, 'create'])
        ->name('debts.create');
    Route::post('deudas', [DebtController::class, 'store'])
        ->name('debts.store');
    Route::get('deudas/{debt}', [DebtController::class, 'show'])
        ->name('debts.show');
    Route::put('deudas/{debt}', [DebtController::class, 'update'])
        ->name('debts.update');
    Route::delete('deudas/{debt}', [DebtController::class, 'destroy'])
        ->name('debts.destroy');

    Route::post('deudas/{debt}/pagos', [DebtPaymentController::class, 'store'])
        ->name('debts.payments.store');
    Route::delete('deudas/{debt}/pagos/{payment}', [DebtPaymentController::class, 'destroy'])
        ->name('debts.payments.destroy');

    Route::post('deudas/{debt}/refinanciacion', [DebtRefinancingController::class, 'store'])
        ->name('debts.refinancings.store');

    // Datos de tarjeta sobre una cuenta type=credit_card (ADR-0002).
    Route::put('cuentas/{account}/tarjeta', [CreditCardController::class, 'update'])
        ->name('accounts.credit-card.update');
    Route::delete('cuentas/{account}/tarjeta', [CreditCardController::class, 'destroy'])
        ->name('accounts.credit-card.destroy');

    // ---- Épica 7: metas de ahorro ----
    // URI en español ('metas'), nombres de ruta 'savings-goals.*'.
    Route::get('metas', [SavingsGoalController::class, 'index'])
        ->name('savings-goals.index');
    Route::get('metas/registrar', [SavingsGoalController::class, 'create'])
        ->name('savings-goals.create');
    Route::post('metas', [SavingsGoalController::class, 'store'])
        ->name('savings-goals.store');
    Route::get('metas/{savingsGoal}', [SavingsGoalController::class, 'show'])
        ->name('savings-goals.show');
    Route::get('metas/{savingsGoal}/editar', [SavingsGoalController::class, 'edit'])
        ->name('savings-goals.edit');
    Route::put('metas/{savingsGoal}', [SavingsGoalController::class, 'update'])
        ->name('savings-goals.update');
    Route::delete('metas/{savingsGoal}', [SavingsGoalController::class, 'destroy'])
        ->name('savings-goals.destroy');

    // Aportes y retiros (no mueven cuentas: progreso de la meta, ADR-0025).
    Route::post('metas/{savingsGoal}/aportes', [SavingsGoalController::class, 'contribute'])
        ->name('savings-goals.contributions.store');
    Route::delete('metas/{savingsGoal}/aportes/{contribution}', [SavingsGoalController::class, 'destroyContribution'])
        ->name('savings-goals.contributions.destroy');

    // Estados como acciones dedicadas (no un select en el formulario):
    // pausar, completar y archivar son decisiones puntuales.
    Route::post('metas/{savingsGoal}/pausar', [SavingsGoalController::class, 'pause'])
        ->name('savings-goals.pause');
    Route::post('metas/{savingsGoal}/reactivar', [SavingsGoalController::class, 'resume'])
        ->name('savings-goals.resume');
    Route::post('metas/{savingsGoal}/completar', [SavingsGoalController::class, 'complete'])
        ->name('savings-goals.complete');
    Route::post('metas/{savingsGoal}/archivar', [SavingsGoalController::class, 'archive'])
        ->name('savings-goals.archive');

    // ---- Épica 8: reportes financieros ----
    // El dashboard completo: comparación de períodos, gráficos, insights y
    // exportación. El hogar sale del activo en sesión, nunca de la URL.
    Route::get('reportes', [ReportController::class, 'index'])
        ->name('reports.index');
    Route::get('reportes/exportar', [ReportController::class, 'export'])
        ->name('reports.export')
        ->middleware('throttle:10,1');

    // ---- Épica 9: recordatorios y notificaciones ----
    // Lista unificada (recurrentes + deudas + metas + sueltos, ADR-0027) y
    // CRUD de los sueltos. El hogar sale del activo en sesión.
    Route::get('recordatorios', [ReminderController::class, 'index'])
        ->name('reminders.index');
    Route::post('recordatorios', [ReminderController::class, 'store'])
        ->name('reminders.store');
    // Interruptor del hogar (solo administrador, HouseholdPolicy::update).
    // Antes de {reminder}: la URI fija debe ganarle al parámetro.
    Route::put('recordatorios/configuracion', [ReminderController::class, 'settings'])
        ->name('reminders.settings');
    // Preferencia personal de digest por correo (ADR-0028), misma regla de orden.
    Route::put('recordatorios/correo', [ReminderController::class, 'email'])
        ->name('reminders.email');
    Route::put('recordatorios/{reminder}', [ReminderController::class, 'update'])
        ->name('reminders.update');
    Route::delete('recordatorios/{reminder}', [ReminderController::class, 'destroy'])
        ->name('reminders.destroy');
    Route::post('recordatorios/{reminder}/completar', [ReminderController::class, 'complete'])
        ->name('reminders.complete');
});
