<?php

use Illuminate\Auth\AuthenticationException;
use Spatie\LaravelFlare\Support\LaravelDocumentationLinkFinder;
use Spatie\LaravelFlare\Support\LaravelVersion;

beforeEach(function () {
    $this->finder = new LaravelDocumentationLinkFinder();
});

it('can find a link for a laravel exception', function () {
    $link = $this->finder->findLinkForThrowable(new AuthenticationException());

    $majorVersion = LaravelVersion::major();

    expect($link)->toEqual("https://laravel.com/docs/{$majorVersion}.x/authentication");
});
