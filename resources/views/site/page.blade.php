@extends('site.layout')

@section('content')
@if ($slug === 'home')
  <section class="hero">
    <div class="wrap">
      <h1>{{ $settings['business_name'] }}</h1>
      <p class="lead">{{ $settings['tagline'] }}</p>
      <p>
        <a class="btn big" href="/app/">Get started</a>
        @if (isset($pages['contact-us']))<a class="btn ghost big" href="/contact-us">Contact us</a>@endif
      </p>
    </div>
  </section>
@endif

<section class="wrap prose">
  @if ($slug !== 'home')<h1>{{ $page->title }}</h1>@endif
  {{ $body }}

  @if ($slug === 'contact-us')
    <div class="card">
      <h2>Get in touch</h2>
      <ul>
        @if ($settings['contact_email'] !== '')<li>Email: <a href="mailto:{{ $settings['contact_email'] }}">{{ $settings['contact_email'] }}</a></li>@endif
        @if ($settings['contact_phone'] !== '')<li>Phone: {{ $settings['contact_phone'] }}</li>@endif
        @if ($settings['address'] !== '')<li>Address: {{ $settings['address'] }}</li>@endif
      </ul>

      @if (session('contact_sent'))
        <div class="ok">Thank you! Your message has been received. We will get back to you soon.</div>
      @endif

      <form method="POST" action="/contact-us">
        @csrf
        <label for="name">Name</label>
        <input id="name" name="name" value="{{ old('name') }}" required maxlength="120">
        @error('name')<div class="err">{{ $message }}</div>@enderror

        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255">
        @error('email')<div class="err">{{ $message }}</div>@enderror

        <label for="phone">Phone (optional)</label>
        <input id="phone" name="phone" value="{{ old('phone') }}" maxlength="40">

        <label for="message">Message</label>
        <textarea id="message" name="message" rows="5" required minlength="10" maxlength="2000">{{ old('message') }}</textarea>
        @error('message')<div class="err">{{ $message }}</div>@enderror

        <div class="hp" aria-hidden="true"><label>Leave this empty <input name="website" tabindex="-1" autocomplete="off"></label></div>
        <button class="btn" type="submit">Send message</button>
      </form>
    </div>
  @endif
</section>

@if ($plans->isNotEmpty())
  <section class="wrap">
    <h2 class="center">Plans</h2>
    <div class="plans">
      @foreach ($plans as $plan)
        <div class="card plan">
          <h3>{{ $plan->name }}</h3>
          <div class="price">&#8377;{{ number_format($plan->amount_paise / 100) }} <small>/ {{ $plan->days }} days</small></div>
          @if ($plan->description)<p>{{ $plan->description }}</p>@endif
          <a class="btn" href="/app/">Get started</a>
        </div>
      @endforeach
    </div>
  </section>
@endif

@if ($slug !== 'home' && $slug !== 'services' && $slug !== 'contact-us' && $slug !== 'about-us')
  <div class="wrap muted small">Last updated: {{ $page->updated_at?->format('d M Y') }}</div>
@endif
@endsection
