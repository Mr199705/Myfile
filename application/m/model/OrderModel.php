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
use think\db\Query;
use traits\model\SoftDelete;
class OrderModel extends Model
{
	use SoftDelete;
    protected static $deleteTime = 'delete_time';
    protected $table = 'ljk_order';
	protected $pk = 'oid';
	
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
    public function getOrdersByWhere($where,$whereOr,$offset, $limit){
       // $guid = session('guid');
        if(!empty($whereOr)){
            return $this
                ->alias('o')
                ->field('o.number,o.status,o.trade,o.confirm,o.dispatch,o.oid,o.uid,o.total,o.content,o.pay,o.adate')
                ->where($where)
                ->where(function ($query) use($whereOr){
                    $query->whereOr($whereOr);
                })
                ->join('ljk_user_member u','u.uid = o.uid','LEFT')
             //   ->where('u.guid',$guid)
                ->order('o.oid desc')
                ->limit($offset, $limit)
                ->select();
        }else{
            return $this
                    ->alias('o')
                    ->field('o.number,o.status,o.trade,o.confirm,o.dispatch,o.oid,o.uid,o.total,o.content,o.pay,o.adate')
                    ->where($where)
                    ->join('ljk_user_member u','u.uid = o.uid','LEFT')
            //        ->where('u.guid',$guid)
                    ->order('o.oid desc')
                    ->limit($offset, $limit)
                    ->select();
        }
            
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
    public function insertOrder($param)
    {
        try{
            $result =  $this->validate('OrderValidate')->save($param);
            if(false === $result){
                // 验证失败 输出错误信息
                return ['code' => -1, 'data' => '', 'msg' => $this->getError()];
            }else{
                return ['code' => 1, 'data' => '添加订单成功！'];
            }
        }catch( PDOException $e){
            return ['code' => -2, 'data' => '', 'msg' => $e->getMessage()];
        }
    }
    public function editOrderStatus($data,$oid){
        try{
        	if(isset($data['pay'])){
        		if($data['pay']==0){
        			$data['pay_time']=0;
        		}else{
        			$data['pay_time']=time();
        		}
        	}
        	if(isset($data['dispatch'])){
        		if($data['dispatch']==2){
        			$data['dispatch_time']=time();
        		}else{
        			$data['dispatch_time']=0;
        		}
        	}
            $result =  $this->validate('OrderValidate')->save($data,['oid'=>$oid]);
            if(false === $result){
                // 验证失败 输出错误信息
                return ['code' => -1, 'data' => '', 'msg' => $this->getError()];
            }else{
                return ['code' => 1, 'data' => ''];
            }
        }catch( PDOException $e){
            return ['code' => -2, 'data' => '', 'msg' => $e->getMessage()];
        }
    }
    /**
     * 编辑管理员信息
     * @param $param
     */
    public function editOrder($param)
    {
        try{
            $result =  $this->validate('OrderValidate')->save($param, ['oid' => $param['id']]);
            if(false === $result){
                // 验证失败 输出错误信息
                return ['code' => 0, 'data' => '', 'msg' => $this->getError()];
            }else{
                return ['code' => 1, 'data' => '', 'msg' => '编辑订单成功'];
            }
        }catch( PDOException $e){
            return ['code' => 0, 'data' => '', 'msg' => $e->getMessage()];
        }
    }
    /**
     * 根据管理员id获取角色信息
     * @param $id
     */
    public function getOneOrder($id)
    {
        return $this->where('oid', $id)->find();
    }
    
    /**
     * 根据管理员id获取角色信息
     * @param $id
     */
    public function getOneOrderBygid($id,$gid)
    {
    	return $this->where('oid', $id)->where('gid', $gid)->find();
    }
}