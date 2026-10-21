<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Theme Favicon and Touch Icons
    |--------------------------------------------------------------------------
    |
    | Here you may specify the paths or URLs for the favicon and touch icons
    | used throughout your application. These can be customized via .env.
    |
    */

    'favicon_ico' => env('FAVICON_ICO', env('THEME_FAVICON_ICO', '/favicon.ico')),

    'favicon_svg' => env('FAVICON_SVG', env('THEME_FAVICON_SVG', '/favicon.svg')),

    'apple_touch_icon' => env('APPLE_TOUCH_ICON', env('THEME_APPLE_TOUCH_ICON', '/apple-touch-icon.png')),

];
