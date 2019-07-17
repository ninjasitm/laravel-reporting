<?php

namespace App\Http\Controllers;

use Validator;
use Illuminate\Http\Request;
use InfyOm\Generator\Utils\ResponseUtil;
use InfyOm\Generator\Common\BaseRepository;
use Response;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Models\DataType;
use Prettus\Repository\Criteria\RequestCriteria;

/**
 * @SWG\Swagger(
 *   basePath="/api/v1",
 *   @SWG\Info(
 *     title="Laravel Generator APIs",
 *     version="1.0.0",
 *   )
 * )
 * This class should be parent class for other API controllers
 * Class AppBaseController
 */
class AppBaseController extends Controller
{
    public function __construct()
    {
        $this->repository = $this->getRepository();

        if (!request()->ajax()) {
        }
    }

    /**
     * Undocumented function
     *
     * @param Request $requeust
     * @return void
     */

    public function index(Request $request, $options=[])
    {
        // GET THE SLUG, ex. 'posts', 'pages', etc.
        $slug = $this->getSlug($request);

        // GET THE DataType based on the slug
        $dataType = $this->getDataType(array_get($options, 'dataType', $slug));

        // Check permission
        // $this->authorize('browse', app($dataType->model_name));

        $getter = 'paginate';

        $this->repository->pushCriteria(new RequestCriteria($request));

        $search = (object) [
            'value' => $request->get('s'),
            'key' => $request->get('key'),
            'filter' => $request->get('filter')
        ];
        $searchable = $dataType->server_side ? array_keys(SchemaManager::describeTable(app($dataType->model_name)->getTable())->toArray()) : '';
        $orderBy = $request->get('order_by', 'id');
        $sortOrder = $request->get('sort_order', 'desc');
        $usesSoftDeletes = false;
        $showSoftDeleted = false;

        $orderColumn = [];
        if ($orderBy) {
            $index = $dataType->browseRows->where('field', $orderBy)->keys()->first() + 1;
            $orderColumn = [[$index, 'desc']];
            if (!$sortOrder && isset($dataType->order_direction)) {
                $sortOrder = $dataType->order_direction;
                $orderColumn = [[$index, $dataType->order_direction]];
            } else {
                $orderColumn = [[$index, 'desc']];
            }
        }

        // Next Get or Paginate the actual content from the MODEL that corresponds to the slug DataType
        if (strlen($dataType->model_name) != 0) {
            $model = app($dataType->model_name);

            if ($dataType->scope && $dataType->scope != '' && method_exists($model, 'scope'.ucfirst($dataType->scope))) {
                $query = $model->{$dataType->scope}();
            } else {
                $query = $model::select('*');
            }

            // Use withTrashed() if model uses SoftDeletes and if toggle is selected
            if ($model && in_array(SoftDeletes::class, class_uses($model)) && app('VoyagerAuth')->user()->can('delete', app($dataType->model_name))) {
                $usesSoftDeletes = true;

                if ($request->get('showSoftDeleted')) {
                    $showSoftDeleted = true;
                    $query = $query->withTrashed();
                }
            }

            // If a column has a relationship associated with it, we do not want to show that field
            $this->removeRelationshipField($dataType, 'browse');

            if ($search->value != '' && $search->key && $search->filter) {
                $search_filter = ($search->filter == 'equals') ? '=' : 'LIKE';
                $search_value = ($search->filter == 'equals') ? $search->value : '%'.$search->value.'%';
                $query->where($search->key, $search_filter, $search_value);
            }

            if ($orderBy && in_array($orderBy, $dataType->fields())) {
                $querySortOrder = (!empty($sortOrder)) ? $sortOrder : 'desc';
                $dataTypeContent = call_user_func([
                    $query->orderBy($orderBy, $querySortOrder),
                    $getter,
                ]);
            } elseif ($model->timestamps) {
                $dataTypeContent = call_user_func([$query->latest($model::CREATED_AT), $getter]);
            } else {
                $dataTypeContent = call_user_func([$query->orderBy($model->getKeyName(), 'DESC'), $getter]);
            }

            // Replace relationships' keys for labels and create READ links if a slug is provided.
            $dataTypeContent = $this->resolveRelations($dataTypeContent, $dataType);
        } else {
            // If Model doesn't exist, get data from table name
            $dataTypeContent = call_user_func([DB::table($dataType->name), $getter]);
            $model = false;
        }


        // Check if server side pagination is enabled
        $isServerSide = isset($dataType->server_side) && $dataType->server_side;

        // Check if a default search key is set
        $defaultSearchKey = $dataType->default_search_key ?? null;

        $view = array_get($options, 'view', $this->getViewSlug($this->slug ?: $slug).'.index');

        return Voyager::view($view, array_merge([
            'models' => $this->repository->orderBy($orderBy, $sortOrder)->$getter(),
            'model' => new $dataType->model_name()
        ], $options, compact(
            'dataType',
            'dataTypeContent',
            'isModelTranslatable',
            'search',
            'orderBy',
            'sortOrder',
            'searchable',
            'isServerSide',
            'defaultSearchKey',
            'usesSoftDeletes',
            'showSoftDeleted'
        )));
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @return void
     */

    public function show(Request $request, $id, $options=[])
    {
        $slug = $this->getSlug($request);

        // GET THE DataType based on the slug
        $dataType = $this->getDataType(array_get($options, 'dataType', $slug));
        $isSoftDeleted = false;

        //This should probably be set on the model by default
        // $relationships = $this->getRelationships($dataType);

        if (strlen($dataType->model_name) != 0) {
            $model = app($dataType->model_name);
            // Use withTrashed() if model uses SoftDeletes and if toggle is selected
            if ($model && in_array(SoftDeletes::class, class_uses($model))) {
                $model = $model->withTrashed();
            }
            if ($dataType->scope && $dataType->scope != '' && method_exists($model, 'scope'.ucfirst($dataType->scope))) {
                $model = $model->{$dataType->scope}();
            }
            $dataTypeContent = call_user_func([$model, 'findOrFail'], $id);
            if ($dataTypeContent->deleted_at) {
                $isSoftDeleted = true;
            }
        } else {
            // If Model doest exist, get data from table name
            $dataTypeContent = DB::table($dataType->name)->where('id', $id)->first();
        }

        // If a column has a relationship associated with it, we do not want to show that field
        $this->removeRelationshipField($dataType, 'read');

        // Check permission
        $this->authorize('read', $dataTypeContent);

        // Check if BREAD is Translatable
        // $isModelTranslatable = is_bread_translatable($dataTypeContent);

        $view = array_get($options, 'view', $this->getViewSlug($this->slug ?: $slug).'.show');

        return Voyager::view($view, array_merge([
            'model' => $dataTypeContent
        ], $options, compact(
            'dataType', 'dataTypeContent', 'isModelTranslatable'
        )));
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @return void
     */

    public function edit(Request $request, $id, $options=[])
    {
        $slug = $this->getSlug($request);

        // GET THE DataType based on the slug
        $dataType = $this->getDataType(array_get($options, 'dataType', $slug));
        if (strlen($dataType->model_name) != 0) {
            $model = app($dataType->model_name);
            // Use withTrashed() if model uses SoftDeletes and if toggle is selected
            if ($model && in_array(SoftDeletes::class, class_uses($model))) {
                $model = $model->withTrashed();
            }
            if ($dataType->scope && $dataType->scope != '' && method_exists($model, 'scope' . ucfirst($dataType->scope))) {
                $model = $model->{$dataType->scope}();
            }
            $dataTypeContent = call_user_func([$model, 'findOrFail'], $id);
        } else {
            // If Model doest exist, get data from table name
            $dataTypeContent = DB::table($dataType->name)->where('id', $id)->first();
        }

        foreach ($dataType->editRows as $key => $row) {
            $dataType->editRows[$key]['col_width'] = isset($row->details->width) ? $row->details->width : 100;
        }

        // If a column has a relationship associated with it, we do not want to show that field
        $this->removeRelationshipField($dataType, 'edit');

        // Check permission`
        $this->authorize('edit', $dataTypeContent);

        // Check if BREAD is Translatable
        $isModelTranslatable = is_bread_translatable($dataTypeContent);

        $view = array_get($options, 'view', $this->getViewSlug($this->slug ?: $slug).'.edit');

        return Voyager::view($view, array_merge([
            'model' => $dataTypeContent
        ], $options, compact(
            'dataType', 'dataTypeContent', 'isModelTranslatable'
        )));
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param [type] $id
     * @return void
     */
    public function update(Request $request, $id, $options=[])
    {
        $slug = $this->getSlug($request);

        // GET THE DataType based on the slug
        $dataType = $this->getDataType(array_get($options, 'dataType', $slug));

        // Compatibility with Model binding.
        $id = $id instanceof Model ? $id->{$id->getKeyName()} : $id;

        $model = app($dataType->model_name);
        if ($dataType->scope && $dataType->scope != '' && method_exists($model, 'scope'.ucfirst($dataType->scope))) {
            $model = $model->{$dataType->scope}();
        }
        if ($model && in_array(SoftDeletes::class, class_uses($model))) {
            $data = $model->withTrashed()->findOrFail($id);
        } else {
            $data = call_user_func([$dataType->model_name, 'findOrFail'], $id);
        }

        // Check permission
        $this->authorize('edit', $data);

        // Validate fields with ajax
        $input = $request->all();

        $data->fill($input);
        $validator = Validator::make($data->getAttributes(), $data::$rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->messages()]);
        }

        $model = $this->repository->update($input, $id);
        if (!$request->ajax()) {

            // event(new BreadDataUpdated($dataType, $data));
            $view = array_get($options, 'view', $this->getViewSlug($this->slug ?: $slug).'.edit');

            return redirect()
                ->route('voyager.'.$view, ['id' => $id])
                ->with([
                    'message'    => __('voyager::voyager.generic.successfully_updated')." {$dataType->display_name_singular}",
                    'alert-type' => 'success',
                ]);
        } else {
            return response()->json([
                'id' => $id,
                'success' => __('voyager::voyager.generic.successfully_updated')." {$dataType->display_name_singular}"
            ]);
        }
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @return void
     */
    public function create(Request $request, $options=[])
    {
        $slug = $this->getSlug($request);

        // GET THE DataType based on the slug
        $dataType = $this->getDataType(array_get($options, 'dataType', $slug));

        // Check permission
        $this->authorize('add', app($dataType->model_name));

        $dataTypeContent = (strlen($dataType->model_name) != 0)
                            ? new $dataType->model_name()
                            : false;

        foreach ($dataType->addRows as $key => $row) {
            $details = json_decode($row->details);
            $dataType->addRows[$key]['col_width'] = isset($details->width) ? $details->width : 100;
        }

        // If a column has a relationship associated with it, we do not want to show that field
        $this->removeRelationshipField($dataType, 'add');

        // Check if BREAD is Translatable
        $isModelTranslatable = is_bread_translatable($dataTypeContent);

        $view = array_get($options, 'view', $this->getViewSlug($this->slug ?: $slug).'.create');

        return Voyager::view($view, array_merge([
            'model' => $dataTypeContent
        ], $options, compact(
            'dataType', 'dataTypeContent', 'isModelTranslatable'
        )));
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @return void
     */
    public function store(Request $request, $options=[])
    {
        $slug = $this->getSlug($request);

        // GET THE DataType based on the slug
        $dataType = $this->getDataType(array_get($options, 'dataType', $slug));

        // Check permission
        $this->authorize('add', app($dataType->model_name));

        // Validate fields with ajax
        $input = $request->all();

        $model = $this->repository->makeModel();
        $validator = Validator::make($input, $model::$rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->messages()]);
        }

        $model->fill($input);

        $model = $this->repository->create($input);

        if (!$request->ajax()) {

            // event(new BreadDataAdded($dataType, $data));
            $view = array_get($options, 'view', $this->getViewSlug($this->slug ?: $slug).'.index');

            //Need to determine if rerouting to new data is best
            return redirect()
                ->route('voyager.'.$view)
                ->with([
                    'message'    => __('voyager::voyager.generic.successfully_added_new')." {$dataType->display_name_singular}",
                    'alert-type' => 'success',
                ]);
        } else {
            return response()->json([
                'id' => $model->id,
                'success' => __('voyager::voyager.generic.successfully_added_new')." {$dataType->display_name_singular}",
                'html' => view($slug.'.dynamic', ['model' => $model])->render()
            ]);
        }
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param [type] $id
     * @return void
     */
    public function destroy(Request $request, $id, $options=[])
    {
        $slug = $this->getSlug($request);

        // GET THE DataType based on the slug
        $dataType = $this->getDataType(array_get($options, 'dataType', $slug));

        // Check permission
        $this->authorize('delete', app($dataType->model_name));

        // Init array of IDs
        $ids = [];
        if (empty($id)) {
            // Bulk delete, get IDs from POST
            $ids = explode(',', $request->ids);
        } else {
            // Single item delete, get ID from URL
            $ids[] = $id;
        }
        foreach ($ids as $id) {
            $data = call_user_func([$dataType->model_name, 'findOrFail'], $id);
            $this->cleanup($dataType, $data);
        }

        $displayName = count($ids) > 1 ? $dataType->display_name_plural : $dataType->display_name_singular;

        $res = $data->destroy($ids);
        $data = $res
            ? [
                'message'    => __('voyager::voyager.generic.successfully_deleted')." {$displayName}",
                'alert-type' => 'success',
            ]
            : [
                'message'    => __('voyager::voyager.generic.error_deleting')." {$displayName}",
                'alert-type' => 'error',
            ];

        // if ($res) {
            // event(new BreadDataDeleted($dataType, $data));
        // }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'id' => $ids
            ]);
        }

        $view = array_get($options, 'view', $this->getViewSlug($this->slug ?: $slug).'.index');

        return redirect()->route($view)->with($data);
    }
}
