<?php


namespace EasyApi;


use app\model\index;

class Controller
{
    protected ?Model    $model  = null; //数据模型
    protected array     $param  = [];   //输入参数
    protected string    $path   = '';   //模型路径
    protected           $back;          //返回数据


    public function __construct()
    {
        if(function_exists('request'))$this->param = request()->param();
        if($model = $this->scoutClassName())$this->implant(new $model());
        $this->param = request()->param();

    }

    public function implant(Model $model = null){
        $this->model = $model;
    }

    /**
     * @param Model $model
     */
    public function setModel(Model $model): Model
    {
        return $this->model = $model;
    }
/*--------------------------------------------------------------------------------------------------------------------*/
//常用方法

    /**
     * 数据表查询方法  {code:200,msg:success,data:{page:1,limit:20,total:0,rows:[]}}
     * @return false|string|\think\response\Json
     */

    public function show()
    {
        $this->filterParamNull();
        $this->model->autoParam($this->param);
        $this->model->select();
        $this->back = $this->model->getBack();
        return Helper::success([
            'page'  =>$this->model->getPage(),
            'limit' =>$this->model->getLimit(),
            'total' =>$this->model->count(),
            'rows'  =>$this->model->getBack(),
        ]);
    }

    public function save()
    {
        $result = $this->model->save($this->param);

        return Helper::auto($result,[$this->model->error()]);
    }

    public function del()
    {
        return Helper::auto($this->model->delete($this->param),[$this->model->error()]);
    }
    /**
     *
     */
    public function view()
    {
        if(empty($this->param))return Helper::fatal('缺少查询条件');
        $this->model->autoParam($this->param);
        $this->back = $this->model->find();
        return Helper::success($this->back);
    }


    /**
     *
     */
    public function required(array $fields = [],?array $param = null)
    {
        $param = $param??$this->param;
        foreach ($fields as $key=>$val)
        {
            if(is_numeric($key)){
                if(!isset($param[$val])) {
                    response(Helper::fatal("{$val}为必填字段")->getContent())->send();
                    exit();
                }
            }else{
                if(!isset($param[$key])){
                    response(Helper::fatal("{$val}为必填字段")->getContent())->send();
                    exit();
                }
            }
        }
        return $this;
    }


/*--------------------------------------------------------------------------------------------------------------------*/
//辅助方法
    /**
     * 查找类名作为模型的名称
     */
    protected function scoutClassName(){
        $path = 'app\\';//路径依赖
        !empty($app_name = app('http')->getName()) && $app_name = $app_name."\\";//应用依赖
        $path.= $app_name;
        $path.= "model\\";
        $class = basename(str_replace('\\', '/', get_class($this)));//通过继承类名获取
        if(class_exists($path.strtolower($class))){
            return $path.strtolower($class);
        }else if (class_exists($path.$class)){
            return $path.$class;
        }else{
            return false;
        }
    }

    /**
     * 过滤空输入参数
     */
    protected function filterParamNull(){
        //判断是否过滤空参数
        if(isset($this->param['filter_param_null'])&&$this->param['filter_param_null']){
            if(strtolower($this->param['filter_param_null'])== 'false')return;
            foreach ($this->param as $key=>$val){
                if(empty($val)&&strlen($val)<=0)unset($this->param[$key]);
            };
            unset($this->param['filter_param_null']);
        }

    }

}