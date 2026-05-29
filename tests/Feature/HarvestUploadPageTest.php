<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HarvestUploadPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_harvest_upload_route_exists(): void
    {
        $this->assertTrue(route('harvest.upload') !== null);
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get(route('harvest.upload'));

        $response->assertRedirect(route('login'));
    }
}
