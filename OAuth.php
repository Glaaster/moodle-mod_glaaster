<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file contains the OAuth 1.0a implementation used for support for LTI 1.1.
 *
 * @package    mod_glaaster
 * @copyright moodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Using a namespace as the basicLTI module imports classes with the same names.
namespace moodle\mod\glaaster;

use Exception;

defined('MOODLE_INTERNAL') || die;

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
// Multiple OAuth classes are intentionally defined in this single file (legacy pattern from mod/lti core).

/**
 * Generic exception class
 */
class GlaasterOAuthException extends Exception {
    // Pass.
}

/**
 * OAuth 1.0 Consumer class
 */
class GlaasterOAuthConsumer {
    /**
     * The consumer key.
     *
     * @var string The consumer key.
     */
    public $key;

    /**
     * This is the secret used to sign requests and should be kept confidential.
     *
     * @var string The consumer secret.
     */
    public $secret;

    /** @var string|null callback URL. */
    public ?string $callbackurl;

    /**
     * Constructor for the OAuth consumer.
     *
     * @param string $key The consumer key.
     * @param string $secret The consumer secret.
     * @param string|null $callbackurl The callback URL for the consumer.
     */
    public function __construct($key, $secret, $callbackurl = null) {
        $this->key = $key;
        $this->secret = $secret;
        $this->callbackurl = $callbackurl;
    }

    /**
     * Returns a string representation of the OAuth consumer.
     *
     * @return string A string representation of the OAuth consumer.
     */
    public function __toString() {
        return "GlaasterOAuthConsumer[key=$this->key,secret=$this->secret]";
    }
}

/**
 * OAuth 1.0 Token class
 *
 * This class represents an OAuth token, which can be either a request token or an access token.
 */
class GlaasterOAuthToken {
    /**
     * The token key.
     *
     * @var string The token key.
     */
    public $key;
    /**
     * The token secret.
     *
     * @var string The token secret.
     */
    public $secret;

    /**
     * Constructs a new OAuth token.
     *
     * @param string $key The token key.
     * @param string $secret The token secret.
     */
    public function __construct($key, $secret) {
        $this->key = $key;
        $this->secret = $secret;
    }

    /**
     * Returns a string representation of the OAuth token.
     *
     * @return string A string representation of the OAuth token.
     */
    public function __toString() {
        return $this->to_string();
    }

    /**
     * generates the basic string serialization of a token that a server
     * would respond to request_token and access_token calls with
     */
    public function to_string(): string {
        return "oauth_token=" .
            GlaasterOAuthUtil::urlencode_rfc3986($this->key) .
            "&oauth_token_secret=" .
            GlaasterOAuthUtil::urlencode_rfc3986($this->secret);
    }
}

/**
 * Base class for all OAuth signature methods.
 *
 * This class provides the basic structure for signature methods used in OAuth 1.0a.
 */
class GlaasterOAuthSignatureMethod {
    /**
     * Verifies the OAuth signature for the given request.
     *
     * @param GlaasterOAuthRequest $request The OAuth request.
     * @param GlaasterOAuthConsumer $consumer The OAuth consumer.
     * @param GlaasterOAuthToken|null $token The OAuth token.
     * @param string $signature The signature to verify.
     * @return bool True if the signature is valid.
     */
    public function check_signature(&$request, $consumer, $token, $signature) {
        $built = $this->build_signature($request, $consumer, $token);
        return $built == $signature;
    }
}

/**
 * Base class for the HMac based signature methods.
 */
abstract class GlaasterOAuthSignatureMethod_HMAC extends GlaasterOAuthSignatureMethod {
    /**
     * Builds the signature for the request using HMAC.
     *
     * @param GlaasterOAuthRequest $request The OAuth request.
     * @param GlaasterOAuthConsumer $consumer The OAuth consumer.
     * @param GlaasterOAuthToken|null $token The OAuth token, or null if not applicable.
     * @return string The computed signature.
     */
    public function build_signature($request, $consumer, $token) {
        global $computedsig;
        $computedsig = false;

        $basestring = $request->get_signature_basestring();
        $request->basestring = $basestring;

        $keyparts = [
            $consumer->secret,
            ($token) ? $token->secret : "",
        ];

        $keyparts = GlaasterOAuthUtil::urlencode_rfc3986($keyparts);
        $key = implode('&', $keyparts);

        $computedsignature =
            base64_encode(hash_hmac(strtolower(substr($this->get_name(), 5)), $basestring, $key, true));
        $computedsig = $computedsignature;
        return $computedsignature;
    }

    /**
     * Name of the Algorithm used.
     *
     * @return string algorithm name.
     */
    abstract public function get_name(): string;
}

/**
 * Implementation for SHA 1.
 */
class GlaasterOAuthSignatureMethod_HMAC_SHA1 extends GlaasterOAuthSignatureMethod_HMAC {
    /**
     * Name of the Algorithm used.
     *
     * @return string algorithm name.
     */
    public function get_name(): string {
        return "HMAC-SHA1";
    }
}

/**
 * OAuth Request class
 *
 * This class represents an OAuth request, including its parameters, HTTP method, and URL.
 * It provides methods for building the signature base string, signing the request,
 * and converting the request to a URL or header format.
 */
class GlaasterOAuthRequest {
    /**
     * The version of the OAuth protocol used.
     * Defaults to '1.0'.
     *
     * @var string $version
     */
    public static $version = '1.0';

    /**
     * The input stream for POST data.
     * Defaults to 'php://input'.
     *
     * @var string $postinput
     */
    public static $postinput = 'php://input';

    /**
     * The base string for the request.
     * This is used to generate the signature.
     *
     * @var string $basestring
     */
    public $basestring;

    /**
     * The parameters of the request.
     * For debugging purposes, this should be an associative array
     *
     * @var array $parameters
     */
    private $parameters;

    /**
     * The HTTP method used for the request (e.g., GET, POST).
     *
     * @var string $httpmethod
     */
    private $httpmethod;

    /**
     * The HTTP URL for the request.
     *
     * This should be a fully qualified URL including scheme, host, and path.
     *
     * @var string $httpurl
     */
    private $httpurl;

    /**
     * Constructor for the OAuth request.
     *
     * @param string $httpmethod The HTTP method (GET, POST, etc.).
     * @param string $httpurl The HTTP URL for the request.
     * @param array|null $parameters Optional parameters for the request.
     */
    public function __construct($httpmethod, $httpurl, $parameters = null) {
        @$parameters || $parameters = [];
        $this->parameters = $parameters;
        $this->httpmethod = $httpmethod;
        $this->httpurl = $httpurl;
    }

    /**
     * Attempt to build up a request from what was passed to the server.
     *
     * @param string|null $httpmethod The HTTP method (GET, POST, etc.). Defaults to server request method.
     * @param string|null $httpurl The HTTP URL. Defaults to current request URL.
     * @param array|null $parameters Optional parameters. Defaults to request parameters.
     * @return GlaasterOAuthRequest The constructed OAuth request.
     */
    public static function from_request($httpmethod = null, $httpurl = null, $parameters = null) {
        $scheme = (!is_https()) ? 'http' : 'https';
        $port = "";
        if (
            $_SERVER['SERVER_PORT'] != "80" && $_SERVER['SERVER_PORT'] != "443" &&
            strpos(':', $_SERVER['HTTP_HOST']) < 0
        ) {
            $port = ':' . $_SERVER['SERVER_PORT'];
        }
        @$httpurl || $httpurl = $scheme .
            '://' . $_SERVER['HTTP_HOST'] .
            $port .
            $_SERVER['REQUEST_URI'];
        @$httpmethod || $httpmethod = $_SERVER['REQUEST_METHOD'];

        // We weren't handed any parameters, so let's find the ones relevant to
        // this request.
        // If you run XML-RPC or similar, you should use this to provide your own
        // parsed parameter-list.
        if (!$parameters) {
            // Find request headers.
            $requestheaders = GlaasterOAuthUtil::get_headers();

            // Parse the query-string to find GET parameters.
            $parameters = GlaasterOAuthUtil::parse_parameters($_SERVER['QUERY_STRING']);

            $ourpost = $_POST;
            // Add POST Parameters if they exist.
            $parameters = array_merge($parameters, $ourpost);

            // We have an Authorization-header with OAuth data. Parse the header
            // and add those overriding any duplicates from GET or POST.
            if (@substr($requestheaders['Authorization'], 0, 6) == "OAuth ") {
                $headerparameters = GlaasterOAuthUtil::split_header($requestheaders['Authorization']);
                $parameters = array_merge($parameters, $headerparameters);
            }
        }

        return new GlaasterOAuthRequest($httpmethod, $httpurl, $parameters);
    }

    /**
     * Creates a new OAuth request from a consumer and token.
     *
     * This method initializes the request with default parameters such as
     * OAuth version, nonce, timestamp, consumer key, and token key if provided.
     *
     * @param GlaasterOAuthConsumer $consumer The OAuth consumer.
     * @param GlaasterOAuthToken|null $token The OAuth token, or null if not applicable.
     * @param string $httpmethod The HTTP method (GET, POST, etc.).
     * @param string $httpurl The HTTP URL for the request.
     * @param array|null $parameters Optional additional parameters for the request.
     * @return GlaasterOAuthRequest The constructed OAuth request.
     */
    public static function from_consumer_and_token($consumer, $token, $httpmethod, $httpurl, $parameters = null) {
        @$parameters || $parameters = [];
        $defaults = [
            "oauth_version" => self::$version,
            "oauth_nonce" => self::generate_nonce(),
            "oauth_timestamp" => self::generate_timestamp(),
            "oauth_consumer_key" => $consumer->key,
        ];
        if ($token) {
            $defaults['oauth_token'] = $token->key;
        }

        $parameters = array_merge($defaults, $parameters);

        // Parse the query-string to find and add GET parameters.
        $parts = parse_url($httpurl);
        if (isset($parts['query'])) {
            $qparms = GlaasterOAuthUtil::parse_parameters($parts['query']);
            $parameters = array_merge($qparms, $parameters);
        }

        return new GlaasterOAuthRequest($httpmethod, $httpurl, $parameters);
    }

    /**
     * util function: current nonce
     */
    private static function generate_nonce() {
        $mt = microtime();
        $rand = mt_rand();

        return md5($mt . $rand); // MD5s look nicer than numbers.
    }

    /**
     * util function: current timestamp
     */
    private static function generate_timestamp() {
        return time();
    }

    /**
     * Returns the value of a parameter by name.
     *
     * @param string $name The name of the parameter to retrieve.
     * @return mixed|null The value of the parameter, or null if not set.
     */
    public function get_parameter($name) {
        return isset($this->parameters[$name]) ? $this->parameters[$name] : null;
    }

    /**
     * Returns all parameters of this request.
     *
     * @return array The parameters of the request.
     */
    public function get_parameters() {
        return $this->parameters;
    }

    /**
     * Unsets a parameter by name.
     *
     * This method removes the specified parameter from the request's parameters.
     *
     * @param string $name The name of the parameter to unset.
     */
    public function unset_parameter($name) {
        unset($this->parameters[$name]);
    }

    /**
     * Returns the base string of this request
     *
     * The base string defined as the method, the url
     * and the parameters (normalized), each urlencoded
     * and the concated with &.
     */
    public function get_signature_basestring() {
        $parts = [
            $this->get_normalized_http_method(),
            $this->get_normalized_http_url(),
            $this->get_signable_parameters(),
        ];

        $parts = GlaasterOAuthUtil::urlencode_rfc3986($parts);

        return implode('&', $parts);
    }

    /**
     * Returns the normalized HTTP method of this request.
     */
    public function get_normalized_http_method() {
        return strtoupper($this->httpmethod);
    }

    /**
     * Parses {@see httpurl} and returns normalized scheme://host/path if non-empty, otherwise return empty string
     *
     * @return string
     */
    public function get_normalized_http_url() {
        if ($this->httpurl === '') {
            return '';
        }

        $parts = parse_url($this->httpurl);

        $port = @$parts['port'];
        $scheme = $parts['scheme'];
        $host = $parts['host'];
        $path = @$parts['path'];

        $port || $port = ($scheme == 'https') ? '443' : '80';

        if (($scheme == 'https' && $port != '443') || ($scheme == 'http' && $port != '80')) {
            $host = "$host:$port";
        }
        return "$scheme://$host$path";
    }

    /**
     * The request parameters, sorted and concatenated into a normalized string.
     *
     * @return string
     */
    public function get_signable_parameters() {
        // Grab all parameters.
        $params = $this->parameters;

        // Remove oauth_signature if present
        // Ref: Spec: 9.1.1 ("The oauth_signature parameter MUST be excluded.").
        if (isset($params['oauth_signature'])) {
            unset($params['oauth_signature']);
        }

        return GlaasterOAuthUtil::build_http_query($params);
    }

    /**
     * builds the Authorization: header
     */
    public function to_header() {
        $out = 'Authorization: OAuth realm=""';
        foreach ($this->parameters as $k => $v) {
            if (!str_starts_with($k, "oauth")) {
                continue;
            }
            if (is_array($v)) {
                throw new GlaasterOAuthException('Arrays not supported in headers');
            }
            $out .= ',' .
                GlaasterOAuthUtil::urlencode_rfc3986($k) .
                '="' .
                GlaasterOAuthUtil::urlencode_rfc3986($v) .
                '"';
        }
        return $out;
    }

    /**
     * Returns a string representation of the OAuth request.
     *
     * This method returns the URL that would be used for a GET request,
     * including any parameters.
     *
     * @return string The URL representation of the OAuth request.
     */
    public function __toString() {
        return $this->to_url();
    }

    /**
     * builds a url usable for a GET request
     */
    public function to_url() {
        $postdata = $this->to_postdata();
        $out = $this->get_normalized_http_url();
        if ($postdata) {
            $out .= '?' . $postdata;
        }
        return $out;
    }

    /**
     * Builds the data for a POST request.
     */
    public function to_postdata() {
        return GlaasterOAuthUtil::build_http_query($this->parameters);
    }

    /**
     * Signs the request with the given signature method, consumer, and token.
     *
     * This method sets the "oauth_signature_method" parameter and computes the
     * "oauth_signature" parameter using the provided signature method.
     *
     * @param GlaasterOAuthSignatureMethod $signaturemethod The signature method to use.
     * @param GlaasterOAuthConsumer $consumer The OAuth consumer.
     * @param GlaasterOAuthToken|null $token The OAuth token, or null if not applicable.
     */
    public function sign_request($signaturemethod, $consumer, $token) {
        $this->set_parameter("oauth_signature_method", $signaturemethod->get_name());
        $signature = $this->build_signature($signaturemethod, $consumer, $token);
        $this->set_parameter("oauth_signature", $signature);
    }

    /**
     * Sets a parameter for the request.
     *
     * This method allows setting a parameter by name and value. If the parameter
     * already exists and duplicates are allowed, it will append the value to an
     * array of values for that parameter.
     *
     * @param string $name The name of the parameter.
     * @param mixed $value The value of the parameter.
     */
    public function set_parameter($name, $value) {
        $this->parameters[$name] = $value;
    }

    /**
     * Builds the signature for the request using the provided signature method, consumer, and token.
     *
     * This method calls the build_signature method of the signature method to compute the signature.
     *
     * @param GlaasterOAuthSignatureMethod $signaturemethod The signature method to use.
     * @param GlaasterOAuthConsumer $consumer The OAuth consumer.
     * @param GlaasterOAuthToken|null $token The OAuth token, or null if not applicable.
     * @return string The computed signature.
     */
    public function build_signature($signaturemethod, $consumer, $token) {
        $signature = $signaturemethod->build_signature($this, $consumer, $token);
        return $signature;
    }
}

/**
 * OAuth Server class
 *
 * This class implements the OAuth 1.0a server functionality, including request token and access token handling.
 */
class GlaasterOAuthServer {
    /**
     * The threshold for timestamp validity in seconds.
     * Defaults to 300 seconds (5 minutes).
     *
     * @var int $timestampthreshold
     */
    protected $timestampthreshold = 300;
    /**
     * The version of the OAuth protocol supported by this server.
     * Defaults to 1.0.
     *
     * @var float $version
     */
    protected $version = 1.0;

    /**
     * The signature methods supported by this server.
     * This is an associative array where the key is the name of the signature method.
     *
     * @var array $signaturemethods
     */
    protected $signaturemethods = [];

    /**
     * The datastore used for storing and retrieving OAuth data.
     * This should implement methods like new_request_token and lookup_consumer.
     *
     * @var mixed $datastore
     */
    protected $datastore;

    /**
     * Constructor for the OAuth server.
     *
     * This method initializes the server with a datastore and sets up the default signature methods.
     *
     * @param mixed $datastoreparam The datastore used for storing and retrieving OAuth data.
     */
    public function __construct($datastoreparam) {
        $this->datastore = $datastoreparam;
    }

    /**
     * Adds a signature method to the server.
     *
     * This method allows adding a new signature method to the server's list of supported methods.
     * The signature method should implement the GlaasterOAuthSignatureMethod interface.
     *
     * @param GlaasterOAuthSignatureMethod $signaturemethod The signature method to add.
     */
    public function add_signature_method($signaturemethod) {
        $this->signaturemethods[$signaturemethod->get_name()] = $signaturemethod;
    }

    /**
     * Process a request_token request and return the request token on success.
     *
     * @param GlaasterOAuthRequest $request The OAuth request.
     * @return GlaasterOAuthToken The new request token.
     */
    public function fetch_request_token(&$request) {
        $this->get_version($request);

        $consumer = $this->get_consumer($request);

        // No token is provided, so we set it to null.
        $token = null;

        $this->check_signature($request, $consumer, $token);

        $newtoken = $this->datastore->new_request_token($consumer);

        return $newtoken;
    }

    /**
     * Get the OAuth version from the request.
     *
     * This method retrieves the OAuth version from the request parameters.
     * If not specified, it defaults to 1.0.
     *
     * @param GlaasterOAuthRequest $request The OAuth request.
     * @return float The OAuth version.
     * @throws GlaasterOAuthException If the version is not supported.
     */
    private function get_version(&$request) {
        $version = $request->get_parameter("oauth_version");
        if (!$version) {
            $version = 1.0;
        }
        if ($version && $version != $this->version) {
            throw new GlaasterOAuthException("OAuth version '$version' not supported");
        }
        return $version;
    }

    /**
     * Get the consumer from the request.
     *
     * This method retrieves the OAuth consumer key from the request parameters
     * and looks it up in the datastore. If the consumer key is not provided or
     * invalid, an exception is thrown.
     *
     * @param GlaasterOAuthRequest $request The OAuth request.
     * @return GlaasterOAuthConsumer The OAuth consumer.
     * @throws GlaasterOAuthException If the consumer key is invalid or not found.
     */
    private function get_consumer(&$request) {
        $consumerkey = @$request->get_parameter("oauth_consumer_key");
        if (!$consumerkey) {
            throw new GlaasterOAuthException("Invalid consumer key");
        }

        $consumer = $this->datastore->lookup_consumer($consumerkey);
        if (!$consumer) {
            throw new GlaasterOAuthException("Invalid consumer");
        }

        return $consumer;
    }

    /**
     * Check the signature of the request.
     *
     * This method verifies the signature of the request using the provided consumer and token.
     * It checks the timestamp and nonce to ensure they are valid and not reused.
     *
     * @param GlaasterOAuthRequest $request The OAuth request.
     * @param GlaasterOAuthConsumer $consumer The OAuth consumer.
     * @param GlaasterOAuthToken|null $token The OAuth token, or null if not applicable.
     * @throws GlaasterOAuthException If the signature is invalid or if timestamp/nonce checks fail.
     */
    private function check_signature(&$request, $consumer, $token) {
        // This should probably be in a different method.
        global $computedsig;
        $computedsig = false;

        $timestamp = @$request->get_parameter('oauth_timestamp');
        $nonce = @$request->get_parameter('oauth_nonce');

        $this->check_timestamp($timestamp);
        $this->check_nonce($consumer, $token, $nonce, $timestamp);

        $signaturemethod = $this->get_signature_method($request);

        $signature = $request->get_parameter('oauth_signature');
        $validsig = $signaturemethod->check_signature($request, $consumer, $token, $signature);

        if (!$validsig) {
            $extext = "Invalid signature";
            if ($computedsig) {
                $extext = $extext . " ours= $computedsig yours=$signature";
            }
            throw new GlaasterOAuthException($extext);
        }
    }

    /**
     * Check that the timestamp is recentish.
     *
     * This method verifies that the provided timestamp is within a reasonable range
     * (default: 5 minutes) from the current time. If the timestamp is too old, an exception is thrown.
     *
     * @param int $timestamp The timestamp to check.
     * @throws GlaasterOAuthException If the timestamp is expired.
     */
    private function check_timestamp($timestamp) {
        // Verify that timestamp is recentish.
        $now = time();
        if ($now - $timestamp > $this->timestampthreshold) {
            throw new GlaasterOAuthException("Expired timestamp, yours $timestamp, ours $now");
        }
    }

    /**
     * Check that the nonce is uniqueish.
     *
     * This method checks if the provided nonce has already been used for the given consumer and token.
     * If the nonce is found, an exception is thrown to prevent replay attacks.
     *
     * @param GlaasterOAuthConsumer $consumer The OAuth consumer.
     * @param GlaasterOAuthToken|null $token The OAuth token, or null if not applicable.
     * @param string $nonce The nonce to check.
     * @param int $timestamp The timestamp of the request.
     * @throws GlaasterOAuthException If the nonce has already been used.
     */
    private function check_nonce($consumer, $token, $nonce, $timestamp) {
        // Verify that the nonce is uniqueish.
        $found = $this->datastore->lookup_nonce($consumer, $token, $nonce, $timestamp);
        if ($found) {
            throw new GlaasterOAuthException("Nonce already used: $nonce");
        }
    }

    /**
     * Get the signature method from the request.
     *
     * This method retrieves the OAuth signature method from the request parameters.
     * If not specified, it defaults to "PLAINTEXT". If the method is not supported,
     * an exception is thrown.
     *
     * @param GlaasterOAuthRequest $request The OAuth request.
     * @return GlaasterOAuthSignatureMethod The signature method to use.
     * @throws GlaasterOAuthException If the signature method is not supported.
     */
    private function get_signature_method(&$request) {
        $signaturemethod = @$request->get_parameter("oauth_signature_method");
        if (!$signaturemethod) {
            $signaturemethod = "PLAINTEXT";
        }
        if (!in_array($signaturemethod, array_keys($this->signaturemethods))) {
            throw new GlaasterOAuthException("Signature method '$signaturemethod' not supported " .
                "try one of the following: " .
                implode(", ", array_keys($this->signaturemethods)));
        }
        return $this->signaturemethods[$signaturemethod];
    }

    /**
     * Process an access_token request.
     *
     * This method retrieves the access token for a request token after it has been authorized.
     * It requires a valid request token and consumer, checks the signature, and returns a new access token.
     *
     * @param GlaasterOAuthRequest $request The OAuth request.
     * @return GlaasterOAuthToken The new access token.
     * @throws GlaasterOAuthException If the request is invalid or the signature check fails.
     */
    public function fetch_access_token(&$request) {
        $this->get_version($request);

        $consumer = $this->get_consumer($request);

        // Requires authorized request token.
        $token = $this->get_token($request, $consumer, "request");

        $this->check_signature($request, $consumer, $token);

        $newtoken = $this->datastore->new_access_token($token, $consumer);

        return $newtoken;
    }

    /**
     * Get the token from the request.
     *
     * This method retrieves the OAuth token from the request parameters.
     * If the token is not provided or invalid, an exception is thrown.
     *
     * @param GlaasterOAuthRequest $request The OAuth request.
     * @param GlaasterOAuthConsumer $consumer The OAuth consumer.
     * @param string $tokentype The type of token to retrieve (default: "access").
     * @return GlaasterOAuthToken The OAuth token.
     * @throws GlaasterOAuthException If the token is invalid or not found.
     */
    private function get_token(&$request, $consumer, $tokentype = "access") {
        $tokenfield = @$request->get_parameter('oauth_token');
        if (!$tokenfield) {
            return false;
        }
        $token = $this->datastore->lookup_token($consumer, $tokentype, $tokenfield);
        if (!$token) {
            throw new GlaasterOAuthException("Invalid $tokentype token: $tokenfield");
        }
        return $token;
    }

    /**
     * Verify the request and return the consumer and token.
     *
     * @param GlaasterOAuthRequest $request The OAuth request to verify.
     * @return array Array containing the consumer and token.
     */
    public function verify_request(&$request) {
        global $computedsig;
        $computedsig = false;
        $this->get_version($request);
        $consumer = $this->get_consumer($request);
        $token = $this->get_token($request, $consumer, "access");
        $this->check_signature($request, $consumer, $token);
        return [
            $consumer,
            $token,
        ];
    }
}

/**
 * OAuth Data Store class
 *
 * This class provides an interface for storing and retrieving OAuth data such as consumers, tokens, and nonces.
 * It should be implemented to interact with a specific data storage solution (e.g., database).
 */
class GlaasterOAuthDataStore {
}

/**
 * Utility class for OAuth operations.
 *
 * This class provides utility functions for handling OAuth headers, parameters,
 * and encoding/decoding operations.
 */
class GlaasterOAuthUtil {
    /**
     * Splits an OAuth Authorization header into its component parameters.
     *
     * @param string $header The Authorization header value.
     * @return array Associative array of header parameters.
     */
    public static function split_header($header) {
        $oauthonly = true;
        $pattern = '/(([-_a-z]*)=("([^"]*)"|([^,]*)),?)/';
        $offset = 0;
        $params = [];
        while (preg_match($pattern, $header, $matches, PREG_OFFSET_CAPTURE, $offset) > 0) {
            $match = $matches[0];
            $headername = $matches[2][0];
            $headercontent = (isset($matches[5])) ? $matches[5][0] : $matches[4][0];
            if (preg_match('/^oauth_/', $headername) || !$oauthonly) {
                $params[$headername] = self::urldecode_rfc3986($headercontent);
            }
            $offset = $match[1] + strlen($match[0]);
        }

        if (isset($params['realm'])) {
            unset($params['realm']);
        }

        return $params;
    }

    // This decodes function isn't taking into consideration the above
    // modifications to the encoding process. However, this method doesn't
    // seem to be used anywhere so leaving it as it is.
    /**
     * Decodes a URL-encoded string according to RFC 3986.
     *
     * This method decodes the input string using the standard PHP urldecode function.
     * It does not apply any additional transformations.
     *
     * @param string $string The URL-encoded string to decode.
     * @return string The decoded string.
     */
    public static function urldecode_rfc3986($string) {
        return urldecode($string);
    }

    // Utility function for turning the Authorization: header into
    // parameters, has to do some non-escaping
    // Can filter out any non-oauth parameters if needed (default behavior).
    /**
     * Retrieves the headers from the request.
     *
     * This method attempts to get the request headers, handling both Apache and non-Apache environments.
     * It returns an associative array of headers with properly formatted keys.
     *
     * @return array The request headers.
     */
    public static function get_headers() {
        if (function_exists('apache_request_headers')) {
            // We need this to get the actual Authorization: header
            // because apache tends to tell us it doesn't exist.
            $in = apache_request_headers();
            $out = [];
            foreach ($in as $key => $value) {
                $key = str_replace(" ", "-", ucwords(strtolower(str_replace("-", " ", $key))));
                $out[$key] = $value;
            }
            return $out;
        }
        // Otherwise, we don't have Apache and are just going to have to hope
        // that $_SERVER actually contains what we need.
        $out = [];
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) == "HTTP_") {
                // This is chaos, basically it is just there to capitalize the first
                // letter of every word that is not an initial HTTP and strip HTTP
                // code from przemek.
                $key = str_replace(" ", "-", ucwords(strtolower(str_replace("_", " ", substr($key, 5)))));
                $out[$key] = $value;
            }
        }
        return $out;
    }

    // Helper to try to sort out headers for people who aren't running apache.

    /**
     * Parses a URL-encoded string of parameters into an associative array.
     *
     * This method takes a URL-encoded string of parameters (e.g., "a=b&a=c&d=e")
     * and returns an associative array where each key corresponds to a parameter name
     * and the value is either a single value or an array of values for that parameter.
     *
     * @param string $input The URL-encoded string of parameters.
     * @return array The parsed parameters as an associative array.
     */
    public static function parse_parameters($input) {
        if (!isset($input) || !$input) {
            return [];
        }

        $pairs = explode('&', $input);

        $parsedparameters = [];
        foreach ($pairs as $pair) {
            $split = explode('=', $pair, 2);
            $parameter = self::urldecode_rfc3986($split[0]);
            $value = isset($split[1]) ? self::urldecode_rfc3986($split[1]) : '';

            if (isset($parsedparameters[$parameter])) {
                // We have already recieved parameter(s) with this name, so add to the list
                // of parameters with this name.

                if (is_scalar($parsedparameters[$parameter])) {
                    // This is the first duplicate, so transform scalar (string) into an array
                    // so we can add the duplicates.
                    $parsedparameters[$parameter] = [
                        $parsedparameters[$parameter],
                    ];
                }

                $parsedparameters[$parameter][] = $value;
            } else {
                $parsedparameters[$parameter] = $value;
            }
        }
        return $parsedparameters;
    }

    /**
     * Builds an array from urlencoded parameters.
     *
     * This function takes an input like a=b&a=c&d=e and returns the parse parameters like this array('a' =>
     * array('b','c'), 'd' => 'e')
     *
     * @param string $params The parameters to build the query from.
     */
    public static function build_http_query($params) {
        if (!$params) {
            return '';
        }

        // Urlencode both keys and values.
        $keys = self::urlencode_rfc3986(array_keys($params));
        $values = self::urlencode_rfc3986(array_values($params));
        $params = array_combine($keys, $values);

        // Parameters are sorted by name, using lexicographical byte value ordering.
        // Ref: Spec: 9.1.1 (1).
        uksort($params, 'strcmp');

        $pairs = [];
        foreach ($params as $parameter => $value) {
            if (is_array($value)) {
                // If two or more parameters share the same name, they are sorted by their value
                // Ref: Spec: 9.1.1 (1).
                natsort($value);
                foreach ($value as $duplicatevalue) {
                    $pairs[] = $parameter . '=' . $duplicatevalue;
                }
            } else {
                $pairs[] = $parameter . '=' . $value;
            }
        }
        // For each parameter, the name is separated from the corresponding value by an '=' character (ASCII code 61)
        // Each name-value pair is separated by an '&' character (ASCII code 38).
        return implode('&', $pairs);
    }

    /**
     * URL-encodes a string according to RFC 3986.
     *
     * This method encodes the input string using the RFC 3986 encoding rules,
     * replacing spaces with '%20' and ensuring that reserved characters are
     * percent-encoded. It can also handle arrays of strings.
     *
     * @param mixed $input The input to encode, can be a string or an array of strings.
     * @return mixed The URL-encoded string or an array of encoded strings.
     */
    public static function urlencode_rfc3986($input) {
        if (is_array($input)) {
            return array_map([
                'moodle\mod\glaaster\GlaasterOAuthUtil',
                'urlencode_rfc3986',
            ], $input);
        } else {
            if (is_scalar($input)) {
                return str_replace('+', ' ', str_replace('%7E', '~', rawurlencode($input)));
            } else {
                return '';
            }
        }
    }
}
