<!doctype html><html lang="es"><body style="margin:0;background:#f4f6f8;font-family:Arial,sans-serif;color:#27364a">
<table role="presentation" width="100%" cellpadding="24">
<tr><td align="center">
<table role="presentation" width="560" style="max-width:100%;background:white;border-radius:12px" cellpadding="32">
<tr><td>
<p style="color:#1B396A;font-size:18px;font-weight:bold">{{ config('catalogue.portal_name') }}</p>
@if(config('catalogue.institution_logo'))<img src="{{ asset(config('catalogue.institution_logo')) }}" width="60" alt="{{ config('catalogue.institution') }}">
@endif<h1 style="font-size:26px">{{ $heading }}</h1>
<p style="line-height:1.7">{{ $bodyText }}</p>
<p style="margin:28px 0"><a href="{{ $url }}" style="display:inline-block;padding:14px 22px;color:white;background:#117533;text-decoration:none;border-radius:6px">{{ $action }}</a></p>
<p style="font-size:13px;line-height:1.6">{{ $note }}</p><hr style="border:0;border-top:1px solid #e6e9ee">
<p style="font-size:12px;color:#687589">{{ config('catalogue.institution') }} · <a href="{{ route('privacy') }}">Privacidad</a></p>
<p style="font-size:12px;word-break:break-all">Si el botón no funciona, copia este enlace en tu navegador:<br>{{ $url }}</p></td></tr></table></td></tr></table></body></html>
