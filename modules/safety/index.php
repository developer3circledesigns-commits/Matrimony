<?php
declare(strict_types=1);

use Matrimony\Http\Controller;
use Matrimony\Http\Request;

final class SafetyController extends Controller
{
    public function __invoke(Request $request): void
    {
        $this->view('safety/views/index', ['pageTitle' => 'Safety Tips'], 'main');
    }
}

(new SafetyController())(new Request());
