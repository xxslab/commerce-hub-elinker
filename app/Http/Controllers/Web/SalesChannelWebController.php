<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\SyncSalesChannelOrdersJob;
use App\Models\SalesChannel;
use App\Services\Integrations\SalesChannelConnectorResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SalesChannelWebController extends Controller
{
    public function index()
    {
        return view('sales_channels.index', [
            'channels' => SalesChannel::where('company_id', $this->company()->id)->latest()->paginate(25),
        ]);
    }

    public function createWooCommerce() { return view('sales_channels.create_woocommerce', ['company' => $this->company()]); }

    public function edit(SalesChannel $salesChannel)
    {
        abort_unless($salesChannel->company_id === $this->company()->id, 404);

        return view('sales_channels.edit', compact('salesChannel'));
    }

    public function update(Request $request, SalesChannel $salesChannel)
    {
        abort_unless($salesChannel->company_id === $this->company()->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $salesChannel->forceFill(['name' => $data['name']])->save();

        return redirect()->route('channels.index')->with('ok', 'Nazwa kanału została zmieniona.');
    }

    public function storeWooCommerce(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_url' => ['required', 'url', 'starts_with:https://'],
            'consumer_key' => ['required', 'string'],
            'consumer_secret' => ['required', 'string'],
        ]);

        $channel = new SalesChannel([
            'company_id' => $this->company()->id,
            'type' => SalesChannel::TYPE_WOOCOMMERCE,
            'name' => $data['name'],
            'base_url' => rtrim($data['base_url'], '/'),
            'status' => 'active',
            'is_enabled' => true,
            'sync_status' => 'idle',
        ]);
        $channel->setCredentials(['consumer_key' => $data['consumer_key'], 'consumer_secret' => $data['consumer_secret']]);
        $channel->setWebhookSecret(Str::random(40));
        $channel->save();
        return redirect()->route('channels.index')->with('ok', 'Kanał WooCommerce dodany. Uruchom test połączenia i skonfiguruj webhook (adres poniżej listy kanałów).');
    }

    public function test(SalesChannel $salesChannel)
    {
        abort_unless($salesChannel->company_id === $this->company()->id, 404);
        try {
            $result = app(SalesChannelConnectorResolver::class)->for($salesChannel)->testConnection();
            if (($result['ok'] ?? false) === true) {
                $salesChannel->forceFill([
                    'sync_status' => 'idle',
                    'last_error' => null,
                    'last_error_code' => null,
                    'consecutive_failures' => 0,
                ])->save();
            }
            return back()->with(($result['ok'] ?? false) ? 'ok' : 'error', ($result['ok'] ?? false) ? 'Połączenie działa.' : ($result['message'] ?? 'Połączenie nie działa. Sprawdź konfigurację.'));
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Test połączenia nie powiódł się. Sprawdź URL, uprawnienia API i logi.');
        }
    }

    public function sync(SalesChannel $salesChannel)
    {
        abort_unless($salesChannel->company_id === $this->company()->id, 404);
        if ($salesChannel->sync_status === 'authentication_error' && ! request()->boolean('force')) {
            return back()->with('error', 'Kanał ma błąd autoryzacji. Popraw dane i wymuś test połączenia przed synchronizacją.');
        }
        SyncSalesChannelOrdersJob::dispatch($salesChannel->id);
        return back()->with('ok', 'Synchronizacja została dodana do kolejki.');
    }

    public function destroy(SalesChannel $salesChannel)
    {
        abort_unless($salesChannel->company_id === $this->company()->id, 404);
        $salesChannel->delete();
        return redirect()->route('channels.index')->with('ok', 'Kanał został usunięty.');
    }

    public function toggle(SalesChannel $salesChannel)
    {
        abort_unless($salesChannel->company_id === $this->company()->id, 404);
        $salesChannel->forceFill(['is_enabled' => ! $salesChannel->is_enabled, 'sync_status' => $salesChannel->is_enabled ? 'disabled' : 'idle'])->save();
        return back()->with('ok', $salesChannel->is_enabled ? 'Automatyczna synchronizacja włączona.' : 'Automatyczna synchronizacja zatrzymana.');
    }
}
