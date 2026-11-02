{{-- One field's value; a relation links to the related record's data page when that model is registered. --}}
@php($related = $field->type === \Ignited\Crud\FieldType::Relation ? $model->{$field->relation} : null)
@php($relatedRes = $related ? app(\Ignited\Crud\ResourceRegistry::class)->forModel($related) : null)
@if ($relatedRes)
  <a href="{{ route('crud.show', [$relatedRes->slug, $related->getKey()]) }}">{{ $field->display($model, $limit ?? 0) }}</a>
@elseif ($field->type === \Ignited\Crud\FieldType::Json && empty($limit))
  <pre class="crud-json">{{ $field->display($model) }}</pre>
@elseif ($field->type === \Ignited\Crud\FieldType::Textarea && empty($limit))
  <pre class="crud-text">{{ $field->display($model) }}</pre>
@else
  {{ $field->display($model, $limit ?? 0) }}
@endif
