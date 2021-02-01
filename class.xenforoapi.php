<?php 
/**
 * XenForo Authentication
 * A class to handle login from outside of Xenforo to pull in data from Xenforo API
 * 
 * Basicly a class to interact with the Xenforo API.  Has a class to Curl into the API 
 *
 * Documentation: https://xenforo.com/community/pages/api-endpoints/
 * 
 * VAR $api         STRING  The api location.
 * VAR $url         STRING  Where the user is sent after login.
 * VAR $userid      STRING  User ID for sending in the CURL head when making specific user requests.
 * VAR $loggedin    BOOL    Is the user logged in, controls visibility of form states
 * VAR $check       BOOL    Test for if it is the check server or not.
 * VAR $xfApiKey    STRING  the API Key from Xenforo.
 * VAR $xenSession  STRING  the session id from the cookie
 * VAR $error       OBJECT  A place for the error to live. 
 *
 *
 * func _sanitize 
 *   returns sanitized inputs. Takes a string.
 *
 * func _handleLogin 
 *   handles the $_POST request from the login form, creates a token and a new logged in session. Get's details from the $_POST login form. 
 *
 * func _getSession 
 *   if already logged in sets up everything by getting the user details from the active session.
 *
 * func xen_curl 
 *   a place to interact with the xenforo API. Returns an JSON string full of awesome data or an error.  
 *   Takes some arguments for query, endpoint and method.
 *
 * func loginBox
 *   sets up the display of the login box and handles errors inside that
 *
 * func welcomeBox 
 *   Displays the user logged in with some notifications.  Curl inside to check and show any notifications avalible. 
 *
 * func ui 
 *   What is called in the front end to display the Login UI
 *
 */
class XenforoAPI
{

    private $api;
    private $url;
    private $userid;
    private $loggedin;
    private $check;
    private $xfApiKey;
    private $xenSession;
    private $error;
    private $email;

    public function __construct() 
    {
        $cookiePrefix       = "<Xenforo Cookie Prefix>"
        $this->url          = "<Return after login URL>";
        $this->api          = "<API URL>";
        $this->apiKey       = "<API KEY HERE>";
        $this->email        = "<YOUR EMAIL FOR ERRORS>";
        $this->xenSession   = (isset($_COOKIE[$cookiePrefix.'session'])) ? $_COOKIE[$cookiePrefix.'session'] : false;
        $this->check        = false;
        $this->userid       = false;
        $this->loggedin     = false;

        $this->_handleLogin();
        $this->_getSession();
        
    }
    
    private function _sanitize($string) 
    {

        $string = trim($string);
        $string = strip_tags($string);

        return $string;

    }
    
    private function _handleLogin() 
    {
        
        if(isset($_POST['login'])) {

            $username = $this->_sanitize($_POST['username']);
            $password = $this->_sanitize($_POST['password']);

            $login = $this->xen_curl(["login" => $username, "password" => $password], "POST", "auth");

            $login = json_decode($login);

            if(isset($login->user->user_id)) {

                $token = $this->xen_curl(["user_id" => $login->user->user_id], "POST", "auth/login-token");

                $token = json_decode($token);

                header("Location: " . $token->login_url . "&return_url=".urlencode($this->url));
            
            } else {
            
                $this->error = (is_array($login->errors)) ? $login->errors : ["undefined"];
            
            }

        }
        
    }

    private function _getSession() 
    {

        if($this->xenSession) {

            $user = $this->xen_curl(["session_id" => $this->xenSession], "POST", "auth/from-session");

            $user = json_decode($user);

            if($user->user->user_id != 0) {
                
                $this->username = $user->user->username;
                $this->userid = $user->user->user_id;
                $this->loggedin = true;

            }

        }

    }

    private function xen_curl($query = [], $method = "GET", $endpoint = "/") 
    {

        $postfields = http_build_query($query);

        $curl = curl_init();

        $args = array(
            CURLOPT_URL => $this->api . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $postfields,
            CURLOPT_HTTPHEADER => array(
                'XF-Api-Key: '.$this->apiKey,
                'Content-Type: application/x-www-form-urlencoded'
            ),
        );
        
        if($this->check)
            $args[CURLOPT_HTTPHEADER][] = 'Authorization: Basic Z3Vlc3Q6d2hpdGVyYWJiaXQxNCE=';
        
        if($this->userid)
            $args[CURLOPT_HTTPHEADER][] = 'XF-Api-User: '.$this->userid;

        curl_setopt_array($curl, $args);

        $responce = curl_exec($curl);

        curl_close($curl);

        $errors = json_decode($responce);

        $server = ($this->check) ? "Check" : "Live";

        if(isset($errors->errors)) {

            $r = "Error Report from {$_SERVER['HTTP_HOST']}:" . "\n";

            foreach($errors->errors as $v){
                $r .= "Code: ". $v->code . "\n";
                $r .= "Message: ". $v->message . "\n";
                $r .= "-------------------------------------" . "\n";
            }


            mail($this->email, "Xenforo API Error on " .$server, $r);

        }

        return $responce;

    }

    private function loginBox($error) 
    {

        $pErrorClass = "";
        $pErrorMessage = "";
        $uErrorClass = "";
        $showBox = "";

        if($error) {

            $showBox = <<<EOT
            <script type="text/javascript">
                $(window).on('load', function() {
                    $('#loginModal').modal({
                        show: true,
                        backdrop: false
                    });
                });
            </script>
EOT;
            
            foreach($error as $v) {
            
                if($v->code == "incorrect_password")
                    $pErrorClass = "is-invalid";
                
                
                if($v->code == "requested_user_x_not_found")
                    $uErrorClass = "is-invalid";
                
                $pErrorMessage = "<div class='invalid-feedback'>".$v->message."</div>";
            
            }
            
        }

        return <<<EOT
        
        <div class="d-flex m-1">
            <button type="button" class="btn btn-link text-white login" data-backdrop="false" data-toggle="modal" data-target="#loginModal">
            <i class="fa fa-sign-in" aria-hidden="true"></i>
            <span class='sr-only'>Login</span>
            </button>
        <div>
        
        <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="loginModalLabel">Sign In</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="">
                        <input type="text" value="" name="username" class="form-control form-control-sm m-1 $pErrorClass"/>
                        <input type="password" value="" name="password" class="form-control form-control-sm m-1 $uErrorClass"/>
                        <input type="submit" value="Login" name="login" class="btn btn-sm btn-primary m-1" /> or <a href="/forums/login/register">Register Now</a>
                        $pErrorMessage
                    </form>
                </div>
                <div class="modal-footer d-flex justify-content-center">
                    <div>Or Login Using:</div>
                    <div class="btn-group" role="group" aria-label="Basic example">
                        <a href="/forums/register/connected-accounts/facebook/?setup=1" class="btn btn-secondary text-white"><i class="fa fa-facebook" aria-hidden="true"></i> Facebook</a>
                        <a href="/forums/register/connected-accounts/google/?setup=1" class="btn btn-secondary text-white"><i class="fa fa-google" aria-hidden="true"></i> Google</a>
                        <a href="/forums/register/connected-accounts/linkedin/?setup=1" class="btn btn-secondary text-white"><i class="fa fa-linkedin" aria-hidden="true"></i> LinkedIn</a>
                    </div>
                </div>
                </div>
            </div>
        </div>
    
        $showBox

EOT;

    }
    

    private function welcomeBox() 
    {

        $alerts = $this->xen_curl(["unread" => true], "GET", "alerts");
        $alerts = json_decode($alerts);
        
        $alerts = array_filter($alerts->alerts, function($k){
            
            return $k->view_date == 0;
            
        });
        
        $number = (count($alerts) != 0) ? "<span class='badge badge-danger'>".count($alerts)."</span>"."\n"."<span class='sr-only'>unread messages</span>" : "";

        return <<<EOT

        <div class="d-flex m-1">
            <a href="/forums/account/" class="btn btn-link text-white">
            <i class="fa fa-user-circle-o" aria-hidden="true"></i>
            <span class='sr-only'>{$this->username}</span>
            $number
            </a>
        </div>  

EOT;

    }

    public function ui() 
    {

        echo "<div class='align-self-end'>";

        if(!$this->loggedin) {

            echo $this->loginBox($this->error);

        } else {

            echo $this->welcomeBox();

        }

        echo "</div>";

    }

}
