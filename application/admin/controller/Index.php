<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/5
 * Time: 10:30
 */

namespace app\admin\controller;
use think\Db;
use think\Request;

class Index extends Base{
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->initData = [
            'sign'=>$this->sign,
            'requestFunc'=>$this->requestFunc,
            'requestUrl'=>$this->requestUrl,
            'cUrl'=>$this->cUrl,
            'jsName'=>$this->jsName,
        ];
        $this->assign('initData',$this->initData);
    }
    public function index_home(){
        //总订单和今日订单
        $todaystart = strtotime(date('Y-m-d'.'00:00:00',time()));
        $todayend = strtotime(date('Y-m-d'.'00:00:00',time()+3600*24));

        $order=Db::name('order')->where(array('gid'=>$this->gid))->count();
        $whereO['gid']=['=',$this->gid];
        $whereO['adate']=['BETWEEN',[$todaystart,$todayend]];
        $todayorder=Db::name('order')->where($whereO)->count();
        //总客户和今日客户
        $users=Db::name('user_member')->where(array('gid'=>$this->gid))->count();
        $whereU['gid']=['=',$this->gid];
        $whereU['regtime']=['BETWEEN',[$todaystart,$todayend]];
        $todayuser=Db::name('user_member')->where($whereU)->count();
        //员工人数
        $groups=Db::name('group_member')->where(array('gid'=>$this->gid))->count();
        //客户数量排行
        $usersnum=[];
        $num=Db::name('group_member')->field('uid,gid,realname')->where(array('gid'=>$this->gid))->group('uid')->select();
        foreach ($num as $k=>$v){
            $count=Db::name('user_member')->field('uid,realname')->where(array('gid'=>$this->gid,'guid'=>$v['uid']))->count();
            $v['num']=$count;
            $userscount[]=$v;
            //排序
            foreach ($userscount as $key=>$value){
                $price[$key] = $value['num'];
            }
            array_multisort($price,SORT_NUMERIC,SORT_DESC,$userscount);
            $usersnum=array_slice($userscount, 0,6);
        }
        $this->assign('users',$users);
        $this->assign('todayuser',$todayuser);
        $this->assign('order',$order);
        $this->assign('todayorder',$todayorder);
        $this->assign('groups',$groups);
        $this->assign('usersnum',$usersnum);


        //-----------------统计金额图标 计算
        $curMonth = date('n');
        $months = range($curMonth-12,$curMonth);
        $monthsData = '';
        $xaxis = '';
        $yaxis = '';//这个需要根据订单金额按一定的规则进行计算;我们就以订单最大值为基准
        $max = 0;
        $sum = 0;
        $unit = '10000,\'万\'';
        $i = 1;
        $j = 1;
        //查询一年的时间中每个月的总金额
        foreach($months as $key=>$value){
            //根据月份值创建时间，创建一个时间区间
            $s = mktime(0, 0, 0, $value, 1);
            $e = mktime(23, 59, 59, $value + 1, 0);
            $t = $this->orderData($s,$e);
            if(!$t){
                $t = 0;
            }
            $sum += $t;
            if($max<=$t){
                $max = $t;
            }
            $m = date('Y/m',$s);
            $xaxis .= '[' . $i++ .',\''. $m . '\'],';
            $monthsData .= '[' . $j++ . ',' . $t . '],';
        }
        //这里设定8格子每一个格子
        if($max > 8 * pow(10,8)){
            $unit = '100000,\'十万\'';
            $pieces_u = '十万';
            $pieces_n = ceil($max / 8);
            $pieces_x = ceil($max / 8 /100000);
            for($i=1; $i <= 8; $i++){
                $yaxis .= '[' . $pieces_n * $i .',\''. $pieces_x * $i .$pieces_u .'\'],';
            }
        }else if($max > 8 * pow(10,4) && $max <= 8 * pow(10,8)){
            $unit = '10000,\'万\'';
            $pieces_u = '万';
            $pieces_n = ceil($max / 8);
            $pieces_x = ceil($max / 8 /10000);
            for($i=1; $i <= 8; $i++){
                $yaxis .= '[' . $pieces_n * $i .',\''. $pieces_x * $i .$pieces_u .'\'],';
            }
        }else{
            $unit = '1,\'\'';
            $pieces_u = '';
            $pieces_n = ceil($max / 8);
            $pieces_x = ceil($max / 8);
            for($i=1; $i <= 8; $i++){
                $yaxis .= '[' . $pieces_n * $i .',\''. $pieces_x * $i .$pieces_u .'\'],';
            }
        }
        $this->assign('unit',$unit);
        $this->assign('sum',$sum);
        $this->assign('xaxis', $xaxis);
        $this->assign('yaxis', $yaxis);
        $this->assign('monthsData',$monthsData);
//        显示公司详情页面
        return $this->fetch('index/index_home');
    }
    private function orderData($s,$e){
        //order 求金额总和
        $whereArr = [];
        //确认订单
        $whereArr['confirm']=['=',1];
        //付款状态
        $whereArr['pay']=['=',1];
        //所属gid
        $whereArr['gid']=['=',$this->gid];
        //时间范围
        $whereArr['adate']=['BETWEEN',[$s,$e]];
        //查询订单金额
        $orderSum =Db::name('order')->where($whereArr)->sum('total');
        return $orderSum;
    }
}