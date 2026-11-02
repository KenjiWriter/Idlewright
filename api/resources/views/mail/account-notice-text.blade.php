{{ __("mail.account_notice.{$kind}.intro", ['driver' => $driver, 'detail' => $named, 'days' => $days]) }}
@if ($url)

{{ __("mail.account_notice.{$kind}.action") }}: {{ $url }}

{{ trans_choice('mail.account_notice.valid', $days) }}
@endif

{{ __('mail.account_notice.footer') }}
