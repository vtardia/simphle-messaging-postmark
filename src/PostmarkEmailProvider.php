<?php

declare(strict_types=1);

namespace Simphle\Messaging\Email\Provider;

use Postmark\Models\PostmarkAttachment;
use Postmark\Models\PostmarkException;
use Postmark\PostmarkClient;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Simphle\Messaging\Email\EmailMessageInterface;
use Simphle\Messaging\Email\EmailMessageValidator;
use Simphle\Messaging\Email\Exception\EmailTransportException;

class PostmarkEmailProvider implements EmailProviderInterface
{
    use EmailMessageValidator;

    private PostmarkClient $mailer;

    public function __construct(
        private readonly string $token,
        private readonly int $timeout = 60,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly array $options = []
    ) {
        if (!class_exists(PostmarkClient::class)) {
            throw new RuntimeException('PostmarkClient is not installed on this system');
        }
        $this->mailer = new PostmarkClient($this->token, $this->timeout);
    }

    public function send(EmailMessageInterface $message, array $options = []): void
    {
        try {
            // Merging send options with global options
            $options = array_replace_recursive($this->options, $options);

            // Standard validation
            // Note: sender must be the valid sender signature from your Postmark account
            [$sender, $recipients, $subject, $html, $text] = $this->validate($message);

            $attachments = array_map(
                fn($a): PostmarkAttachment => PostmarkAttachment::fromFile(
                    filePath: $options['baseDir'] . DIRECTORY_SEPARATOR . $a->path,
                    attachmentName: $a->name,
                    contentId: $a->contentId
                ),
                $message->getAttachments()
            );

            $messages = [];
            foreach ($recipients as $recipient) {
                $messages[] = [
                    'From' => $sender->address, // Name will be populated by Postmark
                    'To' => $recipient->address,
                    'Cc' => implode(',', array_map(fn($cc) => $cc->address, $message->getCC())),
                    'Bcc' => implode(',', array_map(fn($bcc) => $bcc->address, $message->getBCC())),
                    'Subject' => $subject,
                    'HtmlBody' => $html,
                    'TextBody' => $text,
                    'Headers' => $options['headers'] ?? null,
                    'Attachments' => $attachments,
                    'Tag' => $options['tag'] ?? null,
                    'TrackOpens' => $options['trackOpens'] ?? false,
                    'TrackLinks' => $options['trackLinks'] ?? false,
                    'ReplyTo' => $message->getReplyTo()?->address,
                    'Metadata' => $options['metadata'] ?? null,
                    'MessageStream' => $options['messageStream'] ?? null
                ];
            }

            $responses = $this->mailer->sendEmailBatch($messages);
            foreach ($responses as $response) {
                $this->logger->info('Message sent with ID ' . $response->getMessageID());
            }
        } catch (PostmarkException $e) {
            $this->logger->error('[Postmark] Message could not be sent', [
                'error' => $e->getMessage(),
                'status' => $e->getHttpStatusCode(),
                'code' => $e->getPostmarkApiErrorCode(),
            ]);
            throw new EmailTransportException($e->getMessage());
        }
    }
}
