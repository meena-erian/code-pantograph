<?php


/*
    Configurations
        $SECRET : This has to be the secret set for the Github WebHook and
            it's used by Github's Hookshot to sign the payload and then it's
            uesd here to verify that it's sent by the configured hook.
        
        $LOCAL_PATH : The local path to the directory on your http server to 
            which the code-pantograph will write. it can be an absolute realpath
            or a path relative to the location of the php script.
            
        $REPO_PATH : The path to the directory from which the code-pantograph
            will read, relative to the repository's main directory. For example,
            this will be src to synchronize all files in the src directory 
            
        $REPO_BRANCH : The branch from which code-pantograph will read. 
            Default: master.
            
        $USER_ACCESS_TOKEN : Github user access token. Used to let the script
            access the repositoryon behalf of a user. Use this only if the 
            repository from which code-pantograph will read is a private repository.
            See https://github.com/settings/tokens for more information
            Use the repo scope to allow the script to read private repositoies
*/

$SECRET = "********Github WebHook Signature's Secret*******";
$LOCAL_PATH = "..";
$REPO_PATH = "src";
$REPO_BRANCH = "master";
//$USER_ACCESS_TOKEN = "########################################";


define("DEBUG");



function fetch($link, $assoc = true){
    /*
        This function is an interface for the GitHub REST API. It takes a $link
        to a resource in the api.github.com and returns the JSON responce as an
        object or if $assoc is set as true, it returns an associative array.
        
        Note: if $USER_ACCESS_TOKEN is set, it uses it to access the resource on
        behalf of that user
    */
    global $USER_ACCESS_TOKEN;
    $stream_options = array(
	"http" => array(
			"method" => "GET",
			"header" => "User-Agent: code-pantograph\r\nAccept: application/json\r\n",
			"ignore_errors" => true
		)
	);
    
    if(isset($USER_ACCESS_TOKEN))
        $stream_options["http"]["header"] .= 
        "Authorization: token " . $USER_ACCESS_TOKEN . "\r\n";
    
	$response = json_decode( 
        file_get_contents(
	        $link,
		    false,
		    stream_context_create($stream_options)
        ),
        $assoc
	);
	return $response;
}

function file_force_contents($fname, $content){
    /*
        Same as file_put_contents except that it creates directories recursively
        if the directory in which the file to be written does not exist
    */
    $dn = dirname($fname);
    if(!is_dir($dn)){
        mkdir($dn, 0777, true);
    }
    file_put_contents($fname, $content);
}

if(isset($_SERVER['HTTP_X_HUB_SIGNATURE'])){
    /*
        The HTTP_X_HUB_SIGNATURE HTTP request header is required, it's sent by
        GitHub's Hookshot servers and it must be validated.
    */
    $hash = hash_hmac("sha1",file_get_contents("php://input") , $SECRET);
    $signature = explode("=", $_SERVER['HTTP_X_HUB_SIGNATURE'], 2)[1];
    if($hash != $signature){
        if(defined("DEBUG")){
            http_response_code(401);
            die("Invalid Signature");
        }
        die();
    }
    if($_SERVER["CONTENT_TYPE"] != "application/x-www-form-urlencoded"){
        http_response_code(400);
        die("Please Use mime type application/x-www-form-urlencoded");
    }
    if($_SERVER['HTTP_X_GITHUB_EVENT'] != "push" && $_SERVER['HTTP_X_GITHUB_EVENT'] != "ping"){
        http_response_code(404);
        die("This resource handles push events only");
    }
    $payload = json_decode($_POST['payload']);
    if($payload->ref != "refs/heads/" . $REPO_BRANCH){
        http_response_code(404);
        die("Wrong branch");
    }
    $repo = $payload->repository->full_name;
    $commits = $payload->commits;
    $msg = "";
    foreach($commits as $commit){
        foreach($commit->added as $fname){
            if(preg_match("/^".preg_quote($REPO_PATH)."/", $fname)){
                $local_file = $LOCAL_PATH . substr($fname, strlen($REPO_PATH));
                $msg .= "Adding: \"" . $fname . "\" as \"" . $local_file . "\"...";
                $data = fetch("https://api.github.com/repos/" . $repo . "/contents/" . $fname . "?ref=" . $REPO_BRANCH);
                if(isset($data["content"])){
                    file_force_contents($local_file, base64_decode($data["content"]));
                    $msg .= "Done!\n";
                }
                else {
                    http_response_code(500);
                    $msg .= "Failed!\n";
                }
            }
        }
        foreach($commit->removed as $fname){
            if(preg_match("/^".preg_quote($REPO_PATH)."/", $fname)){
                $local_file = $LOCAL_PATH . substr($fname, strlen($REPO_PATH));
                $msg .= "Removing: \"" . $fname . "\" as \"" . $local_file . "\"...";
                if(unlink($local_file)){
                    $msg .= "Done!\n";
                }
                else{
                    http_response_code(500);
                    $msg .= "Failed!\n";
                }
            }
        }
        foreach($commit->modified as $fname){ 
            if(preg_match("/^".preg_quote($REPO_PATH)."/", $fname)){
                $local_file = $LOCAL_PATH . substr($fname, strlen($REPO_PATH));
                $msg .= "Modifing: \"" . $fname . "\" as \"" . $local_file . "\"... ";
                $data = fetch("https://api.github.com/repos/" . $repo . "/contents/" . $fname . "?ref=" . $REPO_BRANCH);
                if(isset($data["content"])){
                    file_force_contents($local_file, base64_decode($data["content"]));
                    $msg .= "Done!\n";
                }
                else {
                    http_response_code(500);
                    $msg .= "Failed!\n";
                }
            }
        }
    }
    die($msg);
}
?>
