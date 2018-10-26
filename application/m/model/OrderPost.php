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
class OrderPost extends Model
{
    /**
     * 根据搜索条件获取订单列表信息
     * @param $where
     * @param $offset
     * @param $limit
     */
    public function getOrderPostByWhere($where, $offset, $limit)
    {
       return $this->where($where)->limit($offset, $limit)->order('id desc')->select();
    }
    /**
     * 根据搜索条件获取所有的订单数量
     * @param $where
     */
    public function getOrderPostCount($where)
    {
        return $this->where($where)->count();
    }
    public function addPost($data){
        return $this->save($data);
    }
    /**
     * 插入信息
     * @param $param
     */
    public function insertOrderPost($param)
    {
        try{
            $result =  $this->validate('OrderPostValidate')->save($param);
            if(false === $result){
                // 验证失败 输出错误信息
                return ['code' => -1, 'data' => '', 'msg' => $this->getError()];
            }else{
                return ['code' => 1, 'data' => '', 'msg' => '添加成功'];
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
    public function getOneOrderPost($id)
    {
        return $this->where('id', $id)->find();
    }
}