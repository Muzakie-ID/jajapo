<?php

namespace App\Notifications;

/** Pemberitahuan untuk super admin: ada yang perlu diperiksa. */
class AdminAlert extends JajapoNotification
{
    protected bool $mail = true;

    /**
     * @param  array<string, ?string>  $details  rincian di email, baris kosong dilewati
     * @param  array{label: string, value: string, note?: string}|null  $highlight
     * @param  string|null  $intro  pembuka email; bawaannya sama dengan teks lonceng
     */
    public function __construct(
        private string $heading,
        private string $text,
        private string $link,
        private string $type = 'shop',
        private array $details = [],
        private ?array $highlight = null,
        private ?string $intro = null,
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

    protected function mailIntro(): string
    {
        return $this->intro ?? $this->text;
    }

    protected function mailDetails(): array
    {
        return array_filter($this->details, fn ($v) => filled($v));
    }

    protected function mailHighlight(): ?array
    {
        return $this->highlight;
    }

    protected function mailAction(): string
    {
        return 'Periksa sekarang';
    }
}
