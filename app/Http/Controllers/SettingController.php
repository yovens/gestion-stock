<?php

namespace App\Http\Controllers;

use App\Models\CashRegister;
use App\Models\ExchangeRate;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class SettingController extends Controller
{
    public const KEYS = [
        'store_name' => 'general', 'store_address' => 'general', 'store_phone' => 'general', 'store_nif' => 'general',
        'receipt_footer' => 'receipt', 'max_discount_percent' => 'sales', 'expiry_alert_days' => 'stock',
        'default_usd_rate' => 'sales', 'backup_path' => 'backup', 'backup_keep' => 'backup',
    ];

    public function edit(): View
    {
        return view('settings.edit', [
            'values' => collect(self::KEYS)->keys()->mapWithKeys(fn ($k) => [$k => Setting::get($k)])->all(),
            'logo' => Setting::get('store_logo'),
            'rates' => ExchangeRate::with('user:id,name')->latest('effective_date')->latest('id')->limit(10)->get(),
            'currentRate' => ExchangeRate::current(),
            'registers' => CashRegister::withCount(['sessions as open_count' => fn ($q) => $q->where('status', 'open')])->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:100'],
            'store_address' => ['nullable', 'string', 'max:255'],
            'store_phone' => ['nullable', 'string', 'max:60'],
            'store_nif' => ['nullable', 'string', 'max:50'],
            'receipt_footer' => ['nullable', 'string', 'max:200'],
            'max_discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'expiry_alert_days' => ['required', 'integer', 'min:1', 'max:365'],
            'default_usd_rate' => ['required', 'numeric', 'gt:0'],
            'backup_path' => ['nullable', 'string', 'max:255'],
            'backup_keep' => ['required', 'integer', 'min:3', 'max:365'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'remove_logo' => ['boolean'],
        ]);

        foreach (self::KEYS as $key => $group) {
            Setting::set($key, $data[$key] ?? null, $group);
        }

        if ($request->hasFile('logo') || $request->boolean('remove_logo')) {
            if ($old = Setting::get('store_logo')) Storage::disk('public')->delete($old);
            Setting::set('store_logo', $request->file('logo')?->store('logo', 'public'), 'general');
        }

        activity('parametres')->causedBy($request->user())->withProperties(collect($data)->except('logo')->all())->log('Paramètres modifiés');

        return back()->with('success', 'Paramètres enregistrés.');
    }

    public function storeRate(Request $request): RedirectResponse
    {
        $data = $request->validate(['rate' => ['required', 'numeric', 'gt:0', 'max:10000'], 'effective_date' => ['required', 'date']]);
        ExchangeRate::create($data + ['currency' => 'USD', 'user_id' => $request->user()->id]);

        return back()->with('success', 'Taux du dollar mis à jour : 1 USD = '.$data['rate'].' HTG.');
    }

    public function storeRegister(Request $request): RedirectResponse
    {
        CashRegister::create($request->validate(['name' => ['required', 'string', 'max:50', 'unique:cash_registers,name']]) + ['is_active' => true]);

        return back()->with('success', 'Caisse ajoutée.');
    }

    public function toggleRegister(CashRegister $register): RedirectResponse
    {
        if ($register->is_active && $register->openSession()) {
            return back()->with('error', 'Cette caisse est ouverte : fermez-la d\'abord.');
        }
        $register->update(['is_active' => ! $register->is_active]);

        return back()->with('success', 'Caisse '.($register->is_active ? 'activée' : 'désactivée').'.');
    }

    public function activity(Request $request): View
    {
        return view('settings.activity', [
            'activities' => Activity::with('causer', 'subject')
                ->when($request->query('journal'), fn ($q, $l) => $q->where('log_name', $l))
                ->latest()->paginate(40)->withQueryString(),
            'logs' => Activity::query()->distinct()->pluck('log_name')->filter()->values(),
        ]);
    }
}
