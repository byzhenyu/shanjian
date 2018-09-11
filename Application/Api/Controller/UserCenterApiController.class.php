<?php
namespace Api\Controller;
use Common\Controller\ApiUserCommonController;
use Think\Verify;

class UserCenterApiController extends ApiUserCommonController{

    /**
     * @TODO
     * @desc 编辑个人资料
     */
    public function editUserInfo(){
        $data = I('post.');
        $nickname = I('nickname', '', 'trim');
        $nickNameLength = mb_strlen($nickname, 'utf-8');
        if($nickNameLength > 11){
            $this->apiReturn(V(0, '昵称不能超过11个字符'));
        }
        $saveData = array();
        $img = app_upload_img('photo', '', '', 'User');
        if (!empty($_FILES['photo'])) {
            if ($img == 0 || $img == -1) {
                $this->apiReturn(V(0, '头像上传失败'));
            }
            else{
                $saveData['head_pic'] = $img;
            }
        }
        $saveData['nickname'] = $nickname;
        $where = array('user_id' => UID);
        $result = D('Admin/User')->saveUserData($where, $saveData);
        if(false !== $result) $this->apiReturn(V(1, '保存成功'));
        $this->apiReturn(V(0, '操作失败,请稍后重试！'));
    }

    /**
     * @desc  用户修改密码
     * @param password string 用户密码
     * @param new_password string 新密码
     * @param re_password string 确认新密码
     */
    public function settingUserPwd(){
        $where = array('user_id' => UID);
        $password = I('password');
        $newPassword = I('new_password');
        $rePassword = I('re_password');
        if(!$password || !$newPassword) $this->apiReturn(V(0, '请输入密码！'));
        $passLen = strlen($newPassword);
        if($passLen < 6 || $passLen > 18) $this->apiReturn(V(0, '密码长度支持6-18位！'));
        if($newPassword != $rePassword) $this->apiReturn(V(0, '两次新密码不一致！'));
        $model = D('Admin/User');
        $userInfo = $model->getUserInfo($where, 'password');
        if(!pwdHash($password, $userInfo['password'], true)) $this->apiReturn(V(0, '原密码输入不正确！'));
        $data = $model->saveUserData($where, array('password' => $newPassword));//before_update的问题
        if(false !== $data){
            $this->apiReturn(V(1, '密码修改成功！'));
        }
        else{
            $this->apiReturn(V(0, '服务器繁忙，请稍后重试！'));
        }
    }

    /**
     * @desc 设置支付密码
     */
    public function saveUserPayPassword(){
        $mobile = I('mobile');
        $sms_code = I('sms_code', 0, 'intval');
        $pay_word = I('pay_password', '', 'trim');
        $model = D('Admin/User');
        $where = array('user_id' => UID);
        $userInfo = $model->getUserInfo($where);
        $user_type = $userInfo['user_type'];
        if(!isMobile($mobile)) $this->apiReturn(V(0, '请输入合法的手机号！'));
        $payLen = strlen($pay_word);
        if($payLen < 6 || $payLen > 18) $this->apiReturn(V(0, '密码长度6-18位！'));
        $valid = D('Admin/SmsMessage')->checkSmsMessage($sms_code, $mobile, $user_type, 6);
        if(!$valid['status']) $this->apiReturn($valid);
        $model = D('Admin/User');
        $where = array('user_id' => UID);
        $data = $model->saveUserData($where, array('pay_password' => pwdHash($pay_word)));
        if(false !== $data){
            $this->apiReturn(V(1, '支付密码设置成功！'));
        }
        else{
            $this->apiReturn(V(0, '支付密码设置失败！'));
        }
    }

    /**
     * @desc 上传身份认证凭证
     */
    public function userAuthUpload(){
        $model = D('Admin/User');
        $where = array('user_id' => UID);
        $userInfo = $model->getUserInfo($where);
        $user_type = $userInfo['user_type'];
        $user_auth = $userInfo['is_auth'];
        if($user_auth) $this->apiReturn(V(0, '身份验证已经通过！'));
        $authModel = D('Admin/UserAuth');
        $data = I('post.');
        $upArray = array();
        if(1 == $user_type){
            if(empty($_FILES['business_license'])) $this->apiReturn(V(0, '请上传营业执照！'));
            $business = app_upload_img('business_license', '', '', 'User');
            $upArray['business_license'] = $business;
        }
        $array = array('idcard_up' => '请上传身份证正面照！', 'idcard_down' => '请上传身份证反面照！', 'hand_pic' => '请上传手持身份证照！');
        $keys = array_keys($array);
        foreach($keys as &$val){
            if(empty($_FILES[$val])) $this->apiReturn(V(0, $array[$val]));
            $$val = app_upload_img($val, '', '', 'User');
            $upArray[$val] = $$val;
        }
        $upKeys = array_keys($upArray);
        foreach($upKeys as &$value){
            if($upArray[$value] == 0 || $upArray[$value] == -1){
                $tempUpload = '营业执照';
                $t = $array[$value];
                $t = str_replace('请上传', '', $t);
                $t = str_replace('！', '', $t);
                if(!$array[$value]) $t = $tempUpload;
                $this->apiReturn(V(0, $t.'上传失败！'));
            }
        }
        $data = array_merge($data, $upArray);
        $create = $authModel->create($data);
        if(false !== $create){
            $this->apiReturn(V(1, '身份验证凭据上传成功！'));
        }
        else{
            $this->apiReturn(V(0, $authModel->getError()));
        }
    }

    /**
     * @desc 发布问题
     */
    public function releaseQuestion(){
        $user_id = UID;
        $data = I('post.');
        $data['user_id'] = $user_id;
        $model = D('Admin/Question');
        $create = $model->create($data);
        if(false !== $create){
            $question_id = $model->add($data);
            if(!$question_id) $this->apiReturn(V(0, $model->getError()));
            //评论图片处理
            $photo = $_FILES['photo'];
            $questionImgModel = D('Admin/QuestionImg');
            if ($photo) {
                foreach ($photo['name'] as $key => $value) {
                    $img_url = app_upload_more_img('photo', '', 'Comment', UID, $key);
                    $data_img['item_id'] = $question_id;
                    $data_img['img_path'] = $img_url;
                    $questionImgModel->add($data_img);
                    thumb($img_url, 180,240);
                }
            }
            $this->apiReturn(V(1, '问题发布成功！'));
        }
        else{
            $this->apiReturn(V(0, $model->getError()));
        }
    }

    /**
     * @desc 发布答案
     */
    public function releaseAnswer(){
        $user_id = UID;
        $data = I('post.');
        $data['user_id'] = $user_id;
        $model = D('Admin/Answer');
        $create = $model->create($data);
        if(false !== $create){
            $answer_id = $model->add($data);
            if(!$answer_id)$this->apiReturn(V(0, $model->getError()));
            //评论图片处理
            $photo = $_FILES['photo'];
            $questionImgModel = D('Admin/QuestionImg');
            if ($photo) {
                foreach ($photo['name'] as $key => $value) {
                    $img_url = app_upload_more_img('photo', '', 'Comment', UID, $key);
                    $data_img['item_id'] = $answer_id;
                    $data_img['img_path'] = $img_url;
                    $data_img['type'] = 2;
                    $questionImgModel->add($data_img);
                    thumb($img_url, 180,240);
                }
            }
            $incWhere = array('id' => $data['question_id']);
            D('Admin/Question')->setQuestionInc($incWhere, 'answer_number');//问题回答数
            $this->apiReturn(V(1, '回答成功！'));
        }
        else{
            $this->apiReturn(V(0, $model->getError()));
        }
    }

    /**
     * @desc 获取问题详情
     */
    public function getQuestionDetail(){
        $question_id = I('question_id', 0, 'intval');
        $where = array('id' => $question_id, 'disabled' => 1);
        $quesModel = D('Admin/Question');
        $questionDetail = $quesModel->getQuestionDetail($where);
        $releaseInfo = D('Admin/User')->getUserInfo(array('user_id' => $questionDetail['user_id']));
        $questionDetail['add_time'] = time_format($questionDetail['add_time']);
        $questionDetail['head_pic'] = strval($releaseInfo['head_pic']);
        $questionDetail['nickname'] = strval($releaseInfo['nickname']);
        $ques_img_where = array('type' => 1, 'item_id' => $question_id);
        $questionImg = D('Admin/QuestionImg')->getQuestionImgList($ques_img_where);
        $answer_where = array('question_id' => $question_id);
        $answerModel = D('Admin/Answer');
        $answer_list = $answerModel->getAnswerList($answer_where);
        $questionPointsModel = D('Admin/QuestionPoints');
        $points_where = array('item_id' => $question_id, 'type' => 1, 'operate_type' => 2, 'user_id' => UID);
        $points_info = $questionPointsModel->getQuestionPointsInfo($points_where);
        if(!$points_info){
            $quesModel->setQuestionInc($where, 'browse_number');
            $questionPointsModel->add($points_where);
        }
        $returnArray = array('question' => $questionDetail, 'question_img' => $questionImg, 'answer_list' => $answer_list['info']);
        $this->apiReturn(V(1, '问题详情获取成功！', $returnArray));
    }

    /**
     * @desc 问题点赞
     */
    public function likeQuestion(){
        $data = I('post.');
        $data['user_id'] = UID;
        $model = D("Admin/QuestionPoints");
        $where = array(
            'item_id' => $data['item_id'],
            'user_id' => $data['user_id'],
            'operate_type' => 1,
            'type' => 1
        );
        $info = $model->getQuestionPointsInfo($where);
        if($info) $this->apiReturn(V(0, '您已经对该问题点过赞！'));
        M()->startTrans();
        $create = $model->create($data);
        if(false !== $create){
            $res = $model->add($data);
            if(false !== $res){
                $incWhere = array('id' => $data['item_id']);
                $qRes = D('Admin/Question')->setQuestionInc($incWhere, 'like_number');
                if(false !== $qRes){
                    $model->add($where);
                    M()->commit();
                    $this->apiReturn(V(1, '点赞成功！'));
                }
                else{
                    M()->rollback();
                    $this->apiReturn(V(0, '点赞失败！'));
                }
            }
            else{
                $this->apiReturn(V(0, $model->getError()));
            }
        }
        else{
            $this->apiReturn(V(0, $model->getError()));
        }
    }

    /**
     * @desc 回答点赞
     */
    public function likeAnswer(){
        $data = I('post.');
        $data['user_id'] = UID;
        if(!$data['type']) $data['type'] = 2;
        $model = D("Admin/QuestionPoints");
        $where = array(
            'item_id' => $data['item_id'],
            'user_id' => $data['user_id'],
            'operate_type' => 1,
            'type' => 2
        );
        $info = $model->getQuestionPointsInfo($where);
        if($info) $this->apiReturn(V(0, '您已经对该回答点过赞！'));
        M()->startTrans();
        $create = $model->create($data);
        if(false !== $create){
            $res = $model->add($data);
            if(false !== $res){
                $incWhere = array('id' => $data['item_id']);
                $qRes = D('Admin/Answer')->setAnswerInc($incWhere, 'like_number');
                if(false !== $qRes){
                    $model->add($where);
                    M()->commit();
                    $this->apiReturn(V(1, '点赞成功！'));
                }
                else{
                    M()->rollback();
                    $this->apiReturn(V(0, '点赞失败！'));
                }
            }
            else{
                $this->apiReturn(V(0, $model->getError()));
            }
        }
        else{
            $this->apiReturn(V(0, $model->getError()));
        }
    }

    /**
     * @desc 设置答案为最佳答案
     */
    public function settingAnswerOptimum(){
        $answer_id = I('answer_id', 0, 'intval');
        $question_id = I('question_id', 0, 'intval');
        if(!$answer_id || !$question_id) $this->apiReturn(V(0, '请传入合法的参数！'));
        $where = array('id' => $answer_id, 'question_id' => $question_id, 'user_id' => UID);
        $res = D('Admin/Answer')->settingOptimum($where);
        $this->apiReturn($res);
    }

    /**
     * @desc 我的提问列表
     */
    public function getPersonalQuestion(){
        $where = array('a.user_id' => UID, 'a.disabled' => 1);
        $model = D('Admin/Question');
        $field = 'u.nickname,u.head_pic,a.id,a.like_number,a.browse_number,a.answer_number,a.add_time,a.question_title';
        $question = $model->getQuestionList($where, $field);
        $question_list = $question['info'];
        foreach($question_list as &$val){
            $val['nickname'] = strval($val['nickname']);
            $val['head_pic'] = strval($val['head_pic']);
            $val['add_time'] = time_format($val['add_time'], 'Y-m-d');
            $img_where = array('type' => 1, 'item_id' => $val['id']);
            $val['question_img'] = D('Admin/QuestionImg')->getQuestionImgList($img_where);
        }
        unset($val);
        $this->apiReturn(V(1, '获取成功！', $question_list));
    }

    /**
     * @desc 我的回答列表
     */
    public function getPersonalAnswer(){
        $where = array('a.user_id' => UID);
        $model = D('Admin/Answer');
        $answer_field = 'a.id,a.answer_content,a.add_time,a.question_id,a.is_anonymous,u.nickname,u.head_pic';
        $answer = $model->getAnswerList($where, $answer_field);
        $answerList = $answer['info'];
        $ques_model = D('Admin/Question');
        $ques_img_model = D('Admin/QuestionImg');
        $ques_field = 'id,question_title,question_content,question_type,like_number,browse_number,answer_number,add_time';
        foreach($answerList as &$val){
            $t_ques_where = array('id' => $val['question_id']);
            $val['question_detail'] = $ques_model->getQuestionDetail($t_ques_where, $ques_field);
            $val['question_detail']['add_time'] = time_format($val['question_detail']['add_time'], 'Y-m-d');
            $img_where = array('type' => 1, 'item_id' => $val['question_id']);
            $val['question_img'] = $ques_img_model->getQuestionImgList($img_where);
        }
        $this->apiReturn(V(1, '', $answerList));
    }

    /**
     * @desc 联系人关系列表
     */
    public function getContactsRelationList(){
        $model = D('Admin/ContactsRelation');
        $list = $model->getContactsRelationList();
        if($list){
            $this->apiReturn(V(1, '联系人关系列表获取成功！', $list['info']));
        }
        else{
            $this->apiReturn(V(0, '获取联系人关系列表失败！'));
        }
    }

    /**
     * @desc 获取联系人列表
     */
    public function getContactsList(){
        $where = array('user_id' => UID);
        $model = D('Admin/Contacts');
        $list = $model->getContactsList($where);
        if($list){
            $this->apiReturn(V(1, '联系人列表获取成功！', $list['info']));
        }
        else{
            $this->apiReturn(V(0, '联系人列表获取失败！'));
        }
    }

    /**
     * @desc 紧急联系人添加/编辑
     */
    public function editContacts(){
        $data = I('post.');
        $data['user_id'] = UID;
        $model = D('Admin/Contacts');
        if($data['id'] > 0){
            $create = $model->create($data, 2);
            if(false !== $create){
                $res = $model->save($data);
                if(false !== $res){
                    $this->apiReturn(V(1, '保存成功！'));
                }
                else{
                    $this->apiReturn(V(0, $model->getError()));
                }
            }
            else{
                $this->apiReturn(V(0, $model->getError()));
            }
        }
        else{
            $create = $model->create($data, 1);
            if(false !== $create){
                $res = $model->add($data);
                if(false !== $res){
                    $this->apiReturn(V(1, '保存成功！'));
                }
                else{
                    $this->apiReturn(V(0, $model->getError()));
                }
            }
            else{
                $this->apiReturn(V(0, $model->getError()));
            }
        }
    }

    /**
     * @desc 获取紧急联系人详情
     */
    public function getContactsInfo(){
        $id = I('id', 0, 'intval');
        $where = array('id' => $id, 'user_id' => UID);
        $model = D('Admin/Contacts');
        $res = $model->getContactsInfo($where);
        if($res){
            $this->apiReturn(V(1, '联系人详情获取成功！', $res));
        }
        else{
            $this->apiReturn(V(0, '联系人详情获取失败！'));
        }
    }

    /**
     * @desc 删除紧急联系人
     */
    public function deleteContacts(){
        $id = I('id', 0, 'intval');
        $where = array('id' => $id, 'user_id' => UID);
        $model = D('Admin/Contacts');
        $del = $model->delContacts($where);
        if(false !== $del){
            $this->apiReturn(V(1, '删除成功！'));
        }
        else{
            $this->apiReturn(V(0, '删除失败！'));
        }
    }

    /**
     * @desc 获取行业/职位信息列表
     */
    public function getPositionIndustryList(){
        $type = I('type', 1, 'intval');
        $parent_id = I('parent_id', 0, 'intval');
        $where = array('parent_id' => $parent_id);
        switch ($type){
            case 1:
                $model = D('Admin/Industry');
                $field = 'id,industry_name as name,parent_id,sort';
                $list = $model->getIndustryList($where, $field);
                break;
            case 2:
                $model = D('Admin/Position');
                $field = 'id,position_name as name,parent_id,sort';
                $list = $model->getPositionList($where, $field, '', false);
                break;
            default:
                $this->apiReturn(V(0, '不合法的数据类型！'));
        }
        $this->apiReturn(V(1, '列表信息获取成功！', $list));
    }

    /**
     * @desc 列表功能
     */
    public function getAssistList(){
        $type = I('type', 0, 'intval');
        switch($type){
            case 1:
                $model = D('Admin/Education');
                $field = 'id,education_name as name';
                $list = $model->getEducationList(array(), $field);
                break;
            case 2:
                $model = D('Admin/CompanyNature');
                $field = 'id,nature_name as name';
                $list = $model->getCompanyNatureList(array(), $field);
                break;
            default:
                $this->apiReturn(V(0, '不合法的数据类型！'));
        }
        $this->apiReturn(V(1, '列表获取成功！', $list));
    }

    /**
     * @desc 获取标签
     */
    public function getTags(){
        $type = I('type', 0, 'intval');
        if(!in_array($type, array(1,2,3,4,5))) $this->apiReturn(V(0, '标签类型不合法！'));
        $model = D('Admin/Tags');
        $where = array('tags_type' => $type);
        $list = $model->getTagsList($where);
        $this->apiReturn(V(1, '标签列表获取成功！', $list));
    }

    /**
     * @desc 获取公司列表
     */
    public function getCompanyList(){
        $keywords = I('keywords', '', 'trim');
        $where = array('company_name' => array('like', '%'.$keywords.'%'));
        $list = D('Admin/Company')->getCompanyList($where);
        $this->apiReturn(V(1, '公司列表获取成功！', $list['info']));
    }

    /**
     * @desc 获取用户银行卡号列表
     */
    public function getUserBankList(){
        $where = array('user_id' => UID);
        $model = D('Admin/UserBank');
        $list = $model->getUserBankList($where);
        $this->apiReturn(V(1, '银行卡号列表获取成功!', $list['info']));
    }

    /**
     * @desc 添加/编辑用户银行卡号信息
     */
    public function editUserBank(){
        $data = I('post.');
        $model = D('Admin/UserBank');
        if($data['id'] > 0){
            $create = $model->create($data, 2);
            if(false !== $create){
                $res = $model->save($data);
                if(false !== $res){
                    $this->apiReturn(V(1, '保存成功！'));
                }
                else{
                    $this->apiReturn(V(0, $model->getError()));
                }
            }
            else{
                $this->apiReturn(V(0, $model->getError()));
            }
        }
        else{
            $create = $model->create($data, 1);
            if(false !== $create){
                $res = $model->add($data);
                if(false !== $res){
                    $this->apiReturn(V(1, '保存成功！'));
                }
                else{
                    $this->apiReturn(V(0, $model->getError()));
                }
            }
            else{
                $this->apiReturn(V(0, $model->getError()));
            }
        }
    }

    /**
     * @desc 获取银行卡号信息
     */
    public function getUserBankInfo(){
        $id = I('post.id', 0, 'intval');
        $where = array('id' => $id, 'user_id' => UID);
        $model = D('Admin/UserBank');
        $info = $model->getUserBankInfo($where);
        if($info){
            $this->apiReturn(V(1, '银行卡信息获取成功！', $info));
        }
        else{
            $this->apiReturn(V(0, '银行卡信息获取失败！'));
        }
    }

    /**
     * @desc 删除银行卡号
     */
    public function deleteUserBank(){
        $id = I('post.id');
        $where = array('user_id' => UID, 'id' => $id);
        $model = D('Admin/UserBank');
        $res = $model->deleteUserBank($where);
        if(false !== $res){
            $this->apiReturn(V(1, '银行卡号删除成功！'));
        }
        else{
            $this->apiReturn(V(0, '操作错误！'));
        }
    }

    /**
     * @desc 用户提现
     */
    public function userWithdraw(){
        $user_id = UID;
        $amount = I('amount', 0, 'intval');
        $bank_id = I('bank_id', 0, 'intval');
        if($amount <= 0) $this->apiReturn(V(0, '请输入合法的提现金额！'));
        $user_model = D('Admin/User');
        $bank_where = $user_where = array('user_id' => $user_id);
        $bank_model = D('Admin/UserBank');
        $bank_where['id'] = $bank_id;
        $bank_info = $bank_model->getUserBankInfo($bank_where);
        if(!$bank_info) $this->apiReturn(V(0, '未找到相关的银行卡号信息！'));
        $user_info = $user_model->getUserInfo($user_where, 'withdrawable_amount,frozen_money');
        $user_withdraw_amount = $user_info['withdrawable_amount'];
        $amount = yuan_to_fen($amount);
        if($amount > $user_withdraw_amount) $this->apiReturn(V(0, '可提现金额不足！'));
        M()->startTrans();
        $user_account_model = D('Admin/UserAccount');
        $accountData = array(
            'user_id' => UID,
            'money' => $amount,
            'type' => 1,
            'payment' => 1,
            'brank_no' => $bank_info['bank_num'],
            'brank_name' => $bank_info['bank_name'],
            'brank_user_name' => $bank_info['cardholder'],
            'trade_no' => 'T'.randNumber(18)
        );
        $account_res = $user_account_model->add($accountData);
        if(!$account_res){
            M()->rollback();
            $this->apiReturn(V(0, '提现数据写入失败！'));
        }
        $withdrawable_amount = $user_withdraw_amount - $amount;
        $user_frozen_money = $user_info['frozen_money'] + $amount;
        $save_data = array('frozen_money' => $user_frozen_money, 'withdrawable_amount' => $withdrawable_amount);
        $user_res = $user_model->saveUserData($user_where, $save_data);
        if(!$user_res){
            M()->rollback();
            $this->apiReturn(V(0, '用户信息修改失败！'));
        }
        else{
            account_log($user_id, $amount, 1, '用户提现！', $account_res);
            M()->commit();
            $this->apiReturn(V(1, '用户提现成功！'));
        }
    }

    /**
     * @desc 创建简历
     */
    public function writeResume(){
        $data = I('post.');
        $data['user_id'] = UID;
        $model = D('Admin/Resume');
        if(!empty($_FILES['photo'])) {
            $img = app_upload_img('photo', '', '', 'User');
            if ($img == 0 || $img == -1) {
                $this->apiReturn(V(0, '头像上传失败'));
            }
            else{
                $data['head_pic'] = $img;
            }
        }
        if(!empty($_FILES['voice'])){
            $img = app_upload_file('voice', '', '', 'Resume');
            if ($img == 0 || $img == -1) {
                $this->apiReturn(V(0, '语音文件上传失败！'));
            }
            else{
                $data['introduced_voice'] = $img;
            }
        }
        if($data['id'] > 0){
            $create = $model->create($data, 2);
            if(false !== $create){
                $res = $model->save($data);
                if(false !== $res){
                    $this->apiReturn(V(1, '基本资料保存成功！'));
                }
                else{
                    $this->apiReturn(V(0, $model->getError()));
                }
            }
            else{
                $this->apiReturn(V(0, $model->getError()));
            }
        }
        else{
            $create = $model->create($data, 1);
            if(false !== $create){
                $res = $model->add($data);
                if($res > 0){
                    $this->apiReturn(V(1, '基本资料保存成功！'));
                }
                else{
                    $this->apiReturn(V(0, $model->getError()));
                }
            }
            else{
                $this->apiReturn(V(0, $model->getError()));
            }
        }
    }

    /**
     * @desc 获取简历基本资料
     */
    public function getResumeInfo(){
        $where = array('user_id' => UID);
        $model = D('Admin/Resume');
        $res = $model->getResumeInfo($where);
        if($res) $this->apiReturn(V(1, '简历获取成功！', $res));
        $this->apiReturn(V(0, '简历获取失败！'));
    }

    /**
     * @desc 写工作经历
     */
    public function writeResumeWork(){
        $data = I('post.');
        $data['user_id'] = UID;
        $model = D('Admin/ResumeWork');
        if(!$data['resume_id']) $data['resume_id'] = D('Admin/Resume')->getResumeField(array('user_id' => UID), 'id');
        $hr_mobile = $data['mobile'];
        $hr_name = $data['hr_name'];
        if($data['id'] > 0){
            $create = $model->create($data, 2);
            if(false !== $create){
                $res = $model->save($data);
                if(false !== $res){
                    $this->apiReturn(V(1, '保存成功！'));
                }
                else{
                    $this->apiReturn(V(0, $model->getError()));
                }
            }
        }
        else{
            if(!isMobile($hr_mobile)) $this->apiReturn(V(0, '请输入合法的hr手机号！'));
            if(!$hr_name) $this->apiReturn(V(0, '请输入hr姓名！'));
            M()->startTrans();
            $create = $model->create($data, 1);
            if (false !== $create){
                $res = $model->add($data);
                if($res > 0){
                    $resumeAuth = array('resume_id' => $res, 'hr_name' => $hr_name, 'hr_mobile' => $hr_mobile, 'user_id' => UID);
                    //简历验证
                    $auth_res = D('Admin/ResumeAuth')->changeResumeAuth($resumeAuth);
                    if(false !== $auth_res){
                        //TODO 发送短信
                        //TODO sendMessageRequest();
                    }
                    M()->commit();
                    $this->apiReturn(V(1, '保存成功！'));
                }
                else{
                    M()->rollback();
                    $this->apiReturn(V(0, $model->getError()));
                }
            }
            else{
                M()->rollback();
                $this->apiReturn(V(0, $model->getError()));
            }
        }
    }

    /**
     * @desc 删除工作经历
     */
    public function deleteResumeWork(){
        $id = I('post.id');
        $where = array('id' => $id, 'user_id' => UID);
        $model = D('Admin/ResumeWork');
        $res = $model->deleteResumeWork($where);
        if($res){
            $this->apiReturn(V(1, '删除成功！'));
        }
        else{
            $this->apiReturn(V(0, '删除失败！'));
        }
    }

    /**
     * @desc 获取工作经历详情
     */
    public function getResumeWorkInfo(){
        $id = I('post.id');
        $where = array('id' => $id, 'user_id' => UID);
        $model = D('Admin/ResumeWork');
        $res = $model->getResumeWorkInfo($where);
        $res['starttime'] = time_format($res['starttime']);
        $res['endtime'] = time_format($res['endtime']);
        if($res){
            $this->apiReturn(V(1, '经历详情获取成功！', $res));
        }
        else{
            $this->apiReturn(V(0, '获取失败！'));
        }
    }

    /**
     * @desc 填写简历教育经历
     */
    public function writeResumeEdu(){
        $data = I('post.');
        if(!$data['resume_id']) $data['resume_id'] = D('Admin/Resume')->getResumeField(array('user_id' => UID), 'id');
        $model = D('Admin/ResumeEdu');
        if($data['id'] > 0){
            $create = $model->create($data, 2);
            if(false !== $create){
                $res = $model->save($data);
                if(false !== $res){
                    $this->apiReturn(V(1, '学历信息保存成功！'));
                }
                else{
                    $this->apiReturn(V(0, $model->getError()));
                }
            }
            else{
                $this->apiReturn(V(0, $model->getError()));
            }
        }
        else{
            $create = $model->create($data, 1);
            if(false !== $create){
                $res = $model->add($data);
                if($res > 0){
                    $this->apiReturn(V(1, '学历信息保存成功！'));
                }
                else{
                    $this->apiReturn(V(0, $model->getError()));
                }
            }
            else{
                $this->apiReturn(V(0, $model->getError()));
            }
        }
    }

    /**
     * @desc 获取简历教育背景详情
     */
    public function getResumeEduInfo(){
        $id = I('post.id');
        $where = array('id' => $id, 'user_id' => UID);
        $model = D('Admin/ResumeEdu');
        $res = $model->getResumeEduInfo($where);
        $res['starttime'] = time_format($res['starttime'], 'Y-m-d');
        $res['endtime'] = time_format($res['endtime'], 'Y-m-d');
        if($res){
            $this->apiReturn(V(1, '', $res));
        }
        else{
            $this->apiReturn(V(0, '获取失败！'));
        }
    }

    /**
     * @desc 删除教育经历
     */
    public function deleteResumeEdu(){
        $id = I('post.id');
        $where = array('id' => $id, 'user_id' => UID);
        $model = D('Admin/ResumeEdu');
        $res = $model->deleteResumeEdu($where);
        if($res){
            $this->apiReturn(V(1, '删除成功！'));
        }
        else{
            $this->apiReturn(V(0, '删除失败！'));
        }
    }

    /**
     * @desc 评价简历
     */
    public function scoreResume(){
        $data = I('post.');
        $data['user_id'] = UID;
        $model = D('Admin/ResumeEvaluation');
        $create = $model->create($data);
        if(false !== $create){
            $res = $model->add($data);
            if($res){
                $this->apiReturn(V(1, '评价成功！'));
            }
            else{
                $this->apiReturn(V(0, $model->getError()));
            }
        }
        else{
            $this->apiReturn(V(0, $model->getError()));
        }
    }

    /**
     * @desc 获取简历详情
     */
    public function getResumeDetail(){
        $user_id = UID;
        $id = I('post.id');
        $resume_id = I('post.resume_id');
        if(!$resume_id) $resume_id = D('');
        $resumeModel = D('Admin/Resume');
        $resumeWorkModel = D('Admin/ResumeWork');
        $resumeEduModel = D('Admin/ResumeEdu');
        $resumeEvaluationModel = D('Admin/ResumeEvaluation');
        $recruitResumeModel = D('Admin/RecruitResume');
        $recruit_where = array('id' => $id);
        $recommend_info = $recruitResumeModel->getRecruitResumeField($recruit_where, 'recommend_label,recommend_voice');
        $resume_where = array('id' => $resume_id);
        $resumeDetail = $resumeModel->getResumeInfo($resume_where);
        if(!$resumeDetail && $user_id == $resumeDetail['user_id']) $this->apiReturn(V(0, '您还没有填写简历！'));
        $where = array('resume_id' => $resume_id);
        $resumeWorkList = $resumeWorkModel->getResumeWorkList($where);
        $resumeEduList = $resumeEduModel->getResumeEduList($where);
        $resumeEvaluation = $resumeEvaluationModel->getResumeEvaluationAvg($where);
        $sum = array_sum(array_values($resumeEvaluation));
        $avg = round($sum/(count($resumeEvaluation)), 2);
        $return = array('detail' => $resumeDetail, 'resume_work' => $resumeWorkList, 'resume_edu' => $resumeEduList, 'resume_evaluation' => $resumeEvaluation, 'evaluation_avg' => $avg, 'recruit_resume' => $recommend_info);
        $this->apiReturn(V(1, '简历获取成功！', $return));
    }


    //TODO
    //TODO
    //TODO
    /**
     * @desc 简历认证列表
     */
    public function authResumeList(){
        $where = array('a.hr_id' => UID);
        $model = D('ResumeAuth');
        $list = $model->getResumeAuthList($where);
        if($list['info']){
            $this->apiReturn(V(1, '简历认证列表获取成功！', $list['info']));
        }
        else{
            $this->apiReturn(V(0, '简历认证列表获取失败！'));
        }
    }

    /**
     * @desc 简历认证确认/放弃
     */
    public function confirmResumeAuth(){
        $id = I('post.id');
        $auth_result = I('post.auth_result');
        if(!in_array($auth_result, array(1, 2))) $this->apiReturn(V(0, '认证状态有误！'));
        $user_where = array('user_id' => UID);
        $userModel = D('Admin/User');
        $user_info = $userModel->getUserInfo($user_where);
        $resume_auth_where = array('id' => $id, 'hr_id' => UID);
        $resumeAuthModel = D('Admin/ResumeAuth');
        $resume_auth_info = $resumeAuthModel->getResumeAuthInfo($resume_auth_where);
        if(!$resume_auth_info || $resume_auth_info['hr_mobile'] != $user_info['mobile']) $this->apiReturn(V(0, '认证信息有误！'));
        $save_data = array('auth_result' => $auth_result, 'auth_time' => NOW_TIME);
        M()->startTrans();
        $res = $resumeAuthModel->saveResumeAuthData($resume_auth_where, $save_data);
        if(1 == $auth_result){
            if(false !== $res){
                M()->commit();
                $this->apiReturn(V(1, '认证操作成功！'));
            }
            else{
                M()->rollback();
                $this->apiReturn(V(0, '认证操作失败！'));
            }
        }
        else{
            $hr_resume_model = D('Admin/HrResume');
            $data = I('post.');
            $create = $hr_resume_model->create($data);
            if(false !== $create){
                $hr_resume_result = $hr_resume_model->add($data);
                if(false !== $hr_resume_result && false !== $res){
                    $task_id = 1;
                    $task_log_res = add_task_log(UID, $task_id);
                    if($task_log_res) D('Admin/User')->changeUserWithdrawAbleAmount(UID, 1, $task_id);
                    M()->commit();
                    $this->apiReturn(V(1, '认证操作成功！'));
                }
                else{
                    M()->rollback();
                    $this->apiReturn(V(0, $hr_resume_model->getError()));
                }
            }
            else{
                M()->rollback();
                $this->apiReturn(V(0, $hr_resume_model->getError()));
            }
        }
    }
}