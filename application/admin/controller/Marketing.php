<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/9/26
 * Time: 17:24
 */
namespace app\admin\controller;
use think\Request;
use think\Db;
class Marketing extends Base
{
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->initData = [
            'sign' => $this->sign,
            'requestFunc' => $this->requestFunc,
            'requestUrl' => $this->requestUrl,
            'cUrl' => $this->cUrl,
            'jsName' => $this->jsName,
        ];
        $this->assign('initData', $this->initData);
    }

    public function index(){
        if (request()->isAjax()) {
            $whereM['gid'] = $this->gid;
            $status=input('isclose');
            if($status){
                if($status==1){
                    $whereM['status']=['eq',1];
                }else{
                    $whereM['status']=['eq',0];
                }
            }
            $kt=input('keytype');
            $kw=input('keywords');
            if($kw){
                if($kt==1){
                    $whereM['title']=['LIKE','%'.$kw.'%'];
                }else if($kt ==2){
                    $whereM['goodsName']=['LIKE','%'.$kw.'%'];
                }
            }
            $rows=10;
            $res = Db::name('into')->where($whereM)
                ->order('id DESC')
                ->paginate($rows);
            $res2=[];
            $arr=[];
            foreach ($res as $k=>$v){
                $whereP['gid']=$this->gid;
                $whereP['sell_status'] = 1;
                $whereP['type'] = 1;
                $whereP['status'] = 1;
                $whereP['unitgid'] = $v['unitgid'];
                $whereP['baojia_id']=$v['bjid'];
                $whereP['id']=['in',$v['skuids']];
                $v['skus']=Db::name('baojia_sku')->field('attrs_value')->where($whereP)->select();

                $whereIr['intoid']=$v['id'];
                $whereIr['gid']=$v['gid'];
                $v['into_rules']=Db::name('into_rules')->where($whereIr)->order('level asc')->select();
                array_push($res2,$v);
            }
            if (!!$res2) {
                $pageInfo = pageInfo($res);
//                $market = $res->items();
                $market = $res2;
            } else {
                $pageInfo = false;
                $market = false;
            }
            $this->assign('pageInfo', $pageInfo);
            $this->assign('market', $market);
            return $this->fetch('/marketing/ajax/index');
        } else {
            return $this->fetch('/marketing/index');
        }
    }
    public function showgoodslist(){
        if (request()->isPost()) {
            $input = input('post.');
            if (isset($input['action'])) {
                $action = $input['action'];
            } else {
                $action = $input['action'] = input('action');
            }
            unset($input['action']);
            switch ($action) {
                case 'showgoodslist':
                    return $this->getGoodsList($input);
                case 'domarket_add':
                    return $this->domarket_add($input);
                case 'domarket_update':
                    return $this->domarket_update($input);
                default:
                    return null;
            }
        }
    }
    public function market_add()
    {
        $this->assign('category', $this->getCategoryList());
        $this->assign('into','');
        $this->assign('title','新增');
        return $this->fetch('/marketing/act/market_edit');
    }
    public function market_update(){
       $id=input('id');
        //编辑营销商品
        $intoList=Db::name('into')
                ->field('id,gid,bjid,title,goodsName,status,type,unitid,unitgid,unit,level,skuids')
            ->where('id',$id)->find();
        $whereBj['gid']=$this->gid;
        $whereBj['id']=$intoList['bjid'];
        $goods=Db::name('baojia')->field('id,goods_id,goodsName,seller_note,unitid,unitgid')->where($whereBj)->find();

//        into 和baojia_sku 的skuid差集
        $into_skuid=Db::name('into')->field('skuids')->where(array('gid'=>$this->gid,'bjid'=>$intoList['bjid']))->select();
        $arr=[];
        for($i = 0, $len = count($into_skuid); $i < $len; ++$i) {
            $arr[]= $into_skuid[$i]['skuids'];
        }
        //将skuids 以逗号组合
        $arrs= implode($arr, ',');
        $into_skuids=[];
        foreach (explode(',',$arrs) as $k){
            $into_skuids[]=intval($k);
        }
        $ids=Db::name('baojia_sku')->field('id')->where(array('gid'=>$this->gid,'baojia_id'=>$intoList['bjid']))->select();
        $baojia_sku_skuids = array_column($ids,'id');
        //得到连个的交集
        $new=[];
        foreach (array_diff($baojia_sku_skuids,$into_skuids) as $ke =>$va){
            $new[]=$va;
        }
        if(intval($intoList['skuids']) != 0){
            $str=implode(',',$new).','.$intoList['skuids'];
        }else{
            $str=0;
        }
        $whereSku['gid']=$this->gid;
        $whereSku['baojia_id']=$intoList['bjid'];
        $whereSku['id']=['in',$str];

        $goods['skus']=Db::name('baojia_sku')->field('id,title,attrs_value')->where($whereSku)->order('id asc')->select();

        $whereUn['gid']=$this->gid;
        $whereUn['unitgid']=$intoList['unitgid'];
        $goods['units']=Db::name('unit')->field('id,uname,title')->where($whereUn)->order('id asc')->select();
        $goods['unit_title']=Db::name('unit')->where($whereUn)->value('title');

        $count_sku=sizeof(explode(',',$intoList['skuids']));

        $whereIr['gid']=$this->gid;
        $whereIr['intoid']=$intoList['id'];
        $intorules=Db::name('into_rules')->where($whereIr)->order('level asc')->select();
        $this->assign('goodsInfo',$goods);
        $this->assign('category', $this->getCategoryList());
        $this->assign('into',$intoList);
        $this->assign('intorules',$intorules);
        $this->assign('count_rule',count($intorules));

        $this->assign('count_rule',$count_sku);
        $this->assign('title','编辑');
        return $this->fetch('/marketing/act/market_edit');
    }
    /**
     * @param array $input
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 执行增加营销商品操作
     */
    private function domarket_add($input = []){
        $bjid=intval($input['bjid']);
        if($input['title'] == ''){
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '请填写分销标题！';
            $arrtips['close'] = ['sign'=>true];
            return $this->tips($arrtips);
        }
        if(intval($input['bjid']) == 0 ||intval($input['skuid_status']) == 0){
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '请选择至少一种商品属性添加分销！';
            $arrtips['close'] = ['sign'=>true];
            return $this->tips($arrtips);
        }
        if(intval(current($input['levelprice'] )) <= 0){
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '分销商品,至少填写一个分销介个且设置金额至少大于0！';
            $arrtips['close'] = ['sign'=>true];
            return $this->tips($arrtips);
        }
        $skuids_all=implode(",", input('skuid/a'));
        if($skuids_all !=0){
            $skuids=$skuids_all;
        }else{
            $skuids=0;
            $whereP['gid']=$this->gid;
            $whereP['bjid']=$input['bjid'];
            $whereP['skuids']=['in',$skuids];
            $into_count=Db::name('into')->where($whereP)->count();
            if($into_count != 0){
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = '该商品属性已参加分销！';
                $arrtips['close'] = ['sign'=>true];
                return $this->tips($arrtips);
            }
        }
        if(floatval($input['unitid']) !=0){
            $into=[
                'gid'=>$this->gid,
                'title'=>$input['title'],
                'level'=>3,
                'bjid'=>$bjid,
                'goodsName'=>$input['goods_name'],
                'status'=>1,
                'type'=>0,
                'unitid'=>$input['unitid'],
                'unitgid'=>$input['unitgid'],
                'unit'=>$input['unit'],
                'skuids'=>$skuids
            ];
            //添加
            $into_status=Db::name('into')->insert($into);
            $whereF['gid']=$this->gid;
            $whereF['bjid']=$bjid;
            $whereF['type']=0;
            $whereF['unitgid']=$input['unitgid'];
            $whereF['unitid']=$input['unitid'];
            $whereF['skuids']=$skuids;

            $into_id=Db::name('into')->where($whereF)->value('id');
            $into_rules_status='';
            foreach ($input['levelprice'] as $key=> $val){
                if(floatval($val) !=0) {
                    $into_rules=[
                        'gid'=>$this->gid,
                        'intoid'=>$into_id,
                        'level'=>$key,
                        'into'=>$val,
                        'type'=>0,
                        'status'=>1,
                        'rtype'=>0
                    ];
                    $into_rules_status=Db::name('into_rules')->insert($into_rules);
                }
            }
            if($into_status && $into_rules_status){
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = '添加营销商品成功！';
                $arrtips['close'] = ['sign'=>true];
                return $this->tips($arrtips);
            }
        }
    }
    /**
     * @param array $input
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * 执行修改营销操作
     */
    private function domarket_update($input = [])
    {
        $bjid = intval($input['bjid']);
        $into_id = intval($input['into_id']);
        if ($input['title'] == '') {
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '请填写分销标题！';
            $arrtips['close'] = ['sign' => true];
            return $this->tips($arrtips);
        }
        if (intval($input['bjid']) == 0  || intval($input['skuid_status'])== 0) {
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '请至少选择一种商品属性！';
            $arrtips['close'] = ['sign' => true];
            return $this->tips($arrtips);
        }
        if (intval(current($input['levelprice'])) <= 0) {
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '分销商品,至少添加一个分销且设置金额至少大于0！';
            $arrtips['close'] = ['sign' => true];
            return $this->tips($arrtips);
        }
        $skuids_all=implode(",", input('skuid/a'));
        if($skuids_all !=0){
            $skuids=$skuids_all;
        }else{
            $skuids=0;
        }
        if (floatval($input['unitid']) != 0) {
            $into = [
                'title' => $input['title'],
                'bjid' => $bjid,
                'goodsName' => $input['goods_name'],
                'type' => 0,
                'unitid' => $input['unitid'],
                'unitgid' => $input['unitgid'],
                'unit' => $input['unit'],
                'skuids' => $skuids
            ];
            //编辑
            $whereInto['gid'] = $this->gid;
            $whereInto['bjid'] = $bjid;
            $whereInto['id'] = $into_id;
            Db::name('into')->where($whereInto)->update($into);
            $in_rule=Db::name('into_rules')->where(array('gid'=>$this->gid,'intoid'=>$into_id))->delete();
            if($in_rule){
                foreach ($input['levelprice'] as $key=> $val){
                    if($val!=0){
                        $into_rules=[
                            'gid'=>$this->gid,
                            'intoid'=>$into_id,
                            'level'=>$key,
                            'into'=>$val,
                            'type'=>0,
                            'status'=>1,
                            'rtype'=>0
                        ];
                        Db::name('into_rules')->insert($into_rules);
                    }
                }
            }
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '编辑营销商品成功！';
            $arrtips['close'] = ['sign' => true];
            return $this->tips($arrtips);
        }
    }
    private function getGoodsList($input = []){
        // 排序
        $into_id = isset($input['into_id']) ? intval($input['into_id']) : 0;
        $p = isset($input['p']) && (intval($input['p']) > 0) ? intval($input['p']) : 1;
        $limit = isset($input['limit']) && (intval(trim($input['limit'])) > 0) ? intval(trim($input['limit'])) : 10;
        $limits = ($p - 1) * $limit;
        $sort = 'bj.id';
        $sortT = 'DESC';
        $whereB = [];
        $whereB['bj.gid'] = $this->gid;
        $whereB['bj.sell_status'] = 1;
        $whereB['bj.status'] = 1;
        $whereB['bj.unitgid'] = ['NEQ', 0];
        $cid = isset($input['cid']) ? $input['cid'] : 0;
        $bjidss = '';
        if ($cid) {
            $whereC['gid'] = $this->gid;
            $whereC['id'] = $cid;
            $whereC['is_show'] = 1;
            $whereC['pid'] = 0;
            $c = Db::name('category')->where($whereC)->count();
            if ($c) {
                //说明是一级分类
                unset($whereC['id']);
                $whereC['gid'] = $this->gid;
                $whereC['is_show'] = 1;
                $whereC['pid'] = $cid;
                $ccid = Db::name('category')->where($whereC)->column('id');
                array_push($ccid, $cid);
                $cid = ['IN', $ccid];
            }
            $whereBc['categoryid'] = $cid;
            $whereBc['gid'] = $this->gid;
            $bjids = Db::name('baojia_cat')->where($whereBc)->column('bid');
            if (empty($bjids)) {
                return json(['code' => 0, 'total' => 0, 'p' => $p, 'msg' => '没有更多数据！']);
            } else {
                $whereB['bj.id'] = ['IN', $bjids];
                $bjidss = 'AND bj.id IN(' . implode(',', $bjids) . ')';
            }
        }
        $w = isset($input['w']) ? trim($input['w']) : '';
        $like = '';
        if ($w) {
            $whereB['bj.goodsName'] = ['LIKE', '%' . $w . '%'];
            $like = "AND bj.goodsName LIKE '%{$w}%' ";
        }
        $field = 'bj.id,bj.goodsName,bj.unitid,bj.goods_id,bj.unitgid,bj.tuanprice,bj.goodsThumb,bj.unit,bj.goodsImg,bj.retailprice,bj.sn,bj.seller_note';
        $dbc = config('database');
        $dbPrefix = $dbc['prefix'];
        $order = '';
        $rs = '';
        $sql = <<<SQL
                SELECT 
                    {$field}
                FROM
                    {$dbPrefix}baojia bj
                WHERE
                    `bj`.`gid` = {$this->gid}
                AND `bj`.`status` = 1
                AND `bj`.`sell_status` = 1
                AND `bj`.`unitgid` <> 0
                {$bjidss}
                {$like}
                AND 
                    CASE WHEN 
                        (SELECT COUNT(*)
                            FROM
                                {$dbPrefix}baojia_skuprice bjsp
                            WHERE
                                bjsp.gid={$this->gid}
                            AND
                                bjsp.tuanprice>0
                            AND 
                                bjsp.bjid=bj.id
                                {$rs}
                        ) >= 1
                            THEN 1 ELSE  `bj`.`tuanprice` > 0 END
                ORDER BY
                    {$sort}
                {$sortT}
                LIMIT
                    {$limits},{$limit}
SQL;
        if ($p === 1) {
            $sqlc = <<<SQL
                    SELECT 
                        count(*) t
                    FROM
                        {$dbPrefix}baojia bj
                    WHERE
                        `bj`.`gid` = {$this->gid}
                    AND `bj`.`status` = 1
                    AND `bj`.`sell_status` = 1
                    AND `bj`.`unitgid` <> 0
                    {$bjidss}
                    {$like}
                    AND 
                        CASE WHEN 
                            (SELECT COUNT(*)
                                FROM
                                    {$dbPrefix}baojia_skuprice bjsp
                                WHERE
                                    bjsp.gid={$this->gid}
                                AND
                                    bjsp.tuanprice>0
                                AND 
                                    bjsp.bjid=bj.id
                                    {$rs}
                            ) >= 1
                        THEN 
                            1 
                        ELSE
                            `bj`.`tuanprice` > 0
                        END

SQL;
            $t = Db::connect()->query($sqlc);
            $total = $t[0]['t'];
            if ($total === 0) {
                return json(['code' => 0, 'total' => 0, 'p' => $p, 'msg' => '没有更多数据！']);
            }
        }
        $bjx = Db::connect()->query($sql);
        $unitg = [];
        foreach ($bjx as $k => $bj) {
            //查询bjid
            $bjid = $bj['id'];
            //显示图片 单位等 (商品没有图片，名字，图片地址，单位 的不显示)
            if (!trim($bj['goodsThumb']) && !trim($bj['goodsImg']) || !trim($bj['goodsName'])) {
                $whereG['goods_id'] = $bj['goods_id'];
                $goods = Db::name('goods')->field('goods_thumb,goods_name,goods_img')->where($whereG)->find();
            }
            $goodsThumb='';
            if (!!trim($bj['goodsThumb'])) {
                $goodsThumb = $bj['goodsThumb'];
            } else if (!!trim($bj['goodsImg'])) {
                $goodsThumb = $bj['goodsImg'];
            } else {
                if (!empty($goods)) {
                    if (!!$goods['goods_thumb']) {
                        $goodsThumb = $goods['goods_thumb'];
                    } else if (!!$goods['goods_img']) {
                        $goodsThumb = $goods['goods_img'];
                    }
                }
            }
            if (!trim($bj['goodsName'])) {
                if (!empty($goods)) {
                    if (!!$goods['goods_name']) {
                        $bjx[$k]['goodsName'] = $goods['goods_name'];
                    }
                }
            }
            if (!!$goodsThumb) {
                $goodsUrl = mkgoodsimgurl(['url' => $goodsThumb]);
            } else {
                $goodsUrl = '';
            }
            $bjx[$k]['goodsImg'] = $goodsUrl;
            if (!isset($unitg[$bj['unitgid']])) {
                $whereU['unitgid'] = $bj['unitgid'];
                $whereU['gid'] = $this->gid;
                $whereU['status'] = 1;
                //单位列表
                $unitg[$bj['unitgid']] = $units = Db::name('unit')->where($whereU)->order(['coefficient' => 'DESC'])->column('id,uname,coefficient,title');
                if (empty($unitg[$bj['unitgid']])) {
                    unset($bjx[$k]);
                    continue;
                }
            } else {
                if (empty($unitg[$bj['unitgid']])) {
                    unset($bjx[$k]);
                    continue;
                } else {
                    $units = $unitg[$bj['unitgid']];
                }
            }
            //sku信息
            $whereBjs['gid'] = $this->gid;
            //得到 包括into自己的属性 和没有添加营销的属性
            $whereBjs['sell_status'] = 1;
            $whereBjs['baojia_id'] = $bjid;
            $whereBjs['type'] = 1;
            $whereBjs['status'] = 1;
            $whereBjs['unitgid'] = $bj['unitgid'];
            //-------------------------------------------------------
            $intoList=Db::name('into')->where(array('gid'=>$this->gid,'bjid'=>$bjid))->select();
            $whereUn['gid']=$this->gid;
            $whereUn['status']=1;
            $whereUn['unitgid']=$bj['unitgid'];
            $bjx[$k]['unitList']=Db::name('unit')->field('id,uname,title')->where($whereUn)->select();
            $bjx[$k]['unit_title']=Db::name('unit')->where($whereUn)->value('title');
            $field_str='id,attr_ids,baojia_id,attr_value_ids,attrs_id,attrs_value,storage_num,unitgid,detail,imgsid,thumb,seller_note';
            if(count($intoList)!=0){
                //  参加了分销  得到所有skuids总集合
                $arr=[];
                for($i = 0, $len = count($intoList); $i < $len; ++$i) {
                    $arr[]= $intoList[$i]['skuids'];
                }
                $arrs= implode($arr, ',');
                $into_skuids=[];
                foreach (explode(',',$arrs) as $m){
                    $into_skuids[]=intval($m);
                }
                foreach ($intoList as $key=>$val){
                    if($into_id==0){
                        //添加
                        if($bjid == $val['bjid']){
                            $whereBjs['id'] = ['not in', $arrs];
                        }
                    }else if($into_id!=0){
                        //编辑
                        $skuids=Db::name('into')->where(array('gid'=>$this->gid,'id'=>$into_id))->value('skuids');
                        //当前skuids和（into所有集合 与baojia_sku集合的共同的差集）的 组合可以用来编辑
                        $ids=Db::name('baojia_sku')->field('id')->where(array('gid'=>$this->gid,'baojia_id'=>$bjid))->select();
                        $baojia_sku_skuids = array_column($ids,'id');
                        //得到连个的交集
                        $aa=array_diff($baojia_sku_skuids,$into_skuids);
                        $new=implode(',',$aa);
                        if ($skuids!='' && $new=='') {
                            $str = $arrs;
                        }else if ($skuids=='' && $new!='') {
                            $str = $new;
                        }else if ($skuids!='' && $new !='') {
                            $str = $skuids . ',' . $new;
                        }else{
                            $str='';
                        }
                        $whereInto['gid']=$this->gid;
                        $whereInto['bjid']=$bjid;
                        $whereInto['id']=['neq',$into_id];
                        $in_skuid=Db::name('into')->field('skuids')->where($whereInto)->select();
                        $skuid=[];
                        for($i = 0, $len = count($in_skuid); $i < $len; ++$i) {
                            $skuid[]= $in_skuid[$i]['skuids'];
                        }
                        $str1= implode($skuid, ',');
                        if($bjid == $val['bjid']){
                            $whereBjs['id'] = ['not in', $str1];
                        }else{
                            $whereBjs['id'] = ['not in', $str];
                        }
                    }
                    $bjss = Db::name('baojia_sku')
                            ->field($field_str)
                            ->where($whereBjs)
                            ->order(['storage_num' => 'DESC'])
                            ->select();
                    if (!empty($bjss)) {
                        $bjx[$k]['skusl'] = 0;
                        for ($j = 0, $jl = count($bjss); $j < $jl; $j++) {
                            $bjx[$k]['ptype'] = 3;
                            $bjs = $bjss[$j];
                            $bjx[$k]['skusl']++;
                            $bjx[$k]['skus'][$bjs['id']] = [
                                'sku' => $bjs
                            ];
                        }
                    } else {
                        if ($bj['tuanprice'] == 0) {
                            continue;
                        } else {
                            $bjx[$k]['ptype'] = 1;
                        }
                    }
                }
            }else{
                $bjss = Db::name('baojia_sku')
                        ->field($field_str)
                        ->where($whereBjs)
                        ->order(['storage_num' => 'DESC'])
                        ->select();
                if (!empty($bjss)) {
                    $bjx[$k]['skusl'] = 0;
                    for ($j = 0, $jl = count($bjss); $j < $jl; $j++) {
                        $bjx[$k]['ptype'] = 3;
                        $bjs = $bjss[$j];
                        $bjx[$k]['skusl']++;
                        $bjx[$k]['skus'][$bjs['id']] = [
                            'sku' => $bjs
                        ];
                    }
                } else {
                    if ($bj['tuanprice'] == 0) {
                        continue;
                    } else {
                        $bjx[$k]['ptype'] = 1;
                    }
                }
            }
        }
        $bjx = array_values($bjx);
        if (isset($total)) {
            return json(['code' => 1, 'p' => $p,'limit'=>$limit, 'msg' => '数据加载成功！', 'data' => $bjx, 'total' => $total]);
        } else {
            if (empty($bjx)) {
                return json(['code' => 0, 'p' => $p,'limit'=>$limit, 'msg' => '数据加载成功！', 'data' => $bjx]);
            } else {
                return json(['code' => 1, 'p' => $p,'limit'=>$limit, 'msg' => '数据加载成功！', 'data' => $bjx]);
            }
        }
    }
    private function getCategoryList()
    {
        $cate = Db::name('category')->where(['gid' => $this->gid, 'status' => 0])->select();
        $category = [];
        foreach ($cate as $ind => $val) {
            if ($val['pid'] == 0) {
                $citem = $val;
                foreach ($cate as $ind2 => $val2) {
                    if ($val['id'] == $val2['pid']) {
                        $citem['c'][] = $val2;
                    }
                }
                $category[] = $citem;
            }
        }
        return $category;
    }
}