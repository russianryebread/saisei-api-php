<?php
/*
   PHP library for Saisei API

   This library provides user friendly access to the REST API
   for Saisei.

   The library is built using PHP standard libraries.
   (no third party tools required, so it will run out of the box
   on most PHP environments).
   The wrapper functions return native PHP objects (arrays and objects),
   so working with them is easily done using built in functions.

    USAGE:

    Simply add saisei_lib.php to your PHP include path:

    require("saisei_lib.php");
    $s = new Saisei('your SaiSei URL');


    BASIC USE PATTERNS:

*/


class Saisei {

  /**
   * Instance URL
   *
   * @var string
   */
  private $url;
  private $port;
  private $username;
  private $password;

  const DEBUG = false;

  /**
   * Class constructor accepting the instance URL, port, username, and password
   *
   * @param string $url
   * @param string $username
   * @param string $password
   * @param int    $port
   */
  public function __construct($url, $username, $password, $port = 5029) {
    $this->url = $url;
    $this->username = $username;
    $this->password = $password;
    $this->port = $port;
  }


  /**
  * Gets a list of interfaces
  *
  * @param array $options
  * @return mixed
  * @link https://$url:$port/rest/top/configurations/running/interfaces
  */
  public function getInterfaces($options = null){
    return $this->GET("/rest/top/configurations/running/interfaces/")->asJSON();
  }

  /**
   * Gets an interface
   *
   * @param string $iface
   * @return mixed
   * @link  https://$url:$port/rest/top/configurations/running/interfaces/$iface
   */
  public function getInterface($iface){
    return $this->GET("/rest/top/configurations/running/interfaces/" . $iface)->asJSON();
  }



################################################################################
################################################################################

  /**
   * Create GET request
   *
   * @param string $url_path
   * @return SaiseiRequest
   */
  private function GET($url_path){
    return new SaiseiRequest("GET", $this->url, $this->port, $url_path, $this->username, $this->password);
  }
  /**
   * Create PUT request
   *
   * @param string $url_path
   * @return SaiseiRequest
   */
  private function PUT($url_path){
    return new SaiseiRequest("PUT", $this->url, $this->port, $url_path, $this->username, $this->password);
  }
  /**
   * Create POST request
   *
   * @param string $url_path
   * @return SaiseiRequest
   */
  private function POST($url_path){
    return new SaiseiRequest("POST", $this->url, $this->port, $url_path, $this->username, $this->password);
  }
  /**
   * Create DELETE request
   *
   * @param string $url_path
   * @return SaiseiRequest
   */
  private function DELETE($url_path){
    return new SaiseiRequest("DELETE", $this->url, $this->port, $url_path, $this->username, $this->password);
  }
}
  /**
   * API Requests class
   *
   * Helper class for executing REST requests to the Saisei API.
   *
   * Usage:
   * 	- Instantiate: $request = new SaiseiRequest('GET', 'create.../)
   *  - Execute: $request->toString();
   *  - Or implicitly execute: $request->asJSON();
   */
  class SaiseiRequest {

    private $curl;
    private $url;
    private $port;
    private $username;
    private $password;
    private $url_path;

    /**
     * Request headers
     *
     * @var array
     */
    private $headers;

    /**
     * Request parameters
     *
     * @var array
     */
    private $querystrings;

    /**
     * Response body
     *
     * @var string
     */
    private $body;
    /**
     * Request initialisation
     *
     * @param string $method (GET|DELETE|POST|PUT)
     * @param string $apikey
     * @param string $url_path
     * @throws Exception
     */
    function __construct($method, $url, $port, $url_path, $username, $password){
      $this->curl = curl_init();
      $this->url = $url;
      $this->port = $port;
      $this->url_path = $url_path;
      $this->username = $username;
      $this->password = $password;
      $this->querystrings = array();
      $this->body = null;
      switch($method){
      case "GET":
        // GET is the default
        break;
      case "DELETE":
        $this->method("DELETE");
        break;
      case "POST":
        $this->method("POST");
        break;
      case "PUT":
        $this->method("PUT");
        break;
      default: throw new Exception('Invalid HTTP method: ' . $method);
      }
      // Have curl return the response, rather than echoing it
      curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);

      // Set up authentication.
      curl_setopt($this->curl, CURLOPT_USERPWD, $this->username . ":" . $this->password);

      // This may be useful for debugging
      if (Saisei::DEBUG == true) {
        curl_setopt($this->curl, CURLOPT_VERBOSE, true);
        $this->verbose = fopen('php://temp', 'w+');
        curl_setopt($this->curl, CURLOPT_STDERR, $this->verbose);
      }

      // Just assume that it's the right box, and ignore invalid SSL.
      curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
    }

    /**
     * Get executed request response
     *
     * @throws Exception
     * @return string
     */
    public function asJSON(){

      $url =  "https://" . $this->url . ':' . $this->port . $this->url_path . $this->buildQueryString();
      curl_setopt($this->curl, CURLOPT_URL, $url);
      curl_setopt($this->curl, CURLOPT_HTTPHEADER, $this->headers);

      $response = curl_exec($this->curl);
      $errno = curl_errno($this->curl);

      if (Saisei::DEBUG == true) {
        if ($response === FALSE) {
            printf("cUrl error (#%d): %s<br>\n", curl_errno($this->curl),
                   htmlspecialchars(curl_error($this->curl)));
        }

        rewind($this->verbose);
        $verboseLog = stream_get_contents($this->verbose);

        echo "Verbose information:\n<pre>", htmlspecialchars($verboseLog), "</pre>\n";
        echo "Error: " . $errorno;
        echo "Info: ";
        print_r (curl_getinfo($this->curl));

      }


      if($errno != 0){
        throw new Exception("HTTP Error (" . $errno . "): " . curl_error($this->curl));
      }
      $status_code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
      if(!($status_code == 200 || $status_code == 201 || $status_code == 202)){
        throw new Exception("Bad HTTP status code: " . $status_code);
      }
      return $response;
    }
    /**
     * Return decoded JSON response
     *
     * @throws Exception
     * @return mixed
     */
    public function asJSON(){
      $data = json_decode($this->asString());
      $errno = json_last_error();
      if($errno != JSON_ERROR_NONE){
        throw new Exception("Error encountered decoding JSON: " . json_last_error_msg());
      }
      return $data;
    }
    /**
     * Add data to the current request
     *
     * @param mixed $obj
     * @throws Exception
     * @return SaiseiRequest
     */
    public function body($obj){
      $data = json_encode($obj);
      $errno = json_last_error();
      if($errno != JSON_ERROR_NONE){
        throw new Exception("Error encountered encoding JSON: " . json_last_error_message());
      }
      curl_setopt($this->curl, CURLOPT_POSTFIELDS, $data);
      $this->headers[] = "Content-Type: application/json";
      return $this;
    }
    /**
     * Set request method
     *
     * @param string $method
     * @return SaiseiRequest
     */
    private function method($method){
      curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $method);
      return $this;
    }
    /**
     * Add query parameter to the current request
     *
     * @param string $name
     * @param mixed $value
     * @return SaiseiRequest
     */
    public function queryParam($name, $value){
      // build the query string for this name/value pair
      $querystring = http_build_query(array($name => $value));
      // append it to the list of query strings
      $this->querystrings[] = $querystring;
      return $this;
    }
    /**
     * Build query string for the current request
     *
     * @return string
     */
    private function buildQueryString(){
      if(count($this->querystrings) == 0){
        return "";
      }
      else{
        $querystring = "?";
        foreach($this->querystrings as $index => $value){
          if($index > 0){
            $querystring .= "&";
          }
          $querystring .= $value;
        }
        return $querystring;
      }
    }
  }
