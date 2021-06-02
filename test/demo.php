<?php
use think\facade\Db;
include "./../vendor/autoload.php";

Db::setConfig(include "./database.php");

//$controller = new \EasyApi\Controller();
//$model =new \EasyApi\Model();

class app_user extends \EasyApi\Model{}

//查询表
$user = new app_user();


//聚合表
$user->ploy('label','app_','label_id');

//
//查询数据
$user->select();
//print_r($user->getBack());
//
//条件查询
$user->autoParam();
//
$user->select();

//聚合查找
$user->autoParam(['username'=>2,'id'=>4]);