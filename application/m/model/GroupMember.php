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
class GroupMember extends Model
{	
	use SoftDelete;
    protected static $deleteTime = 'delete_time';
    protected $table = 'ljk_group_member';
	protected $pk = 'uid';
    /**
     * 根据搜索条件获取团队成员列表信息
     * @param $where
     * @param $offset
     * @param $limit
     */
    public function getGroupMemberByWhere($where, $offset, $limit)
    {
        return $this->where($where)->limit($offset, $limit)->order('uid desc')->select();
    }
    /**
     * 根据搜索条件获取所有的团队成员数量
     * @param $where
     */
    public function getAllGroupMember($where)
    {
        return $this->where($where)->count();
    }
	
    /**
     * 插入管理员信息
     * @param $param
     */
    public function insertGroupMember($param)
    {
        try{
            $result =  $this->validate('GroupValidate')->save($param);
            if(false === $result){
                // 验证失败 输出错误信息
                return ['code' => -1, 'data' => '', 'msg' => $this->getError()];
            }else{
                return ['code' => 1, 'data' => '', 'msg' => '添加团队成员成功'];
            }
        }catch( PDOException $e){
            return ['code' => -2, 'data' => '', 'msg' => $e->getMessage()];
        }
    }
    /**
     * 编辑管理员信息
     * @param $param
     */
    public function editGroupMember($param)
    {
        try{
            $result =  $this->validate('GroupValidate')->save($param, ['uid' => $param['uid']]);
            if(false === $result){
                // 验证失败 输出错误信息
                return ['code' => 0, 'data' => '', 'msg' => $this->getError()];
            }else{
                return ['code' => 1, 'data' => '', 'msg' => '编辑团队成员成功'];
            }
        }catch( PDOException $e){
            return ['code' => 0, 'data' => '', 'msg' => $e->getMessage()];
        }
    }
    /**
     * 根据管理员id获取角色信息
     * @param $id
     */
    public function getOneGroupMember($id)
    {
        return $this->where('uid', $id)->find();
    }
	
    /**
     * 验证密码
     * @param $id
     */
    public function checkGroupMemberPassword($id,$password)
    {
        $hasUser = $this->getOneGroupMember($id);
        if(empty($hasUser)){
            return false;
        }
		$password=md5($password).$hasUser['salt'];
        if(md5($password) == $hasUser['password']){
			return true;
		}
		else{
            return false;
			}	
    }
	
	 /**
     * 修改密码
     * @param $id
     */
    public function updateGroupMemberPassword($id,$password)
    {
        $hasUser = $this->getOneGroupMember($id);
        if(empty($hasUser)){
            return false;
        }
		$password=md5(md5($password).$hasUser['salt']);
		$p['uid']=$id;
		$p['password']=$password;
		$status=$this->editGroupMember($p);
        return $status;
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