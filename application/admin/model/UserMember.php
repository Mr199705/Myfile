<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 http://ljk.cc All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: itkk <it_kk@qq.com>
// +----------------------------------------------------------------------
namespace app\admin\model;
use think\Model;

class UserMember extends Model
{
    protected $table = 'ljk_user_member';
   
    
    public function getUserCount($where)
    {
    	return $this->where($where)->count();
    }
    
    /**
     * 根据搜索条件获取用户列表信息
     * @param $where
     * @param $offset
     * @param $limit
     */
    public function getUsersByWhere($where, $whereOr, $offset, $limit)
    {
        /*        
        return $this->field('ljk_user.*,rolename')
        ->join('ljk_role', 'ljk_user.typeid = ljk_role.id')
        ->where($where)->limit($offset, $limit)->order('id desc')->select();
        */
        if(!empty($whereOr)){
            return $this
                    ->alias('u')
                    ->field('u.*,t.title tt,r.rank_name rn,o.name osn')
                    ->limit($offset, $limit)
                    ->join('__USER_TYPE__ t','u.tpid=t.id','LEFT')
                    ->join('__USER_RANK__ r','u.rankid=r.id','LEFT')
                    ->join('__OAUTH_CLIENTS__ o','u.shopid=o.id','LEFT')
                    ->where($where)
                    ->where(function ($query)use($whereOr){
                        $query->whereOr($whereOr);
                    })
                    ->order('uid desc')
                    ->select();
        }else{
            return $this
                    ->alias('u')
                    ->field('u.*,t.title tt,r.rank_name rn,o.name osn')
                    ->limit($offset, $limit)
                    ->join('__USER_TYPE__ t','u.tpid=t.id','LEFT')
                    ->join('__USER_RANK__ r','u.rankid=r.id','LEFT')
                    ->join('__OAUTH_CLIENTS__ o','u.shopid=o.id','LEFT')
                    ->where($where)
                    ->order('uid desc')
                    ->select();
        }
    }
    
    /**
     * 根据搜索条件获取用户列表信息 带分页信息
     * @param $where
     * @param $offset
     * @param $limit
     */
    public function getUsersByWherePage($where, $whereOr, $limit)
    {
    	/*
    	 return $this->field('ljk_user.*,rolename')
    	->join('ljk_role', 'ljk_user.typeid = ljk_role.id')
    	->where($where)->limit($offset, $limit)->order('id desc')->select();
    	*/
    	if(!empty($whereOr)){
    		return $this
    		->alias('u')
    		->field('u.*,t.title tt,r.rank_name rn,o.name osn')
    		->join('__USER_TYPE__ t','u.tpid=t.id','LEFT')
    		->join('__USER_RANK__ r','u.rankid=r.id','LEFT')
    		->join('__OAUTH_CLIENTS__ o','u.shopid=o.id','LEFT')
    		->where($where)
    		->where(function ($query)use($whereOr){
    			$query->whereOr($whereOr);
    		})
    		->order('uid desc')
    		->paginate($limit);
    	}else{
    		return $this
    		->alias('u')
    		->field('u.*,t.title tt,r.rank_name rn,o.name osn')
    		->join('__USER_TYPE__ t','u.tpid=t.id','LEFT')
    		->join('__USER_RANK__ r','u.rankid=r.id','LEFT')
    		->join('__OAUTH_CLIENTS__ o','u.shopid=o.id','LEFT')
    		->where($where)
    		->order('uid desc')
    		->paginate($limit);
    	}
    }
    public function getPostUserUidByWhere($where,$whereOr){
        if(!empty($whereOr)){
            return $this
                    ->where($where)
                    ->where(function ($query) use($whereOr){
                        $query->whereOr($whereOr);
                    })
                    ->column('uid');
        }else{
            return $this
                    ->where($where)
                    ->column('uid');
        }
    }
    public function getPostUsersByWhere($where, $offset, $limit){
            $query = $this
                ->setTable('ljk_user_post')
                ->alias('p')
                ->field('u.*,t.title tt,r.rank_name rn,o.name osn')
                ->group('uid')
                ->limit($offset,$limit)
                ->join('__USER_MEMBER__ u','p.uid=u.uid','LEFT')
                ->join('__USER_TYPE__ t','u.tpid=t.id','LEFT')
                ->join('__USER_RANK__ r','u.rankid=r.id','LEFT')
                ->join('__OAUTH_CLIENTS__ o','u.shopid=o.id','LEFT')
                ->where($where)
                ->select();
            $res = [];
            foreach($query as $k=>$v){
                $res[] = $v->getData();
            }
            return $res;
    }
    public function getPostUserTotal($where){
        if(isset($where['uid']) && !empty($where['uid'])){
            $total = db()->query("SELECT COUNT(1) as total FROM  (SELECT id FROM ljk_user_post WHERE gid = {$where['gid']} AND guid = {$where['guid']} AND uid IN({$where['uid']}) GROUP BY uid) b;");
        }else{
            $total = db()->query("SELECT COUNT(1) as total FROM  (SELECT id FROM ljk_user_post WHERE gid = {$where['gid']} AND guid = {$where['guid']} GROUP BY uid) b;");
        }
        return $total[0]['total'];
    }
    public function addPost($data){
        return  $this->create($data)->save();
    }
    /**
     * 根据搜索条件获取所有的用户数量
     * @param $where
     */
    public function getAllUsers($where,$whereOr)
    {
        if(!empty($whereOr)){
            return $this
                    ->where($where)
                    ->where(function ($query)use($whereOr){
                        $query->whereOr($whereOr);
                    })->count();
        }else{
            return $this
                    ->where($where)
                    ->count();
        }
    }
	
    /**
     * 添加客户信息
     * @param $param
     */
    public function addUser($param){
        try{
            $result =  $this->validate()->save($param);
            if(false === $result){
                // 验证失败 输出错误信息
                return ['code' => 1, 'data' => '', 'msg' => $this->getError()];
            }else{
                return ['code' => 0, 'data' => ['uid'=>$this->uid], 'msg' => '添加用户成功'];
            }
        }catch( PDOException $e){
            return ['code' => 1, 'data' => '', 'msg' => $e->getMessage()];
        }
    }

    /**
     * 编辑客户信息
     * @param $param
     */
    public function editUser($param){
        try{
            $result =  $this->validate()->save($param, ['uid' => $param['id']]);
            if(false === $result){
                // 验证失败 输出错误信息
                return ['code' => 1, 'data' => '', 'msg' => $this->getError()];
            }else{
                return ['code' => 0, 'data' => '', 'msg' => '编辑用户成功'];
            }
        }catch( PDOException $e){
            return ['code' => 1, 'data' => '', 'msg' => $e->getMessage()];
        }
    }

    /**
     * 根据管理员id获取角色信息
     * @param $id
     */
    public function getOneUser($gid,$uid)
    {
        return $this->alias('u')
                    ->field('u.*,t.title tt,r.rank_name rn,o.name osn')
                    ->where('u.gid',$gid)
                    ->where('u.uid',$uid)
                    ->join('__USER_TYPE__ t','u.tpid=t.id','LEFT')
                    ->join('__USER_RANK__ r','u.rankid=r.id','LEFT')
                    ->join('__OAUTH_CLIENTS__ o','u.shopid=o.id','LEFT')
                    ->find();
    }
    /**
     * 删除管理员
     * @param $id
     */
    public function delUser($id)
    {
        try{
            $this->where('uid', $id)->delete();
            return ['code' => 1, 'data' => '', 'msg' => '删除管理员成功'];

        }catch( PDOException $e){
            return ['code' => 0, 'data' => '', 'msg' => $e->getMessage()];
        }
    }
}