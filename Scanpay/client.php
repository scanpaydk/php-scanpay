<?php namespace Scanpay;

class ScanpayClient
{
    protected $_headers;
    protected $ch;

    public function __construct($apikey = '')
    {
        // Check if lib cURL is enabled
        if (!function_exists('curl_init')) {
            throw new Exception('Please install and enable php-curl.');
        }

        // Public cURL handle (we want to reuse connections)
        $this->ch = curl_init();

        curl_setopt_array($this->ch, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => 30, // Timeout after 30s
            CURLOPT_USE_SSL => CURLUSESSL_ALL,
        ));

        $this->_headers = array(
            'Authorization: Basic ' . base64_encode($apikey),
            'X-Scanpay-SDK: PHP-0.9.1',
            'Content-Type: application/json',
        );
    }


    protected function request($url, $data, $opts)
    {
        $headers = $this->_headers;

        if (isset($opts)) {
            if (isset($opts['headers'])) {
                $headers = array_merge($headers, $opts['headers']);
            }

            // Let the merchant redefine the API key.
            if (isset($opts['auth'])) {
                $headers[0] = 'Authorization: Basic ' . base64_encode($opts['auth']);
            }
        }

        if (isset($data)) {
            curl_setopt_array($this->ch, array(
                CURLOPT_URL => 'https://api.scanpay.dk' . $url,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($data),
            ));
        } else {
            curl_setopt_array($this->ch, array(
                CURLOPT_URL => 'https://api.scanpay.dk' . $url,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_POSTFIELDS => 0,
            ));
        }

        if (!$result = curl_exec($this->ch)) {
            if ($errno = curl_errno($this->ch)) {
                if (function_exists('curl_strerror')) { // PHP 5.5
                    throw new \Exception(curl_strerror($errno));
                }
                throw new \Exception('curl_errno: ' . $errno);
            }
        }

        $code = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        if ($code !== 200) {
            if ($code === 403) {
                throw new \Exception('Invalid API-key');
            }
            throw new \Exception('Unexpected http response code: ' . $code);
        }

        // Decode the json response (@: surpress warnings)
        if (!$resobj = @json_decode($result, true)) {
            throw new \Exception('Invalid response from server');
        }

        if (isset($resobj['error'])) {
            throw new \Exception('server returned error: ' . $resobj['error']);
        }
        return $resobj;
    }


    public function new($data, $opts = [])
    {
        $o = $this->request('/v1/new', $data, $opts);
        if (isset($o['url']) && strlen($o['url']) > 10) {
            return $o;
        }
        throw new \Exception('Invalid response from server');
    }


    public function seq($seq)
    {
        $o = $this->request('/v1/seq/' . $seq, null, null);
        if (isset($o['seq']) && is_int($o['seq']) && isset($o['changes']) && is_array($o['changes'])) {
            return $o;
        }
        throw new \Exception('Invalid response from server');
    }
}

?>