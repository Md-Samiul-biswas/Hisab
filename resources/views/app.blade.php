<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <meta name="theme-color" content="#059669" />
    <title>Budget Choice Bd - Business Hub</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('icon.svg') }}" />
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}" />
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}" />
    @php
      $cssFiles = glob(public_path('assets/index-*.css')) ?: [];
      $jsFiles = glob(public_path('assets/index-*.js')) ?: [];
    @endphp
    @foreach ($cssFiles as $cssFile)
      <link rel="stylesheet" href="{{ asset('assets/' . basename($cssFile)) }}" />
    @endforeach
  </head>
  <body class="bg-neutral-50 text-neutral-900 antialiased">
    <div id="root"></div>
    @foreach ($jsFiles as $jsFile)
      <script type="module" src="{{ asset('assets/' . basename($jsFile)) }}"></script>
    @endforeach
  </body>
</html>
