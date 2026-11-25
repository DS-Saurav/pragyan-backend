<?php

namespace App\Http\Controllers;

use App\Support\PublicSitemap;
use Illuminate\Http\Response;

final class PublicDiscoveryController extends Controller
{
    public function __construct(private PublicSitemap $sitemap) {}

    public function sitemap(): Response
    {
        return response($this->sitemap->xml(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function robots(): Response
    {
        return response($this->sitemap->robots(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
