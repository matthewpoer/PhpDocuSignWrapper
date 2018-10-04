# php-docusign-wrapper
DocuSign offers a reliable and RESTful (although somewhat confusingly architected) v2 API. However, I found that the [official PHP SDK](https://github.com/docusign/docusign-php-client) to just *not work* out of the box. Tried with a few different versions of PHP, tried a few hosts. There are bugs with the namespaces and/or file/class structure. Or something. But the REST API was simple enough that it would be less work to work on a new wrapper than debug theirs.

This codebase will implement basic Auth., interaction with envelopes, recipients, forms and related data. My business goal is to organize the data returned via such forms. Methods outside of this goal will likely not be implemented, but if you want to enhance and share your work back feel free to throw up a pull request.

## Using Pest for REST
[Pest](https://github.com/educoder/pest) is a PHP client library for RESTful web services. I found it to be a minimal and effective back-bone for PhpDocuSignWrapper. The Pest dependency is handled by composer.

Thanks @Educoder!

## PHP 5.6 and Vagrant and DevBox
I chose to support 5.6 because it's still a valid and highly used option in production for a lot of the industry. If you're running the latest PHP 7, the 5.6 code will still function 100%.

I've found that the [DevBox Project by Damian Lewis ](https://github.com/damianlewis/devbox) is a great dev. environment for PHP development like this. A nice `Vagrantfile` might consist of

```
Vagrant.configure("2") do |config|
  config.vm.box = "damianlewis/ubuntu-16.04-lamp"
  config.vm.provision "shell",
    inline: 'update-alternatives --set php "/usr/bin/php5.6" > /dev/null'
end
```

Thanks, @Damian!

## Composer Install

```
composer require matthewpoer/php-docusign-wrapper:dev-master
```

## Authentication
While OAuth is supported by the DocuSign API, it's not implemented here. Instead we're using the [Legacy Header Authentication login method](https://developers.docusign.com/esign-rest-api/reference/Authentication/Authentication/login). Sorry about that.

### API Password
DocuSign does offer a solution to the cleartext password issue inherent here, and that is by providing us with an what the documentation calls the "apiPassword,"

> a token that can be used for authentication in API calls instead of using the user name and password

Okay, great. A Bash script is included here to get this API Password from DocuSign, just like this:

```
$ ./GetApiPassword.sh
Host? enter a subdomain, i.e. 'demo' or 'www'
www
Username:
user@host.tld
Password:
Integrator Key:
my-integrator-key
requesting API Password...
API Password Found:
"apiPassword": "Your Cool API Password"
```

## Sample Code
```
<?php
// maybe you define your connection info. in a config file?
require_once('config.php');

try {

  // invocation and auth.
  echo "Accessing DocuSign..." . PHP_EOL;
  $ds = new PhpDocuSignWrapper(
    DOCUSIGN_HOST,
    DOCUSIGN_EMAIL,
    DOCUSIGN_PASSWORD,
    DOCUSIGN_INTEGRATOR_KEY,
    DOCUSIGN_ACCOUNT_ID
  );

  // list all users
  echo "Preparing All User Retrieval..." . PHP_EOL;
  $users = $ds->get_users();
  $user_count = count($users);
  echo "Found {$user_count} Users:" . PHP_EOL;
  foreach($users as $userId => $userName) {
    echo "\t{$userName}" . PHP_EOL;
  }

  // list only the active users, also show their group associations
  echo "Preparing Active-Only User Retrieval..." . PHP_EOL;
  $users = $ds->get_users(TRUE);
  $user_count = count($users);
  echo "Found {$user_count} Users:" . PHP_EOL;
  foreach($users as $userId => $userName) {
    echo "\t{$userName} has the following groups:" . PHP_EOL;
    foreach($ds->get_user_groups($userId) as $group_name) {
      echo "\t\t-- {$group_name}" . PHP_EOL;
    }
  }

  // list all seen folders and their envelopes
  echo "Preparing folder list..." . PHP_EOL;
  $folders = $ds->get_folders();
  $folders_count = count($folders);
  echo "Found {$folders_count} Folders:" . PHP_EOL;
  foreach($folders as $folderId => $folderName) {
    echo "Contents of $folderName:" . PHP_EOL;
    $contents = $ds->get_folder_contents($folderId);
    if(empty($contents)) {
      echo "\t-- folder is empty --" . PHP_EOL;
    }
    foreach($contents as $envelopeId => $envelopeName) {
      echo "\t{$envelopeName}" . PHP_EOL;
    }
  }

} catch(\Exception $e) {
  $message = $e->getMessage();
  echo 'Error working with DocuSign. Exception: '
    . PHP_EOL
    . $message
    . PHP_EOL;
}

```

## A Note About Folders
The [DocuSign Folders:list API Endpoint](https://developers.docusign.com/esign-rest-api/reference/Folders/Folders/list) will, by default, only return a list of "Normal" (Envelope) Folders, i.e. Inbox, Sent Items, Deleted Items, etc. To get a list of _Template Folders_ we specify a GET param. `template`. The documentation tells us that `template`...

> Specifies the items that are returned. Valid values are:
> * include - The folder list will return normal folders plus template folders.
> * only - Only the list of template folders are returned.

I've found that the `only` value doesn't behave in accordance with these notes, and I can't tell where the folders `only` gives me live. To really get the Template Folders I would recommend fetching all Normal and Template folders with `include` and then removing any folders returned with no `template` param. specified (i.e. the Envelope Templates).

Example code below will find a set of Templates housed in a Folder based on knowing the Folder's Name:

```
$desired_folder_name = 'My Cool Folder Name';
$desired_folder_guid = NULL;
$all_folders = $ds->get_folders('include');
foreach($all_folders as $folder_id => $folder_name) {
  if($folder_name == $desired_folder_name) {
    $desired_folder_guid = $folder_id;
  }
}
$approved_templates = $templates = $ds->get_templates_in_folder($desired_folder_guid);
```
