<?php

define('TWITTER_API_TIMEOUT', 5 );  
define('TWITTER_API_USERAGENT', 'PHP/'.PHP_VERSION.'; http://github.com/timwhitlock/php-twitter-api' );  
define('TWITTER_API_BASE', 'https://api.twitter.com/1.1' );
define('TWITTER_OAUTH_REQUEST_TOKEN_URL', 'https://twitter.com/oauth/request_token');
define('TWITTER_OAUTH_AUTHORIZE_URL', 'https://twitter.com/oauth/authorize');
define('TWITTER_OAUTH_AUTHENTICATE_URL', 'https://twitter.com/oauth/authenticate');
define('TWITTER_OAUTH_ACCESS_TOKEN_URL', 'https://twitter.com/oauth/access_token'); 

class TwitterApiClient {
    
    private $Consumer;
   
    private $AccessToken;
    
  
    private $cache_ttl = null;
    
    
    private $cache_ns;     
    
   
    private $last_rate = array();    
    
    
    private $last_call;     
    
  
    public function __sleep(){
       return array('Consumer','AccessToken');
    }
    
   
    public function enable_cache( $ttl = 0, $namespace = 'twitter_api_' ){
       if( function_exists('apc_store') ){
          $this->cache_ttl = (int) $ttl;
          $this->cache_ns  = $namespace;
          return $this;
       }
       trigger_error( 'Cannot enable Twitter API cache without APC extension' );
       return $this->disable_cache();
    }
    
   
    public function disable_cache(){
       $this->cache_ttl = null;
       $this->cache_ns  = null;
       return $this;
    }
    
    public function has_auth(){
        return $this->AccessToken instanceof TwitterOAuthToken && $this->AccessToken->secret;
    }    
    
    
    public function deauthorize(){
        $this->AccessToken = null;
        return $this;
    }
    
    public function set_oauth( $consumer_key, $consumer_secret, $access_key = '', $access_secret = '' ){
        $this->deauthorize();
        $this->Consumer = new TwitterOAuthToken( $consumer_key, $consumer_secret );
        if( $access_key && $access_secret ){
            $this->AccessToken = new TwitterOAuthToken( $access_key, $access_secret );
        }
        return $this;
    }
    
    
    public function set_oauth_consumer( TwitterOAuthToken $token ){
        $this->Consumer = $token;
        return $this;
    }
    
    
    public function set_oauth_access( TwitterOAuthToken $token ){
        $this->AccessToken = $token;
        return $this;
    }
    
    
    public function get_oauth_request_token( $oauth_callback = 'oob' ){
        $params = $this->oauth_exchange( TWITTER_OAUTH_REQUEST_TOKEN_URL, compact('oauth_callback') );
        return new TwitterOAuthToken( $params['oauth_token'], $params['oauth_token_secret'] );
    }
   
    public function get_oauth_access_token( $oauth_verifier ){
        $params = $this->oauth_exchange( TWITTER_OAUTH_ACCESS_TOKEN_URL, compact('oauth_verifier') );
        $token = new TwitterOAuthToken( $params['oauth_token'], $params['oauth_token_secret'] );
        $token->user = array (
            'id' => $params['user_id'],
            'screen_name' => $params['screen_name'],
        );
        return $token;
    }    
    
    
    private function sanitize_args( array $_args ){
       
        $args = array();
        foreach( $_args as $key => $val ){
            if( is_string($val) ){
                $args[$key] = $val;
            }
            else if( true === $val ){
                $args[$key] = 'true';
            }
            else if( false === $val || null === $val ){
                 $args[$key] = 'false';
            }
            else if( ! is_scalar($val) ){
                throw new TwitterApiException( 'Invalid Twitter parameter ('.gettype($val).') '.$key, -1 );
            }
            else {
                $args[$key] = (string) $val;
            }
        }
        return $args;
    }    
    
    
  
    public function call( $path, array $args = array(), $http_method = 'GET' ){
        $args = $this->sanitize_args( $args );
        
        if( $http_method === 'GET' && isset($this->cache_ttl) ){
           $cachekey = $this->cache_ns.$path.'_'.md5( serialize($args) );
           if( preg_match('/^(\d+)-/', $this->AccessToken->key, $reg ) ){
              $cachekey .= '_'.$reg[1];
           }
           $data = apc_fetch( $cachekey );
           if( is_array($data) ){
               return $data;
           }
        }
        $http = $this->rest_request( $path, $args, $http_method );
        
        $status = $http['status'];
        $data = json_decode( $http['body'], true );
        
        if( ! is_array($data) ){
            $err = array( 
                'message' => $http['error'], 
                'code' => -1 
            );
            TwitterApiException::chuck( $err, $status );
        }
       
        if( isset( $data['errors'] ) ) {
            while( $err = array_shift($data['errors']) ){
                $err['message'] = $err['message'];
                if( $data['errors'] ){
                    $message = sprintf('Twitter error #%d', $err['code'] ).' "'.$err['message'].'"';
                    trigger_error( $message, E_USER_WARNING );
                }
                else {
                    TwitterApiException::chuck( $err, $status );
                }
            }
        }
        if( isset($cachekey) ){
           apc_store( $cachekey, $data, $this->cache_ttl );
        }
        return $data;
    }
 
    public function raw( $path, array $args = array(), $http_method = 'GET' ){
        $args = $this->sanitize_args( $args );
        return $this->rest_request( $path, $args, $http_method );
    }
    
    private function oauth_exchange( $endpoint, array $args ){
       
        $params = new TwitterOAuthParams( $args );
        $params->set_consumer( $this->Consumer );
        if( $this->AccessToken ){
            $params->set_token( $this->AccessToken );
        }
        $params->sign_hmac( 'POST', $endpoint );
        $conf = array (
            'method' => 'POST',
            'headers' => array( 'Authorization' => $params->oauth_header() ),
        );
        $http = self::http_request( $endpoint, $conf );
        $body = trim( $http['body'] );
        $stat = $http['status'];
        if( 200 !== $stat ){
          
            if( 0 === strpos($body, '<?') ){
                $xml = simplexml_load_string($body);
                $body = (string) $xml->error;
            }
            throw new TwitterApiException( $body, -1, $stat );
        }
        parse_str( $body, $params );
        if( ! is_array($params) || ! isset($params['oauth_token']) || ! isset($params['oauth_token_secret']) ){
            throw new TwitterApiException( 'Malformed response from Twitter', -1, $stat );
        }
        return $params;   
    }
    
    
   
    private function rest_request( $path, array $args, $http_method ){
       
        if( ! $this->has_auth() ){
            throw new TwitterApiException( 'Twitter client not authenticated', 0, 401 );
        }
        
        $conf = array (
            'method' => $http_method,
        );

        $endpoint = TWITTER_API_BASE.'/'.$path.'.json';
        $params = new TwitterOAuthParams( $args );
        $params->set_consumer( $this->Consumer );
        $params->set_token( $this->AccessToken );
        $params->sign_hmac( $http_method, $endpoint );
        if( 'GET' === $http_method ){
            $endpoint .= '?'.$params->serialize();
        }
        else {
            $conf['body'] = $params->serialize();
        }
        $http = self::http_request( $endpoint, $conf );        
    
        $this->last_call = $path;
        if( isset($http['headers']['x-rate-limit-limit']) ) {
            $this->last_rate[$path] = array (
                'limit'     => (int) $http['headers']['x-rate-limit-limit'],
                'remaining' => (int) $http['headers']['x-rate-limit-remaining'],
                'reset'     => (int) $http['headers']['x-rate-limit-reset'],
            );
        }
        return $http;
    }    
    
    public static function http_request( $endpoint, array $conf ){

        $conf += array(
            'body' => '',
            'method'  => 'GET',
            'headers' => array(),
        );

        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $endpoint );
        curl_setopt( $ch, CURLOPT_TIMEOUT, TWITTER_API_TIMEOUT );
        curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, TWITTER_API_TIMEOUT );
        curl_setopt( $ch, CURLOPT_USERAGENT, TWITTER_API_USERAGENT );
        curl_setopt( $ch, CURLOPT_HEADER, true );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        
        switch ( $conf['method'] ) {
        case 'GET':
            break;
        case 'POST':
            curl_setopt( $ch, CURLOPT_POST, true );
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $conf['body'] );
            break;
        default:
            throw new TwitterApiException('Unsupported method '.$conf['method'] );    
        }
        
        foreach( $conf['headers'] as $key => $val ){
            $headers[] = $key.': '.$val;
        }
        if( isset($headers) ) {
            curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
        }
        
       
        $response = curl_exec( $ch );
        if ( 60 === curl_errno($ch) ) { 
            curl_setopt( $ch, CURLOPT_CAINFO, __DIR__.'/ca-chain-bundle.crt');
            $response = curl_exec($ch);
        }
        $status = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $headers = array();
        $body = '';
        if( $response && $status ){
            list( $header, $body ) = preg_split('/\r\n\r\n/', $response, 2 ); 
            if( preg_match_all('/^(Content[\w\-]+|X-Rate[^:]+):\s*(.+)/mi', $header, $r, PREG_SET_ORDER ) ){
                foreach( $r as $match ){
                    $headers[ strtolower($match[1]) ] = $match[2];
                }        
            }
            curl_close($ch);
        }
        else {
            $error = curl_error( $ch ) or 
            $error = 'No response from Twitter';
            is_resource($ch) and curl_close($ch);
            throw new TwitterApiException( $error );
        }
        return array (
            'body'    => $body,
            'status'  => $status,
            'headers' => $headers,
        );
    }
    
    public function last_rate_limit_data( $func = '' ){
        $func or $func = $this->last_call;
        return isset($this->last_rate[$func]) ? $this->last_rate[$func] : array( 'limit' => 0 );
    }
    
    public function last_rate_limit_allowance( $func = '' ){
        $data = $this->last_rate_limit_data($func);
        return isset($data['limit']) ? $data['limit'] : null;
    }
       
    public function last_rate_limit_remaining( $func = '' ){
        $data = $this->last_rate_limit_data($func);
        return isset($data['remaining']) ? $data['remaining'] : null;
    }
    
    public function last_rate_limit_reset( $func = '' ){
        $data = $this->last_rate_limit_data($func);
        return isset($data['reset']) ? $data['reset'] : null;
    }
}

class TwitterOAuthToken {
    public $key;
    public $secret;
    public $verifier;
    public $user;
    public function __construct( $key, $secret = '' ){
        if( ! $key ){
           throw new Exception( 'Invalid OAuth token - Key required even if secret is empty' );
        }
        $this->key = $key;
        $this->secret = $secret;
        $this->verifier = '';
    }
    public function get_authorization_url(){
        return TWITTER_OAUTH_AUTHORIZE_URL.'?oauth_token='.rawurlencode($this->key);
    }
    public function get_authentication_url(){
        return TWITTER_OAUTH_AUTHENTICATE_URL.'?oauth_token='.rawurlencode($this->key);
    }
}

class TwitterOAuthParams {
    
    private $args;
    private $consumer_secret;
    private $token_secret;
    
    private static function urlencode( $val ){
        return str_replace( '%7E', '~', rawurlencode($val) );
    }    
    
    public function __construct( array $args = array() ){
        $this->args = $args + array ( 
            'oauth_version' => '1.0',
        );
    }
    
    public function set_consumer( TwitterOAuthToken $Consumer ){
        $this->consumer_secret = $Consumer->secret;
        $this->args['oauth_consumer_key'] = $Consumer->key;
    }   
    
    public function set_token( TwitterOAuthToken $Token ){
        $this->token_secret = $Token->secret;
        $this->args['oauth_token'] = $Token->key;
    }   
    
    private function normalize(){
        $flags = SORT_STRING | SORT_ASC;
        ksort( $this->args, $flags );
        foreach( $this->args as $k => $a ){
            if( is_array($a) ){
                sort( $this->args[$k], $flags );
            }
        }
        return $this->args;
    }
    
    public function serialize(){
        $str = http_build_query( $this->args );
        // PHP_QUERY_RFC3986 requires PHP >= 5.4
        $str = str_replace( array('+','%7E'), array('%20','~'), $str );
        return $str;
    }
    public function sign_hmac( $http_method, $http_rsc ){
        $this->args['oauth_signature_method'] = 'HMAC-SHA1';
        $this->args['oauth_timestamp'] = sprintf('%u', time() );
        $this->args['oauth_nonce'] = sprintf('%f', microtime(true) );
        unset( $this->args['oauth_signature'] );
        $this->normalize();
        $str = $this->serialize();
        $str = strtoupper($http_method).'&'.self::urlencode($http_rsc).'&'.self::urlencode($str);
        $key = self::urlencode($this->consumer_secret).'&'.self::urlencode($this->token_secret);
        $this->args['oauth_signature'] = base64_encode( hash_hmac( 'sha1', $str, $key, true ) );
        return $this->args;
    }
    public function oauth_header(){
        $lines = array();
        foreach( $this->args as $key => $val ){
            $lines[] = self::urlencode($key).'="'.self::urlencode($val).'"';
        }
        return 'OAuth '.implode( ",\n ", $lines );
    }
}

function _twitter_api_http_status_text( $s ){
    static $codes = array (
        100 => 'Continue',
        101 => 'Switching Protocols',
        
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        
        400 => 'Bad Request',
        401 => 'Authorization Required',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Large',
        415 => 'Unsupported Media Type',
        416 => 'Requested range not satisfiable',
        417 => 'Expectation Failed',
        //  ..
        429 => 'Twitter API rate limit exceeded',
        
        500 => 'Twitter server error',
        501 => 'Not Implemented',
        502 => 'Twitter is not responding',
        503 => 'Twitter is too busy to respond',
        504 => 'Gateway Time-out',
        505 => 'HTTP Version not supported',
    );
    return  isset($codes[$s]) ? $codes[$s] : sprintf('Status %u from Twitter', $s );
}

class TwitterApiException extends Exception {

    protected $status = 0;        
        
    public static function chuck( array $err, $status ){
        $code = isset($err['code']) ? (int) $err['code'] : -1;
        $mess = isset($err['message']) ? trim($err['message']) : '';
        static $classes = array (
            404 => 'TwitterApiNotFoundException',
            429 => 'TwitterApiRateLimitException',
        );
        $eclass = isset($classes[$status]) ? $classes[$status] : __CLASS__;
        throw new $eclass( $mess, $code, $status );
    }
               
    public function __construct( $message, $code = 0 ){
        if( 2 < func_num_args() ){
            $this->status = (int) func_get_arg(2);
        }
        if( ! $message ){
            $message = _twitter_api_http_status_text($this->status);
        }
        parent::__construct( $message, $code );
    }
    
    public function getStatus(){
        return $this->status;
    }
    
}

/** 404 */
class TwitterApiNotFoundException extends TwitterApiException {
    
}

/** 429 */
class TwitterApiRateLimitException extends TwitterApiException {
    
}
