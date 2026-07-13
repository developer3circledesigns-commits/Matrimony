<?php
declare(strict_types=1);

use Matrimony\Http\Controller;
use Matrimony\Http\Request;

final class PrivacyController extends Controller
{
    public function __invoke(Request $request): void
    {
        $this->view('privacy/views/index', ['pageTitle' => 'Privacy Policy'], 'main');
    }
}

(new PrivacyController())(new Request());
