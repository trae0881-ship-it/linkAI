<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\WorkspaceInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class WorkspaceInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly WorkspaceInvitation $invitation,
        private readonly string $rawToken,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url', config('app.url')), '/')
            .'/invitations/accept?token='.$this->rawToken
            .'&email='.urlencode($this->invitation->email);

        return (new MailMessage)
            ->subject("You've been invited to join {$this->invitation->workspace->name} on Link2Social AI")
            ->line("You've been invited to join **{$this->invitation->workspace->name}** as {$this->invitation->role}.")
            ->action('Accept Invitation', $url)
            ->line('This invitation link expires in 7 days.');
    }
}
