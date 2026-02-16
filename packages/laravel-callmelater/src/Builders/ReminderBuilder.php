<?php

namespace CallMeLater\Laravel\Builders;

use CallMeLater\Laravel\CallMeLater;
use CallMeLater\Laravel\Exceptions\CallMeLaterException;
use Carbon\Carbon;
use DateTimeInterface;

class ReminderBuilder
{
    protected CallMeLater $client;

    protected string $name;

    protected array $recipients = [];

    protected ?string $message = null;

    protected ?string $idempotencyKey = null;

    protected ?string $timezone = null;

    protected array $intent = [];

    protected array $gate = [];

    protected ?string $callbackUrl = null;

    protected array $metadata = [];

    public function __construct(CallMeLater $client, string $name)
    {
        $this->client = $client;
        $this->name = $name;
        $this->timezone = $client->getTimezone();
    }

    /**
     * Add a recipient by email.
     */
    public function to(string $email): self
    {
        $this->recipients[] = "email:{$email}";

        return $this;
    }

    /**
     * Add multiple email recipients.
     */
    public function toMany(array $emails): self
    {
        foreach ($emails as $email) {
            $this->to($email);
        }

        return $this;
    }

    /**
     * Add a recipient by phone number (SMS).
     */
    public function toPhone(string $phone): self
    {
        $this->recipients[] = "phone:{$phone}";

        return $this;
    }

    /**
     * Add a raw recipient URI.
     */
    public function toRecipient(string $recipientUri): self
    {
        $this->recipients[] = $recipientUri;

        return $this;
    }

    /**
     * Add a channel as recipient.
     */
    public function toChannel(string $channelUuid): self
    {
        $this->recipients[] = "channel:{$channelUuid}";

        return $this;
    }

    /**
     * Set the reminder message.
     */
    public function message(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    /**
     * Set an idempotency key.
     */
    public function idempotencyKey(string $key): self
    {
        $this->idempotencyKey = $key;

        return $this;
    }

    /**
     * Set the timezone.
     */
    public function timezone(string $timezone): self
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * Schedule at a specific time.
     */
    public function at(DateTimeInterface|Carbon|string $time): self
    {
        if ($time instanceof DateTimeInterface) {
            $this->intent['type'] = 'datetime';
            $this->intent['value'] = $time->format('Y-m-d H:i:s');
        } elseif (is_string($time)) {
            $presets = ['tomorrow', 'next_week', 'next_monday', 'next_tuesday',
                'next_wednesday', 'next_thursday', 'next_friday', 'end_of_day',
                'end_of_week', 'end_of_month'];

            if (in_array($time, $presets)) {
                $this->intent['type'] = 'preset';
                $this->intent['value'] = $time;
            } else {
                $this->intent['type'] = 'datetime';
                $this->intent['value'] = $time;
            }
        }

        return $this;
    }

    /**
     * Schedule after a delay.
     */
    public function delay(int $amount, string $unit = 'minutes'): self
    {
        $this->intent['type'] = 'relative';
        $this->intent['value'] = $amount;
        $this->intent['unit'] = $unit;

        return $this;
    }

    /**
     * Schedule in X minutes.
     */
    public function inMinutes(int $minutes): self
    {
        return $this->delay($minutes, 'minutes');
    }

    /**
     * Schedule in X hours.
     */
    public function inHours(int $hours): self
    {
        return $this->delay($hours, 'hours');
    }

    /**
     * Schedule in X days.
     */
    public function inDays(int $days): self
    {
        return $this->delay($days, 'days');
    }

    /**
     * Set the confirm button text.
     */
    public function confirmButton(string $text): self
    {
        $this->gate['confirm_text'] = $text;

        return $this;
    }

    /**
     * Set the decline button text.
     */
    public function declineButton(string $text): self
    {
        $this->gate['decline_text'] = $text;

        return $this;
    }

    /**
     * Set both button texts.
     */
    public function buttons(string $confirm, string $decline): self
    {
        $this->gate['confirm_text'] = $confirm;
        $this->gate['decline_text'] = $decline;

        return $this;
    }

    /**
     * Allow snoozing with max count.
     */
    public function allowSnooze(int $maxSnoozes = 5): self
    {
        $this->gate['max_snoozes'] = $maxSnoozes;

        return $this;
    }

    /**
     * Disable snoozing.
     */
    public function noSnooze(): self
    {
        $this->gate['max_snoozes'] = 0;

        return $this;
    }

    /**
     * Set token expiry in days.
     */
    public function expiresInDays(int $days): self
    {
        $this->gate['token_expiry_days'] = $days;

        return $this;
    }

    /**
     * Require all recipients to respond.
     */
    public function requireAll(): self
    {
        $this->gate['confirmation_mode'] = 'all_required';

        return $this;
    }

    /**
     * Complete on first response.
     */
    public function firstResponse(): self
    {
        $this->gate['confirmation_mode'] = 'first_response';

        return $this;
    }

    /**
     * Add escalation contacts.
     */
    public function escalateTo(array $contacts, int $afterHours = 24): self
    {
        $this->gate['escalation'] = [
            'contacts' => array_map(function ($contact) {
                return str_contains($contact, ':') ? $contact : "email:{$contact}";
            }, $contacts),
            'after_hours' => $afterHours,
        ];

        return $this;
    }

    /**
     * Add URL attachments.
     */
    public function attach(string $url, ?string $name = null): self
    {
        if (! isset($this->gate['attachments'])) {
            $this->gate['attachments'] = [];
        }

        $attachment = ['url' => $url];
        if ($name) {
            $attachment['name'] = $name;
        }

        $this->gate['attachments'][] = $attachment;

        return $this;
    }

    /**
     * Set a callback URL for response notifications.
     */
    public function callback(string $url): self
    {
        $this->callbackUrl = $url;

        return $this;
    }

    /**
     * Alias for callback().
     */
    public function onResponse(string $url): self
    {
        return $this->callback($url);
    }

    /**
     * Add metadata.
     */
    public function metadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);

        return $this;
    }

    /**
     * Add a single metadata key.
     */
    public function meta(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    /**
     * Get the payload array that would be sent to the API.
     */
    public function toArray(): array
    {
        if (empty($this->recipients)) {
            throw new CallMeLaterException('At least one recipient is required');
        }

        $payload = [
            'mode' => 'gated',
            'name' => $this->name,
            'gate' => [
                'recipients' => $this->recipients,
            ],
        ];

        if ($this->message) {
            $payload['gate']['message'] = $this->message;
        }

        // Merge additional gate options
        if (! empty($this->gate)) {
            $payload['gate'] = array_merge($payload['gate'], $this->gate);
        }

        if ($this->idempotencyKey) {
            $payload['idempotency_key'] = $this->idempotencyKey;
        }

        if (! empty($this->intent)) {
            $payload['intent'] = $this->buildIntent();
            if ($this->timezone) {
                $payload['intent']['timezone'] = $this->timezone;
            }
        }

        if ($this->callbackUrl) {
            $payload['callback_url'] = $this->callbackUrl;
        }

        if (! empty($this->metadata)) {
            $payload['metadata'] = $this->metadata;
        }

        return $payload;
    }

    /**
     * Dump the payload and die (for debugging).
     */
    public function dd(): never
    {
        dd($this->toArray());
    }

    /**
     * Send the reminder to CallMeLater.
     */
    public function send(): array
    {
        return $this->client->sendAction($this->toArray());
    }

    /**
     * Alias for send().
     */
    public function dispatch(): array
    {
        return $this->send();
    }

    /**
     * Convert the SDK intent format to the API intent format.
     */
    protected function buildIntent(): array
    {
        $type = $this->intent['type'] ?? null;

        if ($type === 'relative') {
            $value = $this->intent['value'] ?? 0;
            $unit = $this->intent['unit'] ?? 'minutes';

            return ['delay' => "{$value} {$unit}"];
        }

        if ($type === 'preset') {
            return ['preset' => $this->intent['value']];
        }

        if ($type === 'datetime') {
            return ['at' => $this->intent['value']];
        }

        return $this->intent;
    }
}
