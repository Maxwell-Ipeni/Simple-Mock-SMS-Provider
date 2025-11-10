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
                    // set a reasonable timeout to avoid long hangs when remote callbacks are slow
                    $resp = Http::timeout(6)->post($callback, $payload);
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

        // try to extract a 'from' field from common locations
        $from = null;
        if (isset($payload['from'])) {
            $from = $payload['from'];
        } elseif (isset($payload['sender'])) {
            $from = $payload['sender'];
        } elseif (isset($payload['msisdn'])) {
            $from = $payload['msisdn'];
        }

        // Normalize phone numbers: keep +prefix when possible
        if ($from && is_string($from)) {
            $digits = preg_replace('/[^0-9+]/', '', $from);
            if (strpos($digits, '+') !== 0) {
                if (preg_match('/^\d{7,15}$/', $digits)) {
                    $digits = '+' . $digits;
                }
            }
            $from = $digits;
        }

        $entry = [
            'type' => 'incoming',
            'from' => $from,
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

    // Server-side test: perform a GET to an external poll endpoint and return the response
    public function testPoll(Request $request)
    {
        $url = $request->input('url');
        if (!$url) {
            return response()->json(['success' => false, 'error' => 'Missing url'], 400);
        }

        try {
            // set a short timeout to avoid blocking the UI for too long
            $resp = Http::timeout(6)->get($url);
            $status = $resp->status();
            $body = null;
            try { $body = $resp->json(); } catch (\Throwable $t) { $body = $resp->body(); }

            // load inbound template (if any) so we can map incoming items into the user's inbound JSON shape
            $templates = Cache::get('sms_templates', ['inbound' => '', 'outbound' => '']);
            $inboundTpl = is_array($templates) && isset($templates['inbound']) ? $templates['inbound'] : (is_string($templates) ? $templates : '');
    
            return response()->json(['success' => true, 'status' => $status, 'body' => $body]);
        } catch (\Exception $e) {
            Log::error('testPoll error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // Poll an external GET endpoint and save any returned messages into the cache as incoming entries
    public function pollFetch(Request $request)
    {
        $url = $request->input('url');
        if (!$url) {
            return response()->json(['success' => false, 'error' => 'Missing url'], 400);
        }

        try {
            // set a short timeout for polling external endpoints
            $resp = Http::timeout(6)->get($url);
            $status = $resp->status();
            $body = null;
            try { $body = $resp->json(); } catch (\Throwable $t) { $body = $resp->body(); }

            // load inbound template (if any) so we can map incoming items into the user's inbound JSON shape
            $templates = Cache::get('sms_templates', ['inbound' => '', 'outbound' => '']);
            $inboundTpl = is_array($templates) && isset($templates['inbound']) ? $templates['inbound'] : (is_string($templates) ? $templates : '');

            $saved = [];
            // normalize single object or array
            if (is_array($body)) {
                // if associative (single message object), treat as one
                $isAssoc = array_keys($body) !== range(0, count($body) - 1);
                $items = $isAssoc ? [$body] : $body;
            } else {
                // body is a string - create a single message
                $items = [['message' => (string)$body]];
            }

            foreach ($items as $item) {
                $payload = is_array($item) ? $item : ['message' => (string)$item];

                // If an inbound template exists, attempt to apply it. Replace {{message}} with the
                // extracted text and, if the result is JSON, use the parsed object as the payload.
                if (!empty($inboundTpl) && is_string($inboundTpl) && trim($inboundTpl) !== '') {
                    // determine a simple text to substitute
                    $text = null;
                    if (is_array($payload) && isset($payload['message'])) {
                        $text = $payload['message'];
                    } elseif (is_array($payload) && isset($payload['text'])) {
                        $text = $payload['text'];
                    } else {
                        $text = is_string($item) ? $item : json_encode($payload);
                    }

                    // perform replacement for {{message}} (allow whitespace inside braces)
                    $built = preg_replace('/\{\{\s*message\s*\}\}/i', $text, $inboundTpl);
                    if ($built !== null) {
                        $decoded = json_decode($built, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $payload = $decoded;
                        } else {
                            // keep original payload if template didn't produce valid JSON
                        }
                    }
                }

                // attempt to extract a 'from' field similar to submit()
                $from = null;
                if (isset($payload['from'])) {
                    $from = $payload['from'];
                } elseif (isset($payload['sender'])) {
                    $from = $payload['sender'];
                } elseif (isset($payload['msisdn'])) {
                    $from = $payload['msisdn'];
                }

                // normalize phone if looks like digits, ensure leading + if country code present
                if ($from && is_string($from)) {
                    $digits = preg_replace('/[^0-9+]/', '', $from);
                    if (strpos($digits, '+') !== 0) {
                        // if starts with country code like 254 or 1 and length plausible, prefix +
                        if (preg_match('/^\d{7,15}$/', $digits)) {
                            $digits = '+' . $digits;
                        }
                    }
                    $from = $digits;
                }

                $text = isset($payload['message']) ? $payload['message'] : json_encode($payload);

                $entry = [
                    'type' => 'incoming',
                    'from' => $from,
                    'message' => $text,
                    'payload' => $payload,
                    'created_at' => now()->toDateTimeString(),
                ];

                $messages = Cache::get($this->cacheKey, []);
                $messages[] = $entry;
                Cache::put($this->cacheKey, $messages, now()->addDays(7));
                $saved[] = $entry;
            }

            return response()->json(['success' => true, 'status' => $status, 'saved' => $saved]);
        } catch (\Exception $e) {
            Log::error('pollFetch error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // Server-Sent Events stream for cache updates. Streams any new messages added to the cache.
    public function cacheStream(Request $request)
    {
        // allow long-running
        set_time_limit(0);
        ignore_user_abort(true);

        $cacheKey = $this->cacheKey;

        return response()->stream(function () use ($cacheKey) {
            $lastCount = count(Cache::get($cacheKey, []));
            // initial ping
            echo "event: ping\n";
            echo "data: {}\n\n";
            ob_flush(); flush();

            while (!connection_aborted()) {
                $messages = Cache::get($cacheKey, []);
                $count = is_array($messages) ? count($messages) : 0;
                if ($count > $lastCount) {
                    $new = array_slice($messages, $lastCount);
                    foreach ($new as $m) {
                        echo "event: new_message\n";
                        echo 'data: ' . json_encode($m) . "\n\n";
                        ob_flush(); flush();
                    }
                    $lastCount = $count;
                }
                // brief sleep to avoid tight loop
                sleep(1);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }

    // Server-side test: perform a POST to an external send endpoint and return the response
    public function testSend(Request $request)
    {
        $url = $request->input('url');
        $payload = $request->input('payload', []);
        $headers = $request->input('headers', []);

        if (!$url) {
            return response()->json(['success' => false, 'error' => 'Missing url'], 400);
        }

        try {
            // set a short timeout for external POSTs
            $client = Http::withHeaders(is_array($headers) ? $headers : [])->timeout(6);
            $resp = $client->post($url, $payload);
            $status = $resp->status();
            $body = null;
            try { $body = $resp->json(); } catch (\Throwable $t) { $body = $resp->body(); }
            return response()->json(['success' => true, 'status' => $status, 'response' => $body]);
        } catch (\Exception $e) {
            Log::error('testSend error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // Server-side send-reply: perform POST to external endpoint with headers and store outgoing entry in cache
    public function sendReply(Request $request)
    {
        $url = $request->input('url') ?: $request->input('callback');
        $payload = $request->input('payload', []);
        $headers = $request->input('headers', []);

        if (!$url) {
            return response()->json(['success' => false, 'error' => 'Missing url'], 400);
        }

        $callbackResponse = null;
        $status = null;

        // If the target URL points back to this app, dispatch internally to avoid deadlocks
        $appUrl = config('app.url', url('/'));
        $isLocalCallback = false;
        try {
            $callbackHost = parse_url($url, PHP_URL_HOST);
            $appHost = parse_url($appUrl, PHP_URL_HOST);
            if ($callbackHost && $appHost && $callbackHost === $appHost) {
                $isLocalCallback = true;
            }
        } catch (\Throwable $t) {
            // ignore
        }
        if (!$isLocalCallback && Str::startsWith($url, $appUrl)) {
            $isLocalCallback = true;
        }

        if ($isLocalCallback) {
            try {
                Log::info('Dispatching internal send-reply', ['url' => $url, 'payload' => $payload]);
                $path = parse_url($url, PHP_URL_PATH) ?: '/';
                $internalReq = Request::create($path, 'POST', $payload);
                $resp = app()->handle($internalReq);
                $status = $resp->getStatusCode();
                $body = $resp->getContent();
                $decoded = json_decode($body, true);
                $callbackResponse = $decoded ?? $body;
            } catch (\Exception $e) {
                $status = 0;
                $callbackResponse = $e->getMessage();
                Log::error('Internal sendReply error: ' . $e->getMessage(), ['exception' => $e]);
            }
        } else {
            try {
                // set a short timeout for reply POSTs
                $client = Http::withHeaders(is_array($headers) ? $headers : [])->timeout(6);
                $resp = $client->post($url, $payload);
                $status = $resp->status();
                try { $callbackResponse = $resp->json(); } catch (\Throwable $t) { $callbackResponse = $resp->body(); }
            } catch (\Exception $e) {
                $status = 0;
                $callbackResponse = $e->getMessage();
                Log::error('sendReply error: ' . $e->getMessage(), ['exception' => $e]);
            }
        }

        // sanitize the callback response so the UI doesn't get back large HTML pages
        $sanitizedCallback = $this->sanitizeCallbackResponse($callbackResponse);

        $entry = [
            'type' => 'outgoing',
            'message' => is_array($payload) && isset($payload['message']) ? $payload['message'] : (is_string($payload) ? $payload : json_encode($payload)),
            'callback' => $url,
            'payload' => $payload,
            'status' => $status,
            'callback_response' => $sanitizedCallback,
            'created_at' => now()->toDateTimeString(),
        ];

        $messages = Cache::get($this->cacheKey, []);
        $messages[] = $entry;
        Cache::put($this->cacheKey, $messages, now()->addDays(7));

        return response()->json([
            'success' => true,
            'message' => 'Reply sent',
            'data' => $entry,
            'callback_status' => $status,
            'callback_response' => $sanitizedCallback,
        ]);
    }

    /**
     * Sanitize callback responses returned from external endpoints.
     * - If the response is JSON, return the decoded array/object
     * - If it's a string (HTML or text), strip tags, collapse whitespace and truncate to a reasonable length
     */
    protected function sanitizeCallbackResponse($resp)
    {
        if (is_array($resp) || is_object($resp)) {
            return $resp;
        }

        if ($resp === null) return null;

        // try json decode if resp is a string
        if (is_string($resp)) {
            $trimmed = trim($resp);
            $decoded = json_decode($trimmed, true);
            if ($decoded !== null) return $decoded;

            // strip HTML tags and excessive whitespace
            $noHtml = preg_replace('/\s+/', ' ', strip_tags($trimmed));
            $noHtml = html_entity_decode($noHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $max = 800;
            if (mb_strlen($noHtml) > $max) {
                return mb_substr($noHtml, 0, $max) . '... (truncated)';
            }
            return $noHtml;
        }

        // fallback: cast to string
        return (string)$resp;
    }

    // Templates storage (simple cache-backed): save and get
    public function saveTemplate(Request $request)
    {
        $type = $request->input('type', 'outbound'); // inbound or outbound
        $template = $request->input('template', '');
        $templates = Cache::get('sms_templates', ['inbound' => '', 'outbound' => '']);
        $templates[$type] = $template;
        Cache::put('sms_templates', $templates, now()->addDays(30));
        return response()->json(['success' => true, 'templates' => $templates]);
    }

    public function getTemplates()
    {
        $templates = Cache::get('sms_templates', ['inbound' => '', 'outbound' => '']);
        return response()->json($templates);
    }

    // Save/get UI settings (endpoints, headers, user id) to cache so the app can persist context server-side
    public function saveSettings(Request $request)
    {
        $settings = Cache::get('sms_settings', [
            'poll_endpoint' => '',
            'reply_endpoint' => '',
            'header_key' => '',
            'header_value' => '',
            'user_id' => '',
        ]);

        $payload = $request->only(['poll_endpoint', 'reply_endpoint', 'header_key', 'header_value', 'user_id']);
        $settings = array_merge($settings, array_filter($payload, function($v){ return $v !== null; }));
        Cache::put('sms_settings', $settings, now()->addDays(30));

        return response()->json(['success' => true, 'settings' => $settings]);
    }

    public function getSettings()
    {
        $settings = Cache::get('sms_settings', [
            'poll_endpoint' => '',
            'reply_endpoint' => '',
            'header_key' => '',
            'header_value' => '',
            'user_id' => '',
        ]);
        return response()->json($settings);
    }
}
