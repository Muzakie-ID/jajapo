<x-mail::message :preheader="$preheader ?? null" :hero="$hero ?? null">
{{-- Greeting --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
@if ($level === 'error')
# @lang('Whoops!')
@else
# @lang('Hello!')
@endif
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Sorotan: angka atau fakta utama email, misalnya nominal yang masuk ke saldo --}}
@if (! empty($highlight))
<x-mail::panel>
<p class="highlight-label">{{ $highlight['label'] }}</p>
<p class="highlight-value">{{ $highlight['value'] }}</p>
@if (! empty($highlight['note']))
<p class="highlight-note">{{ $highlight['note'] }}</p>
@endif
</x-mail::panel>
@endif

{{-- Rincian: label kecil dan nilai tebal, seperti tiket di aplikasi --}}
@if (! empty($details))
<table class="details" width="100%" cellpadding="0" cellspacing="0" role="presentation">
@foreach ($details as $label => $value)
<tr>
<td class="details-label" width="38%">{{ $label }}</td>
<td class="details-value">{{ $value }}</td>
</tr>
@endforeach
</table>
@endif

{{-- Action Button --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Salutation --}}
@if (! empty($salutation))
{{ $salutation }}
@else
@lang('Regards,')<br>
{{ config('app.name') }}
@endif

{{-- Subcopy --}}
@isset($actionText)
<x-slot:subcopy>
@lang(
    "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\n".
    'into your web browser:',
    [
        'actionText' => $actionText,
    ]
) <br><span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
