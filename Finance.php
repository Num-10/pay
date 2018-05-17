<?php
namespace app\home\controller;

use think\Log;
use think\Cache;
use think\Validate;
use app\common\controller\Homebase;
use app\common\model\App as AppModel;
use app\common\model\Addon as AddonModel;
use app\common\model\Finance as FinanceModel;
use WxPayNotify;
use AlipayNotify;
require EXTEND_PATH . 'wxpay-sdk/WxPay.Api.php';
require EXTEND_PATH . 'wxpay-sdk/WxPay.Notify.php';
require EXTEND_PATH . 'alipay-sdk/alipay_notify.class.php';

class Finance extends Homebase
{
  public function _initialize()
  {
    parent::_initialize();
  }

  /* 支付宝服务器异步通知页面路径 */
  public function alipay_notify()
  {
    // 引入支付宝配置
    require EXTEND_PATH . 'alipay-sdk/alipay.config.php';
    // 计算得出通知验证结果
    $alipayNotify = new AlipayNotify($alipay_config);
    $verify = $alipayNotify->verifyNotify();
    if ($verify) {
      $out_trade_no = input('post.out_trade_no', ''); // 商户订单号
      $trade_no = input('post.trade_no', ''); // 支付宝交易号
      $trade_status = input('post.trade_status', ''); // 交易状态
      $total_fee = input('post.total_fee', ''); // 交易金额
      $gmt_payment = input('post.gmt_payment', ''); // 付款时间
      if ($trade_status == 'TRADE_SUCCESS') {
        if (!FinanceModel::updatePayment($out_trade_no, 'success', ['trade_no' => $trade_no, 'pay_time' => $gmt_payment])) {
          // TODO 更新支付状态失败
          Log::error('支付宝更新状态失败');
        }
      } else if ($trade_status == 'TRADE_CLOSED') {
        FinanceModel::updatePayment($out_trade_no, 'close');
      } else if ($trade_status == 'TRADE_FINISHED') {
        FinanceModel::updatePayment($out_trade_no, 'finish');
      }
      echo 'success';   //请不要修改或删除
    } else {
      abort(404, '页面不存在');
    }
  }

  /* 页面跳转同步通知页面路径 */
  public function alipay_return()
  {
    $paymentId = input('get.out_trade_no', '');
    if ($paymentId) {
      $addonOrder = FinanceModel::getOrder($paymentId);
      $appId = $addonOrder['app_id'];
      if (empty($appId)) {
        $this->redirect('home/app/addon');
      }
      // 存在绑定公众号
      $app = AppModel::getApp($appId);
      if ($app->isExists()) {
        $this->redirect('home/app/addon', ['id' => $app->appid]);
      }
    }
    abort(404, '页面不存在');
  }

  /* 微信支付返回 */
  public function wechat_notify()
  {
    $notify = new WxPayNotify();
    $notify->Handle(false);
    $input = file_get_contents('php://input');
    $postStr = (array) simplexml_load_string($input, 'SimpleXMLElement', LIBXML_NOCDATA);
    // 状态成功进行后续操作
    if (isset($postStr['result_code']) && $postStr['result_code'] == 'SUCCESS') {
      do {
        $out_trade_no = $postStr['out_trade_no'] ?? false;
        $transaction_id = $postStr['transaction_id'] ?? false;
        if (empty($transaction_id) || empty($out_trade_no)) {
          Log::error('微信支付订单号不存在');
          break;
        }
        // 缓存微信数据
        $wechat = Cache::get($out_trade_no, []);
        if (empty($wechat)) {
          Log::error('微信支付缓存数据不存在');
          break;
        }
        if (isset($wechat['total_fee']) && $wechat['total_fee'] != $postStr['total_fee']) {
          Log::error('微信支付价格数据错误');
          break;
        }
        $addonAppid = $wechat['product_id'] ?? '';
        if (empty($addonAppid)) {
          Log::error('微信支付购买应用不存在');
          break;
        }
        $addon = AddonModel::getBy('appid', $addonAppid);
        if (!$addon->isExists() || $addon->status != AddonModel::ADDON_STATUS_ONSALF) {
          Log::error('微信支付购买应用不存在或者下架了');
          break;
        }
        $userId = $wechat['user_id'] ?? '';
        if (empty($userId)) {
          Log::error('微信支付购买应用的用户不存在');
          break;
        }
        $appid = 0;
        $app = AppModel::getBy('appid', $wechat['appid']);
        if (!empty($app)) {
          $appid = $app->getId();
        }
        $paymentId = FinanceModel::insertPayment($userId, 0, bcmul($wechat['total_fee'], 0.01, 2), 0, $wechat['payMode'], [
          'app_id' => $appid,
          'addon_id' => $addon->getId(),
          'period' => $wechat['period'],
          'price' => bcmul($wechat['total_fee'], 0.01, 2),
          'developer_rate' => $wechat['developer_rate'],
          'is_wechat' => $wechat['is_wechat'],
        ], $out_trade_no);
        if (!FinanceModel::updatePayment($out_trade_no, 'success', ['trade_no' => $transaction_id, 'pay_time' => $postStr['time_end']])) {
          Log::error('微信支付更新状态失败');
          break;
        }
        Cache::set(sprintf('payresult%s', $out_trade_no), $paymentId, 3600);
      } while (0);
    } else {
      Log::error($postStr);
    }
  }
}
