<?php

namespace App\Http\Controllers;

use App\Models\PayMongoTestCheckout;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PayMongoTestCheckoutController extends Controller
{
    private const TEST_AMOUNT_CENTAVOS = 10000;

    public function index(): View
    {
        $this->ensureLocalEnvironment();

        return view('paymongo.test-checkout', [
            'isConfigured' => $this->hasValidTestConfiguration(),
            'result' => null,
        ]);
    }

    public function store(): RedirectResponse
    {
        $this->ensureLocalEnvironment();

        $secretKey = config('services.paymongo.secret');
        $baseUrl = config('services.paymongo.base_url');

        if (! is_string($secretKey) || ! is_string($baseUrl) || ! $this->hasValidTestConfiguration()) {
            return back()->withErrors([
                'checkout' => 'PayMongo test credentials are not configured. Add a test-only key to the local environment and clear the configuration cache.',
            ]);
        }

        $referenceNumber = 'DENTALCARE-TEST-'.Str::uuid();

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withBasicAuth($secretKey, '')
                ->connectTimeout(5)
                ->timeout(15)
                ->post(rtrim($baseUrl, '/').'/checkout_sessions', [
                    'data' => [
                        'attributes' => [
                            'line_items' => [
                                [
                                    'name' => config('clinic.name').' Test Checkout',
                                    'amount' => self::TEST_AMOUNT_CENTAVOS,
                                    'currency' => 'PHP',
                                    'quantity' => 1,
                                ],
                            ],
                            'payment_method_types' => ['gcash'],
                            'success_url' => route('paymongo.test.success'),
                            'cancel_url' => route('paymongo.test.cancelled'),
                            'reference_number' => $referenceNumber,
                        ],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            $errorCategory = $this->connectionErrorCategory($exception);

            Log::warning('PayMongo test checkout could not connect to the API.', [
                'error_category' => $errorCategory,
            ]);

            return back()->withErrors([
                'checkout' => 'PayMongo could not be reached. Check the local application logs and try again.',
            ])->with('checkout_diagnostic', $errorCategory);
        }

        if (! $response->successful()) {
            Log::warning('PayMongo test checkout returned an unsuccessful response.', [
                'status' => $response->status(),
            ]);

            return back()->withErrors([
                'checkout' => 'PayMongo could not start the test checkout. Check the local application logs and try again.',
            ]);
        }

        $checkoutUrl = $response->json('data.attributes.checkout_url');
        $checkoutSessionId = $response->json('data.id');

        if (! is_string($checkoutUrl)
            || ! $this->isPayMongoCheckoutUrl($checkoutUrl)
            || ! is_string($checkoutSessionId)
            || ! str_starts_with($checkoutSessionId, 'cs_')) {
            Log::warning('PayMongo test checkout returned an invalid checkout URL.', [
                'status' => $response->status(),
            ]);

            return back()->withErrors([
                'checkout' => 'PayMongo returned an invalid test checkout response. Check the local application logs and try again.',
            ]);
        }

        PayMongoTestCheckout::query()->create([
            'reference_number' => $referenceNumber,
            'checkout_session_id' => $checkoutSessionId,
        ]);

        return redirect()->away($checkoutUrl);
    }

    public function success(): View
    {
        $this->ensureLocalEnvironment();

        return view('paymongo.test-checkout', [
            'isConfigured' => $this->hasValidTestConfiguration(),
            'result' => 'success',
        ]);
    }

    public function cancelled(): View
    {
        $this->ensureLocalEnvironment();

        return view('paymongo.test-checkout', [
            'isConfigured' => $this->hasValidTestConfiguration(),
            'result' => 'cancelled',
        ]);
    }

    private function ensureLocalEnvironment(): void
    {
        abort_unless(
            app()->isLocal() || (app()->environment('testing') && app()->runningUnitTests()),
            404,
        );
    }

    private function hasValidTestConfiguration(): bool
    {
        $secretKey = config('services.paymongo.secret');
        $baseUrl = config('services.paymongo.base_url');

        if (! is_string($secretKey) || ! str_starts_with($secretKey, 'sk_test_') || ! is_string($baseUrl)) {
            return false;
        }

        $urlParts = parse_url($baseUrl);

        return is_array($urlParts)
            && ($urlParts['scheme'] ?? null) === 'https'
            && ($urlParts['host'] ?? null) === 'api.paymongo.com'
            && rtrim($urlParts['path'] ?? '', '/') === '/v2'
            && ! isset($urlParts['user'])
            && ! isset($urlParts['pass'])
            && ! isset($urlParts['query'])
            && ! isset($urlParts['fragment'])
            && ! isset($urlParts['port']);
    }

    private function isPayMongoCheckoutUrl(string $checkoutUrl): bool
    {
        $urlParts = parse_url($checkoutUrl);

        return filter_var($checkoutUrl, FILTER_VALIDATE_URL) !== false
            && is_array($urlParts)
            && ($urlParts['scheme'] ?? null) === 'https'
            && ($urlParts['host'] ?? null) === 'checkout.paymongo.com'
            && ! isset($urlParts['user'])
            && ! isset($urlParts['pass'])
            && ! isset($urlParts['port']);
    }

    private function connectionErrorCategory(ConnectionException $exception): string
    {
        if (preg_match('/cURL error (\d+)/i', $exception->getMessage(), $matches) !== 1) {
            return 'connection_exception';
        }

        return match ((int) $matches[1]) {
            6 => 'dns_resolution',
            7 => 'connection_failed',
            28 => 'connection_timeout',
            35 => 'tls_handshake',
            60 => 'tls_certificate_validation',
            default => 'curl_error_'.$matches[1],
        };
    }
}
