@extends(config('crud.layout'))
@section('title', $resource->label.': '.\Ignited\Crud\Field::nameOf($model))
@section('subtitle', 'Data · '.$model->getKey())

@section('content')
@include('crud::_nav')
@php($trashed = method_exists($model, 'trashed') && $model->trashed())
<div class="d-flex flex-wrap gap-2 mb-3">
  <a class="btn btn-sm btn-outline-secondary" href="{{ route('crud.index', $resource->slug) }}">All {{ strtolower($resource->label) }}</a>
  @if ($url = $resource->screenUrl($model))<a class="btn btn-sm btn-primary" href="{{ $url }}">Open the dedicated page</a>@endif
  @if ($canWrite)
    @if ($trashed)
      <form method="post" action="{{ route('crud.restore', [$resource->slug, $model->getKey()]) }}">@csrf<button class="btn btn-sm btn-outline-primary" type="submit">Restore</button></form>
    @else
      <a class="btn btn-sm btn-outline-primary" href="{{ route('crud.edit', [$resource->slug, $model->getKey()]) }}">Edit</a>
      <form method="post" action="{{ route('crud.destroy', [$resource->slug, $model->getKey()]) }}" data-confirm="Delete this record?{{ $resource->softDeletes() ? ' It can be restored from the trash.' : ' This cannot be undone.' }}">@csrf @method('delete')<button class="btn btn-sm btn-outline-danger" type="submit">Delete</button></form>
    @endif
  @endif
</div>
@if ($trashed)<div class="alert alert-warning">This record is in the trash.</div>@endif
<div class="card"><div class="card-body">
  <dl class="facts crud-facts">
    @foreach ($resource->visibleFields() as $field)
      <dt>{{ $field->label() }}</dt><dd>@include('crud::_value', ['field' => $field, 'model' => $model])</dd>
    @endforeach
  </dl>
</div></div>
@endsection
