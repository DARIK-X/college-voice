<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class VoterDetailsTest extends TestCase
{
    use RefreshDatabase;

    private function prepareVote(): Candidate
    {
        DB::table('elections')->where('id', 1)->update(['status' => 'open']);
        $this->withCredentials()->withCookie('voter_device', (string) Str::uuid());

        return Candidate::create(['name' => 'Кандидат', 'group' => 'А-1', 'active' => true]);
    }

    public function test_name_and_group_are_required_on_server(): void
    {
        $c = $this->prepareVote();
        $this->postJson('/vote', ['candidate_id' => $c->id])->assertUnprocessable()->assertJsonValidationErrors(['voter_name', 'voter_group']);
        $this->assertDatabaseCount('ballots', 0);
        $this->assertSame(0, $c->fresh()->votes);
    }

    public function test_blank_and_overlong_details_are_rejected(): void
    {
        $c = $this->prepareVote();
        $this->postJson('/vote', ['candidate_id' => $c->id, 'voter_name' => '   ', 'voter_group' => '   '])->assertUnprocessable();
        $this->postJson('/vote', ['candidate_id' => $c->id, 'voter_name' => str_repeat('я', 161), 'voter_group' => str_repeat('A', 41)])->assertUnprocessable()->assertJsonValidationErrors(['voter_name', 'voter_group']);
        $this->assertDatabaseCount('ballots', 0);
    }

    public function test_details_saved_and_not_exposed_in_public_results(): void
    {
        $c = $this->prepareVote();
        $this->postJson('/vote', ['candidate_id' => $c->id, 'voter_name' => '  Әбдірахман Тест Ұлы  ', 'voter_group' => '  PRIVATE-77  '])->assertOk();
        $this->assertDatabaseHas('ballots', ['voter_name' => 'Әбдірахман Тест Ұлы', 'voter_group' => 'PRIVATE-77']);
        $response = $this->getJson('/results/data')->assertOk();
        $json = $response->json();
        $this->assertStringNotContainsString('Әбдірахман', json_encode($json, JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString('PRIVATE-77', json_encode($json));
        $this->withoutVite();
        $this->get('/result')->assertOk()->assertDontSee('PRIVATE-77');
    }

    public function test_admin_can_view_list_and_legacy_ballots_are_preserved(): void
    {
        $c = $this->prepareVote();
        DB::table('ballots')->insert(['election_id' => 1, 'candidate_id' => $c->id, 'device_hash' => str_repeat('a', 64), 'created_at' => now()]);
        $this->postJson('/vote', ['candidate_id' => $c->id, 'voter_name' => 'Тестовый Ученик Отчество', 'voter_group' => 'PRIVATE-77'])->assertOk();
        $u = User::factory()->create();
        $u->is_admin = true;
        $u->save();
        $this->withoutVite();
        $this->actingAs($u)->get('/admin/panel')->assertOk()->assertSee('Тестовый Ученик Отчество')->assertSee('PRIVATE-77')->assertSee('Не указано (старый голос)');
    }
}
