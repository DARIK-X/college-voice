<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    private function audit(Request $r, string $action, array $details): void
    {
        DB::table('audit_logs')->insert(['user_id' => $r->user()->id, 'action' => $action, 'details' => json_encode($details, JSON_UNESCAPED_UNICODE), 'created_at' => now()]);
    }

    private function password(Request $r): void
    {
        if (! Hash::check((string) $r->input('password'), $r->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Неверный пароль подтверждения.']);
        }
    }

    public function login()
    {
        return view('election.login');
    }

    public function authenticate(Request $r)
    {
        $v = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt([...$v, 'is_admin' => true])) {
            throw ValidationException::withMessages(['email' => 'Неверные данные для входа.']);
        }
        $r->session()->regenerate();

        return redirect()->route('admin.panel');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/admin/login');
    }

    public function panel()
    {
        return view('election.panel', ['election' => DB::table('elections')->find(1), 'total' => DB::table('ballots')->count(), 'voters' => DB::table('ballots')->orderByDesc('id')->paginate(50, ['id', 'voter_name', 'voter_group', 'created_at'], 'voters_page'), 'logs' => DB::table('audit_logs')->orderByDesc('id')->limit(30)->get()]);
    }

    public function state(Request $r)
    {
        $v = $r->validate(['status' => 'required|in:open,closed', 'confirmation' => 'required|string', 'password' => 'required|string']);
        $this->password($r);
        $expected = $v['status'] === 'open' ? 'НАЧАТЬ' : 'ЗАВЕРШИТЬ';
        if ($v['confirmation'] !== $expected) {
            throw ValidationException::withMessages(['confirmation' => 'Введите слово '.$expected]);
        }
        DB::transaction(function () use ($r, $v) {
            $e = DB::table('elections')->where('id', 1)->lockForUpdate()->first();
            if ($e->status === $v['status']) {
                throw ValidationException::withMessages(['status' => 'Это состояние уже установлено.']);
            }
            if ($v['status'] === 'open' && ! Candidate::where('active', true)->exists()) {
                throw ValidationException::withMessages(['status' => 'Добавьте хотя бы одного кандидата.']);
            }
            DB::table('elections')->where('id', 1)->update(['status' => $v['status'], 'updated_at' => now()]);
            $this->audit($r, 'election.state', ['from' => $e->status, 'to' => $v['status']]);
        });
        Cache::forget('results-v2');

        return back()->with('success', 'Состояние голосования изменено.');
    }

    public function candidates()
    {
        return view('election.candidates', ['candidates' => Candidate::orderBy('id')->get()]);
    }

    public function save(Request $r, ?Candidate $candidate = null)
    {
        $v = $r->validate(['name' => 'required|string|max:160', 'group' => 'required|string|max:40', 'program' => 'nullable|string|max:2000', 'photo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:4096', 'active' => 'nullable|boolean']);
        if ($r->hasFile('photo')) {
            $v['photo'] = '/storage/'.$r->file('photo')->store('candidates', 'public');
        } else {
            unset($v['photo']);
        }
        $v['active'] = $r->boolean('active');
        DB::transaction(function () use ($r, $v, $candidate) {
            DB::table('elections')->where('id', 1)->lockForUpdate()->first();
            if ($candidate) {
                $candidate = Candidate::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
                $candidate->update($v);
            } else {
                $candidate = Candidate::create($v);
            }
            $this->audit($r, 'candidate.save', ['id' => $candidate->id, 'name' => $candidate->name]);
        });
        Cache::forget('results-v2');

        return back()->with('success', 'Кандидат сохранён.');
    }

    public function remove(Request $r, Candidate $candidate)
    {
        $r->validate(['password' => 'required|string']);
        $this->password($r);
        DB::transaction(function () use ($r, $candidate) {
            DB::table('elections')->where('id', 1)->lockForUpdate()->first();
            $c = Candidate::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            if ($c->votes + $c->adjustment > 0) {
                $c->update(['active' => false]);
                $action = 'candidate.withdraw';
            } else {
                $c->delete();
                $action = 'candidate.delete';
            }
            $this->audit($r, $action, ['id' => $c->id, 'name' => $c->name]);
        });
        Cache::forget('results-v2');

        return back()->with('success', 'Кандидат удалён или снят с сохранением голосов.');
    }

    public function adjust(Request $r, Candidate $candidate)
    {
        $v = $r->validate(['delta' => 'required|integer|between:-100000,100000|not_in:0', 'reason' => 'required|string|min:10|max:500', 'password' => 'required|string']);
        $this->password($r);
        DB::transaction(function () use ($r, $candidate, $v) {
            DB::table('elections')->where('id', 1)->lockForUpdate()->first();
            $c = Candidate::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            if ($c->adjustment + (int) $v['delta'] < 0) {
                throw ValidationException::withMessages(['delta' => 'Нельзя удалить реальные голоса или получить отрицательную корректировку.']);
            }
            $before = $c->adjustment;
            $c->increment('adjustment', (int) $v['delta']);
            $this->audit($r, 'votes.adjust', ['candidate' => $c->id, 'before' => $before, 'delta' => (int) $v['delta'], 'reason' => $v['reason']]);
        });
        Cache::forget('results-v2');

        return back()->with('success', 'Корректировка сохранена в журнале.');
    }
}
