<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    private function loginAdmin(): void
    {
        $u = User::factory()->create();
        $u->is_admin = true;
        $u->save();
        $this->actingAs($u);
    }

    public function test_admin_pages_render_and_candidate_can_be_created_and_updated(): void
    {
        $this->withoutVite();
        $this->loginAdmin();
        $this->get('/admin/panel')->assertOk();
        $this->get('/admin/candidate')->assertOk();
        $this->post('/admin/candidate', ['name' => 'Новый кандидат', 'group' => 'А-1', 'active' => 1])->assertRedirect();
        $c = Candidate::firstOrFail();
        $this->assertTrue($c->active);
        $this->get('/admin/candidate')->assertOk()->assertSee('Новый кандидат');
        $this->post('/admin/candidate/'.$c->id, ['name' => 'Изменённое имя', 'group' => 'А-2'])->assertRedirect();
        $this->assertFalse($c->fresh()->active);
        $this->assertSame('Изменённое имя', $c->fresh()->name);
    }

    public function test_svg_upload_is_rejected(): void
    {
        $this->loginAdmin();
        Storage::fake('public');
        $this->post('/admin/candidate', ['name' => 'Кандидат', 'group' => 'А-1', 'photo' => UploadedFile::fake()->createWithContent('unsafe.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('candidates', 0);
    }

    public function test_login_throttling(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => 'blocked@example.com', 'password' => 'bad'])->assertSessionHasErrors();
        }
        $this->post('/admin/login', ['email' => 'blocked@example.com', 'password' => 'bad'])->assertStatus(429);
    }
}
