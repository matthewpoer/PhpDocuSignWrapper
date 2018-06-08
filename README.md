# php-docusign-wrapper
DocuSign offers a reliable and RESTful (although somewhat confusingly architected) v2 API. However, I found that the [official PHP SDK](https://github.com/docusign/docusign-php-client) to just *not work* out of the box. Tried with a few different versions of PHP, tried a few hosts. There are bugs with the namespaces and/or file/class structure. Or something. But the REST API was simple enough that it would be less work to work on a new wrapper than debug. theirs.

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

## Sample Code
```
<?php
// maybe you define your connection info. in a config file?
require_once('config.php');

// invoke the class to handle login
require_once('vendor/autoload.php');
$ds = new PhpDocuSignWrapper(
  DOCUSIGN_HOST, DOCUSIGN_EMAIL, DOCUSIGN_PASSWORD, DOCUSIGN_INTEGRATOR_KEY
);

// build up a list all envelopes
$envelopes = $ds->get_envelopes();

// build list of recipients on to all envelopes
foreach($envelopes as $envelope_id => $envelope) {
  $envelopes[$envelope_id] = $ds->get_recipients_for_envelope($envelope_id);
}

// build recipients and their form data for all envelopes
foreach($envelopes as $envelope_id => $envelope_recipients) {
  foreach($envelope_recipients as $envelope_recipient_id => $envelope_recipient) {
    $field_data = $ds->get_tabs_for_recipient_for_envelope($envelope_id, $envelope_recipient_id);
    $envelopes[$envelope_id][$envelope_recipient_id] = $field_data;
  }
}

// dump results from the first envelope we found
var_dump(array_shift($envelopes));
```
