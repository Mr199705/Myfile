/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
function showPostImg(img){
    var src = $(img).attr('src');
    $('#showPostImgsContent').html('<div style="width:100%;height:100%;background:url('+ src +') no-repeat;background-size:100% 100%;"></div>');
    $('#showPostImgBoxBackDrop').show();
    $('#showPostImgsBox').width('80%');
    $('#showPostImgsBox').height('90%');
    $('#showPostImgBoxBackDrop').off().on('click',function (){
        $(this).hide();
    });
}
function myshowLL(url,id,data){
    if((typeof data)==='undefined' || !data){
        data = {};
    }
    var $tpl = $('#'+ id + '_tpl');
    var $o = $('#' + id);
    var $i = $('#'+ id + '_inner');
    $.ajax({
        url:url,
        data:data,
        beforeSend:function (){
			if($i.children('.page_loading').length==0){
            $i.append('<p class="page_loading" style="height:50px;line-height:50px;color:darkgreen;text-align:center;">数据加载中，请稍候！</p>');
			}
        },
        type:'post',
        success:function (res){
            $i.children('.page_loading').remove();
            if( 1 == res.code ){
                var template = doT.template($tpl.html());
                var _html = template(res.data);
                $i.append(_html);
                data.p++;
                var ih = $i.height();
                var oh = $o.height();
                if(ih <= oh - 50){
                    myshowLL(url,id,data);
                    return false;
                }
                $o.off().on('scroll',function (){
                    var st = this.scrollTop || document.body.scrollTop;//滚动了好远的距离了
                    if ((oh - 50 + st)  >= ih - 40) {
                        $(this).off();
                        myshowLL(url,id,data);
                    }
                });
            }else if(res.code == -3){
                $i.children(':last-child').remove();
                //超时
                var tip = {
                    type:1,
                    title:'提示信息',
                    content:res.msg,
                };
                var template = doT.template($('#tpldialogs').html());
                var t_html = template(tip);
                t_html = t_html.replace('id="dialogs"','');
                $o.html(t_html);
                return false;
            }else if(res.code == -4){
                //无权限
                var tip = {
                    type:1,
                    title:'提示信息',
                    content:res.msg,
                };
                var template = doT.template($('#tpldialogs').html());
                var t_html = template(tip);
                t_html = t_html.replace('id="dialogs"','');
                $o.html(t_html);
                return false;
            }else if (res.code == -5){
                var tip = {
                    type:1,
                    title:'提示信息',
                    content:res.msg,
                };
                var template = doT.template($('#tpldialogs').html());
                var t_html = template(tip);
                t_html = t_html.replace('id="dialogs"','');
                $o.html(t_html);
                setTimeout(function (){
                    window.location.href = "/admin/login/loginout/";
                },1500);
                return false;
            }else{
                if(res.total == 0  && res.p == 1){
                    var template = doT.template($tpl.html());
                    var _html = template();
                    $o.html(_html);
                }else{
                    $i.append('<p class="page_loading" style="height:50px;line-height:50px;color:#000;text-align:center;">-------------------------------我是底线-------------------------------</p>');
                }
            }
        }
    }); 
}
function showLL(url,tpl,data){
    if((typeof data)==='undefined' || !data){
        data = '';
    }
    loadd({window:0,url:url,ul:'.' + tpl,inners:'.list' + tpl,data:data,after:function(res){
        if( 1 == res.code ){
            var template = doT.template($('#' + tpl).html());
            var _html = template(res.data);
            $('.' + tpl).append(_html);
        }else if(res.code == -3){
            //超时
            var tip = {
                type:1,
                title:'提示信息',
                content:res.msg,
            };
            var template = doT.template($('#tpldialogs').html());
            var t_html = template(tip);
            t_html = t_html.replace('id="dialogs"','');
            $('.' + tpl).html(t_html);
            $('.list' + tpl).remove();
            return false;
        }else if(res.code == -4){
            //无权限
            var tip = {
                type:1,
                title:'提示信息',
                content:res.msg,
            };
            var template = doT.template($('#tpldialogs').html());
            var t_html = template(tip);
            t_html = t_html.replace('id="dialogs"','');
            $('.list' + tpl).html(t_html);
            return false;
        }else{
            if(res.total == 0  && res.p == 1){
                var template = doT.template($('#' + tpl).html());
                var _html = template();
                $('.' + tpl).html(_html);
                return res;
            }
        }
    }});
}
function showLA(url,tpl,data){
    if((typeof data)==='undefined' || !data){
        data = '';
    }
    $.ajax({
        type:'post',
        dataType:'json',
        url:url,
        data:data,
        success:function (res){
            if( 1 == res.code ){
                var template = doT.template($('#' + tpl).html());
                var _html = template(res.data);
                $('.' + tpl).html(_html);
            }else if(res.code == -3){
                //账号到期
                var tip = {
                    type:1,
                    title:'提示信息',
                    content:res.msg,
                };
                var template = doT.template($('#tpldialogs').html());
                var t_html = template(tip);
                t_html = t_html.replace('id="dialogs"','');
                $('.' + tpl).html(t_html);
                return false;
            }else if(res.code == -4){
                //无权限
                var tip = {
                    type:1,
                    title:'提示信息',
                    content:res.msg,
                };
                var template = doT.template($('#tpldialogs').html());
                var t_html = template(tip);
                t_html = t_html.replace('id="dialogs"','');
                $('.' + tpl).html(t_html);
                return false;
            }else{
                console.log(typeof res);
            }
        }
    });
}
$('.btn-open-modal').on('click',function (){
    var modal = $(this).data('id');
    var request = $(this).data('request');
    var cloneModal = $(modal).clone();
    var miss = $(cloneModal).find('.modal-close');
    $('#modalBackdropBox').show();
    $('#modalBackdropBox').html('');
    $('#modalBackdropBox').append($(cloneModal));
    $(cloneModal).show();
    $(cloneModal).removeAttr('id');
    $(miss).on('click',function (){
        $('#modalBackdropBox').hide();
        $(cloneModal).remove();
    });
    if(request !== ''){
        eval(request);
    }
});
function openModal(that,sign){
    var modal = $(that).data('id');
    console.log(modal);
    var request = $(that).data('request');
    var $modal = $(modal);
    var cloneModal = $modal.clone();
    var miss = $(cloneModal).find('.modal-close');
    if(sign === true){
        var $modalBox = $(window.top.document.getElementById('modalBackdropBox'));
    }else{
        var $modalBox = $('#modalBackdropBox');
    }
    $(cloneModal).show();
    $(cloneModal).removeAttr('id');
    $modalBox.show();
    $modalBox.html('');
    $modalBox.append($(cloneModal));
    $(miss).off().on('click',function (){
        $modalBox.hide();
        $(cloneModal).remove();
    });
    if(request !== ''){
        eval(request);
    }
}
function openModal2(btn){
    var tpl = $(btn).data('tpl');
    var datas = $(btn).data('datas');
    var data = {};
    if(!!datas){
        data = datas;
    }
    console.log(datas);
    var $modalBox = $('#modalBackdropBox');
    var template = doT.template($('#' + tpl).html());
    var thtml = template(data);
    $modalBox.html(thtml);
    var miss = $modalBox.find('.modal-close');
    $modalBox.show();
    $(miss).off().on('click',function (){
        $modalBox.html('');
        $modalBox.hide();
    });
}
function previewUploadImgs(imgInput,config){
    var that = imgInput;
    var files = that.files;
    var $ul = $('#uploaderFiles');
    if((typeof config)==='undefined'){
        config = {};
        config.l = 6;
        config.size = Math.pow(2,18);//限制在200K
        config.maxw = 1200;
        config.minw = 200;
    }
    var $f = $('<input name="preview_img[]" class="weui-uploader__input" type="file" onchange="previewUploadImgs(this,{l:' + config.l + ',size:' + config.size + '});" accept="image/png,image/gif,image/jpeg" />');
    (typeof config.l === 'undefined') ? config.l = 6 : null;
    (typeof config.size === 'undefined') ? config.size = Math.pow(2,18) : null;
    (typeof config.maxw === 'undefined') ? config.maxw = 1200 : null;
    (typeof config.minw === 'undefined') ? config.minw = 200 : null;
    for(var i=0;i < files.length; i++){
        $(that).remove();
        $('#inputFileBox').append($f);
        if(config.size < files[i].size){
            console.log('图片大于200K，无法上传到服务器，请处理后或选择其他图片上传！');
            //图片宽度不得小于200，暂时不做任何处理
            var tip = {
                tip_backdrop:true,
                tip_close:true,
                tip_msg:{
                    sign:true,
                    content:'图片大于200K，无法上传到服务器，请选择其他图片！'
                }
            };
            setTip(tip);
            return false;
        }else{
            var reader = new FileReader();
            reader.onload = function (e) {
                var img = new Image();
                img.src = e.target.result;
                img.onload = function (){
                    var w = this.width;
                    var h = this.height;
                    if(w > config.maxw){
                        //图片宽度不得大于1200,暂时不做任何的处理
                        $(that).remove();
                        $('#inputFileBox').append($f);
                        console.log('该张图片宽度过大(宽度:'+ w +'px，高度:'+ h +'px)无法达到商品图片要求，超过最大宽度600px，请处理后再上传！');
                        var tip = {
                            tip_backdrop:true,
                            tip_close:true,
                            tip_msg:{
                                sign:true,
                                content:'该张图片宽度过大(宽度:'+ w +'px，高度:'+ h +'px)无法达到商品图片要求，超过最大宽度600px，请处理后再上传！'
                            }
                        };
                        setTip(tip);
                        return false;
                    }else if(w < config.minw){
                        //图片宽度不得小于300，暂时不做任何处理
                        $(that).remove();
                        $('#inputFileBox').append($f);
                        console.log('该张图片宽度过小(宽度:'+ w +'px，高度:'+ h +'px)无法达到商品图片要求，低于最小宽度200px，请选择其他图片上传！');
                        var tip = {
                            tip_backdrop:true,
                            tip_close:true,
                            tip_msg:{
                                sign:true,
                                content:'该张图片宽度过小(宽度:'+ w +'px，高度:'+ h +'px)无法达到商品图片要求，低于最小宽度200px，请选择其他图片上传！'
                            }
                        };
                        setTip(tip);
                        return false;
                    }
                    var html = [
                        '<li class="weui-uploader__file" style="cursor: pointer;" onclick="showBigPreviewImg(this);">',
                        '<span onclick="removePreviewImg(this.parentNode,{l:' + config.l + '});" style="float: right;display: inline-block;color:#f00;width: 24px;height: 24px;line-height: 24px;border-radius: 12px;text-align: center;background-color: rgba(0,0,0,0.8);">—</span>',
                        '</li>'
                    ];
                    var $e = $(html.join(''));
                    $e.attr('data-w',w);
                    $e.attr('data-h',h);
                    $e.css('background-image','url(' + e.target.result + ')');
                    $(that).css('display','none');
                    $e.append($(that));
                    $ul.append($e);
                    var ele = $e[0];
                    showBigPreviewImg(ele,true);
                    var l = parseInt($('#uploadedNum').html());
                    l++;
                    $('#uploadedNum').html(l)
                    if(l < config.l){
                        $('#inputFileBox').show();
                        $('#inputFileBox').append($f);
                    }else{
                        $('#inputFileBox').hide();
                    }
                };
            };
            reader.readAsDataURL(files[i]);
        }
    }
}
function goodsImg(imgInput,config){
    var that = imgInput;
    var files = that.files;
    var $ul = $('#uploaderFiles2');
    var $f = $('<input name="goods_thumb" class="weui-uploader__input" type="file" onchange="goodsImg(this);" accept="image/png,image/gif,image/jpeg" />');
    if((typeof config)==='undefined'){
        config = {};
        config.l = 1;
        config.size = Math.pow(2,18);//限制在200K
        config.maxw = 600;
        config.minw = 200;
    }
    for(var i=0;i < files.length; i++){
        if(config.size < files[i].size){
            $(that).remove();
            $('#inputFileBox2').append($f);
            console.log('图片大于200K，无法上传到服务器，请处理后或选择其他图片上传！');
            var tip = {
                tip_backdrop:true,
                tip_close:true,
                tip_msg:{
                    sign:true,
                    content:'图片大于200K，无法上传到服务器，请处理后或选择其他图片上传！'
                }
            };
            setTip(tip);
            return false;
        }else{
            var reader = new FileReader();
            reader.onload = function (e) {
                var img = new Image();
                img.src = e.target.result;
                img.onload = function (){
                    var w = this.width;
                    var h = this.height;
                    if(false && w > config.maxw){
                        //图片宽度不得大于600,暂时不做任何的处理
                        $(that).remove();
                        $('#inputFileBox2').append($f);
                        var tip = {
                            tip_backdrop:true,
                            tip_close:true,
                            tip_msg:{
                                sign:true,
                                content:'该张图片宽度过大(宽度:'+ w +'px，高度:'+ h +'px)无法达到商品图片要求，超过最大宽度600px，请处理后再上传！'
                            }
                        };
                        setTip(tip);
                        return false;
                    }else if(false && w < config.minw){
                        //图片宽度不得小于200，暂时不做任何处理
                        $(that).remove();
                        $('#inputFileBox2').append($f);
                        var tip = {
                            tip_backdrop:true,
                            tip_close:true,
                            tip_msg:{
                                sign:true,
                                content:'该张图片宽度过小(宽度:'+ w +'px，高度:'+ h +'px)无法达到商品图片要求，低于最小宽度200px，请选择其他图片上传！'
                            }
                        };
                        setTip(tip);
                        return false;
                    }
                    var html = [
                        '<li class="weui-uploader__file" style="cursor: pointer;">',
                        '<span onclick="removeGoodsImg(this.parentNode);" style="float: right;display: inline-block;color:#f00;width: 24px;height: 24px;line-height: 24px;border-radius: 12px;text-align: center;background-color: rgba(0,0,0,0.8);">—</span>',
                        '</li>'
                    ];
                    var $e = $(html.join(''));
                    $e.attr('data-w',w);
                    $e.attr('data-h',h);
                    $e.css('background-image','url(' + e.target.result + ')');
                    $(that).css('display','none');
                    $e.append($(that));
                    $ul.append($e);
                    $('#inputFileBox2').hide();
                };
            };
            reader.readAsDataURL(files[i]);
        }
    }
}
function removeGoodsImg(ele){
    $(ele).remove();
    var $f = $('<input name="goods_thumb" class="weui-uploader__input" type="file" onchange="goodsImg(this);" accept="image/png,image/gif,image/jpeg" />');
    $('#inputFileBox2').append($f);
    $('#inputFileBox2').show();
}
function showBigPreviewImg(ele,sign){
    if(typeof sign === 'undefined'){
        if(event.target === ele){
            sign = true;
        }else{
            sign = false;
        }
    }
    if(sign){
        $('#previewBigImg').parent().show();
        var maxw = parseInt($('#previewBigImg').parent().width());
        var w = $(ele).data('w');
        var h = $(ele).data('h');
        if(isNaN(parseInt(maxw))){
            maxw = 600;
        }else{
            maxw > 0 ? maxw : 600;
        }
        if(w > maxw){
            h = (maxw / w) * h;
            w = maxw;
        }
        if(h > 300){
            w = (300 / h) * w;
            h = 300;
            if(w > maxw){
                h = (maxw / w) * h;
                w = maxw;
            }
        }
        $('#previewBigImg').width(w);
        $('#previewBigImg').height(h);
        $('#previewBigImg').css('background-image',$(ele).css('background-image'));
    }
}
function removePreviewImg(ele,config){
    if((typeof config)==='undefined'){
        config = {};
        config.l = 6;
    }
    $nele = $(ele).next('li');
    $pele = $(ele).prev('li');
    if($nele.length !== 0){
        var elex = $nele[0];
    }else if($pele.length !== 0){
        var elex = $pele[0];
    }
    showBigPreviewImg(elex,true);
    $(ele).remove();
    var l = parseInt($('#uploadedNum').html());
    l--;
    $('#uploadedNum').html(l)
    $('#inputFileBox').show();
    var $f = $('<input name="preview_img[]" class="weui-uploader__input" type="file" onchange="previewUploadImgs(this,{l:'+ config.l +',size:Math.pow(2,18)});" accept="image/png,image/gif,image/jpeg" />');
    $('#inputFileBox').html($f);
    if(l===0){
        $('#previewBigImg').parent().hide();
    }
}
function loadModal(e){
    var mid = 'modalBackdropBox';
    var data = $(e).data('json');
    if(!data){
        data = JSON.parse($(e).data('jsons'));
    }
    var id = $(e).data('id');
    var $outer = $('#' + mid);
    var $tpl = $('#' + id);
    var template = doT.template($tpl.html());
    var thtml = template(data);
    $outer.html(thtml);
    $outer.show();
}
function std(timestamp,showtime) {
    if(typeof showtime === 'undefined'){
        showtime = false;
    }
    var date = new Date(timestamp * 1000);//时间戳为10位需*1000，时间戳为13位的话不需乘1000
    var Y = date.getFullYear() + '-';
    var M = (date.getMonth()+1 < 10 ? '0'+(date.getMonth()+1) : date.getMonth()+1) + '-';
    if(showtime !== false){
        var D = date.getDate() < 10 ? '0' + date.getDate() + ' ': date.getDate() + ' ';
        var h = date.getHours() < 10 ? '0' + date.getHours() + ':': date.getHours() + ':';
        var m = date.getMinutes() < 10 ? '0' + date.getMinutes() + ':': date.getMinutes() + ':';
        var s = date.getSeconds() < 10 ? '0' + date.getSeconds(): date.getSeconds();
        return Y+M+D+h+m+s;
    }else{
        var D = date.getDate() < 10 ? '0' + date.getDate() + ' ': date.getDate();
        return Y+M+D;
    }
}
