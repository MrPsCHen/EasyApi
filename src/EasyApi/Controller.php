<?php


namespace EasyApi;


class Controller
{


    /**
     * @var array|mixed|null
     */
    private $param;

    public function __construct()
    {

        if(class_exists('request'))$this->param = request()->param();
    }

    public function implant(Model $model = null)
    {

    }


    /**
     * @param array $array
     */
    public function required(array $array = [])
    {
        foreach ($array as $key=>$val){
            //字符串
            if(is_string($key)&&!is_numeric($key)){
                if(!isset($this->param[$key]))response()->data(Helper::fatal('字段不能为空'))->send();
                exit;
            }else{
                if(!isset($this->param[$val]))response()->data(Helper::fatal('字段不能为空'))->send();
                exit;
            }
        }
    }























}