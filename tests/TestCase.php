<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Guard `sanctum` meng-cache user yang sudah ter-resolve selama proses
     * berjalan, sehingga actingAs() kedua dalam satu test tidak berlaku.
     * Buang cache guard dulu supaya setiap actingAs benar-benar mengganti user.
     */
    public function actingAs(UserContract $user, $guard = null)
    {
        $this->app['auth']->forgetGuards();

        return parent::actingAs($user, $guard);
    }

    /**
     * Kembali menjadi tamu setelah actingAs() dalam test yang sama.
     */
    protected function asGuest(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }
}
