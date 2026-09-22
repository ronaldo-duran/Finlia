<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountSuspensionController;
use App\Http\Controllers\AcknowledgementController;
use App\Http\Controllers\ActiveHouseholdController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CompulsiveSurveyController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CreditCardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataPolicyController;
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
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\MovementsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReceivableController;
use App\Http\Controllers\ReceivablePaymentController;
use App\Http\Controllers\RecurringExpenseController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SavingsGoalController;
use App\Http\Controllers\TermsController;
use App\Http\Controllers\TourController;
use App\Http\Controllers\TransferController;
use Illuminate\Support\Facades\Route;

Route::group(array_filter(['domain' => config('finlia.domains.marketing')]), function () {
    Route::get('/', [MarketingController::class, 'home'])->name('home');
    Route::get('sitemap.xml', [MarketingController::class, 'sitemap'])->name('sitemap');
    Route::get('llms.txt', [MarketingController::class, 'llms'])->name('llms');

    Route::get('og', [MarketingController::class, 'ogPreview'])->name('og-preview');
    Route::get('contacto', [ContactController::class, 'create'])->name('contact.create');
    Route::post('contacto', [ContactController::class, 'store'])
        ->middleware('throttle:3,60')
        ->name('contact.store');

    Route::get('terminos', [TermsController::class, 'show'])->name('terms.show');
    Route::get('terminos/historial', [TermsController::class, 'history'])->name('terms.history');
    Route::get('terminos/{termsVersion}', [TermsController::class, 'version'])
        ->name('terms.version')
        ->where('termsVersion', '[0-9]{4}-[0-9]{2}-v[0-9]+');
    Route::get('datos', [DataPolicyController::class, 'show'])->name('data.policy');
});

$enLaApp = array_filter(['domain' => config('finlia.domains.app')]);
Route::get('robots.txt', [MarketingController::class, 'robots'])->name('robots');

if (($dominioApp = config('finlia.domains.app')) !== null) {
    Route::domain($dominioApp)->get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));
}
Route::group($enLaApp + ['middleware' => 'guest'], function () {
    Route::get('registro', [RegisteredUserController::class, 'create'])
        ->name('register');
    Route::post('registro', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:5,1');
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:5,1');
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

Route::get('recordatorios/correo/baja', [ReminderController::class, 'unsubscribe'])
    ->name('reminders.unsubscribe')
    ->middleware('signed')
    ->domain(config('finlia.domains.app'));
Route::post('recordatorios/correo/baja', [ReminderController::class, 'unsubscribe'])
    ->middleware('signed')
    ->domain(config('finlia.domains.app'));

Route::get('verificar-correo/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->name('verification.verify')
    ->middleware(['signed', 'throttle:6,1'])
    ->domain(config('finlia.domains.app'));
Route::get('confirmar-correo/{token}', [ProfileController::class, 'confirmEmail'])
    ->name('profile.email.confirm')
    ->middleware('throttle:6,1')
    ->domain(config('finlia.domains.app'));
Route::get('invitaciones/{token}', [InvitationController::class, 'show'])
    ->name('invitations.show')
    ->middleware('throttle:10,1')
    ->domain(config('finlia.domains.app'));
Route::get('manifest.webmanifest', function () {
    return response()->file(public_path('manifest.webmanifest'), [
        'Content-Type' => 'application/manifest+json',
    ]);
})->name('pwa.manifest')
    ->domain(config('finlia.domains.app'));

Route::group($enLaApp + ['middleware' => 'auth'], function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
    Route::get('verificar-correo', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');
    Route::get('verificar-correo/estado', [EmailVerificationController::class, 'status'])
        ->name('verification.status')
        ->middleware('throttle:60,1');
    Route::post('verificar-correo/reenviar', [EmailVerificationController::class, 'resend'])
        ->name('verification.send')
        ->middleware('throttle:verification');
    Route::get('cuenta/suspendida', [AccountSuspensionController::class, 'show'])
        ->name('account.suspended');
    Route::post('cuenta/reactivar', [AccountSuspensionController::class, 'reactivate'])
        ->name('account.reactivate')
        ->middleware('throttle:5,1');
});
Route::group($enLaApp + ['middleware' => ['auth', 'verified']], function () {
    Route::get('terminos/aceptar', [TermsController::class, 'acceptForm'])
        ->name('terms.accept');
    Route::post('terminos/aceptar', [TermsController::class, 'accept'])
        ->name('terms.accept.store')
        ->middleware('throttle:10,1');
    Route::post('terminos/rechazar', [TermsController::class, 'reject'])
        ->name('terms.reject');
});

Route::group($enLaApp + ['middleware' => ['auth', 'verified', 'terms.current', 'account.active', 'tour']], function () {
    Route::get('dashboard', DashboardController::class)
        ->name('dashboard');
    Route::get('perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('perfil/plan', [ProfileController::class, 'plan'])->name('profile.plan');
    Route::put('perfil/datos', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('perfil/contrasena', [ProfileController::class, 'updatePassword'])
        ->name('profile.password.update')
        ->middleware('throttle:6,1');
    Route::put('perfil/correo', [ProfileController::class, 'updateEmail'])
        ->name('profile.email.update')
        ->middleware('throttle:verification');

    Route::delete('perfil/cuenta', [ProfileController::class, 'requestDeletion'])
        ->name('profile.deletion.store')
        ->middleware('throttle:3,1');
    Route::post('perfil/exportar', [ProfileController::class, 'requestExport'])
        ->name('profile.export');
    Route::get('hogares', [HouseholdController::class, 'index'])->name('households.index');
    Route::get('hogares/crear', [HouseholdController::class, 'create'])->name('households.create');
    Route::post('hogares', [HouseholdController::class, 'store'])->name('households.store');
    Route::get('hogares/{household}', [HouseholdController::class, 'show'])->name('households.show');
    Route::get('hogares/{household}/editar', [HouseholdController::class, 'edit'])->name('households.edit');
    Route::put('hogares/{household}', [HouseholdController::class, 'update'])->name('households.update');
    Route::delete('hogares/{household}', [HouseholdController::class, 'destroy'])->name('households.destroy');
    Route::post('hogares/{household}/activar', ActiveHouseholdController::class)->name('households.activate');
    Route::delete('hogares/{household}/miembros/{user}', [HouseholdMemberController::class, 'destroy'])
        ->name('households.members.destroy');

    Route::post('hogares/{household}/invitaciones', [HouseholdInvitationController::class, 'store'])
        ->name('households.invitations.store')
        ->middleware('throttle:10,1');
    Route::delete('hogares/{household}/invitaciones/{invitation}', [HouseholdInvitationController::class, 'destroy'])
        ->name('households.invitations.destroy');

    Route::post('invitaciones/{token}', [InvitationController::class, 'accept'])
        ->name('invitations.accept')
        ->middleware('throttle:10,1');

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
    Route::get('gastos/crear', [ExpenseController::class, 'create'])->name('expenses.create');
    Route::post('gastos', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::get('gastos/{expense}/editar', [ExpenseController::class, 'edit'])->name('expenses.edit');
    Route::put('gastos/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
    Route::delete('gastos/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    Route::post('gastos/{expense}/encuesta', [CompulsiveSurveyController::class, 'store'])
        ->name('expenses.survey.store')
        ->middleware('throttle:60,1');
    Route::get('compras/revisar', [CompulsiveSurveyController::class, 'followUpIndex'])
        ->name('purchases.review.index');
    Route::post('compras/revisar/{response}', [CompulsiveSurveyController::class, 'followUpStore'])
        ->name('purchases.review.store')
        ->middleware('throttle:60,1');
    Route::get('ingresos/crear', [IncomeController::class, 'create'])->name('incomes.create');
    Route::post('ingresos', [IncomeController::class, 'store'])->name('incomes.store');
    Route::get('ingresos/{income}/editar', [IncomeController::class, 'edit'])->name('incomes.edit');
    Route::put('ingresos/{income}', [IncomeController::class, 'update'])->name('incomes.update');
    Route::delete('ingresos/{income}', [IncomeController::class, 'destroy'])->name('incomes.destroy');
    Route::get('movimientos', [MovementsController::class, 'index'])->name('movements.index');
    Route::get('transferencias/crear', [TransferController::class, 'create'])->name('transfers.create');
    Route::post('transferencias', [TransferController::class, 'store'])->name('transfers.store');
    Route::get('transferencias/{transfer}/editar', [TransferController::class, 'edit'])->name('transfers.edit');
    Route::put('transferencias/{transfer}', [TransferController::class, 'update'])->name('transfers.update');
    Route::delete('transferencias/{transfer}', [TransferController::class, 'destroy'])->name('transfers.destroy');
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
    Route::get('ingresos-esperados', [ExpectedIncomeController::class, 'index'])
        ->name('expected-incomes.index');
    Route::post('ingresos-esperados', [ExpectedIncomeController::class, 'store'])
        ->name('expected-incomes.store');
    Route::put('ingresos-esperados/{expectedIncome}', [ExpectedIncomeController::class, 'update'])
        ->name('expected-incomes.update');
    Route::delete('ingresos-esperados/{expectedIncome}', [ExpectedIncomeController::class, 'destroy'])
        ->name('expected-incomes.destroy');
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
    Route::post('avisos/{key}', [AcknowledgementController::class, 'store'])
        ->name('acknowledgements.store');
    Route::post('guias/{tour}/vista', [TourController::class, 'store'])
        ->name('tours.store')
        ->middleware('throttle:30,1');
    Route::put('guias/preferencia', [TourController::class, 'preference'])
        ->name('tours.preference');
    Route::delete('guias/progreso', [TourController::class, 'destroy'])
        ->name('tours.destroy');

    Route::get('reportar-error', [ContactController::class, 'createBugReport'])
        ->name('bug-report.create');
    Route::post('reportar-error', [ContactController::class, 'storeBugReport'])
        ->middleware('throttle:5,60')
        ->name('bug-report.store');
    Route::get('deudas', [DebtController::class, 'index'])
        ->name('debts.index');
    Route::get('deudas/registrar', [DebtController::class, 'create'])
        ->name('debts.create');
    Route::post('deudas', [DebtController::class, 'store'])
        ->name('debts.store');
    Route::get('deudas/{debt}', [DebtController::class, 'show'])
        ->name('debts.show');
    Route::get('deudas/{debt}/editar', [DebtController::class, 'edit'])
        ->name('debts.edit');
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
    Route::get('cuentas-por-cobrar', [ReceivableController::class, 'index'])
        ->name('receivables.index');
    Route::get('cuentas-por-cobrar/registrar', [ReceivableController::class, 'create'])
        ->name('receivables.create');
    Route::post('cuentas-por-cobrar', [ReceivableController::class, 'store'])
        ->name('receivables.store');
    Route::get('cuentas-por-cobrar/{receivable}', [ReceivableController::class, 'show'])
        ->name('receivables.show');
    Route::get('cuentas-por-cobrar/{receivable}/editar', [ReceivableController::class, 'edit'])
        ->name('receivables.edit');
    Route::put('cuentas-por-cobrar/{receivable}', [ReceivableController::class, 'update'])
        ->name('receivables.update');
    Route::delete('cuentas-por-cobrar/{receivable}', [ReceivableController::class, 'destroy'])
        ->name('receivables.destroy');

    Route::post('cuentas-por-cobrar/{receivable}/cobros', [ReceivablePaymentController::class, 'store'])
        ->name('receivables.payments.store');
    Route::delete('cuentas-por-cobrar/{receivable}/cobros/{payment}', [ReceivablePaymentController::class, 'destroy'])
        ->name('receivables.payments.destroy');

    Route::put('cuentas/{account}/tarjeta', [CreditCardController::class, 'update'])
        ->name('accounts.credit-card.update');
    Route::delete('cuentas/{account}/tarjeta', [CreditCardController::class, 'destroy'])
        ->name('accounts.credit-card.destroy');

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
    Route::post('metas/{savingsGoal}/aportes', [SavingsGoalController::class, 'contribute'])
        ->name('savings-goals.contributions.store');
    Route::delete('metas/{savingsGoal}/aportes/{contribution}', [SavingsGoalController::class, 'destroyContribution'])
        ->name('savings-goals.contributions.destroy');
    Route::post('metas/{savingsGoal}/pausar', [SavingsGoalController::class, 'pause'])
        ->name('savings-goals.pause');
    Route::post('metas/{savingsGoal}/reactivar', [SavingsGoalController::class, 'resume'])
        ->name('savings-goals.resume');
    Route::post('metas/{savingsGoal}/completar', [SavingsGoalController::class, 'complete'])
        ->name('savings-goals.complete');
    Route::post('metas/{savingsGoal}/archivar', [SavingsGoalController::class, 'archive'])
        ->name('savings-goals.archive');
    Route::get('reportes', [ReportController::class, 'index'])
        ->name('reports.index');
    Route::get('reportes/exportar', [ReportController::class, 'export'])
        ->name('reports.export')
        ->middleware('throttle:10,1');
    Route::get('recordatorios', [ReminderController::class, 'index'])
        ->name('reminders.index');

    Route::get('recordatorios/nuevo', [ReminderController::class, 'create'])
        ->name('reminders.create');
    Route::post('recordatorios', [ReminderController::class, 'store'])
        ->name('reminders.store');
    Route::put('recordatorios/configuracion', [ReminderController::class, 'settings'])
        ->name('reminders.settings');

    Route::put('recordatorios/correo', [ReminderController::class, 'email'])
        ->name('reminders.email');
    Route::put('recordatorios/{reminder}', [ReminderController::class, 'update'])
        ->name('reminders.update');
    Route::delete('recordatorios/{reminder}', [ReminderController::class, 'destroy'])
        ->name('reminders.destroy');
    Route::post('recordatorios/{reminder}/completar', [ReminderController::class, 'complete'])
        ->name('reminders.complete');
});
