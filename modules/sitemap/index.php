<?php
declare(strict_types=1);

use Matrimony\Http\Controller;
use Matrimony\Http\Request;

final class SitemapController extends Controller
{
    public function __invoke(Request $request): void
    {
        $this->view('sitemap/views/index', ['pageTitle' => 'Sitemap'], 'main');
    }
}

(new SitemapController())(new Request());
