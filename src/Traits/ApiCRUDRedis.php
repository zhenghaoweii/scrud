<?php

namespace limitless\scrud\Traits;

use Illuminate\Http\Request;
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

        if(isset($this->setMiddleware) and count($this->setMiddleware) > 0){
            foreach ($this->setMiddleware as  $key => $middleware){
                if(is_int($key)){
                    $this->middleware($middleware);
                }else{
                    $this->middleware($key, $middleware);
                }
            }
        }

        $this->_redis = Redis::connection();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $result = $this->getRedisAll();

            if (empty($result)) {
                $result = $this->_resourcer($this->_model->all(), true);
                $this->_redis->command('hsetnx', [$this->_model->getTable(), 'all', json_encode($result)]);
            } else {
                return response(['data' => json_decode($result, true)]);
            }
        } catch (\Exception $e) {
            return response(['error' => $e->getMessage()], 500);
        }

        return $result;
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
            $this->storeToListRedis($result);

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
            $result = json_decode($this->getRedisSingular($id));

            if ( ! $result) {
                $result = $this->_resourcer($this->_model->findOrFail($id));
                $this->setRedisSingular($result);
            }
        } catch (\Exception $e) {
            return response(['error' => $e->getMessage()], 500);
        }

        return response(['data' => $result]);
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
            $this->updateToAllRedis($result);
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

            $this->destroyFromAllRedis($backup);
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

    private function _resourcer($data, $collection = false, $list = false)
    {
        if (isset($this->resource) and count($this->resource) > 1) {
            if ( ! $collection && class_exists($this->resource[0])) {
                if($list){
                    $collection = (isset($this->resource[1])) ? $this->resource[1] : $this->resource[0];
                    return (new $collection($data));
                }
                return (new $this->resource[0]($data));
            }

            $collection = (isset($this->resource[1])) ? $this->resource[1] : $this->resource[0];
            if (class_exists($this->resource[1])) {
                return (new $collection(null))::collection($data);
            }
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

    private function delRedisSingular($result)
    {
        $this->_redis->command('hdel', [$this->_model->getTable(), $result->id]);
    }

    private function setRedisSingular($result)
    {
        $this->_redis->command('hset', [$this->_model->getTable(), $result->id, json_encode($result)]);
    }

    private function getRedisAll()
    {
        return $this->_redis->command('hget', [$this->_model->getTable(), 'all']);
    }

    private function getRedisSingular($id)
    {
        return $this->_redis->command('hget', [$this->_model->getTable(), $id]);
    }

    private function storeToListRedis($result)
    {
        $this->setRedisSingular($result);
        $response = $this->_resourcer($result,false, true);
        $finalize = collect(json_decode($this->getRedisAll()))->push($response);

        $this->_redis->command('hset', [$this->_model->getTable(), 'all', json_encode($finalize)]);

        return $response;
    }

    private function updateToAllRedis($result)
    {
        $this->setRedisSingular($result);
        $response      = $this->_resourcer($result,false, true);
        $existed       = collect(json_decode($this->getRedisAll()));
        $key           = $existed->where('id', $result->id)->keys()[0];
        $existed[$key] = $response;

        $this->_redis->command('hset', [$this->_model->getTable(), 'all', json_encode($existed)]);

        return $response;
    }

    private function destroyFromAllRedis($result)
    {
        $this->delRedisSingular($result);

        $existed = collect(json_decode($this->getRedisAll()));
        $existed = $existed->where('id', '!=', $result->id)->values();

        $this->_redis->command('hset', [$this->_model->getTable(), 'all', json_encode($existed)]);
    }
}

