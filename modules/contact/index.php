<?php
declare(strict_types=1);

use Matrimony\Http\Controller;
use Matrimony\Http\Request;

final class ContactController extends Controller
{
    public function __invoke(Request $request): void
    {
        $this->view('contact/views/index', ['pageTitle' => 'Contact Us'], 'main');
    }
}

(new ContactController())(new Request());
