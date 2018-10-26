<?php if (!defined('THINK_PATH')) exit(); /*a:6:{s:74:"D:\wamp64\www\ljk.cc\public/../application/admin\view\user\user_index.html";i:1540521505;s:62:"D:\wamp64\www\ljk.cc\application\admin\view\common\header.html";i:1538041264;s:63:"D:\wamp64\www\ljk.cc\application\admin\view\common\warning.html";i:1478418932;s:60:"D:\wamp64\www\ljk.cc\application\admin\view\common\tips.html";i:1491557406;s:72:"D:\wamp64\www\ljk.cc\application\admin\view\user\search\user_search.html";i:1540453137;s:64:"D:\wamp64\www\ljk.cc\application\admin\view\common\footerjs.html";i:1538041328;}*/ ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="renderer" content="webkit">
<title><?php echo $groupinfo['title']; ?>管理后台</title>
<!--***********************************CSS***********************************-->
<link rel="shortcut icon" href="/resource/favicon.ico">
<link href="/static/admin/css/plugins/iCheck/custom.css" rel="stylesheet">
<link href="/static/admin/css/plugins/dropzone/basic.css" rel="stylesheet">
<link href="/static/admin/css/plugins/dropzone/dropzone.css" rel="stylesheet">
<link href="/static/admin/css/bootstrap.min.css?v=3.4.0" rel="stylesheet">
<link href="/static/admin/font-awesome/css/font-awesome.css?v=4.3.0" rel="stylesheet">
<link href="/static/admin/css/plugins/morris/morris-0.4.3.min.css" rel="stylesheet">
<link href="/static/admin/js/plugins/gritter/jquery.gritter.css" rel="stylesheet">
<link href="/static/admin/css/animate.css" rel="stylesheet">
<link href="/static/admin/css/style.css?v=2.2.0" rel="stylesheet">
<!-- -->
<link href="/static/admin/css/extend.css" rel="stylesheet">
<link href="/static/admin/css/style.min.css" rel="stylesheet">
<link href="/static/admin/css/plugins/iCheck/custom.css" rel="stylesheet">
<link href="/static/common/css/weui.min.css" rel="stylesheet">
<link href="/static/admin/css/my.css" rel="stylesheet">
<!--***********************************CSS***********************************-->
<!--***********************************J S***********************************-->
<script type="text/javascript" src="/static/admin/js/jquery-2.1.1.min.js"></script>
<script type="text/javascript" src="/static/admin/js/script.js"></script>
<script type="text/javascript" src="/static/admin/js/contabc.js"></script>

<script type="text/javascript" src="/static/admin/js/lrz.bundle.js"></script>
<script type="text/javascript" src="/static/common/js/laydate/laydate.js"></script>
<!--***********************************J S***********************************-->

</head>
<body>
<div class="modal inmodal fade in" id="warningBox" style="position: fixed;display: none; z-index: 99999 !important;">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header" style="margin:0px; padding:10px;">
                <button style="display: block;" onclick="javascript:document.getElementById('warningBox').style.display='none';document.getElementById('warningBackdrop').style.display='none';" type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">x</span><span class="sr-only">Close</span>
                </button>
                <h4 class="modal-title">提示信息</h4>
            </div>
            <div class="modal-body" id="warningMsg" style="text-align: center;font-weight: bold;"></div>
            <div class="modal-footer">
                <a class="btn btn-sm btn-primary" href="javascript:void(0);" id="warningContinue" style="display: inline;" onclick="nextAct();">继续</a>
                <a class="btn btn-sm btn-white" href="javascript:void(0);" style="display: inline;" onclick="javascript:document.getElementById('warningBox').style.display='none';document.getElementById('warningBackdrop').style.display='none';">取消</a>
            </div>
        </div>
    </div>
</div>
<div class="modal-backdrop fade in" style="display: none;z-index: 99990 !important;" id="warningBackdrop"></div>
    <script type="text/javascript">
    function closeTip(){
        $('#tipBox').hide();
        $('#tipBackdrop').hide();
    }
</script>
<div class="modal inmodal fade in" id="tipBox" style="display: none;z-index: 99999 !important;">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header" style="margin:0px; padding:10px;">
                <button id="tip_close" style="display: none;" onclick="closeTip();" type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">x</span><span class="sr-only">Close</span>
                </button>
                <h4 class="modal-title">提示信息</h4>
            </div>
            <div class="modal-body" id="tip_msg" style="text-align: center;"></div>
            <div class="modal-footer">
                <a class="btn btn-sm btn-white" href="<?php echo \think\Request::instance()->server('HTTP_REFERER'); ?>" id="tip_back" style="display: none;"></a>
                <a class="btn btn-sm btn-primary" href="javascript:void(0);" id="tip_detail" style="display: none;"></a>
                <a class="btn btn-sm btn-primary" href="javascript:void(0);" id="tip_continue" style="display: none;"></a>
                <button id="tip_close_btn" style="display: none;" onclick="closeTip();" type="button" class="btn-sm btn-primary" data-dismiss="modal">
                    <span aria-hidden="true">关闭</span><span class="sr-only">Close</span>
                </button>
            </div>
        </div>
    </div>
</div>
<div class="modal-backdrop fade in" style="display: none;z-index: 99990 !important;" id="tipBackdrop"></div>
    <div id="wrapper">
  <div  class="gray-bg dashbard-1">
    <div class="row wrapper border-bottom white-bg page-heading">
        <div class="col-sm-8">
            <h2>客户管理</h2>
        </div>
        <div class="col-sm-4">
            <br />
            <a class="btn btn-sm btn-success" href="/admin/user/add" data-index="admin_user_add" onClick="return c(this);">新增客户</a> 
            <a class="btn btn-sm btn-primary " href="javascript:void(0);" onClick="getList({sign:'<?php echo $initData['sign']; ?>'});">刷新</a>
            <a class="btn btn-sm btn-primary " href="javascript:void(0);" onClick="SendEmail()">发送Email</a>
            <?php if(isset($admin_user_import)): ?>
            <a class="btn btn-sm btn-primary " href="/admin/user/import" data-index="admin_user_import" onClick="return c(this);">导入<span style="display: none;">客户资料</span></a>
            <?php endif; ?>
        </div>
    </div>
    <div class="wrapper wrapper-content animated fadeInRight">
      <div class="row">
            <div class="col-sm-12">
          <div class="ibox float-e-margins">
            <form id='<?php echo $initData['sign']; ?>SearchForm' action="<?php echo $initData['requestUrl']; ?>" onSubmit="return false;">
              <?php if(@$psid): ?>
<input type="hidden" name="id" value="<?php echo $psid; ?>" />
<?php endif; ?>
<input type="hidden" data-sign="<?php echo $initData['sign']; ?>" value="" id="initDataSign" />
<input type="hidden" name="action" value="company"/>
    <div class="row">
    <?php if(@$authSearch=='show'): ?>
    <div class="col-sm-1" style=" padding-right:0px; width:100px;">
        <div class="input-group">
            <select name="psid" class="input-sm form-control" style="width:93px; padding-left: 2px;padding-right: 0px;" value="">
                <option value="" <?php if(@$conditions['psid'] == 'all'): ?>selected<?php endif; ?>>授权状态</option>
                <option value="<?php echo $px['psid']; ?>" <?php if(@$conditions['psid'] == $px['psid']): ?>selected<?php endif; ?>>已授权</option>
                <option value="0" <?php if(@$conditions['psid'] == '0'): ?>selected<?php endif; ?>>未授权</option>
            </select>
        </div>
    </div>
    <?php endif; ?>
     <div class="col-sm-1" style=" padding-right:0px; width:100px;">
         <div class="input-group">
             <select class="input-sm form-control" style="width:93px;padding-left: 2px;padding-right: 0px;" name="status" onChange="getList({sign:'<?php echo $initData['sign']; ?>'},1);">
                 <option value="1" selected="selected">有效客户</option>
                 <option value="0">无效客户</option>
             </select>
         </div>
    </div>
    <?php if(count($shops)>1): ?>
    <div class="col-sm-1" style=" padding-right:0px; width:100px;">
        <div class="input-group">
            <select class="input-sm form-control" style="width:93px;padding-left: 2px;padding-right: 0px;" value="0" name="shopid" onchange="getList({sign:'<?php echo $initData['sign']; ?>'},1);" >
                <option  value="">来自</option> 
                <?php if(is_array($shops) || $shops instanceof \think\Collection || $shops instanceof \think\Paginator): if( count($shops)==0 ) : echo "" ;else: foreach($shops as $key=>$shop): ?> 
                <option value="<?php echo $shop['id']; ?>"  <?php if(@$conditions['shopid'] == $shop['id']): ?>selected="selected"<?php endif; ?>><?php echo $shop['name']; ?></option>
                <?php endforeach; endif; else: echo "" ;endif; ?>
            </select>
        </div>
    </div>
    <?php endif; if(@$rankSearch<> '0'): ?>
    <div class="col-sm-1" style=" padding-right:0px; width:100px;">
         <div class="input-group">
             <select class="input-sm form-control" style="width:93px;padding-left: 2px;padding-right: 0px;" value="0" name="rankid" onChange="getList({sign:'<?php echo $initData['sign']; ?>'},1);" >
                 <option value="">客户等级</option>
                  <?php if(is_array($Ranks) || $Ranks instanceof \think\Collection || $Ranks instanceof \think\Paginator): if( count($Ranks)==0 ) : echo "" ;else: foreach($Ranks as $rankid=>$rank): ?>
                 <option value="<?php echo $rank['id']; ?>" <?php if(@$conditions['rankid'] == $rank['id']): ?>selected="selected"<?php endif; ?>><?php echo $rank['rank_name']; ?></option>
                 <?php endforeach; endif; else: echo "" ;endif; ?>
             </select>
         </div>
    </div>
    <?php endif; if(@$tpidSearch<> '0'): ?>
     <div class="col-sm-1" style=" padding-right:0px; width:100px;">
         <div class="input-group">
             <select class="input-sm form-control" style="width:93px;padding-left: 2px;padding-right: 0px;" value="" name="tpid" onChange="getList({sign:'<?php echo $initData['sign']; ?>'},1);">
                 <option value="">客户类型</option>
                 <?php if(is_array($types) || $types instanceof \think\Collection || $types instanceof \think\Paginator): if( count($types)==0 ) : echo "" ;else: foreach($types as $key=>$type): ?>
                 <option value="<?php echo $type['id']; ?>" <?php if(@$conditions['tpid'] == $type['id']): ?>selected="selected"<?php endif; ?>><?php echo $type['title']; ?></option>
                 <?php endforeach; endif; else: echo "" ;endif; ?>
             </select>
         </div>
    </div>
    <?php endif; if(@$tpidSearch<> '0'): if($stages): ?>
    <div class="col-sm-1" style=" padding-right:0px; width:100px;">
        <div class="input-group">
            <select class="input-sm form-control" style="width:93px;padding-left: 2px;padding-right: 0px;" value="" name="stage_id" onChange="getList({sign:'<?php echo $initData['sign']; ?>'},1);">
                <option value=""><?php if($tag_title!='' ||$tag_title != null): ?><?php echo $tag_title; else: ?>客户阶段<?php endif; ?></option>
                <?php if(is_array($stages) || $stages instanceof \think\Collection || $stages instanceof \think\Paginator): if( count($stages)==0 ) : echo "" ;else: foreach($stages as $key=>$stage): ?>
                <option value="<?php echo $stage['id']; ?>" <?php if(@$conditions['stage_id'] == $stage['id']): ?>selected="selected"<?php endif; ?>><?php echo $stage['title']; ?></option>
                <?php endforeach; endif; else: echo "" ;endif; ?>
            </select>
        </div>
    </div>
    <?php endif; endif; ?>
     <div class="col-sm-1" style=" padding-right:0px; width:100px;">
         <div class="input-group">
             <select class="input-sm form-control" style="width:93px;padding-left: 2px;padding-right: 0px;" value="" name="guid" onChange="getList({sign:'<?php echo $initData['sign']; ?>'},1);">
                 <option value="all"  <?php if(@$conditions['guid'] == 'all'): ?>selected="selected"<?php endif; ?>>跟进人</option>
                 <option value="0"  <?php if(@$conditions['guid'] == '0'): ?>selected="selected"<?php endif; ?>>无人跟进</option>
                  <?php if(is_array($members) || $members instanceof \think\Collection || $members instanceof \think\Paginator): if( count($members)==0 ) : echo "" ;else: foreach($members as $key=>$member): ?>
                    <option  value="<?php echo $member['uid']; ?>" <?php if(@$conditions['guid'] == $member['uid']): ?>selected="selected"<?php endif; ?>><?php echo $member['realname']; ?></option>
                  <?php endforeach; endif; else: echo "" ;endif; ?>
             </select>
         </div>
    </div>
  
  <div class="col-sm-1" style=" padding-right:0px; width:90px;">
         <div class="input-group">
             <select class="input-sm form-control" style="width:83px;padding-left: 2px;padding-right: 0px;" value="" name="bdate" onChange="getList({sign:'<?php echo $initData['sign']; ?>'},1);">
                <option value="">拜访周期</option>
                            <option value="7" <?php if(@$conditions['bdate'] == '7'): ?>selected="selected"<?php endif; ?>>7天以上</option>
                            <option value="14" <?php if(@$conditions['bdate'] == '14'): ?>selected="selected"<?php endif; ?>>14天以上</option>
							<option value="30" <?php if(@$conditions['bdate'] == '30'): ?>selected="selected"<?php endif; ?>>30天以上</option>
							<option value="60" <?php if(@$conditions['bdate'] == '60'): ?>selected="selected"<?php endif; ?>>60天以上</option>
							<option value="90" <?php if(@$conditions['bdate'] == '90'): ?>selected="selected"<?php endif; ?>>90天以上</option>
							<option value="120" <?php if(@$conditions['bdate'] == '120'): ?>selected="selected"<?php endif; ?>>120天以上</option>
                </select>
         </div>
    </div>
     <div class="col-sm-1" style=" padding-right:0px; width:90px;">
         <div class="input-group">
             <select class="input-sm form-control" style="width:83px;padding-left: 2px;padding-right: 0px;" value="" name="odate" onChange="getList({sign:'<?php echo $initData['sign']; ?>'},1);">
                <option value="">订单周期</option>
                            <option value="7" >7天以上</option>
                            <option value="14">14天以上</option>
							<option value="30">30天以上</option>
							<option value="60">60天以上</option>
							<option value="90">90天以上</option>
							<option value="120">120天以上</option>
                </select>
         </div>
    </div>
    <div class="col-sm-1" style=" padding-right:0px; width:90px;">
         <div class="input-group">
             <select class="input-sm form-control" style="width:83px;padding-left: 2px;padding-right: 0px;" value="" name="type">
                 <option value="realname" <?php if(@$conditions['type'] == 'realname'): ?>selected<?php endif; ?>>客户名称</option>
                 <option value="address" <?php if(@$conditions['type'] == 'address'): ?>selected<?php endif; ?>>客户地址</option>
                 <option value="contact" <?php if(@$conditions['type'] == 'contact'): ?>selected<?php endif; ?>>联系人</option>
                 <option value="mobile" <?php if(@$conditions['type'] == 'mobile'): ?>selected<?php endif; ?>>联系手机</option>
                 <option value="summary" <?php if(@$conditions['type'] == 'summary'): ?>selected<?php endif; ?>>客户介绍</option>
             </select>
         </div>
    </div>
    <!--style=" padding-left:0px; margin-right:0px;"-->
    <div class="col-sm-2"  >
    <div class="input-group">
        <input type="text" style="width:123px;" name="keyword" placeholder="请输入关键词" value="<?php if(@$conditions['keyword']): ?><?php echo $conditions['keyword']; else: endif; ?>" class="input-sm form-control"> <!--oninput="getList({sign:'<?php echo $initData['sign']; ?>'},1);"-->
        <span class="input-group-btn">
            <button type="button" class="btn btn-sm btn-primary" onclick="getList({sign:'<?php echo $initData['sign']; ?>'},1);"> 搜索</button>
        </span>
    </div>
    </div>
    <?php if(@$more<> '0'): ?>
    <div class="col-sm-1">
    <button type="button" class="btn btn-sm btn-primary" id="genduo">更多</button>
    </div>
    <?php endif; ?>
</div>

<?php if(@$more<> '0'): ?>
            <div class="row" id="saixuan" style=" display:none;">
              <ul class="select">
                <?php if(@$visitidSearch<> '0'): ?>
		<li class="select-list">
			<dl id="select1">
                            <dt>拜访路线：</dt>
                            <dd <?php if(@$conditions['visitid'] == ""): ?>class="select-all selected"<?php endif; ?>>
                                <label><input type="radio" onclick="getList({sign:'<?php echo $initData['sign']; ?>'},1);" value="0" name="visitid"  <?php if(@$conditions['visitid'] == ""): ?>checked="checked"<?php endif; ?> />全部</label>
                            </dd>
                            <?php if(is_array($Visits) || $Visits instanceof \think\Collection || $Visits instanceof \think\Paginator): if( count($Visits)==0 ) : echo "" ;else: foreach($Visits as $key=>$visit): if(@$visit['title'] != ''): ?>
                            <dd <?php if(@$conditions['visitid'] == $visit['id']): ?>class="select-all selected"<?php endif; ?>>
                                <label><input type="radio" onclick="getList({sign:'<?php echo $initData['sign']; ?>'},1);" value="<?php echo $visit['id']; ?>" name="visitid"  <?php if(@$conditions['visitid'] == $visit['id']): ?>checked="checked"<?php endif; ?> /><?php echo $visit['title']; ?></label>
                            </dd>
                            <?php endif; endforeach; endif; else: echo "" ;endif; ?>
                        </dl>
		</li>
                <?php endif; if(@$trustSearch<> '0'): ?>
		<li class="select-list">
                    <dl id="select5">
                            <dt>认证状态：</dt>
                            <dd <?php if(@$conditions['trust'] == '' or $conditions['trust'] == 'all'): ?>class="select-all selected"<?php endif; ?>>
                                <label><input type="radio" onclick="getList({sign:'<?php echo $initData['sign']; ?>'},1);" value="" name="trust"  <?php if(@$conditions['trust'] == '' or $conditions['trust'] == 'all'): ?>checked="checked"<?php endif; ?> />全部</label>
                            </dd>
                            <dd <?php if(@$conditions['trust'] == "0"): ?>class="select-all selected"<?php endif; ?>>
                                <label><input type="radio" onclick="getList({sign:'<?php echo $initData['sign']; ?>'},1);" value="0" name="trust"  <?php if(@$conditions['trust'] == "0"): ?>checked="checked"<?php endif; ?> />未认证</label>
                            </dd>
                            <dd <?php if(@$conditions['trust'] == "1"): ?>class="select-all selected"<?php endif; ?>>
                                <label><input type="radio" onclick="getList({sign:'<?php echo $initData['sign']; ?>'},1);" value="1" name="trust"  <?php if(@$conditions['trust'] == "1"): ?>checked="checked"<?php endif; ?> />已认证</label>
                            </dd>
                    </dl>
		</li>
                <?php endif; if(@$mapSearch<> '0'): ?>
		<li class="select-list">
			<dl id="select5">
				<dt>地图标注：</dt>
				<dd <?php if(@$conditions['x'] == ''): ?>class="select-all selected"<?php endif; ?>>
                                    <label><input type="radio" onclick="getList({sign:'<?php echo $initData['sign']; ?>'},1);" value="" name="x"  <?php if(@$conditions['x'] == ''): ?>checked="checked"<?php endif; ?> />全部</label>
                                </dd>
                                <dd <?php if(@$conditions['x'] == "1"): ?>class="select-all selected"<?php endif; ?>>
                                    <label><input type="radio" onclick="getList({sign:'<?php echo $initData['sign']; ?>'},1);" value="1" name="x"  <?php if(@$conditions['x'] == "1"): ?>checked="checked"<?php endif; ?> />未标注</label>
                                </dd>
                                <dd <?php if(@$conditions['x'] == "2"): ?>class="select-all selected"<?php endif; ?>>
                                    <label><input type="radio" onclick="getList({sign:'<?php echo $initData['sign']; ?>'},1);" value="2" name="x"  <?php if(@$conditions['x'] == "2"): ?>checked="checked"<?php endif; ?> />已标注</label>
                                </dd>
			</dl>
		</li>
                <?php endif; if(@$lastvisitSearch<> '0'): ?>
		<li class="select-list">
			<dl id="select4">
				<dt>客户资料更新：</dt>
                                <dd <?php if(@$conditions['lastvisit'] == '0'): ?>class="select-all selected"<?php endif; ?>>
                                    <label><input type="radio" onclick="getList({sign:'<?php echo $initData['sign']; ?>'},1);" value="0" name="lastvisit"  <?php if(@$conditions['lastvisit'] == '0'): ?>checked="checked"<?php endif; ?> />全部</label>
                                </dd>
				<dd <?php if(@$conditions['lastvisit'] == '1'): ?>class="select-all selected"<?php endif; ?>>
                                    <label><input type="radio" onclick="getList({sign:'<?php echo $initData['sign']; ?>'},1);" value="1" name="lastvisit"  <?php if(@$conditions['lastvisit'] == '1'): ?>checked="checked"<?php endif; ?> />一周内</label>
                                </dd>
				<dd <?php if(@$conditions['lastvisit'] == '2'): ?>class="select-all selected"<?php endif; ?>>
                                    <label><input type="radio" onclick="getList({sign:'<?php echo $initData['sign']; ?>'},1);" value="2" name="lastvisit"  <?php if(@$conditions['lastvisit'] == '2'): ?>checked="checked"<?php endif; ?> />两周内</label>
                                </dd>
				<dd <?php if(@$conditions['lastvisit'] == '3'): ?>class="select-all selected"<?php endif; ?>>
                                    <label><input type="radio" onclick="getList({sign:'<?php echo $initData['sign']; ?>'},1);" value="3" name="lastvisit"  <?php if(@$conditions['lastvisit'] == '3'): ?>checked="checked"<?php endif; ?> />一月内</label>
                                </dd>
				<dd <?php if(@$conditions['lastvisit'] == '4'): ?>class="select-all selected"<?php endif; ?>>
                                    <label><input type="radio" onclick="getList({sign:'<?php echo $initData['sign']; ?>'},1);" value="4" name="lastvisit"  <?php if(@$conditions['lastvisit'] == '4'): ?>checked="checked"<?php endif; ?> />半年内</label>
                                </dd>
				<dd <?php if(@$conditions['lastvisit'] == '5'): ?>class="select-all selected"<?php endif; ?>>
                                    <label><input type="radio" onclick="getList({sign:'<?php echo $initData['sign']; ?>'},1);" value="5" name="lastvisit"  <?php if(@$conditions['lastvisit'] == '5'): ?>checked="checked"<?php endif; ?> />一年内</label>
                                </dd>
				<dd <?php if(@$conditions['lastvisit'] == '6'): ?>class="select-all selected"<?php endif; ?>>
                                    <label><input type="radio" onclick="getList({sign:'<?php echo $initData['sign']; ?>'},1);" value="6" name="lastvisit"  <?php if(@$conditions['lastvisit'] == '6'): ?>checked="checked"<?php endif; ?> />一年前</label>
                                </dd>
			</dl>
		</li>
                <?php endif; if(@$weixinSearch<> '0'): ?>
		<li class="select-list">
			<dl id="select6">
				<dt>微信绑定状态：</dt>
                                <dd class="select-all selected">
                                    <label><input type="radio" onclick="getList({sign:'<?php echo $initData['sign']; ?>'},1);" value="0" name="weixin"  <?php if(@$conditions['weixin'] == ''): ?>checked="checked"<?php endif; ?> />全部</label>
                                </dd>
				<dd <?php if(@$conditions['weixin'] == 1): ?>class="select-all selected"<?php endif; ?>>
                                    <label><input type="radio" onclick="getList({sign:'<?php echo $initData['sign']; ?>'},1);" value="1" name="weixin"  <?php if(@$conditions['weixin'] == 1): ?>checked="checked"<?php endif; ?> />已绑定</label>
                                </dd>
                                <dd <?php if(@$conditions['weixin'] == 2): ?>class="select-all selected"<?php endif; ?>>
                                    <label><input type="radio" onclick="getList({sign:'<?php echo $initData['sign']; ?>'},1);" value="2" name="weixin"  <?php if(@$conditions['weixin'] == 2): ?>checked="checked"<?php endif; ?> />未绑定</label>
                                </dd>
			</dl>
		</li>
                <?php endif; if(@$regTimeSearch<> '0'): ?>
                <li class="select-list">
                    <dl id="select6">
                        <dt style=" line-height: 30px;">注册时间：</dt>
                        <dd  style=" line-height: 30px;">开始：</dd>
                        <dd>
                            <input type="text" class="laydate-input form-control" name="csdate" value="" placeholder="请选择一个开始时间" />
                        </dd>
                        <dd  style=" line-height: 30px;">结束</dd>
                        <dd>
                            <input type="text" class="laydate-input form-control" name="cedate" value="" placeholder="请选择一个结束时间" />
                        </dd>
                        <dd style="margin-top: 2px;">
                            <span><button type="button" class="btn btn-sm btn-primary" onclick="getList({sign:'<?php echo $initData['sign']; ?>'},1);">确定</button></span>
                        </dd>
                    </dl>
		</li>
                <?php endif; endif; ?>
	</ul>
</div>
            </form>
              <div class="ibox-content" id="<?php echo $initData['sign']; ?>List"></div>
              </div>
        </div>
          </div>
    </div>
    <div class="modal inmodal" id="gxuser" tabindex="-1" role="dialog"  aria-hidden="true">
      <div class="modal-dialog ">
            <div class="modal-content animated fadeIn">
          <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"> <span aria-hidden="true">&times;</span> <span class="sr-only">Close</span> </button>
                <h4 class="modal-title">员工客户批量操作</h4>
              </div>
          <form class="form-horizontal" method="post" action="/admin/user/plupdate" onSubmit="return plupdate(this);">
                <div class="modal-body">
              <p>说明：员工名下所有客户的共享转移操作，请谨慎操作。<br>
                <strong>共享</strong>：员工A的客户共享给员工B。<br>
                <strong>取消共享</strong>：员工A的客户不在共享给员工B。<br>
                <strong>转移</strong>：员工A的客户转移给员工B。</p>
              <input type="hidden" name="t" value="gxuser" />
              <div class="form-group">
                    <div class="col-sm-3">
                  <select class="input-sm form-control" style="width:113px;padding-left: 2px;padding-right: 0px;"  name="gx_guid1">
                 <option value="0">选择员工A</option>
                  <?php if(is_array($members) || $members instanceof \think\Collection || $members instanceof \think\Paginator): if( count($members)==0 ) : echo "" ;else: foreach($members as $key=>$member): ?>
                    <option  value="<?php echo $member['uid']; ?>"><?php echo $member['username']; ?>|<?php echo $member['realname']; ?></option>
                  <?php endforeach; endif; else: echo "" ;endif; ?>
             </select>
                </div>
                
                <div class="col-sm-3">
                  <select class="input-sm form-control" style="width:113px;padding-left: 2px;padding-right: 0px;" name="gxtype" >
                 <option value="0">选择操作类型</option>
                 <option value="1">共享</option>
                 <option value="2">取消共享</option>                 
                 <option value="3">转移</option>
                </select>
                </div>
                
                <div class="col-sm-3">
                  <select class="input-sm form-control" style="width:113px;padding-left: 2px;padding-right: 0px;"  name="gx_guid2">
                 <option value="0">选择员工B</option>
                  <?php if(is_array($members) || $members instanceof \think\Collection || $members instanceof \think\Paginator): if( count($members)==0 ) : echo "" ;else: foreach($members as $key=>$member): ?>
                    <option  value="<?php echo $member['uid']; ?>"><?php echo $member['username']; ?>|<?php echo $member['realname']; ?></option>
                  <?php endforeach; endif; else: echo "" ;endif; ?>
             </select>
                </div>
                  </div>
             
            </div>
                <div class="modal-footer">
              <input type="submit" class="btn btn-primary" id="editusersubmit" name="editusersubmit" value="提交">
              <button type="button" class="btn btn-white" data-dismiss="modal">返回</button>
            </div>
              </form>
        </div>
          </div>
    </div>
        <!--操作-->

      </div>
</div>
<script src="/static/admin/js/ajaxSearch.js"></script>
<script src="/static/admin/js/nav.js"></script>
<script src="/static/admin/js/tip.js"></script>
<script src="/static/admin/js/jquery-form.js"></script>
<script src="/static/admin/js/common.js"></script>
<script src="/static/admin/js/newcommon.js"></script>
<?php if(\think\Request::instance()->controller()): ?><script src="/static/admin/js/<?php echo strtolower(\think\Request::instance()->controller()); ?>.js"></script><?php endif; ?>
<script type="text/javascript" src="/static/common/js/dot.js"></script>
<script type="text/javascript" src="/static/common/js/common.js"></script>
<script type="text/javascript" src="/static/common/js/areas.js"></script>
<script type="text/javascript" src="/static/common/js/gpscvt.js"></script>
<script type="text/javascript" src="/static/common/js/dropload.js"></script>
<script src="/static/admin/js/bootstrap.min.js?v=3.4.0"></script>
<script src="/static/admin/js/plugins/metisMenu/jquery.metisMenu.js"></script>
<script src="/static/admin/js/plugins/slimscroll/jquery.slimscroll.min.js"></script>
<!-- Flot -->
<script src="/static/admin/js/plugins/flot/jquery.flot.js"></script>
<script src="/static/admin/js/plugins/flot/jquery.flot.tooltip.min.js"></script>
<script src="/static/admin/js/plugins/flot/jquery.flot.spline.js"></script>
<script src="/static/admin/js/plugins/flot/jquery.flot.resize.js"></script>
<script src="/static/admin/js/plugins/flot/jquery.flot.pie.js"></script>
<!-- Peity -->
<script src="/static/admin/js/plugins/peity/jquery.peity.min.js"></script>
<!-- Custom and plugin javascript -->
<script src="/static/admin/js/hplus.js?v=2.2.0"></script>
<!--script src="/static/admin/js/plugins/pace/pace.min.js"></script>
<!-- jQuery UI -->
<script src="/static/admin/js/plugins/jquery-ui/jquery-ui.min.js"></script>
<!-- GITTER -->
<script src="/static/admin/js/plugins/gritter/jquery.gritter.min.js"></script>
<!-- EayPIE -->
<script src="/static/admin/js/plugins/easypiechart/jquery.easypiechart.js"></script>
<!-- Sparkline -->
<script src="/static/admin/js/plugins/sparkline/jquery.sparkline.min.js"></script>
<!-- iCheck -->
<script src="/static/admin/js/plugins/iCheck/icheck.min.js"></script>
<script src="/static/admin/js/plugins/dropzone/dropzone.js"></script>	
<script>
    $(document).ready(function () {
        $('.i-checks').iCheck({
            checkboxClass: 'icheckbox_square-green',
            radioClass: 'iradio_square-green',
        });
    });	
    $(document).ready(function(){
        $("#genduo").click(function(){
        $("#saixuan").slideToggle();
        });
    });
</script> 
<script type="text/javascript">
    getList({sign:'<?php echo $initData['sign']; ?>'});
</script>
</body>
</html>