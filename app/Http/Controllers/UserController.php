<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public const ROLES = [
        'admin' => 'Administrateur : tout',
        'secretaire' => 'Secrétaire : produits, stock, achats, clients (sans bénéfices ni paiements fournisseurs)',
        'caissier' => 'Caissier : la caisse uniquement',
    ];

    public function index(): View
    {
        return view('users.index', [
            'users' => User::with('roles', 'employee:id,user_id,position')->withTrashed()->orderByDesc('is_active')->orderBy('name')->get(),
            'roles' => self::ROLES,
        ]);
    }

    public function create(): View
    {
        return view('users.form', ['user' => new User(['is_active' => true]), 'roles' => self::ROLES, 'role' => 'caissier']);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $user = User::create($data);
        $user->syncRoles([$data['role']]);

        return redirect()->route('users.index')->with('success', "Compte de {$user->name} créé. Communiquez-lui son mot de passe.");
    }

    public function edit(User $user): View
    {
        return view('users.form', ['user' => $user, 'roles' => self::ROLES, 'role' => $user->getRoleNames()->first()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        if ($user->is($request->user()) && ($data['role'] !== 'admin' || ! $data['is_active'])) {
            return back()->with('error', 'Vous ne pouvez pas retirer vos propres droits d\'administrateur ni désactiver votre compte.');
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);
        $user->syncRoles([$data['role']]);

        return redirect()->route('users.index')->with('success', 'Compte mis à jour.');
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Vous ne pouvez pas désactiver votre propre compte.');
        }
        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', $user->is_active ? "Compte de {$user->name} réactivé." : "Compte de {$user->name} désactivé : il ne peut plus se connecter.");
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(Role::pluck('name'))],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [], ['name' => 'nom', 'password' => 'mot de passe', 'role' => 'rôle']);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
