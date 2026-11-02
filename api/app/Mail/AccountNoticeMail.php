<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A notice to an address of the account about something done to it. It comes in three kinds: `change_asked` goes to the
 * current address when somebody asks to change it, `changed` goes to the address that was replaced, and `unlinked` goes to the
 * address of the account when a way of signing in was removed. The first two carry the link "this was not me", which secures
 * the account again (`url`); the third only informs.
 */
final class AccountNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    public const CHANGE_ASKED = 'change_asked';

    public const CHANGED = 'changed';

    public const UNLINKED = 'unlinked';

    /**
     * @param  string  $detail  what the notice names: the new address (masked), or the key of the way of signing in that was removed
     * @param  string|null  $url  the link "this was not me", when the notice has one
     * @param  int  $days  how many days the link works
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $driver,
        public readonly string $detail,
        public readonly ?string $url,
        public readonly int $days,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __("mail.account_notice.{$this->kind}.subject"));
    }

    public function content(): Content
    {
        // The way of signing in that was removed is named in the language the mail is sent in, which is known only now.
        $named = $this->kind === self::UNLINKED ? __("mail.account_notice.methods.{$this->detail}") : $this->detail;

        return new Content(view: 'mail.account-notice', text: 'mail.account-notice-text', with: ['named' => $named]);
    }
}
