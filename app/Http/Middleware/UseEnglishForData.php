<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Data files are always written in English, whatever interface language the
 * person downloading them uses (I18N-02): the research exports must read the
 * same for every admin, one comparable row per answer (REP-04, REP-06), and
 * import templates must keep the column names the importer expects.
 */
class UseEnglishForData
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale(Locales::DEFAULT);

        return $next($request);
    }
}
