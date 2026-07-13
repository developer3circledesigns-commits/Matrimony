<?php
declare(strict_types=1);

use Matrimony\Http\Controller;
use Matrimony\Http\Request;

final class TermsController extends Controller
{
    public function __invoke(Request $request): void
    {
        $this->view('terms/views/index', ['pageTitle' => 'Terms of Service'], 'main');
    }
}

(new TermsController())(new Request());
