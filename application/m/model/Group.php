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
use traits\model\SoftDelete;
class Group extends Model
{	
	use SoftDelete;
    protected static $deleteTime = 'delete_time';
 //   protected $table = 'ljk_group';
    /**
     * 根据搜索条件获取团队列表信息
     * @param $where
     * @param $offset
     * @param $limit
     */
    public function getGroupsByWhere($where, $offset, $limit)
    {
        return $this->where($where)->limit($offset, $limit)->order('id desc')->select();
    }
    /**
     * 根据搜索条件获取所有的团队数量
     * @param $where
     */
    public function getAllGroups($where)
    {
        return $this->where($where)->count();
    }
	
    /**
     * 插入管理员信息
     * @param $param
     */
    public function insertGroup($param)
    {
        try{
            $result =  $this->validate('GroupValidate')->save($param);
            if(false === $result){
                // 验证失败 输出错误信息
                return ['code' => -1, 'data' => '', 'msg' => $this->getError()];
            }else{
                return ['code' => 1, 'data' => '', 'msg' => '添加团队成功'];
            }
        }catch( PDOException $e){
            return ['code' => -2, 'data' => '', 'msg' => $e->getMessage()];
        }
    }
    /**
     * 编辑管理员信息
     * @param $param
     */
    public function editGroup($param)
    {
        try{
            $result =  $this->validate('GroupValidate')->save($param, ['id' => $param['id']]);
            if(false === $result){
                // 验证失败 输出错误信息
                return ['code' => 0, 'data' => '', 'msg' => $this->getError()];
            }else{
                return ['code' => 1, 'data' => '', 'msg' => '编辑团队成功'];
            }
        }catch( PDOException $e){
            return ['code' => 0, 'data' => '', 'msg' => $e->getMessage()];
        }
    }
    /**
     * 根据管理员id获取角色信息
     * @param $id
     */
    public function getOneGroup($id)
    {
        return $this->where('gid', $id)->find();
    }
    /**
     * 删除管理员
     * @param $id
     */
    /*public function delGroup($id)
    {
        try{
            $this->where('id', $id)->delete();
            return ['code' => 1, 'data' => '', 'msg' => '删除管理员成功'];
        }catch( PDOException $e){
            return ['code' => 0, 'data' => '', 'msg' => $e->getMessage()];
        }
    }*/
}