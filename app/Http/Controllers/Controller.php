<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Habilita $this->authorize() en todos los controladores (uso de Policies).
    use AuthorizesRequests;
}
