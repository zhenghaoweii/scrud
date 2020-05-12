<?php

namespace limitless\scrud\Traits;

use App\Classes\RedisClass;
use Illuminate\Http\Request;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Route;

trait ApiCRUDRedis
{

    protected $_model;
    protected $_redis;

    public function __construct()
    {
        $this->_isDisabled();
        $this->_model = (new $this->model());
        $this->redis  = (isset($this->speed) && $this->speed === true) ? (new RedisClass()) : false;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            if ($this->redis) {
                $result = $this->redis->hgetall($this->_model->getTable());
                if ($result->count() === 0) {
                    $result = $this->_resourcer($this->_model->all(), true);
                    $this->redis->hsetall($this->_model->getTable(), $result);
                }

                return response(['data' => $result]);
            } else {
                $result = $this->_model->all();
            }
        } catch (\Exception $e) {
            return response(['error' => $e->getMessage()], 500);
        }

        return $this->_resourcer($result, true);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->_validator($request, 'create');

        DB::beginTransaction();
        try {
            $result = $this->_model->create($request->all());
            $result = $result->fresh();

            // sync to redis
            if ($this->redis) {
                $this->redis->hsetnx($this->_model->getTable(), $result->id, json_encode($this->_resourcer($result)));
            }
        } catch (\Exception $e) {
            return response(['error' => $e->getMessage()], 500);
        }
        DB::commit();

        return $this->_resourcer($result);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            // get from redis
            if ($this->redis) {
                $result = $this->redis->hget($this->_model->getTable(), $id);
                if ( ! $result) {
                    $result = $this->_resourcer($this->_model->findOrFail($id));
                }

                return response(['data' => $result]);
            } else {
                $result = $this->_model->findOrFail($id);
            }
        } catch (\Exception $e) {
            return response(['error' => $e->getMessage()], 500);
        }

        return $this->_resourcer($result);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {

        $this->_validator($request, 'update');
        DB::beginTransaction();
        try {
            $result = tap($this->_model->findOrFail($id))->update($request->all());

            // sync to redis
            if ($this->redis && $this->redis->hget($this->_model->getTable(), $id)) {
                $this->redis->hset($this->_model->getTable(), $id, $this->_resourcer($result));
            }

        } catch (\Exception $e) {
            return response(['error' => $e->getMessage()], 500);
        }
        DB::commit();

        return $this->_resourcer($result);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {

        DB::beginTransaction();
        try {
            $backup = $this->_model->findOrFail($id);
            $this->_model->destroy($id);

            // sync to redis
            if ($this->redis && $this->redis->hget($this->_model->getTable(), $id)) {
                $this->redis->hdel($this->_model->getTable(), $id);
            }

        } catch (\Exception $e) {
            return response(['error' => $e->getMessage()], 500);
        }

        DB::commit();

        return $this->_resourcer($backup);
    }

    private function _validator($request, $type)
    {

        if (isset($this->validation[$type]) and class_exists($this->validation[$type])) {
            if (isset($this->validation[$type]) and $this->validation[$type] != '') {
                $request->validate((new $this->validation[$type]())->rules());
            }
        }
    }

    private function _resourcer($data, $collection = false)
    {

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