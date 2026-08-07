<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SalesChannel;
use App\Services\Integrations\Allegro\AllegroOAuthService;
use App\Services\Integrations\Ebay\EbayOAuthService;
use Illuminate\Http\Request;

class MarketplaceOAuthController extends Controller
{
    public function connectAllegro(AllegroOAuthService $oauth)
    {
        try { return redirect()->away($oauth->buildAuthorizationUrl($this->company())); }
        catch (\Throwable $e) { report($e); return redirect()->route('marketplace-apps.index', ['marketplace' => 'allegro'])->with('error', 'Nie można rozpocząć połączenia Allegro.'); }
    }

    public function callbackAllegro(Request $request, AllegroOAuthService $oauth)
    {
        $request->validate(['code' => ['required', 'string'], 'state' => ['required', 'string']]);
        try { $channel = $oauth->handleCallback((string) $request->string('code'), (string) $request->string('state')); return redirect()->route('channels.index')->with('ok', 'Allegro połączone jako kanał: ' . $channel->name . '.'); }
        catch (\Throwable $e) { report($e); return redirect()->route('channels.index')->with('error', 'Błąd połączenia Allegro. Sprawdź konfigurację aplikacji i logi.'); }
    }

    public function connectEbay(EbayOAuthService $oauth)
    {
        try { return redirect()->away($oauth->buildAuthorizationUrl($this->company())); }
        catch (\Throwable $e) { report($e); return redirect()->route('marketplace-apps.index', ['marketplace' => 'ebay'])->with('error', 'Nie można rozpocząć połączenia eBay.'); }
    }

    public function callbackEbay(Request $request, EbayOAuthService $oauth)
    {
        $request->validate(['code' => ['required', 'string'], 'state' => ['required', 'string']]);
        try { $channel = $oauth->handleCallback((string) $request->string('code'), (string) $request->string('state')); return redirect()->route('channels.index')->with('ok', 'eBay połączony jako kanał: ' . $channel->name . '.'); }
        catch (\Throwable $e) { report($e); return redirect()->route('channels.index')->with('error', 'Błąd połączenia eBay. Sprawdź konfigurację aplikacji i logi.'); }
    }

    public function refreshToken(SalesChannel $salesChannel, AllegroOAuthService $allegro, EbayOAuthService $ebay)
    {
        abort_unless($salesChannel->company_id === $this->company()->id, 404);
        try {
            match ($salesChannel->type) {
                SalesChannel::TYPE_ALLEGRO => $allegro->refreshToken($salesChannel),
                SalesChannel::TYPE_EBAY => $ebay->refreshToken($salesChannel),
                default => throw new \RuntimeException('Nieobsługiwany typ tokena.'),
            };
            return back()->with('ok', 'Token odświeżony.');
        } catch (\Throwable $e) { report($e); return back()->with('error', 'Nie udało się odświeżyć tokena. Połącz konto ponownie.'); }
    }
}
