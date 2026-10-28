
@if(config('catalogue.turnstile_enabled'))<div class="cf-turnstile" data-sitekey="{{ config('catalogue.turnstile_site_key') }}" data-action="{{ $action }}" data-language="es" data-theme="light"></div>
@endif
