<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $page->title }} | {{ $settings['business_name'] }}</title>
<meta name="description" content="{{ $page->meta_description ?: $settings['tagline'] }}">
<link rel="stylesheet" href="/site.css">
</head>
<body>
<header class="hdr">
  <div class="wrap hdr-in">
    <a class="brand" href="/">{{ $settings['business_name'] }}</a>
    <nav class="menu" aria-label="Main">
      @foreach (['about-us', 'services', 'contact-us'] as $s)
        @if (isset($pages[$s]))<a href="/{{ $s }}" @class(['on' => $slug === $s])>{{ $pages[$s] }}</a>@endif
      @endforeach
      <a class="btn" href="/app/">Login / Sign up</a>
    </nav>
  </div>
</header>

<main>@yield('content')</main>

<footer class="ftr">
  <div class="wrap">
    <div class="ftr-links">
      @foreach (['privacy-policy', 'refund-policy', 'terms-and-conditions', 'disclaimer'] as $s)
        @if (isset($pages[$s]))<a href="/{{ $s }}">{{ $pages[$s] }}</a>@endif
      @endforeach
    </div>
    @if ($settings['footer_text'] !== '')<p>{{ $settings['footer_text'] }}</p>@endif
    <p class="muted">&copy; {{ date('Y') }} {{ $settings['business_name'] }}. All rights reserved.</p>
  </div>
</footer>
</body>
</html>
