<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Broadcasting\Broadcasters\Broadcaster;

/**
 * Test broadcaster that records every broadcast call and evaluates
 * channel authorization with the same semantics as a real broadcaster
 * (this framework's Null/Log broadcasters no-op `auth()`, so the
 * /broadcasting/auth endpoint cannot be exercised against them).
 */
final class RecordingBroadcaster extends Broadcaster
{
    /** @var list<array{channels: list<string>, event: string, payload: array<string, mixed>}> */
    public array $events = [];

    public function broadcast(array $channels, $event, array $payload = []): void
    {
        $this->events[] = [
            'channels' => array_map(static fn ($channel): string => (string) $channel, $channels),
            'event' => (string) $event,
            'payload' => $payload,
        ];
    }

    public function auth($request)
    {
        // Mirrors PusherBroadcaster::normalizeChannelName: patterns in
        // channels.php are registered without the private-/presence- prefix,
        // and non-guarded (public) channels are still callback-checked.
        $raw = (string) $request->input('channel_name', '');
        $channel = str_replace(['private-', 'presence-'], '', $raw);

        return $this->verifyUserCanAccessChannel($request, $channel);
    }

    public function validAuthenticationResponse($request, $result)
    {
        return json_encode(['auth' => 'recording-auth']);
    }
}
