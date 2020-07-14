<?php

namespace Nitm\Reporting\Http\Controllers\API;

use Illuminate\Http\Request;
use Response;
use Prettus\Repository\Criteria\RequestCriteria;
use InfyOm\Generator\Criteria\LimitOffsetCriteria;
use App\Http\Controllers\ApiController;
use App\Helpers\CollectionHelper;

/**
 * @SWG\Swagger(
 *   basePath="/api",
 *   @SWG\Info(
 *     title="Laravel Generator APIs",
 *     version="1.0.0",
 *   )
 * )
 * This class should be parent class for other API controllers
 * Class ApiBaseController
 */
class ApiBaseController extends ApiController
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct($withAuth = true)
    {
        parent::__construct();
        if ($withAuth) {
            $this->middleware('auth:api');
        }
    }

    /**
     * Display a listing of the Model.
     * GET|HEAD /{dataType}
     *
     * @param Request $request
     * @return Response
     */
    protected function _index(Request $request)
    {
        // Check permission
        $model = $this->repository->makeModel();
        $this->authorize('browse', $model);

        $this->repository->pushCriteria(new RequestCriteria($request));
        $this->repository->pushCriteria(new LimitOffsetCriteria($request));
        extract($this->resolveItems($request));

        return $this->sendResponse($data, $dataType->display_name_plural . ' retrieved successfully');
    }

    /**
     * Store a newly created Model in storage.
     * POST /{dataType}
     *
     * @param Request $request
     *
     * @return Response
     */
    protected function _store(Request $request)
    {
        $input = $this->parseInput($request->all());
        $model = $this->repository->makeModel();

        // Check permission
        $this->authorize('add', $model);

        $models = $this->repository->create($input);
        $slug = $this->getSlug($request);

        return $this->sendResponse($models->toArray(), $this->getDataType($slug)->display_name_singular . ' saved successfully');
    }

    /**
     * Display the specified Model.
     * GET|HEAD /{dataType}/{id}
     *
     * @param  int $id
     *
     * @return Response
     */
    protected function _show(Request $request, $id)
    {
        /** @var Model $model */
        $model = $this->repository->findWithoutFail($id);
        $slug = $this->getSlug($request);

        // Check permission
        $this->authorizeAction($request, ['read', $model]);

        if (empty($model)) {
            return $this->sendError($this->getDataType($slug)->display_name_singular . ' not found');
        }

        return $this->sendResponse($model->toArray(), $this->getDataType($slug)->display_name_singular . ' retrieved successfully');
    }

    /**
     * Update the specified Model in storage.
     * PUT/PATCH /{dataType}/{id}
     *
     * @param  int $id
     * @param Request $request
     *
     * @return Response
     */
    protected function _update(Request $request, $id)
    {
        $input = $this->parseInput($request->all());

        /** @var Model $model */
        $model = $this->repository->findWithoutFail($id);
        $slug = $this->getSlug($request);

        if (empty($model)) {
            return $this->sendError($this->getDataType($slug)->display_name_singular . ' not found');
        }

        // Check permission
        $this->authorize('edit', $model);

        $model = $this->repository->update(array_only($input, $model->getFillable()), $id);

        return $this->sendResponse($model->toArray(), $this->getDataType($slug)->display_name_singular . ' updated successfully');
    }

    /**
     * Remove the specified Model from storage.
     * DELETE /{dataType}/{id}
     *
     * @param  int $id
     *
     * @return Response
     */
    protected function destroy(Request $request, $id)
    {
        /** @var Model $model */
        $model = $this->repository->findWithoutFail($id);
        $slug = $this->getSlug($request);

        if (empty($model)) {
            return $this->sendError($this->getDataType()->display_name_singular . ' not found');
        }

        // Check permission
        $this->authorizeAction($request, ['delete', $model]);

        $model->delete();

        return $this->sendResponse($id, $this->getDataType($slug)->display_name_singular . ' deleted successfully');
    }

    /**
     * Parse input and check for json
     *
     * @param [type] $input
     * @return void
     */
    protected function parseInput($input)
    {
        if (is_string($input)) {
            $data = json_decode($input, true);
            $input = (json_last_error() == JSON_ERROR_NONE) ? $data : $input;
        }
        return $input;
    }

    protected function authorizeAction($request, array $authorizeParams)
    {
        call_user_func_array([$this, 'authorize'], $authorizeParams);
        list($action, $model) = $authorizeParams;
        $isAdmin = auth()->user()->roles->filter(function ($role) {
            return $role->slug == 'admin';
        })->count() != 0;
        if ($isAdmin) {
            return true;
        } else {
            switch ($action) {
                case 'create':
                case 'store':
                    return true;
                    break;
            }
            if (is_iterable($model)) {
                array_map(function ($m) {
                    if (!auth()->user()->id != $model->user_id) {
                        abort(403, 'This action is unauthorized');
                    }
                }, $model);
            } else {
                return auth()->user()->id == $model->user_id;
            }
        }
    }



    /**
     * Resolve the relational data for the request
     *
     * @param Request $request
     * @param Builder $builder
     * @param integer $id
     * @param Closure $callback
     * @param Closure $dataCallback
     * @return void
     */
    protected function resolveItems($request, $builder = null, $callback = null, $dataCallback = null)
    {
        $builder = $builder ?: $this->repository;
        $slug = $this->getSlug($request);;
        $options = (array) $request->input('options');
        $defaultOptions = ['form', 'filter', 'items'];
        if (in_array('all', $options) || empty($options)) {
            $options = $defaultOptions;
        }

        $data = [];
        if (!empty($options)) {
            /**
             * Get commn filter options fro the data type
             */
            $modelClass = get_class($builder->getModel());
            if (in_array('form', $options) && method_exists($modelClass, 'getFormOptions')) {
                $data['options']['form'] = $modelClass::getFormOptions($options);
            }
            if (in_array('filter', $options) && method_exists($modelClass, 'getFormOptions')) {
                $data['options']['filter'] = $modelClass::getFilterOptions($options);
            }
            if (is_callable($callback)) {
                $data = call_user_func($callback, $data);
            }
        }
        if (in_array('items', $options)) {
            $results = is_callable($dataCallback) ? $dataCallback($builder) : $builder->getModel()
                ->search($request->all(), true)
                ->orderBy($request->get('order_by', 'id'), $request->get('sort_order', 'desc'))
                ->paginate();
            $data['items'] = $results->getCollection()->toArray();
            $data['pagination'] = CollectionHelper::getPagination($results);
        }
        return [
            'data' => $data,
            'dataType' => $this->getDataType($slug)
        ];
    }
}
