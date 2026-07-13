<?php
declare(strict_types=1);

use Matrimony\Http\Controller;
use Matrimony\Http\Request;

final class PackagesController extends Controller
{
    public function __invoke(Request $request): void
    {
        $this->view('packages/views/index', ['pageTitle' => 'Packages'], 'main');
    }
}

(new PackagesController())(new Request());
