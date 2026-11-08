<?php

namespace App\Notifications;

/** Pemberitahuan untuk super admin: ada yang perlu diperiksa. */
class AdminAlert extends JajapoNotification
{
    protected bool $mail = true;

    public function __construct(
        private string $heading,
        private string $text,
        private string $link,
        private string $type = 'shop',
    ) {
        parent::__construct();
    }

    public function kind(): string
    {
        return $this->type;
    }

    public function title(): string
    {
        return $this->heading;
    }

    public function body(): string
    {
        return $this->text;
    }

    public function url(): string
    {
        return $this->link;
    }
}
