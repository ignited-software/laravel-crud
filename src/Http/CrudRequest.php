<?php

namespace Ignited\Crud\Http;

use Ignited\Crud\Field;
use Ignited\Crud\Resource;
use Ignited\Crud\ResourceRegistry;
use Illuminate\Foundation\Http\FormRequest;

/** Validates a generic data form from the resource's columns; only fillable columns are ever read. */
class CrudRequest extends FormRequest
{
    public function resource(): Resource
    {
        return app(ResourceRegistry::class)->find((string) $this->route('resource')) ?? abort(404);
    }

    /** Before validation, so a reader never learns the form's rules. */
    public function authorize(): bool
    {
        $gate = config('crud.gate');

        return ! $this->resource()->readOnly && ($gate === null || (bool) $this->user()?->can($gate));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $rules = [];
        foreach ($this->resource()->editableFields() as $field) {
            $rules[$field->name] = $field->rules($creating);
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return collect($this->resource()->editableFields())->mapWithKeys(fn (Field $f) => [$f->name => strtolower($f->label())])->all();
    }

    /** @return array<string, mixed> the model's attributes, converted; a blank secret is left out so the model keeps it */
    public function modelAttributes(): array
    {
        $validated = $this->validated();
        $out = [];
        foreach ($this->resource()->editableFields() as $field) {
            [$present, $value] = $field->fromInput($validated[$field->name] ?? null);
            if ($present) {
                $out[$field->name] = $value;
            }
        }

        return $out;
    }
}
