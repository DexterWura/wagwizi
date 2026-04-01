<?php

namespace Laramin\Utility;

use App\Models\GeneralSetting;
use Closure;

class GoToCore{

    public function handle($request, Closure $next)
    {

        $fileExists = file_exists(__DIR__.'/laramin.json');
        $general = $this->getGeneral();
        
        // Check if site is running on zimadsense.com
        $currentHost = @$_SERVER['HTTP_HOST'] ?? '';
        $appUrl = env("APP_URL", '');
        
        // Normalize the domain (remove protocol and www)
        $normalizeDomain = function($url) {
            $url = str_replace(['http://', 'https://'], '', $url);
            $url = str_replace('www.', '', $url);
            $url = rtrim($url, '/');
            return strtolower($url);
        };
        
        $currentDomain = $normalizeDomain($currentHost);
        $appDomain = $normalizeDomain($appUrl);
        $expectedDomain = 'zimadsense.com';
        
        // Only redirect to installation if file doesn't exist AND domain matches zimadsense.com
        if ($fileExists && $general->maintenance_mode != 9 && 
            ($currentDomain === $expectedDomain || $appDomain === $expectedDomain)) {
            return redirect()->route(VugiChugi::acDRouter());
        }
        return $next($request);
    }

    public function getGeneral(){
        $general = cache()->get('GeneralSetting');
        if (!$general) {
            $general = GeneralSetting::first();
        }
        return $general;
    }
}
