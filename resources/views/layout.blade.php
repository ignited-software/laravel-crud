{{-- The fallback layout; an app points `crud.layout` at its own. --}}
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title')</title>
</head>
<body>
  <main>
    <h1>@yield('title')</h1>
    <p>@yield('subtitle')</p>
    @if (session('status'))<p role="status">{{ session('status') }}</p>@endif
    @error('crud')<p role="alert">{{ $message }}</p>@enderror
    @yield('content')
  </main>
</body>
</html>
