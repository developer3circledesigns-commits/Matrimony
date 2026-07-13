<?php
declare(strict_types=1);

use Matrimony\Http\Controller;
use Matrimony\Http\Request;

final class CookiesController extends Controller
{
    public function __invoke(Request $request): void
    {
        $this->view('cookies/views/index', ['pageTitle' => 'Cookie Policy'], 'main');
    }
}

(new CookiesController())(new Request());
