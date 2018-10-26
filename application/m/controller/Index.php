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
        $this->homeData();
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
        $m_quick_bbch=2;
        $this->assign('m_quick_bbch',$m_quick_bbch);
        $m_index_home=3;
        $this->assign('m_index_home',$m_index_home);
        $m_user_index=4;
        $this->assign('m_user_index',$m_user_index);
        $cx_index_index=5;
        $this->assign('cx_index_index',$cx_index_index);
        $m_order_index=6;
        $this->assign('m_order_index',$m_order_index);
        $m_order_editgoods=7;
        $this->assign('m_order_editgoods',$m_order_editgoods);
        $m_rg_index=9;
        $this->assign('m_rg_index',$m_rg_index);
        $m_rg_org=10;
        $this->assign('m_rg_org',$m_rg_org);
        $m_user_subuser=11;
        $this->assign('m_user_subuser',$m_user_subuser);
        $queryparam_usersub=12;
        $this->assign('queryparam_usersub',$queryparam_usersub);

        $this->assign('usernum',$usernum);
        $this->assign('tj',$tj);
        $this->assign('oid',session('oid'));
        $this->assign('groupinfo',session('groupinfo'));
    }
}
