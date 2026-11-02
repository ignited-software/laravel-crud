@extends(config('crud.layout'))
@section('title', $resource->label)
@section('subtitle', 'Data · '.($resource->readOnly ? 'read-only here' : 'generic pages for records without a dedicated screen'))

@section('content')
@include('crud::_nav')
@php($query = request()->query())
<form class="card mb-3" method="get">
  <div class="card-body filterbar">
    <div><label class="form-label" for="q">Search</label><input id="q" name="q" type="search" class="form-control" value="{{ request('q') }}" placeholder="Text columns"></div>
    @foreach ($filterFields as $f)
      <div><label class="form-label" for="filter-{{ $f->name }}">{{ $f->label() }}</label>
        <select id="filter-{{ $f->name }}" name="filter[{{ $f->name }}]" class="form-select">
          <option value="">All</option>
          @if ($f->type === \Ignited\Crud\FieldType::Switch)
            <option value="1" @selected(($filters[$f->name] ?? '') === '1')>Yes</option><option value="0" @selected(($filters[$f->name] ?? '') === '0')>No</option>
          @elseif ($f->type === \Ignited\Crud\FieldType::Select)
            @foreach ($f->options as $value => $label)<option value="{{ $value }}" @selected(($filters[$f->name] ?? '') === (string) $value)>{{ $label }}</option>@endforeach
          @else
            @foreach ($f->related::query()->limit(200)->get() as $m)<option value="{{ $m->getKey() }}" @selected(($filters[$f->name] ?? '') === (string) $m->getKey())>{{ \Ignited\Crud\Field::nameOf($m) }}</option>@endforeach
          @endif
        </select></div>
    @endforeach
    @if ($resource->softDeletes())
      <div><label class="form-label" for="trashed">Trashed</label><select id="trashed" name="trashed" class="form-select">
        <option value="">Hide trashed</option><option value="with" @selected(request('trashed') === 'with')>Include trashed</option><option value="only" @selected(request('trashed') === 'only')>Only trashed</option>
      </select></div>
    @endif
    <input type="hidden" name="sort" value="{{ $sort }}"><input type="hidden" name="dir" value="{{ $dir }}">
    <div class="actions"><button class="btn btn-primary" type="submit">Filter</button>@if ($canWrite)<a class="btn btn-outline-primary" href="{{ route('crud.create', $resource->slug) }}">New</a>@endif</div>
  </div>
</form>

@if ($rows->isEmpty())
  <div class="card"><div class="card-body crud-empty"><p class="fw-semibold mb-1">No {{ strtolower($resource->label) }}</p><p class="mb-0 text-body-secondary">{{ request()->hasAny(['q', 'filter', 'trashed']) ? 'Nothing matches these filters.' : 'Nothing stored yet.' }}</p></div></div>
@else
  <div class="card"><div class="table-responsive"><table class="table table-striped table-hover mb-0 crud-table">
    <thead><tr>
      @foreach ($columns as $col)
        @php($active = $sort === $col->name)
        <th><a href="{{ request()->fullUrlWithQuery(['sort' => $col->name, 'dir' => $active && $dir === 'asc' ? 'desc' : 'asc', 'page' => null]) }}" @if ($active) data-dir="{{ $dir }}" aria-sort="{{ $dir === 'asc' ? 'ascending' : 'descending' }}" @endif>{{ $col->label() }}</a></th>
      @endforeach
      <th class="text-end"><span class="visually-hidden">Open</span></th>
    </tr></thead>
    <tbody>
      @foreach ($rows as $row)
        <tr @if (method_exists($row, 'trashed') && $row->trashed()) data-trashed @endif>
          @foreach ($columns as $col)<td title="{{ $col->display($row, 200) }}">@include('crud::_value', ['field' => $col, 'model' => $row, 'limit' => 60])</td>@endforeach
          <td class="text-end text-nowrap">@if (method_exists($row, 'trashed') && $row->trashed())<span class="tag" data-flag="invalidated">trashed</span> @endif<a href="{{ route('crud.show', [$resource->slug, $row->getKey()]) }}">Open</a></td>
        </tr>
      @endforeach
    </tbody>
  </table></div></div>
  <div class="mt-3">{{ $rows->links() }}</div>
@endif
@endsection
