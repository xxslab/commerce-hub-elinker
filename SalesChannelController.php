<?php

namespace App\Http\Controllers;

use App\Jobs\SyncSalesChannelOrdersJob;
use App\Models\SalesChannel;
use App\Services\Integrations\WooCommerce\WooCommerceClient;
use Illuminate\Http\Request;

class SalesChannelController extends Controller
{
    public function index()
    {
        return SalesChannel::query()
            ->where('company_id', $this->company()->id)
            ->latest()
            ->paginate(25);
    }

    public function storeWooCommerce(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_url' => ['required', 'url'],
            'consumer_key' => ['required', 'string'],
            'consumer_secret' => ['required', 'string'],
        ]);

        $channel = new SalesChannel([
            'company_id' => $this->company()->id,
            'type' => SalesChannel::TYPE_WOOCOMMERCE,
            'name' => $data['name'],
            'base_url' => rtrim($data['base_url'], '/'),
            'status' => 'active',
        ]);
        $channel->setCredentials([
            'consumer_key' => $data['consumer_key'],
            'consumer_secret' => $data['consumer_secret'],
        ]);
        $channel->save();

        return response()->json($channel, 201);
    }

    public function test(SalesChannel $salesChannel)
    {
        abort_unless($salesChannel->company_id === $this->company()->id, 404);

        if ($salesChannel->type !== SalesChannel::TYPE_WOOCOMMERCE) {
            return response()->json(['message' => 'Test implemented for WooCommerce first.'], 422);
        }

        return response()->json([
            'ok' => app(WooCommerceClient::class, ['channel' => $salesChannel])->testConnection(),
        ]);
    }

    public function sync(SalesChannel $salesChannel)
    {
        abort_unless($salesChannel->company_id === $this->company()->id, 404);

        SyncSalesChannelOrdersJob::dispatch($salesChannel->id);

        return response()->json(['queued' => true]);
    }
}
