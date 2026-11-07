<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Akun staf hasil seeder wajib ganti password; untuk test lain anggap sudah diganti.
        // (Perilaku wajib-ganti-password diuji khusus di LoginHardeningTest.)
        if (($this->seed ?? false) && ! ($this->keepForcedPasswordChange ?? false)) {
            User::query()->update(['must_change_password' => false]);
        }
    }
}
