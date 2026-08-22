<?php

declare(strict_types=1);

namespace App\Http\Requests\ExpectedIncome;

/**
 * Valida la edición de un ingreso esperado.
 *
 * Las reglas son idénticas a las del alta (todos los campos son editables),
 * así que se heredan en lugar de duplicarlas. Se mantiene una clase por
 * operación de escritura para respetar la convención del proyecto y poder
 * divergir sin tocar el alta.
 */
class UpdateExpectedIncomeRequest extends StoreExpectedIncomeRequest {}
