<p><strong>{{ $title }}</strong></p>

<p style="white-space:pre-line;">{{ $body }}</p>

@if(!empty($cta_label) && !empty($cta_url))
    <p><a href="{{ $cta_url }}">{{ $cta_label }}</a></p>
@endif

<p>Thanks,<br />{{ config('app.name') }}</p>

