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
namespace app\shop\controller;
use think\Db;
class Goods extends Base{
    public function getGoodsList($input = []){
        // 排序
        $p = isset($input['p']) && (intval($input['p']) > 0) ?  intval($input['p'])  : 1;
        $limit = isset($input['limit']) && (intval(trim($input['limit'])) > 0) ?  intval(trim($input['limit'])) : 10; 
        $limits = ($p - 1) * $limit;
        $s = (int)$input['sort'];
        switch($s){
            case 1:$sort = 'bj.price';$sortT = 'ASC';break;
            case 2:$sort = 'bj.price';$sortT = 'DESC';break;
            case 3:$sort = 'bj.scount';$sortT = 'ASC';break;
            case 4:$sort = 'bj.scount';$sortT = 'DESC';break;
            case 5:$sort = 'bj.ccount';$sortT = 'ASC';break;
            case 6:$sort = 'bj.ccount';$sortT = 'DESC';break;
            default:$sort = 'bj.id';$sortT = 'DESC';
        }
        $whereB = [];
        $whereB['bj.gid'] = $this->gid;
        $whereB['bj.sell_status'] = 1;
        $whereB['bj.status'] = 1;
        $whereB['bj.unitgid'] = ['NEQ',0];
        $cid = isset($input['clas']) ? $input['clas'] : 0;
        $bjidss = '';
        if($cid){
            $whereC['gid'] = $this->gid;
            $whereC['id'] = $cid;
            $whereC['is_show'] = 1;
            $whereC['pid'] = 0;
            $c = Db::name('category')->where($whereC)->count();
            if($c){
                //说明是一级分类
                unset($whereC['id']);
                $whereC['gid'] = $this->gid;
                $whereC['is_show'] = 1;
                $whereC['pid'] = $cid;
                $ccid = Db::name('category')->where($whereC)->column('id');
                array_push($ccid,$cid);
                $cid = ['IN',$ccid];
            }
            $whereBc['categoryid'] = $cid;
            $whereBc['gid'] = $this->gid;
            $bjids = Db::name('baojia_cat')->where($whereBc)->column('bid');
            if(empty($bjids)){
                return json(['code'=>0,'total' => 0, 'p' => $p, 'msg'=>'没有更多数据！']);
            }else{
                $whereB['bj.id'] = ['IN',$bjids];
                $bjidss = 'AND bj.id IN(' . implode(',',$bjids) . ')';
            }
        }
        $w = isset($input['w']) ? trim($input['w']) : '';
        $like = '';
        if($w){
            $whereB['bj.goodsName'] = ['LIKE' , '%' . $w . '%' ];
            $like = "AND bj.goodsName LIKE '%{$w}%' ";
        }
        $field = 'bj.id,bj.goodsName,bj.goods_id,bj.tuanprice,bj.goodsThumb,bj.unit,bj.goodsImg,bj.retailprice';
        $dbc = config('database');
        $dbPrefix = $dbc['prefix'];
        $order = '';
        $rs = '';
        if(!!$this->rankid){
            if($this->gid == -1){
                if($this->rankid !== 68){
                    $rs = "AND bjsp.rankid IN({$this->rankid},68)";
                    $whereBjsp['rankid'] = ['IN',[$this->rankid,68]];
                    $order = 'field(rankid,' . $this->rankid . ') DESC';
                }else{
                    $rs = "AND bjsp.rankid={$this->rankid}";
                    $whereBjsp['rankid'] = $this->rankid;
                    $order = 'rankid DESC';
                }
            }else if($this->gid == 205){
                if($this->rankid !== 333){
                    $rs = "AND bjsp.rankid IN({$this->rankid},333)";
                    $whereBjsp['rankid'] = ['IN',[$this->rankid,333]];
                    $order = 'field(rankid,' . $this->rankid . ') DESC';
                }else{
                    $rs = "AND bjsp.rankid={$this->rankid}";
                    $whereBjsp['rankid'] = $this->rankid;
                    $order = 'rankid DESC';
                }
            }else{
                $rs = "AND bjsp.rankid IN({$this->rankid},0)";
                $whereBjsp['rankid'] = ['IN',[0,$this->rankid]];
                $order = 'rankid DESC';
            }
        }else{
            if($this->gid == -1){
                $rs = "AND bjsp.rankid=68";
                $whereBjsp['rankid'] = 68;
                $order = 'rankid DESC';
            }else if($this->gid == 205){
                $rs = "AND bjsp.rankid=333";
                $whereBjsp['rankid'] = 333;
                $order = 'rankid DESC';
            }else{
                $rs = "AND bjsp.rankid=0";
                $whereBjsp['rankid'] = 0;
                $order = 'rankid DESC';
            }
        }
        if($this->gid == 205 || $this->gid == -1){
            $whereBjspj['bjsp.gid'] = $this->gid;
            $whereBjspj['bjsp.rankid'] = $whereBjsp['rankid'];
            $whereBjspj['bjsp.tuanprice'] = ['GT',0]; 
            if($p === 1){
                $total = Db::name('baojia bj')
                    ->join('baojia_skuprice bjsp','bjsp.bjid=bj.id','LEFT')
                    ->where($whereB)
                    ->where($whereBjspj)
                    ->group('bj.id')
                    ->having('count(bjsp.id) >= 1')
                    ->count();
                if($total === 0){
                    return json(['code'=>0,'total' => 0, 'p' => $p, 'msg'=>'没有更多数据！']);
                }
            }
            $indes = Db::name('baojia bj')
                ->field($field)
                ->join('baojia_skuprice bjsp','bjsp.bjid=bj.id','LEFT')
                ->where($whereB)
                ->where($whereBjspj)
                ->group('bj.id')
                ->having('count(bjsp.id) >= 1')
                ->order( [$sort=>$sortT] )
                ->page($p,$limit)
                ->select();
        }else{
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
            if($p === 1){
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
                if($total === 0){
                    return json(['code' => 0, 'total' => 0, 'p'=>$p, 'msg' => '没有更多数据！']);
                }
            }
            $indes = Db::connect()->query($sql);
        }
        foreach($indes as $ind=>$val){
            if(!trim($val['goodsThumb']) && !trim($val['goodsImg']) || !trim($val['goodsName'])){
                $whereG['goods_id'] = $val['goods_id'];
                $goods = Db::name('goods')->field('goods_thumb,goods_name,goods_img')->where($whereG)->find();
            }
            if(!!trim($val['goodsThumb'])){
                $goodsThumb = $val['goodsThumb'];
            }else if(!!trim($val['goodsImg'])){
                $goodsThumb = $val['goodsImg'];
            }else{
                if(!empty($goods)){
                    if(!!$goods['goods_thumb']){
                        $goodsThumb = $goods['goods_thumb'];
                    }else if(!!$goods['goods_img']){
                        $goodsThumb = $goods['goods_img'];
                    }
                }
            }
            if(!trim($val['goodsName'])){
                if(!empty($goods)){
                    if(!!$goods['goods_name']){
                        $indes[$ind]['goodsName'] = $goods['goods_name'];
                    }
                }
            }
            if(!!$goodsThumb){
                $goodsUrl = mkgoodsimgurl(['url'=>$goodsThumb]);
            }else{
                $goodsUrl = '';
            }
            $indes[$ind]['goodsImg'] = $goodsUrl;
            $bjid = $val['id'];
            $whereBs['baojia_id'] = $bjid;
            $whereBs['gid'] = $this->gid;
            $whereBs['sell_status'] = 1;
            $oneSku = Db::name('baojia_sku')->field('id,attrs_value')->where($whereBs)->find();
            if(!empty($oneSku)){
                $whereBjsp['skuid'] = $oneSku['id'];
                $whereBjsp['status'] = 1;
                $whereBjsp['sell_status'] = 1;
                $whereBjsp['tuanprice'] = ['NEQ',0];
                $bsp = Db::name('baojia_skuprice')->field('id,tuanprice,unitgid,unitid,retailprice')->where($whereBjsp)->order($order)->find();
                $whereU['gid'] = $this->gid;
                $whereU['id'] = $bsp['unitid'];
                $whereU['unitgid'] = $bsp['unitgid'];
                $indes[$ind]['unit'] = Db::name('unit')->where($whereU)->value('uname');
                $indes[$ind]['unitid'] = $bsp['unitid']; 
                $indes[$ind]['tuanprice'] = $bsp['tuanprice'];
                $indes[$ind]['retailprice'] = $bsp['retailprice'];
                $indes[$ind]['bspid'] = $bsp['id'];
                if(!!$oneSku['attrs_value']){
                    $oneSku['attrs_value'] = explode(',',trim($oneSku['attrs_value']));
                }
                $indes[$ind]['sku'] = $oneSku;
            }else if($this->rankid != 0){
                $whereBr['rank_id'] = $this->rankid;
                $whereBr['gid'] = $this->gid;
                $whereBr['goods_id'] = $indes[$ind]['goods_id'];
                $whereBr['tuanprice'] = ['NEQ',0];
                $br = Db::name('baojia_rank')->field('id,tuanprice,retailprice')->where($whereBr)->find();
                if(!empty($br)){
                    $br['tuanprice'] != 0 ? $indes[$ind]['tuanprice'] = $br['tuanprice'] : null;
                    $br['retailprice'] != 0 ? $indes[$ind]['retailprice'] = $br['retailprice'] : null;
                }
            }
        }
        if(isset($total)){
            return json(['code' => 1, 'p' => $p, 'msg' => '数据加载成功！','data' => $indes,'total' => $total]);
        }else{
            if(empty($indes)){
                return json(['code' => 0, 'p' => $p, 'msg' => '数据加载成功！','data' => $indes]);
            }else{
                return json(['code' => 1, 'p' => $p, 'msg' => '数据加载成功！','data' => $indes]);
            }
        }
    }
}
