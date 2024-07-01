<?php

use HeadlessChromium\BrowserFactory;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('generate:dashs', function () {
    $dashboards = \App\Models\Dashboard::all();

    $browser = (new BrowserFactory()) -> createBrowser();

    $page = $browser -> createPage();

    foreach($dashboards as $dashboard) {
        $page -> setViewport(1920, 1080);
        $page -> navigate($dashboard->url)->waitForNavigation();

        sleep(10);

        $namefile = $dashboard->id . "_dashprint.png";

        $screenshot = $page -> screenshot();
        $screenshot -> saveToFile("/var/www/html/public/$namefile");
    }

    $page->close();
})->purpose('generate cash images')->hourly();
