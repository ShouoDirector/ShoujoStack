<?php

namespace Tests;

use BookStack\Http\Request;

class UrlTest extends TestCase
{
    public function test_url_helper_takes_custom_url_into_account()
    {
        $this->runWithEnv('APP_URL', 'http://example.com/bookstack', function () {
            $this->assertEquals('http://example.com/bookstack/books', url('/books'));
        });
    }

    public function test_url_helper_sets_correct_scheme_even_when_request_scheme_is_different()
    {
        $this->runWithEnv('APP_URL', 'https://example.com/', function () {
            $this->get('http://example.com/login')->assertSee('https://example.com/dist/styles.css');
        });
    }

    public function test_app_url_forces_overrides_on_base_request()
    {
        config()->set('app.url', 'https://donkey.example.com:8091/cool/docs');

        // Have to manually get and wrap request in our custom type due to testing mechanics
        $this->get('/login');
        $bsRequest = Request::createFrom(request());

        $this->assertEquals('https://donkey.example.com:8091', $bsRequest->getSchemeAndHttpHost());
        $this->assertEquals('/cool/docs', $bsRequest->getBaseUrl());
        $this->assertEquals('https://donkey.example.com:8091/cool/docs/login', $bsRequest->getUri());
    }

    public function test_app_url_without_path_does_not_duplicate_path_slash()
    {
        config()->set('app.url', 'https://donkey.example.com');

        // Have to manually get and wrap request in our custom type due to testing mechanics
        $this->get('/settings');
        $bsRequest = Request::createFrom(request());

        $this->assertEquals('https://donkey.example.com', $bsRequest->getSchemeAndHttpHost());
        $this->assertEquals('', $bsRequest->getBaseUrl());
        $this->assertEquals('/settings', $bsRequest->getPathInfo());
        $this->assertEquals('https://donkey.example.com/settings', $bsRequest->getUri());
    }
}
