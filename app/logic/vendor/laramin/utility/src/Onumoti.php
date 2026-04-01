<?php

namespace Laramin\Utility;

class Onumoti{

    public static function getData(){
        // Check if the site is running on https://zimadsense.com
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
        
        // Check if current domain or app URL matches zimadsense.com
        if ($currentDomain !== $expectedDomain && $appDomain !== $expectedDomain) {
            $error = \Illuminate\Validation\ValidationException::withMessages([
                'error' => 'This application must run on https://zimadsense.com'
            ]);
            throw $error;
        }
        
        // Domain check passed, continue normally
        return;
    }

    public static function mySite($site,$className){
        $myClass = VugiChugi::clsNm();
        if($myClass != $className){
            return $site->middleware(VugiChugi::mdNm());
        }
    }
}
