<?php if (!defined('THINK_PATH')) exit(); /*a:2:{s:79:"D:\wamp64\www\ljk.cc\public/../application/admin\view\user\ajax\user_index.html";i:1540465488;s:60:"D:\wamp64\www\ljk.cc\application\admin\view\common\page.html";i:1494925570;}*/ ?>
<form id="userPLUpdate" name="useradminform" method="post" action="/admin/user/plupdate" onsubmit="return plupdate(this);">
<div class="table-responsive">
    <table class="table table-striped dindan">
                <thead>
                    <tr>
                        <th style="width:5%;">选择</th>
                        <th style="width:10%;">名称</th>
                        <th style="width:15%;">联系人</th>
                        <th style="width:5%;">积分</th>
                        <th style="width:10%;" >拜访周期</th>
                        <th style="width:10%;" >订单周期</th>
                        <th style="width:20%;text-align: center;" >客户介绍</th>
                        <th style="width:10%;">注册更新时间</th>
                        <th style="width:5%;">微信</th>
                        <th style="width:10%;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($userslist): if(is_array($userslist) || $userslist instanceof \think\Collection || $userslist instanceof \think\Paginator): if( count($userslist)==0 ) : echo "" ;else: foreach($userslist as $key=>$user): ?>
                    <tr>
                        <td>
                            <div class="icheckbox_square-green" style="position: relative;" onclick="setCheck(this);">
                                <input type="checkbox" class="i-checks" name="updateids[]" value="<?php echo $user['uid']; ?>" style="position: absolute; opacity: 0;">
                                <ins class="iCheck-helper" style="position: absolute; top: 0%; left: 0%; display: block; width: 100%; height: 100%; margin: 0px; padding: 0px; background: rgb(255, 255, 255); border: 0px; opacity: 0;"></ins>
                            </div>
                        </td>
                        <td><a href="/<?php echo \think\Request::instance()->module(); ?>/user/detail/uid/<?php echo $user['uid']; ?>" data-index="_user_detail_uid_<!--<?php echo $user['uid']; ?>-->" onClick="return c(this);"><?php echo $user['realname']; ?><span style="display: none;">客户详情</span></a></td>
                        <td><?php echo $user['contact']; ?><br /><?php echo $user['mobile']; if($user['address']): ?><br/><?php echo $user['address']; endif; ?></td>
                        <td><?php echo $user['pay_points']; ?></td>
                        <td><?php if($user['bftime']!=="wuxiao"): ?><?php echo $user['bftime']; ?>天<?php endif; ?></td>
                        <td><?php if($user['otime']!=="wuxiao"): ?><?php echo $user['otime']; ?>天<?php endif; ?></td>
                        <td style="word-break:normal; width:315px; display:block; white-space:pre-wrap;word-wrap : break-word ;overflow: hidden ;"><?php echo $user['summary']; ?></td>
                        <td><font color="#669900"><?php if($user['regtime']!="0"): ?><?php echo date("Y-m-d H:i:s",intval($user['regtime'])); endif; ?></font><?php if($user['lastvisit']!=$user['regtime']): ?><br>
                        <?php if($user['lastvisit']!="0"): ?><?php echo $user['lastvisit']; endif; endif; ?></td>
                        <td><?php if($user['weixin']!=""): ?><font color="#006600">已绑定</font><?php else: ?>未绑定<?php endif; ?></td>
                        <td>
                            <a class="btn  btn-success btn-sm"  data-index="_user_detail_uid_<!--<?php echo $user['uid']; ?>-->" onClick="return c(this);" href="/<?php echo \think\Request::instance()->module(); ?>/user/detail/uid/<?php echo $user['uid']; ?>"><span style="display: none;"><?php echo $user['realname']; ?>客户</span>详情</a> 
                            <?php if(isset($admin_order_add)): ?>
                            <a class="btn  btn-primary btn-sm" data-index="order_add_uid_<?php echo $user['uid']; ?>" onClick="return c(this);" href="/admin/order/add/uid/<?php echo $user['uid']; ?>"><span style='display: none;'><?php if($user['realname']): ?><?php echo $user['realname']; else: ?><?php echo $user['mobile']; endif; ?>-</span>下订单</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; else: echo "" ;endif; ?>
                    <tfoot>
                        <tr>
                            <td>
                                <a class="btn btn-sm btn-success" onclick="checkA(this,'userPLUpdate','updateids[]');" href="javascript:void(0);">√</a>
                            </td>
                            <td colspan="11">
                            <select name="guid"  style="width:103px;padding:5px 2px; border-color:#e5e6e7;">
                                <option value="">跟进人</option>
                                <option value="0">无跟进人</option>
                                <?php if(is_array($members) || $members instanceof \think\Collection || $members instanceof \think\Paginator): if( count($members)==0 ) : echo "" ;else: foreach($members as $key=>$member): ?>
                                <option value="<?php echo $member['uid']; ?>" ><?php echo $member['realname']; ?></option>
                                <?php endforeach; endif; else: echo "" ;endif; ?>
                            </select>
                            <select name="tpid"  style="width:103px;padding:5px 2px; border-color:#e5e6e7;">
                                <option value="">客户类型</option>
                                <?php if(is_array($types) || $types instanceof \think\Collection || $types instanceof \think\Paginator): if( count($types)==0 ) : echo "" ;else: foreach($types as $key=>$type): ?>
                                <option value="<?php echo $type['id']; ?>"><?php echo $type['title']; ?></option>
                                <?php endforeach; endif; else: echo "" ;endif; ?>
                            </select>
                            <?php if($stages): ?>
                            <select name="stage_id" style="width:103px;padding:5px 2px; border-color:#e5e6e7;">
                                <option value="0"><?php if($tag_title!='' ||$tag_title != null): ?><?php echo $tag_title; else: ?>客户阶段<?php endif; ?></option>
                                <?php if(is_array($stages) || $stages instanceof \think\Collection || $stages instanceof \think\Paginator): if( count($stages)==0 ) : echo "" ;else: foreach($stages as $key=>$stage): ?>
                                <option value="<?php echo $stage['id']; ?>" <?php if(@$conditions['stage_id'] == $stage['id']): ?>selected="selected"<?php endif; ?>><?php echo $stage['title']; ?></option>
                                <?php endforeach; endif; else: echo "" ;endif; ?>
                            </select>
                            <?php endif; ?>
                            <select id="rankid" name="rankid"  style="width:103px;padding:5px 2px; border-color:#e5e6e7;">
                                <option value="">客户等级</option>
                                <option value="0">取消等级</option>
                                <?php if(is_array($Ranks) || $Ranks instanceof \think\Collection || $Ranks instanceof \think\Paginator): if( count($Ranks)==0 ) : echo "" ;else: foreach($Ranks as $rankid=>$rank): ?>
                                <option value="<?php echo $rank['id']; ?>"><?php echo $rank['rank_name']; ?></option>
                                <?php endforeach; endif; else: echo "" ;endif; ?>
                            </select>
                            <select id="trust" name="trust"  style="width:103px;padding:5px 2px; border-color:#e5e6e7;">
                                <option value="" >认证状态</option>
                                <option value="1" >认证</option>
                                <option value="0" >取消认证</option>
                            </select>
                            <select id="status" name="status"  style="width:103px;padding:5px 2px; border-color:#e5e6e7;">
                                <option value="" >客户状态</option>
                                <option value="1" >有效</option>
                                <option value="0" >无效</option>
                            </select>
                            <input class="btn btn-sm btn-success" type="submit" value="批量修改" name="searchsubmit"/>
                                                                   <a class="btn  btn-success btn-sm" data-toggle="modal" data-target="#gxuser" href="javascript:void(0);">批量共享转移客户</a> </td>
                        </tr>
                    </tfoot>
                    <?php else: ?>
                    <tr><td colspan="11" style="color:red;">没有找到符合条件的客户</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
</div>
</form>
<div class="row">
    <div class="col-sm-6">
        <div class="dataTables_info" id="DataTables_Table_0_info" role="alert" aria-live="polite" aria-relevant="all" style="margin-top:24px;">
            显示 <?php echo $pageInfo['f']; ?> 到 <?php echo $pageInfo['t']; ?> 项，共 <?php echo $pageInfo['total']; ?> 项
        </div>
    </div>
    <div class="col-sm-6">
        <div class="dataTables_paginate paging_simple_numbers" id="DataTables_Table_0_paginate">
            <ul class="pagination" >
                <?php if($pageInfo['prev']): ?>
                <li class="paginate_button previous" aria-controls="DataTables_Table_0" tabindex="0" id="DataTables_Table_0_previous">
                    <a href="javascript:void(0);" onclick="getList({u:'<?php echo $initData['requestUrl']; ?>',sign:'<?php echo $initData['sign']; ?>'},'<?php echo $pageInfo['prev']; ?>');">上一页</a>
                </li>
                <?php endif; ?>
                <!--首页-->
                <li class="paginate_button <?php if($pageInfo['currentPage']==1): ?>active<?php endif; ?>" aria-controls="DataTables_Table_0" tabindex="0">
                    <a href="javascript:void(0);" <?php if($pageInfo['currentPage']!=1): ?>onclick="getList({u:'<?php echo $initData['requestUrl']; ?>',sign:'<?php echo $initData['sign']; ?>'},'1');"<?php endif; ?>>1</a>
                </li>
                <!--首页-->
                <!--前置省略号-->
                <?php if($pageInfo['currentPage']-3 > 2): ?>
                <li class="paginate_button <?php if($pageInfo['currentPage']==1): ?>active<?php endif; ?>" aria-controls="DataTables_Table_0" tabindex="0">
                    <a href="javascript:void(0);">...</a>
                </li>
                <?php endif; ?>
                <!--前置省略号-->
                <!--前置分页-->
                <?php $__FOR_START_18726__=$pageInfo['currentPage']-3;$__FOR_END_18726__=$pageInfo['currentPage'];for($i=$__FOR_START_18726__;$i < $__FOR_END_18726__;$i+=1){ if($i>=2): ?>
                <li class="paginate_button" aria-controls="DataTables_Table_0" tabindex="0">
                    <a href="javascript:void(0);" onclick="getList({u:'<?php echo $initData['requestUrl']; ?>',sign:'<?php echo $initData['sign']; ?>'},'<?php echo $i; ?>');"><?php echo $i; ?></a>
                </li>
                <?php endif; } ?>
                <!--前置分页-->
                <!--当前分页-->
                <?php if($pageInfo['currentPage'] != 1 && $pageInfo['currentPage'] != $pageInfo['lastPage']): ?>
                <li class="paginate_button active" aria-controls="DataTables_Table_0" tabindex="0">
                    <a href="javascript:void(0);"><?php echo $pageInfo['currentPage']; ?></a>
                </li>
                <?php endif; ?>
                <!--当前分页-->
                <!--后置分页-->
                <?php $__FOR_START_25729__=$pageInfo['currentPage'] + 1;$__FOR_END_25729__=$pageInfo['currentPage'] + 4;for($i=$__FOR_START_25729__;$i < $__FOR_END_25729__;$i+=1){ if($i<= $pageInfo['lastPage'] - 1): ?>
                <li class="paginate_button" aria-controls="DataTables_Table_0" tabindex="0">
                    <a href="javascript:void(0);" onclick="getList({u:'<?php echo $initData['requestUrl']; ?>',sign:'<?php echo $initData['sign']; ?>'},'<?php echo $i; ?>');"><?php echo $i; ?></a>
                </li>
                <?php endif; } ?>
                <!--后置分页-->
                <!--前置省略号-->
                <?php if($pageInfo['currentPage'] + 3 < $pageInfo['lastPage'] - 1): ?>
                <li class="paginate_button" aria-controls="DataTables_Table_0" tabindex="0">
                    <a href="javascript:void(0);">...</a>
                </li>
                <?php endif; ?>
                <!--前置省略号-->
                <?php if($pageInfo['lastPage'] > 1): ?>
                <!--末页-->
                <li class="paginate_button <?php if($pageInfo['currentPage']==$pageInfo['lastPage']): ?>active<?php endif; ?>" aria-controls="DataTables_Table_0" tabindex="0">
                    <a href="javascript:void(0);" <?php if($pageInfo['currentPage']!=$pageInfo['lastPage']): ?>onclick="getList({u:'<?php echo $initData['requestUrl']; ?>',sign:'<?php echo $initData['sign']; ?>'},'<?php echo $pageInfo['lastPage']; ?>');"<?php endif; ?>><?php echo $pageInfo['lastPage']; ?></a>
                </li>
                <!--末页-->
                <?php if($pageInfo['next']): ?>
                <li class="paginate_button" aria-controls="DataTables_Table_0" tabindex="0" id="DataTables_Table_0_next">
                    <a href="javascript:void(0);" onclick="getList({u:'<?php echo $initData['requestUrl']; ?>',sign:'<?php echo $initData['sign']; ?>'},'<?php echo $pageInfo['next']; ?>');">下一页</a>
                </li>
                <?php endif; endif; ?>
            </ul>
        </div>
    </div>
</div>

