<nav class="crud-nav mb-3" aria-label="Data">
  @foreach ($resources as $r)
    <a class="tag" href="{{ route('crud.index', $r->slug) }}" @if ($r->slug === $resource->slug) aria-current="page" @endif>{{ $r->label }}</a>
  @endforeach
</nav>
@if ($resource->note)<p class="small text-body-secondary">{{ $resource->note }}</p>@endif
