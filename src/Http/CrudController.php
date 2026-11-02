<?php

namespace Ignited\Crud\Http;

use Ignited\Crud\Field;
use Ignited\Crud\FieldType;
use Ignited\Crud\Resource;
use Ignited\Crud\ResourceRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;
use LogicException;

/**
 * The generic data pages: index, show, create, edit and delete for every model in `config/crud.php`,
 * built from its columns. A fallback next to the dedicated screens; writes need the `crud.gate` ability.
 */
class CrudController
{
    public function __construct(private readonly ResourceRegistry $registry) {}

    public function index(Request $request, string $resource): View
    {
        $res = $this->resource($resource);
        $this->authorize('viewAny', $res->model);

        $query = $res->query()->with($res->relationNames());
        if ($res->softDeletes()) {
            match ($request->query('trashed')) {
                'with' => $query->withTrashed(),
                'only' => $query->onlyTrashed(),
                default => null,
            };
        }
        $this->search($query, $res, (string) $request->query('q', ''));
        $filters = $this->filters($query, $res, (array) $request->query('filter', []));
        [$sort, $dir] = $this->sort($query, $res, $request);

        return view('crud::index', [
            'resource' => $res,
            'resources' => $this->registry->all(),
            'rows' => $query->paginate((int) config('crud.per_page', 25))->withQueryString(),
            'columns' => $res->listFields(),
            'filterFields' => array_values(array_filter($res->visibleFields(), fn (Field $f) => $f->type->filterable())),
            'filters' => $filters,
            'sort' => $sort,
            'dir' => $dir,
            'canWrite' => $this->canWrite($res),
        ]);
    }

    public function show(string $resource, string $id): View
    {
        $res = $this->resource($resource);
        $model = $res->find($id)->load($res->relationNames());
        $this->authorize('view', $model);

        return view('crud::show', ['resource' => $res, 'resources' => $this->registry->all(), 'model' => $model, 'canWrite' => $this->canWrite($res)]);
    }

    public function create(string $resource): View
    {
        $res = $this->writable($resource);
        $this->authorize('create', $res->model);

        return $this->form($res, $res->newModel());
    }

    public function store(CrudRequest $request, string $resource): RedirectResponse
    {
        $res = $this->writable($resource);
        $this->authorize('create', $res->model);

        $model = $res->newModel()->fill($request->modelAttributes());

        return $this->save($model, $res, 'created');
    }

    public function edit(string $resource, string $id): View
    {
        $res = $this->writable($resource);
        $model = $res->find($id);
        $this->authorize('update', $model);

        return $this->form($res, $model);
    }

    public function update(CrudRequest $request, string $resource, string $id): RedirectResponse
    {
        $res = $this->writable($resource);
        $model = $res->find($id);
        $this->authorize('update', $model);

        return $this->save($model->fill($request->modelAttributes()), $res, 'saved');
    }

    public function destroy(string $resource, string $id): RedirectResponse
    {
        $res = $this->writable($resource);
        $model = $res->find($id);
        $this->authorize('delete', $model);

        try {
            $model->delete();
        } catch (QueryException $e) {
            return back()->withErrors(['crud' => $this->refusal($e)]);
        }

        return redirect()->route('crud.index', $res->slug)->with('status', $res->softDeletes() ? 'Moved to the trash; restore it from the trashed filter.' : 'Deleted.');
    }

    public function restore(string $resource, string $id): RedirectResponse
    {
        $res = $this->writable($resource);
        abort_unless($res->softDeletes(), 404);
        $model = $res->find($id);
        $this->authorize('restore', $model);

        try {
            $model->restore();
        } catch (QueryException $e) {
            return back()->withErrors(['crud' => $this->refusal($e)]);
        }

        return redirect()->route('crud.show', [$res->slug, $model->getKey()])->with('status', 'Restored.');
    }

    private function resource(string $slug): Resource
    {
        return $this->registry->find($slug) ?? abort(404);
    }

    /** A resource that takes writes, for someone holding the `crud.gate` ability. */
    private function writable(string $slug): Resource
    {
        $res = $this->resource($slug);
        abort_if($res->readOnly, 403, "{$res->label} are read-only here.");
        if (($gate = config('crud.gate')) !== null) {
            Gate::authorize($gate);
        }

        return $res;
    }

    private function canWrite(Resource $res): bool
    {
        $gate = config('crud.gate');

        return ! $res->readOnly && ($gate === null || Gate::allows($gate));
    }

    /** The model's own policy, unless the app turned policies off for these pages (`crud.policies`). */
    private function authorize(string $ability, mixed $arguments): void
    {
        if (config('crud.policies', true)) {
            Gate::authorize($ability, $arguments);
        }
    }

    private function form(Resource $res, Model $model): View
    {
        $options = [];
        foreach ($res->editableFields() as $field) {
            if ($field->type === FieldType::Relation) {
                $options[$field->name] = $field->related::query()->limit(500)->get()
                    ->mapWithKeys(fn (Model $m) => [$m->getKey() => Field::nameOf($m)])->sort()->all();
            }
        }

        return view('crud::form', ['resource' => $res, 'resources' => $this->registry->all(), 'model' => $model, 'relationOptions' => $options]);
    }

    /** Model guards (saving hooks, append-only rules) and database constraints come back as form errors. */
    private function save(Model $model, Resource $res, string $verb): RedirectResponse
    {
        try {
            $model->save();
        } catch (InvalidArgumentException|LogicException $e) {
            return back()->withInput()->withErrors(['crud' => $e->getMessage()]);
        } catch (QueryException $e) {
            return back()->withInput()->withErrors(['crud' => $this->refusal($e)]);
        }

        return redirect()->route('crud.show', [$res->slug, $model->getKey()])->with('status', "{$res->label}: record {$verb}.");
    }

    private function refusal(QueryException $e): string
    {
        return 'The database refused this: '.($e->errorInfo[2] ?? 'a constraint was violated').'.';
    }

    private function search(Builder $query, Resource $res, string $term): void
    {
        $columns = array_map(fn (Field $f) => $f->name, array_filter($res->visibleFields(), fn (Field $f) => $f->type->searchable()));
        if ($term === '' || $columns === []) {
            return;
        }
        $query->where(function (Builder $q) use ($columns, $term) {
            foreach ($columns as $column) {
                $q->orWhere($column, 'like', '%'.addcslashes($term, '%_\\').'%');
            }
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string> the filters that applied
     */
    private function filters(Builder $query, Resource $res, array $input): array
    {
        $applied = [];
        foreach ($input as $name => $value) {
            $field = $res->field((string) $name);
            if ($field === null || $field->isSecret() || ! $field->type->filterable() || ! is_string($value) || $value === '') {
                continue;
            }
            $query->where($field->name, $field->type === FieldType::Switch ? (int) filter_var($value, FILTER_VALIDATE_BOOLEAN) : $value);
            $applied[$field->name] = $value;
        }

        return $applied;
    }

    /** @return array{0: string, 1: string} */
    private function sort(Builder $query, Resource $res, Request $request): array
    {
        $field = $res->field((string) $request->query('sort', ''));
        $sort = $field !== null && ! $field->isSecret() ? $field->name : ($res->field('created_at') ? 'created_at' : $res->newModel()->getKeyName());
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $dir)->orderBy($res->newModel()->getKeyName());

        return [$sort, $dir];
    }
}
