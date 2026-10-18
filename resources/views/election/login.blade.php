@extends('election.layout',['admin'=>true])
@section('title','Вход')
@section('content')<section class="login-panel"><span class="eyebrow">УПРАВЛЕНИЕ ВЫБОРАМИ</span><h1>Вход</h1><p class="muted">Доступ для администратора</p><form method="post" action="/admin/login">@csrf<label>Email<input type="email" name="email" value="{{ old('email') }}" required autocomplete="username" maxlength="254"></label><label>Пароль<input type="password" name="password" required autocomplete="current-password"></label><button class="button w-full">Войти →</button></form></section>@endsection
