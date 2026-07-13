<?php
declare(strict_types=1);

use Matrimony\Http\Controller;
use Matrimony\Http\Request;

final class FaqController extends Controller
{
    public function __invoke(Request $request): void
    {
        $this->view('faq/views/index', ['pageTitle' => 'FAQ'], 'main');
    }
}

(new FaqController())(new Request());
