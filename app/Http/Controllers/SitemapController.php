<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * The public pages for search engines (client report 2026-10-04), linked
 * from robots.txt.
 */
final class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $pages = [
            ['url' => url('/'), 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['url' => url('/contact'), 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['url' => url('/hotel-signup'), 'priority' => '0.6', 'changefreq' => 'monthly'],
        ];
        $today = now()->toDateString();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($pages as $page) {
            $xml .= '  <url><loc>'.e($page['url']).'</loc><lastmod>'.$today.'</lastmod>'
                .'<changefreq>'.$page['changefreq'].'</changefreq><priority>'.$page['priority'].'</priority></url>'."\n";
        }

        return response($xml.'</urlset>'."\n", 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
