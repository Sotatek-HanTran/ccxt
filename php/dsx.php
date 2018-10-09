<?php

namespace ccxt;

// PLEASE DO NOT EDIT THIS FILE, IT IS GENERATED AND WILL BE OVERWRITTEN:
// https://github.com/ccxt/ccxt/blob/master/CONTRIBUTING.md#how-to-contribute-code

use Exception as Exception; // a common import

class dsx extends liqui {

    public function describe () {
        return array_replace_recursive (parent::describe (), array (
            'id' => 'dsx',
            'name' => 'DSX',
            'countries' => array ( 'UK' ),
            'rateLimit' => 1500,
            'has' => array (
                'CORS' => false,
                'fetchOrder' => true,
                'fetchOrders' => true,
                'fetchOpenOrders' => true,
                'fetchClosedOrders' => true,
                'fetchTickers' => true,
                'fetchMyTrades' => true,
                'fetchOrderBooks' => false,
                'fetchL2OrderBook' => false,
            ),
            'urls' => array (
                'logo' => 'https://user-images.githubusercontent.com/1294454/27990275-1413158a-645a-11e7-931c-94717f7510e3.jpg',
                'api' => array (
                    'public' => 'https://dsx.uk/mapi', // market data
                    'private' => 'https://dsx.uk/tapi', // trading
                    'dwapi' => 'https://dsx.uk/dwapi', // deposit/withdraw
                ),
                'www' => 'https://dsx.uk',
                'doc' => array (
                    'https://api.dsx.uk',
                    'https://dsx.uk/api_docs/public',
                    'https://dsx.uk/api_docs/private',
                    '',
                ),
            ),
            'api' => array (
                // market data (public)
                'public' => array (
                    'get' => array (
                        'barsFromMoment/{id}/{period}/{start}', // empty reply :\
                        'depth/{pair}',
                        'info',
                        'lastBars/{id}/{period}/{amount}', // period is (m, h or d)
                        'periodBars/{id}/{period}/{start}/{end}',
                        'ticker/{pair}',
                        'trades/{pair}',
                    ),
                ),
                // trading (private)
                'private' => array (
                    'post' => array (
                        'info/account',
                        'history/transactions',
                        'history/trades',
                        'history/orders',
                        'orders',
                        'Trade',
                        'order/cancel',
                        'order/status',
                        'order/new',
                    ),
                ),
                // deposit / withdraw (private)
                'dwapi' => array (
                    'post' => array (
                        'getCryptoDepositAddress',
                        'cryptoWithdraw',
                        'fiatWithdraw',
                        'getTransactionStatus',
                        'getTransactions',
                    ),
                ),
            ),
        ));
    }

    public function get_base_quote_from_market_id ($id) {
        $uppercase = strtoupper ($id);
        $base = mb_substr ($uppercase, 0, 3);
        $quote = mb_substr ($uppercase, 3, 6);
        $base = $this->common_currency_code($base);
        $quote = $this->common_currency_code($quote);
        return array ( $base, $quote );
    }

    public function fetch_balance ($params = array ()) {
        $this->load_markets();
        $response = $this->privatePostInfoAccount ();
        $balances = $response['return'];
        $result = array ( 'info' => $balances );
        $funds = $balances['funds'];
        $currencies = is_array ($funds) ? array_keys ($funds) : array ();
        for ($c = 0; $c < count ($currencies); $c++) {
            $currency = $currencies[$c];
            $uppercase = strtoupper ($currency);
            $uppercase = $this->common_currency_code($uppercase);
            $account = array (
                'free' => $funds[$currency]['available'],
                'used' => 0.0,
                'total' => $funds[$currency]['total'],
            );
            $account['used'] = $account['total'] - $account['free'];
            $result[$uppercase] = $account;
        }
        return $this->parse_balance($result);
    }

    public function parse_ticker ($ticker, $market = null) {
        $timestamp = $ticker['updated'] * 1000;
        $symbol = null;
        if ($market)
            $symbol = $market['symbol'];
        $average = $this->safe_float($ticker, 'avg');
        if ($average !== null)
            if ($average > 0)
                $average = 1 / $average;
        $last = $this->safe_float($ticker, 'last');
        return array (
            'symbol' => $symbol,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601 ($timestamp),
            'high' => $this->safe_float($ticker, 'high'),
            'low' => $this->safe_float($ticker, 'low'),
            'bid' => $this->safe_float($ticker, 'buy'),
            'bidVolume' => null,
            'ask' => $this->safe_float($ticker, 'sell'),
            'askVolume' => null,
            'vwap' => null,
            'open' => null,
            'close' => $last,
            'last' => $last,
            'previousClose' => null,
            'change' => null,
            'percentage' => null,
            'average' => $average,
            'baseVolume' => $this->safe_float($ticker, 'vol'),
            'quoteVolume' => $this->safe_float($ticker, 'vol_cur'),
            'info' => $ticker,
        );
    }

    public function sign_body_with_secret ($body) {
        return $this->decode ($this->hmac ($this->encode ($body), $this->encode ($this->secret), 'sha512', 'base64'));
    }

    public function sign ($path, $api = 'public', $method = 'GET', $params = array (), $headers = null, $body = null) {
        $url = $this->urls['api'][$api];
        $query = $this->omit ($params, $this->extract_params($path));
        if ($api === 'private') {
            $url .= '/v2/' . $this->implode_params($path, $params);
            $this->check_required_credentials();
            $nonce = $this->nonce ();
            $body = $this->urlencode (array_merge (array (
                'nonce' => $nonce,
                'method' => $path,
            ), $query));
            $signature = $this->sign_body_with_secret($body);
            $headers = array (
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Key' => $this->apiKey,
                'Sign' => $signature,
            );
        } else if ($api === 'public') {
            $url .= '/' . $this->implode_params($path, $params);
            if ($query) {
                $url .= '?' . $this->urlencode ($query);
            }
        } else {
            $url .= $this->implode_params($path, $params);
            if ($method === 'GET') {
                if ($query) {
                    $url .= '?' . $this->urlencode ($query);
                }
            } else {
                if ($query) {
                    $body = $this->json ($query);
                    $headers = array (
                        'Content-Type' => 'application/json',
                    );
                }
            }
        }
        return array ( 'url' => $url, 'method' => $method, 'body' => $body, 'headers' => $headers );
    }

    public function parse_trade ($trade, $market = null) {
        $timestamp = intval ($trade['timestamp']) * 1000;
        $side = $trade['type'];
        if ($side === 'ask')
            $side = 'sell';
        if ($side === 'bid')
            $side = 'buy';
        $price = $this->safe_float($trade, 'price');
        if (is_array ($trade) && array_key_exists ('rate', $trade)) {
            $price = $this->safe_float($trade, 'rate');
        }
        $id = $this->safe_string($trade, 'trade_id');
        $order = $this->safe_string($trade, 'orderId');
        if (is_array ($trade) && array_key_exists ('pair', $trade)) {
            $marketId = $trade['pair'];
            $market = $this->markets_by_id[$marketId];
        }
        $symbol = null;
        if ($market !== null) {
            $symbol = $market['symbol'];
        }
        $amount = $this->safe_float($trade, 'amount');
        if (is_array ($trade) && array_key_exists ('volume', $trade)) {
            $amount = $this->safe_float($trade, 'volume');
        }
        $type = 'limit'; // all trades are still limit trades
        $isYourOrder = $this->safe_value($trade, 'is_your_order');
        $takerOrMaker = 'taker';
        if ($isYourOrder !== null) {
            if ($isYourOrder) {
                $takerOrMaker = 'maker';
            }
        }
        $fee = $this->calculate_fee($symbol, $type, $side, $amount, $price, $takerOrMaker);
        return array (
            'id' => $id,
            'order' => $order,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601 ($timestamp),
            'symbol' => $symbol,
            'type' => $type,
            'side' => $side,
            'price' => $price,
            'amount' => $amount,
            'fee' => $fee,
            'info' => $trade,
        );
    }

    public function fetch_my_trades ($symbol = null, $since = null, $limit = null, $params = array ()) {
        $this->load_markets();
        $market = null;
        $request = array ();
        if ($symbol !== null) {
            $market = $this->market ($symbol);
            $request['pair'] = $market['id'];
        }
        if ($limit !== null) {
            $request['count'] = intval ($limit);
        }
        if ($since !== null) {
            $request['since'] = intval ($since / 1000);
        }
        $response = $this->privatePostHistoryTrades (array_merge ($request, $params));
        $trades = array ();
        if (is_array ($response) && array_key_exists ('return', $response)) {
            $trades = $response['return'];
        }
        // $trades = is_array ($trades || array ()).map (trade => $trades[trade].concat (array ('trade_id':trade))) ? array_keys ($trades || array ()).map (trade => $trades[trade].concat (array ('trade_id':trade))) : array ();
        return $this->parse_trades($trades, $market, $since, $limit);
    }

    public function fetch_order ($id, $symbol = null, $params = array ()) {
        $this->load_markets();
        $response = $this->privatePostOrderStatus (array_merge (array (
            'orderId' => intval ($id),
        ), $params));
        $id = (string) $id;
        $newOrder = $this->parse_order(array_merge (array ( 'id' => $id ), $response['return']));
        $oldOrder = (is_array ($this->orders) && array_key_exists ($id, $this->orders)) ? $this->orders[$id] : array ();
        $this->orders[$id] = array_merge ($oldOrder, $newOrder);
        return $this->orders[$id];
    }

    public function fetch_orders ($symbol = null, $since = null, $limit = null, $params = array ()) {
        if (is_array ($this->options) && array_key_exists ('fetchOrdersRequiresSymbol', $this->options))
            if ($this->options['fetchOrdersRequiresSymbol'])
                if ($symbol === null)
                    throw new ExchangeError ($this->id . ' fetchOrders requires a $symbol argument');
        $this->load_markets();
        $request = array ();
        $market = null;
        if ($symbol !== null) {
            $market = $this->market ($symbol);
            $request['pair'] = $market['id'];
        }
        $response = $this->privatePostOrders (array_merge ($request, $params));
        // liqui etc can only return 'open' orders (i.e. no way to fetch 'closed' orders)
        $openOrders = array ();
        if (is_array ($response) && array_key_exists ('return', $response))
            $openOrders = $this->parse_orders($response['return'], $market);
        $allOrders = $this->updateCachedOrders ($openOrders, $symbol);
        $result = $this->filter_by_symbol($allOrders, $symbol);
        return $this->filter_by_since_limit($result, $since, $limit);
    }

    public function create_order ($symbol, $type, $side, $amount, $price = null, $params = array ()) {
        if ($type === 'market') {
            throw new ExchangeError ($this->id . ' allows limit orders only');
        }
        $this->load_markets();
        $market = $this->market ($symbol);
        $request = array (
            'pair' => $market['id'],
            'type' => $side,
            'volume' => $this->amount_to_precision($symbol, $amount),
            'rate' => $this->price_to_precision($symbol, $price),
            'orderType' => $type,
        );
        $price = floatval ($price);
        $amount = floatval ($amount);
        $response = $this->privatePostOrderNew (array_merge ($request, $params));
        $id = null;
        $status = 'open';
        $filled = 0.0;
        $remaining = $amount;
        if (is_array ($response) && array_key_exists ('return', $response)) {
            $id = $this->safe_string($response['return'], 'orderId');
            if ($id === '0') {
                $id = $this->safe_string($response['return'], 'init_order_id');
                $status = 'closed';
            }
            $filled = $this->safe_float($response['return'], 'received', 0.0);
            $remaining = $this->safe_float($response['return'], 'remains', $amount);
        }
        $timestamp = $this->milliseconds ();
        $order = array (
            'id' => $id,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601 ($timestamp),
            'lastTradeTimestamp' => null,
            'status' => $status,
            'symbol' => $symbol,
            'type' => $type,
            'side' => $side,
            'price' => $price,
            'cost' => $price * $filled,
            'amount' => $amount,
            'remaining' => $remaining,
            'filled' => $filled,
            'fee' => null,
            // 'trades' => $this->parse_trades($order['trades'], $market),
        );
        $this->orders[$id] = $order;
        return array_merge (array ( 'info' => $response ), $order);
    }

    public function parse_order ($order, $market = null) {
        $id = (string) $order['id'];
        $status = $this->safe_string($order, 'status');
        if ($status !== 'null') {
            $status = $this->parse_order_status($status);
        }
        $timestamp = intval ($order['timestampCreated']) * 1000;
        $symbol = null;
        if ($market === null) {
            $market = $this->markets_by_id[$order['pair']];
        }
        if ($market !== null) {
            $symbol = $market['symbol'];
        }
        $remaining = null;
        $amount = null;
        $price = $this->safe_float($order, 'rate');
        $filled = null;
        $cost = null;
        $remaining = $this->safe_float($order, 'remainingVolume');
        $amount = $this->safe_float($order, 'volume');
        if ($amount !== null) {
            if ($remaining !== null) {
                $filled = $amount - $remaining;
                $cost = $price * $filled;
            }
        }
        $fee = null;
        $result = array (
            'info' => $order,
            'id' => $id,
            'symbol' => $symbol,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601 ($timestamp),
            'lastTradeTimestamp' => null,
            'type' => 'limit',
            'side' => $order['type'],
            'price' => $price,
            'cost' => $cost,
            'amount' => $amount,
            'remaining' => $remaining,
            'filled' => $filled,
            'status' => $status,
            'fee' => $fee,
        );
        return $result;
    }

    public function cancel_order ($id, $symbol = null, $params = array ()) {
        $this->load_markets();
        $request = array ();
        $request['orderId'] = $id;
        $response = $this->privatePostOrderCancel (array_merge ($request, $params));
        if (is_array ($this->orders) && array_key_exists ($id, $this->orders)) {
            $this->orders[$id]['status'] = 'canceled';
        }
        return $response;
    }
}
