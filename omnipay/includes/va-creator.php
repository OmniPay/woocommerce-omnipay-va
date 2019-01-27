<?php

namespace WooOmniPayID;

if (!function_exists('\WooOmniPayID\curl_post_json')) {
    function curl_post_json($url, $data)
    {
        $data_string = json_encode($data);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string))
        );

        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }

    function va_payment_channels($va_channels, $merchantid, $verifykey, $amount, $invoiceid, $bill_name, $bill_email, $bill_mobile, $bill_desc, $expiry_minute)
    {
        // receive virtual account numbers..
        $va_endpoint = 'https://secure.omnipay.co.id/OmniPay/api-v2/va/index.php';

        $results = array();

        $request = new \stdClass();
        $request->returnurl = get_site_url() . "?wc-api=omnipay-va-id&oid=$invoiceid";
        $request->merchantid = $merchantid;
        $request->orderid = $invoiceid;
        $request->amount = floor($amount);
        $request->bill_name = $bill_name;
        $request->bill_email = $bill_email;
        $request->bill_mobile = $bill_mobile;
        $request->bill_desc = $bill_desc;
        $request->expiry_minute = floor($expiry_minute);

        $request->vcode = md5($request->amount . $request->merchantid .
            $request->orderid . $verifykey);

        foreach ($va_channels as $provider) {
            $request->provider = $provider;
            $result = curl_post_json($va_endpoint, $request);
            $json = json_decode($result);
            if(!$json){
                // there is an error..
                $results[] = $result;
            } else {
                $results[] = $json;
            }
        }

        return $results;
    }

    function va_number_separator($va) {
        $str = '';
        $orig = $va;
        while($orig) {
            $str .= substr($orig, 0, 4) . ' ';
            $orig = substr($orig, 4);
        }
        return $str;
    }
}

