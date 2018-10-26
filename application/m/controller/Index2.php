<?php
// +----------------------------------------------------------------------
// | snake
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 http://ljk.cc All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: itkk <it_kk@qq.com>
// +----------------------------------------------------------------------
namespace app\m\controller;
use app\m\model\GroupMember;
use app\m\model\OrderGoods;
use think\wechatsdk\Api;
use think\Db;
class Index extends Base
{
    public function index(){ 
        $this->initAppData();
        return $this->fetch('/index');
    }
    
    private function initAppData(){
        $this->indexData();
        $this->homeData();
        $this->driverData();
    }
    private function driverData(){
        $this->assign('order_dispatch_status',config('order_dispatch_status'));
    }
    private function indexData(){
        //根据节点获取系统信息（系统：,功能应用，）
        //通过节点id查询到对应的系统名称
        //查询出关联的系统 id
        //Db::name(xxxx)->where('field',['IN',$rules])->field(xxxx);
        $memberaccess = trim(session('memberaccess'));
        if(!$memberaccess){
          //  $this->redirect('login/loginout');
        }
        $res = json_decode($memberaccess);
        
        $rules = [];
        foreach ($res->rules as $r){
        	$rules[]=$r;
        }
        $whereGAR = [];
        $whereGAR['m'] = 1;
        $whereGAR['id'] = ['IN',$rules];
        $mRules = Db::name('group_auth_rule')->where($whereGAR)->select();
        $entrance = [];
        $quickNodes = [];
        $quick = [];
        foreach($mRules as $v){
            $this->assign($v['url_name'],$v);
            if($v['type'] == 0){
                if($qk1 = trim(trim($v['quick_nodes']),',')){
                    $qk2 = explode(',',$qk1);
                    $qk2 = array_intersect($qk2, $rules);
                    if(!empty($qk2)){
                        $whereGAR = [];
                        $whereGAR['id'] = ['IN',$qk2];
                        $v['quick_nodes'] =
                        Db::name('group_auth_rule')
                        ->where($whereGAR)
                        ->select();
                    }
                }
                $entrance[] = $v;
                continue;
            }else if($v['type'] == 2){
                if($q1 = trim(trim($v['quick_nodes']),',')){
                    $q2 = explode(',',$q1);
                    $q2 = array_intersect($q2, $rules);
                    if(!empty($q2)){
                        $whereGAR = [];
                        $whereGAR['id'] = ['IN',$q2];
                        $v['quick_nodes'] =
                        Db::name('group_auth_rule')
                        ->where($whereGAR)
                        ->select();
                    }
                }
                $quick[]=$v;
            }
            $this->assign($v);
        }
        if($this->gid==133){
        //	print_r($quick);
        }
        $gUser = $this->gUser();
        $config = $this->wxConfig();
        $this->assign('wxconfig',$config);
        $this->assign('entrance',$entrance);
        $this->assign('gUser',$gUser);
        $this->assign('quick',$quick);
        $this->assign('groupinfo',session('groupinfo'));
    }
    private function gUser(){
    	$map['gid'] = session('gid');
    	$map['uid'] = session('guid');
    	$map['status'] = 1;
    	$gUser = Db::name('group_member')->where($map)->find();
    	if(!$gUser){
    		$this->redirect(url('m/login/loginout'));
    	}
    	if(time()-$gUser['lastvisit']>86400){
    		//更新登录时间
    		Db::name('group_member')->where($map)->update(['lastvisit'=>time()]);
    		Db::name('group_member')->where($map)->setInc('landnum');
    		//更新到期时间
    		$expDate = Db::name('group_account')->where('gid',$gUser['gid'])->where('guid',$gUser['uid'])->value('exp_date');
    		session('group_account_expdate',$expDate);
    	}
    	$gUser['exp_date'] = date('Y-m-d',session('group_account_expdate'));
    	if($gUser['img']) $gUser['img']=mkurl(['url'=>$gUser['img']]);
    	return $gUser;
    }
    private function homeData(){ 
        if(input('oid')){
            session('oid',input('oid'));
            $this->redirect('#dingdanxiangqing');
        }
        $map2['gid'] = session('gid');
        $map2['status'] = 1;
        $map2['guid'] = session('guid');
        $date6=date('Y-m-d',strtotime('-6days'));
        $orderlist=Db::name('order')->where($map2)->whereTime('adate','>',$date6)->field("FROM_UNIXTIME(adate,'%Y-%m-%d') days,sum(total) count,count(oid) num")->group('days')->select();
        $tj['zuser']=Db::name('user_gm')->where($map2)->count();
        $tj['bfts']=Db::name('user_post')->where($map2)->whereTime('adate', 'today')->count();
        if($tj['bfts']==0) $tj['bfuser']=0;
        else $tj['bfuser']=Db::name('user_post')->where($map2)->whereTime('adate', 'today')->count('DISTINCT uid');
        //	echo $tj['bfuser'];
        $yorder=Db::name('order')->where($map2)->whereTime('adate', 'month')->field("sum(total) count,count(oid) num")->select();
        $map3['o.gid'] = session('gid');
        $map3['o.status'] = 1;
        $map3['o.guid'] = session('guid');
        $yorderlist=Db::name('order')->alias('o')->join('ljk_order_goods og','o.oid = og.oid')->where($map3)->whereTime('o.adate', 'month')->field("og.name,sum(og.num) count,og.unit")->group('name,unit')->order('count desc')->limit(6)->select();
        $goodsdatatitle='';
        $goodsdata='';
        foreach($yorderlist as $order){
            $goodsdatatitle.="'".$order['name']."',";
            $goodsdata.="{value:".$order['count'].", name:'".$order['name']."(".$order['unit'].")'},";
        }
        $goodsdatatitle="[".substr($goodsdatatitle,0,-1)."]";
        $goodsdata=substr($goodsdata,0,-1);
        $yuser=Db::name('user_member')->where($map2)->whereTime('regtime', 'month')->field("count(uid) num")->select();
        $userlist=Db::name('user_member')->where($map2)->whereTime('regtime','>',$date6)->field("FROM_UNIXTIME(regtime,'%Y-%m-%d') days,count(uid) num")->group('days')->select();
        //自定义周数组
        $weekArr=array("周日","周一","周二","周三","周四","周五","周六");
        $j=0;
        $ww='';
        for($i=-6;$i<=0;$i++){
            $week=$weekArr[date("w",strtotime($i.'days'))];
            $ww.="'".$week."',";
            $jes[$j]['days']=date('Y-m-d',strtotime($i.'days'));
            $jes[$j]['count']=0;
            $jes[$j]['ordernum']=0;
            $jes[$j]['usernum']=0;
            //		echo $j;
            $j++;
        }
        $ww="[".substr($ww,0,-1)."]";
        foreach($jes as $key=>$je){
            $tj['bzje']=0;
            $tj['bzsl']=0;
            foreach($orderlist as $order){
                $tj['bzje']+=$order['count'];
                $tj['bzsl']+=$order['num'];
                if($order['days']==$je['days']&&$order['count']){
                    $je['count']=$order['count'];
                    $je['ordernum']=$order['num'];
                }
            }
            $tj['bzuser']=0;
            foreach($userlist as $user){
                $tj['bzuser']+=$user['num'];
                if($user['days']==$je['days']&&$user['num']){
                    $je['usernum']=$user['num'];
                }
            }
            $jess=''; $ordernum=''; $usernum='';
            $jess.=$je['count'].',';
            $ordernum.=$je['ordernum'].',';
            $usernum.=$je['usernum'].',';
            if($key==6){
                $tj['jrje']=$je['count'];
                $tj['jrsl']=$je['ordernum'];
                $tj['jruser']=$je['usernum'];
            }
        }
        $tj['jrje']=sprintf("%.2f",$tj['jrje']);
        $tj['bzje']=sprintf("%.2f",$tj['bzje']);
        if($tj['jrsl']!=0) $tj['kdj']=sprintf("%.2f",$tj['jrje']/$tj['jrsl']);
        else $tj['kdj']=0;
        if($yorder[0]['count']) $tj['byje']=$yorder[0]['count']; else $yorder[0]['count']=0;
        $tj['bysl']=$yorder[0]['num'];
        $tj['byuser']=$yuser[0]['num'];
        $jess="[".substr($jess,0,-1)."]";
        $ordernum="[".substr($ordernum,0,-1)."]";
        $usernum="[".substr($usernum,0,-1)."]";
        //获取客户类型数据
        $visits = Db::name('user_visit')->field('id,title')->where('gid',$this->gid)->where('status',1)->order('displayorder DESC')->select();
        $userMemberType = Db::name('user_type')->field('id,status,title')->where('gid',$this->gid)->where('status',1)->select();
        $this->assign('userMemberType',$userMemberType); 
        $this->assign('visits',$visits);
        //print_r($tj);
        $this->assign('ww',$ww);
        $this->assign('jes',$jess);
        $this->assign('goodsdatatitle',$goodsdatatitle);
        $this->assign('goodsdata',$goodsdata);
        $this->assign('ordernum',$ordernum);
        $this->assign('usernum',$usernum);
        $this->assign('tj',$tj);
        $this->assign('oid',session('oid'));
        $this->assign('groupinfo',session('groupinfo'));
    }
   //编辑用户信息
    public function updateMember(){
        $mobile = input("param.mobile");
        $email = input("param.email");
        $qq = input("param.qq");
        if($mobile) $map['mobile'] = $mobile;
        if($email) $map['email'] = $email;
        if($qq) $map['qq'] = $qq;
		$GroupMember = new GroupMember;
        $hasUser = $GroupMember->save($map,['uid' => session('guid')]);
/*        if(empty($hasUser)){
            return json(['code' => -1, 'data' => '', 'msg' => '管理员不存在']);
        }
		$password=md5($password).$hasUser['salt'];
        if(md5($password) != $hasUser['password']){
            return json(['code' => -2, 'data' => '', 'msg' => '密码错误']);
        }
        if(1 != $hasUser['status']){
            return json(['code' => -6, 'data' => '', 'msg' => '该账号被禁用']);
        }*/
        return json(['code' => 1, 'url' => url('index/index'), 'msg' => '更新成功']);
    }
	
	//编辑用户密码
    public function updatePassword()
    {
		$oldpwd = input("param.oldpwd");
        $pwd = input("param.pwd");
        $pwdconfirm = input("param.pwdconfirm");
        if($pwd != $pwdconfirm){
                $tip= ['code'=>0,'msg'=>'修改失败！两次输入的密码不一致！'];
                return json($tip);
            }
		$GroupMember = new GroupMember;
        if(!$GroupMember->checkGroupMemberPassword(session('guid'),$oldpwd)){
                $tip= ['code'=>0,'msg'=>'修改失败！旧密码不正确！'];
                return json($tip);
            }		
        if($GroupMember->updateGroupMemberPassword(session('guid'),$pwd)){
                $tip= ['code'=>1,'url' => url('login/loginOut'),'msg'=>'修改密码成功！'];
                return json($tip);
            }		
	}
   
}
