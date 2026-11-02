@extends(config('crud.layout'))
@section('title', ($model->exists ? 'Edit' : 'New').' · '.$resource->label)
@section('subtitle', 'Data · only the model\'s fillable columns are written')

@section('content')
@include('crud::_nav')
<div class="card form-card"><div class="card-body">
  <form method="post" action="{{ $model->exists ? route('crud.update', [$resource->slug, $model->getKey()]) : route('crud.store', $resource->slug) }}" novalidate>
    @csrf
    @if ($model->exists) @method('put') @endif
    @foreach ($resource->editableFields() as $field)
      @php($id = 'f-'.$field->name)
      @php($value = old($field->name, $field->formValue($model)))
      @php($invalid = $errors->has($field->name) ? 'is-invalid' : '')
      <div class="row mb-3">
        <label class="col-sm-3 col-form-label" for="{{ $id }}">{{ $field->label() }}</label>
        <div class="col-sm-9">
          @switch($field->type)
            @case(\Ignited\Crud\FieldType::Textarea)
              <textarea id="{{ $id }}" name="{{ $field->name }}" class="form-control {{ $invalid }}" rows="5">{{ $value }}</textarea>@break
            @case(\Ignited\Crud\FieldType::Json)
              <textarea id="{{ $id }}" name="{{ $field->name }}" class="form-control crud-json {{ $invalid }}" rows="6" spellcheck="false">{{ $value }}</textarea>@break
            @case(\Ignited\Crud\FieldType::Switch)
              <input type="hidden" name="{{ $field->name }}" value="0">
              <div class="form-check form-switch pt-2"><input id="{{ $id }}" class="form-check-input {{ $invalid }}" type="checkbox" role="switch" name="{{ $field->name }}" value="1" @checked($value === '1' || $value === true || ($value === '' && ! $model->exists))></div>@break
            @case(\Ignited\Crud\FieldType::Select)
              <select id="{{ $id }}" name="{{ $field->name }}" class="form-select {{ $invalid }}">@if ($field->nullable)<option value="">–</option>@endif @foreach ($field->options as $v => $l)<option value="{{ $v }}" @selected((string) $value === (string) $v)>{{ $l }}</option>@endforeach</select>@break
            @case(\Ignited\Crud\FieldType::Relation)
              <select id="{{ $id }}" name="{{ $field->name }}" class="form-select {{ $invalid }}"><option value="">–</option>@foreach ($relationOptions[$field->name] ?? [] as $v => $l)<option value="{{ $v }}" @selected((string) $value === (string) $v)>{{ $l }}</option>@endforeach</select>@break
            @case(\Ignited\Crud\FieldType::Secret)
              <input id="{{ $id }}" name="{{ $field->name }}" type="password" class="form-control {{ $invalid }}" autocomplete="new-password">
              @unless ($errors->has($field->name))<div class="form-text">Write-only{{ $model->exists ? '; leave blank to keep the stored value' : '' }}.</div>@endunless @break
            @default
              <input id="{{ $id }}" name="{{ $field->name }}" class="form-control {{ $invalid }}" value="{{ $value }}"
                type="{{ match ($field->type) { \Ignited\Crud\FieldType::Integer, \Ignited\Crud\FieldType::Number => 'number', \Ignited\Crud\FieldType::Date => 'date', \Ignited\Crud\FieldType::DateTime => 'datetime-local', default => 'text' } }}"
                @if ($field->type === \Ignited\Crud\FieldType::Number) step="any" @endif @if ($field->maxLength) maxlength="{{ $field->maxLength }}" @endif>
              @if ($field->type === \Ignited\Crud\FieldType::DateTime)<div class="form-text">In {{ \Ignited\Crud\Field::zone() }}.</div>@endif
          @endswitch
          @error($field->name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
      </div>
    @endforeach
    <div class="d-flex justify-content-end gap-2">
      <a class="align-self-center me-2" href="{{ $model->exists ? route('crud.show', [$resource->slug, $model->getKey()]) : route('crud.index', $resource->slug) }}">Cancel</a>
      <button class="btn btn-primary" type="submit">{{ $model->exists ? 'Save' : 'Create' }}</button>
    </div>
  </form>
</div></div>
@endsection
