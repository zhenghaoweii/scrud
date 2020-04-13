<?php

namespace limitless\scrud\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Route;

trait ApiCRUD {

    protected $_model;

    public function __construct() {
        $this->_isDisabled();
        $this->_model = (new $this->model());
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        try {
            $result = $this->_model->all();
        } catch (\Exception $e) {
            return response(['error' => $e->getMessage()], 500);
        }

        return $this->_resourcer($result, true);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        $this->_validator($request, 'create');

        DB::beginTransaction();
        try {
            $result = $this->_model->create($request->all());
            $result = $result->fresh();
        } catch (\Exception $e) {
            return response(['error' => $e->getMessage()], 500);
        }
        DB::commit();
        return $this->_resourcer($result);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id) {
        try {
            $result = $this->_model->findOrFail($id);
            $result->subject;
        } catch (\Exception $e) {
            return response(['error' => $e->getMessage()], 500);
        }

        return $this->_resourcer($result);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {

        $this->_validator($request, 'update');
        DB::beginTransaction();
        try {
            $result = tap($this->_model->findOrFail($id))->update($request->all());
        } catch (\Exception $e) {
            return response(['error' => $e->getMessage()], 500);
        }
        DB::commit();
        return $this->_resourcer($result);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {

        DB::beginTransaction();
        try {
            $backup = $this->_model->findOrFail($id);
            $this->_model->destroy($id);
        } catch (\Exception $e) {
            return response(['error' => $e->getMessage()], 500);
        }
        DB::commit();
        return $this->_resourcer($backup);
    }

    private function _validator($request, $type) {

        if (isset($this->validation[$type]) and class_exists($this->validation[$type])) {
            if (isset($this->validation[$type]) and $this->validation[$type] != '')
                $request->validate((new $this->validation[$type]())->rules());
        }
    }

    private function _resourcer($data, $collection = false) {

        if (isset($this->resource) and $this->resource != '' and class_exists($this->resource)) {
            if ( ! $collection) {
                return (new $this->resource($data));
            }

            return (new $this->resource(null))::collection($data);
        }

        return response(['data' => $data]);
    }

    private function _isDisabled()
    {
        if (Route::getCurrentRoute() !== null) {
            $action = explode('@', Route::getCurrentRoute()->action['controller'])[1];
            if (isset($this->disabled) and in_array($action, $this->disabled)) {
                abort(403, 'Not found!');
            }
        }
    }
}