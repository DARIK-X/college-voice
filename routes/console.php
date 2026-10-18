<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

Artisan::command('admin:create', function () {
    $email = $this->ask('Email администратора');
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Некорректный email.');

        return 1;
    }
    if (User::where('email', $email)->exists()) {
        $this->error('Пользователь существует.');

        return 1;
    }
    $password = $this->secret('Пароль (минимум 12 символов)');
    if (mb_strlen((string) $password) < 12) {
        $this->error('Слишком короткий пароль.');

        return 1;
    }
    $u = new User;
    $u->name = 'Администратор';
    $u->email = $email;
    $u->password = Hash::make($password);
    $u->is_admin = true;
    $u->save();
    $this->info('Администратор создан.');
})->purpose('Создать администратора без публичной регистрации');
