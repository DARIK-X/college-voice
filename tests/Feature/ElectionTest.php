<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ElectionTest extends TestCase
{
    use RefreshDatabase;

    private function candidate(): Candidate
    {
        return Candidate::create(['name' => 'Тестовый кандидат', 'group' => 'А-1', 'active' => true, 'photo' => '/assets/candidate-1.png']);
    }

    private function open(): void
    {
        DB::table('elections')->where('id', 1)->update(['status' => 'open']);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->is_admin = true;
        $u->password = Hash::make('test-password-123');
        $u->save();

        return $u;
    }

    public function test_public_pages_have_no_admin_links(): void
    {
        $this->withoutVite();
        $this->candidate();
        $this->get('/')->assertOk()->assertDontSee('/admin/');
        $this->get('/result')->assertOk()->assertDontSee('/admin/');
    }

    public function test_same_device_cannot_vote_twice(): void
    {
        $c = $this->candidate();
        $this->open();
        $this->withCredentials()->withCookie('voter_device', (string) Str::uuid());
        $this->postJson('/vote', ['candidate_id' => $c->id, 'voter_name' => 'Тестовый Ученик Иванович', 'voter_group' => 'СА-26-1Б'])->assertOk();
        $this->postJson('/vote', ['candidate_id' => $c->id, 'voter_name' => 'Тестовый Ученик Иванович', 'voter_group' => 'СА-26-1Б'])->assertUnprocessable();
        $this->assertDatabaseCount('ballots', 1);
        $this->assertSame(1, $c->fresh()->votes);
    }

    public function test_closed_election_rejects_vote(): void
    {
        $c = $this->candidate();
        $this->withCredentials()->withCookie('voter_device', (string) Str::uuid())->postJson('/vote', ['candidate_id' => $c->id, 'voter_name' => 'Тестовый Ученик Иванович', 'voter_group' => 'СА-26-1Б'])->assertUnprocessable();
        $this->assertDatabaseCount('ballots', 0);
    }

    public function test_missing_cookie_cannot_vote(): void
    {
        $c = $this->candidate();
        $this->open();
        $this->postJson('/vote', ['candidate_id' => $c->id, 'voter_name' => 'Тестовый Ученик Иванович', 'voter_group' => 'СА-26-1Б'])->assertStatus(419);
        $this->assertDatabaseCount('ballots', 0);
    }

    public function test_inactive_candidate_rejects_vote(): void
    {
        $c = $this->candidate();
        $c->update(['active' => false]);
        $this->open();
        $this->withCredentials()->withCookie('voter_device', (string) Str::uuid())->postJson('/vote', ['candidate_id' => $c->id, 'voter_name' => 'Тестовый Ученик Иванович', 'voter_group' => 'СА-26-1Б'])->assertUnprocessable();
    }

    public function test_distinct_devices_each_vote_once(): void
    {
        $c = $this->candidate();
        $this->open();
        foreach (range(1, 2) as $i) {
            $this->withCredentials()->withCookie('voter_device', (string) Str::uuid())->postJson('/vote', ['candidate_id' => $c->id, 'voter_name' => 'Тестовый Ученик Иванович', 'voter_group' => 'СА-26-1Б'])->assertOk();
        }$this->assertSame(2, $c->fresh()->votes);
    }

    public function test_admin_routes_are_protected(): void
    {
        $this->get('/admin/panel')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create())->get('/admin/candidate')->assertForbidden();
    }

    public function test_only_admin_can_authenticate(): void
    {
        $u = User::factory()->create(['password' => Hash::make('secret')]);
        $this->post('/admin/login', ['email' => $u->email, 'password' => 'secret'])->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_admin_login_and_logout(): void
    {
        $u = $this->admin();
        $this->post('/admin/login', ['email' => $u->email, 'password' => 'test-password-123'])->assertRedirect('/admin/panel');
        $this->assertAuthenticatedAs($u);
        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_state_requires_correct_confirmation_and_password(): void
    {
        $this->candidate();
        $u = $this->admin();
        $this->actingAs($u)->post('/admin/panel/state', ['status' => 'open', 'confirmation' => 'oops', 'password' => 'test-password-123'])->assertSessionHasErrors('confirmation');
        $this->assertDatabaseHas('elections', ['status' => 'draft']);
        $this->post('/admin/panel/state', ['status' => 'open', 'confirmation' => 'НАЧАТЬ', 'password' => 'wrong'])->assertSessionHasErrors('password');
        $this->post('/admin/panel/state', ['status' => 'open', 'confirmation' => 'НАЧАТЬ', 'password' => 'test-password-123'])->assertRedirect();
        $this->assertDatabaseHas('elections', ['status' => 'open']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'election.state']);
    }

    public function test_adjustments_are_audited_and_cannot_remove_real_ballots(): void
    {
        $c = $this->candidate();
        $u = $this->admin();
        $this->actingAs($u)->post("/admin/candidate/{$c->id}/adjust", ['delta' => 15, 'reason' => 'Исправление тестовых данных', 'password' => 'test-password-123'])->assertRedirect();
        $this->assertSame(15, $c->fresh()->adjustment);
        $this->assertSame(0, $c->fresh()->votes);
        $this->assertDatabaseHas('audit_logs', ['action' => 'votes.adjust']);
        $this->post("/admin/candidate/{$c->id}/adjust", ['delta' => -16, 'reason' => 'Исправление тестовых данных', 'password' => 'test-password-123'])->assertSessionHasErrors('delta');
    }

    public function test_candidate_with_votes_is_withdrawn_not_deleted(): void
    {
        $c = $this->candidate();
        $c->votes = 1;
        $c->save();
        $this->actingAs($this->admin())->post("/admin/candidate/{$c->id}/remove", ['password' => 'test-password-123'])->assertRedirect();
        $this->assertDatabaseHas('candidates', ['id' => $c->id, 'active' => false, 'votes' => 1]);
    }

    public function test_results_percentages_and_empty_state(): void
    {
        $this->getJson('/results/data')->assertOk()->assertJsonMissingPath('total')->assertJsonPath('candidates', []);
        $c = $this->candidate();
        $c->votes = 3;
        $c->adjustment = 1;
        $c->save();
        Cache::forget('results-v2');
        $this->getJson('/results/data')->assertJsonMissingPath('total')->assertJsonMissingPath('candidates.0.votes')->assertJsonMissingPath('candidates.0.adjustment')->assertJsonPath('candidates.0.percent', 100);
    }

    public function test_database_rejects_duplicate_ballots(): void
    {
        $c = $this->candidate();
        $row = ['election_id' => 1, 'candidate_id' => $c->id, 'device_hash' => str_repeat('a', 64), 'created_at' => now()];
        DB::table('ballots')->insert($row);
        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('ballots')->insert($row);
    }
}
