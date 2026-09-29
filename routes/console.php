<?php
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
Artisan::command('caisse:user {email} {--admin}', function () {
    $data = ['name' => $this->ask('Nom'), 'email' => $this->argument('email'), 'password' => $this->secret('Mot de passe (12 caractères minimum)')];
    $validator = Validator::make($data, ['name' => 'required|string|max:255', 'email' => 'required|email|unique:users', 'password' => ['required', Password::min(12)]]);
    if ($validator->fails()) { $this->error($validator->errors()->first()); return 1; }
    User::create([...$data, 'is_admin' => $this->option('admin'), 'is_active' => true]);
    $this->info('Utilisateur créé.');
});

Artisan::command('caisse:admin {email}', function () {
    $user = User::where('email', $this->argument('email'))->first();
    if (!$user) { $this->error('Utilisateur introuvable. Inscrivez d’abord ce compte.'); return 1; }
    $user->update(['is_admin' => true, 'is_active' => true]);
    $this->info("{$user->email} est maintenant administrateur et actif.");
    return 0;
})->purpose('Promouvoir un utilisateur inscrit en administrateur');
