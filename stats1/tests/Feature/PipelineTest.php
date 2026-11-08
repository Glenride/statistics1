<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_page_can_be_rendered()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/pipeline');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('pipeline/index')
        );
    }
}
