<?php


namespace EasyApi;


class Controller
{
    protected ?Model    $model  = null;
    protected array     $param  = [];
    protected string    $path   = '';
    protected           $back;


    public function __construct()
    {

    }

    public function implant(Model $model = null){

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

    public function show()
    {

        $this->model->select();
        return $this->model->getBack();
    }

    public function save()
    {

    }

    public function del()
    {

    }

    public function view()
    {

    }







/*--------------------------------------------------------------------------------------------------------------------*/
//辅助方法
    /**
     * 查找类名作为模型的名称
     */
    protected function scoutClassName(){
        $class = basename(str_replace('\\', '/', get_class($this)));
        return class_exists($class)?$class:false;
    }

}