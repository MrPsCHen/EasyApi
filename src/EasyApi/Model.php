<?php


namespace EasyApi;


use think\facade\Db;

class Model
{
    protected array     $tables         = [];   //表对象
    protected int       $page           = 1;    //页码
    protected int       $limit          = 20;   //条数
    protected           $cursor         = null; //游标对象
    protected ?array    $back           = [];   //返回对象
    protected array     $_ploy          = [];   //聚合参数
    protected array     $_extra         = [];   //扩展参数
    protected array     $_extra_arr     = [];

    protected array     $full_field     = [];
    protected array     $cursor_like    = [];
    protected array     $cursor_in      = [];
    protected array     $cursor_where   = [];
    protected array     $cursor_join    = [];


    public function __construct(string $table = '',string $prefix = '')
    {
        $this->table($this->getTable());
        $this->getTable();
        $this->setMaster();
    }

    public function table(string $table,string $prefix = '')
    {
        $this->tables[$prefix.$table] = new Table($table,$prefix);
    }

/*--------------------------------------------------------------------------------------------------------------------*/
//常规查询
    public function select()
    {

        $this->outFiledFull();
        $this->_join();
        $this->_decorate();
        $this->_decorate_prefix();
        $this->cursor->where($this->cursor_where);
        $this->back = $this->cursor->select()->toArray();
        $this->_extra_join();
        return $this;
    }
    public function find()
    {
        $this->outFiledFull();
        $this->_join();
        $this->_decorate();
        $this->_decorate_prefix();
        $this->cursor->where($this->cursor_where);
        $this->back = $this->cursor->find();
        $this->_extra_join(true);
        return $this->back;

    }
    public function count(){
        return $this->cursor->count();
    }
    public function update(?array $array = [])
    {
        $table = reset($this->tables);
        $table->verifyFiled();
        $this->cursor->update($array);
    }

    public function delete()
    {

    }

    public function insert()
    {

    }

    public function save()
    {

    }



/*--------------------------------------------------------------------------------------------------------------------*/
//语句修饰方法
    public function order()
    {

    }

    public function where(?array $array)
    {
        $this->cursor->where($array);
        return $this;
    }

    /**
     * @return int
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * @param int $page
     */
    public function setPage(int $page): void
    {
        $this->page = $page;
    }

    /**
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @param int $limit
     */
    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }





/*--------------------------------------------------------------------------------------------------------------------*/
//聚合查询

    public function ploy(string $table,string $prefix,string $contact_filed,string $join_type = 'LEFT',bool $master = true)
    {
        $this->_ploy[$prefix.$table] = [$contact_filed,$join_type,$master];
        !isset($this->tables[$prefix.$table])&& $this->tables[$prefix.$table] = new Table($table,$prefix);
        return $this->tables[$prefix.$table];
    }

    public function extra(string $table,string $prefix,string $contact_filed)
    {
        $this->_extra[$prefix.$table] = [$contact_filed,true];
        !isset($this->tables[$prefix.$table])&& $this->tables[$prefix.$table] = new Table($table,$prefix);
        return $this->tables[$prefix.$table];
    }



/*--------------------------------------------------------------------------------------------------------------------*/
//工具方法
    public function autoParam(array $array)
    {
        if(isset($array['page'])){$this->page = $array['page']; unset($array['page']);}
        if(isset($array['limit'])){$this->page = $array['limit']; unset($array['limit']);}
        if(isset($array['size'])){$this->page = $array['size']; unset($array['size']);}
        $this->cursor_where = $array;
    }
    public function error()
    {

    }

    public function display(array $filed,string $table)
    {

    }

    public function filter(array $filed,string $table)
    {

    }

    public function like(array $array)
    {
        $this->cursor_like = $array;

        return $this;
    }
    public function in(array $array){
        $this->cursor_in = $array;
        return $this;
    }

    public function chooseTable(string $table_name = ''){
        if(empty($table_name))return reset($this->tables);
        return $this->tables[$table_name];
    }

    public function setMaster(string $table = ''){
        if(isset($this->tables[$table])){
            $table = $this->tables[$table]->getTable();
        }else{
            $table = reset($this->tables);
        }
        $this->cursor = Db::name($table->getTable());

    }

    /**
     * @return array
     */
    public function getBack(): array
    {
        return $this->back;
    }


/*--------------------------------------------------------------------------------------------------------------------*/
//辅助方法

    /**
     * 修饰词：in,like等
     */
    protected function _decorate(){
        if(empty($this->cursor_like) && empty($this->cursor_in))return;
        foreach ($this->cursor_where as $k=>$v){
            if(in_array($k,$this->cursor_like) && isset($this->full_field[$k])){

                $this->cursor_where[] = [$this->full_field[$k].'.'.$k,'LIKE',"%{$v}%"];
//                unset($this->cursor_where[$this->full_field[$k].'.'.$k]);

            }else if(in_array($k,$this->cursor_in) && isset($this->full_field[$k])){
                $this->cursor_where[] = [$this->full_field[$k].'.'.$k,'IN',is_array($v)?implode(',',$v):$v];
                unset($this->cursor_where[$this->full_field[$k].'.'.$k]);
            }else if(isset($this->full_field[$k])){
                $this->cursor_where[] = [$this->full_field[$k].'.'.$k,'=',$v];
            }
            unset($this->cursor_where[$k]);
        }

    }

    /**
     * 加前缀
     */
    protected function _decorate_prefix(){
        foreach ($this->cursor_where as $k =>$v){
            if(isset($this->full_field[$k])){
                $this->cursor_where[$this->full_field[$k].'.'.$k] = $v;
                unset($this->cursor_where[$k]);
            }
        }
    }


    //
    protected function outFiled():array
    {
        $table = reset($this->tables);
        $back = $table->outFiled(false);
        foreach ($this->_ploy as $k=>$v){
            $back = array_merge($back,$this->tables[$k]->outFiled());
        }
        return array_values($back);
    }

    //导出所有查询字段
    protected function outFiledFull(){
        foreach (array_reverse($this->tables) as $key => $table){
            $this->full_field = array_merge($this->full_field,array_fill_keys($table->getFieldFull(),$table->getTable()));
        }
    }


    //
    protected function outCondition():array
    {
        return [];
    }

    //
    protected function getTable()
    {
        return get_class($this);
    }

    protected function _join(){
        //导出主表字段
        $this->cursor->field($this->outFiled());
        $table      = reset($this->tables);
        foreach ($this->_ploy as $key=>$value){
            if(in_array($key,$this->cursor_join))continue;
            $join_table = $this->tables[$key];
            if($value[2]){
                $condition = "{$table->getTable()}.{$value[0]}={$join_table->getTable()}.{$join_table->getPrimary()}";
            }else{
                $condition = "{$table->getTable()}.{$table->getPrimary()}={$join_table->getTable()}.{$value[0]}";
            }
            $this->cursor_join[] = $key;
            $this->cursor->join($key,$condition,$value[1]);
        }
    }
    protected function _extra_join(bool $flag_find = false){
        $table      = reset($this->tables);
        foreach ($this->_extra as $key=>$value){
            if($value[1]){

                $condition = $flag_find?$this->back[$value[0]]:array_column($this->back,$value[0]);
                $searchFiled = $this->tables[$key]->getPrimary();
            }else{
                $condition = $flag_find?$this->back[$value[0]]:array_column($this->back,$table->getPrimary());
                $searchFiled = $table->getPrimary();
            }

            $extra_arr = Db::table($key)
                      -> where([[$searchFiled,'IN',is_array($condition)?implode(',',$condition):$condition]])
                      -> column('*',$searchFiled);

            if($value[1]){
                if($flag_find){
                    $consult = $this->back[$value[0]];
                    $consult = explode(',',$consult);
                    $consult = array_flip($consult);
                    $this->back[$key] = array_merge(array_intersect_key($extra_arr,$consult),[]);
                }else{
                    foreach ($this->back as $_key =>$_val){
                        $consult = $_val[$value[0]];                //获取对应字段参数
                        $consult = explode(',',$consult);  //可能存在多个值，进行分割
                        $consult = array_flip($consult);           //键值对调，进行数组合并
                        $this->back[$_key][$key] = array_merge(array_intersect_key($extra_arr,$consult),[]);
                    }
                }


            }
        }
    }












}