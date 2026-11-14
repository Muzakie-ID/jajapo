@props(['preheader' => null, 'hero' => null])
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<title>{{ config('app.name') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<style>
@media only screen and (max-width: 600px) {
.inner-body {
width: 100% !important;
}

.body {
padding: 0 12px !important;
}

.content-cell {
padding: 24px !important;
}

.hero {
padding: 22px 24px 20px !important;
}

.hero-title {
font-size: 19px !important;
}

.footer {
width: 100% !important;
}
}

@media only screen and (max-width: 500px) {
.button {
width: 100% !important;
}
}
</style>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
{!! $head ?? '' !!}
</head>
<body>
@if (filled($preheader))
{{-- Cuplikan yang tampil di daftar inbox. Spasi kosong di belakangnya mencegah teks kepala email ikut terambil. --}}
<div style="display: none; max-height: 0; max-width: 0; overflow: hidden; opacity: 0; font-size: 1px; line-height: 1px; color: #eef3ec; mso-hide: all;">{{ $preheader }}{!! str_repeat('&#847;&zwnj;&nbsp;', 90) !!}</div>
@endif

<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
{!! $header ?? '' !!}

<!-- Email Body -->
<tr>
<td class="body" width="100%" cellpadding="0" cellspacing="0" style="border: hidden !important;">
<table class="inner-body" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
@if (! empty($hero))
<tr>
<td class="hero">
@if (! empty($hero['eyebrow']))
<p class="hero-eyebrow">{{ $hero['eyebrow'] }}</p>
@endif
<p class="hero-title">{{ $hero['title'] }}</p>
</td>
</tr>
@endif
<!-- Body content -->
<tr>
<td class="content-cell">
{!! Illuminate\Mail\Markdown::parse($slot) !!}

{!! $subcopy ?? '' !!}
</td>
</tr>
</table>
</td>
</tr>

{!! $footer ?? '' !!}
</table>
</td>
</tr>
</table>
</body>
</html>
