# code-pantograph
Use this PHP script to synchronize a folder on an HTTP server with a folder in a Github repository. Such that any commits or puch events on the files on the specified folder in the specified repository will directly take effect on its remote copy in the configured http server.

# Setup Requirments
 * You have to be a contributor in the specified repository because you will need to create a Github webhook
 * If the specified repository is private, you will have to configure code pantograph with an access token to allow it to access the repository on your behalf (or on behalf of a github user who has read access to the repository)
 * A publicly available HTTP server with PHP support to host the script and in case of synchronizing a private repository, it must be configured with secure HTTPS (SSL/TLS) because you definitely don't want your access toke reaching the wrong hands.
 
Note: The HTTP server has to be online 24/7 or whenever a change occurs in the synchronized code because if the push notification was sent while your server was down, the Hookshot will NOT try again later.

# Getting Started

The below variables has to be configured in `pantograph.php`
```php
$SECRET = "********Github WebHook Signature's Secret*******";
$LOCAL_PATH = ".."; // The path to the directory on you http server to which 
$REPO_PATH = "src";
$REPO_BRANCH = "master";
//$USER_ACCESS_TOKEN = "########################################";
```
`$SECRET` : This has to be the secret set for the Github WebHook and
            it's used by Github's Hookshot to sign the payload and then it's
            uesd here to verify that it's sent by the configured hook.
            Better use a long complex code because that's not a password, you 
            will not need to remember it!
        
`$LOCAL_PATH` : The local path to the directory on your http server to 
            which the code-pantograph will write. it can be an absolute realpath
            or a path relative to the location of the php script.
            
`$REPO_PATH` : The path to the directory from which the code-pantograph
            will read, relative to the repository's main directory. For example,
            this will be src to synchronize all files in the src directory 
            
`$REPO_BRANCH` : The branch from which code-pantograph will read. 
            Default: master.
            
`$USER_ACCESS_TOKEN` : Github user access token. Used to let the script
            access the repositoryon behalf of a user. Use this only if the 
            repository from which code-pantograph will read is a private repository.
            See https://github.com/settings/tokens for more information
            Use the **repo** scope to allow the script to read private repositoies
            
After configuring `pantograph.php`, 
1. Save it on your HTTP server so that the Github hook shot can send POST requests to it.
2. Add a new Webhook to your Github repository (from the repository **Settings** tab, the **Webhooks** tab on the left)
3. Configure the new webhook with the same $SECRET configured in `pantograph.php` and the **Payload URL** is, of course, the HTTP link to `pantograph.php` on your HTTP host. and leave the rest of the webhook setting as its default configurations.

# How it Works
Whenever any changes occur in the specified repository:
1. Github's HookShot sends notification Git **push** event to pantograph.php, in which it specifies what files has changed in this event. 
2. `pantograph.php` validates that this is a valid notification and signed by Github with the Hmac signature with the same secret set as set in the hook's settings and then it filters out any files lying outside the specified directory.
3. `pantograph.php` makes GET calls to the Github API to fetch the new files and then it writes, overwrites, or deletes their alternative copies on the local folder of the HTTP server.

Note: Just like a pantograph, it only writes the **changes** and it doesn't verify that the current local copy is the same as in the Github repository. 
