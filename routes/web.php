<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ActiveHouseholdController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
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
use App\Http\Controllers\RecurringExpenseController;
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

// ---- Rutas privadas (requieren sesión) ----
Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    Route::get('dashboard', DashboardController::class)
        ->name('dashboard');

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
});
