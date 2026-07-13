<?php
declare(strict_types=1);

use Matrimony\Http\Controller;
use Matrimony\Http\Request;

final class AboutController extends Controller
{
    public function __invoke(Request $request): void
    {
        $this->view('about/views/index', ['pageTitle' => 'About Us'], 'main');
    }
}

(new AboutController())(new Request());
