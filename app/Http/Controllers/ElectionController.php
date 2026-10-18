<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ElectionController extends Controller
{
    public function home(Request $request)
    {
        return view('election.home', ['candidates' => Candidate::where('active', true)->orderBy('id')->get(), 'election' => DB::table('elections')->find(1), 'voted' => DB::table('ballots')->where('election_id', 1)->where('device_hash', $request->attributes->get('device_hash'))->exists()]);
    }

    public function result()
    {
        return view('election.result');
    }

    public function data()
    {
        $data = Cache::remember('results-v2', 2, function () {
            $rows = Candidate::orderBy('id')->get(['id', 'name', 'group', 'photo', 'active', 'votes', 'adjustment']);
            $total = $rows->sum(fn ($c) => $c->votes + $c->adjustment);

            return ['status' => DB::table('elections')->value('status'), 'candidates' => $rows->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'group' => $c->group, 'photo' => $c->photo, 'active' => $c->active, 'percent' => $total ? round(($c->votes + $c->adjustment) * 100 / $total, 1) : 0])];
        });

        return response()->json($data);
    }

    public function vote(Request $request)
    {
        $input = $request->validate([
            'candidate_id' => 'required|integer',
            'voter_name' => 'required|string|min:5|max:160',
            'voter_group' => 'required|string|min:2|max:40',
        ], [
            'voter_name.required' => 'Введите ФИО.',
            'voter_name.min' => 'ФИО должно содержать минимум 5 символов.',
            'voter_name.max' => 'ФИО должно содержать не более 160 символов.',
            'voter_group.required' => 'Введите название группы.',
            'voter_group.min' => 'Название группы должно содержать минимум 2 символа.',
            'voter_group.max' => 'Название группы должно содержать не более 40 символов.',
        ]);
        DB::transaction(function () use ($request, $input) {
            $election = DB::table('elections')->where('id', 1)->lockForUpdate()->first();
            if ($election->status !== 'open') {
                throw ValidationException::withMessages(['vote' => 'Голосование сейчас закрыто.']);
            }
            $hash = $request->attributes->get('device_hash');
            if (DB::table('ballots')->where('election_id', 1)->where('device_hash', $hash)->exists()) {
                throw ValidationException::withMessages(['vote' => 'Ваш голос уже принят.']);
            }
            $candidate = Candidate::whereKey($input['candidate_id'])->where('active', true)->lockForUpdate()->first();
            if (! $candidate) {
                throw ValidationException::withMessages(['vote' => 'Кандидат больше не участвует.']);
            }
            DB::table('ballots')->insert(['election_id' => 1, 'candidate_id' => $candidate->id, 'device_hash' => $hash, 'voter_name' => $input['voter_name'], 'voter_group' => $input['voter_group'], 'created_at' => now()]);
            $candidate->increment('votes');
        }, 5);
        Cache::forget('results-v2');

        return response()->json(['message' => 'Спасибо! Ваш голос принят.']);
    }
}
