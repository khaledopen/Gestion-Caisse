<?php
namespace App\Http\Controllers\Admin;
use App\Models\User;
use Illuminate\Http\Request;

class UserController
{
    public function index()
    {
        return view('admin.users', ['users' => User::withCount('transactions')->orderByDesc('created_at')->paginate(15)]);
    }

    public function toggleStatus(Request $request, User $user)
    {
        abort_if($user->is_admin, 422, 'Un administrateur ne peut pas être bloqué.');
        $user->update(['is_active' => !$user->is_active]);
        return back()->with('success', $user->is_active ? 'Utilisateur débloqué.' : 'Utilisateur bloqué.');
    }
}
