<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Http\Requests\Debt\UpdateCreditCardRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;

/**
 * Atributos de tarjeta de crédito sobre una cuenta con type=credit_card
 * (Épica 6, ADR-0002). Se gestionan desde el detalle de la cuenta.
 *
 * ⚠️ Nunca se guarda número completo, CVV ni PIN (docs/SECURITY.md §4).
 */
class CreditCardController extends Controller
{
    /**
     * Crea o actualiza los datos de tarjeta de una cuenta.
     */
    public function update(UpdateCreditCardRequest $request, Account $account): RedirectResponse
    {
        // La autorización cuelga de la cuenta: es su dueño quien la configura.
        $this->authorize('update', $account);

        // Solo una cuenta de tipo tarjeta puede tener cupo y fecha de corte.
        abort_unless($account->type === AccountType::CreditCard, 404);

        $data = $request->validatedData();

        $card = $account->creditCard;

        if ($card === null) {
            $card = $account->creditCard()->make($data);
            $card->household_id = $account->household_id;
            // Cupo disponible inicial = cupo total; a partir de ahí lo mueve el uso.
            $card->available_credit = $data['credit_limit'];
            $card->save();

            return redirect()
                ->route('accounts.show', $account)
                ->with('status', __('Datos de la tarjeta guardados.'));
        }

        $card->fill($data);
        // Al cambiar el cupo, el disponible se ajusta conservando lo ya usado.
        $used = (float) $card->getOriginal('credit_limit') - (float) $card->getOriginal('available_credit');
        $card->available_credit = round(max(0.0, (float) $data['credit_limit'] - $used), 2);
        $card->save();

        return redirect()
            ->route('accounts.show', $account)
            ->with('status', __('Datos de la tarjeta actualizados.'));
    }

    public function destroy(Account $account): RedirectResponse
    {
        $this->authorize('update', $account);

        $account->creditCard?->delete();

        return redirect()
            ->route('accounts.show', $account)
            ->with('status', __('Datos de la tarjeta eliminados.'));
    }
}
