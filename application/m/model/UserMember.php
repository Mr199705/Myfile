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
use think\Db;
class UserMember extends Model{
    protected $table = 'ljk_user_member';
    /**
     * 根据搜索条件获取用户列表信息
     * @param $where
     * @param $offset
     * @param $limit
     */
    public function getUsersByWhere($where=[], $whereOr1=[],$whereOr2=[],$offset, $limit,$t=0){
            if($t==1){
                if(empty($whereOr1) && empty($whereOr2)){
                    return Db::name('user_gm gm')
                            ->field('u.*,t.title tt,r.rank_name rn,o.name osn')
                            ->limit($offset, $limit)
                            ->join('__USER_MEMBER__ u','gm.uid=u.uid','LEFT')
                            ->join('__USER_TYPE__ t','u.tpid=t.id','LEFT')
                            ->join('__USER_RANK__ r','u.rankid=r.id','LEFT')
                            ->join('__OAUTH_CLIENTS__ o','u.shopid=o.id','LEFT')
                            ->where($where)
                            ->order('gm.uid desc')
                            ->group('gm.uid')
                            ->select();
            }else{
                    return Db::name('user_gm gm')
                            ->field('u.*,t.title tt,r.rank_name rn,o.name osn')
                            ->limit($offset, $limit)
                            ->join('__USER_MEMBER__ u','gm.uid=u.uid','LEFT')
                            ->join('__USER_TYPE__ t','u.tpid=t.id','LEFT')
                            ->join('__USER_RANK__ r','u.rankid=r.id','LEFT')
                            ->join('__OAUTH_CLIENTS__ o','u.shopid=o.id','LEFT')
                            ->where($where)
                            ->where(function ($query)use($whereOr1,$whereOr2){
                                    if(empty($whereOr2)){
                                            $query->whereOr($whereOr1);
                                    }else if(empty($whereOr1)){
                                            $query->whereOr($whereOr2);
                                    }else{
                                            $query->where(function ($query) use($whereOr1){
                                                    $query->whereOr($whereOr1);
                                            })->where(function ($query) use($whereOr2){
                                                    $query->whereOr($whereOr2);
                                            });
                                    }
                            })
                            ->order('gm.uid desc')
                            ->group('gm.uid')
                            ->select();
                    }
            }else{
                if(empty($whereOr1) && empty($whereOr2)){
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
            }else{
                    return $this
                            ->alias('u')
                            ->field('u.*,t.title tt,r.rank_name rn,o.name osn')
                            ->limit($offset, $limit)
                            ->join('__USER_TYPE__ t','u.tpid=t.id','LEFT')
                            ->join('__USER_RANK__ r','u.rankid=r.id','LEFT')
                            ->join('__OAUTH_CLIENTS__ o','u.shopid=o.id','LEFT')
                            ->where($where)
                            ->where(function ($query)use($whereOr1,$whereOr2){
                                    if(empty($whereOr2)){
                                            $query->whereOr($whereOr1);
                                    }else if(empty($whereOr1)){
                                            $query->whereOr($whereOr2);
                                    }else{
                                            $query->where(function ($query) use($whereOr1){
                                                    $query->whereOr($whereOr1);
                                            })->where(function ($query) use($whereOr2){
                                                    $query->whereOr($whereOr2);
                                            });
                                    }
                            })
                            ->order('uid desc')
                            ->select();
            }
            }
    }
    public function getAllUsers($where,$whereOr1,$whereOr2,$t=0){
    if($t===1){
            if(empty($whereOr1) && empty($whereOr2)){
                    return Db::name('user_gm gm')
                            ->join('__USER_MEMBER__ u','gm.uid=u.uid','LEFT')
                            ->where($where)
                            ->group('gm.uid')
                            ->count();
            }else{
                    return Db::name('user_gm gm')
                            ->join('__USER_MEMBER__ u','gm.uid=u.uid','LEFT')
                            ->where($where)
                            ->where(function ($query)use($whereOr1,$whereOr2){
                                    if(empty($whereOr2)){
                                            $query->whereOr($whereOr1);
                                    }else if(empty($whereOr1)){
                                            $query->whereOr($whereOr2);
                                    }else{
                                            $query->where(function ($query) use($whereOr1){
                                                    $query->whereOr($whereOr1);
                                            })->where(function ($query) use($whereOr2){
                                                    $query->whereOr($whereOr2);
                                            });
                                    }
                            })
                            ->group('gm.uid')
                            ->count();
            }
    }else{
            if(empty($whereOr1) && empty($whereOr2)){
                    return $this
                            ->where($where)
                            ->count();
            }else{
                    return $this
                            ->where($where)
                            ->where(function ($query)use($whereOr1,$whereOr2){
                                    if(empty($whereOr2)){
                                            $query->whereOr($whereOr1);
                                    }else if(empty($whereOr1)){
                                            $query->whereOr($whereOr2);
                                    }else{
                                            $query->where(function ($query) use($whereOr1){
                                                    $query->whereOr($whereOr1);
                                            })->where(function ($query) use($whereOr2){
                                                    $query->whereOr($whereOr2);
                                            });
                                    }
                            })->count();
            }
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
     * 根据搜索条件获取所有的用户数量(多人跟进)
     * @param $where
     */
    public function getAllUsersVisit($where,$whereOr){
        if(!empty($whereOr)){
            return  Db::name('user_gm')
            		->alias('gm')
            		->join('__USER_MEMBER__ u','gm.uid=u.uid','LEFT')
                    ->where($where)
                    ->where(function ($query)use($whereOr){
                        $query->whereOr($whereOr);
                    })->count();
        }else{
            return  Db::name('user_gm')
                    ->alias('gm')
            		->join('__USER_MEMBER__ u','gm.uid=u.uid','LEFT')
                    ->count();
        }
    }
    public function checkUser($param){
        if($param['mobile']){
            $user= $this->where('gid',$param['gid'])->where('mobile',$param['mobile'])->find();
            if($user) $userinfo=$user->data;
            else $userinfo='';
            if($userinfo){
                if(!$userinfo['realname']) $udata['realname']=$param['realname'];
                if(!$userinfo['tpid']) $udata['tpid']=$param['tpid'];
                if(!$userinfo['address']) $udata['address']=$param['address'];
                if(!$userinfo['contact']) $udata['contact']=$param['contact'];
                if(!$userinfo['x']) $udata['x']=$param['x'];
                if(!$userinfo['y']) $udata['y']=$param['y'];    		
                if($userinfo['guid']){
                    if($userinfo['guid']==$param['guid']){
                        if($udata) $this->where('uid',$userinfo['uid'])->update($udata);
                        return ['code' => 0, 'data' => ['uid'=>$userinfo['uid']], 'msg' => '用户已添加，点击详情可以修改相关信息'];
                    }else{
                        $map['guid']=$param['guid'];
                        $map['uid']=$userinfo['uid'];
                        $user_gm=Db::name('user_gm')->where($map)->find();
                        if($user_gm){
                            if($udata) $this->where('uid',$userinfo['uid'])->update($udata);
                            return ['code' => 0, 'data' => ['uid'=>$userinfo['uid']], 'msg' => '添加用户成功'];
                        }else{
                            $realname= Db::name('group_member')->where('uid',$userinfo['guid'])->value('realname');
                            return ['code' => 1, 'data' => '', 'msg' =>'该客户资料已经添加，客户在【'.$realname.'】名下请联系管理员授权'];
                        }
                    }
                }else{
                    $udata['guid']=$param['guid'];
                    $this->where('uid',$userinfo['uid'])->update($udata);
                    $this->addgjr($userinfo['uid'],$param['guid']);
                    return ['code' => 0, 'data' => ['uid'=>$userinfo['uid']], 'msg' => '添加用户成功'];
                }
            }else{
                return ['code'=>2];
            }
        }else{
            return ['code'=>2];
        }
    }
    /**
     * 添加客户信息
     * @param $param
     */
    public function addUser($param){
        try{
//        	if($this->where('gid',$param['gid'])->where('mobile',$param['mobile'])->count()){
//        		// 验证失败 输出错误信息
//        		return ['code' => 1, 'data' => '', 'msg' =>'该客户资料已经添加，如客户信息没有在你的名下请联系管理员'];
//        	}
            $chuser=$this->checkUser($param);
            if($chuser['code'] != 2){
                return $chuser;
            }
            $result =  $this->validate()->save($param);
            if(false === $result){
                // 验证失败 输出错误信息
                return ['code' => 1, 'data' => '', 'msg' => $this->getError()];
            }else{
            	//新增跟进人
            	$this->addgjr($this->uid,$param['guid']);
                return ['code' => 0, 'data' => ['uid'=>$this->uid], 'msg' => '添加用户成功'];
            }
        }catch( PDOException $e){
            return ['code' => 1, 'data' => '', 'msg' => $e->getMessage()];
        }
    }
    /**
     * 添加跟进人
     * @param $param
     */
    public function addgjr($uid,$guid){
    	if($guid){
            $isguid=Db::name('user_gm')->where('gid',session('gid'))->where('uid',$uid)->where('guid',$guid)->count();
            if(!$isguid){
                $data['gid']=session('gid');
                $data['uid']=$uid;
                $data['guid']=$guid;
                $data['adate']=time();
                Db::name('user_gm')->insert($data);
            }
    	}
    }
    /**
     * 编辑客户信息
     * @param $param
     */
    public function editUser($param){
        try{
            if($this->where('gid',$param['gid'])->where('mobile',$param['mobile'])->where('uid','neq',$param['id'])->count()){
                // 验证失败 输出错误信息
                return ['code' => 1, 'data' => '', 'msg' =>'手机号码已经存在，如客户信息没有在你的名下请联系管理员'];
            }
            $result =  $this->save($param, ['uid' => $param['uid']]);
            if(false === $result){
                // 验证失败 输出错误信息
                return ['code' => 1, 'data' => '', 'msg' => $this->getError()];
            }else{
            	//新增跟进人
            	$this->addgjr($param['uid'],$param['guid']);
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
    public function getOneUser($gid,$uid){
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
    public function delUser($id){
        try{
            $this->where('uid', $id)->delete();
            return ['code' => 1, 'data' => '', 'msg' => '删除管理员成功'];
        }catch( PDOException $e){
            return ['code' => 0, 'data' => '', 'msg' => $e->getMessage()];
        }
    }
}