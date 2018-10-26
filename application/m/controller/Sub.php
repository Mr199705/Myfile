<?php
namespace app\m\controller;
use think\Db;
class Sub extends Base{
    //put your code here
    private $subgms = [];
    public function index(){
        $index = controller('Index'); 
        return $index->home();
    }
    //下属拜访客户工作记录列表
    public function subordinatefollowrecord(){
        if(request()->isAjax() && request()->isPost()){
            $param = input('post.');
            $guid = $this->guid;
            $this->getsubgm($guid);
            $subgms = $this->subgms;
            $subgmsguids = [];
            $whereP['p.gid'] = $this->gid;
            foreach($subgms as $v){
                if($v['guid'] !== $this->guid){
                    $subgmsguids[] = $v['guid'];
                }
            }
            if(isset($param['subguid']) && !! ($subguid = trim($param['subguid']))){
                if(in_array($subguid,$subgmsguids)){
                    $whereP['p.guid'] = $subguid;
                }else{
                    return json(['code'=>0,'total'=>0,'p'=>1,'data'=>null,'msg'=>'数据加载成功']);
                }
            }else{
                if(empty($subgmsguids)){
                    return json(['code'=>0,'total'=>0,'p'=>1,'data'=>null,'msg'=>'数据加载成功']);
                }
                $whereP['p.guid'] = ['IN',$subgmsguids];
            }
            $p = $param['p'];
            $limit = $param['limit'];
            $during = $this->setInterval($param);
            $s = $during['start'];
            $e = $during['stop'];
            $whereP['adate'] = ['BETWEEN',[$s,$e]];
            return json($this->visitRecordList($whereP, $p, $limit));
        }else{
            return json(['code'=>-1,'msg'=>'非法操作！']);
        }
    }
    public function subordinatedkrecord(){
        if(request()->isAjax() && request()->isPost()){
            $param = input('post.');
            $this->getsubgm($this->guid);
            $subgmsguids = [];
            foreach($this->subgms as $v){
                if($v['guid'] != $this->guid){
                    $subgmsguids[] = $v['guid'];
                }
            }
            $whereDK['dk.gid'] = $this->gid;
            if(isset($param['subguid']) && !!($subguid = trim($param['subguid']))){
                if(in_array($subguid,$subgmsguids)){
                    $whereDK['dk.uid'] = $subguid;
                }else{
                    return json(['code'=>0,'total'=>0,'p'=>1,'data'=>null,'msg'=>'数据加载成功']);
                }
            }else{
                if(empty($subgmsguids)){
                    return json(['code'=>0,'total'=>0,'p'=>1,'data'=>null,'msg'=>'数据加载成功']);
                }
                $whereDK['dk.uid'] = ['IN',$subgmsguids];
            }
            $p = $param['p'];
            $limit = $param['limit'];
            $offset = ( $p - 1) * $limit;
            $during = $this->setInterval($param);
            $s = $during['start'];
            $e = $during['stop'];
            $whereDK['unix_timestamp(dk.dkdt)'] = ['BETWEEN',[$s,$e]];
            if(isset($param['t']) && !!(trim($param['t']))){
                $whereDK['dk.dktype'] = trim($param['t']);
            }
            $total = Db::name('oa_kqdkjl dk')->where($whereDK)->count();
            $data = Db::name('oa_kqdkjl dk')
                    ->field('dk.*,gm.realname')
                    ->where($whereDK)
                    ->join('group_member gm','dk.uid=gm.uid','LEFT')
                    ->order('dk.dkdt DESC')
                    ->limit($offset,$limit)
                    ->select();
            if(count($data) !== 0){
                $res = ['code'=>1,'total'=>$total,'p'=>$p,'data'=>$data,'msg'=>'数据加载成功'];
            }else{
                $res = ['code'=>0,'total'=>$total,'p'=>$p,'data'=>null,'msg'=>'数据加载成功'];
            }
            return json($res);
        }else{
            return json(['code'=>-1,'msg'=>'非法操作！']);
        }
    }
    private function visitRecordList($whereP,$p,$limit){
        $offset = ($p - 1) * $limit;
        $data = Db::name('user_post')
                ->alias('p')
                ->field('p.id,p.imgsid,p.content,p.adate,p.xm_title,m.realname,u.uid uid,u.realname urealname,u.address uaddress,u.mobile umobile')
                ->where($whereP)
                ->join('group_member m','p.guid=m.uid','LEFT')
                ->join('user_member u','p.uid=u.uid','LEFT')
                ->limit($offset,$limit)
                ->order('p.adate desc')
                ->select();
        $total = Db::name('user_post p')
                ->where($whereP)
                ->count();
        $unum = Db::name('user_post p')
                ->where($whereP)
                ->group('uid')
                ->count();
        //处理获取的$data更改时间格式，获取图片附件url
        foreach($data as $k=>$v){
            $data[$k]['adate'] = date('Y-m-d H:i:s',$v['adate']);
            $imgsid = explode('_',$v['imgsid']);
            $unique  = array_values(array_unique($imgsid));
            $imgsurl = [];
            $imgsurlu = [];
            for($i=0;$i<count($unique);$i++){
                $imgsurlu[$unique[$i]] = mkurl(Db::name('file')->field('savename,savepath,ext,url')->find($unique[$i]));
            }
            for($i=0;$i<count($imgsid);$i++){
                $imgsurl[] = $imgsurlu[$imgsid[$i]];
            }
            $data[$k]['imgsurl'] = $imgsurl;
            //获取位置信息
            $pid = $v['id'];
            $lbs = Db::name('group_memberlbs')->field('id,business,street,street_number,province,city,district,address,upnum')->where('pid',$pid)->where('type',0)->find();
            if(!empty($lbs)){
                if(!$lbs['address']&&$lbs['address']<5){
                    $lbs=updategps($lbs['id']);
                }
                $data[$k]['lbs'] = $lbs;
            }else{
                $data[$k]['lbs'] = null;
            }
        }
        if(empty($data)){
            return ['code'=>0,'total'=>$total,'p'=>$p,'data'=>['data'=>$data,'unum'=>$unum],'msg'=>'没有数据！'];
        }else{
            return ['code'=>1,'total'=>$total,'p'=>$p,'data'=>['data'=>$data,'unum'=>$unum],'msg'=>'加载成功！'];
        }
    }   
    private function setInterval($param){
        //时间以csdate,cedate为准
        $s = str_replace('T','',trim($param['s']));
        $e = str_replace('T','',trim($param['e']));
        $n = date('Y-m-d H:i:s');
        $s = !!$s ? $s : date('Y-m-d');
        $e = !!$e ? $e : $n;
        $start = strtotime($s);
        $stop = strtotime($e);
        return ['start'=>$start,'stop'=>$stop];
    }
    //下属订单产生记录列表
    protected function getsubgm($guid=0){
        $subgms = Db::name('group_member')
            ->field('uid guid,realname,superid')
            ->where('gid',$this->gid)
            ->where('uid','NEQ',$guid)
            ->where(function ($query) use($guid){
                $query
                ->whereOr(function ($query) use($guid){
                    $x['superid'] = ['LIKE', $guid . ',%'];
                    $query->whereOr($x);
                })
                ->whereOr(function ($query) use($guid){
                    $x['superid'] = ['LIKE','%,' . $guid . ',%'];
                    $query->whereOr($x);
                })
                ->whereOr(function ($query) use($guid){
                    $x['superid'] =  ['EQ',$guid];
                    $query->whereOr($x);
                })
                ->whereOr(function ($query) use($guid){
                    $query->whereOr('superid',['LIKE','%,' . $guid]);
                }
                );
            })
            ->select();
        if(!!$subgms){
            foreach($subgms as $v){
                if(!isset($this->subgms[$v['guid']])){
                    $this->subgms[$v['guid']] = $v;
                    //$this->getsubgm($v['guid']);//需要多级查看下属时开启
                }
            }
        }
    }
}
