<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2023 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------
namespace app\api\controller\v1\user;

use app\dao\order\DeliveryServiceDao;
use app\dao\user\UserDao;
use app\dao\user\UserMoneyDao;
use app\dao\user\UserWithdrawalDao;
use app\model\user\UserWithdrawal;
use app\Request;
use app\services\order\StoreOrderCreateServices;
use app\services\pay\PayServices;
use app\services\user\UserRechargeServices;
use think\App;
use think\Db;

/**
 * 充值类
 * Class UserRechargeController
 * @package app\api\controller\user
 */
class UserWithdrawalController
{
    protected $services = NUll;

    /**
     * UserRechargeController constructor.
     * @param UserRechargeServices $services
     */
    public function __construct(UserRechargeServices $services)
    {
        $this->services = $services;
    }

	
	
	
	public function apply(Request $request) {
		[$amount,$bankAccount,$password] = $request->postMore([
			['amount',0],['bankAccount',''],['password','']
		                                                      ],true);
		if (!$amount || $amount <= 0) return app('json')->fail('请输入正确的金额');
		if (!$bankAccount) return app('json')->fail('请输入您的银行账号');
		if (!$password) return app('json')->fail('密码无效');
		
		$uid = $request->uid();
		$balance = $request->user('now_money');
		if ($amount > $balance) return app('json')->fail('余额不足');
		
		
		$orderId = app()->make(StoreOrderCreateServices::class)->getNewOrderId('tx');
		
		\think\facade\Db::startTrans();
		try {
			//扣除金额
			$user = (new UserDao())->getOne(['uid' => $uid]);
			$user['now_money'] = $user['now_money'] - $amount;
			$user->save();
			
			
			//添加提现记录
			$data = [
				'uid' => $uid,
				'order_id' => $orderId,
				'price' => $amount,
				'status' => 0,
				'add_time' => time(),
				'finish_time' => 0,
				'bank_account' => $bankAccount,
				'channel_type' => 'h5',
			];
			$withdrawalDao = new UserWithdrawalDao();
			$saved = $withdrawalDao->save($data);
			//添加余额变动记录
			$log = [
				'uid' => $uid,
				'link_id' => $saved->id,
				'type' => 'withdrawal',
				'title' => '用户提现',
				'number' => $amount,
				'balance' =>  $user['now_money'],
				'pm' => 0,
				'mark' => "用户申请提现{$amount}",
				'status' => 0,
				'add_time' => time()
			];
			(new UserMoneyDao())->save($log);
			\think\facade\Db::commit();
			return app('json')->success();
		}
		catch (\Throwable $e) {
			\think\facade\Db::rollback();
			return app('json')->fail(410126);
		}
	}
	
	
	
	
	
	
    /**
     * 用户充值
     * @param Request $request
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function recharge(Request $request)
    {
        [$price, $recharId, $bankAccount,$type, $from] = $request->postMore([
            ['price', 0],
            ['rechar_id', 0],
            ['bankAccount', ''],
            ['type', 0],
            ['from', ''],
        ], true);
        if (!$price || $price <= 0) return app('json')->fail(410122);
        if (!in_array($type, [0, 1])) return app('json')->fail(410123);
//        if (!in_array($from, [PayServices::WEIXIN_PAY, 'weixinh5', 'routine', PayServices::ALIAPY_PAY])) return app('json')->fail(410123);
        $storeMinRecharge = sys_config('store_user_min_recharge');
        if (!$recharId && $price < $storeMinRecharge) return app('json')->fail(410124, null, ['money' => $storeMinRecharge]);
        $uid = (int)$request->uid();
        $re = $this->services->recharge($uid, $price, $recharId, $type, $from, true,$bankAccount);
        if ($re) {
            $payType = '';
            return app('json')->status($payType, 410125, $re);
        }
        return app('json')->fail(410126);
    }

    /**
     * TODO 小程序充值 弃用
     * @param Request $request
     * @return mixed
     */
    public function routine(Request $request)
    {
        [$price, $recharId, $type] = $request->postMore([['price', 0], ['rechar_id', 0], ['type', 0]], true);
        if (!$price || $price <= 0) return app('json')->fail(410122);
        $storeMinRecharge = sys_config('store_user_min_recharge');
        if ($price < $storeMinRecharge) return app('json')->fail(410124, null, ['money' => $storeMinRecharge]);
        $from = 'routine';
        $uid = (int)$request->uid();
        $re = $this->services->recharge($uid, $price, $recharId, $type, $from);
        if ($re) {
            unset($re['msg']);
            return app('json')->success(410125, $re['data']);
        }
        return app('json')->fail(410126);
    }

    /**
     * TODO 公众号充值 弃用
     * @param Request $request
     * @return mixed
     */
    public function wechat(Request $request)
    {
        [$price, $recharId, $from, $type] = $request->postMore([['price', 0], ['rechar_id', 0], ['from', 'weixin'], ['type', 0]], true);
        if (!$price || $price <= 0) return app('json')->fail(410122);
        $storeMinRecharge = sys_config('store_user_min_recharge');
        if ($price < $storeMinRecharge) return app('json')->fail(410124, null, ['money' => $storeMinRecharge]);
        $uid = (int)$request->uid();
        $re = $this->services->recharge($uid, $price, $recharId, $type, $from);
        if ($re) {
            unset($re['msg']);
            return app('json')->success(410125, $re);
        }
        return app('json')->fail(410126);
    }

    /**
     * 充值额度选择
     * @return mixed
     */
    public function index()
    {
        $rechargeQuota = sys_data('user_recharge_quota') ?? [];
        $data['recharge_quota'] = $rechargeQuota;
        $recharge_attention = sys_config('recharge_attention');
        $recharge_attention = explode("\n", $recharge_attention);
        $data['recharge_attention'] = $recharge_attention;
        return app('json')->success($data);
    }
}
