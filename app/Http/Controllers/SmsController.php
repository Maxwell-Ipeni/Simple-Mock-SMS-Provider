<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SmsController extends Controller
{
    protected $cacheKey = 'fake_sms_messages';

    // Default provider messages
    protected $templates = [
        'Hello, this is a test message.',
        'Hi, how can I help you?',
        "I'm just testing the fake SMS provider."
    ];

    // Store an outgoing message into cache (no callback)
    public function sendMessage(Request $request)
    {
        $payload = $request->input('payload', $request->all());

        $text = is_array($payload) && isset($payload['message']) ? $payload['message'] : (is_string($payload) ? $payload : json_encode($payload));

        $entry = [
            'type' => 'outgoing',
            'message' => $text,
            'payload' => $payload,
            'created_at' => now()->toDateTimeString(),
        ];

        $messages = Cache::get($this->cacheKey, []);
        $messages[] = $entry;
        Cache::put($this->cacheKey, $messages, now()->addDays(7));

        return response()->json([
            'success' => true,
            'message' => 'Message queued',
            'data' => $entry,
        ]);
    }

    // Simulate receiving/getting a message and post it to callback URL
    public function getMessage(Request $request)
    {
        // log the incoming request for debugging
        Log::info('getMessage called', ['request' => $request->all()]);

        $callback = $request->input('callback', url('/receive-sms'));

        // Providers can be dynamic (stored in cache or passed in request)
        $providers = Cache::get('fake_sms_providers', $this->templates);
        if ($request->has('providers') && is_array($request->input('providers'))) {
            $providers = $request->input('providers');
            Cache::put('fake_sms_providers', $providers, now()->addDays(7));
        }

        $message = $providers[array_rand($providers)];
        $payload = $request->input('payload', ['message' => $message]);

        // Post to callback and capture response
        $callbackResponse = null;
        $status = null;

        // If the callback points back to this application (same host or same app URL),
        // dispatch the request internally to avoid deadlocks when using the PHP built-in server.
        $appUrl = config('app.url', url('/'));
        $isLocalCallback = false;
        try {
            $callbackHost = parse_url($callback, PHP_URL_HOST);
            $appHost = parse_url($appUrl, PHP_URL_HOST);
            if ($callbackHost && $appHost && $callbackHost === $appHost) {
                $isLocalCallback = true;
            }
        } catch (\Throwable $t) {
            // ignore parse errors and fall back to string check
        }

        if (!$isLocalCallback && Str::startsWith($callback, $appUrl)) {
            $isLocalCallback = true;
        }

        if ($isLocalCallback) {
            try {
                Log::info('Dispatching internal callback', ['callback' => $callback, 'payload' => $payload]);
                $path = parse_url($callback, PHP_URL_PATH) ?: '/receive-sms';
                $internalReq = Request::create($path, 'POST', $payload);
                $resp = app()->handle($internalReq);
                $status = $resp->getStatusCode();
                $body = $resp->getContent();
                $decoded = json_decode($body, true);
                $callbackResponse = $decoded ?? $body;
                Log::info('Internal callback response', ['status' => $status, 'response' => $callbackResponse]);
            } catch (\Exception $e) {
                $status = 0;
                $callbackResponse = $e->getMessage();
                Log::error('Internal callback error: ' . $e->getMessage(), ['exception' => $e]);
            }
        } else {
            try {
                Log::info('Posting to callback', ['callback' => $callback, 'payload' => $payload]);
                $resp = Http::post($callback, $payload);
                $status = $resp->status();
                // try to decode json, fallback to raw body
                $callbackResponse = $resp->json() ?? $resp->body();
                Log::info('Callback response', ['status' => $status, 'response' => $callbackResponse]);
            } catch (\Exception $e) {
                $status = 0;
                $callbackResponse = $e->getMessage();
                Log::error('Callback error: ' . $e->getMessage(), ['exception' => $e]);
            }
        }

        $entry = [
            'type' => 'outbound_to_callback',
            'message' => $message,
            'callback' => $callback,
            'payload' => $payload,
            'status' => $status,
            'callback_response' => $callbackResponse,
            'created_at' => now()->toDateTimeString(),
        ];

        $messages = Cache::get($this->cacheKey, []);
        $messages[] = $entry;
        Cache::put($this->cacheKey, $messages, now()->addDays(7));

        return response()->json([
            'success' => true,
            'message' => 'Sent to callback',
            'data' => $entry,
            'callback_status' => $status,
            'callback_response' => $callbackResponse,
        ]);
    }

    // Endpoint to receive incoming messages/callbacks
    public function submit(Request $request)
    {
        $payload = $request->all();
        Log::info('submit called', ['payload' => $payload]);
        $text = isset($payload['message']) ? $payload['message'] : json_encode($payload);

        $entry = [
            'type' => 'incoming',
            'message' => $text,
            'payload' => $payload,
            'created_at' => now()->toDateTimeString(),
        ];

        $messages = Cache::get($this->cacheKey, []);
        $messages[] = $entry;
        Cache::put($this->cacheKey, $messages, now()->addDays(7));

        return response()->json([
            'success' => true,
            'message' => 'Received',
            'data' => $entry,
        ]);
    }

    // Return cached messages
    public function cacheWatch()
    {
        $messages = Cache::get($this->cacheKey, []);
        return response()->json($messages);
    }
}
