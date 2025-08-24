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
namespace app\adminapi\controller\v1\finance;

use app\adminapi\controller\AuthController;
use app\services\user\UserRechargeServices;
use app\services\user\UserWithdrawalServices;
use think\facade\App;

/**
 * Class UserRecharge
 * @package app\adminapi\controller\v1\finance
 */
class UserWithdrawal extends AuthController
{
    /**
     * UserRecharge constructor.
     * @param App $app
     * @param UserWithdrawalServices $services
     */
    public function __construct(App $app, UserWithdrawalServices $services)
    {
        parent::__construct($app);
        $this->services = $services;
    }

    /**
     * 显示资源列表
     * @return \think\Response
     */
    public function index()
    {
        $where = $this->request->getMore([
            ['data', ''],
            ['paid', ''],
            ['nickname', ''],
        ]);
        return app('json')->success($this->services->getWithdrawalList($where));
    }
	
	/**
	 * 确认充值到账
	 * @return void
	 */
	public function confirm() {
		$params = $this->request->getMore([
			['id',0]
		]);
		return app('json')->success($this->services->confirm($params['id']) ? 100002 : 100008);
	}

	/**
	 * 确认充值到账
	 * @return void
	 */
	public function reject() {
		$params = $this->request->getMore([
			['id',0]
		]);
		return app('json')->success($this->services->reject($params['id']) ? 100002 : 100008);
	}

    /**
     * 删除指定资源
     * @param int $id
     * @return \think\Response
     */
    public function delete($id)
    {
        if (!$id) return app('json')->fail(100100);
        return app('json')->success($this->services->delRecharge((int)$id) ? 100002 : 100008);
    }

    /**
     * 获取用户提现数据
     * @return array
     */
    public function user_withdrawal()
    {
        $where = $this->request->getMore([
            ['data', ''],
            ['paid', ''],
            ['nickname', ''],
        ]);
        return app('json')->success($this->services->user_withdrawal($where));
    }
}
