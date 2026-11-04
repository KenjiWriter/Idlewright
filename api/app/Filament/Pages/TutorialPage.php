<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Content\Definitions\TutorialForm;
use App\Content\ImageRefused;
use App\Filament\Forms\TutorialFields;
use App\Services\ContentImages;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * The tutorial of the game: the device the mentor writes on, the words of the tutorial, the story before the choice of a wreck, the steps with the messages of the
 * mentor and what the driver is given, and the arrangements of the first run. A picture that is chosen (the icon of the button, the frame of a device of the game's
 * own, the picture of the story) is kept in the store of images when the draft is saved, and the draft names it.
 */
class TutorialPage extends RulesDocumentPage
{
    protected static ?string $slug = 'tutorial';

    protected static ?int $navigationSort = 20;

    /** The pictures of the form: each is a field that names the picture, with a box for a new file and a switch that takes it off. */
    private const PICTURES = ['icon', 'frame', 'story_picture'];

    public static function getNavigationLabel(): string
    {
        return __('admin.tutorial.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.tutorial.title');
    }

    protected function documentSlug(): string
    {
        return 'tutorial';
    }

    protected function fields(array $document): array
    {
        return TutorialFields::schema($document);
    }

    protected function toState(array $document): array
    {
        return TutorialForm::toState($document);
    }

    protected function toDocument(array $document, array $state): array
    {
        return TutorialForm::apply($document, $this->withPictures($state));
    }

    /** A picture that cannot be kept stops the saving after it is said, and nothing is kept. */
    public function save(): void
    {
        try {
            parent::save();
        } catch (Halt) {
            // Said already.
        }
    }

    /**
     * The state of the form with the pictures in it: a file that was chosen is kept and its name is the picture, a picture that is taken off is none, and the boxes
     * of the form that are not a part of the document are gone. A picture whose fields the form does not show (the frame of a device that is not the game's own)
     * stays out of the state, so that the document keeps it.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function withPictures(array $state): array
    {
        foreach (self::PICTURES as $field) {
            if (! array_key_exists($field, $state) && ! array_key_exists("{$field}_upload", $state)) {
                continue;
            }

            $upload = $state["{$field}_upload"] ?? null;
            $upload = is_array($upload) ? Arr::first($upload) : $upload;

            if (! empty($state["{$field}_remove"])) {
                $state[$field] = null;
            }

            unset($state["{$field}_upload"], $state["{$field}_remove"]);

            $bytes = match (true) {
                $upload instanceof TemporaryUploadedFile => $upload->get(),
                $upload instanceof UploadedFile => (string) file_get_contents($upload->getRealPath()),
                default => null,
            };

            if ($bytes === null) {
                continue;
            }

            try {
                $state[$field] = app(ContentImages::class)->store((string) $bytes);
            } catch (ImageRefused $refusal) {
                Notification::make()->danger()->title(__("admin.definitions.image_refused.{$refusal->reason}", $refusal->params))->persistent()->send();

                $this->halt();
            }
        }

        return $state;
    }
}
