<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 http://ljk.cc All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: itkk <it_kk@qq.com>
// +----------------------------------------------------------------------
namespace app\m\model;
use think\Model;
//use traits\model\SoftDelete;
class OrderGoods extends Model
{
	//use SoftDelete;
    //protected static $deleteTime = 'delete_time';
   // protected $table = 'ljk_order';
	//protected $pk = 'oid';
	public function orderinfo()
    {
        return $this->hasOne('order','oid')->field('guid,uid');
    }
	
/*	public function getPayAttr($value)
    {
        $pay = [0=>'未付款',1=>'已付款'];
        return $pay[$value];
    }
*/    /**
     * 根据搜索条件获取订单列表信息
     * @param $where
     * @param $offset
     * @param $limit
     */
    public function getOrdersGoodsByWhere($where, $offset='', $limit='')
    {
/*        return $this->field('ljk_Order.*,rolename')
            ->join('ljk_role', 'ljk_Order.typeid = ljk_role.id')
            ->where($where)->limit($offset, $limit)->order('id desc')->select();
			
*/     if($offset&&$limit)
			    return $this->where($where)->limit($offset, $limit)->order('id desc')->select();
		else
				return $this->where($where)->select();
    }
    /**
     * 根据搜索条件获取所有的订单数量
     * @param $where
     */
    public function getAllOrders($where)
    {
        return $this->where($where)->count();
    }
	
    /**
     * 插入管理员信息
     * @param $param
     */
    public function addOrderGoods(){
        try{
            $result =  $this->validate('OrderValidate')->save($param);
            if(false === $result){
                // 验证失败 输出错误信息
                return ['code' => -1, 'data' => '', 'msg' => $this->getError()];
            }else{
                return ['code' => 1, 'data' => '', 'msg' => '添加订单成功'];
            }
        }catch( PDOException $e){
            return ['code' => -2, 'data' => '', 'msg' => $e->getMessage()];
        }
    }
    public function insertOrder($param)
    {
        try{
            $result =  $this->validate('OrderValidate')->save($param);
            if(false === $result){
                // 验证失败 输出错误信息
                return ['code' => -1, 'data' => '', 'msg' => $this->getError()];
            }else{
                return ['code' => 1, 'data' => '', 'msg' => '添加订单成功'];
            }
        }catch( PDOException $e){
            return ['code' => -2, 'data' => '', 'msg' => $e->getMessage()];
        }
    }
    /**
     * 编辑管理员信息
     * @param $param
     */
    public function editOrderGoods($param)
    {
        try{
            $result =  $this->validate('OrderGoodsValidate')->save($param, ['oid' => $param['id']]);
            if(false === $result){
                // 验证失败 输出错误信息
                return ['code' => 0, 'data' => '', 'msg' => $this->getError()];
            }else{
                return ['code' => 1, 'data' => '', 'msg' => '编辑订单商品成功'];
            }
        }catch( PDOException $e){
            return ['code' => 0, 'data' => '', 'msg' => $e->getMessage()];
        }
    }
    /**
     * 根据管理员id获取角色信息
     * @param $id
     */
    public function getOneOrderGoods($id)
    {
        return $this->where('id', $id)->find();
    }
}