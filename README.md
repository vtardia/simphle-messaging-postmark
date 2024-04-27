# Postmark Transport Provider for Simphle Messaging

Postmark provider for [Simphle Messaging](https://github.com/vtardia/simphle-messaging) wrapping around the official [Postmark PHP client](https://github.com/ActiveCampaign/postmark-php).

## Install

```shell
composer require vtardia/simphle-messaging-postmark
```

## Usage

```php
use Simphle\Messaging\Email\Provider\PostmarkEmailProvider;

try {
    $message = /* Create a message here... */
    $mailer = new PostmarkEmailProvider(
        token: '<YourPostmarkAPIKey>',
        logger: /* optional PRS logger */,
        options: [/* see Postmark PHP docs*/] 
    );
    
    // Send the email
    $mailer->send($message /*, [more, options]*/);
} catch (InvalidMessageException $e) {
    // Do something...
} catch (EmailTransportException $e) {
    // Do something else...
}
```
