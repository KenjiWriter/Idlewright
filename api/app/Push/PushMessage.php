<?php

declare(strict_types=1);

namespace App\Push;

/**
 * One push message: what the notification says (in the language of the driver, written by the server so that the service worker needs no texts), where a tap leads, how long
 * the push service keeps it and how urgent it is. A `tag` makes a newer message of the same thing take the place of the older one that the device has not shown yet.
 */
final readonly class PushMessage
{
    public function __construct(
        public string $kind,
        public string $title,
        public string $body,
        public string $url,
        public string $tag,
        public int $ttl,
        public string $urgency = 'normal',
        public string $lang = 'en',
        public ?string $nid = null,
    ) {}

    /** What the service worker receives: the JSON of the notification. */
    public function content(): string
    {
        return json_encode(
            ['kind' => $this->kind, 'title' => $this->title, 'body' => $this->body, 'url' => $this->url, 'tag' => $this->tag, 'lang' => $this->lang, 'nid' => $this->nid],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    /** The `Topic` header (RFC 8030): a message with the same topic replaces one that waits at the push service. At most 32 characters of base64url. */
    public function topic(): string
    {
        return substr(Base64Url::encode(hash('sha256', $this->tag, true)), 0, 32);
    }

    /** @return array{kind: string, title: string, body: string, url: string, tag: string, ttl: int, urgency: string, lang: string, nid?: string|null} for the queue */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'tag' => $this->tag,
            'ttl' => $this->ttl,
            'urgency' => $this->urgency,
            'lang' => $this->lang,
            'nid' => $this->nid,
        ];
    }

    /** @param  array{kind: string, title: string, body: string, url: string, tag: string, ttl: int, urgency: string, lang: string, nid?: string|null}  $data */
    public static function fromArray(array $data): self
    {
        return new self($data['kind'], $data['title'], $data['body'], $data['url'], $data['tag'], $data['ttl'], $data['urgency'], $data['lang'], $data['nid'] ?? null);
    }
}
