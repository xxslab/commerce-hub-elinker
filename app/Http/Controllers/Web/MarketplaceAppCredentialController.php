<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceAppCredential;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketplaceAppCredentialController extends Controller
{
    public function index()
    {
        $company = $this->company();
        $credentials = MarketplaceAppCredential::query()
            ->where('company_id', $company->id)
            ->orderBy('marketplace')
            ->orderByDesc('id')
            ->get();

        return view('marketplace_apps.index', compact('credentials'));
    }

    public function create(string $marketplace)
    {
        abort_unless(in_array($marketplace, [MarketplaceAppCredential::MARKETPLACE_ALLEGRO, MarketplaceAppCredential::MARKETPLACE_EBAY], true), 404);

        $redirectUri = url("/integrations/{$marketplace}/callback");
        return view('marketplace_apps.form', [
            'credential' => new MarketplaceAppCredential([
                'marketplace' => $marketplace,
                'environment' => 'production',
                'redirect_uri' => $redirectUri,
                'is_active' => true,
            ]),
            'marketplace' => $marketplace,
            'mode' => 'create',
        ]);
    }

    public function store(Request $request, string $marketplace)
    {
        abort_unless(in_array($marketplace, [MarketplaceAppCredential::MARKETPLACE_ALLEGRO, MarketplaceAppCredential::MARKETPLACE_EBAY], true), 404);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'environment' => ['required', Rule::in(['production', 'sandbox'])],
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['required', 'string', 'max:2000'],
            'redirect_uri' => ['required', 'url', 'max:500'],
            'scopes' => ['nullable', 'string', 'max:4000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $credential = new MarketplaceAppCredential();
        $credential->fill([
            'company_id' => $this->company()->id,
            'marketplace' => $marketplace,
            'environment' => $data['environment'],
            'name' => $data['name'] ?? strtoupper($marketplace) . ' app',
            'client_id' => $data['client_id'],
            'redirect_uri' => $data['redirect_uri'],
            'scopes' => $data['scopes'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
        $credential->setClientSecret($data['client_secret']);
        $credential->save();

        if ($credential->is_active) {
            MarketplaceAppCredential::query()
                ->where('company_id', $credential->company_id)
                ->where('marketplace', $credential->marketplace)
                ->where('id', '!=', $credential->id)
                ->update(['is_active' => false]);
        }

        return redirect()->route('marketplace-apps.index', ['marketplace' => $marketplace])->with('ok', 'Dane aplikacji zapisane. Teraz możesz połączyć konto sprzedawcy.');
    }

    public function edit(MarketplaceAppCredential $credential)
    {
        abort_unless($credential->company_id === $this->company()->id, 404);

        return view('marketplace_apps.form', [
            'credential' => $credential,
            'marketplace' => $credential->marketplace,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, MarketplaceAppCredential $credential)
    {
        abort_unless($credential->company_id === $this->company()->id, 404);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'environment' => ['required', Rule::in(['production', 'sandbox'])],
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:2000'],
            'redirect_uri' => ['required', 'url', 'max:500'],
            'scopes' => ['nullable', 'string', 'max:4000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $credential->fill([
            'environment' => $data['environment'],
            'name' => $data['name'] ?? $credential->name,
            'client_id' => $data['client_id'],
            'redirect_uri' => $data['redirect_uri'],
            'scopes' => $data['scopes'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        if (!empty($data['client_secret'])) {
            $credential->setClientSecret($data['client_secret']);
        }

        $credential->save();

        if ($credential->is_active) {
            MarketplaceAppCredential::query()
                ->where('company_id', $credential->company_id)
                ->where('marketplace', $credential->marketplace)
                ->where('id', '!=', $credential->id)
                ->update(['is_active' => false]);
        }

        return redirect()->route('marketplace-apps.index', ['marketplace' => $credential->marketplace])->with('ok', 'Dane aplikacji zaktualizowane.');
    }

}
