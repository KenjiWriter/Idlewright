<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Content\TutorialSteps;
use App\Services\ContentImages;
use App\Support\I18nText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The story that comes between the name of a new driver and the choice of a wreck, in the language of the driver: whether the game tells one, its picture, its
 * scenes (the words still with {name} in them, which the app fills in), the first message and who it is from (the sender of the messenger when the story names
 * none) and what it is written on. A tutorial that says nothing of a story is `builtin`: the app tells the story of RideIdle with its own words and picture, and
 * `sender` is the sender of the messenger, or null for the number of the app.
 *
 * @property-read TutorialSteps $resource
 */
final class TutorialStoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $story = $this->resource->story();
        $messenger = TutorialResource::messenger($this->resource->messenger());
        // The first message comes from the sender of the story, and from the one of the messenger when the story names none (also in the story of the app).
        $deviceSender = $this->resource->messenger()['sender'] ?? null;

        if ($story === null) {
            return ['enabled' => true, 'builtin' => true, 'picture_url' => null, 'scenes' => [], 'sender' => self::text($deviceSender), 'sms' => null, 'messenger' => $messenger];
        }

        $picture = $story['picture'] ?? null;

        return [
            'enabled' => (bool) ($story['enabled'] ?? false),
            'builtin' => false,
            'picture_url' => is_string($picture) && preg_match(ContentImages::NAME, $picture) === 1 ? ContentImages::url($picture) : null,
            'scenes' => array_map(static fn (array $scene): array => [
                'text' => I18nText::from($scene['text'] ?? null)->for(),
                'ms' => (int) ($scene['ms'] ?? 0),
                'camera' => (string) ($scene['camera'] ?? 'still'),
                'focus' => ['x' => (int) ($scene['focus']['x'] ?? 50), 'y' => (int) ($scene['focus']['y'] ?? 50)],
            ], array_values((array) ($story['scenes'] ?? []))),
            'sender' => self::text($story['sender'] ?? $deviceSender),
            'sms' => isset($story['sms']) ? I18nText::from($story['sms'])->for() : null,
            'messenger' => $messenger,
        ];
    }

    /** @param  array<string, string>|string|null  $text */
    private static function text(array|string|null $text): ?string
    {
        return $text === null ? null : I18nText::from($text)->for();
    }
}
